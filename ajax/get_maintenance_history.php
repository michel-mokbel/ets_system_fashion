<?php
/**
 * Maintenance history timeline endpoint.
 *
 * Supplies paginated historical records per asset or schedule, enabling the UI
 * to render maintenance timelines with technician notes and costs.
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
$schedule_id = $_POST['schedule_id'] ?? '';
$status = $_POST['status'] ?? '';

// Build query
$where = [];
$params = [];
$param_types = '';

// Base query with joins
$query = "SELECT mh.*, a.name as asset_name, ms.schedule_type, ms.frequency_value, ms.frequency_unit
          FROM maintenance_history mh
          JOIN maintenance_schedules ms ON mh.maintenance_schedule_id = ms.id
          JOIN assets a ON ms.asset_id = a.id";

// Apply search
if (!empty($search)) {
    $where[] = "(a.name LIKE ? OR ms.schedule_type LIKE ? OR mh.completed_by LIKE ? OR mh.notes LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

// Apply asset filter
if (!empty($asset_id)) {
    $where[] = "ms.asset_id = ?";
    $params[] = $asset_id;
    $param_types .= 'i';
}

// Apply schedule filter
if (!empty($schedule_id)) {
    $where[] = "mh.maintenance_schedule_id = ?";
    $params[] = $schedule_id;
    $param_types .= 'i';
}

// Apply status filter
if (!empty($status)) {
    $where[] = "mh.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Combine all conditions
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

// Get total count before pagination
$count_query = "SELECT COUNT(*) as total FROM maintenance_history mh";
if (!empty($where)) {
    $count_query .= " JOIN maintenance_schedules ms ON mh.maintenance_schedule_id = ms.id
                      JOIN assets a ON ms.asset_id = a.id
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
$order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 2;
$order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

// Columns for sorting
$columns = ['a.name', 'ms.schedule_type', 'mh.completion_date', 'mh.completed_by', 'mh.status', 'mh.notes'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY mh.completion_date DESC";
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
    // Format schedule type
    $schedule_type = '';
    switch ($row['schedule_type']) {
        case 'daily':
            $schedule_type = 'Daily';
            break;
        case 'weekly':
            $schedule_type = 'Weekly';
            break;
        case 'monthly':
            $schedule_type = 'Monthly';
            break;
        case 'quarterly':
            $schedule_type = 'Quarterly';
            break;
        case 'yearly':
            $schedule_type = 'Yearly';
            break;
        case 'custom':
            $schedule_type = 'Every ' . $row['frequency_value'] . ' ' . $row['frequency_unit'];
            break;
        default:
            $schedule_type = ucfirst($row['schedule_type']);
    }
    
    $data[] = [
        'id' => $row['id'],
        'maintenance_schedule_id' => $row['maintenance_schedule_id'],
        'asset_name' => htmlspecialchars($row['asset_name']),
        'schedule_type' => $schedule_type,
        'completion_date' => $row['completion_date'],
        'completed_by' => htmlspecialchars($row['completed_by'] ?? ''),
        'status' => $row['status'],
        'notes' => htmlspecialchars($row['notes'] ?? '')
    ];
}

echo json_encode([
    'draw' => intval($draw),
    'recordsTotal' => $total_records,
    'recordsFiltered' => $total_records,
    'data' => $data
]); 