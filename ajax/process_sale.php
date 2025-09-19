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

// Only allow store_manager, sales_person, or admin
if (!in_array($role, ['store_manager', 'sales_person', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$cart = $_POST['cart'] ?? [];
if (is_string($cart)) {
    $decoded = json_decode($cart, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $cart = $decoded;
    }
}
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? 'cash');
$subtotal = floatval($_POST['subtotal'] ?? 0);
$tax = floatval($_POST['tax'] ?? 0);
$discount = floatval($_POST['discount'] ?? 0);
$total = floatval($_POST['total'] ?? 0);

// Get payment amounts
$amount_paid = floatval($_POST['amount_paid'] ?? 0);
$cash_amount = floatval($_POST['cash_amount'] ?? 0);
$mobile_amount = floatval($_POST['mobile_amount'] ?? 0);
$change_due = $amount_paid - $total;

// Debug: Log the received data
error_log("Sale Debug - Cart: " . print_r($cart, true));
error_log("Sale Debug - Cart empty: " . (empty($cart) ? 'YES' : 'NO'));
error_log("Sale Debug - Cart is array: " . (is_array($cart) ? 'YES' : 'NO'));
error_log("Sale Debug - Total: " . $total . " (Total < 0: " . ($total < 0 ? 'YES' : 'NO') . ")");

if (empty($cart) || !is_array($cart) || $total < 0) {
    $debug_message = 'Cart validation failed: ';
    if (empty($cart)) $debug_message .= 'Cart is empty. ';
    if (!is_array($cart)) $debug_message .= 'Cart is not array. ';
    if ($total < 0) $debug_message .= 'Total is negative (' . $total . '). ';
    
    error_log("Sale Debug - " . $debug_message);
    echo json_encode(['success' => false, 'message' => 'Cart is empty or invalid', 'debug' => $debug_message]);
    exit;
}

$conn->begin_transaction();
try {
    // Generate invoice number
    $invoice_number = generate_invoice_number($store_id);
    $status = 'completed';
    $payment_status = ($payment_method === 'credit') ? 'pending' : 'paid';
    $now = date('Y-m-d H:i:s');
    // Insert invoice
    $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, store_id, customer_name, customer_phone, subtotal, tax_amount, discount_amount, total_amount, amount_paid, cash_amount, mobile_amount, change_due, payment_method, payment_status, status, sales_person_id, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?)");
    $stmt->bind_param('sissddddddddssisss', $invoice_number, $store_id, $customer_name, $customer_phone, $subtotal, $tax, $discount, $total, $amount_paid, $cash_amount, $mobile_amount, $change_due, $payment_method, $payment_status, $status, $user_id, $now, $now);
    $stmt->execute();
    $invoice_id = $conn->insert_id;
    // Insert invoice items and update inventory
    foreach ($cart as $item) {
        $item_id = intval($item['item_id']);
        $barcode_id = intval($item['barcode_id']);
        $quantity = intval($item['quantity']);
        $unit_price = floatval($item['unit_price']);
        $total_price = floatval($item['total_price']);
        $discount_amount = floatval($item['discount_amount'] ?? 0);
        $discount_percentage = floatval($item['discount_percentage'] ?? 0);
        
        // Handle custom items (non-inventory items)
        $item_name = $item['item_name'] ?? null;
        $item_code = $item['item_code'] ?? null;
        $is_custom_item = isset($item['is_non_inventory']) && $item['is_non_inventory'] ? 1 : 0;
        
        // Insert invoice item with custom item support
        $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_id, barcode_id, quantity, unit_price, total_price, discount_amount, discount_percentage, item_name, item_code, is_custom_item) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiiiddddssi', $invoice_id, $item_id, $barcode_id, $quantity, $unit_price, $total_price, $discount_amount, $discount_percentage, $item_name, $item_code, $is_custom_item);
        $stmt->execute();
        
        // Update store inventory (only for regular inventory items, not custom items)
        if (!$is_custom_item) {
            update_store_inventory($store_id, $item_id, $barcode_id, $quantity, 'out', ['type' => 'sale', 'id' => $invoice_id]);
        }
    }
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Sale completed successfully', 'invoice_id' => $invoice_id]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error processing sale: ' . $e->getMessage()]);
} 
