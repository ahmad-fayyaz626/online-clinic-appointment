<?php
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=UTF-8');

$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT) ?: 0;
$date = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
$day = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date)
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
if ($doctorId <= 0 || !$day || $day->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
    http_response_code(400);
    echo json_encode(['error' => 'Choose a doctor and a valid date.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT appointment_time FROM appointments
                           WHERE doctor_id = ? AND appointment_date = ? AND status IN ('booked', 'completed')");
    $stmt->execute([$doctorId, $date]);
    echo json_encode([
        'booked_slots' => $stmt->fetchAll(PDO::FETCH_COLUMN),
        'cutoff' => $date === date('Y-m-d') ? date('H:i:s') : null,
    ]);
} catch (PDOException $error) {
    report_app_error($error);
    http_response_code(503);
    echo json_encode(['error' => 'Availability could not be checked.']);
}
