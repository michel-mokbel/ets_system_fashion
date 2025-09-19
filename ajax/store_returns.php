<?php
/**
 * Store returns listing endpoint.
 *
 * Provides return records initiated by the current store with status and date
 * filters, allowing managers to audit return history.
 */
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
$where = 'r.store_id = ?';
$params = [$store_id];
$types = 'i';
if ($from_date) {
    $where .= ' AND r.return_date >= ?';
    $params[] = $from_date . ' 00:00:00';
    $types .= 's';
}
if ($to_date) {
    $where .= ' AND r.return_date <= ?';
    $params[] = $to_date . ' 23:59:59';
    $types .= 's';
}
if ($status) {
    $where .= ' AND r.status = ?';
    $params[] = $status;
    $types .= 's';
}
$sql = "SELECT r.id, r.return_number, r.return_date, r.total_amount, r.status, r.original_invoice_id, i.invoice_number AS original_invoice_number FROM returns r LEFT JOIN invoices i ON r.original_invoice_id = i.id WHERE $where ORDER BY r.return_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$returns = [];
while ($row = $result->fetch_assoc()) {
    $returns[] = $row;
}
echo json_encode(['success' => true, 'data' => $returns]); 