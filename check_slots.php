<?php
/**
 * AJAX Endpoint: Check booked slots for a specific doctor and date
 */
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date      = isset($_GET['date']) ? trim($_GET['date']) : '';

if ($doctor_id <= 0 || empty($date)) {
    echo json_encode(['booked_slots' => []]);
    exit;
}

try {
    // Only check booked status
    $stmt = $pdo->prepare("
        SELECT appointment_time 
        FROM appointments 
        WHERE doctor_id = ? 
          AND appointment_date = ? 
          AND status = 'booked'
    ");
    $stmt->execute([$doctor_id, $date]);
    $slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['booked_slots' => $slots ?: []]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error', 'booked_slots' => []]);
}
