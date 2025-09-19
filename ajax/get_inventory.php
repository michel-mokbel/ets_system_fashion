<?php
/**
 * Comprehensive inventory listing endpoint.
 *
 * Powers the admin inventory DataTable by joining catalog metadata, store stock
 * levels, and container references. Applies search and filter criteria, enforces
 * store scoping for non-admins, and outputs DataTables-friendly JSON.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['draw' => intval($_POST['draw'] ?? 1), 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
    exit;
}

$draw = $_POST['draw'] ?? 1;
$start = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;
$search = $_POST['search']['value'] ?? '';
$category = $_POST['category'] ?? '';
$filter_item_code = trim($_POST['item_code'] ?? '');
$filter_name = trim($_POST['name'] ?? '');
$stock_status = $_POST['stock_status'] ?? '';
$status = $_POST['status'] ?? '';
$store_id = $_POST['store_id'] ?? '';

$user_store_id = $_SESSION['store_id'] ?? null;
if (!is_admin() && !is_inventory_manager() && $user_store_id) {
    $store_id = $user_store_id;
}

$where = [];
$params = [];
$param_types = '';

if ($store_id) {
    $query = "SELECT i.id, i.item_code, i.name, c.name as category_name, COALESCE(si.current_stock, 0) as current_stock, COALESCE(si.minimum_stock, 0) as minimum_stock, COALESCE(si.selling_price, i.selling_price) as selling_price, i.base_price, i.status, s.name as store_name, si.location_in_store, b.barcode, cont.container_number, cont.id as container_id
              FROM inventory_items i
              LEFT JOIN categories c ON i.category_id = c.id
              LEFT JOIN store_inventory si ON i.id = si.item_id AND si.store_id = ?
              LEFT JOIN stores s ON si.store_id = s.id
              LEFT JOIN barcodes b ON i.id = b.item_id AND si.barcode_id = b.id
              LEFT JOIN containers cont ON i.container_id = cont.id";
    $params[] = $store_id;
    $param_types .= 'i';
} else {
    $query = "SELECT i.id, i.item_code, i.name, c.name as category_name, COALESCE(SUM(si.current_stock), 0) as current_stock, COALESCE(AVG(si.minimum_stock), 0) as minimum_stock, COALESCE(AVG(si.selling_price), i.selling_price) as selling_price, i.base_price, i.status, '' as store_name, '' as location_in_store, GROUP_CONCAT(DISTINCT b.barcode) as barcode, cont.container_number, cont.id as container_id
              FROM inventory_items i
              LEFT JOIN categories c ON i.category_id = c.id
              LEFT JOIN store_inventory si ON i.id = si.item_id
              LEFT JOIN barcodes b ON i.id = b.item_id
              LEFT JOIN containers cont ON i.container_id = cont.id
              GROUP BY i.id, i.item_code, i.name, c.name, i.base_price, i.status, cont.container_number, cont.id";
}

if (!empty($search)) {
    $where[] = "(i.item_code LIKE ? OR i.name LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}
// Additional precise filters
if ($filter_item_code !== '') {
    $where[] = "i.item_code LIKE ?";
    $params[] = "%$filter_item_code%";
    $param_types .= 's';
}
if ($filter_name !== '') {
    $where[] = "i.name LIKE ?";
    $params[] = "%$filter_name%";
    $param_types .= 's';
}
if (!empty($category)) {
    $where[] = "i.category_id = ?";
    $params[] = $category;
    $param_types .= 'i';
}
if (!empty($status)) {
    $where[] = "i.status = ?";
    $params[] = $status;
    $param_types .= 's';
}
if (!empty($where)) {
    $query .= ($store_id ? ' WHERE ' : ' HAVING ') . implode(' AND ', $where);
}
if (!empty($stock_status)) {
    $stock_where = [];
    switch ($stock_status) {
        case 'low':
            $stock_where[] = "current_stock <= minimum_stock AND current_stock > 0";
            break;
        case 'out':
            $stock_where[] = "current_stock = 0";
            break;
        case 'normal':
            $stock_where[] = "current_stock > minimum_stock";
            break;
    }
    if (!empty($stock_where)) {
        $query .= (empty($where) ? ($store_id ? ' WHERE ' : ' HAVING ') : ' AND ') . implode(' AND ', $stock_where);
    }
}
$order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 1;
$order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';
$columns = ['i.item_code', 'i.name', 'category_name', 'current_stock', 'minimum_stock', 'selling_price', 'i.status'];
if (isset($columns[$order_column])) {
    $query .= " ORDER BY " . $columns[$order_column] . " $order_dir";
} else {
    $query .= " ORDER BY i.name ASC";
}
$query .= " LIMIT ?, ?";
$params[] = $start;
$params[] = $length;
$param_types .= 'ii';
$stmt = $conn->prepare($query);
if ($param_types) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$data = [];
while ($row = $result->fetch_assoc()) {
    $stock_badge = '';
    if ($row['current_stock'] == 0) {
        $stock_badge = '<span class="badge bg-danger">Out of Stock</span>';
    } elseif ($row['current_stock'] <= $row['minimum_stock']) {
        $stock_badge = '<span class="badge bg-warning">Low Stock</span>';
    } else {
        $stock_badge = '<span class="badge bg-success">In Stock</span>';
    }
    $data[] = [
        'id' => $row['id'],
        'item_code' => htmlspecialchars($row['item_code']),
        'name' => htmlspecialchars($row['name']),
        'category' => htmlspecialchars($row['category_name'] ?? 'Uncategorized'),
        'container_number' => htmlspecialchars($row['container_number'] ?? '-'),
        'stock_status' => $stock_badge,
        'current_stock' => intval($row['current_stock']),
        'minimum_stock' => intval($row['minimum_stock']),
        'base_price' => floatval($row['base_price']),
        'selling_price' => floatval($row['selling_price']),
        'location' => htmlspecialchars($row['location_in_store'] ?? ''),
        'store' => htmlspecialchars($row['store_name'] ?? ''),
        'status' => $row['status']
    ];
}
// Get total count
$count_query = $store_id ? "SELECT COUNT(*) as total FROM inventory_items WHERE status = 'active'" : "SELECT COUNT(*) as total FROM inventory_items";
$count_result = $conn->query($count_query);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
echo json_encode([
    'draw' => intval($draw),
    'recordsTotal' => intval($total_records),
    'recordsFiltered' => intval($total_records),
    'data' => $data
]);