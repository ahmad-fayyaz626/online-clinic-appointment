<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/clinic_queue.php';

require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit;
}

$doctorId = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT) ?: 0;
$pdo->beginTransaction();
try {
    $doctor = $pdo->prepare('SELECT user_id FROM doctors WHERE id = ? FOR UPDATE');
    $doctor->execute([$doctorId]);
    $userId = $doctor->fetchColumn();
    if (!$userId) {
        throw new QueueRuleViolation('Doctor not found.');
    }

    $visits = $pdo->prepare('SELECT id FROM appointments WHERE doctor_id = ? LIMIT 1');
    $visits->execute([$doctorId]);
    if ($visits->fetchColumn()) {
        throw new QueueRuleViolation('This doctor has appointment history and cannot be removed.');
    }

    $delete = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = \'doctor\'');
    $delete->execute([$userId]);
    $pdo->commit();
    set_flash('success', 'Doctor removed.');
} catch (QueueRuleViolation $error) {
    $pdo->rollBack();
    set_flash('error', $error->getMessage());
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    report_app_error($error);
    set_flash('error', 'The doctor could not be removed. Please try again.');
}

header('Location: admin_dashboard.php', true, 303);
exit;
