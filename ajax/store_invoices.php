<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_store_manager()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$store_id = $_SESSION['store_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action !== 'list') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$status = $_GET['status'] ?? '';
$where = 'store_id = ?';
$params = [$store_id];
$types = 'i';
if ($from_date) {
    $where .= ' AND created_at >= ?';
    $params[] = $from_date . ' 00:00:00';
    $types .= 's';
}
if ($to_date) {
    $where .= ' AND created_at <= ?';
    $params[] = $to_date . ' 23:59:59';
    $types .= 's';
}
if ($status) {
    $where .= ' AND payment_status = ?';
    $params[] = $status;
    $types .= 's';
}
$sql = "SELECT id, invoice_number, customer_name, total_amount, payment_status, created_at FROM invoices WHERE $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$invoices = [];
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}
echo json_encode(['success' => true, 'data' => $invoices]); 