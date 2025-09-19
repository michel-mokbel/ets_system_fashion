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
$asset_id = $_POST['asset_id'] ?? '';
$maintenance_type = $_POST['maintenance_type'] ?? '';
$status = $_POST['status'] ?? '';

// Build query
$where = [];
$params = [];
$param_types = '';

// Base query
$query = "SELECT wo.*, a.name as asset_name 
          FROM work_orders wo
          LEFT JOIN assets a ON wo.asset_id = a.id";

// Apply search
if (!empty($search)) {
    $where[] = "(wo.work_order_number LIKE ? OR a.name LIKE ? OR wo.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

// Apply asset filter
if (!empty($asset_id)) {
    $where[] = "wo.asset_id = ?";
    $params[] = $asset_id;
    $param_types .= 'i';
}

// Apply maintenance type filter
if (!empty($maintenance_type)) {
    $where[] = "wo.maintenance_type = ?";
    $params[] = $maintenance_type;
    $param_types .= 's';
}

// Apply status filter
if (!empty($status)) {
    $where[] = "wo.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Combine all conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

// Get total count before pagination
$count_query = "SELECT COUNT(*) as total FROM work_orders wo";
if (!empty($where)) {
    $count_query .= " LEFT JOIN assets a ON wo.asset_id = a.id 
                     WHERE " . implode(' AND ', $where);
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
$columns = ['wo.work_order_number', 'a.name', 'wo.maintenance_type', 'wo.priority', 'wo.scheduled_date', 'wo.status'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY wo.created_at DESC";
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
        'work_order_number' => htmlspecialchars($row['work_order_number']),
        'asset_id' => $row['asset_id'],
        'asset_name' => htmlspecialchars($row['asset_name'] ?? 'Unknown Asset'),
        'maintenance_type' => $row['maintenance_type'],
        'priority' => $row['priority'],
        'description' => $row['description'],
        'status' => $row['status'],
        'scheduled_date' => $row['scheduled_date'],
        'completed_date' => $row['completed_date'],
        'notes' => $row['notes'],
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