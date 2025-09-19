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
$term = trim($_POST['term'] ?? '');
$store_id = $_SESSION['store_id'] ?? null;

// Debug: Log search parameters (remove in production)
// error_log("Search Items Debug - Term: '$term', Store ID: '$store_id'");

if (empty($term)) {
    echo json_encode(['success' => true, 'items' => []]);
    exit;
}

if (!$store_id) {
    echo json_encode(['success' => false, 'message' => 'No store ID in session']);
    exit;
}
// Search items by name or code, join barcodes and store_inventory for price/stock
// First try with store assignments, then fallback to all items if none found
$query = "SELECT i.id, i.name, i.item_code, b.id as barcode_id, b.barcode, COALESCE(si.selling_price, i.base_price) as price, COALESCE(si.current_stock, 0) as current_stock
          FROM inventory_items i
          LEFT JOIN store_item_assignments sia ON i.id = sia.item_id AND sia.store_id = ? AND sia.is_active = 1
          LEFT JOIN barcodes b ON i.id = b.item_id
          LEFT JOIN store_inventory si ON i.id = si.item_id AND si.store_id = ? AND si.barcode_id = b.id
          WHERE (i.name LIKE ? OR i.item_code LIKE ?) 
            AND i.status = 'active' 
          GROUP BY i.id, b.id
          ORDER BY sia.item_id IS NOT NULL DESC, i.name ASC LIMIT 20";
$stmt = $conn->prepare($query);
$like = "%$term%";
$stmt->bind_param('iiss', $store_id, $store_id, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'item_code' => $row['item_code'],
        'barcode_id' => $row['barcode_id'],
        'barcode' => $row['barcode'],
        'selling_price' => $row['price'],
        'current_stock' => $row['current_stock']
    ];
}

// Debug: Log results (remove in production)
// error_log("Search Items Debug - Found " . count($items) . " items");

echo json_encode(['success' => true, 'items' => $items]); 