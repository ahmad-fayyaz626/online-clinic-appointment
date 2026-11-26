<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/clinic_queue.php';

require_role('patient');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
        (new ClinicQueue($pdo))->cancel($appointmentId, 'patient', current_user()['id']);
        set_flash('success', 'Appointment cancelled. The time slot has been released.');
    } catch (QueueRuleViolation $error) {
        set_flash('error', $error->getMessage());
    } catch (PDOException $error) {
        report_app_error($error);
        set_flash('error', 'The appointment could not be cancelled. Please try again.');
    }
}

header('Location: dashboard.php', true, 303);
exit;
