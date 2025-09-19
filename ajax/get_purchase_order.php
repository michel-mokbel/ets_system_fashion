<?php
/**
 * Retrieves a single purchase order with line items.
 *
 * Populates the purchase order edit modal by returning header, supplier, and
 * item details. Restricted to administrative users.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get purchase order ID
$po_id = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid purchase order ID']);
    exit;
}

// Get purchase order data
$po_query = "SELECT po.* FROM purchase_orders po WHERE po.id = ?";
$po_stmt = $conn->prepare($po_query);
$po_stmt->bind_param('i', $po_id);
$po_stmt->execute();
$po_result = $po_stmt->get_result();

if ($po_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
    exit;
}

$purchase_order = $po_result->fetch_assoc();

// Get supplier data
$supplier_query = "SELECT * FROM suppliers WHERE id = ?";
$supplier_stmt = $conn->prepare($supplier_query);
$supplier_stmt->bind_param('i', $purchase_order['supplier_id']);
$supplier_stmt->execute();
$supplier_result = $supplier_stmt->get_result();
$supplier = $supplier_result->fetch_assoc();

// Get purchase order items
$items_query = "SELECT poi.*, ii.name as item_name, ii.item_code 
                FROM purchase_order_items poi
                JOIN inventory_items ii ON poi.item_id = ii.id
                WHERE poi.purchase_order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param('i', $po_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Return data as JSON
echo json_encode([
    'success' => true,
    'data' => [
        'purchase_order' => $purchase_order,
        'supplier' => $supplier,
        'items' => $items
    ]
]);
?> 