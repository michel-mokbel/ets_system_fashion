<?php
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

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$source_store_id = (int)($_POST['source_store_id'] ?? 0);
$destination_store_id = (int)($_POST['destination_store_id'] ?? 0);
$item_id = (int)($_POST['item_id'] ?? 0);
$barcode_id = (int)($_POST['barcode_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);

// Validation
if ($source_store_id <= 0 || $destination_store_id <= 0 || $item_id <= 0 || $barcode_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

if ($source_store_id === $destination_store_id) {
    echo json_encode(['success' => false, 'message' => 'Source and destination stores cannot be the same']);
    exit;
}

error_log('Store-to-store transfer request - Source: ' . $source_store_id . ', Dest: ' . $destination_store_id . ', Item: ' . $item_id . ', Barcode: ' . $barcode_id . ', Qty: ' . $quantity);

$conn->begin_transaction();

try {
    // 1. Check source stock
    $check_stmt = $conn->prepare("SELECT current_stock, selling_price, cost_price FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ? FOR UPDATE");
    $check_stmt->bind_param('iii', $source_store_id, $item_id, $barcode_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('Item not found in source store');
    }
    
    $source_data = $check_result->fetch_assoc();
    $available_stock = (int)$source_data['current_stock'];
    
    if ($available_stock < $quantity) {
        throw new Exception("Insufficient stock. Available: {$available_stock}, Requested: {$quantity}");
    }
    
    // 2. Deduct from source
    $deduct_stmt = $conn->prepare("UPDATE store_inventory SET current_stock = current_stock - ? WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
    $deduct_stmt->bind_param('iiii', $quantity, $source_store_id, $item_id, $barcode_id);
    $deduct_stmt->execute();
    
    // 3. Add to destination (or update if exists)
    $dest_check_stmt = $conn->prepare("SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
    $dest_check_stmt->bind_param('iii', $destination_store_id, $item_id, $barcode_id);
    $dest_check_stmt->execute();
    $dest_check_result = $dest_check_stmt->get_result();
    
    if ($dest_check_result->num_rows > 0) {
        // Update existing inventory
        $update_dest_stmt = $conn->prepare("UPDATE store_inventory SET current_stock = current_stock + ? WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
        $update_dest_stmt->bind_param('iiii', $quantity, $destination_store_id, $item_id, $barcode_id);
        $update_dest_stmt->execute();
    } else {
        // Insert new inventory record
        $insert_dest_stmt = $conn->prepare("INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) VALUES (?, ?, ?, ?, ?, ?)");
        $insert_dest_stmt->bind_param('iiiidd', $destination_store_id, $item_id, $barcode_id, $quantity, $source_data['selling_price'], $source_data['cost_price']);
        $insert_dest_stmt->execute();
    }
    
    // 4. Ensure store assignment exists for destination
    $assignment_stmt = $conn->prepare("INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) VALUES (?, ?, ?, 'Auto-assigned via store-to-store transfer') ON DUPLICATE KEY UPDATE is_active = 1");
    $user_id = $_SESSION['user_id'];
    $assignment_stmt->bind_param('iii', $destination_store_id, $item_id, $user_id);
    $assignment_stmt->execute();
    
    // 5. Create transfer shipment record
    $shipment_number = 'ST-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    error_log('Creating transfer shipment with number: ' . $shipment_number);
    
    $shipment_stmt = $conn->prepare("INSERT INTO transfer_shipments (shipment_number, source_store_id, destination_store_id, total_items, status, transfer_type, notes, created_by, packed_by, received_by, created_at, packed_at, shipped_at, received_at) VALUES (?, ?, ?, ?, 'received', 'direct', 'Store-to-store direct transfer', ?, ?, ?, NOW(), NOW(), NOW(), NOW())");
    $user_id = $_SESSION['user_id'];
    error_log('User ID: ' . $user_id);
    
    $shipment_stmt->bind_param('siiiiii', $shipment_number, $source_store_id, $destination_store_id, $quantity, $user_id, $user_id, $user_id);
    $shipment_stmt->execute();
    $shipment_id = $conn->insert_id;
    error_log('Created shipment with ID: ' . $shipment_id);
    
    // 6. Create transfer box for store-to-store transfers
    $box_stmt = $conn->prepare("INSERT INTO transfer_boxes (shipment_id, box_number, box_label, total_items) VALUES (?, 1, 'Store-to-Store Transfer Items', ?)");
    $box_stmt->bind_param('ii', $shipment_id, $quantity);
    $box_stmt->execute();
    $box_id = $conn->insert_id;
    error_log('Created transfer box with ID: ' . $box_id);
    
    // 7. Create transfer items record - EXACTLY like the original function
    $transfer_item_stmt = $conn->prepare("
        INSERT INTO transfer_items 
        (shipment_id, box_id, item_id, barcode_id, quantity_requested, quantity_packed, quantity_received, unit_cost, selling_price) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $transfer_item_stmt->bind_param("iiiiiiidd", $shipment_id, $box_id, $item_id, $barcode_id, $quantity, $quantity, $quantity, $source_data['cost_price'], $source_data['selling_price']);
    
    if (!$transfer_item_stmt->execute()) {
        throw new Exception("Failed to create transfer item: " . $transfer_item_stmt->error);
    }
    
    // 8. Get store names for success message
    $source_name_stmt = $conn->prepare("SELECT name FROM stores WHERE id = ?");
    $source_name_stmt->bind_param('i', $source_store_id);
    $source_name_stmt->execute();
    $source_name = $source_name_stmt->get_result()->fetch_assoc()['name'];
    error_log('Source store name: ' . $source_name);
    
    $dest_name_stmt = $conn->prepare("SELECT name FROM stores WHERE id = ?");
    $dest_name_stmt->bind_param('i', $destination_store_id);
    $dest_name_stmt->execute();
    $dest_name = $dest_name_stmt->get_result()->fetch_assoc()['name'];
    error_log('Destination store name: ' . $dest_name);
    
    $conn->commit();
    error_log('Transaction committed successfully');
    
    $response = [
        'success' => true, 
        'message' => "Successfully transferred {$quantity} items from {$source_name} to {$dest_name}",
        'shipment_number' => $shipment_number
    ];
    error_log('Sending response: ' . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('Store-to-store transfer error: ' . $e->getMessage());
    error_log('Store-to-store transfer error trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
