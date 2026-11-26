<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/clinic_queue.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}
$actor = current_user();
if (!$actor) {
    http_response_code(401);
    exit;
}

$queue = new ClinicQueue($pdo);
$queueRole = $actor['role'];
if (in_array($queueRole, ['doctor', 'admin'], true) && !isset($_SESSION['csrf_token'])) {
    csrf_field();
}
session_write_close();

switch ($queueRole) {
    case 'admin':
        $queueData = $queue->clinicOverview();
        $receptionVisits = $queue->receptionVisits();
        break;
    case 'doctor':
        $doctorId = $queue->doctorId($actor['id']);
        if (!$doctorId) {
            http_response_code(403);
            exit;
        }
        $queueData = $queue->doctorQueue($doctorId);
        break;
    case 'patient':
        $queueData = $queue->patientQueue($actor['id']);
        break;
    default:
        http_response_code(403);
        exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('X-CarePulse-Queue: 1');
header('X-CarePulse-Date: ' . date('l, j F'));
require __DIR__ . '/includes/queue_cards.php';
