<?php
/**
 * Maintenance schedule listing endpoint.
 *
 * Returns upcoming and overdue maintenance tasks with recurrence data for the
 * admin maintenance module, applying search filters and enforcing permissions.
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
$asset_id = $_POST['asset_id'] ?? '';
$schedule_type = $_POST['schedule_type'] ?? '';
$status = $_POST['status'] ?? '';

// Build query
$where = [];
$params = [];
$param_types = '';

// Base query
$query = "SELECT ms.*, a.name as asset_name, a.id as asset_id
          FROM maintenance_schedules ms
          LEFT JOIN assets a ON ms.asset_id = a.id";

// Apply search
if (!empty($search)) {
    $where[] = "(a.name LIKE ? OR ms.schedule_type LIKE ? OR ms.assigned_technician LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

// Apply asset filter
if (!empty($asset_id)) {
    $where[] = "ms.asset_id = ?";
    $params[] = $asset_id;
    $param_types .= 'i';
}

// Apply schedule type filter
if (!empty($schedule_type)) {
    $where[] = "ms.schedule_type = ?";
    $params[] = $schedule_type;
    $param_types .= 's';
}

// Apply status filter
if (!empty($status)) {
    $where[] = "ms.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Combine all conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

// Get total count before pagination
$count_query = "SELECT COUNT(*) as total FROM maintenance_schedules ms";
if (!empty($where)) {
    $count_query .= " LEFT JOIN assets a ON ms.asset_id = a.id 
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
$order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 4;
$order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

// Columns for sorting
$columns = ['a.name', 'ms.schedule_type', 'ms.frequency_value', 'ms.last_maintenance', 'ms.next_maintenance', 'ms.assigned_technician', 'ms.status'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY ms.next_maintenance ASC";
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
    // Format frequency display
    $frequency = '';
    switch ($row['schedule_type']) {
        case 'daily':
            $frequency = 'Every day';
            break;
        case 'weekly':
            $frequency = 'Every week';
            break;
        case 'monthly':
            $frequency = 'Every month';
            break;
        case 'quarterly':
            $frequency = 'Every 3 months';
            break;
        case 'yearly':
            $frequency = 'Every year';
            break;
        case 'custom':
            $frequency = 'Every ' . $row['frequency_value'] . ' ' . $row['frequency_unit'];
            break;
        default:
            $frequency = $row['schedule_type'];
    }
    
    $data[] = [
        'id' => $row['id'],
        'asset_id' => $row['asset_id'],
        'asset_name' => htmlspecialchars($row['asset_name']),
        'schedule_type' => ucfirst($row['schedule_type']),
        'frequency' => $frequency,
        'last_maintenance' => $row['last_maintenance'],
        'next_maintenance' => $row['next_maintenance'],
        'technician_name' => htmlspecialchars($row['assigned_technician'] ?? 'Unassigned'),
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