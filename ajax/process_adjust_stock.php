<?php
/**
 * Handles manual stock adjustment requests.
 *
 * Validates adjustment reasons, updates store inventory quantities, logs the
 * movement in `inventory_transactions`, and returns JSON responses for the
 * adjustment UI.
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
$quantity = intval($_POST['quantity'] ?? 0);
$transaction_type = $_POST['transaction_type'] ?? '';
$notes = $_POST['notes'] ?? '';
$store_id = 1; // Main warehouse

// Batch incoming stock
if (isset($_POST['action']) && $_POST['action'] === 'batch_incoming') {
    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items provided.']);
        exit;
    }
    $results = [];
    foreach ($items as $entry) {
        $item_id = intval($entry['item_id'] ?? 0);
        $quantity = intval($entry['quantity'] ?? 0);
        $notes = $entry['notes'] ?? '';
        if ($item_id <= 0 || $quantity <= 0) {
            $results[] = ['item_id' => $item_id, 'success' => false, 'message' => 'Invalid item or quantity'];
            continue;
        }
        // Get barcode_id for this item in warehouse (if multiple, pick first)
        $barcode_stmt = $conn->prepare("SELECT id FROM barcodes WHERE item_id = ? LIMIT 1");
        $barcode_stmt->bind_param('i', $item_id);
        $barcode_stmt->execute();
        $barcode_result = $barcode_stmt->get_result();
        $barcode_id = $barcode_result->num_rows ? $barcode_result->fetch_assoc()['id'] : null;
        if (!$barcode_id) {
            $results[] = ['item_id' => $item_id, 'success' => false, 'message' => 'No barcode found'];
            continue;
        }
        // Get current stock
        $check_stmt = $conn->prepare("SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
        $check_stmt->bind_param('iii', $store_id, $item_id, $barcode_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $current_stock = $check_result->num_rows ? (int)$check_result->fetch_assoc()['current_stock'] : 0;
        $new_stock = $current_stock + $quantity;
        if ($check_result->num_rows) {
            $update_stmt = $conn->prepare("UPDATE store_inventory SET current_stock = ?, last_updated = NOW() WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
            $update_stmt->bind_param('iiii', $new_stock, $store_id, $item_id, $barcode_id);
            $update_stmt->execute();
        } else {
            $price_stmt = $conn->prepare("SELECT base_price FROM inventory_items WHERE id = ?");
            $price_stmt->bind_param('i', $item_id);
            $price_stmt->execute();
            $price_result = $price_stmt->get_result();
            $base_price = $price_result->num_rows ? floatval($price_result->fetch_assoc()['base_price']) : 0.0;
            $insert_stmt = $conn->prepare("INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param('iiiid', $store_id, $item_id, $barcode_id, $new_stock, $base_price);
            $insert_stmt->execute();
        }
        $user_id = $_SESSION['user_id'] ?? null;
        $trans_stmt = $conn->prepare("INSERT INTO inventory_transactions (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, user_id, notes, transaction_date) VALUES (?, ?, ?, 'in', ?, 'manual', ?, ?, NOW())");
        $trans_stmt->bind_param('iiiiss', $store_id, $item_id, $barcode_id, $quantity, $user_id, $notes);
        $trans_stmt->execute();
        $results[] = ['item_id' => $item_id, 'success' => true];
    }
    $all_success = array_reduce($results, function($carry, $r) { return $carry && $r['success']; }, true);
    echo json_encode(['success' => $all_success, 'results' => $results]);
    exit;
}

if ($item_id <= 0 || $quantity <= 0 || !in_array($transaction_type, ['in', 'out', 'adjustment'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}
// Get barcode_id for this item in warehouse (if multiple, pick first)
$barcode_stmt = $conn->prepare("SELECT id FROM barcodes WHERE item_id = ? LIMIT 1");
$barcode_stmt->bind_param('i', $item_id);
$barcode_stmt->execute();
$barcode_result = $barcode_stmt->get_result();
$barcode_id = $barcode_result->num_rows ? $barcode_result->fetch_assoc()['id'] : null;
if (!$barcode_id) {
    echo json_encode(['success' => false, 'message' => 'No barcode found for this item.']);
    exit;
}
// Get current stock
$check_stmt = $conn->prepare("SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
$check_stmt->bind_param('iii', $store_id, $item_id, $barcode_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$current_stock = $check_result->num_rows ? (int)$check_result->fetch_assoc()['current_stock'] : 0;
$new_stock = $current_stock;
switch ($transaction_type) {
    case 'in':
        $new_stock = $current_stock + $quantity;
        break;
    case 'out':
        if ($quantity > $current_stock) {
            echo json_encode(['success' => false, 'message' => 'Cannot remove more than current stock']);
            exit;
        }
        $new_stock = $current_stock - $quantity;
        break;
    case 'adjustment':
        $new_stock = $quantity;
        $quantity = abs($new_stock - $current_stock);
        $transaction_type = ($new_stock > $current_stock) ? 'in' : 'out';
        break;
}
// Update or insert store_inventory
if ($check_result->num_rows) {
    $update_stmt = $conn->prepare("UPDATE store_inventory SET current_stock = ?, last_updated = NOW() WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
    $update_stmt->bind_param('iiii', $new_stock, $store_id, $item_id, $barcode_id);
    $update_stmt->execute();
} else {
    // Get base price for this item
    $price_stmt = $conn->prepare("SELECT base_price FROM inventory_items WHERE id = ?");
    $price_stmt->bind_param('i', $item_id);
    $price_stmt->execute();
    $price_result = $price_stmt->get_result();
    $base_price = $price_result->num_rows ? floatval($price_result->fetch_assoc()['base_price']) : 0.0;
    $insert_stmt = $conn->prepare("INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price) VALUES (?, ?, ?, ?, ?)");
    $insert_stmt->bind_param('iiiid', $store_id, $item_id, $barcode_id, $new_stock, $base_price);
    $insert_stmt->execute();
}
// Log transaction
$user_id = $_SESSION['user_id'] ?? null;
$trans_stmt = $conn->prepare("INSERT INTO inventory_transactions (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, user_id, notes, transaction_date) VALUES (?, ?, ?, ?, ?, 'manual', ?, ?, NOW())");
$trans_stmt->bind_param('iiisiis', $store_id, $item_id, $barcode_id, $transaction_type, $quantity, $user_id, $notes);
$trans_stmt->execute();
echo json_encode(['success' => true, 'message' => 'Stock adjusted successfully']); 