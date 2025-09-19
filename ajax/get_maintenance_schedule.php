<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get schedule ID
$schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;

if ($schedule_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
    exit;
}

// Query schedule data
$query = "SELECT * FROM maintenance_schedules WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $schedule_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Maintenance schedule not found']);
    exit;
}

$schedule = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'data' => $schedule
]);
?> 