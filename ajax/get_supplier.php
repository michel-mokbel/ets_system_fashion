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

// Get supplier ID
$supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;

if ($supplier_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
    exit;
}

// Query supplier data
$query = "SELECT * FROM suppliers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Supplier not found']);
    exit;
}

$supplier = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'data' => $supplier
]);
?> 