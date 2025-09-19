<?php
/**
 * Lists source store items available for transfer.
 *
 * Validates available quantities, respects store assignments, and returns JSON
 * so the transfer workflow can build shipments from eligible stock.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!can_access_transfers()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$store_id = (int)($_GET['store_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Debug logging
error_log("Store items request - Store ID: $store_id, Search: '$search'");

if ($store_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid store ID']);
    exit;
}

// Build query to get items with stock from the store
$where_conditions = ["si.store_id = ?", "si.current_stock > 0", "i.status = 'active'"];
$params = [$store_id];
$param_types = 'i';

if (!empty($search)) {
    $where_conditions[] = "(i.name LIKE ? OR i.item_code LIKE ? OR b.barcode LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
}

$where_clause = implode(' AND ', $where_conditions);

$query = "SELECT 
            i.id as item_id,
            i.name as item_name,
            i.item_code,
            b.id as barcode_id,
            b.barcode,
            si.current_stock,
            si.selling_price,
            si.cost_price,
            c.name as category_name
          FROM store_inventory si
          JOIN inventory_items i ON si.item_id = i.id
          JOIN barcodes b ON si.barcode_id = b.id
          LEFT JOIN categories c ON i.category_id = c.id
          WHERE $where_clause
          ORDER BY i.name ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'item_id' => (int)$row['item_id'],
        'barcode_id' => (int)$row['barcode_id'],
        'item_name' => $row['item_name'],
        'item_code' => $row['item_code'],
        'barcode' => $row['barcode'],
        'current_stock' => (int)$row['current_stock'],
        'selling_price' => (float)$row['selling_price'],
        'cost_price' => (float)$row['cost_price'],
        'category_name' => $row['category_name']
    ];
}

// Debug logging
error_log("Returning " . count($items) . " items for store $store_id");

echo json_encode(['success' => true, 'items' => $items]);
?>
