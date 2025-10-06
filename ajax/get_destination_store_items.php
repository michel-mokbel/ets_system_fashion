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
    echo json_encode(['success' => false, 'message' => 'Not authenticated', 'debug' => [
        'session_status' => session_status(),
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? 'not set'
    ]]);
    exit;
}

// Check if user has access to transfers
if (!can_access_transfers()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get parameters
$store_id = (int)($_GET['store_id'] ?? $_POST['store_id'] ?? 0);
$search = $_GET['search'] ?? $_POST['search'] ?? '';
$category_id = $_GET['category_id'] ?? $_POST['category_id'] ?? '';
$container_id = $_GET['container_id'] ?? $_POST['container_id'] ?? '';

if ($store_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid store ID']);
    exit;
}

// Build the query to get items that are assigned to the destination store
// This includes both items with stock and items with zero stock (but assigned)
$query = "SELECT DISTINCT
            i.id as item_id,
            i.item_code,
            i.name as item_name,
            i.description,
            i.selling_price,
            i.base_price,
            c.name as category_name,
            b.id as barcode_id,
            b.barcode,
            COALESCE(si.current_stock, 0) as current_stock,
            COALESCE(si.selling_price, i.selling_price) as store_selling_price,
            COALESCE(si.cost_price, i.base_price) as store_cost_price,
            cont.id as container_id,
            cont.container_number
          FROM inventory_items i
          INNER JOIN store_item_assignments sia ON i.id = sia.item_id
          LEFT JOIN categories c ON i.category_id = c.id
          LEFT JOIN barcodes b ON i.id = b.item_id
          LEFT JOIN store_inventory si ON (i.id = si.item_id AND si.store_id = ? AND b.id = si.barcode_id)
          LEFT JOIN containers cont ON i.container_id = cont.id
          WHERE sia.store_id = ? 
            AND sia.is_active = 1
            AND i.status = 'active'";

$params = [$store_id, $store_id];
$types = 'ii';

// Add search filter
if (!empty($search)) {
    $query .= " AND (i.item_code LIKE ? OR i.name LIKE ? OR b.barcode LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

// Add category filter
if (!empty($category_id)) {
    $query .= " AND i.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

// Container filter
if (!empty($container_id)) {
    $query .= " AND i.container_id = ?";
    $params[] = (int)$container_id;
    $types .= 'i';
}

$query .= " ORDER BY i.name ASC, b.barcode ASC";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database execute error: ' . $stmt->error]);
    exit;
}

$result = $stmt->get_result();
if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database result error: ' . $stmt->error]);
    exit;
}

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'item_id' => $row['item_id'],
        'barcode_id' => $row['barcode_id'],
        'item_code' => $row['item_code'],
        'item_name' => $row['item_name'],
        'description' => $row['description'],
        'barcode' => $row['barcode'],
        'current_stock' => (int)$row['current_stock'],
        'selling_price' => (float)$row['store_selling_price'],
        'cost_price' => (float)$row['store_cost_price'],
        'category_name' => $row['category_name'],
        'container_id' => $row['container_id'],
        'container_number' => $row['container_number']
    ];
}

// Get store information
$store_query = "SELECT name, store_code FROM stores WHERE id = ?";
$store_stmt = $conn->prepare($store_query);
$store_stmt->bind_param('i', $store_id);
$store_stmt->execute();
$store_result = $store_stmt->get_result();
$store_info = $store_result->fetch_assoc();

// Build distinct container list for filter UI
$containers = [];
$containers_map = [];
foreach ($items as $it) {
    if (!empty($it['container_id'])) {
        $containers_map[$it['container_id']] = $it['container_number'];
    }
}
foreach ($containers_map as $id => $num) {
    $containers[] = ['id' => (int)$id, 'container_number' => $num];
}

echo json_encode([
    'success' => true,
    'items' => $items,
    'store_info' => $store_info,
    'containers' => $containers,
    'total_count' => count($items)
]);
?> 