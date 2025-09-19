<?php
/**
 * Lists all items tied to a procurement container.
 *
 * Feeds the nested tables in the container management UI by returning serialized
 * item, box, and quantity data. Only administrators may call this endpoint and
 * responses are emitted as JSON.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'error' => 'Invalid security token',
        'success' => false,
        'message' => 'Invalid security token'
    ]);
    exit;
}

/**
 * Get display name for an item based on its type
 */
function getItemDisplayName($item) {
    switch ($item['item_type']) {
        case 'box':
            if (isset($item['warehouse_box_name']) && $item['warehouse_box_name']) {
                return $item['warehouse_box_name'];
            } elseif (isset($item['new_box_name']) && $item['new_box_name']) {
                return $item['new_box_name'];
            } else {
                return "Box #" . ($item['warehouse_box_number'] ?? $item['new_box_number'] ?? 'Unknown');
            }
        case 'existing_item':
            return $item['existing_item_name'] ?: "Item #" . $item['item_id'];
        case 'new_item':
            return $item['new_item_name'] ?: "New Item";
        default:
            return "Unknown Item";
    }
}

/**
 * Get display type for an item
 */
function getItemDisplayType($item) {
    switch ($item['item_type']) {
        case 'box':
            return "Warehouse Box";
        case 'existing_item':
            return "Existing Item";
        case 'new_item':
            return "New Item";
        default:
            return "Unknown";
    }
}

try {
    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Get container ID
    $container_id = isset($_POST['container_id']) ? (int)$_POST['container_id'] : 0;
    
    if ($container_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid container ID'
        ]);
        exit;
    }
    
    // Check if container_items table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'container_items'");
    if (!$table_check || $table_check->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Container items table does not exist'
        ]);
        exit;
    }
    
    // Verify container exists
    $container_check = $conn->prepare("SELECT id, container_number, status FROM containers WHERE id = ?");
    if (!$container_check) {
        throw new Exception('Failed to prepare container check query: ' . $conn->error);
    }
    
    $container_check->bind_param('i', $container_id);
    $container_check->execute();
    $container_result = $container_check->get_result();
    
    if ($container_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Container not found'
        ]);
        exit;
    }
    
    $container_info = $container_result->fetch_assoc();
    
    // Get container items with detailed information (new table structure)
    $query = "SELECT 
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
                c.name as category_name,
                c.id as category_id
              FROM container_items ci
              LEFT JOIN inventory_items ii ON ci.item_id = ii.id
              LEFT JOIN container_item_details cid ON ci.id = cid.container_item_id
              LEFT JOIN categories c ON cid.category_id = c.id
              WHERE ci.container_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare items query: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $container_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get container boxes separately (new table structure)
    $boxes_query = "SELECT 
                    cb.*,
                    wb.box_name as warehouse_box_name,
                    wb.box_number as warehouse_box_number,
                    wb.box_type as warehouse_box_type,
                    wb.quantity as warehouse_box_available_quantity
                  FROM container_boxes cb
                  LEFT JOIN warehouse_boxes wb ON cb.warehouse_box_id = wb.id
                  WHERE cb.container_id = ?";
    
    $boxes_stmt = $conn->prepare($boxes_query);
    if (!$boxes_stmt) {
        throw new Exception('Failed to prepare boxes query: ' . $conn->error);
    }
    
    $boxes_stmt->bind_param('i', $container_id);
    $boxes_stmt->execute();
    $boxes_result = $boxes_stmt->get_result();
    
    $items = [];
    $total_items = 0;
    $total_value = 0;
    $item_types = [];
    
    // Process items
    while ($row = $result->fetch_assoc()) {
        // Calculate display values
        $row['display_name'] = getItemDisplayName($row);
        $row['display_type'] = getItemDisplayType($row);
        $row['item_type_display'] = getItemDisplayType($row); // Add this for frontend compatibility
        
        // Calculate total value based on item type
        if ($row['item_type'] === 'existing_item') {
            $unit_cost = floatval($row['existing_item_base_price'] ?? 0);
            $quantity = intval($row['quantity_in_container'] ?? 1);
            $row['total_value'] = $unit_cost * $quantity;
        } elseif ($row['item_type'] === 'new_item') {
            $unit_cost = floatval($row['new_item_unit_cost'] ?? 0);
            $quantity = intval($row['quantity_in_container'] ?? 1);
            $row['total_value'] = $unit_cost * $quantity;
        } else {
            $row['total_value'] = 0;
        }
        
        $row['can_edit'] = $container_info['status'] !== 'processed';
        $row['can_remove'] = $container_info['status'] !== 'processed';
        
        // Add to totals
        $total_items += $row['quantity_in_container'];
        $total_value += $row['total_value'];
        
        // Track item types
        if (!isset($item_types[$row['item_type']])) {
            $item_types[$row['item_type']] = 0;
        }
        $item_types[$row['item_type']]++;
        
        $items[] = $row;
    }
    
    // Process boxes
    while ($row = $boxes_result->fetch_assoc()) {
        // Set item_type for boxes
        $row['item_type'] = 'box';
        
        // Calculate display values
        $row['display_name'] = getItemDisplayName($row);
        $row['display_type'] = getItemDisplayType($row);
        $row['item_type_display'] = getItemDisplayType($row); // Add this for frontend compatibility
        $row['total_value'] = 0; // Boxes don't have cost in this context
        $row['can_edit'] = $container_info['status'] !== 'processed';
        $row['can_remove'] = $container_info['status'] !== 'processed';
        
        // Add to totals
        $total_items += $row['quantity'];
        
        // Track item types
        if (!isset($item_types['box'])) {
            $item_types['box'] = 0;
        }
        $item_types['box']++;
        
        $items[] = $row;
    }
    
    // Get container summary
    $container_summary = [
        'id' => $container_info['id'],
        'container_number' => $container_info['container_number'],
        'status' => $container_info['status'],
        'total_items' => $total_items,
        'total_value' => $total_value,
        'item_types' => $item_types,
        'can_add_items' => $container_info['status'] !== 'processed'
    ];
    
    echo json_encode([
        'success' => true,
        'container' => $container_summary,
        'items' => $items,
        'count' => count($items)
    ]);
    
} catch (Exception $e) {
    error_log("Get container items error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while retrieving container items: ' . $e->getMessage()
    ]);
}
?>
