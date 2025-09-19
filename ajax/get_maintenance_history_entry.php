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

// Get history ID
$history_id = (int)($_POST['history_id'] ?? 0);

if ($history_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid history ID']);
    exit;
}

// Get history entry
$query = "SELECT mh.*, ms.schedule_type, ms.frequency_value, ms.frequency_unit, a.name as asset_name
          FROM maintenance_history mh
          JOIN maintenance_schedules ms ON mh.maintenance_schedule_id = ms.id
          JOIN assets a ON ms.asset_id = a.id
          WHERE mh.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $history_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Maintenance history entry not found']);
    exit;
}

$history_data = $result->fetch_assoc();

// Format schedule type for display
$schedule_type = '';
switch ($history_data['schedule_type']) {
    case 'daily':
        $schedule_type = 'Daily';
        break;
    case 'weekly':
        $schedule_type = 'Weekly';
        break;
    case 'monthly':
        $schedule_type = 'Monthly';
        break;
    case 'quarterly':
        $schedule_type = 'Quarterly';
        break;
    case 'yearly':
        $schedule_type = 'Yearly';
        break;
    case 'custom':
        $schedule_type = 'Every ' . $history_data['frequency_value'] . ' ' . $history_data['frequency_unit'];
        break;
    default:
        $schedule_type = ucfirst($history_data['schedule_type']);
}

// Prepare data for response
$response_data = [
    'id' => $history_data['id'],
    'maintenance_schedule_id' => $history_data['maintenance_schedule_id'],
    'completion_date' => $history_data['completion_date'],
    'completed_by' => $history_data['completed_by'] ?? '',
    'status' => $history_data['status'],
    'notes' => $history_data['notes'] ?? '',
    'asset_name' => $history_data['asset_name'],
    'schedule_type' => $schedule_type,
    'created_at' => $history_data['created_at']
];

echo json_encode(['success' => true, 'data' => $response_data]); 