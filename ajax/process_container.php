<?php
// Turn off error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

require_once 'ajax_session_init.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Not admin']);
    exit;
}



// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add':
        addContainer();
        break;
    case 'edit':
        editContainer();
        break;
    case 'delete':
        deleteContainer();
        break;
    case 'process':
        processContainer();
        break;
    case 'update_status':
        updateContainerStatus();
        break;
    case 'get_financial_summary':
        getFinancialSummary();
        break;
    case 'calculate_costs':
        calculateCosts();
        break;
    case 'add_item':
        addItemToContainer();
        break;
    case 'remove_item':
        removeItemFromContainer();
        break;
    case 'get_container_items':
        getContainerItems();
        break;
    case 'update_item':
        updateContainerItem();
        break;
    case 'test':
        echo json_encode(['success' => true, 'message' => 'Test endpoint working', 'session' => $_SESSION]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new container (simplified bulk mode only)
 */
function addContainer() {
    global $conn;
    
    error_log("=== ADD CONTAINER FUNCTION STARTED ===");
    error_log("POST data: " . print_r($_POST, true));
    
    // Check database connection
    if (!$conn || $conn->connect_error) {
        error_log("Database connection failed: " . ($conn ? $conn->connect_error : 'No connection'));
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    try {
        // Validate required fields
        $required_fields = ['container_number', 'supplier_id', 'total_weight_kg', 'total_price'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => "Field $field is required"]);
                return;
            }
        }
        
        // Sanitize inputs
        $container_number = sanitize_input($_POST['container_number']);
        $supplier_id = (int)$_POST['supplier_id'];
        $total_weight_kg = (float)$_POST['total_weight_kg'];
        $total_price = (float)$_POST['total_price'];
        $shipment_cost = isset($_POST['shipment_cost']) ? (float)$_POST['shipment_cost'] : 0;
        $profit_margin_percentage = isset($_POST['profit_margin_percentage']) ? (float)$_POST['profit_margin_percentage'] : 0;
        $amount_paid = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0;
        $arrival_date = !empty($_POST['arrival_date']) ? $_POST['arrival_date'] : null;
        $notes = sanitize_input($_POST['notes']);
        
        // Calculate derived values
        $price_per_kg = $total_weight_kg > 0 ? ($total_price / $total_weight_kg) : 0;
        $base_cost = $total_price; // In bulk mode, total_price is the base cost
        $total_cost = $base_cost + $shipment_cost;
        $remaining_balance = $total_cost - $amount_paid;
        
        // Check if required tables exist
        $required_tables = ['containers', 'container_items', 'container_item_details'];
        foreach ($required_tables as $table) {
            $table_check = $conn->query("SHOW TABLES LIKE '$table'");
            if (!$table_check || $table_check->num_rows === 0) {
                error_log("Required table '$table' does not exist");
                echo json_encode(['success' => false, 'message' => "Required table '$table' does not exist"]);
                return;
            }
        }
        
        // Check if container number already exists
        $check_sql = "SELECT id FROM containers WHERE container_number = ?";
        $check_stmt = $conn->prepare($check_sql);
        if (!$check_stmt) {
            throw new Exception('Failed to prepare container number check query: ' . $conn->error);
        }
        $check_stmt->bind_param('s', $container_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Container number already exists']);
            return;
        }
        
        // Start transaction
        $conn->begin_transaction();
        error_log("Transaction started");
        
        try {
        // Insert container
        $sql = "INSERT INTO containers (container_number, supplier_id, total_weight_kg, price_per_kg, 
                    total_cost, amount_paid, remaining_balance, shipment_cost, profit_margin_percentage, 
                    arrival_date, notes, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare container insert query: ' . $conn->error);
        }
        $user_id = $_SESSION['user_id'];
        
        error_log("About to insert container with data: " . print_r([
            'container_number' => $container_number,
            'supplier_id' => $supplier_id,
            'total_weight_kg' => $total_weight_kg,
            'price_per_kg' => $price_per_kg,
            'total_cost' => $total_cost,
            'amount_paid' => $amount_paid,
            'remaining_balance' => $remaining_balance,
            'shipment_cost' => $shipment_cost,
            'profit_margin_percentage' => $profit_margin_percentage,
            'arrival_date' => $arrival_date,
            'notes' => $notes,
            'user_id' => $user_id
        ], true));
        // Fixed type string to match 12 parameters: siddddddsssi
        error_log("Binding container parameters with types: siddddddsssi");
        $bind_result = $stmt->bind_param('siddddddsssi', $container_number, $supplier_id, $total_weight_kg, 
                         $price_per_kg, $total_cost, $amount_paid, $remaining_balance, 
                         $shipment_cost, $profit_margin_percentage, 
                         $arrival_date, $notes, $user_id);
        
        if (!$bind_result) {
            throw new Exception('Failed to bind container parameters: ' . $stmt->error);
        }
        
        error_log("Executing container insert...");
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert container: ' . $stmt->error);
        }
            
            $container_id = $conn->insert_id;
            
            // Process items if provided
            if (!empty($_POST['items_data'])) {
                error_log("Processing items data: " . $_POST['items_data']);
                $items_data = json_decode($_POST['items_data'], true);
                if (is_array($items_data)) {
                    error_log("Found " . count($items_data) . " items to process");
                    foreach ($items_data as $item) {
                        $item_data = [];
                        
                        switch ($item['type']) {
                            case 'box':
                                // Handle boxes separately
                                $box_data = validateAndPrepareBoxItemForCreation($item);
                                if (!empty($box_data)) {
                                    addBoxToContainer($container_id, $box_data);
                                }
                                break;
                            case 'existing_item':
                                $item_data = validateAndPrepareExistingItemForCreation($item);
                                break;
                            case 'new_item':
                                $item_data = validateAndPrepareNewItemForCreation($item);
                                break;
                        }
                        
                        if (!empty($item_data)) {
                            // Insert container item with new clean structure
                            $insert_sql = "INSERT INTO container_items (
                                container_id, item_type, item_id, quantity_in_container, 
                                is_processed, processed_at, processed_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            
                            $insert_stmt = $conn->prepare($insert_sql);
                            
                            // Create variables for bind_param (cannot pass literals by reference)
                            $is_processed = 0;
                            $processed_at = null;
                            $processed_by = null;
                            
                            $insert_stmt->bind_param(
                'isiisss',
                $container_id,
                $item['type'],
                $item_data['item_id'],
                $item_data['quantity_in_container'],
                $is_processed,
                $processed_at,
                $processed_by
                            );
                            
                            if (!$insert_stmt->execute()) {
                                throw new Exception('Failed to insert container item: ' . $insert_stmt->error);
                            }
                            
                            $container_item_id = $conn->insert_id;
                            
                            // If this is a new item, also insert into container_item_details
                            if ($item['type'] === 'new_item' && !empty($item_data['name'])) {
                                $details_sql = "INSERT INTO container_item_details (
                                    container_item_id, name, code, description, category_id, 
                                    brand, size, color, material, unit_cost, selling_price
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                
                                $details_stmt = $conn->prepare($details_sql);
                                if (!$details_stmt) {
                                    throw new Exception('Failed to prepare details statement: ' . $conn->error);
                                }
                                
                                // Create variables for bind_param (cannot pass literals by reference)
                                $details_unit_cost = $item_data['unit_cost'] ?? 0.00;
                                $details_selling_price = $item_data['selling_price'] ?? 0.00;
                                
                                $details_stmt->bind_param('isssissssdd',
                                    $container_item_id,
                                    $item_data['name'],
                                    $item_data['code'],
                                    $item_data['description'],
                                    $item_data['category_id'],
                                    $item_data['brand'],
                                    $item_data['size'],
                                    $item_data['color'],
                                    $item_data['material'],
                                    $details_unit_cost,
                                    $details_selling_price
                                );
                                
                                if (!$details_stmt->execute()) {
                                    throw new Exception('Failed to insert item details: ' . $details_stmt->error);
                                }
                            }
                        }
                    }
                }
            }
            
            // Create financial summary record if table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'container_financial_summary'");
            if ($check_table && $check_table->num_rows > 0) {
                $total_all_costs = $total_cost + $shipment_cost;
                $expected_revenue = $total_all_costs * (1 + $profit_margin_percentage / 100);
                
                $financial_sql = "INSERT INTO container_financial_summary 
                                 (container_id, base_cost, shipment_cost, total_all_costs, 
                                  profit_margin_percentage, expected_selling_total, actual_selling_total, actual_profit) 
                                 VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00)";
                $financial_stmt = $conn->prepare($financial_sql);
                if (!$financial_stmt) {
                    throw new Exception('Failed to prepare financial summary insert query: ' . $conn->error);
                }
                $financial_stmt->bind_param('iddddd', $container_id, $total_cost, $shipment_cost, 
                                           $total_all_costs, $profit_margin_percentage, $expected_revenue);
                
                if (!$financial_stmt->execute()) {
                    throw new Exception('Failed to create financial summary: ' . $financial_stmt->error);
                }
            }
            
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Container added successfully',
                'container_id' => $container_id
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add container error: " . $e->getMessage());
        error_log("Add container error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'An error occurred while adding the container: ' . $e->getMessage()]);
    } catch (Error $e) {
        error_log("Add container fatal error: " . $e->getMessage());
        error_log("Add container fatal error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'A fatal error occurred while adding the container: ' . $e->getMessage()]);
    }
}

/**
 * Edit an existing container (simplified)
 */
function editContainer() {
    global $conn;
    
    try {
        // Validate required fields
        $required_fields = ['container_id', 'container_number', 'supplier_id', 'total_weight_kg', 'total_price'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => "Field $field is required"]);
                return;
            }
        }
        
        // Sanitize inputs
        $container_id = (int)$_POST['container_id'];
        $container_number = sanitize_input($_POST['container_number']);
        $supplier_id = (int)$_POST['supplier_id'];
        $total_weight_kg = (float)$_POST['total_weight_kg'];
        $total_price = (float)$_POST['total_price'];
        $shipment_cost = isset($_POST['shipment_cost']) ? (float)$_POST['shipment_cost'] : 0;
        $profit_margin_percentage = isset($_POST['profit_margin_percentage']) ? (float)$_POST['profit_margin_percentage'] : 0;
        $amount_paid = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0;
        $arrival_date = !empty($_POST['arrival_date']) ? $_POST['arrival_date'] : null;
        $status = sanitize_input($_POST['status']);
        $notes = sanitize_input($_POST['notes']);
        $actual_profit = isset($_POST['actual_profit']) ? (float)$_POST['actual_profit'] : 0;
        
        // Calculate derived values
        $price_per_kg = $total_weight_kg > 0 ? ($total_price / $total_weight_kg) : 0;
        $base_cost = $total_price;
        $total_cost = $base_cost + $shipment_cost;
        $remaining_balance = $total_cost - $amount_paid;
        
        // Check if container exists
        $check_sql = "SELECT id, status FROM containers WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $container_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Container not found']);
            return;
        }
        
        $current_container = $check_result->fetch_assoc();
        
        // Allow editing containers in any status
        // Removed the restriction to edit processed containers
        
        // Check if container number already exists for different container
        $check_sql = "SELECT id FROM containers WHERE container_number = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('si', $container_number, $container_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Container number already exists']);
            return;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
        // Update container
        $sql = "UPDATE containers SET container_number = ?, supplier_id = ?, total_weight_kg = ?, 
                price_per_kg = ?, total_cost = ?, amount_paid = ?, remaining_balance = ?, 
                shipment_cost = ?, profit_margin_percentage = ?,
                arrival_date = ?, status = ?, notes = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare container update query: ' . $conn->error);
        }
        $stmt->bind_param('sidddddddsssi', $container_number, $supplier_id, $total_weight_kg, 
                         $price_per_kg, $total_cost, $amount_paid, $remaining_balance, 
                         $shipment_cost, $profit_margin_percentage,
                         $arrival_date, $status, $notes, $container_id);
        
            if (!$stmt->execute()) {
                throw new Exception('Failed to update container');
            }
            
            // Update financial summary if the table exists
            $total_all_costs = $total_cost + $shipment_cost;
            $expected_revenue = $total_all_costs * (1 + $profit_margin_percentage / 100);
            
            // Check if financial summary table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'container_financial_summary'");
            if ($check_table && $check_table->num_rows > 0) {
                // Check if record exists, if not create it
                $check_record = $conn->prepare("SELECT id, actual_selling_total FROM container_financial_summary WHERE container_id = ?");
                $check_record->bind_param('i', $container_id);
                $check_record->execute();
                $existing_record = $check_record->get_result()->fetch_assoc();
                
                if ($existing_record) {
                    // Update existing record with manual actual profit
                    $financial_sql = "UPDATE container_financial_summary SET 
                                     base_cost = ?, shipment_cost = ?, total_all_costs = ?, 
                                     profit_margin_percentage = ?, expected_selling_total = ?,
                                     actual_profit = ?
                                     WHERE container_id = ?";
                    $financial_stmt = $conn->prepare($financial_sql);
                    if (!$financial_stmt) {
                        throw new Exception('Failed to prepare financial summary update query: ' . $conn->error);
                    }
                    $financial_stmt->bind_param('ddddddi', $total_cost, $shipment_cost, $total_all_costs, 
                                               $profit_margin_percentage, $expected_revenue, $actual_profit, $container_id);
                } else {
                    // Create new record with manual actual profit
                    $financial_sql = "INSERT INTO container_financial_summary 
                                     (container_id, base_cost, shipment_cost, total_all_costs, 
                                      profit_margin_percentage, expected_selling_total, actual_selling_total, actual_profit) 
                                     VALUES (?, ?, ?, ?, ?, ?, 0, ?)";
                    $financial_stmt = $conn->prepare($financial_sql);
                    if (!$financial_stmt) {
                        throw new Exception('Failed to prepare financial summary insert query: ' . $conn->error);
                    }
                    $financial_stmt->bind_param('idddddd', $container_id, $total_cost, $shipment_cost, 
                                               $total_all_costs, $profit_margin_percentage, $expected_revenue, $actual_profit);
                }
                
                if (!$financial_stmt->execute()) {
                    throw new Exception('Failed to update financial summary: ' . $financial_stmt->error);
                }
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Container updated successfully']);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Edit container error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating the container: ' . $e->getMessage()]);
    }
}

/**
 * Delete a container (simplified cleanup)
 */
function deleteContainer() {
    global $conn;
    
    try {
        $container_id = (int)$_POST['container_id'];
        
        if ($container_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid container ID']);
            return;
        }
        
        // Check if container has items or boxes
        $check_items_sql = "SELECT COUNT(*) as count FROM container_items WHERE container_id = ?";
        $check_items_stmt = $conn->prepare($check_items_sql);
        $check_items_stmt->bind_param('i', $container_id);
        $check_items_stmt->execute();
        $items_result = $check_items_stmt->get_result();
        $item_count = $items_result->fetch_assoc()['count'];
        
        $check_boxes_sql = "SELECT COUNT(*) as count FROM container_boxes WHERE container_id = ?";
        $check_boxes_stmt = $conn->prepare($check_boxes_sql);
        $check_boxes_stmt->bind_param('i', $container_id);
        $check_boxes_stmt->execute();
        $boxes_result = $check_boxes_stmt->get_result();
        $box_count = $boxes_result->fetch_assoc()['count'];
        
        if ($item_count > 0 || $box_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete container with items or boxes']);
            return;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete from container_financial_summary
            $financial_sql = "DELETE FROM container_financial_summary WHERE container_id = ?";
            $financial_stmt = $conn->prepare($financial_sql);
            $financial_stmt->bind_param('i', $container_id);
            $financial_stmt->execute();
        
        // Delete container
        $sql = "DELETE FROM containers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $container_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                    $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Container deleted successfully']);
            } else {
                    $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Container not found']);
            }
        } else {
                $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to delete container']);
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Delete container error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the container']);
    }
}

/**
 * Process container items (simplified for bulk mode)
 */
function processContainer() {
    global $conn;
    
    try {
        $container_id = (int)$_POST['container_id'];
        
        if ($container_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid container ID']);
            return;
        }
        
        // Get container info and verify it's not already processed
        $container_sql = "SELECT * FROM containers WHERE id = ? AND status != 'processed'";
        $container_stmt = $conn->prepare($container_sql);
        $container_stmt->bind_param('i', $container_id);
        $container_stmt->execute();
        $container_result = $container_stmt->get_result();
        
        if ($container_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Container not found or already processed']);
            return;
        }
        
        $container_info = $container_result->fetch_assoc();
        
        // Double-check: verify container is not already processed
        if ($container_info['status'] === 'processed') {
            echo json_encode(['success' => false, 'message' => 'Container is already processed']);
            return;
        }
        
        error_log("processContainer: Processing container ID: $container_id, current status: {$container_info['status']}");
        
        // Use the comprehensive processContainerItems function that handles both items and boxes
        $result = processContainerItems($container_id);
        
        if (!$result['success']) {
            echo json_encode($result);
            return;
        }
        
        // Update container status to processed
        $update_sql = "UPDATE containers SET status = 'processed', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('i', $container_id);
        $update_stmt->execute();
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("Process container error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while processing the container: ' . $e->getMessage()]);
    }
}

/**
 * Process a single container item based on its type
 */
function processContainerItem($item, $container_id) {
    global $conn;
    
    try {
        switch ($item['item_type']) {
            case 'existing_item':
                return processExistingContainerItem($item, $container_id);
                
            case 'new_item':
                // For new items, item_id might be 0 (placeholder)
                if ($item['item_id'] == 0) {
                    return processNewContainerItem($item, $container_id);
                } else {
                    // This is an existing item that was marked as 'new_item' type
                    return processExistingContainerItem($item, $container_id);
                }
                
            case 'box':
                // Boxes are now handled by the container_boxes table
                // This should not happen with the new structure
                throw new Exception('Box items should be processed through container_boxes table, not container_items');
                
            default:
                throw new Exception('Invalid item type: ' . $item['item_type']);
        }
    } catch (Exception $e) {
        error_log("Error processing container item {$item['id']}: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Process an existing item from container to inventory
 */
function processExistingContainerItem($item, $container_id) {
    global $conn;
    
    try {
        $item_id = $item['item_id'];
        $quantity = $item['quantity_in_container'];
        
        if (!$item_id || !$quantity) {
            throw new Exception('Missing required item data');
        }
        
        // Get pricing information from inventory_items table
        $item_info_sql = "SELECT base_price, selling_price FROM inventory_items WHERE id = ?";
        $item_info_stmt = $conn->prepare($item_info_sql);
        $item_info_stmt->bind_param('i', $item_id);
        $item_info_stmt->execute();
        $item_info_result = $item_info_stmt->get_result();
        
        if ($item_info_result->num_rows === 0) {
            throw new Exception('Inventory item not found');
        }
        
        $item_info = $item_info_result->fetch_assoc();
        $unit_cost = $item_info['base_price'];
        $selling_price = $item_info['selling_price'];
        
        // Simple: Update stock in store_inventory
        $warehouse_id = 1;
        
        // Log the current state
        error_log("processExistingContainerItem: Processing item_id: $item_id, quantity: $quantity, warehouse_id: $warehouse_id");
        
        // Get the existing barcode for this item
        $barcode_sql = "SELECT id FROM barcodes WHERE item_id = ? LIMIT 1";
        $barcode_stmt = $conn->prepare($barcode_sql);
        $barcode_stmt->bind_param('i', $item_id);
        $barcode_stmt->execute();
        $barcode_result = $barcode_stmt->get_result();
        
        if ($barcode_result->num_rows === 0) {
            throw new Exception('No barcode found for existing item');
        }
        
        $barcode_id = $barcode_result->fetch_assoc()['id'];
        error_log("processExistingContainerItem: Using existing barcode_id: $barcode_id");
        
        // Check if item already exists in store_inventory
        $check_sql = "SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('iii', $warehouse_id, $item_id, $barcode_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $current_stock = $check_result->fetch_assoc()['current_stock'];
            error_log("processExistingContainerItem: Item exists, current stock: $current_stock");
        } else {
            error_log("processExistingContainerItem: Item does not exist in store_inventory, will create new record");
        }
        
        $sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                current_stock = current_stock + VALUES(current_stock),
                selling_price = VALUES(selling_price),
                cost_price = VALUES(cost_price)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iiiidd', $warehouse_id, $item_id, $barcode_id, $quantity, $selling_price, $unit_cost);
        $stmt->execute();
        
        // Check the result
        if ($stmt->affected_rows > 0) {
            error_log("processExistingContainerItem: SQL executed successfully, affected rows: " . $stmt->affected_rows);
        } else {
            error_log("processExistingContainerItem: SQL executed but no rows affected");
        }
        
        // Verify the update
        $verify_sql = "SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ?";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param('iii', $warehouse_id, $item_id, $barcode_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $new_stock = $verify_result->fetch_assoc()['current_stock'];
        error_log("processExistingContainerItem: New stock after update: $new_stock");
        
        // Update the container_id for the existing item
        $update_container_sql = "UPDATE inventory_items SET container_id = ? WHERE id = ?";
        $update_container_stmt = $conn->prepare($update_container_sql);
        $update_container_stmt->bind_param('ii', $container_id, $item_id);
        $update_container_stmt->execute();
        error_log("processExistingContainerItem: Updated container_id to $container_id for item_id $item_id");
        
        return [
            'success' => true,
            'selling_value' => $selling_price * $quantity,
            'message' => 'Existing item stock updated'
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to process existing item: ' . $e->getMessage());
    }
}

/**
 * Process a new item from container to inventory
 */
function processNewContainerItem($item, $container_id) {
    global $conn;
    
    try {
        $quantity = $item['quantity_in_container'];
        
        if (!$quantity) {
            throw new Exception('Missing required item data');
        }
        
        // Get item details from container_item_details table
        $details_sql = "SELECT * FROM container_item_details WHERE container_item_id = ?";
        $details_stmt = $conn->prepare($details_sql);
        $details_stmt->bind_param('i', $item['id']);
        $details_stmt->execute();
        $details_result = $details_stmt->get_result();
        
        if ($details_result->num_rows === 0) {
            throw new Exception('Item details not found');
        }
        
        $item_details = $details_result->fetch_assoc();
        
        // Get pricing from container item details
        $unit_cost = $item_details['unit_cost'] ?? 0.00;
        $selling_price = $item_details['selling_price'] ?? 0.00;
        
        // Create new inventory item
        error_log("processNewContainerItem: Creating new item with data: " . json_encode($item_details));
        error_log("processNewContainerItem: Container ID: " . $container_id);
        
        try {
            $sql = "INSERT INTO inventory_items (name, item_code, description, category_id, brand, size, color, material, 
                    base_price, selling_price, container_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            error_log("processNewContainerItem: SQL: " . $sql);
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare inventory_items statement: ' . $conn->error);
            }
            
            error_log("processNewContainerItem: Binding parameters...");
            error_log("processNewContainerItem: container_id value: " . var_export($container_id, true));
            $bind_result = $stmt->bind_param('sssissssddi', 
                $item_details['name'],
                $item_details['code'],
                $item_details['description'],
                $item_details['category_id'],
                $item_details['brand'],
                $item_details['size'],
                $item_details['color'],
                $item_details['material'],
                $unit_cost,
                $selling_price,
                $container_id
            );
            
            if (!$bind_result) {
                throw new Exception('Failed to bind parameters: ' . $stmt->error);
            }
            
            error_log("processNewContainerItem: Executing inventory_items INSERT");
            $execute_result = $stmt->execute();
            
            if (!$execute_result) {
                throw new Exception('Failed to execute inventory_items INSERT: ' . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Failed to create inventory item - no rows affected');
            }
            
            $new_item_id = $conn->insert_id;
            error_log("processNewContainerItem: Created inventory item with ID: $new_item_id");
            
        } catch (Exception $e) {
            error_log("processNewContainerItem: Error creating inventory item: " . $e->getMessage());
            throw $e;
        }
        
        // Generate a barcode for the new item
        try {
            error_log("processNewContainerItem: Generating barcode for item_id: $new_item_id, price: $selling_price");
            $barcode = generate_barcode($new_item_id, $selling_price);
            error_log("processNewContainerItem: Generated barcode: $barcode");
            
            $barcode_sql = "INSERT INTO barcodes (barcode, item_id, price) VALUES (?, ?, ?)";
            $barcode_stmt = $conn->prepare($barcode_sql);
            if (!$barcode_stmt) {
                throw new Exception('Failed to prepare barcode statement: ' . $conn->error);
            }
            
            $barcode_stmt->bind_param('sid', $barcode, $new_item_id, $selling_price);
            $barcode_execute = $barcode_stmt->execute();
            
            if (!$barcode_execute) {
                throw new Exception('Failed to execute barcode INSERT: ' . $barcode_stmt->error);
            }
            
            $barcode_id = $conn->insert_id;
            error_log("processNewContainerItem: Created barcode with ID: $barcode_id");
            
        } catch (Exception $e) {
            error_log("processNewContainerItem: Error creating barcode: " . $e->getMessage());
            throw $e;
        }
        
        // Add to warehouse inventory
        try {
            error_log("processNewContainerItem: Adding to store_inventory...");
            $warehouse_id = 1;
            $inventory_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) 
                             VALUES (?, ?, ?, ?, ?, ?)";
            $inventory_stmt = $conn->prepare($inventory_sql);
            if (!$inventory_stmt) {
                throw new Exception('Failed to prepare store_inventory statement: ' . $conn->error);
            }
            
            $inventory_stmt->bind_param('iiiidd', $warehouse_id, $new_item_id, $barcode_id, $quantity, $selling_price, $unit_cost);
            $inventory_execute = $inventory_stmt->execute();
            
            if (!$inventory_execute) {
                throw new Exception('Failed to execute store_inventory INSERT: ' . $inventory_stmt->error);
            }
            
            error_log("processNewContainerItem: Successfully added to store_inventory");
            
        } catch (Exception $e) {
            error_log("processNewContainerItem: Error adding to store_inventory: " . $e->getMessage());
            throw $e;
        }
        
        return [
            'success' => true,
            'selling_value' => $selling_price * $quantity,
            'message' => 'New item created and added to inventory'
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to process new item: ' . $e->getMessage());
    }
}

/**
 * Process a box from container to inventory
 */
function processContainerBox($box, $container_id) {
    global $conn;
    
    try {
        $quantity = $box['quantity'];
        $box_type = $box['box_type'];
        
        error_log("processContainerBox: Processing box ID {$box['id']}, type: $box_type, quantity: $quantity");
        error_log("processContainerBox: Full box data: " . json_encode($box));
        
        if ($quantity === null || $quantity === '' || $quantity <= 0) {
            error_log("processContainerBox: Invalid quantity: $quantity. Box data: " . json_encode($box));
            throw new Exception("Box '{$box['warehouse_box_name']}' has invalid quantity ($quantity). Please edit the box quantity to be greater than 0 or remove the box from the container.");
        }
        
        if ($box_type === 'existing') {
            // Existing box - update quantity in warehouse_boxes
            $warehouse_box_id = $box['warehouse_box_id'];
            if (!$warehouse_box_id) {
                throw new Exception('Missing warehouse box ID for existing box');
            }
            
            // Update quantity for existing box
            error_log("processContainerBox: Updating existing box $warehouse_box_id quantity by +$quantity");
            $sql = "UPDATE warehouse_boxes SET quantity = quantity + ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ii', $quantity, $warehouse_box_id);
            $stmt->execute();
            
            // Verify the update
            $check_sql = "SELECT quantity FROM warehouse_boxes WHERE id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param('i', $warehouse_box_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $new_quantity = $check_result->fetch_assoc()['quantity'];
            error_log("processContainerBox: Box $warehouse_box_id quantity after update: $new_quantity");
            
            // Update the container_id for the existing box
            $update_container_sql = "UPDATE warehouse_boxes SET container_id = ? WHERE id = ?";
            $update_container_stmt = $conn->prepare($update_container_sql);
            $update_container_stmt->bind_param('ii', $container_id, $warehouse_box_id);
            $update_container_stmt->execute();
            error_log("processContainerBox: Updated container_id to $container_id for box_id $warehouse_box_id");
            
        } else {
            // New box - create it first, then update quantity
            $new_box_number = $box['new_box_number'];
            $new_box_name = $box['new_box_name'];
            $new_box_type = $box['new_box_type'] ?? '';
            $new_box_notes = $box['new_box_notes'] ?? '';
            
            if (empty($new_box_number) || empty($new_box_name)) {
                throw new Exception('Missing new box data');
            }
            
            // Create the new box with the specified quantity and unit cost
            $unit_cost = $box['unit_cost'] ?? 0.00;
            error_log("processContainerBox: Creating new box with quantity: $quantity, unit_cost: $unit_cost");
            error_log("processContainerBox: Container ID: " . $container_id);
            $sql = "INSERT INTO warehouse_boxes (box_number, box_name, box_type, quantity, unit_cost, notes, container_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = $conn->prepare($sql);
            error_log("processContainerBox: container_id value: " . var_export($container_id, true));
            $stmt->bind_param('sssidsi', 
                $new_box_number,
                $new_box_name,
                $new_box_type,
                $quantity, // Set the quantity directly instead of starting with 0
                $unit_cost,
                $new_box_notes,
                $container_id
            );
            $stmt->execute();
            
            $new_box_id = $conn->insert_id;
            error_log("processContainerBox: New box created with ID: $new_box_id, quantity: $quantity");
            
            // Update the container_box with the new warehouse_box_id
            $update_sql = "UPDATE container_boxes SET warehouse_box_id = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('ii', $new_box_id, $box['id']);
            $update_stmt->execute();
            
            // No need to update quantity again - it was set during creation
            $warehouse_box_id = $new_box_id;
        }
        
        // Remove the duplicate quantity update - quantity is already handled above
        
        // Mark box as processed
        $update_sql = "UPDATE container_boxes SET is_processed = 1, processed_at = CURRENT_TIMESTAMP WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('i', $box['id']);
        $update_stmt->execute();
        
        return [
            'success' => true,
            'selling_value' => 0, // Boxes don't have selling value
            'message' => 'Box processed successfully'
        ];
        
    } catch (Exception $e) {
        throw new Exception('Failed to process box: ' . $e->getMessage());
    }
}

// Legacy processBoxContainerItem function removed - boxes are now handled by container_boxes table

/**
 * Get financial summary for a container
 */
function getFinancialSummary() {
    global $conn;
    
    try {
        $container_id = (int)$_POST['container_id'];
        
        if ($container_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid container ID']);
            return;
        }
        
        $sql = "SELECT 
                    c.container_number,
                    c.total_weight_kg,
                    c.price_per_kg,
                    c.total_price,
                    cfs.base_cost,
                    cfs.shipment_cost,
                    cfs.total_all_costs,
                    cfs.profit_margin_percentage,
                    cfs.expected_selling_total,
                    cfs.actual_selling_total,
                    cfs.actual_profit,
                    s.name as supplier_name
                FROM containers c
                JOIN container_financial_summary cfs ON c.id = cfs.container_id
                LEFT JOIN suppliers s ON c.supplier_id = s.id
                WHERE c.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $container_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $summary = $result->fetch_assoc();
            
            // Calculate additional metrics
            $summary['roi_percentage'] = $summary['total_all_costs'] > 0 ? 
                ($summary['actual_profit'] / $summary['total_all_costs'] * 100) : 0;
            $summary['cost_per_kg'] = $summary['total_weight_kg'] > 0 ? 
                ($summary['total_all_costs'] / $summary['total_weight_kg']) : 0;
            
            echo json_encode(['success' => true, 'data' => $summary]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Financial summary not found']);
        }
        
    } catch (Exception $e) {
        error_log("Get financial summary error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while retrieving financial summary']);
    }
}

/**
 * Calculate real-time costs based on input values (simplified)
 */
function calculateCosts() {
    try {
        $total_weight_kg = (float)$_POST['total_weight_kg'];
        $total_price = (float)$_POST['total_price'];
        $shipment_cost = isset($_POST['shipment_cost']) ? (float)$_POST['shipment_cost'] : 0;
        $profit_margin_percentage = isset($_POST['profit_margin_percentage']) ? (float)$_POST['profit_margin_percentage'] : 0;
        
        $base_cost = $total_price;
        $price_per_kg = $total_weight_kg > 0 ? ($total_price / $total_weight_kg) : 0;
        $total_all_costs = $base_cost + $shipment_cost;
        $expected_revenue = $total_all_costs * (1 + $profit_margin_percentage / 100);
        $expected_profit = $expected_revenue - $total_all_costs;
        $roi_percentage = $total_all_costs > 0 ? ($expected_profit / $total_all_costs * 100) : 0;
        $cost_per_kg = $total_weight_kg > 0 ? ($total_all_costs / $total_weight_kg) : 0;
        
        echo json_encode([
            'success' => true,
            'calculations' => [
                'base_cost' => $base_cost,
                'price_per_kg' => $price_per_kg,
                'shipment_cost' => $shipment_cost,
                'total_all_costs' => $total_all_costs,
                'expected_revenue' => $expected_revenue,
                'expected_profit' => $expected_profit,
                'roi_percentage' => $roi_percentage,
                'cost_per_kg' => $cost_per_kg
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Calculate costs error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while calculating costs']);
    }
}

/**
 * Update container status (pending→received→processed)
 */
function updateContainerStatus() {
    global $conn;
    $container_id = isset($_POST['container_id']) ? (int)$_POST['container_id'] : 0;
    $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';
    if ($container_id <= 0 || !in_array($new_status, ['pending', 'received', 'processed'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid container or status']);
        return;
    }
    // Only allow valid transitions
    $check_sql = "SELECT status FROM containers WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $container_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Container not found']);
        return;
    }
    $current_status = $result->fetch_assoc()['status'];
    $valid = false;
    if ($current_status === 'pending' && $new_status === 'received') $valid = true;
    if ($current_status === 'received' && $new_status === 'processed') $valid = true;
    if (!$valid) {
        echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
        return;
    }
    // If status is being set to 'processed', we need to process the container items first
    if ($new_status === 'processed') {
        error_log("updateContainerStatus: Processing container ID: $container_id");
        
        // Check if container has items or boxes to process
        $items_check = "SELECT COUNT(*) as item_count FROM container_items WHERE container_id = ? AND is_processed = 0";
        $items_stmt = $conn->prepare($items_check);
        $items_stmt->bind_param('i', $container_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        $item_count = $items_result->fetch_assoc()['item_count'];
        
        $boxes_check = "SELECT COUNT(*) as box_count FROM container_boxes WHERE container_id = ? AND is_processed = 0";
        $boxes_stmt = $conn->prepare($boxes_check);
        $boxes_stmt->bind_param('i', $container_id);
        $boxes_stmt->execute();
        $boxes_result = $boxes_stmt->get_result();
        $box_count = $boxes_result->fetch_assoc()['box_count'];
        
        $total_count = $item_count + $box_count;
        error_log("updateContainerStatus: Found $item_count items and $box_count boxes to process (total: $total_count)");
        
        if ($total_count == 0) {
            echo json_encode(['success' => false, 'message' => 'Container has no items or boxes to process']);
            return;
        }
        
        // Process the container items directly here
        try {
            error_log("updateContainerStatus: Calling processContainerItems");
            $result = processContainerItems($container_id);
            error_log("updateContainerStatus: processContainerItems result: " . json_encode($result));
            
            if ($result['success']) {
                // Update container status to processed
                $update_sql = "UPDATE containers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param('si', $new_status, $container_id);
                $update_stmt->execute();
                
                echo json_encode($result);
            } else {
                echo json_encode($result);
            }
            return;
        } catch (Exception $e) {
            error_log("updateContainerStatus: Exception: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to process container: ' . $e->getMessage()]);
            return;
        }
    }
    
    // For other status updates, proceed normally
    $update_sql = "UPDATE containers SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('si', $new_status, $container_id);
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Container status updated to ' . $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

/**
 * Process container items and add them to inventory
 */
function processContainerItems($container_id) {
    global $conn;
    
    try {
        error_log("processContainerItems: Starting to process container ID: $container_id");
        
        // Get all unprocessed container items
        $items_sql = "SELECT * FROM container_items WHERE container_id = ? AND is_processed = 0 ORDER BY id ASC";
        $items_stmt = $conn->prepare($items_sql);
        $items_stmt->bind_param('i', $container_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Get all unprocessed container boxes
        $boxes_sql = "SELECT * FROM container_boxes WHERE container_id = ? AND is_processed = 0 ORDER BY id ASC";
        $boxes_stmt = $conn->prepare($boxes_sql);
        $boxes_stmt->bind_param('i', $container_id);
        $boxes_stmt->execute();
        $boxes_result = $boxes_stmt->get_result();
        
        $total_items = $items_result->num_rows + $boxes_result->num_rows;
        error_log("processContainerItems: Found " . $total_items . " items/boxes to process");
        
        if ($total_items === 0) {
            return ['success' => false, 'message' => 'No items or boxes found in container to process'];
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $total_selling_value = 0;
            $processed_items = 0;
            $errors = [];
            
            while ($item = $items_result->fetch_assoc()) {
                try {
                    error_log("processContainerItems: Processing item ID: {$item['id']}, type: {$item['item_type']}");
                    $result = processContainerItem($item, $container_id);
                    if ($result['success']) {
                        $total_selling_value += $result['selling_value'];
                        $processed_items++;
                        error_log("processContainerItems: Item {$item['id']} processed successfully");
                    } else {
                        $errors[] = "Item ID {$item['id']}: " . $result['message'];
                        error_log("processContainerItems: Item {$item['id']} failed: " . $result['message']);
                    }
                } catch (Exception $e) {
                    $errors[] = "Item ID {$item['id']}: " . $e->getMessage();
                    error_log("processContainerItems: Item {$item['id']} exception: " . $e->getMessage());
                }
            }
            
            // Process container boxes
            while ($box = $boxes_result->fetch_assoc()) {
                try {
                    error_log("processContainerItems: Processing box ID: {$box['id']}, type: {$box['box_type']}");
                    $result = processContainerBox($box, $container_id);
                    if ($result['success']) {
                        $total_selling_value += $result['selling_value'];
                        $processed_items++;
                        error_log("processContainerItems: Box {$box['id']} processed successfully");
                    } else {
                        $errors[] = "Box ID {$box['id']}: " . $result['message'];
                        error_log("processContainerItems: Box {$box['id']} failed: " . $result['message']);
                    }
                } catch (Exception $e) {
                    $errors[] = "Box ID {$box['id']}: " . $e->getMessage();
                    error_log("processContainerItems: Box {$box['id']} exception: " . $e->getMessage());
                }
            }
            
            // If there were errors, rollback and return them
            if (!empty($errors)) {
                $conn->rollback();
                return [
                    'success' => false, 
                    'message' => 'Errors occurred while processing items and boxes: ' . implode('; ', $errors)
                ];
            }
            
            $conn->commit();
            return [
                'success' => true, 
                'message' => "Container processed successfully. $processed_items items added to inventory.",
                'total_selling_value' => $total_selling_value,
                'processed_items' => $processed_items
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception('Failed to process container items: ' . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Process container items error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while processing container items: ' . $e->getMessage()];
    }
}

/**
 * Add item to container (box, existing item, or new item)
 */
function addItemToContainer() {
    global $conn;
    
    try {
        // Validate required fields
        $required_fields = ['container_id', 'item_type'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => "Field $field is required"]);
                return;
            }
        }
        
        $container_id = (int)$_POST['container_id'];
        $item_type = sanitize_input($_POST['item_type']);
        
        // Validate container exists
        $container_check = $conn->prepare("SELECT id, status FROM containers WHERE id = ?");
        $container_check->bind_param('i', $container_id);
        $container_check->execute();
        $container_result = $container_check->get_result();
        
        if ($container_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Container not found']);
            return;
        }
        
        $container = $container_result->fetch_assoc();
        if ($container['status'] === 'processed') {
            echo json_encode(['success' => false, 'message' => 'Cannot add items to processed containers']);
            return;
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $item_data = [];
            
                    switch ($item_type) {
            case 'box':
                // Handle boxes separately - they go to container_boxes table
                if (isset($_POST['new_box_number']) && !empty($_POST['new_box_number'])) {
                    // New box creation
                    $box_data = validateAndPrepareNewBox();
                    $box_id = addBoxToContainer($container_id, $box_data);
                    
                    // Commit the transaction for boxes
                    $conn->commit();
                    error_log("addItemToContainer: Transaction committed for new box, box_id: " . $box_id);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Box added to container successfully'
                    ]);
                    return;
                } else {
                    // Existing box selection
                    $box_data = validateAndPrepareBoxItem();
                    $box_id = addBoxToContainer($container_id, $box_data);
                    
                    // Commit the transaction for boxes
                    $conn->commit();
                    error_log("addItemToContainer: Transaction committed for existing box, box_id: " . $box_id);
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Box added to container successfully'
                    ]);
                    return;
                }
                break;
            case 'existing_item':
                $item_data = validateAndPrepareExistingItem();
                break;
            case 'new_item':
                $item_data = validateAndPrepareNewItem();
                break;
            default:
                throw new Exception('Invalid item type');
        }
            
            if (empty($item_data)) {
                throw new Exception('Failed to prepare item data');
            }
            
            error_log("addItemToContainer: Item data prepared: " . json_encode($item_data));
            
            // Debug: Check required fields based on item type
            $required_fields = ['quantity_in_container', 'quantity'];
            
            // Add type-specific required fields
            switch ($item_type) {
                case 'existing_item':
                    $required_fields[] = 'item_id';
                    break;
                case 'new_item':
                    $required_fields[] = 'name';
                    $required_fields[] = 'code';
                    
                    $required_fields[] = 'selling_price';
                    break;
                case 'box':
                    // Boxes don't need additional validation here
                    break;
            }
            
            foreach ($required_fields as $field) {
                if (!array_key_exists($field, $item_data)) {
                    error_log("addItemToContainer: Missing required field: $field for item type: $item_type");
                    throw new Exception("Missing required field: $field");
                }
            }
            error_log("addItemToContainer: All required fields present for item type: $item_type");
            
            // Insert container item into the new clean structure
            // Only insert the required fields, let the database handle defaults for processed_at and processed_by
            $insert_sql = "INSERT INTO container_items (
                container_id, item_type, item_id, quantity_in_container, 
                is_processed
            ) VALUES (?, ?, ?, ?, ?)";
            
            error_log("addItemToContainer: SQL prepared: " . $insert_sql);
            
            $insert_stmt = $conn->prepare($insert_sql);
            if (!$insert_stmt) {
                error_log("addItemToContainer: Failed to prepare statement: " . $conn->error);
                throw new Exception('Failed to prepare INSERT statement: ' . $conn->error);
            }
            error_log("addItemToContainer: Statement prepared successfully");
            
            // Validate the prepared statement
            if (!is_object($insert_stmt) || !method_exists($insert_stmt, 'bind_param')) {
                error_log("addItemToContainer: ERROR - Prepared statement is invalid or corrupted");
                throw new Exception('Prepared statement is invalid or corrupted');
            }
            
            // Build the type string for 5 parameters dynamically
            // i=container_id, s=item_type, [i/s]=item_id (i if integer, s if NULL), i=quantity_in_container, i=is_processed
            $item_id_type = ($item_data['item_id'] !== null) ? 'i' : 's';
            $types = 'is' . $item_id_type . 'is';
            error_log("addItemToContainer: Dynamic type string: " . $types . " (item_id type: " . $item_id_type . ")");
            error_log("addItemToContainer: About to bind parameters...");
            error_log("addItemToContainer: Parameters to bind: container_id=" . $container_id . ", item_type=" . $item_type . ", item_id=" . $item_data['item_id'] . ", quantity=" . $item_data['quantity_in_container']);
            
            // Debug: Log the exact values being bound
            error_log("addItemToContainer: Binding values: container_id=" . var_export($container_id, true) . 
                     ", item_type=" . var_export($item_type, true) . 
                     ", item_id=" . var_export($item_data['item_id'], true) . 
                     ", quantity=" . var_export($item_data['quantity_in_container'], true) . 
                     ", is_processed=0");
            
            // Validate that item_id is not NULL for existing items
            if ($item_type === 'existing_item' && (empty($item_data['item_id']) || $item_data['item_id'] === null)) {
                error_log("addItemToContainer: ERROR - item_id is NULL or empty for existing_item type");
                throw new Exception('Item ID is required for existing items');
            }
            
            // Additional validation - check if all variables are properly defined
            error_log("addItemToContainer: Variable validation - container_id type: " . gettype($container_id) . 
                     ", item_type type: " . gettype($item_type) . 
                     ", item_id type: " . gettype($item_data['item_id']) . 
                     ", quantity type: " . gettype($item_data['quantity_in_container']));
            
            // Check if any variables are undefined (key doesn't exist in array)
            // Note: NULL values are valid for item_id (new items) and other optional fields
            if (!isset($container_id) || !isset($item_type) || !array_key_exists('item_id', $item_data) || !array_key_exists('quantity_in_container', $item_data)) {
                error_log("addItemToContainer: ERROR - One or more variables are undefined");
                throw new Exception('One or more required variables are undefined');
            }
            
            error_log("addItemToContainer: About to call bind_param...");
            
            try {
                // Create variables for all parameters so they can be passed by reference
                $is_processed = 0;
                
                error_log("addItemToContainer: About to call bind_param with types: $types");
                error_log("addItemToContainer: Parameter values: " . json_encode([
                    'container_id' => $container_id,
                    'item_type' => $item_type,
                    'item_id' => $item_data['item_id'],
                    'quantity' => $item_data['quantity_in_container'],
                    'is_processed' => $is_processed
                ]));
                
                $bind_result = $insert_stmt->bind_param(
                    $types,
                    $container_id,
                    $item_type,
                    $item_data['item_id'],
                    $item_data['quantity_in_container'],
                    $is_processed // is_processed (now a variable that can be passed by reference)
                );
                
                error_log("addItemToContainer: bind_param call completed");
                
                error_log("addItemToContainer: Bind result: " . ($bind_result ? 'true' : 'false'));
                if (!$bind_result) {
                    error_log("addItemToContainer: Bind failed with error: " . $insert_stmt->error);
                    throw new Exception('Failed to bind parameters: ' . $insert_stmt->error);
                }
            } catch (Exception $e) {
                error_log("addItemToContainer: Exception during bind_param: " . $e->getMessage());
                throw $e;
            } catch (Error $e) {
                error_log("addItemToContainer: Fatal error during bind_param: " . $e->getMessage());
                throw new Exception('Fatal error during parameter binding: ' . $e->getMessage());
            }
            error_log("addItemToContainer: Parameters bound successfully");
            
            // Additional debug logging
            error_log("addItemToContainer: All parameters bound, checking for any binding errors...");
            if ($insert_stmt->error) {
                error_log("addItemToContainer: Statement error after binding: " . $insert_stmt->error);
            }
            
            error_log("addItemToContainer: About to execute INSERT statement");
            error_log("addItemToContainer: SQL: " . $insert_sql);
            error_log("addItemToContainer: Types: " . $types);
            
            // Check database connection status
            if ($conn->connect_error) {
                error_log("addItemToContainer: Database connection error: " . $conn->connect_error);
                throw new Exception('Database connection error: ' . $conn->connect_error);
            }
            
            if (!$insert_stmt->execute()) {
                error_log("addItemToContainer: INSERT failed with error: " . $insert_stmt->error);
                error_log("addItemToContainer: MySQL errno: " . $conn->errno);
                throw new Exception('Failed to insert container item: ' . $insert_stmt->error);
            }
            $container_item_id = $insert_stmt->insert_id;
            error_log("addItemToContainer: INSERT successful, new container item ID: " . $container_item_id);
            
            // If this is a new item, also insert into container_item_details
            if ($item_type === 'new_item' && !empty($item_data['name'])) {
                $details_sql = "INSERT INTO container_item_details (
                    container_item_id, name, code, description, category_id, 
                    brand, size, color, material, unit_cost, selling_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $details_stmt = $conn->prepare($details_sql);
                if (!$details_stmt) {
                    throw new Exception('Failed to prepare details statement: ' . $conn->error);
                }
                
                // Create variables for all parameters so they can be passed by reference
                $details_container_item_id = $container_item_id;
                $details_name = $item_data['name'];
                $details_code = $item_data['code'] ?? '';
                $details_description = $item_data['description'] ?? '';
                $details_category_id = $item_data['category_id'];
                $details_brand = $item_data['brand'] ?? '';
                $details_size = $item_data['size'] ?? '';
                $details_color = $item_data['color'] ?? '';
                $details_material = $item_data['material'] ?? '';
                $details_unit_cost = $item_data['unit_cost'] ?? 0.00;
                $details_selling_price = $item_data['selling_price'] ?? 0.00;
                
                $details_stmt->bind_param('isssissssdd',
                    $details_container_item_id,
                    $details_name,
                    $details_code,
                    $details_description,
                    $details_category_id,
                    $details_brand,
                    $details_size,
                    $details_color,
                    $details_material,
                    $details_unit_cost,
                    $details_selling_price
                );
                
                if (!$details_stmt->execute()) {
                    throw new Exception('Failed to insert item details: ' . $details_stmt->error);
                }
                
                error_log("addItemToContainer: Item details inserted successfully");
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Item added to container successfully',
                'item_id' => $insert_stmt->insert_id
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Add item to container error: " . $e->getMessage());
        error_log("Add item to container error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    } catch (Error $e) {
        error_log("Add item to container fatal error: " . $e->getMessage());
        error_log("Add item to container fatal error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'A fatal error occurred: ' . $e->getMessage()]);
    }
}

/**
 * Validate and prepare new box creation data
 */
function validateAndPrepareNewBox() {
    $required_fields = ['new_box_number', 'new_box_name', 'new_box_quantity'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field $field is required for new box creation");
        }
    }
    
    $box_number = $_POST['new_box_number'];
    $box_name = sanitize_input($_POST['new_box_name']);
    $box_type = sanitize_input($_POST['new_box_type'] ?? '');
    $quantity = (int)$_POST['new_box_quantity'];
    $unit_cost = isset($_POST['new_box_unit_cost']) ? (float)$_POST['new_box_unit_cost'] : 0.00;
    $notes = sanitize_input($_POST['new_box_notes'] ?? '');
    
    if (empty($box_number) || $quantity <= 0) {
        throw new Exception('Invalid box number or quantity');
    }
    
    if ($unit_cost < 0) {
        throw new Exception('Unit cost cannot be negative');
    }
    
    // Return data for addBoxToContainer function
    return [
        'warehouse_box_id' => null, // Will be created during processing
        'quantity' => $quantity,
        'new_box_number' => $box_number,
        'new_box_name' => $box_name,
        'new_box_type' => $box_type,
        'new_box_unit_cost' => $unit_cost,
        'new_box_notes' => $notes
    ];
}

/**
 * Validate and prepare box item data
 */
function validateAndPrepareBoxItem() {
    global $conn;
    
    error_log("validateAndPrepareBoxItem: Starting validation");
    error_log("validateAndPrepareBoxItem: warehouse_box_id: " . ($_POST['warehouse_box_id'] ?? 'NOT_SET'));
    error_log("validateAndPrepareBoxItem: quantity: " . ($_POST['quantity'] ?? 'NOT_SET'));
    
    $warehouse_box_id = (int)$_POST['warehouse_box_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($warehouse_box_id <= 0 || $quantity <= 0) {
        error_log("validateAndPrepareBoxItem: Validation failed - warehouse_box_id: $warehouse_box_id, quantity: $quantity");
        throw new Exception('Invalid warehouse box or quantity');
    }
    
    // Check if box exists
    $box_check = $conn->prepare("SELECT id FROM warehouse_boxes WHERE id = ?");
    $box_check->bind_param('i', $warehouse_box_id);
    $box_check->execute();
    $box_result = $box_check->get_result();
    
    if ($box_result->num_rows === 0) {
        throw new Exception('Warehouse box not found');
    }
    
    // Check if box is already assigned to this container
    $existing_check = $conn->prepare("SELECT id FROM container_boxes WHERE container_id = ? AND warehouse_box_id = ?");
    $existing_check->bind_param('ii', $_POST['container_id'], $warehouse_box_id);
    $existing_check->execute();
    
    if ($existing_check->get_result()->num_rows > 0) {
        throw new Exception('Box is already assigned to this container');
    }
    
    return [
        'warehouse_box_id' => $warehouse_box_id,
        'quantity' => $quantity
    ];
}

/**
 * Validate and prepare existing item data
 */
function validateAndPrepareExistingItem() {
    global $conn;
    
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($item_id <= 0 || $quantity <= 0) {
        throw new Exception('Invalid item or quantity');
    }
    
    // Check if item exists
    $item_check = $conn->prepare("SELECT id, base_price, selling_price FROM inventory_items WHERE id = ?");
    $item_check->bind_param('i', $item_id);
    $item_check->execute();
    $item_result = $item_check->get_result();
    
    if ($item_result->num_rows === 0) {
        throw new Exception('Inventory item not found');
    }
    
    $item = $item_result->fetch_assoc();
    
    return [
        'warehouse_box_id' => null,
        'item_id' => $item_id,
        'name' => null,
        'code' => null,
        'description' => null,
        'category_id' => null,
        'brand' => null,
        'size' => null,
        'color' => null,
        'material' => null,
        'quantity' => $quantity,
        'quantity_in_container' => $quantity
    ];
}

/**
 * Validate and prepare new item data
 */
function validateAndPrepareNewItem() {
    $required_fields = ['name', 'quantity', 'selling_price'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field $field is required for new items");
        }
    }
    
    $quantity = (int)$_POST['quantity'];
    $unit_cost = (float)$_POST['unit_cost'];
    $selling_price = (float)$_POST['selling_price'];
    
    if ($quantity <= 0 || $selling_price < 0) {
        throw new Exception('Invalid quantity or pricing values');
    }
    
    return [
        'warehouse_box_id' => null,
        'item_id' => null, // Use NULL for new items since they don't have an existing inventory item ID
        'name' => sanitize_input($_POST['name']),
        'code' => sanitize_input($_POST['code'] ?? ''),
        'description' => sanitize_input($_POST['description'] ?? ''),
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'brand' => sanitize_input($_POST['brand'] ?? ''),
        'size' => sanitize_input($_POST['size'] ?? ''),
        'color' => sanitize_input($_POST['color'] ?? ''),
        'material' => sanitize_input($_POST['material'] ?? ''),
        'quantity' => $quantity,
        'quantity_in_container' => $quantity,
        'unit_cost' => !empty($_POST['unit_cost']) ? (float)$_POST['unit_cost'] : 0.00,
        'selling_price' => $selling_price
    ];
}

/**
 * Add box to container (separate from items)
 */
function addBoxToContainer($container_id, $box_data) {
    global $conn;
    
    try {
        // Insert into container_boxes table
        $insert_sql = "INSERT INTO container_boxes (
            container_id, box_type, warehouse_box_id, new_box_number, new_box_name, 
            new_box_type, new_box_notes, quantity, unit_cost, is_processed, processed_at, processed_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // Debug: Count the placeholders in the SQL
        $placeholder_count = substr_count($insert_sql, '?');
        error_log("addBoxToContainer: SQL statement: " . $insert_sql);
        error_log("addBoxToContainer: Placeholder count in SQL: " . $placeholder_count);
        
        $insert_stmt = $conn->prepare($insert_sql);
        if (!$insert_stmt) {
            error_log("addBoxToContainer: Failed to prepare statement: " . $conn->error);
            throw new Exception('Failed to prepare INSERT statement: ' . $conn->error);
        }
        
        // Determine box type: 'existing' if warehouse_box_id is provided, 'new' if not
        $box_type = (!empty($box_data['warehouse_box_id']) && $box_data['warehouse_box_id'] > 0) ? 'existing' : 'new';
        $warehouse_box_id = $box_data['warehouse_box_id'] ?? null;
        $quantity = $box_data['quantity'];
        $processed_at = null;
        $processed_by = null;
        
        // Create variables for all parameters so they can be passed by reference
        $new_box_number = $box_data['new_box_number'] ?? null;
        $new_box_name = $box_data['new_box_name'] ?? null;
        $new_box_type = $box_data['new_box_type'] ?? null;
        $new_box_notes = $box_data['new_box_notes'] ?? null;
        $unit_cost = $box_data['new_box_unit_cost'] ?? 0.00;
        $is_processed = 0;
        
        // Debug: Log the exact parameters being bound
        error_log("addBoxToContainer: Binding parameters:");
        error_log("- container_id: $container_id (type: " . gettype($container_id) . ")");
        error_log("- box_type: $box_type (type: " . gettype($box_type) . ")");
        error_log("- warehouse_box_id: " . var_export($warehouse_box_id, true) . " (type: " . gettype($warehouse_box_id) . ")");
        error_log("- new_box_number: " . var_export($new_box_number, true) . " (type: " . gettype($new_box_number) . ")");
        error_log("- new_box_name: " . var_export($new_box_name, true) . " (type: " . gettype($new_box_name) . ")");
        error_log("- new_box_type: " . var_export($new_box_type, true) . " (type: " . gettype($new_box_type) . ")");
        error_log("- new_box_notes: " . var_export($new_box_notes, true) . " (type: " . gettype($new_box_notes) . ")");
        error_log("- quantity: $quantity (type: " . gettype($quantity) . ")");
        error_log("- unit_cost: $unit_cost (type: " . gettype($unit_cost) . ")");
        error_log("- is_processed: $is_processed (type: " . gettype($is_processed) . ")");
        error_log("- processed_at: " . var_export($processed_at, true) . " (type: " . gettype($processed_at) . ")");
        error_log("- processed_by: " . var_export($processed_by, true) . " (type: " . gettype($processed_by) . ")");
        
        // Verify parameter count
        $param_count = 12; // SQL has 12 placeholders
        $actual_count = 12; // We're binding 12 parameters
        error_log("addBoxToContainer: Parameter count verification - Expected: $param_count, Actual: $actual_count");
        
        if ($param_count !== $actual_count) {
            throw new Exception("Parameter count mismatch: Expected $param_count, got $actual_count");
        }
        
        // Define type string - corrected types (12 characters)
        $type_string = 'isissssidiis';
        
        // Debug: Count everything manually
        $sql_placeholders = substr_count($insert_sql, '?');
        $type_string_length = strlen($type_string);
        error_log("addBoxToContainer: Manual count - SQL placeholders: $sql_placeholders, Type string length: $type_string_length");
        
        // Debug: Show each character of the type string
        for ($i = 0; $i < strlen($type_string); $i++) {
            error_log("addBoxToContainer: Type string char " . ($i + 1) . ": '" . $type_string[$i] . "'");
        }
        
        // Debug: Log the exact bind_param call
        error_log("addBoxToContainer: About to call bind_param with type string: '$type_string'");
        error_log("addBoxToContainer: Parameters array: " . json_encode([
            'container_id' => $container_id,
            'box_type' => $box_type,
            'warehouse_box_id' => $warehouse_box_id,
            'new_box_number' => $new_box_number,
            'new_box_name' => $new_box_name,
            'new_box_type' => $new_box_type,
            'new_box_notes' => $new_box_notes,
            'quantity' => $quantity,
            'unit_cost' => $unit_cost,
            'is_processed' => $is_processed,
            'processed_at' => $processed_at,
            'processed_by' => $processed_by
        ]));
        
        // Count the actual parameters being passed
        $bind_params = [
            $container_id,
            $box_type,
            $warehouse_box_id,
            $new_box_number,
            $new_box_name,
            $new_box_type,
            $new_box_notes,
            $quantity,
            $unit_cost,
            $is_processed,
            $processed_at,
            $processed_by
        ];
        
        error_log("addBoxToContainer: Actual parameter count: " . count($bind_params));
        error_log("addBoxToContainer: Type string: '$type_string'");
        error_log("addBoxToContainer: Type string length: " . strlen($type_string));
        error_log("addBoxToContainer: Parameter count: " . count($bind_params));
        error_log("addBoxToContainer: Type string breakdown: i-s-i-s-s-s-s-d-i-i-s-s = 12 characters");
        
        // Validate parameter count before bind_param
        $param_count_in_call = 12; // Count manually: container_id, box_type, warehouse_box_id, new_box_number, new_box_name, new_box_type, new_box_notes, quantity, unit_cost, is_processed, processed_at, processed_by
        $type_string_count = strlen($type_string);
        
        if ($param_count_in_call !== $type_string_count) {
            error_log("addBoxToContainer: CRITICAL ERROR - Parameter count mismatch!");
            error_log("addBoxToContainer: Parameters in call: $param_count_in_call");
            error_log("addBoxToContainer: Type string length: $type_string_count");
            error_log("addBoxToContainer: Type string: '$type_string'");
            throw new Exception("Parameter count mismatch: $param_count_in_call parameters but type string has $type_string_count characters");
        }
        
        $insert_stmt->bind_param($type_string, 
            $container_id,
            $box_type,
            $warehouse_box_id,
            $new_box_number,
            $new_box_name,
            $new_box_type,
            $new_box_notes,
            $quantity,
            $unit_cost,
            $is_processed,
            $processed_at,
            $processed_by
        );
        
        // Execute the statement
        error_log("addBoxToContainer: About to execute INSERT statement");
        $execute_result = $insert_stmt->execute();
        error_log("addBoxToContainer: Execute result: " . ($execute_result ? 'true' : 'false'));
        
        if (!$execute_result) {
            error_log("addBoxToContainer: Execute failed with error: " . $insert_stmt->error);
            error_log("addBoxToContainer: MySQL errno: " . $conn->errno);
            throw new Exception('Failed to insert container box: ' . $insert_stmt->error);
        }
        
        // Check if any rows were affected
        $affected_rows = $insert_stmt->affected_rows;
        error_log("addBoxToContainer: Affected rows: " . $affected_rows);
        
        if ($affected_rows === 0) {
            error_log("addBoxToContainer: WARNING - No rows were affected by the INSERT");
            throw new Exception('No rows were affected by the INSERT statement');
        }
        
        // Get the insert ID
        $insert_id = $insert_stmt->insert_id;
        error_log("addBoxToContainer: Insert ID: " . $insert_id);
        
        if ($insert_id === 0) {
            error_log("addBoxToContainer: WARNING - Insert ID is 0, this might indicate an issue");
        }
        
        return $insert_id;
        
    } catch (Exception $e) {
        error_log("Add box to container error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Remove item from container
 */
function removeItemFromContainer() {
    global $conn;
    
    try {
        $item_id = (int)$_POST['item_id'];
        $container_id = (int)$_POST['container_id'];
        
        if ($item_id <= 0 || $container_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item or container ID']);
            return;
        }
        
        // Check if container is processed
        $container_check = $conn->prepare("SELECT status FROM containers WHERE id = ?");
        $container_check->bind_param('i', $container_id);
        $container_check->execute();
        $container_result = $container_check->get_result();
        
        if ($container_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Container not found']);
            return;
        }
        
        $container = $container_result->fetch_assoc();
        if ($container['status'] === 'processed') {
            echo json_encode(['success' => false, 'message' => 'Cannot remove items from processed containers']);
            return;
        }
        
        // First, try to delete from container_boxes (for boxes)
        error_log("removeItemFromContainer: Attempting to delete box with ID $item_id from container $container_id");
        $delete_box_sql = "DELETE FROM container_boxes WHERE id = ? AND container_id = ?";
        $delete_box_stmt = $conn->prepare($delete_box_sql);
        $delete_box_stmt->bind_param('ii', $item_id, $container_id);
        $delete_box_stmt->execute();
        
        error_log("removeItemFromContainer: Box delete affected rows: " . $delete_box_stmt->affected_rows);
        
        if ($delete_box_stmt->affected_rows > 0) {
            error_log("removeItemFromContainer: Box successfully deleted");
            echo json_encode(['success' => true, 'message' => 'Box removed from container']);
            return;
        }
        
        // If not a box, try to delete from container_items (for items)
        error_log("removeItemFromContainer: Attempting to delete item with ID $item_id from container $container_id");
        
        // Delete related records first (foreign key constraints)
        $delete_details_sql = "DELETE FROM container_item_details WHERE container_item_id = ?";
        $delete_details_stmt = $conn->prepare($delete_details_sql);
        $delete_details_stmt->bind_param('i', $item_id);
        $delete_details_stmt->execute();
        error_log("removeItemFromContainer: Details delete affected rows: " . $delete_details_stmt->affected_rows);
        
        // Delete the main container item
        $delete_sql = "DELETE FROM container_items WHERE id = ? AND container_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param('ii', $item_id, $container_id);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            error_log("removeItemFromContainer: Item successfully deleted");
            echo json_encode(['success' => true, 'message' => 'Item removed from container']);
        } else {
            error_log("removeItemFromContainer: Item delete failed or not found. Affected rows: " . $delete_stmt->affected_rows);
            echo json_encode(['success' => false, 'message' => 'Item not found or already removed']);
        }
        
    } catch (Exception $e) {
        error_log("Remove item from container error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
}

/**
 * Get all items in a container
 */
function getContainerItems() {
    global $conn;
    
    try {
        $container_id = (int)$_POST['container_id'];
        
        if ($container_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid container ID']);
            return;
        }
        
        // Get container items with new table structure
        $items_query = "SELECT 
                    ci.*,
                    ii.name as existing_item_name,
                    ii.item_code as existing_item_code,
                    ii.base_price as existing_item_base_price,
                    ii.selling_price as existing_item_selling_price,
                    cid.name as new_item_name,
                    cid.code as new_item_code,
                    cid.description as new_item_description,
                    cid.brand as new_item_brand,
                    cid.size as new_item_size,
                    cid.color as new_item_color,
                    cid.material as new_item_material,
                    cid.unit_cost as new_item_unit_cost,
                    cid.selling_price as new_item_selling_price,
                    c.name as category_name
                  FROM container_items ci
                  LEFT JOIN inventory_items ii ON ci.item_id = ii.id
                  LEFT JOIN container_item_details cid ON ci.id = cid.container_item_id
                  LEFT JOIN categories c ON cid.category_id = c.id
                  WHERE ci.container_id = ?
                  ORDER BY ci.created_at ASC";
        
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param('i', $container_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        // Get container boxes
        $boxes_query = "SELECT 
                    cb.*,
                    wb.box_name as warehouse_box_name,
                    wb.box_number as warehouse_box_number,
                    wb.box_type as warehouse_box_type,
                    COALESCE(wb.unit_cost, cb.unit_cost) as warehouse_box_unit_cost
                  FROM container_boxes cb
                  LEFT JOIN warehouse_boxes wb ON cb.warehouse_box_id = wb.id
                  WHERE cb.container_id = ?
                  ORDER BY cb.created_at ASC";
        
        $boxes_stmt = $conn->prepare($boxes_query);
        $boxes_stmt->bind_param('i', $container_id);
        $boxes_stmt->execute();
        $boxes_result = $boxes_stmt->get_result();
        
        // Combine items and boxes
        $items = [];
        while ($row = $items_result->fetch_assoc()) {
            $row['item_type_display'] = 'item';
            $items[] = $row;
        }
        
        while ($row = $boxes_result->fetch_assoc()) {
            $row['item_type_display'] = 'box';
            error_log("getContainerItems: Box data: " . json_encode($row));
            $items[] = $row;
        }
        
        error_log("getContainerItems: Total items count: " . count($items));
        error_log("getContainerItems: Final items data: " . json_encode($items));
        
        echo json_encode(['success' => true, 'data' => $items]);
        
    } catch (Exception $e) {
        error_log("Get container items error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while retrieving container items']);
    }
}

/**
 * Update container item
 */
function updateContainerItem() {
    global $conn;
    
    // Debug: Log the incoming POST data
    error_log("updateContainerItem: POST data: " . print_r($_POST, true));
    
    // Get form data
    $item_id = (int)($_POST['item_id'] ?? 0);
    $container_id = (int)($_POST['container_id'] ?? 0);
    $item_type = sanitize_input($_POST['item_type'] ?? '');
    
    error_log("updateContainerItem: Parsed data - item_id: $item_id, container_id: $container_id, item_type: $item_type");
    

    
    // Validate required fields
        if ($item_id <= 0 || $container_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid item or container ID']);
            return;
        }
        
        // Check if container is processed
        $container_check = $conn->prepare("SELECT status FROM containers WHERE id = ?");
        $container_check->bind_param('i', $container_id);
        $container_check->execute();
        $container_result = $container_check->get_result();
        
        if ($container_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Container not found']);
            return;
        }
        
    $container_data = $container_result->fetch_assoc();
    if ($container_data['status'] === 'processed') {
        echo json_encode(['success' => false, 'message' => 'Cannot edit items in processed containers']);
            return;
        }
        
    if ($item_type === 'existing_item') {
        // Update existing item - follow the same pattern as process_inventory.php
        $item_code = sanitize_input($_POST['item_code'] ?? '');
        $name = sanitize_input($_POST['name'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $base_price = isset($_POST['unit_cost']) ? floatval($_POST['unit_cost']) : 0.0;
        $selling_price = isset($_POST['selling_price']) ? floatval($_POST['selling_price']) : $base_price;
        $size = sanitize_input($_POST['size'] ?? '');
        $color = sanitize_input($_POST['color'] ?? '');
        $material = sanitize_input($_POST['material'] ?? '');
        $brand = sanitize_input($_POST['brand'] ?? '');
        
        // Get the actual inventory item ID from container_items
        $get_item_query = "SELECT item_id FROM container_items WHERE id = ?";
        $get_item_stmt = $conn->prepare($get_item_query);
        $get_item_stmt->bind_param('i', $item_id);
        $get_item_stmt->execute();
        $get_item_result = $get_item_stmt->get_result();
        $item_data = $get_item_result->fetch_assoc();
        $inventory_item_id = $item_data['item_id'];
        
        // Update inventory_items - same as process_inventory.php
        $update_query = "UPDATE inventory_items 
            SET item_code = ?, name = ?, description = ?, category_id = ?, subcategory_id = ?, 
                base_price = ?, selling_price = ?, size = ?, color = ?, material = ?, brand = ?, 
                updated_at = NOW() 
            WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param(
            'sssiiddssssi',
            $item_code, $name, $description, $category_id, $subcategory_id,
            $base_price, $selling_price, $size, $color, $material, $brand,
            $inventory_item_id
        );
        
        if ($update_stmt->execute()) {
            // Update store_inventory selling prices
                            $update_store = "UPDATE store_inventory SET selling_price = ? WHERE item_id = ?";
                            $store_stmt = $conn->prepare($update_store);
            $store_stmt->bind_param('di', $selling_price, $inventory_item_id);
                            $store_stmt->execute();
                    
            echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update item: ' . $conn->error]);
        }
        
    } elseif ($item_type === 'box') {

        
        // Get box details to determine if it's existing or new
        $get_box_query = "SELECT box_type, warehouse_box_id FROM container_boxes WHERE id = ?";
        $get_box_stmt = $conn->prepare($get_box_query);
        $get_box_stmt->bind_param('i', $item_id);
        $get_box_stmt->execute();
        $get_box_result = $get_box_stmt->get_result();
        $box_data = $get_box_result->fetch_assoc();
        
                            // Only update fields that are actually provided (not empty)
                    $update_fields = [];
                    $update_values = [];
                    $update_types = '';
                    
                    if (isset($_POST['box_name']) && $_POST['box_name'] !== '') {
                        $update_fields[] = ($box_data['box_type'] === 'existing') ? "box_name = ?" : "new_box_name = ?";
                        $update_values[] = sanitize_input($_POST['box_name']);
                        $update_types .= 's';
                    }
                    
                    if (isset($_POST['box_description']) && $_POST['box_description'] !== '') {
                        $update_fields[] = ($box_data['box_type'] === 'existing') ? "notes = ?" : "new_box_notes = ?";
                        $update_values[] = sanitize_input($_POST['box_description']);
                        $update_types .= 's';
                    }
                    
                    if (isset($_POST['box_brand']) && $_POST['box_brand'] !== '') {
                        $update_fields[] = ($box_data['box_type'] === 'existing') ? "box_type = ?" : "new_box_type = ?";
                        $update_values[] = sanitize_input($_POST['box_brand']);
                        $update_types .= 's';
                    }
                    
                    if (isset($_POST['box_unit_cost']) && $_POST['box_unit_cost'] !== '') {
                        error_log("updateContainerItem: Adding box_unit_cost to update fields: " . $_POST['box_unit_cost']);
                        $update_fields[] = "unit_cost = ?";
                        $update_values[] = floatval($_POST['box_unit_cost']);
                        $update_types .= 'd';
                    }
                    
                    error_log("updateContainerItem: Update fields: " . json_encode($update_fields));
                    error_log("updateContainerItem: Update values: " . json_encode($update_values));
                    error_log("updateContainerItem: Update types: " . $update_types);
                    
                    if (!empty($update_fields)) {
                        if ($box_data['box_type'] === 'existing' && $box_data['warehouse_box_id']) {
                            // Update existing box in warehouse_boxes table
                            $update_values[] = $box_data['warehouse_box_id'];
                            $update_types .= 'i';
                            $update_query = "UPDATE warehouse_boxes SET " . implode(', ', $update_fields) . " WHERE id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bind_param($update_types, ...$update_values);
                            
                            if ($update_stmt->execute()) {
                                echo json_encode(['success' => true, 'message' => 'Box updated successfully']);
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Failed to update box: ' . $conn->error]);
                            }
                        } else {
                            // Update new box in container_boxes table
                            $update_values[] = $item_id;
                            $update_types .= 'i';
                            $update_query = "UPDATE container_boxes SET " . implode(', ', $update_fields) . " WHERE id = ?";
                            $update_stmt = $conn->prepare($update_query);
                            $update_stmt->bind_param($update_types, ...$update_values);
                            
                            if ($update_stmt->execute()) {
                                echo json_encode(['success' => true, 'message' => 'Box updated successfully']);
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Failed to update box: ' . $conn->error]);
                            }
                        }
                    } else {
                        echo json_encode(['success' => true, 'message' => 'No changes to update']);
                    }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid item type']);
    }
}

// ============================================================================
// HELPER FUNCTIONS FOR CONTAINER CREATION
// ============================================================================

/**
 * Validate and prepare box item data for container creation
 */
function validateAndPrepareBoxItemForCreation($item) {
    error_log("validateAndPrepareBoxItemForCreation: Processing item: " . json_encode($item));
    
    // Check if this is a new box or existing box
    if (isset($item['new_box_data'])) {
        // New box creation
        error_log("validateAndPrepareBoxItemForCreation: Processing NEW box");
        
        if (empty($item['new_box_data']['box_number']) || empty($item['new_box_data']['box_name']) || empty($item['quantity'])) {
            error_log("validateAndPrepareBoxItemForCreation: Missing required new box data");
            return null;
        }
        
        // Set default values for optional fields
        $box_type = !empty($item['new_box_data']['box_type']) ? $item['new_box_data']['box_type'] : '';
        $box_quantity = !empty($item['new_box_data']['quantity']) ? (int)$item['new_box_data']['quantity'] : 0;
        $box_notes = !empty($item['new_box_data']['notes']) ? $item['new_box_data']['notes'] : '';
        
        // Don't create the warehouse box yet - it will be created when the container is processed
        // Just prepare the data for the container_boxes table
        $box_data = [
            'warehouse_box_id' => null, // Will be set when processed
            'quantity' => (int)$item['quantity'],
            'new_box_number' => $item['new_box_data']['box_number'],
            'new_box_name' => $item['new_box_data']['box_name'],
            'new_box_type' => $box_type,
            'new_box_notes' => $box_notes
        ];
        
        error_log("validateAndPrepareBoxItemForCreation: Prepared NEW box data: " . json_encode($box_data));
        return $box_data;
    } else {
        // Existing box selection
        error_log("validateAndPrepareBoxItemForCreation: Processing EXISTING box");
        
        if (empty($item['warehouse_box_id']) || empty($item['quantity'])) {
            error_log("validateAndPrepareBoxItemForCreation: Missing required existing box data");
            return null;
        }
        
        $box_data = [
            'warehouse_box_id' => (int)$item['warehouse_box_id'],
            'quantity' => (int)$item['quantity']
        ];
        
        error_log("validateAndPrepareBoxItemForCreation: Prepared EXISTING box data: " . json_encode($box_data));
        return $box_data;
    }
}

/**
 * Validate and prepare existing item data for container creation
 */
function validateAndPrepareExistingItemForCreation($item) {
    if (empty($item['item_id']) || empty($item['quantity'])) {
        return null;
    }
    
    return [
        'warehouse_box_id' => null,
        'item_id' => (int)$item['item_id'],
        'new_item_name' => null,
        'new_item_code' => null,
        'new_item_description' => null,
        'category_id' => null,
        'new_item_brand' => null,
        'new_item_size' => null,
        'new_item_color' => null,
        'new_item_material' => null,
        'quantity' => (int)$item['quantity'],
        'quantity_in_container' => (int)$item['quantity']
    ];
}

/**
 * Validate and prepare new item data for container creation
 */
function validateAndPrepareNewItemForCreation($item) {
    if (empty($item['name']) || empty($item['code']) || empty($item['quantity']) || empty($item['unit_cost']) || empty($item['selling_price'])) {
        return null;
    }
    
    return [
        'warehouse_box_id' => null,
        'item_id' => null,
        'name' => sanitize_input($item['name']),
        'code' => sanitize_input($item['code']),
        'description' => sanitize_input($item['description'] ?? ''),
        'category_id' => !empty($item['category_id']) ? (int)$item['category_id'] : null,
        'brand' => sanitize_input($item['brand'] ?? ''),
        'size' => sanitize_input($item['size'] ?? ''),
        'color' => sanitize_input($item['color'] ?? ''),
        'material' => sanitize_input($item['material'] ?? ''),
        'quantity' => (int)$item['quantity'],
        'quantity_in_container' => (int)$item['quantity'],
        'unit_cost' => (float)$item['unit_cost'],
        'selling_price' => (float)$item['selling_price']
    ];
}



?> 