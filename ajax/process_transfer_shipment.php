<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has access to transfers
if (!can_access_transfers()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create_shipment':
        createShipment();
        break;
    case 'create_box_transfer':
        createBoxTransfer();
        break;
    case 'create_box_transfer_multi':
        createBoxTransferMulti();
        break;
    case 'create_direct_transfer':
        createDirectTransfer();
        break;
    case 'pack_shipment':
        packShipment();
        break;
    case 'receive_shipment':
        receiveShipment();
        break;
    case 'cancel_shipment':
        cancelShipment();
        break;
    case 'calculate_average_cost':
        calculateAverageCost();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Helper function to calculate average cost for an item
 * Box cost is split per item quantity independently
 * 
 * Example:
 * - Box unit cost: $100, item quantity: 5
 * - Box cost per unit: $100 / 5 = $20
 * - If item base price is $15, average cost = ($20 + $15) / 2 = $17.50
 */
function calculateItemAverageCost($warehouse_box_id, $item_id, $quantity) {
    global $conn;
    
    try {
        // Get box unit cost and total items in the box
        $box_query = "SELECT wb.unit_cost, COUNT(ti.id) as total_items_in_box 
                      FROM warehouse_boxes wb 
                      LEFT JOIN transfer_items ti ON ti.box_id = (
                          SELECT tb.id FROM transfer_boxes tb 
                          WHERE tb.warehouse_box_id = wb.id 
                          LIMIT 1
                      )
                      WHERE wb.id = ?
                      GROUP BY wb.id";
        $box_stmt = $conn->prepare($box_query);
        $box_stmt->bind_param('i', $warehouse_box_id);
        $box_stmt->execute();
        $box_result = $box_stmt->get_result();
        
        if ($box_result->num_rows === 0) {
            return 0;
        }
        
        $box_data = $box_result->fetch_assoc();
        $box_unit_cost = (float)($box_data['unit_cost'] ?? 0);
        $total_items_in_box = (int)($box_data['total_items_in_box'] ?? 1);
        
        // Get item base price
        $item_query = "SELECT base_price FROM inventory_items WHERE id = ?";
        $item_stmt = $conn->prepare($item_query);
        $item_stmt->bind_param('i', $item_id);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();
        
        if ($item_result->num_rows === 0) {
            return 0;
        }
        
        $item_data = $item_result->fetch_assoc();
        $item_base_price = (float)($item_data['base_price'] ?? 0);
        
        // Calculate average cost: box cost split per total items in box + base price average
        $average_cost = 0;
        
        if ($box_unit_cost > 0 && $item_base_price > 0) {
            // Both costs available: 
            // 1. Box cost per item = box_unit_cost / total_items_in_box (split across all items in box)
            // 2. Calculate average: (box_cost_per_item + item_base_price) / 2
            $box_cost_per_item = $box_unit_cost / max(1, $total_items_in_box);
            $average_cost = ($box_cost_per_item + $item_base_price) / 2;
        } elseif ($box_unit_cost > 0) {
            // Only box cost available: split per total items in box
            $average_cost = $box_unit_cost / max(1, $total_items_in_box);
        } elseif ($item_base_price > 0) {
            // Only item base price available
            $average_cost = $item_base_price;
        } else {
            // No costs available
            $average_cost = 0;
        }
        
        // Ensure average cost is reasonable
        $average_cost = max(0.01, min(999999.99, $average_cost));
        
        return round($average_cost, 2);
        
    } catch (Exception $e) {
        error_log("Item average cost calculation failed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Create a new transfer shipment with multiple boxes
 */
function createShipment() {
    global $conn;
    
    try {
        $source_store_id = (int)($_POST['source_store_id'] ?? 0);
        $destination_store_id = (int)($_POST['destination_store_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // Handle multiple boxes - new format
        if (isset($_POST['boxes']) && is_string($_POST['boxes'])) {
            $boxes = json_decode($_POST['boxes'], true);
        } else {
            // Backward compatibility - single box format
            $items = json_decode($_POST['items'] ?? '[]', true);
            $box_number = (int)($_POST['box_number'] ?? 1);
            $box_label = trim($_POST['box_label'] ?? '');
            
            $boxes = [[
                'id' => $box_number,
                'label' => $box_label,
                'items' => $items
            ]];
        }
        
        // Log input data for debugging
        error_log("Transfer creation - Source: $source_store_id, Destination: $destination_store_id, Boxes: " . count($boxes));
        
        // Validation
        if ($source_store_id <= 0 || $destination_store_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid store selection']);
            return;
        }
        
        if ($source_store_id === $destination_store_id) {
            echo json_encode(['success' => false, 'message' => 'Source and destination stores cannot be the same']);
            return;
        }
        
        if (empty($boxes)) {
            echo json_encode(['success' => false, 'message' => 'No boxes provided']);
            return;
        }
        
        // Calculate total items across all boxes
        $total_items = 0;
        foreach ($boxes as $box) {
            if (empty($box['items'])) {
                echo json_encode(['success' => false, 'message' => 'Box ' . ($box['label'] ?: '#' . $box['id']) . ' has no items']);
                return;
            }
            $total_items += array_sum(array_column($box['items'], 'quantity'));
        }
        
        $conn->begin_transaction();
        
        // Generate shipment number
        $shipment_number = generateShipmentNumber($source_store_id, $destination_store_id);
        
        // Create the main transfer shipment record
        $stmt = $conn->prepare("
            INSERT INTO transfer_shipments 
            (shipment_number, source_store_id, destination_store_id, total_items, status, notes, created_by) 
            VALUES (?, ?, ?, ?, 'received', ?, ?)
        ");
        
        $created_by = $_SESSION['user_id'];
        $stmt->bind_param("siiisi", $shipment_number, $source_store_id, $destination_store_id, $total_items, $notes, $created_by);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create shipment: " . $stmt->error);
        }
        
        $shipment_id = $conn->insert_id;
        error_log("Created shipment ID: $shipment_id with number: $shipment_number");
        
        // Create transfer boxes and their items
        foreach ($boxes as $box) {
            $box_number = (int)$box['id'];
            $box_label = trim($box['label'] ?? '');
            $box_items = $box['items'];
            $box_total_items = array_sum(array_column($box_items, 'quantity'));
            
            // Create transfer box record
            $box_stmt = $conn->prepare("
                INSERT INTO transfer_boxes 
                (shipment_id, box_number, box_label, warehouse_box_id, total_items) 
                VALUES (?, ?, ?, ?, 0)
            ");
            $box_stmt->bind_param("isis", $shipment_id, $box_number, $box_label, $warehouse_box_id);
            
            if (!$box_stmt->execute()) {
                throw new Exception("Failed to create transfer box: " . $box_stmt->error);
            }
            
            $box_id = $conn->insert_id;
            error_log("Created box ID: $box_id for box number: $box_number");
            
            // Process items in this box
            foreach ($box_items as $item) {
                $item_id = (int)$item['item_id'];
                $barcode_id = (int)$item['barcode_id'];
                $quantity = (int)$item['quantity'];
                $unit_cost = (float)($item['unit_cost'] ?? 0);
                $selling_price = (float)($item['selling_price'] ?? 0);
                
                // Create transfer item record
                $item_stmt = $conn->prepare("
                    INSERT INTO transfer_items 
                    (shipment_id, box_id, item_id, barcode_id, quantity_requested, quantity_packed, quantity_received, unit_cost, selling_price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $item_stmt->bind_param("iiiiiiidd", $shipment_id, $box_id, $item_id, $barcode_id, $quantity, $quantity, $quantity, $unit_cost, $selling_price);
                
                if (!$item_stmt->execute()) {
                    throw new Exception("Failed to create transfer item: " . $item_stmt->error);
                }
                
                // Update inventory for transfer (deduct from source, add to destination)
                updateInventoryForTransfer($source_store_id, $destination_store_id, $item_id, $barcode_id, $quantity, $shipment_id, $unit_cost, $selling_price);
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Transfer created successfully with " . count($boxes) . " box(es)",
            'shipment_id' => $shipment_id,
            'shipment_number' => $shipment_number,
            'total_boxes' => count($boxes),
            'total_items' => $total_items
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Transfer creation failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Create a new box-based transfer from warehouse to store
 */
function createBoxTransfer() {
    global $conn;
    
    try {
        $source_store_id = (int)($_POST['source_store_id'] ?? 0);
        $destination_store_id = (int)($_POST['destination_store_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // Get selected boxes and transfer items
        $selected_boxes = json_decode($_POST['selected_boxes'] ?? '[]', true);
        $transfer_items = json_decode($_POST['transfer_items'] ?? '[]', true);
        
        // Log input data for debugging
        error_log("Box Transfer creation - Source: $source_store_id, Destination: $destination_store_id, Boxes: " . count($selected_boxes) . ", Items: " . count($transfer_items));
        error_log("Selected boxes data: " . json_encode($selected_boxes));
        error_log("Transfer items data: " . json_encode($transfer_items));
        
        // Validation
        if ($source_store_id <= 0 || $destination_store_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid store selection']);
            return;
        }
        
        if ($source_store_id === $destination_store_id) {
            echo json_encode(['success' => false, 'message' => 'Source and destination stores cannot be the same']);
            return;
        }
        
        if (empty($selected_boxes)) {
            echo json_encode(['success' => false, 'message' => 'No boxes selected']);
            return;
        }
        
        if (empty($transfer_items)) {
            echo json_encode(['success' => false, 'message' => 'No items selected for transfer']);
            return;
        }
        
        $conn->begin_transaction();
        
        // Generate shipment number
        $shipment_number = 'TS-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create transfer shipment record - IMMEDIATELY SET TO RECEIVED
        $shipment_stmt = $conn->prepare("
            INSERT INTO transfer_shipments 
            (shipment_number, source_store_id, destination_store_id, status, transfer_type, notes, created_by, packed_by, received_by, packed_at, received_at) 
            VALUES (?, ?, ?, 'received', 'box', ?, ?, ?, ?, NOW(), NOW())
        ");
        $user_id = $_SESSION['user_id'];
        $shipment_stmt->bind_param("siisiii", $shipment_number, $source_store_id, $destination_store_id, $notes, $user_id, $user_id, $user_id);
        
        if (!$shipment_stmt->execute()) {
            throw new Exception("Failed to create transfer shipment: " . $shipment_stmt->error);
        }
        
        $shipment_id = $conn->insert_id;
        error_log("Created shipment ID: $shipment_id");
        
        // Create transfer boxes (multiple instances based on requested quantity)
        $box_counter = 1;
        $warehouse_box_mapping = []; // Map transfer box number to warehouse box ID
        
        foreach ($selected_boxes as $box) {
            $requested_quantity = isset($box['request_quantity']) ? (int)$box['request_quantity'] : 1;
            $warehouse_box_id = (int)$box['id'];
            
            error_log("Processing warehouse box: ID=$warehouse_box_id, Name=" . ($box['box_name'] ?? 'N/A') . ", Requested Qty=$requested_quantity");
            
            // Create multiple box instances based on requested quantity
            for ($i = 1; $i <= $requested_quantity; $i++) {
                $box_number = $box_counter++;
                // Create a proper box label
                $box_label_parts = [];
                if (!empty($box['box_name'])) {
                    $box_label_parts[] = $box['box_name'];
                }
                if (!empty($box['box_type'])) {
                    $box_label_parts[] = '(' . $box['box_type'] . ')';
                }
                
                // Add instance number if multiple instances
                if ($requested_quantity > 1) {
                    $box_label_parts[] = 'Instance ' . $i;
                }
                
                $box_label = implode(' ', $box_label_parts);
                if (empty($box_label)) {
                    $box_label = 'Warehouse Box ' . $warehouse_box_id;
                }
                
                // Create transfer box record
                $box_stmt = $conn->prepare("
                    INSERT INTO transfer_boxes 
                    (shipment_id, box_number, box_label, warehouse_box_id, total_items) 
                    VALUES (?, ?, ?, ?, 0)
                ");
                $box_stmt->bind_param("isis", $shipment_id, $box_number, $box_label, $warehouse_box_id);
                
                if (!$box_stmt->execute()) {
                    throw new Exception("Failed to create transfer box: " . $box_stmt->error);
                }
                
                $transfer_box_id = $conn->insert_id;
                
                // Store mapping: transfer box number -> warehouse box ID
                $warehouse_box_mapping[$box_number] = $warehouse_box_id;
                
                error_log("Created transfer box instance $i for warehouse box ID: $warehouse_box_id (Box #$box_number, Transfer Box ID: $transfer_box_id)");
            }
        }
        
        // Process transfer items
        $total_items = 0;
        $boxIdByNumber = [];
        // Map box_number => id so we can attach items to chosen box
        $boxes_map_stmt = $conn->prepare("SELECT id, box_number FROM transfer_boxes WHERE shipment_id = ?");
        $boxes_map_stmt->bind_param('i', $shipment_id);
        $boxes_map_stmt->execute();
        $boxes_map_res = $boxes_map_stmt->get_result();
        while ($bm = $boxes_map_res->fetch_assoc()) {
            $boxIdByNumber[(int)$bm['box_number']] = (int)$bm['id'];
        }
        
        error_log("Box mapping established: " . json_encode($boxIdByNumber));
        
        $perBoxTotals = [];
        foreach ($transfer_items as $item) {
            $item_id = (int)$item['item_id'];
            $barcode_id = (int)$item['barcode_id'];
            $quantity = (int)$item['quantity'];
            $selling_price = (float)$item['selling_price'];
            $cost_price = (float)$item['cost_price'];
            $box_number = isset($item['box_number']) ? (int)$item['box_number'] : 1;
            $box_id_for_item = $boxIdByNumber[$box_number] ?? null;
            
            error_log("Processing item: Item ID $item_id, Box Number: $box_number, Mapped Box ID: " . ($box_id_for_item ?? 'null'));
            
            if (!$box_id_for_item) {
                // Fallback to first available box id if mapping not found
                $box_id_for_item = reset($boxIdByNumber) ?: null;
                error_log("Fallback: Using first available box ID: " . ($box_id_for_item ?? 'null'));
            }
            if (!$box_id_for_item) {
                throw new Exception('No transfer box found to attach items');
            }
            
            // Calculate average cost for this item using the warehouse box mapping
            $warehouse_box_id = $warehouse_box_mapping[$box_number] ?? null;
            if ($warehouse_box_id) {
                $avg_cost = calculateItemAverageCost($warehouse_box_id, $item_id, $quantity);
                // Update item's base price with new average cost
                $update_base_price_stmt = $conn->prepare("UPDATE inventory_items SET base_price = ? WHERE id = ?");
                $update_base_price_stmt->bind_param('di', $avg_cost, $item_id);
                $update_base_price_stmt->execute();
                
                // Use new average cost for transfer
                $cost_price = $avg_cost;
                
                error_log("Calculated average cost for item $item_id: $avg_cost using warehouse box ID: $warehouse_box_id");
            } else {
                error_log("Warning: No warehouse box mapping found for transfer box number: $box_number");
            }
            
            // Create transfer item record
            $item_stmt = $conn->prepare("
                INSERT INTO transfer_items 
                (shipment_id, box_id, item_id, barcode_id, quantity_requested, quantity_packed, quantity_received, unit_cost, selling_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $item_stmt->bind_param("iiiiiiidd", $shipment_id, $box_id_for_item, $item_id, $barcode_id, $quantity, $quantity, $quantity, $cost_price, $selling_price);
            
            if (!$item_stmt->execute()) {
                throw new Exception("Failed to create transfer item: " . $item_stmt->error);
            }
            
            $total_items += $quantity;
            $perBoxTotals[$box_id_for_item] = ($perBoxTotals[$box_id_for_item] ?? 0) + $quantity;
            
            // IMMEDIATE INVENTORY PROCESSING FOR BOX TRANSFERS
            // For box transfers from warehouse: No deduction needed (items are pre-allocated in boxes)
            // Just add to destination store inventory
            $dest_stock_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                              current_stock = current_stock + VALUES(current_stock),
                              selling_price = VALUES(selling_price),
                              cost_price = VALUES(cost_price)";
            $dest_stock_stmt = $conn->prepare($dest_stock_sql);
            $dest_stock_stmt->bind_param('iiiidd', $destination_store_id, $item_id, $barcode_id, $quantity, $selling_price, $cost_price);
            $dest_stock_stmt->execute();
            
            // ENSURE STORE ASSIGNMENT EXISTS - Create if not exists
            $assignment_sql = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) 
                              VALUES (?, ?, ?, 'Auto-assigned via box transfer') 
                              ON DUPLICATE KEY UPDATE 
                              is_active = 1,
                              assigned_by = VALUES(assigned_by),
                              assigned_at = CURRENT_TIMESTAMP";
            $assignment_stmt = $conn->prepare($assignment_sql);
            $assignment_stmt->bind_param('iii', $destination_store_id, $item_id, $user_id);
            $assignment_stmt->execute();
            
            // Log inbound transaction only (no outbound for box transfers from warehouse)
            $in_trans_sql = "INSERT INTO inventory_transactions 
                            (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                            VALUES (?, ?, ?, 'in', ?, 'shipment', ?, 'inbound', ?, ?)";
            $in_trans_stmt = $conn->prepare($in_trans_sql);
            $in_notes = "Box transfer in from Main Warehouse: {$shipment_number}";
            $in_trans_stmt->bind_param('iiiiiis', $destination_store_id, $item_id, $barcode_id, $quantity, $shipment_id, $user_id, $in_notes);
            $in_trans_stmt->execute();
            
            error_log("Added transfer item and updated inventory: Item ID $item_id, Quantity: $quantity");
        }
        
        // Update each box total_items
        $update_box_stmt = $conn->prepare("UPDATE transfer_boxes SET total_items = ? WHERE id = ?");
        foreach ($perBoxTotals as $box_id => $qty_total) {
            $update_box_stmt->bind_param('ii', $qty_total, $box_id);
            $update_box_stmt->execute();
        }
        
        // Update the shipment with total items count
        $update_shipment_stmt = $conn->prepare("UPDATE transfer_shipments SET total_items = ? WHERE id = ?");
        $item_count = count($transfer_items);
        $update_shipment_stmt->bind_param("ii", $item_count, $shipment_id);
        $update_shipment_stmt->execute();
        
        // Decrement warehouse box quantities
        error_log("Starting warehouse box quantity updates. Selected boxes: " . json_encode($selected_boxes));
        
        foreach ($selected_boxes as $box) {
            $warehouse_box_id = (int)$box['id'];
            $requested_quantity = isset($box['request_quantity']) ? (int)$box['request_quantity'] : 1;
            
            error_log("Processing warehouse box ID: $warehouse_box_id, Requested quantity: $requested_quantity");
            error_log("Box data: " . json_encode($box));
            
            // Check if warehouse box exists and get current quantity
            $current_qty_stmt = $conn->prepare("SELECT quantity FROM warehouse_boxes WHERE id = ?");
            $current_qty_stmt->bind_param('i', $warehouse_box_id);
            $current_qty_stmt->execute();
            $current_qty_result = $current_qty_stmt->get_result();
            
            if ($current_qty_result->num_rows === 0) {
                error_log("ERROR: Warehouse box ID $warehouse_box_id does not exist!");
                throw new Exception("Warehouse box ID $warehouse_box_id not found");
            }
            
            $current_qty = $current_qty_result->fetch_assoc()['quantity'] ?? 'unknown';
            error_log("Current quantity for warehouse box ID $warehouse_box_id: $current_qty");
            
            // Update warehouse box quantity
            $update_warehouse_box_stmt = $conn->prepare("
                UPDATE warehouse_boxes 
                SET quantity = GREATEST(0, quantity - ?) 
                WHERE id = ?
            ");
            $update_warehouse_box_stmt->bind_param('ii', $requested_quantity, $warehouse_box_id);
            
            if (!$update_warehouse_box_stmt->execute()) {
                throw new Exception("Failed to update warehouse box quantity: " . $update_warehouse_box_stmt->error);
            }
            
            // Log the quantity update
            $affected_rows = $update_warehouse_box_stmt->affected_rows;
            if ($affected_rows > 0) {
                error_log("Updated warehouse box ID $warehouse_box_id: decremented by $requested_quantity");
                
                // Get new quantity after update
                $new_qty_stmt = $conn->prepare("SELECT quantity FROM warehouse_boxes WHERE id = ?");
                $new_qty_stmt->bind_param('i', $warehouse_box_id);
                $new_qty_stmt->execute();
                $new_qty_result = $new_qty_stmt->get_result();
                $new_qty = $new_qty_result->fetch_assoc()['quantity'] ?? 'unknown';
                error_log("New quantity for warehouse box ID $warehouse_box_id: $new_qty");
            } else {
                error_log("Warning: No rows affected when updating warehouse box ID $warehouse_box_id");
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Box-based transfer created successfully",
            'shipment_id' => $shipment_id,
            'shipment_number' => $shipment_number,
            'selected_boxes' => count($selected_boxes),
            'transfer_items' => count($transfer_items)
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Box transfer creation failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Create a box-based transfer to multiple destinations in one shipment
 * Expects:
 * - selected_boxes: JSON array of warehouse boxes metadata
 * - transfer_items: JSON array of { item_id, barcode_id, quantity, cost_price, selling_price, destination_store_id }
 * - notes
 */
function createBoxTransferMulti() {
    global $conn;
    
    try {
        $source_store_id = (int)($_POST['source_store_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $selected_boxes = json_decode($_POST['selected_boxes'] ?? '[]', true);
        $transfer_items = json_decode($_POST['transfer_items'] ?? '[]', true);

        if ($source_store_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid source store']);
            return;
        }
        if (empty($selected_boxes)) {
            echo json_encode(['success' => false, 'message' => 'No boxes selected']);
            return;
        }
        if (empty($transfer_items)) {
            echo json_encode(['success' => false, 'message' => 'No items selected for transfer']);
            return;
        }

        // Validate each item has destination_store_id
        foreach ($transfer_items as $idx => $item) {
            if (empty($item['destination_store_id']) || (int)$item['destination_store_id'] <= 0) {
                echo json_encode(['success' => false, 'message' => 'Each item must have a destination store']);
                return;
            }
        }

        $conn->begin_transaction();

        // Determine destination stores from items and pick a primary destination for the shipment row
        $destination_store_ids = [];
        foreach ($transfer_items as $it) {
            $ds = (int)($it['destination_store_id'] ?? 0);
            if ($ds > 0) { $destination_store_ids[$ds] = true; }
        }
        if (empty($destination_store_ids)) {
            throw new Exception('No destination store found in transfer items');
        }
        $destination_store_id_primary = (int)array_key_first($destination_store_ids);

        // Generate shipment number using first destination
        $shipment_number = generateShipmentNumber($source_store_id, $destination_store_id_primary);

        // Create shipment; keep transfer_type as 'box' for compatibility (match single-destination insert signature)
        $stmt = $conn->prepare("INSERT INTO transfer_shipments 
            (shipment_number, source_store_id, destination_store_id, status, transfer_type, notes, created_by, packed_by, received_by, packed_at, received_at) 
            VALUES (?, ?, ?, 'received', 'box', ?, ?, ?, ?, NOW(), NOW())");
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param('siisiii', $shipment_number, $source_store_id, $destination_store_id_primary, $notes, $user_id, $user_id, $user_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create shipment: ' . $stmt->error);
        }
        $shipment_id = $conn->insert_id;

        // Create transfer boxes (multiple instances based on requested quantity)
        $box_counter = 1;
        $warehouse_box_mapping = []; // Map transfer box number to warehouse box ID
        
        foreach ($selected_boxes as $box) {
            $requested_quantity = isset($box['request_quantity']) ? (int)$box['request_quantity'] : 1;
            $warehouse_box_id = (int)$box['id'];
            
            error_log("Processing warehouse box: ID=$warehouse_box_id, Name=" . ($box['box_name'] ?? 'N/A') . ", Requested Qty=$requested_quantity");
            
            // Create multiple box instances based on requested quantity
            for ($i = 1; $i <= $requested_quantity; $i++) {
                $box_number = $box_counter++;
                
                // Create a proper box label
                $box_label_parts = [];
                if (!empty($box['box_name'])) {
                    $box_label_parts[] = $box['box_name'];
                }
                if (!empty($box['box_type'])) {
                    $box_label_parts[] = '(' . $box['box_type'] . ')';
                }
                
                // Add instance number if multiple instances
                if ($requested_quantity > 1) {
                    $box_label_parts[] = 'Instance ' . $i;
                }
                
                $box_label = implode(' ', $box_label_parts);
                if (empty($box_label)) {
                    $box_label = 'Warehouse Box ' . $warehouse_box_id;
                }
                
                // Create transfer box record
                $box_stmt = $conn->prepare("
                    INSERT INTO transfer_boxes 
                    (shipment_id, box_number, box_label, warehouse_box_id, total_items) 
                    VALUES (?, ?, ?, ?, 0)
                ");
                $box_stmt->bind_param("isis", $shipment_id, $box_number, $box_label, $warehouse_box_id);
                
                if (!$box_stmt) { 
                    throw new Exception('Prepare transfer_boxes failed: ' . $conn->error); 
                }
                $box_stmt->execute();
                
                $transfer_box_id = $conn->insert_id;
                
                // Store mapping: transfer box number -> warehouse box ID
                $warehouse_box_mapping[$box_number] = $warehouse_box_id;
                
                error_log("Created transfer box instance $i for warehouse box ID: $warehouse_box_id (Box #$box_number, Transfer Box ID: $transfer_box_id)");
            }
        }

        // Map box_number => box_id so items can be assigned to chosen box
        $total_items = 0;
        $boxIdByNumber = [];
        $map_stmt = $conn->prepare("SELECT id, box_number FROM transfer_boxes WHERE shipment_id = ?");
        if (!$map_stmt) { throw new Exception('Prepare select transfer_boxes map failed: ' . $conn->error); }
        $map_stmt->bind_param('i', $shipment_id);
        $map_stmt->execute();
        $map_res = $map_stmt->get_result();
        while ($m = $map_res->fetch_assoc()) {
            $boxIdByNumber[(int)$m['box_number']] = (int)$m['id'];
        }
        
        error_log("Box mapping established: " . json_encode($boxIdByNumber));

        // Ensure transfer_items has destination_store_id column for precise per-item destination
        $has_dest_col = false;
        $colCheck = $conn->query("SHOW COLUMNS FROM transfer_items LIKE 'destination_store_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $has_dest_col = true;
        } else {
            if ($conn->query("ALTER TABLE transfer_items ADD COLUMN destination_store_id INT NULL AFTER selling_price") === true) {
                $has_dest_col = true;
                error_log('Added destination_store_id column to transfer_items');
            } else {
                error_log('Could not add destination_store_id to transfer_items: ' . $conn->error);
                $has_dest_col = false; // Continue without it
            }
        }

        $perBoxTotals = [];
        foreach ($transfer_items as $item) {
            $item_id = (int)$item['item_id'];
            $barcode_id = (int)$item['barcode_id'];
            $quantity = (int)$item['quantity'];
            $selling_price = (float)($item['selling_price'] ?? 0);
            $cost_price = (float)($item['cost_price'] ?? 0);
            $destination_store_id = (int)$item['destination_store_id'];
            $box_number = isset($item['box_number']) ? (int)$item['box_number'] : 1;
            $box_id_for_item = $boxIdByNumber[$box_number] ?? null;
            
            error_log("Processing item: Item ID $item_id, Box Number: $box_number, Mapped Box ID: " . ($box_id_for_item ?? 'null'));
            
            if (!$box_id_for_item) {
                // Fallback to first mapped box id
                $box_id_for_item = reset($boxIdByNumber) ?: null;
                error_log("Fallback: Using first available box ID: " . ($box_id_for_item ?? 'null'));
            }
            if (!$box_id_for_item) {
                throw new Exception('No transfer box found for item assignment');
            }

            // Calculate average cost for this item using the warehouse box mapping
            $warehouse_box_id = $warehouse_box_mapping[$box_number] ?? null;
            if ($warehouse_box_id) {
                $avg_cost = calculateItemAverageCost($warehouse_box_id, $item_id, $quantity);
                // Update item's base price with new average cost
                $update_base_price_stmt = $conn->prepare("UPDATE inventory_items SET base_price = ? WHERE id = ?");
                $update_base_price_stmt->bind_param('di', $avg_cost, $item_id);
                $update_base_price_stmt->execute();
                
                // Use new average cost for transfer
                $cost_price = $avg_cost;
                
                error_log("Calculated average cost for item $item_id: $avg_cost using warehouse box ID: $warehouse_box_id");
            } else {
                error_log("Warning: No warehouse box mapping found for transfer box number: $box_number");
            }

            $total_items += $quantity;
            $perBoxTotals[$box_id_for_item] = ($perBoxTotals[$box_id_for_item] ?? 0) + $quantity;

            if ($has_dest_col) {
                $sql = "INSERT INTO transfer_items 
                    (shipment_id, box_id, item_id, barcode_id, quantity_requested, quantity_packed, quantity_received, unit_cost, selling_price, destination_store_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_item = $conn->prepare($sql);
                if (!$stmt_item) { throw new Exception('Prepare transfer_items (with dest) failed: ' . $conn->error); }
                $stmt_item->bind_param('iiiiiiiddi', $shipment_id, $box_id_for_item, $item_id, $barcode_id, $quantity, $quantity, $quantity, $cost_price, $selling_price, $destination_store_id);
            } else {
                $sql = "INSERT INTO transfer_items 
                    (shipment_id, box_id, item_id, barcode_id, quantity_requested, quantity_packed, quantity_received, unit_cost, selling_price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_item = $conn->prepare($sql);
                if (!$stmt_item) { throw new Exception('Prepare transfer_items failed: ' . $conn->error); }
                $stmt_item->bind_param('iiiiiiidd', $shipment_id, $box_id_for_item, $item_id, $barcode_id, $quantity, $quantity, $quantity, $cost_price, $selling_price);
            }
            if (!$stmt_item->execute()) {
                throw new Exception('Failed to create transfer item: ' . $stmt_item->error);
            }

            // Inventory updates: add to destination store only (box transfers do not deduct warehouse here)
            $dest_stock_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE current_stock = current_stock + VALUES(current_stock), selling_price = VALUES(selling_price), cost_price = VALUES(cost_price)";
            $dest_stock_stmt = $conn->prepare($dest_stock_sql);
            if (!$dest_stock_stmt) { throw new Exception('Prepare dest stock failed: ' . $conn->error); }
            $dest_stock_stmt->bind_param('iiiidd', $destination_store_id, $item_id, $barcode_id, $quantity, $selling_price, $cost_price);
            $dest_stock_stmt->execute();

            // Ensure destination assignment
            $assignment_sql = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) 
                VALUES (?, ?, ?, 'Auto-assigned via multi-destination box transfer') 
                ON DUPLICATE KEY UPDATE is_active = 1, assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP";
            $assignment_stmt = $conn->prepare($assignment_sql);
            if (!$assignment_stmt) { throw new Exception('Prepare assignment failed: ' . $conn->error); }
            $assignment_stmt->bind_param('iii', $destination_store_id, $item_id, $user_id);
            $assignment_stmt->execute();

            // Log inbound transaction per destination
            $in_trans_sql = "INSERT INTO inventory_transactions (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                VALUES (?, ?, ?, 'in', ?, 'shipment', ?, 'inbound', ?, ?)";
            $in_trans_stmt = $conn->prepare($in_trans_sql);
            if (!$in_trans_stmt) { throw new Exception('Prepare inventory transaction failed: ' . $conn->error); }
            $in_notes = "Box transfer (multi) in from Main Warehouse: {$shipment_number}";
            $in_trans_stmt->bind_param('iiiiiis', $destination_store_id, $item_id, $barcode_id, $quantity, $shipment_id, $user_id, $in_notes);
            $in_trans_stmt->execute();
        }

        // Update per-box totals
        $upd_box = $conn->prepare("UPDATE transfer_boxes SET total_items = ? WHERE id = ?");
        if (!$upd_box) { throw new Exception('Prepare update box total failed: ' . $conn->error); }
        foreach ($perBoxTotals as $box_id => $qty_total) {
            $upd_box->bind_param('ii', $qty_total, $box_id);
            $upd_box->execute();
        }

        $upd_sh = $conn->prepare("UPDATE transfer_shipments SET total_items = ? WHERE id = ?");
        if (!$upd_sh) { throw new Exception('Prepare update shipment total failed: ' . $conn->error); }
        $upd_sh->bind_param('ii', $total_items, $shipment_id);
        $upd_sh->execute();

        // Decrement warehouse box quantities
        error_log("Starting warehouse box quantity updates in multi-destination transfer. Selected boxes: " . json_encode($selected_boxes));
        
        foreach ($selected_boxes as $box) {
            $warehouse_box_id = (int)$box['id'];
            $requested_quantity = isset($box['request_quantity']) ? (int)$box['request_quantity'] : 1;
            
            error_log("Processing warehouse box ID: $warehouse_box_id, Requested quantity: $requested_quantity");
            error_log("Box data: " . json_encode($box));
            
            // Check if warehouse box exists and get current quantity
            $current_qty_stmt = $conn->prepare("SELECT quantity FROM warehouse_boxes WHERE id = ?");
            $current_qty_stmt->bind_param('i', $warehouse_box_id);
            $current_qty_stmt->execute();
            $current_qty_result = $current_qty_stmt->get_result();
            
            if ($current_qty_result->num_rows === 0) {
                error_log("ERROR: Warehouse box ID $warehouse_box_id does not exist!");
                throw new Exception("Warehouse box ID $warehouse_box_id not found");
            }
            
            $current_qty = $current_qty_result->fetch_assoc()['quantity'] ?? 'unknown';
            error_log("Current quantity for warehouse box ID $warehouse_box_id: $current_qty");
            
            // Update warehouse box quantity
            $update_warehouse_box_stmt = $conn->prepare("
                UPDATE warehouse_boxes 
                SET quantity = GREATEST(0, quantity - ?) 
                WHERE id = ?
            ");
            $update_warehouse_box_stmt->bind_param('ii', $requested_quantity, $warehouse_box_id);
            
            if (!$update_warehouse_box_stmt->execute()) {
                throw new Exception("Failed to update warehouse box quantity: " . $update_warehouse_box_stmt->error);
            }
            
            // Log the quantity update
            $affected_rows = $update_warehouse_box_stmt->affected_rows;
            if ($affected_rows > 0) {
                error_log("Updated warehouse box ID $warehouse_box_id: decremented by $requested_quantity");
                
                // Get new quantity after update
                $new_qty_stmt = $conn->prepare("SELECT quantity FROM warehouse_boxes WHERE id = ?");
                $new_qty_stmt->bind_param('i', $warehouse_box_id);
                $new_qty_stmt->execute();
                $new_qty_result = $new_qty_stmt->get_result();
                $new_qty = $new_qty_result->fetch_assoc()['quantity'] ?? 'unknown';
                error_log("New quantity for warehouse box ID $warehouse_box_id: $new_qty");
            } else {
                error_log("Warning: No rows affected when updating warehouse box ID $warehouse_box_id");
            }
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Multi-destination box transfer created successfully',
            'shipment_id' => $shipment_id,
            'shipment_number' => $shipment_number,
            'destinations' => array_values(array_unique(array_map(function($i){return (int)$i['destination_store_id'];}, $transfer_items)))
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Multi-destination box transfer failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Pack a shipment (move from pending to in_transit)
 */
function packShipment() {
    global $conn;
    
    $shipment_id = (int)($_POST['shipment_id'] ?? 0);
    
    if ($shipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Get shipment details
        $shipment_query = "SELECT ts.*, s1.store_code as source_code, s2.store_code as dest_code 
                           FROM transfer_shipments ts
                           JOIN stores s1 ON ts.source_store_id = s1.id
                           JOIN stores s2 ON ts.destination_store_id = s2.id
                           WHERE ts.id = ? AND ts.status = 'pending'";
        $shipment_stmt = $conn->prepare($shipment_query);
        $shipment_stmt->bind_param('i', $shipment_id);
        $shipment_stmt->execute();
        $shipment_result = $shipment_stmt->get_result();
        
        if ($shipment_result->num_rows === 0) {
            throw new Exception('Shipment not found or already processed');
        }
        
        $shipment = $shipment_result->fetch_assoc();
        
        // Get shipment items
        $items_query = "SELECT * FROM transfer_items WHERE shipment_id = ?";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param('i', $shipment_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Process each item - complete transfer in one step
        while ($item = $items_result->fetch_assoc()) {
            $quantity = $item['quantity_requested'];
            
            // Step 1: Deduct from source store inventory (only if source is not main warehouse)
            if ($shipment['source_store_id'] != 1) {
                // Traditional store-to-store transfer: deduct from source
                $deduct_sql = "UPDATE store_inventory 
                              SET current_stock = current_stock - ? 
                              WHERE store_id = ? AND item_id = ? AND barcode_id = ? AND current_stock >= ?";
                $deduct_stmt = $conn->prepare($deduct_sql);
                $deduct_stmt->bind_param('iiiii', $quantity, $shipment['source_store_id'], $item['item_id'], $item['barcode_id'], $quantity);
                $deduct_stmt->execute();
                
                if ($deduct_stmt->affected_rows === 0) {
                    throw new Exception("Insufficient stock for item ID {$item['item_id']}");
                }
            }
            // If source is main warehouse (store_id = 1), skip deduction as it's box-based
            
            // Step 2: Add to destination store inventory
            $dest_stock_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                              current_stock = current_stock + VALUES(current_stock),
                              selling_price = VALUES(selling_price),
                              cost_price = VALUES(cost_price)";
            $dest_stock_stmt = $conn->prepare($dest_stock_sql);
            $dest_stock_stmt->bind_param('iiiidd', $shipment['destination_store_id'], $item['item_id'], $item['barcode_id'], $quantity, $item['selling_price'], $item['unit_cost']);
            $dest_stock_stmt->execute();
            
            // ENSURE STORE ASSIGNMENT EXISTS - Create if not exists
            $assignment_sql = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) 
                              VALUES (?, ?, ?, 'Auto-assigned via transfer packing') 
                              ON DUPLICATE KEY UPDATE 
                              is_active = 1,
                              assigned_by = VALUES(assigned_by),
                              assigned_at = CURRENT_TIMESTAMP";
            $assignment_stmt = $conn->prepare($assignment_sql);
            $assignment_stmt->bind_param('iii', $shipment['destination_store_id'], $item['item_id'], $user_id);
            $assignment_stmt->execute();
            
            // Step 3: Update transfer item quantities (both packed and received)
            $update_item_sql = "UPDATE transfer_items SET quantity_packed = ?, quantity_received = ? WHERE id = ?";
            $update_item_stmt = $conn->prepare($update_item_sql);
            $update_item_stmt->bind_param('iii', $quantity, $quantity, $item['id']);
            $update_item_stmt->execute();
            
            // Step 4: Log outbound transaction (from source) - only if source is not main warehouse
            if ($shipment['source_store_id'] != 1) {
                $out_trans_sql = "INSERT INTO inventory_transactions 
                                 (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                                 VALUES (?, ?, ?, 'out', ?, 'shipment', ?, 'outbound', ?, ?)";
                $out_trans_stmt = $conn->prepare($out_trans_sql);
                $out_notes = "Transfer out to {$shipment['dest_code']}: {$shipment['shipment_number']}";
                $user_id = $_SESSION['user_id'];
                $out_trans_stmt->bind_param('iiiiiis', $shipment['source_store_id'], $item['item_id'], $item['barcode_id'], $quantity, $shipment_id, $user_id, $out_notes);
                $out_trans_stmt->execute();
            }
            
            // Step 5: Log inbound transaction (to destination)
            $in_trans_sql = "INSERT INTO inventory_transactions 
                            (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                            VALUES (?, ?, ?, 'in', ?, 'shipment', ?, 'inbound', ?, ?)";
            $in_trans_stmt = $conn->prepare($in_trans_sql);
            $source_name = ($shipment['source_store_id'] == 1) ? "Main Warehouse" : $shipment['source_code'];
            $in_notes = "Transfer in from {$source_name}: {$shipment['shipment_number']}";
            $user_id = $_SESSION['user_id'];
            $in_trans_stmt->bind_param('iiiiiis', $shipment['destination_store_id'], $item['item_id'], $item['barcode_id'], $quantity, $shipment_id, $user_id, $in_notes);
            $in_trans_stmt->execute();
        }
        
        // Update shipment status - complete the transfer in one step
        $update_shipment_sql = "UPDATE transfer_shipments 
                               SET status = 'received', 
                                   packed_by = ?, 
                                   packed_at = NOW(), 
                                   shipped_at = NOW(),
                                   received_by = ?,
                                   received_at = NOW()
                               WHERE id = ?";
        $update_shipment_stmt = $conn->prepare($update_shipment_sql);
        $user_id = $_SESSION['user_id'];
        $update_shipment_stmt->bind_param('iii', $user_id, $user_id, $shipment_id);
        $update_shipment_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Transfer completed successfully! Items moved from source to destination.']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Receive a shipment (add to destination inventory)
 */
function receiveShipment() {
    global $conn;
    
    $shipment_id = (int)($_POST['shipment_id'] ?? 0);
    $received_items = json_decode($_POST['received_items'] ?? '[]', true);
    
    if ($shipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Get shipment details
        $shipment_query = "SELECT * FROM transfer_shipments WHERE id = ? AND status = 'in_transit'";
        $shipment_stmt = $conn->prepare($shipment_query);
        $shipment_stmt->bind_param('i', $shipment_id);
        $shipment_stmt->execute();
        $shipment_result = $shipment_stmt->get_result();
        
        if ($shipment_result->num_rows === 0) {
            throw new Exception('Shipment not found or not ready for receiving');
        }
        
        $shipment = $shipment_result->fetch_assoc();
        
        // Process received items
        foreach ($received_items as $item_data) {
            $transfer_item_id = (int)($item_data['transfer_item_id'] ?? 0);
            $quantity_received = (int)($item_data['quantity_received'] ?? 0);
            
            if ($transfer_item_id <= 0 || $quantity_received <= 0) {
                continue;
            }
            
            // Get transfer item details
            $item_query = "SELECT * FROM transfer_items WHERE id = ? AND shipment_id = ?";
            $item_stmt = $conn->prepare($item_query);
            $item_stmt->bind_param('ii', $transfer_item_id, $shipment_id);
            $item_stmt->execute();
            $item_result = $item_stmt->get_result();
            
            if ($item_result->num_rows === 0) {
                continue;
            }
            
            $item = $item_result->fetch_assoc();
            
            // Update received quantity
            $update_item_sql = "UPDATE transfer_items SET quantity_received = ? WHERE id = ?";
            $update_item_stmt = $conn->prepare($update_item_sql);
            $update_item_stmt->bind_param('ii', $quantity_received, $transfer_item_id);
            $update_item_stmt->execute();
            
            // Add to destination store inventory
            $dest_stock_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                              current_stock = current_stock + VALUES(current_stock),
                              selling_price = VALUES(selling_price),
                              cost_price = VALUES(cost_price)";
            $dest_stock_stmt = $conn->prepare($dest_stock_sql);
            $dest_stock_stmt->bind_param('iiidd', $shipment['destination_store_id'], $item['item_id'], $item['barcode_id'], $quantity_received, $item['selling_price'], $item['unit_cost']);
            $dest_stock_stmt->execute();
            
            // ENSURE STORE ASSIGNMENT EXISTS - Create if not exists
            $assignment_sql = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) 
                              VALUES (?, ?, ?, 'Auto-assigned via transfer receipt') 
                              ON DUPLICATE KEY UPDATE 
                              is_active = 1,
                              assigned_by = VALUES(assigned_by),
                              assigned_at = CURRENT_TIMESTAMP";
            $assignment_stmt = $conn->prepare($assignment_sql);
            $assignment_stmt->bind_param('iii', $shipment['destination_store_id'], $item['item_id'], $user_id);
            $assignment_stmt->execute();
            
            // Log inbound transaction
            $trans_sql = "INSERT INTO inventory_transactions 
                         (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                         VALUES (?, ?, ?, 'in', ?, 'shipment', ?, 'inbound', ?, ?)";
            $trans_stmt = $conn->prepare($trans_sql);
            $notes = "Received from shipment: {$shipment['shipment_number']}";
            $user_id = $_SESSION['user_id'];
            $trans_stmt->bind_param('iiiiiis', $shipment['destination_store_id'], $item['item_id'], $item['barcode_id'], $quantity_received, $shipment_id, $user_id, $notes);
            $trans_stmt->execute();
        }
        
        // Update shipment status
        $update_shipment_sql = "UPDATE transfer_shipments SET status = 'received', received_by = ?, received_at = NOW() WHERE id = ?";
        $update_shipment_stmt = $conn->prepare($update_shipment_sql);
        $user_id = $_SESSION['user_id'];
        $update_shipment_stmt->bind_param('ii', $user_id, $shipment_id);
        $update_shipment_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Shipment received successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Cancel a shipment
 */
function cancelShipment() {
    global $conn;
    
    $shipment_id = (int)($_POST['shipment_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if ($shipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
        exit;
    }
    
    $conn->begin_transaction();
    
    try {
        // Get shipment details
        $shipment_query = "SELECT * FROM transfer_shipments WHERE id = ? AND status IN ('pending', 'in_transit')";
        $shipment_stmt = $conn->prepare($shipment_query);
        $shipment_stmt->bind_param('i', $shipment_id);
        $shipment_stmt->execute();
        $shipment_result = $shipment_stmt->get_result();
        
        if ($shipment_result->num_rows === 0) {
            throw new Exception('Shipment not found or cannot be cancelled');
        }
        
        $shipment = $shipment_result->fetch_assoc();
        
        // If shipment was already packed (in_transit), restore source inventory
        if ($shipment['status'] === 'in_transit') {
            $items_query = "SELECT * FROM transfer_items WHERE shipment_id = ?";
            $items_stmt = $conn->prepare($items_query);
            $items_stmt->bind_param('i', $shipment_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                // Restore stock to source
                $restore_sql = "UPDATE store_inventory 
                               SET current_stock = current_stock + ? 
                               WHERE store_id = ? AND item_id = ? AND barcode_id = ?";
                $restore_stmt = $conn->prepare($restore_sql);
                $restore_stmt->bind_param('iiii', $item['quantity_packed'], $shipment['source_store_id'], $item['item_id'], $item['barcode_id']);
                $restore_stmt->execute();
                
                // Log restoration transaction
                $trans_sql = "INSERT INTO inventory_transactions 
                             (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                             VALUES (?, ?, ?, 'in', ?, 'shipment', ?, 'cancelled', ?, ?)";
                $trans_stmt = $conn->prepare($trans_sql);
                $notes = "Cancelled shipment restoration: {$shipment['shipment_number']} - {$reason}";
                $user_id = $_SESSION['user_id'];
                $trans_stmt->bind_param('iiiiiis', $shipment['source_store_id'], $item['item_id'], $item['barcode_id'], $item['quantity_packed'], $shipment_id, $user_id, $notes);
                $trans_stmt->execute();
            }
        }
        
        // Cancel shipment
        $cancel_sql = "UPDATE transfer_shipments SET status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), '\nCancelled: ', ?) WHERE id = ?";
        $cancel_stmt = $conn->prepare($cancel_sql);
        $cancel_stmt->bind_param('si', $reason, $shipment_id);
        $cancel_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Shipment cancelled successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Generate shipment number
 */
function generateShipmentNumber($source_store_id, $destination_store_id) {
    global $conn;
    
    // Simple prefix using store IDs
    $prefix = 'TRF-' . str_pad($source_store_id, 2, '0', STR_PAD_LEFT) . str_pad($destination_store_id, 2, '0', STR_PAD_LEFT) . '-';
    
    // Get next number
    $query = "SELECT MAX(CAST(SUBSTRING(shipment_number, LENGTH(?) + 1) AS UNSIGNED)) as max_num 
              FROM transfer_shipments 
              WHERE shipment_number LIKE CONCAT(?, '%')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $prefix, $prefix);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $next_number = 1;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $next_number = ($row['max_num'] ?? 0) + 1;
    }
    
    return $prefix . str_pad($next_number, 6, '0', STR_PAD_LEFT);
}

/**
 * Update inventory for transfer (deduct from source, add to destination)
 */
function updateInventoryForTransfer($source_store_id, $destination_store_id, $item_id, $barcode_id, $quantity, $shipment_id, $unit_cost, $selling_price) {
    global $conn;
    
    // Step 1: Deduct from source store inventory
    $deduct_sql = "UPDATE store_inventory 
                  SET current_stock = current_stock - ? 
                  WHERE store_id = ? AND item_id = ? AND barcode_id = ? AND current_stock >= ?";
    $deduct_stmt = $conn->prepare($deduct_sql);
    
    if (!$deduct_stmt) {
        throw new Exception("Failed to prepare deduct statement: " . $conn->error);
    }
    
    $deduct_stmt->bind_param('iiiii', $quantity, $source_store_id, $item_id, $barcode_id, $quantity);
    $deduct_stmt->execute();
    
    if ($deduct_stmt->affected_rows === 0) {
        throw new Exception("Insufficient stock for item ID {$item_id}");
    }
    
    // Step 2: Add to destination store inventory
    $dest_stock_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                      VALUES (?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      current_stock = current_stock + VALUES(current_stock),
                      selling_price = VALUES(selling_price),
                      cost_price = VALUES(cost_price)";
    $dest_stock_stmt = $conn->prepare($dest_stock_sql);
    
    if (!$dest_stock_stmt) {
        throw new Exception("Failed to prepare destination stock statement: " . $conn->error);
    }
    
    $dest_stock_stmt->bind_param('iiiidd', $destination_store_id, $item_id, $barcode_id, $quantity, $selling_price, $unit_cost);
    $dest_stock_stmt->execute();
    
    // ENSURE STORE ASSIGNMENT EXISTS - Create if not exists
    $user_id = $_SESSION['user_id'] ?? 1; // Fallback to admin if no session
    $assignment_sql = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) 
                      VALUES (?, ?, ?, 'Auto-assigned via transfer function') 
                      ON DUPLICATE KEY UPDATE 
                      is_active = 1,
                      assigned_by = VALUES(assigned_by),
                      assigned_at = CURRENT_TIMESTAMP";
    $assignment_stmt = $conn->prepare($assignment_sql);
    $assignment_stmt->bind_param('iii', $destination_store_id, $item_id, $user_id);
    $assignment_stmt->execute();
    
    // Step 3: Create transactions - check which columns actually exist
    $columns_check = $conn->query("SHOW COLUMNS FROM inventory_transactions");
    $available_columns = [];
    while ($column = $columns_check->fetch_assoc()) {
        $available_columns[] = $column['Field'];
    }
    
    error_log("Available inventory_transactions columns: " . implode(', ', $available_columns));
    
    // Build dynamic INSERT statements based on available columns
    $base_columns = ['store_id', 'item_id', 'barcode_id', 'transaction_type', 'quantity'];
    $base_values = [$source_store_id, $item_id, $barcode_id, 'outbound', $quantity];
    $base_types = 'iiiis';
    
    $outbound_columns = $base_columns;
    $outbound_values = $base_values;
    $outbound_types = $base_types;
    
    // Add optional columns if they exist
    if (in_array('shipment_id', $available_columns)) {
        $outbound_columns[] = 'shipment_id';
        $outbound_values[] = $shipment_id;
        $outbound_types .= 'i';
    }
    
    if (in_array('transfer_type', $available_columns)) {
        $outbound_columns[] = 'transfer_type';
        $outbound_values[] = 'outbound';
        $outbound_types .= 's';
    }
    
    if (in_array('created_by', $available_columns)) {
        $outbound_columns[] = 'created_by';
        $outbound_values[] = $_SESSION['user_id'];
        $outbound_types .= 'i';
    }
    
    if (in_array('user_id', $available_columns)) {
        $outbound_columns[] = 'user_id';
        $outbound_values[] = $_SESSION['user_id'];
        $outbound_types .= 'i';
    }
    
    // Create outbound transaction
    $outbound_sql = "INSERT INTO inventory_transactions (" . implode(', ', $outbound_columns) . ") VALUES (" . str_repeat('?,', count($outbound_columns) - 1) . "?)";
    $outbound_stmt = $conn->prepare($outbound_sql);
    
    if (!$outbound_stmt) {
        throw new Exception("Failed to prepare outbound transaction: " . $conn->error);
    }
    
    $outbound_stmt->bind_param($outbound_types, ...$outbound_values);
    $outbound_stmt->execute();
    
    // Create inbound transaction (copy structure but change values)
    $inbound_values = $outbound_values;
    $inbound_values[0] = $destination_store_id; // Change store_id
    $inbound_values[3] = 'inbound'; // Change transaction_type
    
    // Update transfer_type if it exists
    if (in_array('transfer_type', $available_columns)) {
        $transfer_type_index = array_search('transfer_type', $outbound_columns);
        if ($transfer_type_index !== false) {
            $inbound_values[$transfer_type_index] = 'inbound';
        }
    }
    
    $inbound_stmt = $conn->prepare($outbound_sql); // Same SQL structure
    
    if (!$inbound_stmt) {
        throw new Exception("Failed to prepare inbound transaction: " . $conn->error);
    }
    
    $inbound_stmt->bind_param($outbound_types, ...$inbound_values);
    $inbound_stmt->execute();
    
    error_log("Created inventory transactions using columns: " . implode(', ', $outbound_columns));
}

/**
 * Create direct transfer with immediate completion
 */
function createDirectTransfer() {
    global $conn;
    
    try {
        $source_store_id = 1; // Always main warehouse
        $destination_store_id = (int)($_POST['destination_store_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $selected_items = json_decode($_POST['selected_items'] ?? '[]', true);
        
        // Validation
        if ($destination_store_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid destination store']);
            return;
        }
        
        if (empty($selected_items)) {
            echo json_encode(['success' => false, 'message' => 'No items selected']);
            return;
        }
        
        // Validate stock availability BEFORE starting transaction
        foreach ($selected_items as $item) {
            $stock_check = $conn->prepare("
                SELECT COALESCE(current_stock, 0) as available_stock 
                FROM store_inventory 
                WHERE store_id = 1 AND item_id = ? AND barcode_id = ?
            ");
            $stock_check->bind_param('ii', $item['item_id'], $item['barcode_id']);
            $stock_check->execute();
            $stock_result = $stock_check->get_result();
            $stock_row = $stock_result->fetch_assoc();
            
            $available = $stock_row ? (int)$stock_row['available_stock'] : 0;
            if ($available < (int)$item['quantity']) {
                echo json_encode([
                    'success' => false, 
                    'message' => "Insufficient stock for {$item['item_name']}. Available: {$available}, Requested: {$item['quantity']}"
                ]);
                return;
            }
        }
        
        $conn->begin_transaction();
        
        // Generate shipment number
        $shipment_number = 'DT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create transfer shipment - IMMEDIATELY SET TO RECEIVED
        $shipment_stmt = $conn->prepare("
            INSERT INTO transfer_shipments 
            (shipment_number, source_store_id, destination_store_id, status, transfer_type, notes, created_by, packed_by, received_by, packed_at, received_at) 
            VALUES (?, ?, ?, 'received', 'direct', ?, ?, ?, ?, NOW(), NOW())
        ");
        $user_id = $_SESSION['user_id'];
        $shipment_stmt->bind_param("siisiii", $shipment_number, $source_store_id, $destination_store_id, $notes, $user_id, $user_id, $user_id);
        
        if (!$shipment_stmt->execute()) {
            throw new Exception("Failed to create transfer shipment: " . $shipment_stmt->error);
        }
        
        $shipment_id = $conn->insert_id;
        
        // Create single transfer box for direct transfers
        $box_stmt = $conn->prepare("
            INSERT INTO transfer_boxes 
            (shipment_id, box_number, box_label, total_items) 
            VALUES (?, 1, 'Direct Transfer Items', ?)
        ");
        $total_quantity = array_sum(array_column($selected_items, 'quantity'));
        $box_stmt->bind_param("ii", $shipment_id, $total_quantity);
        $box_stmt->execute();
        $box_id = $conn->insert_id;
        
        // Process each item - COMPLETE TRANSFER IMMEDIATELY
        foreach ($selected_items as $item) {
            $quantity = (int)$item['quantity'];
            $item_id = (int)$item['item_id'];
            $barcode_id = (int)$item['barcode_id'];
            $cost_price = (float)$item['cost_price'];
            $selling_price = (float)$item['selling_price'];
            
            // For direct transfers, we don't have a warehouse box, so we'll use the item's existing base price
            // But we can still update the base price if needed for consistency
            $item_query = "SELECT base_price FROM inventory_items WHERE id = ?";
            $item_stmt_check = $conn->prepare($item_query);
            $item_stmt_check->bind_param('i', $item_id);
            $item_stmt_check->execute();
            $item_result = $item_stmt_check->get_result();
            
            if ($item_result->num_rows > 0) {
                $item_data = $item_result->fetch_assoc();
                $existing_base_price = (float)($item_data['base_price'] ?? 0);
                
                // If no base price exists, set it to the cost price
                if ($existing_base_price <= 0 && $cost_price > 0) {
                    $update_base_price_stmt = $conn->prepare("UPDATE inventory_items SET base_price = ? WHERE id = ?");
                    $update_base_price_stmt->bind_param('di', $cost_price, $item_id);
                    $update_base_price_stmt->execute();
                }
            }
            
            // Create transfer item record - SET ALL QUANTITIES IMMEDIATELY
            $item_stmt = $conn->prepare("
                INSERT INTO transfer_items 
                (shipment_id, box_id, item_id, barcode_id, quantity_requested, quantity_packed, quantity_received, unit_cost, selling_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $item_stmt->bind_param("iiiiiiidd", $shipment_id, $box_id, $item_id, $barcode_id, $quantity, $quantity, $quantity, $cost_price, $selling_price);
            
            if (!$item_stmt->execute()) {
                throw new Exception("Failed to create transfer item: " . $item_stmt->error);
            }
            
            // IMMEDIATE INVENTORY UPDATE - Deduct from warehouse
            $deduct_sql = "UPDATE store_inventory 
                          SET current_stock = current_stock - ? 
                          WHERE store_id = 1 AND item_id = ? AND barcode_id = ? AND current_stock >= ?";
            $deduct_stmt = $conn->prepare($deduct_sql);
            $deduct_stmt->bind_param('iiii', $quantity, $item_id, $barcode_id, $quantity);
            $deduct_stmt->execute();
            
            if ($deduct_stmt->affected_rows === 0) {
                throw new Exception("Failed to deduct warehouse stock for {$item['item_name']}");
            }
            
            // IMMEDIATE INVENTORY UPDATE - Add to destination store
            $dest_stock_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                              current_stock = current_stock + VALUES(current_stock),
                              selling_price = VALUES(selling_price),
                              cost_price = VALUES(cost_price)";
            $dest_stock_stmt = $conn->prepare($dest_stock_sql);
            $dest_stock_stmt->bind_param('iiiidd', $destination_store_id, $item_id, $barcode_id, $quantity, $selling_price, $cost_price);
            $dest_stock_stmt->execute();
            
            // ENSURE STORE ASSIGNMENT EXISTS - Create if not exists
            $assignment_sql = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) 
                              VALUES (?, ?, ?, 'Auto-assigned via direct transfer') 
                              ON DUPLICATE KEY UPDATE 
                              is_active = 1,
                              assigned_by = VALUES(assigned_by),
                              assigned_at = CURRENT_TIMESTAMP";
            $assignment_stmt = $conn->prepare($assignment_sql);
            $assignment_stmt->bind_param('iii', $destination_store_id, $item_id, $user_id);
            $assignment_stmt->execute();
            
            // Log outbound transaction (from warehouse)
            $out_trans_sql = "INSERT INTO inventory_transactions 
                             (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                             VALUES (1, ?, ?, 'out', ?, 'shipment', ?, 'outbound', ?, ?)";
            $out_trans_stmt = $conn->prepare($out_trans_sql);
            $out_notes = "Direct transfer out to destination: {$shipment_number}";
            $out_trans_stmt->bind_param('iiiiis', $item_id, $barcode_id, $quantity, $shipment_id, $user_id, $out_notes);
            $out_trans_stmt->execute();
            
            // Log inbound transaction (to destination store)
            $in_trans_sql = "INSERT INTO inventory_transactions 
                            (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, shipment_id, transfer_type, user_id, notes) 
                            VALUES (?, ?, ?, 'in', ?, 'shipment', ?, 'inbound', ?, ?)";
            $in_trans_stmt = $conn->prepare($in_trans_sql);
            $in_notes = "Direct transfer in from Main Warehouse: {$shipment_number}";
            $in_trans_stmt->bind_param('iiiiiis', $destination_store_id, $item_id, $barcode_id, $quantity, $shipment_id, $user_id, $in_notes);
            $in_trans_stmt->execute();
        }
        
        // Update shipment total
        $update_stmt = $conn->prepare("UPDATE transfer_shipments SET total_items = ? WHERE id = ?");
        $item_count = count($selected_items);
        $update_stmt->bind_param("ii", $item_count, $shipment_id);
        $update_stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Direct transfer completed successfully",
            'shipment_id' => $shipment_id,
            'shipment_number' => $shipment_number,
            'total_items' => $item_count,
            'total_quantity' => $total_quantity,
            'status' => 'received'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Direct transfer creation failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Calculate average cost for transfer items
 * Box cost is distributed proportionally based on item quantities
 * 
 * Example:
 * - Box unit cost: $100
 * - Item 1 quantity: 5 (gets $50 worth of box cost)
 * - Item 2 quantity: 3 (gets $30 worth of box cost)  
 * - Item 3 quantity: 2 (gets $20 worth of box cost)
 * 
 * Formula: (box_unit_cost * quantity) / total_items_in_box
 */
function calculateAverageCost() {
    global $conn;
    
    try {
        $box_id = (int)($_POST['box_id'] ?? 0);
        $item_id = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        if ($box_id <= 0 || $item_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid box or item ID']);
            return;
        }
        
        // Get box unit cost
        $box_query = "SELECT unit_cost FROM warehouse_boxes WHERE id = ?";
        $box_stmt = $conn->prepare($box_query);
        $box_stmt->bind_param('i', $box_id);
        $box_stmt->execute();
        $box_result = $box_stmt->get_result();
        
        if ($box_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Box not found']);
            return;
        }
        
        $box_data = $box_result->fetch_assoc();
        $box_unit_cost = (float)($box_data['unit_cost'] ?? 0);
        
        // Get item base price
        $item_query = "SELECT base_price FROM inventory_items WHERE id = ?";
        $item_stmt = $conn->prepare($item_query);
        $item_stmt->bind_param('i', $item_id);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();
        
        if ($item_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
            return;
        }
        
        $item_data = $item_result->fetch_assoc();
        $item_base_price = (float)($item_data['base_price'] ?? 0);
        
        // Calculate average cost: box cost split per item quantity + base price average
        $average_cost = 0;
        $calculation_details = [];
        
        if ($box_unit_cost > 0 && $item_base_price > 0) {
            // Both costs available: 
            // 1. Box cost per unit = box_unit_cost / item_quantity (split per item)
            // 2. Calculate average: (box_cost_per_unit + item_base_price) / 2
            $box_cost_per_unit = $box_unit_cost / max(1, $quantity);
            $average_cost = ($box_cost_per_unit + $item_base_price) / 2;
            $calculation_details = [
                'box_unit_cost' => $box_unit_cost,
                'item_quantity' => $quantity,
                'box_cost_per_unit' => round($box_cost_per_unit, 2),
                'item_base_price' => $item_base_price,
                'calculation' => 'per_item_quantity_split'
            ];
        } elseif ($box_unit_cost > 0) {
            // Only box cost available: split per item quantity
            $average_cost = $box_unit_cost / max(1, $quantity);
            $calculation_details = [
                'box_unit_cost' => $box_unit_cost,
                'item_quantity' => $quantity,
                'calculation' => 'per_item_quantity_split'
            ];
        } elseif ($item_base_price > 0) {
            // Only item base price available
            $average_cost = $item_base_price;
            $calculation_details = [
                'item_base_price' => $item_base_price,
                'calculation' => 'item_base_price'
            ];
        } else {
            // No costs available
            $average_cost = 0;
            $calculation_details = [
                'calculation' => 'no_cost'
            ];
        }
        
        // Ensure average cost is reasonable
        $average_cost = max(0.01, min(999999.99, $average_cost));
        
        echo json_encode([
            'success' => true,
            'average_cost' => round($average_cost, 2),
            'calculation_details' => $calculation_details,
            'message' => 'Average cost calculated successfully'
        ]);
        
    } catch (Exception $e) {
        error_log("Average cost calculation failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to calculate average cost: ' . $e->getMessage()]);
    }
}
?> 