<?php
require_once __DIR__ . '/config/db.php';

// Authentication required
require_login();

$patient_id = $_SESSION['user_id'];

// Handle POST cancellation request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);

    if ($appointment_id <= 0) {
        set_flash('error', 'Invalid appointment request.');
        header('Location: dashboard.php');
        exit;
    }

    // Business Rule 4: Ownership check - Patient can only cancel their own appointment
    $stmt = $pdo->prepare("
        SELECT id, doctor_id, appointment_date, appointment_time, status 
        FROM appointments 
        WHERE id = ? AND patient_id = ?
        LIMIT 1
    ");
    $stmt->execute([$appointment_id, $patient_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        set_flash('error', 'Appointment not found or you do not have permission to modify it.');
        header('Location: dashboard.php');
        exit;
    }

    if ($appointment['status'] === 'cancelled') {
        set_flash('info', 'This appointment has already been cancelled.');
        header('Location: dashboard.php');
        exit;
    }

    // Business Rule 3: Cancel = status change, delete nahi.
    // Row rehti hai, status 'cancelled' ho jata hai, aur wo slot dobara free ho jata hai.
    try {
        $update_stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'cancelled' 
            WHERE id = ? AND patient_id = ?
        ");
        $update_stmt->execute([$appointment_id, $patient_id]);

        set_flash('success', 'Appointment successfully cancelled. The doctor\'s slot has been freed.');
    } catch (PDOException $e) {
        set_flash('error', 'Failed to cancel appointment: ' . $e->getMessage());
    }

    header('Location: dashboard.php');
    exit;
}

// If accessed via GET directly, redirect to dashboard
header('Location: dashboard.php');
exit;
