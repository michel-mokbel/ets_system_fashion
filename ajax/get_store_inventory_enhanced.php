<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$store_id = (int)($_POST['store_id'] ?? 0);
$category_id = $_POST['category_id'] ?? '';
$search = trim($_POST['search'] ?? '');
$stock_status = $_POST['stock_status'] ?? '';
$include_zero_stock = isset($_POST['include_zero_stock']) ? (bool)$_POST['include_zero_stock'] : false;

// Check access rights
$user_role = $_SESSION['user_role'] ?? '';
$user_store_id = $_SESSION['store_id'] ?? null;

// Allow viewing all stores for admin and inventory_manager
if ($user_role !== 'admin' && $user_role !== 'inventory_manager') {
    if ($store_id > 0 && $store_id !== $user_store_id) {
        echo json_encode(['success' => false, 'message' => 'Access denied to this store']);
        exit;
    }
    // For store managers without specific store filter, show only their store
    if ($store_id === 0) {
        $store_id = $user_store_id;
    }
}

// Build the query - start with assigned items, not just items with stock
$where_conditions = ["sia.is_active = 1", "i.status = 'active'"];
$params = [];
$param_types = '';

// Add store filter if specific store is selected
if ($store_id > 0) {
    $where_conditions[] = "sia.store_id = ?";
    $params[] = $store_id;
    $param_types .= 'i';
}

// Stock status filter
if (!$include_zero_stock) {
    $where_conditions[] = "COALESCE(si.current_stock, 0) > 0";
}

if ($stock_status === 'low_stock') {
    $where_conditions[] = "COALESCE(si.current_stock, 0) <= COALESCE(si.minimum_stock, 0) AND COALESCE(si.current_stock, 0) > 0";
} elseif ($stock_status === 'out_of_stock') {
    $where_conditions[] = "COALESCE(si.current_stock, 0) = 0";
} elseif ($stock_status === 'in_stock') {
    $where_conditions[] = "COALESCE(si.current_stock, 0) > COALESCE(si.minimum_stock, 0)";
}

// Category filter
if (!empty($category_id)) {
    $where_conditions[] = "i.category_id = ?";
    $params[] = $category_id;
    $param_types .= 'i';
}

// Search filter
if (!empty($search)) {
    $where_conditions[] = "(i.name LIKE ? OR i.item_code LIKE ? OR b.barcode LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
}

$where_clause = implode(' AND ', $where_conditions);

// Determine weekday: 0 (Mon) .. 6 (Sun)
$weekday = (int)date('N') - 1;

$query = "SELECT 
            si.id as store_inventory_id,
            sia.store_id,
            i.id as item_id,
            b.id as barcode_id,
            COALESCE(si.current_stock, 0) as current_stock,
            COALESCE(si.minimum_stock, 0) as minimum_stock,
            COALESCE(wp.price, COALESCE(si.selling_price, i.selling_price)) as selling_price,
            COALESCE(si.cost_price, i.base_price) as cost_price,
            si.location_in_store,
            si.aisle,
            si.shelf,
            si.bin,
            si.last_updated,
            i.name as item_name,
            i.item_code,
            i.description,
            i.size,
            i.color,
            i.brand,
            i.material,
            i.image_path,
            c.name as category_name,
            cont.container_number,
            cont.id as container_id,
            b.barcode,
            s.name as store_name,
            s.store_code,
            CASE 
                WHEN COALESCE(si.current_stock, 0) = 0 THEN 'out_of_stock'
                WHEN COALESCE(si.current_stock, 0) <= COALESCE(si.minimum_stock, 0) THEN 'low_stock'
                ELSE 'in_stock'
            END as stock_status
          FROM store_item_assignments sia
          JOIN inventory_items i ON sia.item_id = i.id
          JOIN stores s ON sia.store_id = s.id
          LEFT JOIN categories c ON i.category_id = c.id
          LEFT JOIN containers cont ON i.container_id = cont.id
          LEFT JOIN barcodes b ON i.id = b.item_id
          LEFT JOIN store_inventory si ON (sia.item_id = si.item_id AND sia.store_id = si.store_id AND b.id = si.barcode_id)
          LEFT JOIN item_weekly_prices wp ON (wp.item_id = i.id AND wp.store_id = sia.store_id AND wp.weekday = ?)
          WHERE {$where_clause}
          ORDER BY i.name ASC, i.size ASC, i.color ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    // Prepend weekday to params/types
    $param_types = 'i' . $param_types;
    array_unshift($params, $weekday);
    $stmt->bind_param($param_types, ...$params);
} else {
    $stmt->bind_param('i', $weekday);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'store_inventory_id' => $row['store_inventory_id'],
        'store_id' => $row['store_id'],
        'store_name' => $row['store_name'],
        'store_code' => $row['store_code'],
        'id' => $row['item_id'], // For compatibility with existing code
        'item_id' => $row['item_id'],
        'barcode_id' => $row['barcode_id'],
        'name' => $row['item_name'],
        'item_code' => $row['item_code'],
        'description' => $row['description'],
        'size' => $row['size'],
        'color' => $row['color'],
        'brand' => $row['brand'],
        'material' => $row['material'],
        'category_name' => $row['category_name'],
        'subcategory_name' => null, // Removed as table doesn't exist
        'container_number' => $row['container_number'],
        'container_id' => $row['container_id'],
        'barcode' => $row['barcode'],
        'current_stock' => (int)$row['current_stock'],
        'minimum_stock' => (int)$row['minimum_stock'],
        'selling_price' => (float)$row['selling_price'],
        'cost_price' => (float)$row['cost_price'],
        'location_in_store' => $row['location_in_store'],
        'aisle' => $row['aisle'],
        'shelf' => $row['shelf'],
        'bin' => $row['bin'],
        'last_updated' => $row['last_updated'],
        'stock_status' => $row['stock_status'],
        'image_path' => $row['image_path']
    ];
}

// Get store statistics based on assigned items
$stats_where = "sia.is_active = 1 AND i.status = 'active'";
$stats_params = [];
$stats_param_types = '';

if ($store_id > 0) {
    $stats_where .= " AND sia.store_id = ?";
    $stats_params[] = $store_id;
    $stats_param_types = 'i';
}

$stats_query = "SELECT 
                  COUNT(*) as total_items,
                  SUM(CASE WHEN COALESCE(si.current_stock, 0) = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
                  SUM(CASE WHEN COALESCE(si.current_stock, 0) <= COALESCE(si.minimum_stock, 0) AND COALESCE(si.current_stock, 0) > 0 THEN 1 ELSE 0 END) as low_stock_count,
                  SUM(COALESCE(si.current_stock, 0) * COALESCE(si.selling_price, i.selling_price)) as total_inventory_value
                FROM store_item_assignments sia
                JOIN inventory_items i ON sia.item_id = i.id
                LEFT JOIN barcodes b ON i.id = b.item_id
                LEFT JOIN store_inventory si ON (sia.item_id = si.item_id AND sia.store_id = si.store_id AND b.id = si.barcode_id)
                WHERE {$stats_where}";

$stats_stmt = $conn->prepare($stats_query);
if (!empty($stats_params)) {
    $stats_stmt->bind_param($stats_param_types, ...$stats_params);
}
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

echo json_encode([
    'success' => true, 
    'items' => $items,
    'total_count' => count($items),
    'statistics' => [
        'total_items' => (int)$stats['total_items'],
        'out_of_stock_count' => (int)$stats['out_of_stock_count'],
        'low_stock_count' => (int)$stats['low_stock_count'],
        'total_inventory_value' => (float)$stats['total_inventory_value']
    ]
]);
?> 