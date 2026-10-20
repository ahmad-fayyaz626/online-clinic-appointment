<?php
require_once __DIR__ . '/config/db.php';

// Access control: Admin only
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_dashboard.php');
    exit;
}

$doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
if ($doctor_id <= 0) {
    set_flash('error', 'Invalid doctor record.');
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch doctor and user id
$stmt = $pdo->prepare("
    SELECT d.id AS doctor_id, d.user_id, u.full_name 
    FROM doctors d 
    INNER JOIN users u ON d.user_id = u.id 
    WHERE d.id = ? 
    LIMIT 1
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    set_flash('error', 'Doctor not found.');
    header('Location: admin_dashboard.php');
    exit;
}

try {
    // Deleting the user record cascades to doctors and appointments due to ON DELETE CASCADE
    $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $del_stmt->execute([$doctor['user_id']]);

    set_flash('success', "Doctor Dr. {$doctor['full_name']} was successfully removed.");
} catch (PDOException $e) {
    set_flash('error', 'Database error removing doctor: ' . $e->getMessage());
}

header('Location: admin_dashboard.php');
exit;
