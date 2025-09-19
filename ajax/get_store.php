<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Allow admins and inventory managers to view store details
if (!is_admin() && !is_inventory_manager()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}
$store_id = intval($_POST['store_id'] ?? 0);
if ($store_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid store ID']);
    exit;
}
$query = "SELECT * FROM stores WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $store_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Store not found']);
} 