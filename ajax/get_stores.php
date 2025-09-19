<?php
/**
 * Provides store listings for dropdowns and DataTables.
 *
 * Supports filtering by status and search term while enforcing admin access.
 * Returns results in JSON suitable for the stores management page.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Allow admins and inventory managers to view stores
if (!is_admin() && !is_inventory_manager()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';

// Build query
$where = [];
$params = [];
$param_types = '';

// Base query
$query = "SELECT s.*, u.full_name as manager_name FROM stores s LEFT JOIN users u ON s.manager_id = u.id";

// Apply search
if (!empty($search)) {
    $where[] = "(s.store_code LIKE ? OR s.name LIKE ? OR s.address LIKE ? OR s.phone LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, array_fill(0, 5, $search_param));
    $param_types .= str_repeat('s', 5);
}

// Combine all conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

// Get total count before pagination
$count_query = "SELECT COUNT(*) as total FROM stores s LEFT JOIN users u ON s.manager_id = u.id";
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
$columns = ['s.store_code', 's.name', 's.address', 's.phone', 'u.full_name', 's.status'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY s.name ASC";
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
        'store_code' => htmlspecialchars($row['store_code']),
        'name' => htmlspecialchars($row['name']),
        'address' => htmlspecialchars($row['address'] ?? ''),
        'phone' => htmlspecialchars($row['phone'] ?? ''),
        'manager_name' => htmlspecialchars($row['manager_name'] ?? ''),
        'status' => $row['status']
    ];
}

echo json_encode([
    'draw' => intval($draw),
    'recordsTotal' => $total_records,
    'recordsFiltered' => $total_records,
    'data' => $data
]); 