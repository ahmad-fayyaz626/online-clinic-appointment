<?php

final class QueueRuleViolation extends RuntimeException {}

final class ClinicQueue
{
    private const NO_SHOW_GRACE_MINUTES = 15;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function token(int $id): string
    {
        return 'CP-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }

    public function doctorId(int $userId): int
    {
        return (int)$this->query('SELECT id FROM doctors WHERE user_id = ?', [$userId])->fetchColumn();
    }

    public function book(int $patientId, int $doctorId, string $date, string $time, string $reason): void
    {
        $this->transaction(function () use ($patientId, $doctorId, $date, $time, $reason) {
            // Booking takes the doctor lock first, then the patient lock, before checking either slot.
            $this->lockDoctor($doctorId);
            $this->query('SELECT id FROM users WHERE id = ? FOR UPDATE', [$patientId])->fetchColumn();
            if ($this->slotTime($date, $time) <= new DateTimeImmutable()) {
                throw new QueueRuleViolation('Choose a time slot that is still upcoming.');
            }
            if ($this->query(
                "SELECT id FROM appointments WHERE patient_id = ? AND appointment_date = ?
                 AND appointment_time = ? AND status IN ('booked', 'completed') LIMIT 1",
                [$patientId, $date, $time]
            )->fetchColumn()) {
                throw new QueueRuleViolation('You already have an appointment at this time.');
            }
            if ($this->query(
                "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ?
                 AND appointment_time = ? AND status IN ('booked', 'completed') LIMIT 1",
                [$doctorId, $date, $time]
            )->fetchColumn()) {
                throw new QueueRuleViolation('This time slot has already been taken. Please choose another.');
            }
            $this->query(
                'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason)
                 VALUES (?, ?, ?, ?, ?)',
                [$patientId, $doctorId, $date, $time, $reason]
            );
        });
    }

    public function checkIn(string $token, int $adminId): string
    {
        $token = strtoupper(trim($token));
        if (!preg_match('/^CP-([0-9]{6,10})$/D', $token, $parts)
            || (int)$parts[1] > 2147483647 || self::token((int)$parts[1]) !== $token) {
            throw new QueueRuleViolation('Enter the appointment token shown on the patient’s slip, such as CP-000123.');
        }

        return $this->changeAppointment((int)$parts[1], 'admin', $adminId, function (array $visit) use ($token, $adminId) {
            $this->requireBooked($visit);
            if ($visit['appointment_date'] !== date('Y-m-d')) {
                throw new QueueRuleViolation('Check-in is available on the appointment date.');
            }
            if ($visit['checked_in_at'] !== null) {
                return $token . ' is already checked in.';
            }
            $this->query(
                'UPDATE appointments SET checked_in_at = ?, checked_in_by = ? WHERE id = ?',
                [$this->utcNow(), $adminId, $visit['id']]
            );
            $this->recordReceptionEvent((int)$visit['id'], $adminId, 'check_in');
            return $token . ' checked in.';
        });
    }

    public function correctReception(int $id, int $adminId, string $action, int $expectedEvent): void
    {
        $this->changeAppointment($id, 'admin', $adminId, function (array $visit) use ($adminId, $action, $expectedEvent) {
            $now = new DateTimeImmutable();
            if ($visit['appointment_date'] !== $now->format('Y-m-d') || $visit['consultation_started_at'] !== null) {
                throw new QueueRuleViolation('Reception can correct today’s visits before a consultation starts.');
            }
            $latestEvent = (int)$this->query(
                'SELECT id FROM appointment_queue_events WHERE appointment_id = ? ORDER BY id DESC LIMIT 1',
                [$visit['id']]
            )->fetchColumn();
            if ($latestEvent !== $expectedEvent) {
                throw new QueueRuleViolation('This appointment changed. Review it before trying again.');
            }

            switch ($action) {
                case 'undo_check_in':
                    $this->requireBooked($visit);
                    if ($visit['checked_in_at'] === null) {
                        throw new QueueRuleViolation('This patient is already awaiting check-in.');
                    }
                    $this->query(
                        'UPDATE appointments SET checked_in_at = NULL, checked_in_by = NULL WHERE id = ?',
                        [$visit['id']]
                    );
                    break;
                case 'no_show':
                    $this->requireBooked($visit);
                    if (!$this->canMarkNoShow($visit, $now)) {
                        throw new QueueRuleViolation('Mark an appointment as missed once it is 15 minutes overdue and the patient has not checked in.');
                    }
                    $this->query("UPDATE appointments SET status = 'no_show' WHERE id = ?", [$visit['id']]);
                    break;
                case 'restore':
                    if ($visit['status'] !== 'no_show') {
                        throw new QueueRuleViolation('Only a missed visit can be restored here.');
                    }
                    $this->query("UPDATE appointments SET status = 'booked' WHERE id = ?", [$visit['id']]);
                    break;
                default:
                    throw new QueueRuleViolation('Unknown reception action.');
            }
            $this->recordReceptionEvent((int)$visit['id'], $adminId, $action);
        });
    }

    public function receptionVisits(): array
    {
        $now = new DateTimeImmutable();
        $visits = $this->query(
            "SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.checked_in_at,
                    p.full_name AS patient_name, u.full_name AS doctor_name,
                    COALESCE((SELECT MAX(id) FROM appointment_queue_events WHERE appointment_id = a.id), 0) AS event_id
             FROM appointments a JOIN users p ON p.id = a.patient_id
             JOIN doctors d ON d.id = a.doctor_id JOIN users u ON u.id = d.user_id
             WHERE a.appointment_date = ? AND a.status IN ('booked', 'no_show')
               AND a.consultation_started_at IS NULL
             ORDER BY a.appointment_time, a.id",
            [$now->format('Y-m-d')]
        )->fetchAll();
        foreach ($visits as &$visit) {
            if ($visit['status'] === 'no_show') {
                $visit['action'] = 'restore';
            } elseif ($visit['checked_in_at'] !== null) {
                $visit['action'] = 'undo_check_in';
            } elseif ($this->canMarkNoShow($visit, $now)) {
                $visit['action'] = 'no_show';
            } else {
                $visit['action'] = 'check_in';
            }
        }
        return $visits;
    }

    private function canMarkNoShow(array $visit, DateTimeImmutable $now): bool
    {
        return $visit['checked_in_at'] === null
            && $this->slotTime($visit['appointment_date'], $visit['appointment_time'])
                ->modify('+' . self::NO_SHOW_GRACE_MINUTES . ' minutes') <= $now;
    }

    private function recordReceptionEvent(int $id, int $actorId, string $event): void
    {
        $this->query(
            'INSERT INTO appointment_queue_events (appointment_id, actor_id, event_type, occurred_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(6))',
            [$id, $actorId, $event]
        );
    }

    public function start(int $appointmentId, int $doctorId): void
    {
        $this->changeAppointment($appointmentId, 'doctor', $doctorId, function (array $visit) use ($doctorId) {
            if ($visit['status'] === 'booked' && $visit['consultation_started_at'] !== null) {
                return;
            }
            $this->requireBooked($visit);
            if ($this->query('SELECT id FROM appointments WHERE active_doctor_id = ?', [$doctorId])->fetchColumn()) {
                throw new QueueRuleViolation('Finish the consultation in progress before starting another.');
            }
            $next = $this->query(
                "SELECT id, appointment_time FROM appointments
                 WHERE doctor_id = ? AND appointment_date = ? AND status = 'booked'
                   AND checked_in_at IS NOT NULL AND consultation_started_at IS NULL
                 ORDER BY appointment_time, checked_in_at, id LIMIT 1",
                [$doctorId, date('Y-m-d')]
            )->fetch();
            if (!$next || (int)$next['id'] !== (int)$visit['id']) {
                throw new QueueRuleViolation('The queue has changed. Review the next patient and try again.');
            }
            if ($this->slotTime($visit['appointment_date'], $visit['appointment_time']) > new DateTimeImmutable()) {
                throw new QueueRuleViolation('This patient’s booked time has not arrived yet.');
            }
            $this->query('UPDATE appointments SET consultation_started_at = ? WHERE id = ?', [$this->utcNow(), $visit['id']]);
        });
    }

    public function finish(int $appointmentId, int $doctorId, string $notes): void
    {
        $this->changeAppointment($appointmentId, 'doctor', $doctorId, function (array $visit) use ($notes) {
            if ($visit['status'] === 'completed' && $visit['consultation_finished_at'] !== null) {
                return;
            }
            $this->requireBooked($visit);
            if ($visit['consultation_started_at'] === null) {
                throw new QueueRuleViolation('Start the consultation from the live queue before completing it.');
            }
            $this->query(
                "UPDATE appointments SET status = 'completed', consultation_finished_at = ?, doctor_notes = ? WHERE id = ?",
                [$this->utcNow(), $notes, $visit['id']]
            );
        });
    }

    public function cancel(int $appointmentId, string $role, int $actorId): void
    {
        $this->changeAppointment($appointmentId, $role, $actorId, function (array $visit) use ($role) {
            if ($visit['status'] === 'cancelled') {
                return;
            }
            $this->requireBooked($visit);
            if ($visit['consultation_started_at'] !== null) {
                throw new QueueRuleViolation('A consultation in progress can only be completed by its doctor.');
            }
            if ($role === 'patient' && $visit['appointment_date'] < date('Y-m-d')) {
                throw new QueueRuleViolation('Contact the clinic about a past appointment.');
            }
            $this->query("UPDATE appointments SET status = 'cancelled' WHERE id = ?", [$visit['id']]);
        });
    }

    public function saveTiming(int $doctorId, int $min, int $max): void
    {
        if ($min < 5 || $max > 120 || $min > $max) {
            throw new QueueRuleViolation('Enter a range between 5 and 120 minutes, with the minimum first.');
        }
        $this->transaction(function () use ($doctorId, $min, $max) {
            $this->lockDoctor($doctorId);
            $this->query('UPDATE doctors SET queue_min_minutes = ?, queue_max_minutes = ? WHERE id = ?', [$min, $max, $doctorId]);
        });
    }

    public function clinicOverview(): array
    {
        [$today, $from, $until] = $this->day();
        return $this->query(
            "SELECT d.id, u.full_name, s.name AS specialty, d.queue_min_minutes, d.queue_max_minutes,
               (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id AND a.appointment_date = ?
                AND a.status = 'booked' AND a.checked_in_at IS NOT NULL AND a.consultation_started_at IS NULL) AS waiting,
               (SELECT COUNT(*) FROM appointments a WHERE a.active_doctor_id = d.id) AS active,
               (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id AND a.consultation_finished_at >= ?
                AND a.consultation_finished_at < ?) AS seen
             FROM doctors d JOIN users u ON u.id = d.user_id JOIN specialties s ON s.id = d.specialty_id
             ORDER BY u.full_name, d.id",
            [$today, $from, $until]
        )->fetchAll();
    }

    public function doctorQueue(int $doctorId): array
    {
        $now = new DateTimeImmutable();
        return $this->transaction(function () use ($doctorId, $now) {
            $rows = $this->liveRows($doctorId, true, $now);
            $rows['can_start'] = $rows['current'] === null && $rows['waiting'] !== []
                && $this->slotTime($rows['waiting'][0]['appointment_date'], $rows['waiting'][0]['appointment_time']) <= $now;
            $rows['seen'] = $this->seenToday($doctorId, $now);
            return $rows;
        });
    }

    public function patientQueue(int $patientId): array
    {
        $now = new DateTimeImmutable();
        [$today, $from, $until] = $this->day($now);
        return $this->transaction(function () use ($patientId, $now, $today, $from, $until) {
            $appointments = $this->query(
                "SELECT a.id, a.doctor_id, a.appointment_date, a.appointment_time, a.status,
                        a.checked_in_at, a.consultation_started_at,
                        u.full_name AS doctor_name, d.queue_min_minutes, d.queue_max_minutes
                 FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users u ON u.id = d.user_id
                 WHERE a.patient_id = ? AND (a.appointment_date = ? OR a.active_doctor_id IS NOT NULL
                    OR (a.consultation_finished_at >= ? AND a.consultation_finished_at < ?))
                 ORDER BY CASE WHEN a.active_doctor_id IS NOT NULL THEN 0
                               WHEN a.status = 'booked' AND a.checked_in_at IS NOT NULL THEN 1
                               WHEN a.status = 'booked' THEN 2
                               WHEN a.status = 'completed' THEN 3 ELSE 4 END,
                          a.appointment_date, a.appointment_time, a.id",
                [$patientId, $today, $from, $until]
            )->fetchAll();

            $queues = [];
            foreach ($appointments as &$visit) {
                $doctorId = (int)$visit['doctor_id'];
                if (!isset($queues[$doctorId])) {
                    $queues[$doctorId] = ['seen' => $this->seenToday($doctorId, $now)];
                }
                $visit['seen'] = $queues[$doctorId]['seen'];
                $visit['state'] = self::visitState($visit);
                if ($visit['state'] !== 'waiting') {
                    continue;
                }
                if (!isset($queues[$doctorId]['rows'])) {
                    $queues[$doctorId]['rows'] = $this->liveRows($doctorId, false, $now);
                    $queues[$doctorId]['duration'] = $this->durationRange($visit, $now);
                }
                $visit += $this->estimate($visit, $queues[$doctorId]['rows'], $queues[$doctorId]['duration'], $now);
            }
            return $appointments;
        });
    }

    public static function visitState(array $visit): string
    {
        if ($visit['status'] !== 'booked') {
            return $visit['status'];
        }
        if ($visit['consultation_started_at'] !== null) {
            return 'in consultation';
        }
        return $visit['checked_in_at'] === null ? 'booked' : 'waiting';
    }

    private function estimate(array $visit, array $queue, array $duration, DateTimeImmutable $now): array
    {
        $ahead = [];
        foreach ($queue['waiting'] as $waiting) {
            if ((int)$waiting['id'] === (int)$visit['id']) {
                break;
            }
            $ahead[] = $waiting;
        }

        [$low, $high] = $duration;
        $nowSeconds = $now->getTimestamp();
        $elapsed = 0;
        if ($queue['current']) {
            $started = new DateTimeImmutable($queue['current']['consultation_started_at'], new DateTimeZone('UTC'));
            $elapsed = max(0, $nowSeconds - $started->getTimestamp());
            if ($elapsed >= $high) {
                return ['ahead' => count($ahead), 'wait' => null];
            }
        }

        $earliest = $nowSeconds;
        $latest = $nowSeconds;
        if ($queue['current']) {
            $earliest += max(1, $low - $elapsed);
            $latest += $high - $elapsed;
        }
        foreach ($ahead as $waiting) {
            $slot = $this->slotTime($waiting['appointment_date'], $waiting['appointment_time'])->getTimestamp();
            $earliest = max($earliest, $slot) + $low;
            $latest = max($latest, $slot) + $high;
        }
        $slot = $this->slotTime($visit['appointment_date'], $visit['appointment_time'])->getTimestamp();
        return [
            'ahead' => count($ahead),
            'wait' => [
                (int)ceil((max($earliest, $slot) - $nowSeconds) / 60),
                (int)ceil((max($latest, $slot) - $nowSeconds) / 60),
            ],
            'ready' => $ahead === [] && $queue['current'] === null && $slot <= $nowSeconds,
        ];
    }

    private function durationRange(array $visit, DateTimeImmutable $now): array
    {
        $durations = $this->query(
            "SELECT TIMESTAMPDIFF(SECOND, consultation_started_at, consultation_finished_at) AS duration
             FROM appointments WHERE doctor_id = ? AND consultation_finished_at >= ?
               AND TIMESTAMPDIFF(SECOND, consultation_started_at, consultation_finished_at) BETWEEN 120 AND 7200
             ORDER BY consultation_finished_at DESC LIMIT 24",
            [$visit['doctor_id'], $this->utc($now->modify('-60 days'))]
        )->fetchAll(PDO::FETCH_COLUMN);
        if (count($durations) < 8) {
            return [(int)$visit['queue_min_minutes'] * 60, (int)$visit['queue_max_minutes'] * 60];
        }
        sort($durations, SORT_NUMERIC);
        $last = count($durations) - 1;
        return [(int)$durations[(int)floor($last * 0.25)], (int)$durations[(int)ceil($last * 0.75)]];
    }

    private function liveRows(int $doctorId, bool $withName, ?DateTimeImmutable $now = null): array
    {
        [$today] = $this->day($now);
        $name = $withName ? ', u.full_name AS patient_name' : '';
        $join = $withName ? ' JOIN users u ON u.id = a.patient_id' : '';
        $fields = 'a.id, a.appointment_date, a.appointment_time, a.consultation_started_at';
        $current = $this->query(
            "SELECT {$fields}{$name} FROM appointments a{$join} WHERE a.active_doctor_id = ?",
            [$doctorId]
        )->fetch();
        $waiting = $this->query(
            "SELECT {$fields}{$name} FROM appointments a{$join} WHERE a.doctor_id = ?
             AND a.appointment_date = ? AND a.status = 'booked'
             AND a.checked_in_at IS NOT NULL AND a.consultation_started_at IS NULL
             ORDER BY a.appointment_time, a.checked_in_at, a.id",
            [$doctorId, $today]
        )->fetchAll();
        return ['current' => $current ?: null, 'waiting' => $waiting];
    }

    private function seenToday(int $doctorId, ?DateTimeImmutable $now = null): int
    {
        [, $from, $until] = $this->day($now);
        return (int)$this->query(
            'SELECT COUNT(*) FROM appointments WHERE doctor_id = ?
             AND consultation_finished_at >= ? AND consultation_finished_at < ?',
            [$doctorId, $from, $until]
        )->fetchColumn();
    }

    private function changeAppointment(int $id, string $role, int $actorId, callable $change)
    {
        $scope = [
            'doctor' => [' AND doctor_id = ?', [$actorId]],
            'patient' => [' AND patient_id = ?', [$actorId]],
            'admin' => ['', []],
        ][$role];
        [$condition, $params] = $scope;
        $params = array_merge([$id], $params);
        // The first lookup is outside the transaction. Re-read under the doctor lock before changing anything.
        $doctorId = (int)$this->query('SELECT doctor_id FROM appointments WHERE id = ?' . $condition, $params)->fetchColumn();
        if (!$doctorId) {
            throw new QueueRuleViolation('Appointment not found.');
        }
        return $this->transaction(function () use ($doctorId, $condition, $params, $change) {
            $this->lockDoctor($doctorId);
            $visit = $this->query(
                'SELECT id, appointment_date, appointment_time, status, checked_in_at,
                        consultation_started_at, consultation_finished_at FROM appointments
                 WHERE id = ?' . $condition . ' FOR UPDATE',
                $params
            )->fetch();
            if (!$visit) {
                throw new QueueRuleViolation('Appointment not found.');
            }
            return $change($visit);
        });
    }

    private function lockDoctor(int $doctorId): void
    {
        if (!$this->query('SELECT id FROM doctors WHERE id = ? FOR UPDATE', [$doctorId])->fetchColumn()) {
            throw new QueueRuleViolation('Doctor not found.');
        }
    }

    private function requireBooked(array $visit): void
    {
        if ($visit['status'] !== 'booked') {
            throw new QueueRuleViolation('Only a booked appointment can be changed.');
        }
    }

    private function transaction(callable $work)
    {
        $this->db->beginTransaction();
        try {
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function query(string $sql, array $params): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function day(?DateTimeImmutable $now = null): array
    {
        $start = ($now ?? new DateTimeImmutable())->setTime(0, 0);
        return [$start->format('Y-m-d'), $this->utc($start), $this->utc($start->modify('+1 day'))];
    }

    private function slotTime(string $date, string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' ' . $time);
    }

    private function utcNow(): string
    {
        return $this->utc(new DateTimeImmutable());
    }

    private function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
