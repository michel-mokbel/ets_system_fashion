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

$invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
$invoice_number = trim($_POST['invoice_number'] ?? '');

if (!$invoice_id && !$invoice_number) {
    echo json_encode(['success' => false, 'message' => 'No invoice id or number provided']);
    exit;
}

// Fetch invoice with store and sales person info
if ($invoice_id) {
    $stmt = $conn->prepare("SELECT i.*, s.name as store_name, s.id as store_id, u.id as sales_person_id, u.full_name as sales_person_name FROM invoices i LEFT JOIN stores s ON i.store_id = s.id LEFT JOIN users u ON i.sales_person_id = u.id WHERE i.id = ? LIMIT 1");
    $stmt->bind_param('i', $invoice_id);
} else {
    $stmt = $conn->prepare("SELECT i.*, s.name as store_name, s.id as store_id, u.id as sales_person_id, u.full_name as sales_person_name FROM invoices i LEFT JOIN stores s ON i.store_id = s.id LEFT JOIN users u ON i.sales_person_id = u.id WHERE i.invoice_number = ? LIMIT 1");
    $stmt->bind_param('s', $invoice_number);
}
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
    exit;
}
$invoice = $result->fetch_assoc();
$invoice_id = $invoice['id'];

// Get invoice items - Handle both regular and custom items
$stmt = $conn->prepare("
    SELECT 
        ii.id as invoice_item_id, 
        ii.item_id, 
        ii.barcode_id, 
        ii.quantity, 
        ii.unit_price, 
        ii.total_price,
        ii.discount_amount,
        ii.discount_percentage,
        ii.is_custom_item,
        CASE 
            WHEN ii.is_custom_item = 1 THEN ii.item_name
            ELSE COALESCE(i.name, CONCAT('Item ID: ', ii.item_id))
        END as name,
        CASE 
            WHEN ii.is_custom_item = 1 THEN ii.item_code
            ELSE COALESCE(i.item_code, CONCAT('CODE-', ii.item_id))
        END as item_code,
        b.barcode
    FROM invoice_items ii 
    LEFT JOIN inventory_items i ON ii.item_id = i.id AND ii.is_custom_item = 0
    LEFT JOIN barcodes b ON ii.barcode_id = b.id AND ii.is_custom_item = 0
    WHERE ii.invoice_id = ?
    ORDER BY ii.id ASC
");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}

// Debug: Log invoice items query results
error_log("Invoice Items Debug - Invoice ID: " . $invoice_id);
error_log("Invoice Items Debug - Items found: " . count($items));
error_log("Invoice Items Debug - Items: " . print_r($items, true));

// If no items found with JOIN, try to get items without JOIN to see if the issue is with inventory_items
if (empty($items)) {
    error_log("Invoice Items Debug - No items found with JOIN, checking invoice_items table directly");
    $debug_stmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $debug_stmt->bind_param('i', $invoice_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    $debug_items = [];
    while ($debug_row = $debug_result->fetch_assoc()) {
        $debug_items[] = $debug_row;
    }
    error_log("Invoice Items Debug - Raw invoice_items: " . print_r($debug_items, true));
    
    // If we have invoice_items but the JOIN failed, create items with basic info
    if (!empty($debug_items)) {
        error_log("Invoice Items Debug - Creating fallback items");
        foreach ($debug_items as $debug_item) {
            $items[] = [
                'invoice_item_id' => $debug_item['id'],
                'item_id' => $debug_item['item_id'],
                'barcode_id' => $debug_item['barcode_id'],
                'quantity' => $debug_item['quantity'],
                'unit_price' => $debug_item['unit_price'],
                'total_price' => $debug_item['total_price'],
                'discount_amount' => $debug_item['discount_amount'],
                'discount_percentage' => $debug_item['discount_percentage'],
                'name' => 'Item ID: ' . $debug_item['item_id'],
                'item_code' => 'CODE-' . $debug_item['item_id'],
                'barcode' => null
            ];
        }
        error_log("Invoice Items Debug - Fallback items created: " . count($items));
    }
}

$invoice['items'] = $items;

// Return all invoice data
$invoice['invoice_id'] = $invoice_id;
echo json_encode(['success' => true, 'data' => $invoice]); 