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
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if user has access to transfers
if (!can_access_transfers()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get parameters
$search = $_GET['search'] ?? $_POST['search'] ?? '';
$type_filter = $_GET['type'] ?? $_POST['type'] ?? '';

// Base query
$base_query = "FROM warehouse_boxes wb
               LEFT JOIN users u ON wb.created_by = u.id
               LEFT JOIN containers c ON wb.container_id = c.id";

$where_conditions = [];
$params = [];
$types = '';

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(wb.box_number LIKE ? OR wb.box_name LIKE ? OR wb.box_type LIKE ? OR wb.quantity LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
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

// Main query to get boxes
$data_query = "SELECT 
                 wb.id,
                 wb.box_number,
                 wb.box_name,
                 wb.box_type,
                 wb.quantity,
                 wb.notes,
                 wb.created_at,
                 wb.updated_at,
                 u.full_name as created_by_name,
                 c.container_number,
                 c.id as container_id
               $base_query 
               $where_clause
               ORDER BY wb.box_number ASC";

$data_stmt = $conn->prepare($data_query);
if (!empty($params)) {
    $data_stmt->bind_param($types, ...$params);
}
$data_stmt->execute();
$result = $data_stmt->get_result();

$boxes = [];
while ($row = $result->fetch_assoc()) {
    $boxes[] = [
        'id' => $row['id'],
        'box_number' => $row['box_number'],
        'box_name' => $row['box_name'],
        'box_type' => $row['box_type'],
        'quantity' => $row['quantity'],
        'notes' => $row['notes'],
        'created_at' => $row['created_at'],
        'created_by_name' => $row['created_by_name'],
        'formatted_date' => date('M d, Y', strtotime($row['created_at'])),
        'container_number' => $row['container_number'],
        'container_id' => $row['container_id']
    ];
}

// Get unique types for filter
$types_query = "SELECT DISTINCT box_type FROM warehouse_boxes WHERE box_type IS NOT NULL AND box_type != '' ORDER BY box_type";
$types_result = $conn->query($types_query);
$box_types = [];
while ($type_row = $types_result->fetch_assoc()) {
    $box_types[] = $type_row['box_type'];
}

echo json_encode([
    'success' => true,
    'boxes' => $boxes,
    'box_types' => $box_types,
    'total_count' => count($boxes)
]);
?> 