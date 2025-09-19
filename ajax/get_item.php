<?php
/**
 * Fetches a single inventory item for editing.
 *
 * Returns catalog attributes, pricing, and assignment hints to populate admin
 * edit dialogs. Ensures the caller has inventory privileges before emitting
 * JSON.
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
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get item ID from POST
$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

if ($item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

try {
    // Get user's store if not admin
    $user_store_id = $_SESSION['store_id'] ?? null;
    $store_filter = '';
    $store_params = [];
    
    if (!is_admin() && !is_inventory_manager() && $user_store_id) {
        $store_filter = ' AND si.store_id = ?';
        $store_params[] = $user_store_id;
    }

    // Build query to get item with category, subcategory, and store inventory information
    $query = "SELECT 
                i.id,
                i.item_code,
                i.name,
                i.description,
                i.category_id,
                i.subcategory_id,
                i.base_price,
                i.selling_price,
                i.size,
                i.color,
                i.material,
                i.brand,
                i.image_path,
                i.status,
                i.container_id,
                i.created_at,
                i.updated_at,
                c.name as category_name,
                sc.name as subcategory_name,
                cont.container_number,
                GROUP_CONCAT(DISTINCT CONCAT(s.name, ':', COALESCE(si.current_stock, 0), ':', COALESCE(si.minimum_stock, 0), ':', COALESCE(si.selling_price, i.base_price), ':', COALESCE(si.cost_price, 0), ':', COALESCE(si.location_in_store, '')) SEPARATOR '|') as store_inventory,
                GROUP_CONCAT(DISTINCT b.barcode SEPARATOR ',') as barcodes
              FROM inventory_items i
              LEFT JOIN categories c ON i.category_id = c.id
              LEFT JOIN subcategories sc ON i.subcategory_id = sc.id
              LEFT JOIN containers cont ON i.container_id = cont.id
              LEFT JOIN store_inventory si ON i.id = si.item_id" . $store_filter . "
              LEFT JOIN stores s ON si.store_id = s.id
              LEFT JOIN barcodes b ON i.id = b.item_id
              WHERE i.id = ?
              GROUP BY i.id";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    // Bind parameters
    $params = array_merge($store_params, [$item_id]);
    $param_types = str_repeat('i', count($store_params)) . 'i';
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $item = $result->fetch_assoc();
        
        // Parse store inventory data
        $store_inventories = [];
        if (!empty($item['store_inventory'])) {
            $stores = explode('|', $item['store_inventory']);
            foreach ($stores as $store_data) {
                if (!empty($store_data)) {
                    $parts = explode(':', $store_data);
                    if (count($parts) >= 6) {
                        $store_inventories[] = [
                            'store_name' => $parts[0],
                            'current_stock' => (int)$parts[1],
                            'minimum_stock' => (int)$parts[2],
                            'selling_price' => (float)$parts[3],
                            'cost_price' => (float)$parts[4],
                            'location_in_store' => $parts[5]
                        ];
                    }
                }
            }
        }
        $item['store_inventories'] = $store_inventories;
        
        // Parse barcode data
        $barcodes = [];
        if (!empty($item['barcodes'])) {
            $barcodes = explode(',', $item['barcodes']);
        }
        $item['barcodes_list'] = $barcodes;
        
        // Calculate total stock across all stores
        $total_stock = 0;
        $avg_selling_price = 0;
        $locations = [];
        
        foreach ($store_inventories as $store_inv) {
            $total_stock += $store_inv['current_stock'];
            $avg_selling_price += $store_inv['selling_price'];
            if (!empty($store_inv['location_in_store'])) {
                $locations[] = $store_inv['store_name'] . ': ' . $store_inv['location_in_store'];
            }
        }
        
        if (count($store_inventories) > 0) {
            $avg_selling_price = $avg_selling_price / count($store_inventories);
        } else {
            $avg_selling_price = $item['selling_price']; // Use actual selling_price instead of base_price
        }
        
        $item['total_stock'] = $total_stock;
        $item['average_selling_price'] = $avg_selling_price;
        $item['all_locations'] = implode(', ', $locations);
        
        // Determine stock status
        $min_stock = 0;
        foreach ($store_inventories as $store_inv) {
            $min_stock += $store_inv['minimum_stock'];
        }
        
        if ($total_stock == 0) {
            $item['stock_status'] = 'out_of_stock';
            $item['stock_status_text'] = 'Out of Stock';
            $item['stock_status_class'] = 'bg-danger';
        } elseif ($total_stock <= $min_stock) {
            $item['stock_status'] = 'low_stock';
            $item['stock_status_text'] = 'Low Stock';
            $item['stock_status_class'] = 'bg-warning';
        } else {
            $item['stock_status'] = 'in_stock';
            $item['stock_status_text'] = 'In Stock';
            $item['stock_status_class'] = 'bg-success';
        }
        
        // Format prices
        $item['base_price_formatted'] = number_format($item['base_price'], 2);
        $item['average_selling_price_formatted'] = number_format($avg_selling_price, 2);
        
        // Remove the raw concatenated data
        unset($item['store_inventory']);
        unset($item['barcodes']);
        
        echo json_encode(['success' => true, 'data' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
    
} catch (Exception $e) {
    error_log("Error retrieving item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving item data: ' . $e->getMessage()]);
}