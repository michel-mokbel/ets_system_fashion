<?php
require_once '../includes/session_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Check if user has inventory access (admin or inventory manager)
if (!can_access_inventory()) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Debug logging
error_log("=== GET_BOXES DEBUG ===");
error_log("POST data: " . json_encode($_POST));
error_log("Search value: " . ($_POST['search']['value'] ?? 'NOT_SET'));
error_log("Custom search: " . ($_POST['search'] ?? 'NOT_SET'));
error_log("Type filter: " . ($_POST['type'] ?? 'NOT_SET'));

// DataTables parameters
$draw = (int)($_POST['draw'] ?? 1);
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);

// Handle both DataTables built-in search and custom form search
$search = '';
if (isset($_POST['search']['value']) && !empty($_POST['search']['value'])) {
    // DataTables built-in search
    $search = $_POST['search']['value'];
    error_log("Using DataTables search: '$search'");
} elseif (isset($_POST['search']) && !empty($_POST['search'])) {
    // Custom form search
    $search = $_POST['search'];
    error_log("Using custom form search: '$search'");
} else {
    error_log("No search parameter found");
}

error_log("Final search value: '$search'");

// Filter parameters
$type_filter = $_POST['type'] ?? '';

// Base query
$base_query = "FROM warehouse_boxes wb
               LEFT JOIN users u ON wb.created_by = u.id
               LEFT JOIN containers c ON wb.container_id = c.id";

$where_conditions = [];
$params = [];
$types = '';

// Search filter
if (!empty($search)) {
    error_log("Search filter applied: '$search'");
    $where_conditions[] = "(wb.box_number LIKE ? OR wb.box_name LIKE ? OR wb.box_type LIKE ? OR wb.quantity LIKE ? OR wb.unit_cost LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= 'sssss';
} else {
    error_log("No search filter applied");
}

// Type filter
if (!empty($type_filter)) {
    $where_conditions[] = "wb.box_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Count total records
$count_query = "SELECT COUNT(DISTINCT wb.id) as total $base_query $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];

// Main query with data
$data_query = "SELECT 
                 wb.id,
                 wb.box_number,
                 wb.box_name,
                 wb.box_type,
                 wb.quantity,
                 wb.unit_cost,
                 wb.notes,
                 wb.created_at,
                 wb.updated_at,
                 u.full_name as created_by_name,
                 c.container_number,
                 c.id as container_id
               $base_query 
               $where_clause
               ORDER BY wb.created_at DESC
               LIMIT ? OFFSET ?";

$data_params = array_merge($params, [$length, $start]);
$data_types = $types . 'ii';

$data_stmt = $conn->prepare($data_query);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$result = $data_stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    // Format values
    $created_date = date('M d, Y', strtotime($row['created_at']));

    if (is_admin() || is_inventory_manager()):
        // Action buttons
        $actions = '
        <div class="d-flex gap-1">
            <button class="btn btn-sm btn-info view-box" data-id="' . $row['id'] . '" title="View Details">
                <i class="bi bi-eye"></i>
            </button>
            
            <button class="btn btn-sm btn-primary edit-box" data-id="' . $row['id'] . '" title="Edit Box">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-danger delete-box" data-id="' . $row['id'] . '" title="Delete Box">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    ';
    else:
        $actions = '
        <div class="d-flex gap-1">
            <button class="btn btn-sm btn-info view-box" data-id="' . $row['id'] . '" title="View Details">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    ';
    endif;
    
    $data[] = [
        '', // Expand button placeholder
        htmlspecialchars($row['box_number']),
        htmlspecialchars($row['box_name']),
        htmlspecialchars($row['box_type'] ?: '-'),
        htmlspecialchars($row['container_number'] ?: '-'),
        htmlspecialchars($row['quantity'] ?: '0'),
        'CFA ' . number_format($row['unit_cost'], 2),
        $created_date,
        $actions
    ];
}

// Get statistics for dashboard
$stats_query = "SELECT COUNT(*) as total_boxes FROM warehouse_boxes";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get unique types for filters
$types_query = "SELECT DISTINCT box_type FROM warehouse_boxes WHERE box_type IS NOT NULL AND box_type != '' ORDER BY box_type";
$types_result = $conn->query($types_query);
$types = [];
while ($type_row = $types_result->fetch_assoc()) {
    $types[] = $type_row['box_type'];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $total_records,
    'recordsFiltered' => $total_records,
    'data' => $data,
    'stats' => $stats,
    'filter_options' => [
        'types' => $types
    ]
]);
