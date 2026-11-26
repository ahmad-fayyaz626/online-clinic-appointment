<?php if ($queueRole === 'admin'): ?>
    <details class="reception-review" data-preserve-disclosure="reception-review">
        <summary>Review check-ins and missed visits</summary>
        <p>Undo an arrival before the visit starts, or mark it missed after 15 minutes if the patient is still absent.</p>
        <ul class="reception-roster">
            <?php
            $actionLabels = ['undo_check_in' => 'Undo check-in', 'no_show' => 'Mark missed', 'restore' => 'Restore booking'];
            $actionStates = ['undo_check_in' => 'Checked in', 'no_show' => 'Awaiting check-in', 'restore' => 'Missed'];
            $shown = false;
            foreach ($receptionVisits as $row):
                $action = $row['action'];
                if (!isset($actionLabels[$action])) continue;
                $shown = true;
            ?>
                <li>
                    <div>
                        <strong><?= e($row['patient_name']) ?></strong>
                        <p><?= e($row['doctor_name']) ?> &middot; <?= e(date('g:i A', strtotime($row['appointment_time']))) ?></p>
                        <small><?= e(ClinicQueue::token((int)$row['id'])) ?> &middot; <?= e($actionStates[$action]) ?></small>
                    </div>
                    <form action="queue_action.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= e($action) ?>">
                        <input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="event_id" value="<?= (int)$row['event_id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm" aria-label="<?= e($actionLabels[$action] . ' for ' . $row['patient_name']) ?>"><?= e($actionLabels[$action]) ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if (!$shown): ?>
                <li class="reception-empty">There are no check-ins or missed visits to review right now.</li>
            <?php endif; ?>
        </ul>
    </details>
    <dl class="queue-metrics">
        <div><dt>Waiting in clinic</dt><dd><?= array_sum(array_column($queueData, 'waiting')) ?></dd></div>
        <div><dt>In consultation</dt><dd><?= array_sum(array_column($queueData, 'active')) ?></dd></div>
        <div><dt>Seen today</dt><dd><?= array_sum(array_column($queueData, 'seen')) ?></dd></div>
    </dl>
    <div class="queue-list-heading"><h3>By doctor</h3></div>
    <?php if ($queueData): ?>
        <div class="queue-doctors">
            <?php foreach ($queueData as $row): ?>
                <article class="queue-doctor">
                    <div class="queue-doctor-name">
                        <h4><?= e($row['full_name']) ?></h4>
                        <p><?= e($row['specialty']) ?></p>
                    </div>
                    <dl class="queue-doctor-counts">
                        <div><dt>Waiting</dt><dd><?= (int)$row['waiting'] ?></dd></div>
                        <div><dt>In consult</dt><dd><?= (int)$row['active'] ?></dd></div>
                        <div><dt>Seen</dt><dd><?= (int)$row['seen'] ?></dd></div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="queue-empty">Add a doctor to start tracking the clinic queue.</p>
    <?php endif; ?>
<?php elseif ($queueRole === 'doctor'): ?>
    <?php $current = $queueData['current']; $waiting = $queueData['waiting']; ?>
    <dl class="queue-metrics">
        <div><dt>Checked in and waiting</dt><dd><?= count($waiting) ?></dd></div>
        <div><dt>In consultation</dt><dd><?= $current ? 1 : 0 ?></dd></div>
        <div><dt>Seen today</dt><dd><?= (int)$queueData['seen'] ?></dd></div>
    </dl>
    <?php if ($current): ?>
        <div class="queue-current">
            <div>
                <span class="queue-status" data-state="active">In consultation</span>
                <h3><?= e($current['patient_name']) ?></h3>
                <p><?= e(ClinicQueue::token((int)$current['id'])) ?></p>
            </div>
            <a class="btn queue-primary" href="doctor_complete_appointment.php?id=<?= (int)$current['id'] ?>">Finish consultation</a>
        </div>
    <?php endif; ?>
    <div class="queue-list-heading"><h3>Waiting patients</h3></div>
    <?php if ($waiting): ?>
        <ol class="queue-patients">
            <?php foreach ($waiting as $index => $row): ?>
                <li>
                    <span class="queue-position" aria-hidden="true"><?= $index + 1 ?></span>
                    <div class="queue-patient-name">
                        <strong><?= e($row['patient_name']) ?></strong>
                        <p><?= e(ClinicQueue::token((int)$row['id'])) ?> <span aria-hidden="true">·</span>
                           <?= e(date('g:i A', strtotime($row['appointment_time']))) ?></p>
                    </div>
                    <?php if ($index === 0 && $queueData['can_start']): ?>
                        <form action="queue_action.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="start">
                            <input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn queue-primary">Start consultation</button>
                        </form>
                    <?php elseif ($index === 0): ?>
                        <span class="queue-status" data-state="waiting"><?= $current ? 'Up next' : 'Starts at ' . e(date('g:i A', strtotime($row['appointment_time']))) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <p class="queue-empty">No patients are waiting. New arrivals appear here after reception checks them in.</p>
    <?php endif; ?>
<?php else: ?>
    <?php if (!$queueData): ?>
        <div class="queue-empty">
            <h3>No visits today</h3>
            <p>Your queue position will appear here on the day of your appointment, after check-in.</p>
        </div>
    <?php else: ?>
        <div class="queue-visits">
            <?php foreach ($queueData as $row): ?>
                <?php
                $state = $row['state'];
                $ready = !empty($row['ready']);
                $ahead = (int)($row['ahead'] ?? 0);
                $wait = $row['wait'] ?? null;
                $labels = [
                    'booked' => 'Check in at reception',
                    'waiting' => $ready ? 'You’re next' : ($ahead === 0 ? 'First in line' : $ahead . ($ahead === 1 ? ' person ahead' : ' people ahead')),
                    'in consultation' => 'Your consultation has started',
                    'completed' => 'Visit complete',
                    'cancelled' => 'Appointment cancelled',
                    'no_show' => 'Appointment marked as missed',
                ];
                $instructions = [
                    'booked' => 'Share your appointment token when you arrive. Your place in line will appear here.',
                    'waiting' => $ready ? 'Stay nearby so the clinic can call your token.'
                        : ($wait === null ? 'The doctor is running behind. Your place in line is still shown here.' : ''),
                    'in consultation' => '',
                    'completed' => 'Open your appointment slip to see any notes your doctor shared.',
                    'cancelled' => 'You can find another appointment in the doctor directory.',
                    'no_show' => 'If you’re at the clinic, speak to reception about restoring this booking.',
                ];
                $tone = $state === 'waiting' && !$ready ? 'waiting'
                    : (in_array($state, ['waiting', 'in consultation', 'completed'], true) ? 'active' : 'neutral');
                ?>
                <article class="queue-visit patient-visit" data-visit="<?= (int)$row['id'] ?>" data-visit-state="<?= e($state) ?>">
                    <div class="patient-visit-progress" data-state="<?= e($tone) ?>">
                        <div>
                            <strong data-visit-status><?= e($labels[$state]) ?></strong>
                            <?php if ($instructions[$state] !== ''): ?>
                                <p><?= e($instructions[$state]) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($state === 'waiting' && !$ready): ?>
                            <?php
                            $waitLabel = $wait === null ? 'Delayed' : ($wait[0] === $wait[1] ? $wait[1] . ' min' : $wait[0] . '–' . $wait[1] . ' min');
                            ?>
                            <dl class="patient-visit-wait"><dt>Estimated wait</dt><dd data-visit-wait><?= e($waitLabel) ?></dd></dl>
                        <?php endif; ?>
                    </div>
                    <div class="patient-visit-details">
                        <div>
                            <h3><?= e($row['doctor_name']) ?></h3>
                            <p><?= e(date('g:i A', strtotime($row['appointment_time']))) ?> appointment</p>
                        </div>
                        <div class="patient-visit-token">
                            <span>Appointment token</span>
                            <strong><?= e(ClinicQueue::token((int)$row['id'])) ?></strong>
                        </div>
                    </div>
                    <div class="patient-visit-footer">
                        <span><strong><?= (int)$row['seen'] ?></strong> <?= (int)$row['seen'] === 1 ? 'visit' : 'visits' ?> completed by this doctor today</span>
                        <a class="clinic-text-link" href="appointment_slip.php?id=<?= (int)$row['id'] ?>">View appointment slip <span aria-hidden="true">&rarr;</span></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
