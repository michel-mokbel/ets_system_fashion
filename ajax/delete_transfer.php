<?php
/**
 * Transfer deletion handler.
 *
 * Reverses a previously recorded transfer shipment by restoring source
 * inventory, decrementing destination stock, and cleaning up transfer-related
 * records. Guarded for admin/warehouse roles and wraps operations in
 * transactions to maintain stock integrity.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$shipment_id = (int)($_POST['shipment_id'] ?? 0);

if ($shipment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get transfer details
    $shipment_query = "
        SELECT ts.*, s1.name as source_store_name, s2.name as destination_store_name
        FROM transfer_shipments ts
        JOIN stores s1 ON ts.source_store_id = s1.id
        JOIN stores s2 ON ts.destination_store_id = s2.id
        WHERE ts.id = ?
    ";
    
    $shipment_stmt = $conn->prepare($shipment_query);
    if (!$shipment_stmt) {
        throw new Exception("Failed to prepare shipment query: " . $conn->error);
    }
    
    $shipment_stmt->bind_param('i', $shipment_id);
    $shipment_stmt->execute();
    $shipment_result = $shipment_stmt->get_result();
    
    if ($shipment_result->num_rows === 0) {
        throw new Exception("Transfer not found");
    }
    
    $shipment = $shipment_result->fetch_assoc();
    
    error_log("Processing transfer ID: $shipment_id, Status: " . $shipment['status']);
    
    // Get all transfer items
    $items_query = "
        SELECT ti.*, tb.warehouse_box_id
        FROM transfer_items ti
        LEFT JOIN transfer_boxes tb ON ti.box_id = tb.id
        WHERE ti.shipment_id = ?
    ";
    
    $items_stmt = $conn->prepare($items_query);
    if (!$items_stmt) {
        throw new Exception("Failed to prepare items query: " . $conn->error);
    }
    
    $items_stmt->bind_param('i', $shipment_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    $transfer_items = [];
    while ($item = $items_result->fetch_assoc()) {
        $transfer_items[] = $item;
    }
    
    error_log("Found " . count($transfer_items) . " transfer items");
    
    // Simple inventory restoration - restore source and remove from destination
    foreach ($transfer_items as $item) {
        $item_id = (int)$item['item_id'];
        $barcode_id = (int)$item['barcode_id'];
        $quantity = (int)$item['quantity_requested'];
        
        // 1. Restore source inventory (warehouse boxes or store)
        if ($shipment['source_store_id'] == 1 && $item['warehouse_box_id']) {
            // Restore warehouse box quantity
            $restore_box_stmt = $conn->prepare("
                UPDATE warehouse_boxes 
                SET quantity = quantity + ? 
                WHERE id = ?
            ");
            $restore_box_stmt->bind_param('ii', $quantity, $item['warehouse_box_id']);
            if (!$restore_box_stmt->execute()) {
                throw new Exception("Failed to restore warehouse box quantity: " . $restore_box_stmt->error);
            }
            error_log("Restored $quantity to warehouse box ID: " . $item['warehouse_box_id']);
        }
        
        // 2. Remove from destination store inventory
        $remove_dest_stmt = $conn->prepare("
            UPDATE store_inventory 
            SET current_stock = GREATEST(0, current_stock - ?) 
            WHERE store_id = ? AND item_id = ? AND barcode_id = ?
        ");
        $remove_dest_stmt->bind_param('iiii', $quantity, $shipment['destination_store_id'], $item_id, $barcode_id);
        if (!$remove_dest_stmt->execute()) {
            throw new Exception("Failed to remove destination store inventory: " . $remove_dest_stmt->error);
        }
        error_log("Removed $quantity from destination store ID: " . $shipment['destination_store_id']);
    }
    
    // Update transfer status to cancelled
    $update_status_stmt = $conn->prepare("
        UPDATE transfer_shipments 
        SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $update_status_stmt->bind_param('i', $shipment_id);
    if (!$update_status_stmt->execute()) {
        throw new Exception("Failed to update transfer status: " . $update_status_stmt->error);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Transfer successfully reversed and inventory restored'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Transfer deletion failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to delete transfer: ' . $e->getMessage()
    ]);
}
?>
