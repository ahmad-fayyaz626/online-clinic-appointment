<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/clinic_queue.php';

require_doctor();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['status'] ?? '') !== 'cancelled') {
            throw new QueueRuleViolation('Use the live queue to start and complete consultations.');
        }
        $queue = new ClinicQueue($pdo);
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT) ?: 0;
        $queue->cancel($appointmentId, 'doctor', $queue->doctorId(current_user()['id']));
        set_flash('success', 'Appointment cancelled.');
    } catch (QueueRuleViolation $error) {
        set_flash('error', $error->getMessage());
    } catch (PDOException $error) {
        report_app_error($error);
        set_flash('error', 'The appointment could not be updated. Please try again.');
    }
}

header('Location: doctor_dashboard.php', true, 303);
exit;
