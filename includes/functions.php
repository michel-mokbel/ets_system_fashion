<?php
// Turn off error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Authentication functions
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Role-based access control
function has_role($required_role) {
    if (!is_logged_in()) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'] ?? '';
    
    // Admin has access to everything
    if ($user_role === 'admin') {
        return true;
    }
    
    // Check specific role
    if (is_array($required_role)) {
        return in_array($user_role, $required_role);
    }
    
    return $user_role === $required_role;
}

function require_role($required_role) {
    if (!has_role($required_role)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied. Required role: " . (is_array($required_role) ? implode(' or ', $required_role) : $required_role));
    }
}

function get_user_store_id() {
    return $_SESSION['store_id'] ?? null;
}

function is_admin() {
    return has_role('admin');
}

function is_store_manager() {
    return has_role('store_manager');
}

function is_sales_person() {
    return has_role('sales_person');
}

function is_inventory_manager() {
    return has_role('inventory_manager');
}

function is_transfer_manager() {
    return has_role('transfer_manager');
}

// Read-only viewer role
function is_view_only() {
    if (!is_logged_in()) return false;
    $user_role = $_SESSION['user_role'] ?? '';
    return $user_role === 'viewer';
}

function can_access_inventory() {
    // Viewers can access pages for read-only browsing
    return is_admin() || is_inventory_manager() || is_view_only();
}

function can_access_transfers() {
    // Viewers can access pages for read-only browsing
    return is_admin() || is_inventory_manager() || is_transfer_manager() || is_store_manager() || is_view_only();
}

// Enforce write restrictions for viewer accounts
function require_write_access() {
    if (is_view_only()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Read-only user: action not permitted']);
        exit;
    }
}

function can_access_store($store_id) {
    if (is_admin() || is_inventory_manager()) {
        return true; // Admin and inventory manager can access all stores
    }
    
    return get_user_store_id() == $store_id;
}

// Utility functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function format_date($date) {
    return date('d/m/Y', strtotime($date));
}

function format_currency($amount) {
    return number_format($amount, 2);
}

/**
 * Get all categories
 * @return array Array of categories
 */
function get_all_categories() {
    global $conn;
    
    $categories = [];
    $query = "SELECT id, name, description, created_at FROM categories ORDER BY name ASC";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    
    return $categories;
}

/**
 * Get all subcategories for a category
 * @param int $category_id The category ID
 * @return array Array of subcategories
 */
function get_subcategories($category_id) {
    global $conn;
    
    $subcategories = [];
    $query = "SELECT id, name, description FROM subcategories WHERE category_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = $row;
        }
    }
    
    return $subcategories;
}

/**
 * Get category name by ID
 * @param int $category_id The category ID
 * @return string The category name or empty string if not found
 */
function get_category_name($category_id) {
    global $conn;
    
    if (empty($category_id)) {
        return '';
    }
    
    $query = "SELECT name FROM categories WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['name'];
    }
    
    return '';
}

/**
 * Get subcategory name by ID
 * @param int $subcategory_id The subcategory ID
 * @return string The subcategory name or empty string if not found
 */
function get_subcategory_name($subcategory_id) {
    global $conn;
    
    if (empty($subcategory_id)) {
        return '';
    }
    
    $query = "SELECT name FROM subcategories WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['name'];
    }
    
    return '';
}

/**
 * Get all stores
 * @return array Array of stores
 */
function get_all_stores() {
    global $conn;
    
    $stores = [];
    $query = "SELECT s.*, u.full_name as manager_name 
              FROM stores s 
              LEFT JOIN users u ON s.manager_id = u.id 
              WHERE s.status = 'active' 
              ORDER BY s.name ASC";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stores[] = $row;
        }
    }
    
    return $stores;
}

function get_all_salespersons() {
    global $conn;
    
    $salespersons = [];
    $query = "SELECT u.id, u.full_name, u.username, s.name as store_name
              FROM users u 
              LEFT JOIN stores s ON u.store_id = s.id
              WHERE u.role IN ('sales_person', 'store_manager') 
              AND u.status = 'active'
              ORDER BY u.full_name ASC";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $salespersons[] = $row;
        }
    }
    
    return $salespersons;
}

function get_salespersons_by_store($store_id) {
    global $conn;
    
    $salespersons = [];
    $query = "SELECT u.id, u.full_name, u.username, s.name as store_name
              FROM users u 
              LEFT JOIN stores s ON u.store_id = s.id
              WHERE u.role IN ('sales_person', 'store_manager') 
              AND u.status = 'active'
              AND u.store_id = ?
              ORDER BY u.full_name ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("SQL Error in get_salespersons_by_store: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $salespersons[] = $row;
        }
    }
    
    return $salespersons;
}

/**
 * Get store information by ID
 * @param int $store_id The store ID
 * @return array|null Store information or null if not found
 */
function get_store_info($store_id) {
    global $conn;
    
    $query = "SELECT s.*, u.full_name as manager_name 
              FROM stores s 
              LEFT JOIN users u ON s.manager_id = u.id 
              WHERE s.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Generate barcode for an item
 * @param int $item_id The item ID
 * @param float $price The selling price
 * @return string The generated barcode
 */
function generate_barcode($item_id, $price) {
    global $conn;
    
    error_log("generate_barcode called with item_id: " . $item_id . " and price: " . $price);
    
    // Check if there's already a barcode with this price (shared barcode)
    $query = "SELECT barcode FROM barcodes WHERE price = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('d', $price);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("Found " . $result->num_rows . " existing barcodes with price: " . $price);
    
    if ($result && $result->num_rows > 0) {
        // Return existing barcode (shared)
        $shared_barcode = $result->fetch_assoc()['barcode'];
        error_log("Returning existing barcode: " . $shared_barcode);
        return $shared_barcode;
    } else {
        // Generate unique barcode
        do {
            $barcode = '123456789' . str_pad($item_id, 4, '0', STR_PAD_LEFT) . str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
            
            // Check if barcode already exists
            $query = "SELECT id FROM barcodes WHERE barcode = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $barcode);
            $stmt->execute();
            $result = $stmt->get_result();
        } while ($result && $result->num_rows > 0);
        
        error_log("Generated new unique barcode: " . $barcode);
        return $barcode;
    }
}

/**
 * Get item by barcode
 * @param string $barcode The barcode to search for
 * @param int $store_id The store ID (optional)
 * @return array|null Item information or null if not found
 */
function get_item_by_barcode($barcode, $store_id = null) {
    global $conn;
    
    $query = "SELECT i.*, si.current_stock, 
                     COALESCE(wp.price, si.selling_price, i.selling_price) as selling_price, 
                     si.cost_price, 
                     c.name as category_name
              FROM inventory_items i
              LEFT JOIN categories c ON i.category_id = c.id";
    
    if ($store_id) {
        // Only return items that are assigned to this store
        $query .= " INNER JOIN store_item_assignments sia ON i.id = sia.item_id
                   LEFT JOIN store_inventory si ON (si.item_id = i.id AND si.store_id = ?)
                   LEFT JOIN item_weekly_prices wp ON (wp.item_id = i.id AND wp.store_id = ? AND wp.weekday = ?)
                   WHERE i.item_code = ? AND sia.store_id = ? AND sia.is_active = 1";
        $stmt = $conn->prepare($query);
        $weekday = (int)date('N') - 1;
        $stmt->bind_param('iiisi', $store_id, $store_id, $weekday, $barcode, $store_id);
    } else {
        $query .= " WHERE i.item_code = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $barcode);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Update store inventory stock
 * @param int $store_id Store ID
 * @param int $item_id Item ID
 * @param int $barcode_id Barcode ID
 * @param int $quantity Quantity to add/subtract
 * @param string $type Transaction type (in/out/adjustment)
 * @param array $reference Reference information
 * @return bool Success status
 */
function update_store_inventory($store_id, $item_id, $barcode_id, $quantity, $type, $reference = null) {
    global $conn;
    
    // Debug log
    error_log("update_store_inventory called with: store_id=$store_id, item_id=$item_id, barcode_id=$barcode_id, quantity=$quantity, type=$type");
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update stock
        $operator = ($type === 'in') ? '+' : '-';
        $update_sql = "UPDATE store_inventory 
                      SET current_stock = current_stock $operator ? 
                      WHERE store_id = ? AND item_id = ? AND barcode_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iiii", $quantity, $store_id, $item_id, $barcode_id);
        $stmt->execute();
        error_log("UPDATE affected_rows: " . $stmt->affected_rows);

        // If no rows were updated
        if ($stmt->affected_rows === 0) {
            if ($type === 'out') {
                // Prevent negative stock insert
                error_log("WARNING: Attempted to sell item not in stock: store_id=$store_id, item_id=$item_id, barcode_id=$barcode_id");
                $conn->rollback();
                return false;
            }
            // For 'in' type, allow insert
            $price_stmt = $conn->prepare("SELECT selling_price FROM inventory_items WHERE id = ?");
            $price_stmt->bind_param('i', $item_id);
            $price_stmt->execute();
            $price_result = $price_stmt->get_result();
            $selling_price = $price_result->num_rows ? floatval($price_result->fetch_assoc()['selling_price']) : 0.0;
            $initial_stock = $quantity;
            $insert_sql = "INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiiid", $store_id, $item_id, $barcode_id, $initial_stock, $selling_price);
            $insert_stmt->execute();
            error_log("INSERT executed: store_id=$store_id, item_id=$item_id, barcode_id=$barcode_id, current_stock=$initial_stock, selling_price=$selling_price");
        }

        // Log transaction
        $log_sql = "INSERT INTO inventory_transactions 
                   (store_id, item_id, barcode_id, transaction_type, quantity, reference_type, reference_id, user_id) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($log_sql);
        $reference_type = $reference['type'] ?? 'manual';
        $reference_id = $reference['id'] ?? null;
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param("iiisisii", $store_id, $item_id, $barcode_id, $type, $quantity, $reference_type, $reference_id, $user_id);
        $stmt->execute();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Store inventory update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get low stock items for a store
 * @param int $store_id Store ID (null for all stores if admin)
 * @return array Array of low stock items
 */
function get_low_stock_items($store_id = null) {
    global $conn;
    
    $query = "SELECT si.*, i.name, i.item_code, s.name as store_name, c.name as category_name
              FROM store_inventory si
              JOIN inventory_items i ON si.item_id = i.id
              JOIN stores s ON si.store_id = s.id
              LEFT JOIN categories c ON i.category_id = c.id
              WHERE si.current_stock <= si.minimum_stock 
              AND i.status = 'active'";
    
    if ($store_id) {
        $query .= " AND si.store_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $store_id);
    } else {
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    return $items;
}

/**
 * Generate next invoice number
 * @param int $store_id Store ID
 * @return string Invoice number
 */
function generate_invoice_number($store_id) {
    global $conn;
    
    $store_info = get_store_info($store_id);
    $store_code = $store_info['store_code'] ?? 'STORE';
    
    // Get last invoice number for this store
    $query = "SELECT MAX(CAST(SUBSTRING(invoice_number, LENGTH(?) + 2) AS UNSIGNED)) as last_number 
              FROM invoices 
              WHERE store_id = ? AND invoice_number LIKE ?";
    $pattern = $store_code . '-%';
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sis', $store_code, $store_id, $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $last_number = 0;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_number = $row['last_number'] ?? 0;
    }
    
    $next_number = $last_number + 1;
    return $store_code . '-' . str_pad($next_number, 6, '0', STR_PAD_LEFT);
}

/**
 * Generate next return number
 * @param int $store_id Store ID
 * @return string Return number
 */
function generate_return_number($store_id) {
    global $conn;
    
    $store_info = get_store_info($store_id);
    $store_code = $store_info['store_code'] ?? 'STORE';
    
    // Get last return number for this store
    $query = "SELECT MAX(CAST(SUBSTRING(return_number, LENGTH(?) + 3) AS UNSIGNED)) as last_number 
              FROM returns 
              WHERE store_id = ? AND return_number LIKE ?";
    $pattern = $store_code . '-R%';
    $stmt = $conn->prepare($query);
    $stmt->bind_param('sis', $store_code, $store_id, $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $last_number = 0;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_number = $row['last_number'] ?? 0;
    }
    
    $next_number = $last_number + 1;
    return $store_code . '-R' . str_pad($next_number, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify manager password for returns
 * @param string $password Password to verify
 * @return bool True if password is correct
 */
function verify_manager_password($password) {
    global $conn;
    
    $user_id = $_SESSION['user_id'];
    $query = "SELECT manager_password FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $password === $row['manager_password'];
    }
    
    return false;
}

// Translation functions
function getTranslation($key) {
    global $translations;
    
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    
    return $key;
}

function language_switcher() {
    global $available_languages;
    
    $current_lang = $_SESSION['lang'] ?? 'en';
    $output = '<div class="language-switcher">';
    
    foreach ($available_languages as $code => $language) {
        $active = ($current_lang == $code) ? 'active' : '';
        $output .= '<a href="?lang=' . $code . '" class="lang-flag ' . $active . '" title="' . $language['name'] . '">';
        $output .= '<span class="flag-icon flag-icon-' . $language['flag'] . '"></span>';
        $output .= '</a>';
    }
    
    $output .= '</div>';
    return $output;
}

// Error and notification functions
function showToast($title, $message, $type = 'success') {
    $_SESSION['toast'] = [
        'title' => $title,
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Define base URL for assets and links
 * @return string Base URL
 */
function get_base_url() {
//    return '/ets-inventory/';
     return '/ets_system_fashion/';
}

/**
 * Store-to-Store Transfer Functions
 */

/**
 * Check if user can perform store-to-store transfers
 * @return bool
 */
function can_do_store_transfers() {
    return is_admin() || is_inventory_manager() || is_transfer_manager();
}

/**
 * Get store inventory items for transfer
 * @param int $store_id Store ID
 * @param string $search Search term
 * @return array Items with stock
 */
function get_store_inventory_for_transfer($store_id, $search = '') {
    global $conn;
    
    $where_conditions = ["si.store_id = ?", "si.current_stock > 0", "i.status = 'active'"];
    $params = [$store_id];
    $param_types = 'i';
    
    if (!empty($search)) {
        $where_conditions[] = "(i.name LIKE ? OR i.item_code LIKE ? OR b.barcode LIKE ?)";
        $search_term = "%$search%";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
        $param_types .= 'sss';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT 
                i.id as item_id,
                i.name as item_name,
                i.item_code,
                b.id as barcode_id,
                b.barcode,
                si.current_stock,
                si.selling_price,
                si.cost_price,
                c.name as category_name
              FROM store_inventory si
              JOIN inventory_items i ON si.item_id = i.id
              JOIN barcodes b ON si.barcode_id = b.id
              LEFT JOIN categories c ON i.category_id = c.id
              WHERE $where_clause
              ORDER BY i.name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'item_id' => (int)$row['item_id'],
            'barcode_id' => (int)$row['barcode_id'],
            'item_name' => $row['item_name'],
            'item_code' => $row['item_code'],
            'barcode' => $row['barcode'],
            'current_stock' => (int)$row['current_stock'],
            'selling_price' => (float)$row['selling_price'],
            'cost_price' => (float)$row['cost_price'],
            'category_name' => $row['category_name']
        ];
    }
    
    return $items;
}

/**
 * Process store-to-store transfer
 * @param int $source_id Source store ID
 * @param int $dest_id Destination store ID
 * @param int $item_id Item ID
 * @param int $barcode_id Barcode ID
 * @param int $quantity Quantity to transfer
 * @return array Result with success status and message
 */
function process_store_to_store_transfer($source_id, $dest_id, $item_id, $barcode_id, $quantity) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Check source stock
        $check_stmt = $conn->prepare("SELECT current_stock, selling_price, cost_price FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ? FOR UPDATE");
        $check_stmt->bind_param('iii', $source_id, $item_id, $barcode_id);
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
        
        // Deduct from source
        $deduct_stmt = $conn->prepare("UPDATE store_inventory SET current_stock = current_stock - ? WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
        $deduct_stmt->bind_param('iiii', $quantity, $source_id, $item_id, $barcode_id);
        $deduct_stmt->execute();
        
        // Add to destination
        $dest_check_stmt = $conn->prepare("SELECT current_stock FROM store_inventory WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
        $dest_check_stmt->bind_param('iii', $dest_id, $item_id, $barcode_id);
        $dest_check_stmt->execute();
        $dest_check_result = $dest_check_stmt->get_result();
        
        if ($dest_check_result->num_rows > 0) {
            $update_dest_stmt = $conn->prepare("UPDATE store_inventory SET current_stock = current_stock + ? WHERE store_id = ? AND item_id = ? AND barcode_id = ?");
            $update_dest_stmt->bind_param('iiii', $quantity, $dest_id, $item_id, $barcode_id);
            $update_dest_stmt->execute();
        } else {
            $insert_dest_stmt = $conn->prepare("INSERT INTO store_inventory (store_id, item_id, barcode_id, current_stock, selling_price, cost_price) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_dest_stmt->bind_param('iiiidd', $dest_id, $item_id, $barcode_id, $quantity, $source_data['selling_price'], $source_data['cost_price']);
            $insert_dest_stmt->execute();
        }
        
        // Ensure store assignment exists
        $assignment_stmt = $conn->prepare("INSERT INTO store_item_assignments (store_id, item_id, assigned_by, notes) VALUES (?, ?, ?, 'Auto-assigned via store-to-store transfer') ON DUPLICATE KEY UPDATE is_active = 1");
        $user_id = $_SESSION['user_id'];
        $assignment_stmt->bind_param('iii', $dest_id, $item_id, $user_id);
        $assignment_stmt->execute();
        
        // Log transfer
        $log_stmt = $conn->prepare("INSERT INTO stock_transfers (item_id, source, destination, quantity, transferred_by, transferred_at) VALUES (?, ?, ?, ?, ?, NOW())");
        
        $source_name_stmt = $conn->prepare("SELECT name FROM stores WHERE id = ?");
        $source_name_stmt->bind_param('i', $source_id);
        $source_name_stmt->execute();
        $source_name = $source_name_stmt->get_result()->fetch_assoc()['name'];
        
        $dest_name_stmt = $conn->prepare("SELECT name FROM stores WHERE id = ?");
        $dest_name_stmt->bind_param('i', $dest_id);
        $dest_name_stmt->execute();
        $dest_name = $dest_name_stmt->get_result()->fetch_assoc()['name'];
        
        $username = $_SESSION['username'] ?? 'system';
        $log_stmt->bind_param('issis', $item_id, $source_name, $dest_name, $quantity, $username);
        $log_stmt->execute();
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "Successfully transferred {$quantity} items from {$source_name} to {$dest_name}"
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Store-to-store transfer error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?> 
