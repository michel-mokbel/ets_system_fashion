<?php
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
$category = $_POST['category'] ?? '';
$location = $_POST['location'] ?? '';
$status = $_POST['status'] ?? '';

// Build query
$where = [];
$params = [];
$param_types = '';

// Base query
$query = "SELECT a.*, c.name as category_name 
          FROM assets a
          LEFT JOIN categories c ON a.category_id = c.id";

// Apply search
if (!empty($search)) {
    $where[] = "(a.asset_code LIKE ? OR a.name LIKE ? OR a.description LIKE ? OR a.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

// Apply category filter
if (!empty($category)) {
    $where[] = "a.category_id = ?";
    $params[] = $category;
    $param_types .= 'i';
}

// Apply location filter
if (!empty($location)) {
    $where[] = "a.location LIKE ?";
    $params[] = "%$location%";
    $param_types .= 's';
}

// Apply status filter
if (!empty($status)) {
    $where[] = "a.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Combine all conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

// Get total count before pagination
$count_query = "SELECT COUNT(*) as total FROM assets a";
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
$order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 1;
$order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

$columns = ['a.asset_code', 'a.name', 'c.name', 'a.location', 'a.purchase_date', 'a.warranty_expiry', 'a.status'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY a.name ASC";
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
        'asset_code' => htmlspecialchars($row['asset_code']),
        'name' => htmlspecialchars($row['name']),
        'category' => htmlspecialchars($row['category_name'] ?? 'Uncategorized'),
        'location' => htmlspecialchars($row['location'] ?? ''),
        'purchase_date' => $row['purchase_date'],
        'warranty_expiry' => $row['warranty_expiry'],
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