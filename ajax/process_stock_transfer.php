<?php
/**
 * Generic stock transfer processor.
 *
 * Executes warehouse-to-store and store-to-store transfers by validating stock
 * levels, adjusting inventory, creating shipment records, and returning JSON
 * responses for the transfer UIs.
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
$item_id = intval($_POST['item_id'] ?? 0);
$source = trim($_POST['source'] ?? '');
$destination = trim($_POST['destination'] ?? '');
$quantity = intval($_POST['quantity'] ?? 0);
if ($item_id <= 0 || !$source || !$destination || $quantity <= 0 || $source === $destination) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}
// Helper to get store_id by name
function get_store_id($conn, $name) {
    if ($name === 'Warehouse') return 1;
    $stmt = $conn->prepare("SELECT id FROM stores WHERE name = ?");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? (int)$row['id'] : null;
}
function prepare_or_error($conn, $sql, $context) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL prepare error in $context: " . $conn->error);
        echo json_encode(['success' => false, 'message' => "SQL error ($context): " . $conn->error]);
        exit;
    }
    return $stmt;
}
$source_id = get_store_id($conn, $source);
$dest_id = get_store_id($conn, $destination);
if (!$source_id || !$dest_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid source or destination']);
    exit;
}
// Get barcode_id for this item (pick first)
$barcode_stmt = prepare_or_error($conn, "SELECT id FROM barcodes WHERE item_id = ? LIMIT 1", 'barcode lookup');
$barcode_stmt->bind_param('i', $item_id);
$barcode_stmt->execute();
$barcode_result = $barcode_stmt->get_result();
$barcode_id = $barcode_result->num_rows ? $barcode_result->fetch_assoc()['id'] : null;
if (!$barcode_id) {
    echo json_encode(['success' => false, 'message' => 'No barcode found for this item.']);
    exit;
}
$conn->begin_transaction();
try {
    // 1. Check source stock
    $check_stmt = prepare_or_error($conn, "SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ? FOR UPDATE", 'check source stock');
    $check_stmt->bind_param('iii', $source_id, $item_id, $barcode_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $source_stock = $check_result->num_rows ? (int)$check_result->fetch_assoc()['current_stock'] : 0;
    if ($source_stock < $quantity) throw new Exception('Not enough stock in source');
    // 2. Deduct from source
    $update_stmt = prepare_or_error($conn, "UPDATE store_inventory SET current_stock = current_stock - ? WHERE store_id = ? AND item_id = ? AND barcode_id = ?", 'deduct source');
    $update_stmt->bind_param('iiii', $quantity, $source_id, $item_id, $barcode_id);
    $update_stmt->execute();
    // 3. Add to destination
    $check_dest_stmt = prepare_or_error($conn, "SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ? FOR UPDATE", 'check dest stock');
    $check_dest_stmt->bind_param('iii', $dest_id, $item_id, $barcode_id);
    $check_dest_stmt->execute();
    $check_dest_result = $check_dest_stmt->get_result();
    if ($check_dest_result->num_rows) {
        $update_dest_stmt = prepare_or_error($conn, "UPDATE store_inventory SET current_stock = current_stock + ? WHERE store_id = ? AND item_id = ? AND barcode_id = ?", 'update dest');
        $update_dest_stmt->bind_param('iiii', $quantity, $dest_id, $item_id, $barcode_id);
        $update_dest_stmt->execute();
    } else {
        // Get barcode_id for this item (pick first)
        $price_stmt = prepare_or_error($conn, "SELECT selling_price FROM store_inventory WHERE store_id = 1 AND item_id = ? AND barcode_id = ?", 'get warehouse price');
        $price_stmt->bind_param('ii', $item_id, $barcode_id);
        $price_stmt->execute();
        $price_result = $price_stmt->get_result();
        if ($price_result->num_rows) {
            $selling_price = floatval($price_result->fetch_assoc()['selling_price']);
        } else {
            // fallback to selling_price
            $price_stmt2 = prepare_or_error($conn, "SELECT selling_price FROM inventory_items WHERE id = ?", 'get selling price');
            $price_stmt2->bind_param('i', $item_id);
            $price_stmt2->execute();
            $price_result2 = $price_stmt2->get_result();
            $selling_price = $price_result2->num_rows ? floatval($price_result2->fetch_assoc()['selling_price']) : 0.0;
        }
        $insert_dest_stmt = prepare_or_error($conn, "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price) VALUES (?, ?, ?, ?, ?)", 'insert dest');
        $insert_dest_stmt->bind_param('iiiid', $dest_id, $item_id, $barcode_id, $quantity, $selling_price);
        $insert_dest_stmt->execute();
    }
    // 4. Log transfer
    $log_stmt = prepare_or_error($conn, "INSERT INTO stock_transfers (item_id, source, destination, quantity, transferred_by, transferred_at) VALUES (?, ?, ?, ?, ?, NOW())", 'log transfer');
    $user = $_SESSION['username'] ?? 'system';
    $log_stmt->bind_param('issis', $item_id, $source, $destination, $quantity, $user);
    $log_stmt->execute();
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    error_log('Stock transfer error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 