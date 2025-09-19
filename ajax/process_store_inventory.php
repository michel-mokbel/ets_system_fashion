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

// Only admin, inventory managers, and store managers can perform these operations
if (!has_role(['admin', 'inventory_manager', 'store_manager'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'adjust_stock':
        adjustStock();
        break;
    case 'update_location':
        updateLocation();
        break;
    case 'update_minimum_stock':
        updateMinimumStock();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Adjust stock for a store inventory item
 */
function adjustStock() {
    global $conn;
    
    $store_inventory_id = (int)($_POST['store_inventory_id'] ?? 0);
    $adjustment_type = $_POST['adjustment_type'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    // Validate input
    if ($store_inventory_id <= 0 || empty($adjustment_type) || $quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }
    
    if (!in_array($adjustment_type, ['add', 'remove', 'set'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid adjustment type']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Get current store inventory details
        $inventory_query = "SELECT si.*, i.name as item_name, i.item_code, s.name as store_name 
                           FROM store_inventory si
                           JOIN inventory_items i ON si.item_id = i.id
                           JOIN stores s ON si.store_id = s.id
                           WHERE si.id = ?";
        $inventory_stmt = $conn->prepare($inventory_query);
        $inventory_stmt->bind_param('i', $store_inventory_id);
        $inventory_stmt->execute();
        $inventory_result = $inventory_stmt->get_result();
        
        if ($inventory_result->num_rows === 0) {
            throw new Exception('Store inventory item not found');
        }
        
        $inventory = $inventory_result->fetch_assoc();
        $current_stock = (int)$inventory['current_stock'];
        
        // Check store access for non-admin users
        $user_role = $_SESSION['user_role'] ?? '';
        $user_store_id = $_SESSION['store_id'] ?? null;
        
        if ($user_role !== 'admin' && $user_role !== 'inventory_manager' && $inventory['store_id'] != $user_store_id) {
            throw new Exception('Access denied to this store inventory');
        }
        
        // Calculate new stock level
        $new_stock = $current_stock;
        $actual_change = 0;
        
        switch ($adjustment_type) {
            case 'add':
                $new_stock = $current_stock + $quantity;
                $actual_change = $quantity;
                $transaction_type = 'in';
                break;
            case 'remove':
                if ($quantity > $current_stock) {
                    throw new Exception('Cannot remove more stock than available');
                }
                $new_stock = $current_stock - $quantity;
                $actual_change = $quantity;
                $transaction_type = 'out';
                break;
            case 'set':
                $new_stock = $quantity;
                $actual_change = abs($new_stock - $current_stock);
                $transaction_type = ($new_stock > $current_stock) ? 'in' : 'out';
                break;
        }
        
        // Update store inventory
        $update_query = "UPDATE store_inventory SET current_stock = ?, last_updated = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ii', $new_stock, $store_inventory_id);
        $update_stmt->execute();
        
        // Log the transaction
        $transaction_query = "INSERT INTO inventory_transactions 
                             (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, user_id, notes) 
                             VALUES (?, ?, ?, ?, ?, 'adjustment', ?, ?)";
        $transaction_stmt = $conn->prepare($transaction_query);
        $notes = "Stock adjustment ({$adjustment_type}): {$reason}";
        $user_id = $_SESSION['user_id'];
        $transaction_stmt->bind_param('iisisiss', 
            $inventory['store_id'], 
            $inventory['item_id'], 
            $inventory['barcode_id'], 
            $transaction_type, 
            $actual_change, 
            $user_id, 
            $notes
        );
        $transaction_stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Stock adjusted successfully',
            'old_stock' => $current_stock,
            'new_stock' => $new_stock,
            'change' => $actual_change
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Update location information for a store inventory item
 */
function updateLocation() {
    global $conn;
    
    $store_inventory_id = (int)($_POST['store_inventory_id'] ?? 0);
    $aisle = trim($_POST['aisle'] ?? '');
    $shelf = trim($_POST['shelf'] ?? '');
    $bin = trim($_POST['bin'] ?? '');
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
    $location_in_store = trim($_POST['location_in_store'] ?? '');
    
    // Validate input
    if ($store_inventory_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid store inventory ID']);
        exit;
    }
    
    if ($minimum_stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Minimum stock cannot be negative']);
        exit;
    }
    
    try {
        // Get current store inventory details for access check
        $inventory_query = "SELECT si.*, i.name as item_name, s.name as store_name 
                           FROM store_inventory si
                           JOIN inventory_items i ON si.item_id = i.id
                           JOIN stores s ON si.store_id = s.id
                           WHERE si.id = ?";
        $inventory_stmt = $conn->prepare($inventory_query);
        $inventory_stmt->bind_param('i', $store_inventory_id);
        $inventory_stmt->execute();
        $inventory_result = $inventory_stmt->get_result();
        
        if ($inventory_result->num_rows === 0) {
            throw new Exception('Store inventory item not found');
        }
        
        $inventory = $inventory_result->fetch_assoc();
        
        // Check store access for non-admin users
        $user_role = $_SESSION['user_role'] ?? '';
        $user_store_id = $_SESSION['store_id'] ?? null;
        
        if ($user_role !== 'admin' && $user_role !== 'inventory_manager' && $inventory['store_id'] != $user_store_id) {
            throw new Exception('Access denied to this store inventory');
        }
        
        // Update location information
        $update_query = "UPDATE store_inventory 
                        SET aisle = ?, shelf = ?, bin = ?, minimum_stock = ?, location_in_store = ?, last_updated = NOW() 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('sssiisi', $aisle, $shelf, $bin, $minimum_stock, $location_in_store, $store_inventory_id);
        
    if ($update_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Location updated successfully',
                'item_name' => $inventory['item_name'],
                'store_name' => $inventory['store_name']
            ]);
    } else {
            throw new Exception('Failed to update location');
    }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Update minimum stock level for a store inventory item
 */
function updateMinimumStock() {
    global $conn;
    
    $store_inventory_id = (int)($_POST['store_inventory_id'] ?? 0);
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
    
    // Validate input
    if ($store_inventory_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid store inventory ID']);
        exit;
    }
    
    if ($minimum_stock < 0) {
        echo json_encode(['success' => false, 'message' => 'Minimum stock cannot be negative']);
        exit;
    }
    
    try {
        // Get current store inventory details for access check
        $inventory_query = "SELECT si.*, i.name as item_name, s.name as store_name 
                           FROM store_inventory si
                           JOIN inventory_items i ON si.item_id = i.id
                           JOIN stores s ON si.store_id = s.id
                           WHERE si.id = ?";
        $inventory_stmt = $conn->prepare($inventory_query);
        $inventory_stmt->bind_param('i', $store_inventory_id);
        $inventory_stmt->execute();
        $inventory_result = $inventory_stmt->get_result();
        
        if ($inventory_result->num_rows === 0) {
            throw new Exception('Store inventory item not found');
        }
        
        $inventory = $inventory_result->fetch_assoc();
        
        // Check store access for non-admin users
        $user_role = $_SESSION['user_role'] ?? '';
        $user_store_id = $_SESSION['store_id'] ?? null;
        
        if ($user_role !== 'admin' && $user_role !== 'inventory_manager' && $inventory['store_id'] != $user_store_id) {
            throw new Exception('Access denied to this store inventory');
        }
        
        // Update minimum stock level
        $update_query = "UPDATE store_inventory 
                        SET minimum_stock = ?, last_updated = NOW() 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ii', $minimum_stock, $store_inventory_id);
        
        if ($update_stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Minimum stock level updated successfully',
                'item_name' => $inventory['item_name'],
                'store_name' => $inventory['store_name'],
                'old_minimum_stock' => $inventory['minimum_stock'],
                'new_minimum_stock' => $minimum_stock
            ]);
        } else {
            throw new Exception('Failed to update minimum stock level');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?> 