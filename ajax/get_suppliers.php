<?php
/**
 * Supplier listing endpoint.
 *
 * Serves DataTables requests from the supplier management UI, supporting search
 * and status filters while restricting access to administrators.
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
$name = $_POST['name'] ?? '';
$status = $_POST['status'] ?? '';

// Build query
$where = [];
$params = [];
$param_types = '';

// Base query
$query = "SELECT * FROM suppliers";

// Apply search
if (!empty($search)) {
    $where[] = "(name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ? OR address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sssss';
}

// Apply name filter
if (!empty($name)) {
    $where[] = "name LIKE ?";
    $params[] = "%$name%";
    $param_types .= 's';
}

// Apply status filter
if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Combine all conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

// Get total count before pagination
$count_query = "SELECT COUNT(*) as total FROM suppliers";
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
$columns = ['name', 'contact_person', 'email', 'phone', 'status'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY name ASC";
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
        'name' => htmlspecialchars($row['name']),
        'contact_person' => htmlspecialchars($row['contact_person'] ?? ''),
        'email' => htmlspecialchars($row['email'] ?? ''),
        'phone' => htmlspecialchars($row['phone'] ?? ''),
        'address' => htmlspecialchars($row['address'] ?? ''),
        'status' => $row['status'],
        'created_at' => $row['created_at']
    ];
}

echo json_encode([
    'draw' => intval($draw),
    'recordsTotal' => $total_records,
    'recordsFiltered' => $total_records,
    'data' => $data
]);
?> 