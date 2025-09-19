<?php
/**
 * Provides detailed information for a specific container item.
 *
 * Returns box associations, cost breakdown, and quantity metrics for display in
 * the container management modal. Access is restricted to admins and results
 * are formatted as JSON.
 */
require_once 'ajax_session_init.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $container_id = (int)($_POST['container_id'] ?? 0);
    
    if ($item_id <= 0 || $container_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item or container ID']);
        exit;
    }
    
    // First, check if it's an item or a box
    $check_query = "SELECT 'item' as type FROM container_items WHERE id = ? AND container_id = ?
                    UNION ALL
                    SELECT 'box' as type FROM container_boxes WHERE id = ? AND container_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('iiii', $item_id, $container_id, $item_id, $container_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $type_row = $check_result->fetch_assoc();
    $item_type = $type_row['type'];
    
    if ($item_type === 'item') {
        // Get item details
        $query = "SELECT 
                    ci.*,
                    ii.name as existing_item_name,
                    ii.item_code as existing_item_code,
                    ii.description as existing_item_description,
                    ii.category_id as existing_item_category_id,
                    ii.subcategory_id as existing_item_subcategory_id,
                    ii.brand as existing_item_brand,
                    ii.size as existing_item_size,
                    ii.color as existing_item_color,
                    ii.material as existing_item_material,
                    ii.base_price as existing_item_base_price,
                    ii.selling_price as existing_item_selling_price,
                    cid.name as new_item_name,
                    cid.code as new_item_code,
                    cid.description as new_item_description,
                    cid.category_id as new_item_category_id,
                    cid.brand as new_item_brand,
                    cid.size as new_item_size,
                    cid.color as new_item_color,
                    cid.material as new_item_material,
                    cid.unit_cost as new_item_unit_cost,
                    cid.selling_price as new_item_selling_price
                  FROM container_items ci
                  LEFT JOIN inventory_items ii ON ci.item_id = ii.id
                  LEFT JOIN container_item_details cid ON ci.id = cid.container_item_id
                  WHERE ci.id = ? AND ci.container_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $item_id, $container_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        
    } else {
        // Get box details
        $query = "SELECT 
                    cb.*,
                    wb.box_name as warehouse_box_name,
                    wb.box_number as warehouse_box_number,
                    wb.box_type as warehouse_box_type,
                    wb.unit_cost as warehouse_box_unit_cost
                  FROM container_boxes cb
                  LEFT JOIN warehouse_boxes wb ON cb.warehouse_box_id = wb.id
                  WHERE cb.id = ? AND cb.container_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $item_id, $container_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
    }
    
    // Format the response based on item type
    if ($item_type === 'item') {
        if ($item['item_type'] === 'existing_item') {
            $response_data = [
                'id' => $item['id'],
                'item_type' => $item['item_type'],
                'item_code' => $item['existing_item_code'],
                'name' => $item['existing_item_name'],
                'description' => $item['existing_item_description'],
                'category_id' => $item['existing_item_category_id'],
                'subcategory_id' => $item['existing_item_subcategory_id'],
                'brand' => $item['existing_item_brand'],
                'size' => $item['existing_item_size'],
                'color' => $item['existing_item_color'],
                'material' => $item['existing_item_material'],
                'unit_cost' => $item['existing_item_base_price'],
                'selling_price' => $item['existing_item_selling_price'],
                'quantity_in_container' => $item['quantity_in_container']
            ];
        } else {
            $response_data = [
                'id' => $item['id'],
                'item_type' => $item['item_type'],
                'item_code' => $item['new_item_code'],
                'name' => $item['new_item_name'],
                'description' => $item['new_item_description'],
                'category_id' => $item['new_item_category_id'],
                'subcategory_id' => null,
                'brand' => $item['new_item_brand'],
                'size' => $item['new_item_size'],
                'color' => $item['new_item_color'],
                'material' => $item['new_item_material'],
                'unit_cost' => $item['new_item_unit_cost'],
                'selling_price' => $item['new_item_selling_price'],
                'quantity_in_container' => $item['quantity_in_container']
            ];
        }
    } else {
        // Box data - determine if it's existing or new box
        if ($item['box_type'] === 'existing') {
            // Existing box - get data from warehouse_boxes
            $response_data = [
                'id' => $item['id'],
                'item_type' => 'box',
                'box_type' => 'existing',
                'item_code' => $item['warehouse_box_number'] ?? '',
                'name' => $item['warehouse_box_name'] ?? '',
                'description' => $item['notes'] ?? '',
                'category_id' => null,
                'subcategory_id' => null,
                'brand' => $item['warehouse_box_type'] ?? '',
                'size' => '',
                'color' => '',
                'material' => '',
                'unit_cost' => $item['warehouse_box_unit_cost'] ?? $item['unit_cost'] ?? 0,
                'selling_price' => 0, // Boxes don't have selling price
                'quantity_in_container' => $item['quantity'] ?? 1
            ];
        } else {
            // New box - get data from container_boxes
            $response_data = [
                'id' => $item['id'],
                'item_type' => 'box',
                'box_type' => 'new',
                'item_code' => $item['new_box_number'] ?? '',
                'name' => $item['new_box_name'] ?? '',
                'description' => $item['new_box_notes'] ?? '',
                'category_id' => null,
                'subcategory_id' => null,
                'brand' => $item['new_box_type'] ?? '',
                'size' => '',
                'color' => '',
                'material' => '',
                'unit_cost' => $item['unit_cost'] ?? 0,
                'selling_price' => 0, // Boxes don't have selling price
                'quantity_in_container' => $item['quantity'] ?? 1
            ];
        }
    }
    
    echo json_encode(['success' => true, 'data' => $response_data]);
    
} catch (Exception $e) {
    error_log("Get container item details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
