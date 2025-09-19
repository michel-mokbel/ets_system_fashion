<?php
/**
 * Store-level inventory feed.
 *
 * Provides item stock levels, barcode assignments, and status flags for a
 * specific store. Used across store dashboards and adjustment dialogs while
 * enforcing access control.
 */
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

// Accept store_id from request, fallback to session store_id
$store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : ($_SESSION['store_id'] ?? null);

if (!$store_id) {
    echo json_encode(['success' => false, 'message' => 'No store selected']);
    exit;
}

// Role-based access control: store managers can only access their assigned store, admins and inventory managers can access all stores
$user_role = $_SESSION['user_role'] ?? '';
$session_store_id = $_SESSION['store_id'] ?? null;

if ($user_role === 'store_manager' && $session_store_id && $store_id != $session_store_id) {
    echo json_encode(['success' => false, 'message' => 'Access denied to this store']);
    exit;
}
// Get additional filters
$category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
$search = isset($_POST['search']) ? trim($_POST['search']) : '';

// Build query with filters
$where_conditions = ["i.status = 'active'"];
$params = [$store_id];
$param_types = 'i';

if ($category_id) {
    $where_conditions[] = "i.category_id = ?";
    $params[] = $category_id;
    $param_types .= 'i';
}

if ($search) {
    $where_conditions[] = "(i.name LIKE ? OR i.item_code LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT i.id, i.name, i.item_code, b.id as barcode_id, COALESCE(si.selling_price, i.base_price) as selling_price, COALESCE(si.current_stock, 0) as current_stock, c.container_number, c.id as container_id
          FROM inventory_items i
          INNER JOIN store_item_assignments sia ON i.id = sia.item_id
          LEFT JOIN barcodes b ON i.id = b.item_id
          LEFT JOIN store_inventory si ON i.id = si.item_id AND si.store_id = ? AND si.barcode_id = b.id
          LEFT JOIN containers c ON i.container_id = c.id
          WHERE $where_clause AND sia.store_id = ? AND sia.is_active = 1
          GROUP BY i.id, b.id
          ORDER BY i.name ASC";

// Add store_id parameter for store assignment check
$params[] = $store_id;
$param_types .= 'i';

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'item_code' => $row['item_code'],
        'barcode_id' => $row['barcode_id'],
        'selling_price' => $row['selling_price'],
        'current_stock' => $row['current_stock'],
        'container_number' => $row['container_number'],
        'container_id' => $row['container_id']
    ];
}
echo json_encode(['success' => true, 'items' => $items]); 