<?php
require_once __DIR__ . '/config/db.php';

// Access control: Doctor only
require_doctor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: doctor_dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate target status
if (!in_array($new_status, ['completed', 'cancelled'], true)) {
    set_flash('error', 'Invalid status update requested.');
    header('Location: doctor_dashboard.php');
    exit;
}

if ($appointment_id <= 0) {
    set_flash('error', 'Invalid appointment record.');
    header('Location: doctor_dashboard.php');
    exit;
}

// 1. Fetch doctor ID for currently logged-in user
$doctor_stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
$doctor_stmt->execute([$user_id]);
$doctor = $doctor_stmt->fetch();

if (!$doctor) {
    set_flash('error', 'Doctor profile not found.');
    header('Location: doctor_dashboard.php');
    exit;
}

$doctor_id = (int)$doctor['id'];

// 2. Verify ownership: appointment must belong to this doctor
$apt_stmt = $pdo->prepare("
    SELECT id, status 
    FROM appointments 
    WHERE id = ? AND doctor_id = ? 
    LIMIT 1
");
$apt_stmt->execute([$appointment_id, $doctor_id]);
$appointment = $apt_stmt->fetch();

if (!$appointment) {
    set_flash('error', 'Appointment not found or you do not have permission to manage it.');
    header('Location: doctor_dashboard.php');
    exit;
}

// 3. Update status in database
try {
    $update_stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
    $update_stmt->execute([$new_status, $appointment_id, $doctor_id]);

    $label = ($new_status === 'completed') ? 'marked as completed' : 'cancelled';
    set_flash('success', "Appointment #{$appointment_id} successfully {$label}.");
} catch (PDOException $e) {
    set_flash('error', 'Database error updating appointment: ' . $e->getMessage());
}

header('Location: doctor_dashboard.php');
exit;
