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
$category = $_GET['category'] ?? '';
$where = 'store_id = ?';
$params = [$store_id];
$types = 'i';
if ($from_date) {
    $where .= ' AND expense_date >= ?';
    $params[] = $from_date;
    $types .= 's';
}
if ($to_date) {
    $where .= ' AND expense_date <= ?';
    $params[] = $to_date;
    $types .= 's';
}
if ($status) {
    $where .= ' AND status = ?';
    $params[] = $status;
    $types .= 's';
}
if ($category) {
    $where .= ' AND category LIKE ?';
    $params[] = '%' . $category . '%';
    $types .= 's';
}
$sql = "SELECT id, expense_number, expense_date, category, description, amount, status FROM expenses WHERE $where ORDER BY expense_date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}
echo json_encode(['success' => true, 'data' => $expenses]); 