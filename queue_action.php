<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/clinic_queue.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$actor = current_user();
$role = $actor['role'];
$action = $_POST['action'] ?? '';
$corrections = [
    'undo_check_in' => 'Check-in undone. The appointment is still booked.',
    'no_show' => 'Appointment marked as missed.',
    'restore' => 'Appointment restored. The patient can check in at reception.',
];
if (($role !== 'admin' || !is_string($action)
        || (!in_array($action, ['check_in', 'save_timing'], true) && !isset($corrections[$action])))
    && ($role !== 'doctor' || $action !== 'start')) {
    http_response_code(403);
    exit('This action is unavailable for your account.');
}

$queue = new ClinicQueue($pdo);
try {
    if ($action === 'check_in') {
        $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
        set_flash('success', $queue->checkIn($token, $actor['id']));
    } elseif (isset($corrections[$action])) {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
        $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
        if ($eventId === false || $eventId === null || $eventId < 0) {
            throw new QueueRuleViolation('Refresh the reception list before trying again.');
        }
        $queue->correctReception($appointmentId, $actor['id'], $action, $eventId);
        set_flash('success', $corrections[$action]);
    } elseif ($action === 'save_timing') {
        $doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT) ?: 0;
        $min = filter_input(INPUT_POST, 'min_minutes', FILTER_VALIDATE_INT) ?: 0;
        $max = filter_input(INPUT_POST, 'max_minutes', FILTER_VALIDATE_INT) ?: 0;
        $queue->saveTiming($doctorId, $min, $max);
        set_flash('success', 'Consultation timing updated.');
    } else {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
        $queue->start($appointmentId, $queue->doctorId($actor['id']));
        set_flash('success', 'Consultation started.');
    }
} catch (QueueRuleViolation $error) {
    set_flash('error', $error->getMessage());
} catch (PDOException $error) {
    report_app_error($error);
    set_flash('error', 'The queue could not be updated. Refresh the page and try again.');
}

$dashboard = $role === 'doctor' ? 'doctor_dashboard.php' : 'admin_dashboard.php';
header('Location: ' . $dashboard, true, 303);
exit;
