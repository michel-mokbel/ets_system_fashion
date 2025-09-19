<?php
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

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Debug incoming data
error_log("POST data: " . print_r($_POST, true));

switch ($action) {
    case 'add':
        addPurchaseOrder();
        break;
    case 'edit':
        editPurchaseOrder();
        break;
    case 'delete':
        deletePurchaseOrder();
        break;
    case 'update_status':
        updatePurchaseOrderStatus();
        break;
    case 'receive':
        receiveItems();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new purchase order
 */
function addPurchaseOrder() {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get form data
        $po_number = sanitize_input($_POST['po_number'] ?? '');
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $order_date = sanitize_input($_POST['order_date'] ?? '');
        $expected_delivery = sanitize_input($_POST['expected_delivery'] ?? '');
        $notes = sanitize_input($_POST['notes'] ?? '');
        
        // Make sure status is never empty
        $posted_status = trim($_POST['status'] ?? '');
        $status = in_array($posted_status, ['draft', 'pending']) ? $posted_status : 'draft';
        
        $user_id = $_SESSION['user_id'];
        
        // Debug logging
        error_log("Purchase Order Status received: " . ($_POST['status'] ?? 'null'));
        error_log("Status being used: " . $status);
        
        // Validate required fields
        if (empty($po_number) || $supplier_id <= 0 || empty($order_date) || empty($expected_delivery)) {
            echo json_encode(['success' => false, 'message' => 'Purchase order number, supplier, order date, and expected delivery date are required']);
            exit;
        }
        
        // Validate dates
        $validated_order_date = validateDate($order_date);
        $validated_expected_delivery = validateDate($expected_delivery);
        
        if ($validated_order_date === false || $validated_expected_delivery === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }
        
        // Use the validated dates (which are now guaranteed to be in YYYY-MM-DD format)
        $order_date = $validated_order_date;
        $expected_delivery = $validated_expected_delivery;
        
        // Check if PO number already exists
        $check_query = "SELECT id FROM purchase_orders WHERE po_number = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('s', $po_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Purchase order number already exists']);
            exit;
        }
        
        // Check if supplier exists
        $check_supplier = "SELECT id FROM suppliers WHERE id = ? AND status = 'active'";
        $check_stmt = $conn->prepare($check_supplier);
        $check_stmt->bind_param('i', $supplier_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Supplier not found or inactive']);
            exit;
        }
        
        // Check if items are provided
        if (!isset($_POST['items']) || !is_array($_POST['items']) || count($_POST['items']) === 0) {
            echo json_encode(['success' => false, 'message' => 'At least one item is required']);
            exit;
        }
        
        // Calculate total amount
        $total_amount = 0;
        $items = $_POST['items'];
        
        foreach ($items as $item) {
            $item_id = (int)($item['item_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            $unit_price = (float)($item['unit_price'] ?? 0);
            
            if ($item_id <= 0 || $quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid item or quantity']);
                exit;
            }
            
            // Check if item exists
            $check_item = "SELECT id FROM inventory_items WHERE id = ? AND status = 'active'";
            $check_stmt = $conn->prepare($check_item);
            $check_stmt->bind_param('i', $item_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'One or more items not found or inactive']);
                exit;
            }
            
            $total_amount += $quantity * $unit_price;
        }
        
        // Insert purchase order
        $insert_query = "INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, 
                                                  status, total_amount, notes, created_by, created_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        // Force status to be either 'draft' or 'pending', never empty
        if ($posted_status === 'pending') {
            $status = 'pending';
        } else {
            $status = 'draft';
        }
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('sisssisi', $po_number, $supplier_id, $order_date, $expected_delivery, $status, $total_amount, $notes, $user_id);
        $stmt->execute();
        
        $po_id = $conn->insert_id;
        
        // Insert purchase order items
        foreach ($items as $item) {
            $item_id = (int)($item['item_id'] ?? 0);
            $quantity = (int)($item['quantity'] ?? 0);
            $unit_price = (float)($item['unit_price'] ?? 0);
            $total_price = $quantity * $unit_price;
            
            $insert_item_query = "INSERT INTO purchase_order_items (purchase_order_id, item_id, quantity, 
                                                                unit_price, total_price)
                                VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_item_query);
            $stmt->bind_param('iiddd', $po_id, $item_id, $quantity, $unit_price, $total_price);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Purchase order created successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error creating purchase order: ' . $e->getMessage()]);
    }
}

/**
 * Edit an existing purchase order
 */
function editPurchaseOrder() {
    global $conn;
    
    // Get form data
    $po_id = (int)($_POST['po_id'] ?? 0);
    $po_number = sanitize_input($_POST['po_number'] ?? '');
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $order_date = sanitize_input($_POST['order_date'] ?? '');
    $expected_delivery = sanitize_input($_POST['expected_delivery'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    $status = sanitize_input($_POST['status'] ?? '');
    
    // Validate required fields
    if ($po_id <= 0 || empty($po_number) || $supplier_id <= 0 || empty($order_date) || empty($expected_delivery)) {
        echo json_encode(['success' => false, 'message' => 'Purchase order ID, number, supplier, order date, and expected delivery date are required']);
        exit;
    }
    
    // Validate dates
    $validated_order_date = validateDate($order_date);
    $validated_expected_delivery = validateDate($expected_delivery);
    
    if ($validated_order_date === false || $validated_expected_delivery === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    
    // Use the validated dates
    $order_date = $validated_order_date;
    $expected_delivery = $validated_expected_delivery;
    
    // Check if purchase order exists
    $check_query = "SELECT id, status FROM purchase_orders WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $po_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    $po_data = $check_result->fetch_assoc();
    $current_status = $po_data['status'];
    
    // Validate status if provided
    if (!empty($status)) {
        $valid_statuses = ['draft', 'pending', 'approved', 'cancelled'];
        
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }
        
        // Validate status transitions
        $valid_transition = false;
        
        switch ($status) {
            case 'draft':
                $valid_transition = ($current_status === 'draft');
                break;
            case 'pending':
                $valid_transition = in_array($current_status, ['draft', 'pending']);
                break;
            case 'approved':
                $valid_transition = ($current_status === 'pending');
                break;
            case 'cancelled':
                $valid_transition = in_array($current_status, ['draft', 'pending', 'approved']);
                break;
        }
        
        if (!$valid_transition) {
            echo json_encode(['success' => false, 'message' => 'Invalid status transition from ' . $current_status . ' to ' . $status]);
            exit;
        }
    } else {
        // Keep current status if not provided
        $status = $current_status;
    }
    
    // Check if PO number already exists (for a different PO)
    $check_query = "SELECT id FROM purchase_orders WHERE po_number = ? AND id != ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('si', $po_number, $po_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order number already exists']);
        exit;
    }
    
    // Update purchase order
    $update_query = "UPDATE purchase_orders SET 
                        po_number = ?, 
                        supplier_id = ?, 
                        order_date = ?, 
                        expected_delivery_date = ?, 
                        notes = ?,
                        status = ?,
                        updated_at = NOW() 
                     WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('sissssi', $po_number, $supplier_id, $order_date, $expected_delivery, $notes, $status, $po_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Purchase order updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update purchase order: ' . $conn->error]);
    }
}

/**
 * Delete a purchase order
 */
function deletePurchaseOrder() {
    global $conn;
    
    // Get purchase order ID
    $po_id = (int)($_POST['po_id'] ?? 0);
    
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase order ID']);
        exit;
    }
    
    // Check if purchase order exists
    $check_query = "SELECT id FROM purchase_orders WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $po_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete purchase order items first
        $delete_items_query = "DELETE FROM purchase_order_items WHERE purchase_order_id = ?";
        $delete_items_stmt = $conn->prepare($delete_items_query);
        $delete_items_stmt->bind_param('i', $po_id);
        $delete_items_stmt->execute();
        
        // Delete purchase order
        $delete_query = "DELETE FROM purchase_orders WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('i', $po_id);
        $delete_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Purchase order deleted successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error deleting purchase order: ' . $e->getMessage()]);
    }
}

/**
 * Update purchase order status
 */
function updatePurchaseOrderStatus() {
    global $conn;
    
    // Get parameters
    $po_id = (int)($_POST['po_id'] ?? 0);
    $status = sanitize_input($_POST['status'] ?? '');
    
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase order ID']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['pending', 'approved', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Check if purchase order exists
    $check_query = "SELECT id, status FROM purchase_orders WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $po_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    $current_po = $check_result->fetch_assoc();
    $current_status = $current_po['status'];
    
    // Validate status transitions
    $valid_transition = false;
    
    switch ($status) {
        case 'pending':
            $valid_transition = ($current_status === 'draft');
            break;
        case 'approved':
            $valid_transition = ($current_status === 'pending');
            break;
        case 'cancelled':
            $valid_transition = in_array($current_status, ['draft', 'pending', 'approved']);
            break;
    }
    
    if (!$valid_transition) {
        echo json_encode(['success' => false, 'message' => 'Invalid status transition from ' . $current_status . ' to ' . $status]);
        exit;
    }
    
    // Update purchase order status
    $update_query = "UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('si', $status, $po_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Purchase order status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update purchase order status: ' . $conn->error]);
    }
}

/**
 * Receive items for a purchase order
 */
function receiveItems() {
    global $conn;
    
    // Get parameters
    $po_id = (int)($_POST['po_id'] ?? 0);
    $receive_date = sanitize_input($_POST['receive_date'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    if ($po_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase order ID']);
        exit;
    }
    
    // Validate receive date
    $validated_receive_date = validateDate($receive_date);
    if (empty($receive_date) || $validated_receive_date === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid receive date']);
        exit;
    }
    
    // Use the validated date
    $receive_date = $validated_receive_date;
    
    // Check if purchase order exists and is approved
    $check_query = "SELECT id, status FROM purchase_orders WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $po_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit;
    }
    
    $po_data = $check_result->fetch_assoc();
    
    if ($po_data['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Only approved purchase orders can be received']);
        exit;
    }
    
    // Check if items are provided
    if (!isset($_POST['items']) || !is_array($_POST['items']) || count($_POST['items']) === 0) {
        echo json_encode(['success' => false, 'message' => 'No items to receive']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $items = $_POST['items'];
        $user_id = $_SESSION['user_id'];
        
        // Update purchase order status
        $update_query = "UPDATE purchase_orders SET status = 'received', received_date = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('si', $receive_date, $po_id);
        $update_stmt->execute();
        
        // Process each item
        foreach ($items as $item_id => $item_data) {
            $item_id = (int)$item_id;
            $received_quantity = (int)($item_data['received_quantity'] ?? 0);
            $inventory_item_id = (int)($item_data['item_id'] ?? 0);
            
            if ($received_quantity <= 0 || $inventory_item_id <= 0) {
                continue; // Skip invalid items
            }
            
            // Update purchase order item with received quantity
            $update_item_query = "UPDATE purchase_order_items SET received_quantity = ? WHERE id = ? AND purchase_order_id = ?";
            $update_item_stmt = $conn->prepare($update_item_query);
            $update_item_stmt->bind_param('iii', $received_quantity, $item_id, $po_id);
            $update_item_stmt->execute();
            
            // Add inventory transaction
            $transaction_query = "INSERT INTO inventory_transactions (item_id, transaction_type, quantity, 
                                                                  reference_type, reference_id, user_id, notes)
                              VALUES (?, 'in', ?, 'purchase_order', ?, ?, ?)";
            
            $transaction_stmt = $conn->prepare($transaction_query);
            $transaction_stmt->bind_param('iiis', $inventory_item_id, $received_quantity, $po_id, $user_id, $notes);
            $transaction_stmt->execute();
            
            // Update inventory item stock
            $update_stock_query = "UPDATE inventory_items SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ?";
            $update_stock_stmt = $conn->prepare($update_stock_query);
            $update_stock_stmt->bind_param('ii', $received_quantity, $inventory_item_id);
            $update_stock_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Items received successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error receiving items: ' . $e->getMessage()]);
    }
}

/**
 * Validate date format (YYYY-MM-DD or MM/DD/YYYY)
 * @param string $date Date string to validate
 * @return string|bool Normalized date in YYYY-MM-DD format or false if invalid
 */
function validateDate($date) {
    // First check if it's already in YYYY-MM-DD format
    $format = 'Y-m-d';
    $dt = DateTime::createFromFormat($format, $date);
    if ($dt && $dt->format($format) === $date) {
        return $date;
    }
    
    // Check if it's in MM/DD/YYYY format
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        $month = $matches[1];
        $day = $matches[2];
        $year = $matches[3];
        
        // Validate month, day, year
        if (checkdate((int)$month, (int)$day, (int)$year)) {
            return "$year-$month-$day"; // Return in YYYY-MM-DD format
        }
    }
    
    return false;
} 