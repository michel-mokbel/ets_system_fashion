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

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$role = $_SESSION['user_role'];

if (!in_array($role, ['store_manager', 'sales_person', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$original_invoice_id = intval($_POST['original_invoice_id'] ?? 0);
$return_reason = trim($_POST['return_reason'] ?? '');
$return_type = $_POST['return_type'] ?? 'partial';
$notes = trim($_POST['notes'] ?? '');
$items = $_POST['items'] ?? [];
if (is_string($items)) {
    $decoded = json_decode($items, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $items = $decoded;
    }
}
if ($original_invoice_id <= 0 || empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Calculate total refund
$total_amount = 0;
foreach ($items as $item) {
    $total_amount += floatval($item['unit_price']) * intval($item['quantity_returned']);
}

$conn->begin_transaction();
try {
    // Generate return number
    $return_number = generate_return_number($store_id);
    $status = 'processed';
    $now = date('Y-m-d H:i:s');
    // Insert return
    $stmt = $conn->prepare("INSERT INTO returns (return_number, original_invoice_id, store_id, return_reason, total_amount, return_type, status, processed_by, return_date, processed_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('siisdssssss', $return_number, $original_invoice_id, $store_id, $return_reason, $total_amount, $return_type, $status, $user_id, $now, $now, $notes);
    $stmt->execute();
    $return_id = $conn->insert_id;
    // Insert return items and update inventory
    foreach ($items as $item) {
        $invoice_item_id = intval($item['invoice_item_id']);
        $item_id = intval($item['item_id']);
        $barcode_id = intval($item['barcode_id']);
        $quantity_returned = intval($item['quantity_returned']);
        $unit_price = floatval($item['unit_price']);
        $total_refund = $unit_price * $quantity_returned;
        $condition_notes = $item['condition_notes'] ?? '';
        // Insert return item
        $stmt = $conn->prepare("INSERT INTO return_items (return_id, invoice_item_id, item_id, barcode_id, quantity_returned, unit_price, total_refund, condition_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiiiidds', $return_id, $invoice_item_id, $item_id, $barcode_id, $quantity_returned, $unit_price, $total_refund, $condition_notes);
        $stmt->execute();
        // Update store inventory (add back returned quantity)
        update_store_inventory($store_id, $item_id, $barcode_id, $quantity_returned, 'in', ['type' => 'return', 'id' => $return_id]);
    }
    $conn->commit();
    // Update invoice payment_status
    $new_status = ($return_type === 'full') ? 'refunded' : 'partial_refund';
    $update_stmt = $conn->prepare("UPDATE invoices SET payment_status = ? WHERE id = ?");
    $update_stmt->bind_param('si', $new_status, $original_invoice_id);
    $update_stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Return processed successfully', 'return_id' => $return_id, 'return_number' => $return_number]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error processing return: ' . $e->getMessage()]);
} 