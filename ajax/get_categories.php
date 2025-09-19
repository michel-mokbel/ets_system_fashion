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

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'error' => 'Invalid security token',
        'draw' => intval($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

try {
// Get DataTables parameters
$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';

// Get filter parameters
$name = $_POST['name'] ?? '';

    // Get total count of all categories
$total_query = "SELECT COUNT(*) as total FROM categories";
$total_stmt = $conn->prepare($total_query);
    if ($total_stmt === false) {
        throw new Exception("Failed to prepare total count query: " . $conn->error);
    }
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_records = $total_result->fetch_assoc()['total'];

    // Build query with subcategory counts
$where = [];
$params = [];
$param_types = '';

    // Base query - get categories with subcategory counts
    $query = "SELECT 
                c.id,
                c.name,
                c.description,
                c.created_at,
                COUNT(sc.id) as subcategory_count,
                COUNT(DISTINCT i.id) as item_count
          FROM categories c 
              LEFT JOIN subcategories sc ON c.id = sc.category_id
              LEFT JOIN inventory_items i ON c.id = i.category_id
              ";

// Apply search
if (!empty($search)) {
        $where[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
        $param_types .= 'ss';
}

// Apply name filter
if (!empty($name)) {
    $where[] = "c.name LIKE ?";
    $params[] = "%$name%";
    $param_types .= 's';
}

    // Add WHERE clause if conditions exist
if (!empty($where)) {
    $query .= " WHERE " . implode(' AND ', $where);
}

    // Group by category
    $query .= " GROUP BY c.id, c.name, c.description, c.created_at";

// Get filtered count
$filtered_count = $total_records; // Default to total if no filters
if (!empty($where)) {
        $count_query = "SELECT COUNT(DISTINCT c.id) as total FROM categories c";
        if (!empty($where)) {
    $count_query .= " WHERE " . implode(' AND ', $where);
        }
    
    $count_stmt = $conn->prepare($count_query);
        if ($count_stmt === false) {
            throw new Exception("Failed to prepare filtered count query: " . $conn->error);
        }
        
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $filtered_count = $count_result->fetch_assoc()['total'];
}

// Add sorting
$order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 0;
$order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

// Columns for sorting
    $columns = ['c.name', 'c.description', 'subcategory_count', 'item_count', 'c.created_at'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
} else {
    $query .= " ORDER BY c.name ASC";
}

// Add pagination
$query .= " LIMIT ?, ?";
$params[] = $start;
$params[] = $length;
$param_types .= 'ii';

    // Prepare and execute the final query
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare main query: " . $conn->error);
    }
    
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
            'description' => htmlspecialchars($row['description'] ?? ''),
            'subcategory_count' => intval($row['subcategory_count']),
            'item_count' => intval($row['item_count']),
            'created_at' => $row['created_at'],
            'created_at_formatted' => date('Y-m-d H:i', strtotime($row['created_at']))
        ];
    }

    $response = [
        'draw' => intval($draw),
        'recordsTotal' => intval($total_records),
        'recordsFiltered' => intval($filtered_count),
        'data' => $data
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_categories.php: " . $e->getMessage());
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'draw' => intval($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
} 