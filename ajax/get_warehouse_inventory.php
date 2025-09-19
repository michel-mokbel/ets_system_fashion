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

function getWarehouseInventory() {
    global $conn;
    
    $search = trim($_POST['search'] ?? '');
    $category_filter = (int)($_POST['category_id'] ?? 0);
    $stock_filter = $_POST['stock_filter'] ?? '';
    $destination_store_id = (int)($_POST['destination_store_id'] ?? 0);
    
    $where_conditions = ["i.status = 'active'"];
    $params = [1, $destination_store_id, $destination_store_id]; // Main warehouse store_id, destination store_id for inventory, destination store_id for assignments
    $param_types = 'iii';
    
    // Search filter
    if (!empty($search)) {
        $where_conditions[] = "(i.name LIKE ? OR i.item_code LIKE ? OR i.brand LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $param_types .= 'sss';
    }
    
    // Category filter
    if ($category_filter > 0) {
        $where_conditions[] = "i.category_id = ?";
        $params[] = $category_filter;
        $param_types .= 'i';
    }
    
    // Stock filter
    if ($stock_filter === 'available') {
        $where_conditions[] = "COALESCE(si_warehouse.current_stock, 0) > 0";
    } elseif ($stock_filter === 'low') {
        $where_conditions[] = "COALESCE(si_warehouse.current_stock, 0) <= COALESCE(si_warehouse.minimum_stock, 0) AND COALESCE(si_warehouse.current_stock, 0) > 0";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT 
                i.id, i.item_code, i.name, i.base_price, i.selling_price,
                i.size, i.color, i.brand, i.material,
                c.name as category_name,
                sc.name as subcategory_name,
                b.id as barcode_id, b.barcode, b.price as barcode_price,
                COALESCE(si_warehouse.current_stock, 0) as warehouse_stock,
                COALESCE(si_warehouse.cost_price, i.base_price) as cost_price,
                COALESCE(si_warehouse.selling_price, i.selling_price) as warehouse_selling_price,
                COALESCE(si_warehouse.minimum_stock, 0) as minimum_stock,
                COALESCE(si_destination.current_stock, 0) as destination_stock,
                cont.container_number, cont.id as container_id
              FROM inventory_items i
              LEFT JOIN categories c ON i.category_id = c.id
              LEFT JOIN subcategories sc ON i.subcategory_id = sc.id
              LEFT JOIN barcodes b ON i.id = b.item_id
              LEFT JOIN store_inventory si_warehouse ON (i.id = si_warehouse.item_id AND si_warehouse.store_id = ? AND si_warehouse.barcode_id = b.id)
              LEFT JOIN store_inventory si_destination ON (i.id = si_destination.item_id AND si_destination.store_id = ? AND si_destination.barcode_id = b.id)
              INNER JOIN store_item_assignments sia ON (i.id = sia.item_id AND sia.store_id = ? AND sia.is_active = 1)
              LEFT JOIN containers cont ON i.container_id = cont.id
              WHERE $where_clause
              ORDER BY i.name ASC, b.id ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => $row['id'],
            'item_code' => $row['item_code'],
            'name' => $row['name'],
            'size' => $row['size'],
            'color' => $row['color'],
            'brand' => $row['brand'],
            'material' => $row['material'],
            'category_name' => $row['category_name'],
            'subcategory_name' => $row['subcategory_name'],
            'barcode_id' => $row['barcode_id'],
            'barcode' => $row['barcode'],
            'warehouse_stock' => (int)$row['warehouse_stock'],
            'destination_stock' => (int)$row['destination_stock'],
            'cost_price' => (float)$row['cost_price'],
            'selling_price' => (float)$row['warehouse_selling_price'],
            'minimum_stock' => (int)$row['minimum_stock'],
            'stock_status' => getStockStatus($row['warehouse_stock'], $row['minimum_stock']),
            'container_number' => $row['container_number'],
            'container_id' => $row['container_id']
        ];
    }
    
    echo json_encode(['success' => true, 'items' => $items]);
}

function getStockStatus($current, $minimum) {
    if ($current <= 0) return 'out_of_stock';
    if ($current <= $minimum) return 'low_stock';
    return 'in_stock';
}

getWarehouseInventory();
?> 