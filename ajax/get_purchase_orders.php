<?php
/**
 * Purchase order DataTable endpoint.
 *
 * Supports filtering by supplier, status, and date while returning paginated
 * results for the procurement module. Ensures the caller is an administrator
 * before outputting DataTables JSON.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get DataTables parameters
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';

// Get filter parameters
$supplier_id = isset($_POST['supplier_id']) && !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
$status = isset($_POST['status']) && !empty($_POST['status']) ? $_POST['status'] : null;
$date_range = isset($_POST['date_range']) && !empty($_POST['date_range']) ? $_POST['date_range'] : null;

// Build query
$where = [];
$params = [];
$param_types = '';

// Base query
$query = "SELECT po.*, s.name as supplier_name 
          FROM purchase_orders po
          JOIN suppliers s ON po.supplier_id = s.id";

// Apply search
if (!empty($search)) {
    $where[] = "(po.po_number LIKE ? OR s.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

// Apply supplier filter
if ($supplier_id !== null) {
    $where[] = "po.supplier_id = ?";
    $params[] = $supplier_id;
    $param_types .= 'i';
}

// Apply status filter
if ($status !== null) {
    $where[] = "po.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Apply date range filter
if ($date_range !== null) {
    $dates = explode(' - ', $date_range);
    if (count($dates) === 2) {
        $start_date = $dates[0];
        $end_date = $dates[1];
        $where[] = "(po.order_date BETWEEN ? AND ?)";
        $params[] = $start_date;
        $params[] = $end_date;
        $param_types .= 'ss';
    }
}

// Combine all conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

// Get total count before pagination
$count_query = "SELECT COUNT(*) as total FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id";
if (!empty($where)) {
    $count_query .= " WHERE " . implode(' AND ', $where);
}

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];

// Add sorting
$order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 0;
$order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

// Columns for sorting
$columns = ['po.po_number', 's.name', 'po.order_date', 'po.expected_delivery_date', 'po.total_amount', 'po.status'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY po.order_date DESC";
}

// Add pagination
$query .= " LIMIT ?, ?";
$params[] = $start;
$params[] = $length;
$param_types .= 'ii';

// Prepare and execute the final query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Format data for DataTables
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'po_number' => htmlspecialchars($row['po_number']),
        'supplier_id' => $row['supplier_id'],
        'supplier_name' => htmlspecialchars($row['supplier_name']),
        'order_date' => $row['order_date'],
        'expected_delivery_date' => $row['expected_delivery_date'],
        'total_amount' => $row['total_amount'],
        'status' => $row['status']
    ];
}

echo json_encode([
    'draw' => intval($draw),
    'recordsTotal' => $total_records,
    'recordsFiltered' => $total_records,
    'data' => $data
]);
?> 