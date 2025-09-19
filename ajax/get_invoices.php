<?php
/**
 * Invoice DataTable endpoint.
 *
 * Supplies paginated invoice listings with optional store, status, and date
 * filters. Supports both admin and store contexts while enforcing appropriate
 * access control and outputting DataTables JSON.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action !== 'list') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
$invoice_number = $_GET['invoice_number'] ?? '';
$customer_name = $_GET['customer_name'] ?? '';
$status = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where = '1=1';
$params = [];
$types = '';

// Invoice number filter
if ($invoice_number) {
    $where .= ' AND invoice_number LIKE ?';
    $params[] = '%' . $invoice_number . '%';
    $types .= 's';
}

// Customer name filter
if ($customer_name) {
    $where .= ' AND customer_name LIKE ?';
    $params[] = '%' . $customer_name . '%';
    $types .= 's';
}

// Status filter
if ($status) {
    $where .= ' AND payment_status = ?';
    $params[] = $status;
    $types .= 's';
}

// Date range filter
if ($start_date && $end_date) {
    $where .= ' AND DATE(created_at) >= ? AND DATE(created_at) <= ?';
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= 'ss';
} elseif ($start_date) {
    $where .= ' AND DATE(created_at) >= ?';
    $params[] = $start_date;
    $types .= 's';
} elseif ($end_date) {
    $where .= ' AND DATE(created_at) <= ?';
    $params[] = $end_date;
    $types .= 's';
}
$sql = "SELECT id, invoice_number, customer_name, total_amount, payment_status, created_at FROM invoices WHERE $where ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$invoices = [];
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}
echo json_encode(['success' => true, 'data' => $invoices]); 