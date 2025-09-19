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

// Get work order ID
$work_order_id = isset($_POST['work_order_id']) ? (int)$_POST['work_order_id'] : 0;

if ($work_order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid work order ID']);
    exit;
}

// Query work order data with asset details
$query = "SELECT wo.*, a.name as asset_name 
          FROM work_orders wo
          LEFT JOIN assets a ON wo.asset_id = a.id
          WHERE wo.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $work_order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Work order not found']);
    exit;
}

$work_order = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'data' => $work_order
]);
?> 