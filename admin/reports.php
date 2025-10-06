<?php
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';
require_once '../includes/db.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Get report type from query string - default to sales_per_store
$report_type = isset($_GET['type']) ? $_GET['type'] : 'sales_per_store';
// Helper: Get date range with time support
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_time = isset($_GET['start_time']) ? $_GET['start_time'] : '00:00';
$end_time = isset($_GET['end_time']) ? $_GET['end_time'] : '23:59';

// Combine date and time for database queries
$start_datetime = $start_date . ' ' . $start_time . ':00';
$end_datetime = $end_date . ' ' . $end_time . ':59';

// Helper: Format currency
function fmt($n) { return number_format($n, 2); }

// Function to get inventory low stock items
function getLowStockItems($conn) {
    $query = "SELECT i.*, c.name as category_name
              FROM inventory_items i
              LEFT JOIN categories c ON i.category_id = c.id
              WHERE i.current_stock <= i.minimum_stock AND i.status = 'active'
              ORDER BY i.current_stock ASC";
    
    $result = $conn->query($query);
    $items = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    return $items;
}

// Function to get inventory transactions
function getInventoryTransactions($conn, $start_date, $end_date) {
    // Add time component to make end_date inclusive (set to end of day)
    $end_date_inclusive = $end_date . ' 23:59:59';
    
    $query = "SELECT t.*, i.name as item_name, i.item_code, u.username
              FROM inventory_transactions t
              LEFT JOIN inventory_items i ON t.item_id = i.id
              LEFT JOIN users u ON t.user_id = u.id
              WHERE t.transaction_date BETWEEN ? AND ? 
              ORDER BY t.transaction_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start_date, $end_date_inclusive);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
    
    return $transactions;
}

// Invoice Items with Container
function getInvoiceItemsWithContainer($conn, $start_datetime, $end_datetime, $store_id = '', $category_id = '', $subcategory_id = '', $container_id = '') {
    $where = ["inv.payment_status = 'paid'", "inv.created_at BETWEEN ? AND ?"];
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    
    if (!empty($store_id)) { $where[] = "inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
    if (!empty($category_id)) { $where[] = "ii.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
    if (!empty($subcategory_id)) { $where[] = "ii.subcategory_id = ?"; $params[] = $subcategory_id; $types .= 'i'; }
    if (!empty($container_id)) { $where[] = "ii.container_id = ?"; $params[] = $container_id; $types .= 'i'; }
    
    $sql = "SELECT
                inv.invoice_number,
                inv.created_at as invoice_date,
                s.name as store_name,
                ii.item_code,
                ii.name as item_name,
                cont.container_number,
                it.quantity,
                it.unit_price,
                it.total_price
            FROM invoices inv
            JOIN stores s ON inv.store_id = s.id
            JOIN invoice_items it ON inv.id = it.invoice_id
            JOIN inventory_items ii ON it.item_id = ii.id
            LEFT JOIN containers cont ON ii.container_id = cont.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY inv.created_at DESC, inv.id DESC, it.id ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('SQL Error in getInvoiceItemsWithContainer: ' . $conn->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}


// Daily Sales
function getDailySales($conn, $start_datetime, $end_datetime, $store_id = '', $category_id = '', $subcategory_id = '') {
    // If category or subcategory filtering is needed, use a different approach
    if ($category_id || $subcategory_id) {
        // When filtering by category/subcategory, we need to join with items but avoid Cartesian product
        $sql = "SELECT DATE(inv.created_at) as sale_date, 
                       SUM(DISTINCT inv.total_amount) as total_sales
                FROM invoices inv
                JOIN invoice_items it ON inv.id = it.invoice_id
                JOIN inventory_items ii ON it.item_id = ii.id
                WHERE inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?";
        $params = [$start_datetime, $end_datetime];
        $types = 'ss';
        if ($store_id) { $sql .= " AND inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
        if ($category_id) { $sql .= " AND ii.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
        if ($subcategory_id) { $sql .= " AND ii.subcategory_id = ?"; $params[] = $subcategory_id; $types .= 'i'; }
        $sql .= " GROUP BY sale_date ORDER BY sale_date DESC";
    } else {
        // Simple query without item joins when no category filtering is needed
        $sql = "SELECT DATE(inv.created_at) as sale_date, 
                       SUM(inv.total_amount) as total_sales
                FROM invoices inv
                WHERE inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?";
        $params = [$start_datetime, $end_datetime];
        $types = 'ss';
        if ($store_id) { $sql .= " AND inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
        $sql .= " GROUP BY sale_date ORDER BY sale_date DESC";
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getDailySales: " . $conn->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Daily Sales per Store
function getDailySalesPerStore($conn, $start_datetime, $end_datetime, $store_id = '', $category_id = '', $subcategory_id = '', $salesperson_id = '') {
    // If category or subcategory filtering is needed, use a different approach
    if ($category_id || $subcategory_id) {
        // When filtering by category/subcategory, we need to join with items but avoid Cartesian product
        $sql = "SELECT DATE(inv.created_at) as sale_date, s.name as store_name, 
                       SUM(DISTINCT inv.total_amount) as total_sales
                FROM invoices inv
                JOIN stores s ON inv.store_id = s.id
                JOIN invoice_items it ON inv.id = it.invoice_id
                JOIN inventory_items ii ON it.item_id = ii.id
                WHERE inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?";
        $params = [$start_datetime, $end_datetime];
        $types = 'ss';
        if ($store_id) { $sql .= " AND inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
        if ($category_id) { $sql .= " AND ii.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
        if ($subcategory_id) { $sql .= " AND ii.subcategory_id = ?"; $params[] = $subcategory_id; $types .= 'i'; }
        $sql .= " GROUP BY sale_date, store_name ORDER BY sale_date DESC, store_name";
    } else {
        // Simple query without item joins when no category filtering is needed
        $sql = "SELECT DATE(inv.created_at) as sale_date, s.name as store_name, 
                       SUM(inv.total_amount) as total_sales
                FROM invoices inv
                JOIN stores s ON inv.store_id = s.id
                WHERE inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?";
        $params = [$start_datetime, $end_datetime];
        $types = 'ss';
        if ($store_id) { $sql .= " AND inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
        $sql .= " GROUP BY sale_date, store_name ORDER BY sale_date DESC, store_name";
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getDailySalesPerStore: " . $conn->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Daily Sales per Store per Item
function getDailySalesPerStoreItem($conn, $start_datetime, $end_datetime, $store_id = '', $category_id = '', $subcategory_id = '') {
    $sql = "SELECT DATE(inv.created_at) as sale_date, s.name as store_name, ii.name as item_name, ii.item_code, SUM(it.quantity) as qty_sold, SUM(it.total_price) as total_sales
            FROM invoices inv
            JOIN stores s ON inv.store_id = s.id
            JOIN invoice_items it ON inv.id = it.invoice_id
            JOIN inventory_items ii ON it.item_id = ii.id
            WHERE inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?";
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    if ($store_id) { $sql .= " AND inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
    if ($category_id) { $sql .= " AND ii.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
    if ($subcategory_id) { $sql .= " AND ii.subcategory_id = ?"; $params[] = $subcategory_id; $types .= 'i'; }
    $sql .= " GROUP BY sale_date, store_name, item_name, item_code ORDER BY sale_date DESC, store_name, item_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Daily Sales per Store per Category
function getDailySalesPerStoreCategory($conn, $start_datetime, $end_datetime, $store_id = '', $category_id = '', $subcategory_id = '') {
    $sql = "SELECT DATE(inv.created_at) as sale_date, s.name as store_name, c.name as category_name, SUM(it.quantity) as qty_sold, SUM(it.total_price) as total_sales
            FROM invoices inv
            JOIN stores s ON inv.store_id = s.id
            JOIN invoice_items it ON inv.id = it.invoice_id
            JOIN inventory_items ii ON it.item_id = ii.id
            JOIN categories c ON ii.category_id = c.id
            WHERE inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?";
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    if ($store_id) { $sql .= " AND inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
    if ($category_id) { $sql .= " AND ii.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
    if ($subcategory_id) { $sql .= " AND ii.subcategory_id = ?"; $params[] = $subcategory_id; $types .= 'i'; }
    $sql .= " GROUP BY sale_date, store_name, category_name ORDER BY sale_date DESC, store_name, category_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Daily Sales per Store per Subcategory
function getDailySalesPerStoreSubcategory($conn, $start_datetime, $end_datetime, $store_id = '', $category_id = '', $subcategory_id = '') {
    $sql = "SELECT DATE(inv.created_at) as sale_date, s.name as store_name, sc.name as subcategory_name, SUM(it.quantity) as qty_sold, SUM(it.total_price) as total_sales
            FROM invoices inv
            JOIN stores s ON inv.store_id = s.id
            JOIN invoice_items it ON inv.id = it.invoice_id
            JOIN inventory_items ii ON it.item_id = ii.id
            JOIN subcategories sc ON ii.subcategory_id = sc.id
            WHERE inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?";
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    if ($store_id) { $sql .= " AND inv.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
    if ($category_id) { $sql .= " AND ii.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
    if ($subcategory_id) { $sql .= " AND ii.subcategory_id = ?"; $params[] = $subcategory_id; $types .= 'i'; }
    $sql .= " GROUP BY sale_date, store_name, subcategory_name ORDER BY sale_date DESC, store_name, subcategory_name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Purchase Orders Report
function getPurchaseOrders($conn, $start_date, $end_date) {
    $sql = "SELECT po.po_number, po.order_date, s.name as supplier_name, po.status, po.total_amount
            FROM purchase_orders po
            JOIN suppliers s ON po.supplier_id = s.id
            WHERE po.order_date BETWEEN ? AND ?
            ORDER BY po.order_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Inventory Report
function getInventoryReport($conn, $store_id = '', $category_id = '', $subcategory_id = '') {
    $sql = "SELECT ii.name as item_name, ii.item_code, c.name as category_name, sc.name as subcategory_name, si.current_stock, s.name as store_name
            FROM store_inventory si
            JOIN inventory_items ii ON si.item_id = ii.id
            LEFT JOIN categories c ON ii.category_id = c.id
            LEFT JOIN subcategories sc ON ii.subcategory_id = sc.id
            JOIN stores s ON si.store_id = s.id WHERE 1=1";
    $params = [];
    $types = '';
    if ($store_id) { $sql .= " AND si.store_id = ?"; $params[] = $store_id; $types .= 'i'; }
    if ($category_id) { $sql .= " AND ii.category_id = ?"; $params[] = $category_id; $types .= 'i'; }
    if ($subcategory_id) { $sql .= " AND ii.subcategory_id = ?"; $params[] = $subcategory_id; $types .= 'i'; }
    $sql .= " ORDER BY ii.name, s.name";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Profits & Expenses Report - Store Summary
function getProfitsExpensesReport($conn, $start_datetime, $end_datetime, $store_id = '') {
    // First, get revenue data using the same method as daily sales report
    $sql = "SELECT 
                s.id as store_id,
                s.name as store_name,
                s.store_code,
                COALESCE(SUM(inv.total_amount), 0) as total_revenue
            FROM stores s
            LEFT JOIN invoices inv ON s.id = inv.store_id AND inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?
            WHERE s.status = 'active'";
    
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    
    if ($store_id) { 
        $sql .= " AND s.id = ?"; 
        $params[] = $store_id; 
        $types .= 'i'; 
    }
    
    $sql .= " GROUP BY s.id ORDER BY s.name";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getProfitsExpensesReport: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stores_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Now get cost of goods data separately
    $cost_sql = "SELECT 
                    s.id as store_id,
                    COALESCE(SUM(inv_items.quantity * ii.base_price), 0) as total_cost_of_goods
                 FROM stores s
                 LEFT JOIN invoices inv ON s.id = inv.store_id AND inv.payment_status = 'paid' AND inv.created_at BETWEEN ? AND ?
                 LEFT JOIN invoice_items inv_items ON inv.id = inv_items.invoice_id
                 LEFT JOIN inventory_items ii ON inv_items.item_id = ii.id
                 WHERE s.status = 'active'";
    
    if ($store_id) {
        $cost_sql .= " AND s.id = ?";
    }
    
    $cost_sql .= " GROUP BY s.id";
    
    $cost_stmt = $conn->prepare($cost_sql);
    if ($cost_stmt) {
        $cost_stmt->bind_param($types, ...$params);
        $cost_stmt->execute();
        $cost_data = $cost_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Merge cost data with revenue data
        $cost_lookup = [];
        foreach ($cost_data as $cost) {
            $cost_lookup[$cost['store_id']] = $cost['total_cost_of_goods'];
        }
        
        foreach ($stores_data as &$store) {
            $store['total_cost_of_goods'] = $cost_lookup[$store['store_id']] ?? 0;
        }
    } else {
        // Fallback: set all costs to 0 if query fails
        foreach ($stores_data as &$store) {
            $store['total_cost_of_goods'] = 0;
        }
    }
    
    // Now get expenses for each store (if expenses table exists)
    $expenses_data = [];
    $check_expenses = $conn->query("SHOW TABLES LIKE 'expenses'");
    if ($check_expenses && $check_expenses->num_rows > 0) {
        $expenses_sql = "SELECT 
                            store_id,
                            SUM(amount) as total_expenses
                         FROM expenses 
                         WHERE date BETWEEN ? AND ?";
        
        if ($store_id) {
            $expenses_sql .= " AND store_id = ?";
        }
        
        $expenses_sql .= " GROUP BY store_id";
        
        $expenses_stmt = $conn->prepare($expenses_sql);
        if ($expenses_stmt) {
            if ($store_id) {
                $expenses_stmt->bind_param('ssi', $start_datetime, $end_datetime, $store_id);
            } else {
                $expenses_stmt->bind_param('ss', $start_datetime, $end_datetime);
            }
            $expenses_stmt->execute();
            $expenses_result = $expenses_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Convert to associative array for easy lookup
            foreach ($expenses_result as $expense) {
                $expenses_data[$expense['store_id']] = $expense['total_expenses'];
            }
        }
    }
    
    // Combine the data and calculate profits
    foreach ($stores_data as &$store) {
        $store['total_expenses'] = $expenses_data[$store['store_id']] ?? 0;
        $store['gross_profit'] = $store['total_revenue'] - $store['total_cost_of_goods'];
        $store['net_profit'] = $store['gross_profit'] - $store['total_expenses'];
        
        // Calculate profit margin (avoid division by zero)
        if ($store['total_revenue'] > 0) {
            $store['profit_margin_percentage'] = ($store['net_profit'] / $store['total_revenue']) * 100;
        } else {
            $store['profit_margin_percentage'] = 0;
        }
    }
    
    // Sort by net profit descending
    usort($stores_data, function($a, $b) {
        return $b['net_profit'] <=> $a['net_profit'];
    });
    
    return $stores_data;
}

// Sales per Store Summary Report with detailed metrics
function getSalesPerStoreSummary($conn, $start_date, $end_date, $store_id = '') {
    try {
        // Simple approach to avoid complex joins and potential errors
        $end_datetime = $end_date . ' 23:59:59';
        
        // Main sales query without complex subqueries
        $sql = "SELECT 
                    s.id as store_id,
                    s.name as store_name,
                    s.store_code,
                    COUNT(inv.id) as total_transactions,
                    COUNT(DISTINCT DATE(inv.created_at)) as active_days,
                    COALESCE(SUM(inv.total_amount), 0) as total_revenue,
                    COALESCE(AVG(inv.total_amount), 0) as avg_transaction_value,
                    MIN(inv.created_at) as first_sale,
                    MAX(inv.created_at) as last_sale,
                    COUNT(DISTINCT CASE WHEN inv.customer_name IS NOT NULL AND inv.customer_name != '' THEN inv.customer_name END) as unique_customers,
                    COALESCE(SUM(CASE WHEN inv.payment_method = 'cash' THEN inv.total_amount ELSE 0 END), 0) as cash_sales,
                    COALESCE(SUM(CASE WHEN inv.payment_method = 'card' THEN inv.total_amount ELSE 0 END), 0) as card_sales,
                    COALESCE(SUM(CASE WHEN inv.payment_method = 'mobile' THEN inv.total_amount ELSE 0 END), 0) as mobile_sales,
                    COALESCE(SUM(CASE WHEN inv.payment_method = 'credit' THEN inv.total_amount ELSE 0 END), 0) as credit_sales
                FROM stores s
                LEFT JOIN invoices inv ON s.id = inv.store_id 
                    AND inv.payment_status = 'paid' 
                    AND inv.created_at BETWEEN ? AND ?
                WHERE s.status = 'active'";
        
        $params = [$start_date, $end_datetime];
        $types = 'ss';
        
        if ($store_id) {
            $sql .= " AND s.id = ?";
            $params[] = $store_id;
            $types .= 'i';
        }
        
        $sql .= " GROUP BY s.id, s.name, s.store_code ORDER BY total_revenue DESC";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error in getSalesPerStoreSummary: " . $conn->error);
            return [];
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stores_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get total items sold for each store in a separate query
        foreach ($stores_data as &$store) {
            $items_sql = "SELECT COALESCE(SUM(inv_items.quantity), 0) as total_items_sold
                         FROM invoices inv
                         JOIN invoice_items inv_items ON inv.id = inv_items.invoice_id
                         WHERE inv.store_id = ? 
                           AND inv.payment_status = 'paid' 
                           AND inv.created_at BETWEEN ? AND ?";
            
            $items_stmt = $conn->prepare($items_sql);
            if ($items_stmt) {
                $items_stmt->bind_param('iss', $store['store_id'], $start_date, $end_datetime);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result()->fetch_assoc();
                $store['total_items_sold'] = $items_result['total_items_sold'] ?? 0;
            } else {
                $store['total_items_sold'] = 0;
            }
            
            // Get cost of goods for this store
            $cost_sql = "SELECT COALESCE(SUM(inv_items.quantity * COALESCE(ii.base_price, 0)), 0) as cost_of_goods
                        FROM invoices inv
                        JOIN invoice_items inv_items ON inv.id = inv_items.invoice_id
                        LEFT JOIN inventory_items ii ON inv_items.item_id = ii.id
                        WHERE inv.store_id = ? 
                          AND inv.payment_status = 'paid' 
                          AND inv.created_at BETWEEN ? AND ?";
            
            $cost_stmt = $conn->prepare($cost_sql);
            if ($cost_stmt) {
                $cost_stmt->bind_param('iss', $store['store_id'], $start_date, $end_datetime);
                $cost_stmt->execute();
                $cost_result = $cost_stmt->get_result()->fetch_assoc();
                $store['cost_of_goods'] = $cost_result['cost_of_goods'] ?? 0;
            } else {
                $store['cost_of_goods'] = 0;
            }
            
            // Calculate derived metrics
            $store['gross_profit'] = $store['total_revenue'] - $store['cost_of_goods'];
            
            if ($store['total_transactions'] > 0) {
                $store['items_per_transaction'] = round($store['total_items_sold'] / $store['total_transactions'], 2);
            } else {
                $store['items_per_transaction'] = 0;
            }
            
            if ($store['active_days'] > 0) {
                $store['daily_avg_revenue'] = round($store['total_revenue'] / $store['active_days'], 2);
                $store['daily_avg_transactions'] = round($store['total_transactions'] / $store['active_days'], 2);
            } else {
                $store['daily_avg_revenue'] = 0;
                $store['daily_avg_transactions'] = 0;
            }
            
            if ($store['total_revenue'] > 0) {
                $store['profit_margin_percentage'] = ($store['gross_profit'] / $store['total_revenue']) * 100;
            } else {
                $store['profit_margin_percentage'] = 0;
            }
        }
        
        return $stores_data;
        
    } catch (Exception $e) {
        error_log("Error in getSalesPerStoreSummary: " . $e->getMessage());
        return [];
    }
}

// Top Selling Items per Store
function getTopSellingItemsByStore($conn, $start_datetime, $end_datetime, $store_id = '', $limit = 10) {
    $sql = "SELECT 
                s.name as store_name,
                ii.name as item_name,
                ii.item_code,
                c.name as category_name,
                SUM(inv_items.quantity) as total_quantity_sold,
                SUM(inv_items.total_price) as total_revenue,
                AVG(inv_items.unit_price) as avg_selling_price,
                COUNT(DISTINCT inv.id) as transactions_count
            FROM stores s
            JOIN invoices inv ON s.id = inv.store_id
            JOIN invoice_items inv_items ON inv.id = inv_items.invoice_id
            JOIN inventory_items ii ON inv_items.item_id = ii.id
            LEFT JOIN categories c ON ii.category_id = c.id
            WHERE inv.payment_status = 'paid' 
                AND inv.created_at BETWEEN ? AND ?
                AND s.status = 'active'";
    
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    
    if ($store_id) {
        $sql .= " AND s.id = ?";
        $params[] = $store_id;
        $types .= 'i';
    }
    
    $sql .= " GROUP BY s.id, s.name, ii.id, ii.name, ii.item_code, c.name
              ORDER BY s.name, total_quantity_sold DESC";
    
    if ($limit > 0) {
        $sql .= " LIMIT ?";
        $params[] = $limit * 10; // Allow more results for multiple stores
        $types .= 'i';
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getTopSellingItemsByStore: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Store Performance Comparison
function getStorePerformanceComparison($conn, $start_date, $end_date) {
    $sql = "SELECT 
                s.name as store_name,
                s.store_code,
                DATE(inv.created_at) as sale_date,
                SUM(inv.total_amount) as daily_revenue,
                COUNT(inv.id) as daily_transactions,
                SUM(inv_items.quantity) as daily_items_sold
            FROM stores s
            LEFT JOIN invoices inv ON s.id = inv.store_id 
                AND inv.payment_status = 'paid' 
                AND inv.created_at BETWEEN ? AND ?
            LEFT JOIN invoice_items inv_items ON inv.id = inv_items.invoice_id
            WHERE s.status = 'active'
            GROUP BY s.id, s.name, s.store_code, DATE(inv.created_at)
            ORDER BY s.name, sale_date DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getStorePerformanceComparison: " . $conn->error);
        return [];
    }
    
    $end_datetime = $end_date . ' 23:59:59';
    $stmt->bind_param('ss', $start_date, $end_datetime);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Expenses per Store Report
function getExpensesPerStore($conn, $start_datetime, $end_datetime, $store_id = '') {
    $sql = "SELECT 
                e.expense_date,
                s.name as store_name,
                s.store_code,
                e.expense_number,
                e.category,
                e.description,
                e.amount,
                e.status,
                u.full_name as added_by_name,
                a.full_name as approved_by_name
            FROM expenses e
            JOIN stores s ON e.store_id = s.id
            LEFT JOIN users u ON e.added_by = u.id
            LEFT JOIN users a ON e.approved_by = a.id
            WHERE e.expense_date BETWEEN DATE(?) AND DATE(?)
            AND s.status = 'active'";
    
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    
    if ($store_id) {
        $sql .= " AND e.store_id = ?";
        $params[] = $store_id;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY e.expense_date DESC, s.name, e.expense_number";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getExpensesPerStore: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Sales per Invoice Report
function getSalesPerInvoice($conn, $start_datetime, $end_datetime, $store_id = '', $salesperson_id = '') {
    $sql = "SELECT 
                i.invoice_number,
                i.created_at,
                s.name as store_name,
                s.store_code,
                i.customer_name,
                i.customer_phone,
                i.subtotal,
                i.tax_amount,
                i.discount_amount,
                i.total_amount,
                i.amount_paid,
                CASE 
                    WHEN i.payment_method = 'cash' THEN i.total_amount
                    WHEN i.payment_method = 'cash_mobile' THEN COALESCE(i.cash_amount, 0)
                    ELSE 0 
                END as cash_amount,
                CASE 
                    WHEN i.payment_method = 'mobile' THEN i.total_amount
                    WHEN i.payment_method = 'cash_mobile' THEN COALESCE(i.mobile_amount, 0)
                    ELSE 0 
                END as mobile_amount,
                i.change_due,
                i.payment_method,
                i.payment_status,
                i.status,
                u.full_name as sales_person_name,
                COUNT(ii.id) as total_items
            FROM invoices i
            JOIN stores s ON i.store_id = s.id
            LEFT JOIN users u ON i.sales_person_id = u.id
            LEFT JOIN invoice_items ii ON i.id = ii.invoice_id
            WHERE i.created_at BETWEEN ? AND ?
            AND s.status = 'active'";
    
    $params = [$start_datetime, $end_datetime];
    $types = 'ss';
    
    if ($store_id) {
        $sql .= " AND i.store_id = ?";
        $params[] = $store_id;
        $types .= 'i';
    }
    
    if ($salesperson_id) {
        $sql .= " AND i.sales_person_id = ?";
        $params[] = $salesperson_id;
        $types .= 'i';
    }
    
    $sql .= " GROUP BY i.id ORDER BY i.created_at DESC, i.invoice_number";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getSalesPerInvoice: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Sales vs Expenses Report
function getSalesVsExpenses($conn, $start_datetime, $end_datetime, $store_id = '', $salesperson_id = '') {
    $data = [];
    
    // Get sales data
    $sales_sql = "SELECT 
                    s.id as store_id,
                    s.name as store_name,
                    u.id as salesperson_id,
                    u.full_name as salesperson_name,
                    SUM(i.total_amount) as total_sales,
                    SUM(CASE 
                        WHEN i.payment_method = 'cash' THEN i.total_amount
                        WHEN i.payment_method = 'cash_mobile' THEN COALESCE(i.cash_amount, 0)
                        ELSE 0 
                    END) as total_cash_amount,
                    SUM(CASE 
                        WHEN i.payment_method = 'mobile' THEN i.total_amount
                        WHEN i.payment_method = 'cash_mobile' THEN COALESCE(i.mobile_amount, 0)
                        ELSE 0 
                    END) as total_mobile_amount,
                    COUNT(i.id) as total_invoices
                  FROM invoices i
                  JOIN stores s ON i.store_id = s.id
                  LEFT JOIN users u ON i.sales_person_id = u.id
                  WHERE i.created_at BETWEEN ? AND ?
                  AND s.status = 'active'";
    
    $sales_params = [$start_datetime, $end_datetime];
    $sales_types = 'ss';
    
    if ($store_id) {
        $sales_sql .= " AND i.store_id = ?";
        $sales_params[] = $store_id;
        $sales_types .= 'i';
    }
    
    if ($salesperson_id) {
        $sales_sql .= " AND i.sales_person_id = ?";
        $sales_params[] = $salesperson_id;
        $sales_types .= 'i';
    }
    
    $sales_sql .= " GROUP BY s.id, u.id ORDER BY s.name, u.full_name";
    
    $sales_stmt = $conn->prepare($sales_sql);
    if (!$sales_stmt) {
        error_log("SQL Error in getSalesVsExpenses (sales): " . $conn->error);
        return [];
    }
    
    $sales_stmt->bind_param($sales_types, ...$sales_params);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();
    
    // Get expenses data
    $expenses_sql = "SELECT 
                       s.id as store_id,
                       s.name as store_name,
                       u.id as salesperson_id,
                       u.full_name as salesperson_name,
                       SUM(e.amount) as total_expenses,
                       COUNT(e.id) as total_expense_count
                     FROM expenses e
                     JOIN stores s ON e.store_id = s.id
                     LEFT JOIN users u ON e.added_by = u.id
                     WHERE e.expense_date BETWEEN DATE(?) AND DATE(?)
                     AND s.status = 'active'";
    
    $expenses_params = [$start_datetime, $end_datetime];
    $expenses_types = 'ss';
    
    if ($store_id) {
        $expenses_sql .= " AND e.store_id = ?";
        $expenses_params[] = $store_id;
        $expenses_types .= 'i';
    }
    
    if ($salesperson_id) {
        $expenses_sql .= " AND e.added_by = ?";
        $expenses_params[] = $salesperson_id;
        $expenses_types .= 'i';
    }
    
    $expenses_sql .= " GROUP BY s.id, u.id ORDER BY s.name, u.full_name";
    
    $expenses_stmt = $conn->prepare($expenses_sql);
    if (!$expenses_stmt) {
        error_log("SQL Error in getSalesVsExpenses (expenses): " . $conn->error);
        return [];
    }
    
    $expenses_stmt->bind_param($expenses_types, ...$expenses_params);
    $expenses_stmt->execute();
    $expenses_result = $expenses_stmt->get_result();
    
    // Combine sales and expenses data
    $combined_data = [];
    
    // Process sales data
    while ($row = $sales_result->fetch_assoc()) {
        $key = $row['store_id'] . '_' . ($row['salesperson_id'] ?? 'null');
        $combined_data[$key] = [
            'store_id' => $row['store_id'],
            'store_name' => $row['store_name'],
            'salesperson_id' => $row['salesperson_id'],
            'salesperson_name' => $row['salesperson_name'] ?? 'Unknown',
            'total_sales' => (float)$row['total_sales'],
            'total_cash_amount' => (float)$row['total_cash_amount'],
            'total_mobile_amount' => (float)$row['total_mobile_amount'],
            'total_invoices' => (int)$row['total_invoices'],
            'total_expenses' => 0.0,
            'total_expense_count' => 0
        ];
    }
    
    // Process expenses data
    while ($row = $expenses_result->fetch_assoc()) {
        $key = $row['store_id'] . '_' . ($row['salesperson_id'] ?? 'null');
        
        if (isset($combined_data[$key])) {
            $combined_data[$key]['total_expenses'] = (float)$row['total_expenses'];
            $combined_data[$key]['total_expense_count'] = (int)$row['total_expense_count'];
        } else {
            $combined_data[$key] = [
                'store_id' => $row['store_id'],
                'store_name' => $row['store_name'],
                'salesperson_id' => $row['salesperson_id'],
                'salesperson_name' => $row['salesperson_name'] ?? 'Unknown',
                'total_sales' => 0.0,
                'total_cash_amount' => 0.0,
                'total_mobile_amount' => 0.0,
                'total_invoices' => 0,
                'total_expenses' => (float)$row['total_expenses'],
                'total_expense_count' => (int)$row['total_expense_count']
            ];
        }
    }
    
    return array_values($combined_data);
}

// Containers Report
function getContainersReport($conn, $start_date, $end_date) {
    // Check if containers table exists
    $check_containers = $conn->query("SHOW TABLES LIKE 'containers'");
    if (!$check_containers || $check_containers->num_rows === 0) {
        error_log("Containers table does not exist");
        return [];
    }
    
    // Check if container_financial_summary table exists
    $check_financial = $conn->query("SHOW TABLES LIKE 'container_financial_summary'");
    $has_financial_table = ($check_financial && $check_financial->num_rows > 0);
    
    if ($has_financial_table) {
        // Full query with financial summary
        $sql = "SELECT 
                    c.id,
                    c.container_number,
                    c.supplier_id,
                    s.name as supplier_name,
                    c.total_weight_kg,
                    c.total_cost as base_cost,
                    c.shipment_cost,
                    c.profit_margin_percentage,
                    c.status,
                    c.arrival_date,
                    c.created_at,
                    COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) as total_all_costs,
                    COALESCE(cfs.expected_selling_total, 0) as expected_selling_total,
                    COALESCE(cfs.actual_selling_total, 0) as actual_selling_total,
                    COALESCE(cfs.actual_profit, 0) as actual_profit,
                    CASE 
                        WHEN COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) > 0 
                        THEN (COALESCE(cfs.actual_profit, 0) / COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0)))) * 100
                        ELSE 0 
                    END as actual_profit_margin
                FROM containers c
                LEFT JOIN suppliers s ON c.supplier_id = s.id
                LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
                WHERE c.created_at BETWEEN ? AND ?
                ORDER BY c.created_at DESC";
    } else {
        // Simplified query without financial summary table
        $sql = "SELECT 
                    c.id,
                    c.container_number,
                    c.supplier_id,
                    s.name as supplier_name,
                    c.total_weight_kg,
                    c.total_cost as base_cost,
                    c.shipment_cost,
                    c.profit_margin_percentage,
                    c.status,
                    c.arrival_date,
                    c.created_at,
                    (c.total_cost + COALESCE(c.shipment_cost, 0)) as total_all_costs,
                    0 as expected_selling_total,
                    0 as actual_selling_total,
                    0 as actual_profit,
                    0 as actual_profit_margin
                FROM containers c
                LEFT JOIN suppliers s ON c.supplier_id = s.id
                WHERE c.created_at BETWEEN ? AND ?
                ORDER BY c.created_at DESC";
    }
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL Error in getContainersReport: " . $conn->error);
        return [];
    }
    
    $end_datetime = $end_date . ' 23:59:59';
    $stmt->bind_param('ss', $start_date, $end_datetime);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo getTranslation('reports.title'); ?></h1>
</div>

<!-- Report Navigation -->
<div class="card mb-4">
    <div class="card-body">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo ($report_type == 'sales_per_store') ? 'active' : ''; ?>" href="?type=sales_per_store">
                    <i class="bi bi-shop me-1"></i> Daily Sales per Store
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($report_type == 'invoice_items_with_container') ? 'active' : ''; ?>" href="?type=invoice_items_with_container">
                    <i class="bi bi-receipt me-1"></i> Invoice Items with Container
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($report_type == 'sales_per_item') ? 'active' : ''; ?>" href="?type=sales_per_item">
                    <i class="bi bi-box me-1"></i> Daily Sales per Store per Item
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($report_type == 'expenses_per_store') ? 'active' : ''; ?>" href="?type=expenses_per_store">
                    <i class="bi bi-receipt-cutoff me-1"></i> Expenses per Store
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($report_type == 'sales_per_invoice') ? 'active' : ''; ?>" href="?type=sales_per_invoice">
                    <i class="bi bi-file-earmark-text me-1"></i> Sales per Invoice
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($report_type == 'sales_vs_expenses') ? 'active' : ''; ?>" href="?type=sales_vs_expenses">
                    <i class="bi bi-graph-up me-1"></i> Sales vs Expenses
                </a>
            </li>
            
            <!-- Hidden Reports - Do not remove, only hide -->
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'inventory') ? 'active' : ''; ?>" href="?type=inventory">
                    <i class="bi bi-box-seam me-1"></i> <?php echo getTranslation('reports.inventory_report'); ?>
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'low_stock') ? 'active' : ''; ?>" href="?type=low_stock">
                    <i class="bi bi-exclamation-triangle me-1"></i> <?php echo getTranslation('reports.low_stock_report'); ?>
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'daily_sales') ? 'active' : ''; ?>" href="?type=daily_sales">
                    <i class="bi bi-calendar-day me-1"></i> Daily Sales
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'sales_per_category') ? 'active' : ''; ?>" href="?type=sales_per_category">
                    <i class="bi bi-tags me-1"></i> Daily Sales per Store per Category
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'sales_per_subcategory') ? 'active' : ''; ?>" href="?type=sales_per_subcategory">
                    <i class="bi bi-tag me-1"></i> Daily Sales per Store per Subcategory
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'purchase_orders') ? 'active' : ''; ?>" href="?type=purchase_orders">
                    <i class="bi bi-receipt me-1"></i> Purchase Orders
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'inventory_report') ? 'active' : ''; ?>" href="?type=inventory_report">
                    <i class="bi bi-archive me-1"></i> Inventory Report
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'profits_expenses') ? 'active' : ''; ?>" href="?type=profits_expenses">
                    <i class="bi bi-graph-up-arrow me-1"></i> Store Profits
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'containers') ? 'active' : ''; ?>" href="?type=containers">
                    <i class="bi bi-box-seam me-1"></i> Containers Report
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'store_summary') ? 'active' : ''; ?>" href="?type=store_summary">
                    <i class="bi bi-graph-up-arrow me-1"></i> Store Sales Summary
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'top_items_store') ? 'active' : ''; ?>" href="?type=top_items_store">
                    <i class="bi bi-trophy me-1"></i> Top Items by Store
                </a>
            </li>
            <li class="nav-item" style="display: none;">
                <a class="nav-link <?php echo ($report_type == 'store_comparison') ? 'active' : ''; ?>" href="?type=store_comparison">
                    <i class="bi bi-bar-chart me-1"></i> Store Performance
                </a>
            </li>
        </ul>
    </div>
</div>

<?php
$stores = get_all_stores();
$categories = get_all_categories();
$selected_store = isset($_GET['store_id']) ? $_GET['store_id'] : '';
$salespersons = $selected_store ? get_salespersons_by_store($selected_store) : get_all_salespersons();
$selected_category = isset($_GET['category_id']) ? $_GET['category_id'] : '';
$selected_subcategory = isset($_GET['subcategory_id']) ? $_GET['subcategory_id'] : '';
$selected_salesperson = isset($_GET['salesperson_id']) ? $_GET['salesperson_id'] : '';
$selected_container = isset($_GET['container_id']) ? $_GET['container_id'] : '';
$subcategories = $selected_category ? get_subcategories($selected_category) : [];
?>
<?php if (in_array($report_type, ['sales_per_store', 'sales_per_item', 'expenses_per_store', 'sales_per_invoice', 'sales_vs_expenses', 'invoice_items_with_container'])): ?>
<!-- Date and Filter Bar for Sales Reports -->
<div class="card mb-4">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
      <div class="col-md-2">
        <label class="form-label">Start Date</label>
        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label">Start Time</label>
        <input type="time" class="form-control" name="start_time" value="<?php echo htmlspecialchars($start_time); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">End Date</label>
        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
      </div>
      <div class="col-md-1">
        <label class="form-label">End Time</label>
        <input type="time" class="form-control" name="end_time" value="<?php echo htmlspecialchars($end_time); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Store</label>
        <select class="form-select" name="store_id" onchange="updateSalesPersons(this.value)">
          <option value="">All Stores</option>
          <?php foreach ($stores as $store): ?>
            <option value="<?php echo $store['id']; ?>" <?php if ($selected_store == $store['id']) echo 'selected'; ?>><?php echo htmlspecialchars($store['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Sales Person</label>
        <select class="form-select" name="salesperson_id">
          <option value="">All Sales Persons</option>
          <?php foreach ($salespersons as $sp): ?>
            <option value="<?php echo $sp['id']; ?>" <?php if ($selected_salesperson == $sp['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($sp['full_name'] . ($sp['store_name'] ? ' (' . $sp['store_name'] . ')' : '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Category</label>
        <select class="form-select" name="category_id" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php if ($selected_category == $cat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php
        $containers_list = [];
        $containers_q = $conn->query("SELECT id, container_number FROM containers ORDER BY container_number DESC");
        if ($containers_q) { while ($c = $containers_q->fetch_assoc()) { $containers_list[] = $c; } }
        $selected_container = isset($_GET['container_id']) ? $_GET['container_id'] : '';
      ?>
      <div class="col-md-2">
        <label class="form-label">Container</label>
        <select class="form-select" name="container_id">
          <option value="">All Containers</option>
          <?php foreach ($containers_list as $cont): ?>
            <option value="<?php echo $cont['id']; ?>" <?php if ($selected_container == $cont['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cont['container_number']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Subcategory</label>
        <select class="form-select" name="subcategory_id">
          <option value="">All Subcategories</option>
          <?php foreach ($subcategories as $subcat): ?>
            <option value="<?php echo $subcat['id']; ?>" <?php if ($selected_subcategory == $subcat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($subcat['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1 d-grid">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
      </div>
    </form>
</div>
</div>
<?php endif; ?>

<?php if ($report_type === 'invoice_items_with_container'): ?>
<?php
    $rows = getInvoiceItemsWithContainer($conn, $start_datetime, $end_datetime, $selected_store, $selected_category, $selected_subcategory, $selected_container);
?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Invoice Items with Container</h5>
    <small class="text-muted">Date: <?php echo htmlspecialchars($start_date); ?> to <?php echo htmlspecialchars($end_date); ?></small>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped table-hover">
        <thead class="table-light">
          <tr>
            <th>Invoice #</th>
            <th>Date</th>
            <th>Store</th>
            <th>Item Code</th>
            <th>Item Name</th>
            <th>Container</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Unit Price</th>
            <th class="text-end">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-3">No data</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?php echo htmlspecialchars($r['invoice_number']); ?></td>
                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($r['invoice_date']))); ?></td>
                <td><?php echo htmlspecialchars($r['store_name']); ?></td>
                <td><?php echo htmlspecialchars($r['item_code']); ?></td>
                <td><?php echo htmlspecialchars($r['item_name']); ?></td>
                <td><?php echo htmlspecialchars($r['container_number'] ?? '-'); ?></td>
                <td class="text-end"><?php echo (int)$r['quantity']; ?></td>
                <td class="text-end">CFA <?php echo number_format((float)$r['unit_price'], 2); ?></td>
                <td class="text-end">CFA <?php echo number_format((float)$r['total_price'], 2); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Hidden Universal Filter Bar for other reports -->
<?php if (!in_array($report_type, ['sales_per_store', 'sales_per_item', 'expenses_per_store', 'sales_per_invoice', 'sales_vs_expenses'])): ?>
<div class="card mb-4" style="display: none;">
  <div class="card-body">
    <form method="get" class="row g-3 align-items-end">
      <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
      <div class="col-md-2">
        <label class="form-label">Start Date</label>
        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">End Date</label>
        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Store</label>
        <select class="form-select" name="store_id">
          <option value="">All Stores</option>
          <?php foreach ($stores as $store): ?>
            <option value="<?php echo $store['id']; ?>" <?php if ($selected_store == $store['id']) echo 'selected'; ?>><?php echo htmlspecialchars($store['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Category</label>
        <select class="form-select" name="category_id" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php if ($selected_category == $cat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Subcategory</label>
        <select class="form-select" name="subcategory_id">
          <option value="">All Subcategories</option>
          <?php foreach ($subcategories as $subcat): ?>
            <option value="<?php echo $subcat['id']; ?>" <?php if ($selected_subcategory == $subcat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($subcat['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (in_array($report_type, ['inventory', 'profits_expenses', 'containers'])): ?>
<!-- Date Range Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="type" value="<?php echo $report_type; ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo getTranslation('reports.start_date'); ?></label>
                <input type="text" class="form-control datepicker" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo getTranslation('reports.end_date'); ?></label>
                <input type="text" class="form-control datepicker" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i> <?php echo getTranslation('reports.generate_report'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($report_type === 'inventory'): ?>
<!-- Inventory Transactions Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo getTranslation('reports.inventory_transactions'); ?></h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('inventory_transactions.xls')">
            <i class="bi bi-download me-1"></i> <?php echo getTranslation('reports.export_excel', 'Export to Excel'); ?>
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="inventoryTransactionsTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('reports.date'); ?></th>
                        <th><?php echo getTranslation('reports.item_code'); ?></th>
                        <th><?php echo getTranslation('reports.item_name'); ?></th>
                        <th><?php echo getTranslation('reports.transaction_type'); ?></th>
                        <th><?php echo getTranslation('reports.quantity'); ?></th>
                        <th><?php echo getTranslation('reports.reference'); ?></th>
                        <th><?php echo getTranslation('reports.user'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $transactions = getInventoryTransactions($conn, $start_date, $end_date);
                    if (count($transactions) > 0):
                        foreach ($transactions as $transaction):
                            $type_class = $transaction['transaction_type'] === 'in' ? 'text-success' : 
                                         ($transaction['transaction_type'] === 'out' ? 'text-danger' : 'text-warning');
                            $reference = '';
                            if ($transaction['reference_type'] === 'purchase_order') {
                                $reference = 'PO: ' . $transaction['reference_id'];
                            } elseif ($transaction['reference_type'] === 'work_order') {
                                $reference = 'WO: ' . $transaction['reference_id'];
                            } else {
                                $reference = 'Manual';
                            }
                    ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['item_code']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['item_name']); ?></td>
                        <td><span class="<?php echo $type_class; ?>"><?php echo ucfirst($transaction['transaction_type']); ?></span></td>
                        <td><?php echo $transaction['quantity']; ?></td>
                        <td><?php echo $reference; ?></td>
                        <td><?php echo htmlspecialchars($transaction['username'] ?? 'Unknown'); ?></td>
                    </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <tr>
                        <td colspan="7" class="text-center"><?php echo getTranslation('reports.no_transactions'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($report_type === 'low_stock'): ?>
<!-- Low Stock Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo getTranslation('reports.low_stock_items'); ?></h5>
        <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('low_stock_items.xls')">
            <i class="bi bi-download me-1"></i> <?php echo getTranslation('reports.export_excel', 'Export to Excel'); ?>
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="lowStockTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('reports.item_code'); ?></th>
                        <th><?php echo getTranslation('reports.name'); ?></th>
                        <th><?php echo getTranslation('reports.category'); ?></th>
                        <th><?php echo getTranslation('reports.current_stock'); ?></th>
                        <th><?php echo getTranslation('reports.minimum_stock'); ?></th>
                        <th><?php echo getTranslation('reports.location'); ?></th>
                        <th><?php echo getTranslation('reports.status'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $low_stock_items = getLowStockItems($conn);
                    if (count($low_stock_items) > 0):
                        foreach ($low_stock_items as $item):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></td>
                        <td class="text-danger"><strong><?php echo $item['current_stock']; ?></strong></td>
                        <td><?php echo $item['minimum_stock']; ?></td>
                        <td><?php echo htmlspecialchars($item['location'] ?? ''); ?></td>
                        <td><span class="badge bg-<?php echo ($item['status'] === 'active') ? 'success' : 'secondary'; ?>"><?php echo $item['status']; ?></span></td>
                    </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <tr>
                        <td colspan="7" class="text-center"><?php echo getTranslation('reports.no_low_stock'); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<?php elseif ($report_type === 'daily_sales'): ?>
<!-- Daily Sales Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daily Sales</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('daily_sales.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('daily_sales')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="dailySalesTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getDailySales($conn, $start_datetime, $end_datetime, $selected_store, $selected_category, $selected_subcategory);
                    if ($rows) foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                            <td><?php echo fmt($row['total_sales']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'sales_per_store'): ?>
<!-- Daily Sales per Store Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daily Sales per Store</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('sales_per_store.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('sales_per_store')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="salesPerStoreTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getDailySalesPerStore($conn, $start_datetime, $end_datetime, $selected_store, $selected_category, $selected_subcategory);
                    if ($rows) foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                            <td><?php echo fmt($row['total_sales']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'sales_per_item'): ?>
<!-- Daily Sales per Store per Item Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daily Sales per Store per Item</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('sales_per_item.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('sales_per_item')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="salesPerItemTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Item</th>
                        <th>Item Code</th>
                        <th>Qty Sold</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getDailySalesPerStoreItem($conn, $start_datetime, $end_datetime, $selected_store, $selected_category, $selected_subcategory);
                    $total_qty_sold = 0;
                    $total_sales_amount = 0;
                    
                    if ($rows): 
                        foreach ($rows as $row): 
                            $total_qty_sold += (int)$row['qty_sold'];
                            $total_sales_amount += (float)$row['total_sales'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_code']); ?></td>
                            <td><?php echo (int)$row['qty_sold']; ?></td>
                            <td><?php echo fmt($row['total_sales']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totals Row -->
                    <tr class="table-warning fw-bold border-top border-2">
                        <td colspan="4" class="text-end"><strong>TOTALS:</strong></td>
                        <td><strong><?php echo number_format($total_qty_sold); ?></strong></td>
                        <td><strong><?php echo fmt($total_sales_amount); ?></strong></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>No sales data found for the selected criteria.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'expenses_per_store'): ?>
<!-- Expenses per Store Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Expenses per Store</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('expenses_per_store.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="expensesPerStoreTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Expense #</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Added By</th>
                        <th>Approved By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $expense_rows = getExpensesPerStore($conn, $start_datetime, $end_datetime, $selected_store);
                    $total_expenses = 0;
                    $approved_expenses = 0;
                    $pending_expenses = 0;
                    
                    if ($expense_rows): 
                        foreach ($expense_rows as $row): 
                            $total_expenses += (float)$row['amount'];
                            if ($row['status'] === 'approved') {
                                $approved_expenses += (float)$row['amount'];
                            } elseif ($row['status'] === 'pending') {
                                $pending_expenses += (float)$row['amount'];
                            }
                            
                            // Status badge styling
                            $status_class = '';
                            switch ($row['status']) {
                                case 'approved':
                                    $status_class = 'bg-success';
                                    break;
                                case 'pending':
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 'rejected':
                                    $status_class = 'bg-danger';
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                            }
                    ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($row['expense_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['expense_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo fmt($row['amount']); ?></td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['added_by_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['approved_by_name'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Summary Totals Row -->
                    <tr class="table-info fw-bold border-top border-2">
                        <td colspan="5" class="text-end"><strong>TOTALS:</strong></td>
                        <td><strong><?php echo fmt($total_expenses); ?></strong></td>
                        <td colspan="3" class="text-muted">
                            <small>
                                Approved: <?php echo fmt($approved_expenses); ?> | 
                                Pending: <?php echo fmt($pending_expenses); ?>
                            </small>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>No expenses found for the selected criteria.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'sales_per_invoice'): ?>
<!-- Sales per Invoice Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Sales per Invoice</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('sales_per_invoice.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="salesPerInvoiceTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Invoice #</th>
                        <th>Store</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Subtotal</th>
                        <th>Tax</th>
                        <th>Discount</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Cash Amount</th>
                        <th>Mobile Amount</th>
                        <th>Amount Paid</th>
                        <th>Change Due</th>
                        <th>Payment Status</th>
                        <th>Sales Person</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $invoice_rows = getSalesPerInvoice($conn, $start_datetime, $end_datetime, $selected_store, $selected_salesperson);
                    $total_subtotal = 0;
                    $total_tax = 0;
                    $total_discount = 0;
                    $total_amount = 0;
                    $total_invoices = 0;
                    
                    if ($invoice_rows): 
                        foreach ($invoice_rows as $row): 
                            $total_subtotal += (float)$row['subtotal'];
                            $total_tax += (float)$row['tax_amount'];
                            $total_discount += (float)$row['discount_amount'];
                            $total_amount += (float)$row['total_amount'];
                            $total_invoices++;
                            
                            // Payment status badge styling
                            $payment_status_class = '';
                            switch ($row['payment_status']) {
                                case 'paid':
                                    $payment_status_class = 'bg-success';
                                    break;
                                case 'partial':
                                    $payment_status_class = 'bg-warning text-dark';
                                    break;
                                case 'pending':
                                    $payment_status_class = 'bg-info';
                                    break;
                                case 'refunded':
                                    $payment_status_class = 'bg-danger';
                                    break;
                                default:
                                    $payment_status_class = 'bg-secondary';
                            }
                            
                            // Payment method styling
                            $payment_class = '';
                            switch ($row['payment_method']) {
                                case 'cash':
                                    $payment_class = 'text-success';
                                    break;
                                case 'card':
                                    $payment_class = 'text-primary';
                                    break;
                                case 'mobile':
                                    $payment_class = 'text-info';
                                    break;
                                case 'cash_mobile':
                                    $payment_class = 'text-success';
                                    break;
                                case 'credit':
                                    $payment_class = 'text-warning';
                                    break;
                            }
                    ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Walk-in'); ?></td>
                            <td><?php echo $row['total_items']; ?></td>
                            <td><?php echo fmt($row['subtotal']); ?></td>
                            <td><?php echo fmt($row['tax_amount']); ?></td>
                            <td><?php echo fmt($row['discount_amount']); ?></td>
                            <td><strong><?php echo fmt($row['total_amount']); ?></strong></td>
                            <td>
                                <?php if ($row['payment_method'] === 'cash_mobile'): ?>
                                    <span class="<?php echo $payment_class; ?>">Cash + Mobile</span>
                                <?php else: ?>
                                    <span class="<?php echo $payment_class; ?>"><?php echo ucfirst($row['payment_method']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo fmt($row['cash_amount'] ?? 0); ?></td>
                            <td><?php echo fmt($row['mobile_amount'] ?? 0); ?></td>
                            <td><?php echo fmt($row['amount_paid'] ?? 0); ?></td>
                            <td><?php echo fmt($row['change_due'] ?? 0); ?></td>
                            <td><span class="badge <?php echo $payment_status_class; ?>"><?php echo ucfirst($row['payment_status']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['sales_person_name'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Summary Totals Row -->
                    <tr class="table-warning fw-bold border-top border-2">
                        <td colspan="4" class="text-end"><strong>TOTALS (<?php echo $total_invoices; ?> invoices):</strong></td>
                        <td><strong><?php echo array_sum(array_column($invoice_rows, 'total_items')); ?></strong></td>
                        <td><strong><?php echo fmt($total_subtotal); ?></strong></td>
                        <td><strong><?php echo fmt($total_tax); ?></strong></td>
                        <td><strong><?php echo fmt($total_discount); ?></strong></td>
                        <td><strong><?php echo fmt($total_amount); ?></strong></td>
                        <td colspan="6"></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="16" class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>No invoices found for the selected criteria.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'sales_vs_expenses'): ?>
<!-- Sales vs Expenses Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Sales vs Expenses Analysis</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('sales_vs_expenses.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="salesVsExpensesTable">
                <thead class="table-light">
                    <tr>
                        <th>Store</th>
                        <th>Sales Person</th>
                        <th>Total Sales</th>
                        <th>Cash Amount</th>
                        <th>Mobile Amount</th>
                        <th>Total Expenses</th>
                        <th>Invoices</th>
                        <th>Expenses</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sales_expenses_rows = getSalesVsExpenses($conn, $start_datetime, $end_datetime, $selected_store, $selected_salesperson);
                    $total_sales = 0;
                    $total_cash_amount = 0;
                    $total_mobile_amount = 0;
                    $total_expenses = 0;
                    $total_invoices = 0;
                    $total_expense_count = 0;
                    
                    if ($sales_expenses_rows): 
                        foreach ($sales_expenses_rows as $row): 
                            $total_sales += $row['total_sales'];
                            $total_cash_amount += $row['total_cash_amount'];
                            $total_mobile_amount += $row['total_mobile_amount'];
                            $total_expenses += $row['total_expenses'];
                            $total_invoices += $row['total_invoices'];
                            $total_expense_count += $row['total_expense_count'];
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['store_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['salesperson_name']); ?></td>
                            <td><?php echo fmt($row['total_sales']); ?></td>
                            <td><?php echo fmt($row['total_cash_amount']); ?></td>
                            <td><?php echo fmt($row['total_mobile_amount']); ?></td>
                            <td><?php echo fmt($row['total_expenses']); ?></td>
                            <td><?php echo $row['total_invoices']; ?></td>
                            <td><?php echo $row['total_expense_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Summary Totals Row -->
                    <tr class="table-info fw-bold border-top border-2">
                        <td colspan="2" class="text-end"><strong>TOTALS:</strong></td>
                        <td><strong><?php echo fmt($total_sales); ?></strong></td>
                        <td><strong><?php echo fmt($total_cash_amount); ?></strong></td>
                        <td><strong><?php echo fmt($total_mobile_amount); ?></strong></td>
                        <td><strong><?php echo fmt($total_expenses); ?></strong></td>
                        <td><strong><?php echo $total_invoices; ?></strong></td>
                        <td><strong><?php echo $total_expense_count; ?></strong></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>No data found for the selected criteria.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<?php if ($sales_expenses_rows): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Sales</h6>
                        <h4><?php echo fmt($total_sales); ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-arrow-up-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total Expenses</h6>
                        <h4><?php echo fmt($total_expenses); ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-arrow-down-circle fs-1"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($report_type === 'sales_per_category'): ?>
<!-- Daily Sales per Store per Category Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daily Sales per Store per Category</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('sales_per_category.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('sales_per_category')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="salesPerCategoryTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Category</th>
                        <th>Qty Sold</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getDailySalesPerStoreCategory($conn, $start_date, $end_date, $selected_store, $selected_category, $selected_subcategory);
                    if ($rows) foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td><?php echo (int)$row['qty_sold']; ?></td>
                            <td><?php echo fmt($row['total_sales']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'sales_per_subcategory'): ?>
<!-- Daily Sales per Store per Subcategory Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daily Sales per Store per Subcategory</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('sales_per_subcategory.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('sales_per_subcategory')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="salesPerSubcategoryTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Subcategory</th>
                        <th>Qty Sold</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getDailySalesPerStoreSubcategory($conn, $start_date, $end_date, $selected_store, $selected_category, $selected_subcategory);
                    if ($rows) foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['subcategory_name']); ?></td>
                            <td><?php echo (int)$row['qty_sold']; ?></td>
                            <td><?php echo fmt($row['total_sales']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'purchase_orders'): ?>
<!-- Purchase Orders Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Purchase Orders</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('purchase_orders.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('purchase_orders')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="purchaseOrdersTable">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getPurchaseOrders($conn, $start_date, $end_date);
                    if ($rows) foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['po_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['order_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo fmt($row['total_amount']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'inventory_report'): ?>
<!-- Inventory Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Inventory Report</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('inventory_report.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('inventory_report')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="inventoryReportTable">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Item Code</th>
                        <th>Category</th>
                        <th>Subcategory</th>
                        <th>Current Stock</th>
                        <th>Store</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getInventoryReport($conn, $selected_store, $selected_category, $selected_subcategory);
                    if ($rows) foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['subcategory_name']); ?></td>
                            <td><?php echo (int)$row['current_stock']; ?></td>
                            <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'profits_expenses'): ?>
<!-- Profits & Expenses Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Store Profits & Expenses Summary</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('profits_expenses.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('profits_expenses')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="profitsExpensesTable">
                <thead class="table-light">
                    <tr>
                        <th>Store</th>
                        <th>Store Code</th>
                        <th>Total Revenue</th>
                        <th>Cost of Goods</th>
                        <th>Gross Profit</th>
                        <th>Operating Expenses</th>
                        <th>Net Profit</th>
                        <th>Profit Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getProfitsExpensesReport($conn, $start_date, $end_date, $selected_store);
                    if ($rows && count($rows) > 0): 
                        foreach ($rows as $row): 
                            $profit_class = $row['net_profit'] > 0 ? 'text-success' : 'text-danger';
                            $gross_profit_class = $row['gross_profit'] > 0 ? 'text-success' : 'text-danger';
                            $margin_class = $row['profit_margin_percentage'] > 20 ? 'text-success' : ($row['profit_margin_percentage'] > 10 ? 'text-warning' : 'text-danger');
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['store_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['store_code'] ?? 'N/A'); ?></td>
                            <td>$<?php echo fmt($row['total_revenue']); ?></td>
                            <td>$<?php echo fmt($row['total_cost_of_goods']); ?></td>
                            <td class="<?php echo $gross_profit_class; ?>">$<?php echo fmt($row['gross_profit']); ?></td>
                            <td>$<?php echo fmt($row['total_expenses']); ?></td>
                            <td class="<?php echo $profit_class; ?>"><strong>$<?php echo fmt($row['net_profit']); ?></strong></td>
                            <td class="<?php echo $margin_class; ?>"><strong><?php echo number_format($row['profit_margin_percentage'], 2); ?>%</strong></td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>No sales data found for the selected date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'containers'): ?>
<!-- Containers Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Containers Report</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('containers_report.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToPDF('containers_report')">
                <i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="containersTable">
                <thead class="table-light">
                    <tr>
                        <th>Container #</th>
                        <th>Supplier</th>
                        <th>Weight (KG)</th>
                        <th>Base Cost</th>
                        <th>Shipment Cost</th>
                        <th>Total Costs</th>
                        <th>Expected Revenue</th>
                        <th>Actual Revenue</th>
                        <th>Actual Profit</th>
                        <th>Profit Margin %</th>
                        <th>Status</th>
                        <th>Arrival Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rows = getContainersReport($conn, $start_date, $end_date);
                    if ($rows && count($rows) > 0): 
                        foreach ($rows as $row): 
                            $profit_class = $row['actual_profit'] > 0 ? 'text-success' : 'text-danger';
                            $margin_class = $row['actual_profit_margin'] > 20 ? 'text-success' : ($row['actual_profit_margin'] > 10 ? 'text-warning' : 'text-danger');
                            $status_class = $row['status'] === 'completed' ? 'bg-success' : ($row['status'] === 'processing' ? 'bg-warning' : 'bg-secondary');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['container_number'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?></td>
                            <td><?php echo number_format($row['total_weight_kg'] ?? 0, 2); ?></td>
                            <td>$<?php echo fmt($row['base_cost'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['shipment_cost'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['total_all_costs'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['expected_selling_total'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['actual_selling_total'] ?? 0); ?></td>
                            <td class="<?php echo $profit_class; ?>">$<?php echo fmt($row['actual_profit'] ?? 0); ?></td>
                            <td class="<?php echo $margin_class; ?>"><?php echo number_format($row['actual_profit_margin'] ?? 0, 2); ?>%</td>
                            <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($row['status'] ?? 'unknown'); ?></span></td>
                            <td><?php echo $row['arrival_date'] ? date('Y-m-d', strtotime($row['arrival_date'])) : 'N/A'; ?></td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>No container data found for the selected date range.
                                <?php if (!$rows): ?>
                                <br><small>The containers table may not exist or be accessible.</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'store_summary'): ?>
<!-- Store Sales Summary Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Store Sales Summary - Detailed Analysis</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('store_summary.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="storeSummaryTable">
                <thead class="table-light">
                    <tr>
                        <th>Store</th>
                        <th>Store Code</th>
                        <th>Total Revenue</th>
                        <th>Transactions</th>
                        <th>Items Sold</th>
                        <th>Avg Transaction</th>
                        <th>Items/Transaction</th>
                        <th>Daily Avg Revenue</th>
                        <th>Active Days</th>
                        <th>Cost of Goods</th>
                        <th>Gross Profit</th>
                        <th>Profit Margin %</th>
                        <th>Unique Customers</th>
                        <th>Cash Sales</th>
                        <th>Card Sales</th>
                        <th>Mobile Sales</th>
                        <th>Credit Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $summary_data = getSalesPerStoreSummary($conn, $start_date, $end_date, $selected_store);
                    if ($summary_data && count($summary_data) > 0): 
                        foreach ($summary_data as $row): 
                            $profit_class = $row['gross_profit'] > 0 ? 'text-success' : 'text-danger';
                            $margin_class = $row['profit_margin_percentage'] > 20 ? 'text-success' : ($row['profit_margin_percentage'] > 10 ? 'text-warning' : 'text-danger');
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['store_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['store_code'] ?? 'N/A'); ?></td>
                            <td><strong>$<?php echo fmt($row['total_revenue'] ?? 0); ?></strong></td>
                            <td><?php echo number_format($row['total_transactions'] ?? 0); ?></td>
                            <td><?php echo number_format($row['total_items_sold'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['avg_transaction_value'] ?? 0); ?></td>
                            <td><?php echo number_format($row['items_per_transaction'] ?? 0, 2); ?></td>
                            <td>$<?php echo fmt($row['daily_avg_revenue'] ?? 0); ?></td>
                            <td><?php echo number_format($row['active_days'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['cost_of_goods'] ?? 0); ?></td>
                            <td class="<?php echo $profit_class; ?>">$<?php echo fmt($row['gross_profit'] ?? 0); ?></td>
                            <td class="<?php echo $margin_class; ?>"><strong><?php echo number_format($row['profit_margin_percentage'] ?? 0, 2); ?>%</strong></td>
                            <td><?php echo number_format($row['unique_customers'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['cash_sales'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['card_sales'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['mobile_sales'] ?? 0); ?></td>
                            <td>$<?php echo fmt($row['credit_sales'] ?? 0); ?></td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="17" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>No sales data found for the selected date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'top_items_store'): ?>
<!-- Top Selling Items by Store Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Top Selling Items by Store</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('top_items_store.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="topItemsStoreTable">
                <thead class="table-light">
                    <tr>
                        <th>Store</th>
                        <th>Item Name</th>
                        <th>Item Code</th>
                        <th>Category</th>
                        <th>Quantity Sold</th>
                        <th>Total Revenue</th>
                        <th>Avg Price</th>
                        <th>Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $top_items = getTopSellingItemsByStore($conn, $start_date, $end_date, $selected_store, 10);
                    if ($top_items && count($top_items) > 0): 
                        $current_store = '';
                        $rank = 0;
                        foreach ($top_items as $item): 
                            if ($current_store !== $item['store_name']) {
                                $current_store = $item['store_name'];
                                $rank = 1;
                            } else {
                                $rank++;
                            }
                            
                            if ($rank <= 10): // Show top 10 per store
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['store_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                            <td><span class="badge bg-primary"><?php echo number_format($item['total_quantity_sold']); ?></span></td>
                            <td><strong>$<?php echo fmt($item['total_revenue']); ?></strong></td>
                            <td>$<?php echo fmt($item['avg_selling_price']); ?></td>
                            <td><?php echo number_format($item['transactions_count']); ?></td>
                        </tr>
                    <?php 
                            endif;
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>No sales data found for the selected date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($report_type === 'store_comparison'): ?>
<!-- Store Performance Comparison Report -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Store Performance Comparison - Daily Breakdown</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" onclick="exportTableToCSV('store_comparison.xls')">
                <i class="bi bi-download me-1"></i> Export to Excel
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="storeComparisonTable">
                <thead class="table-light">
                    <tr>
                        <th>Store</th>
                        <th>Store Code</th>
                        <th>Date</th>
                        <th>Daily Revenue</th>
                        <th>Daily Transactions</th>
                        <th>Items Sold</th>
                        <th>Avg Transaction Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $comparison_data = getStorePerformanceComparison($conn, $start_date, $end_date);
                    if ($comparison_data && count($comparison_data) > 0): 
                        foreach ($comparison_data as $row): 
                            $avg_transaction = $row['daily_transactions'] > 0 ? $row['daily_revenue'] / $row['daily_transactions'] : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['store_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['store_code'] ?? 'N/A'); ?></td>
                            <td><?php echo $row['sale_date'] ? date('M d, Y', strtotime($row['sale_date'])) : 'No Sales'; ?></td>
                            <td><strong>$<?php echo fmt($row['daily_revenue'] ?? 0); ?></strong></td>
                            <td><?php echo number_format($row['daily_transactions'] ?? 0); ?></td>
                            <td><?php echo number_format($row['daily_items_sold'] ?? 0); ?></td>
                            <td>$<?php echo fmt($avg_transaction); ?></td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-info-circle me-2"></i>No sales data found for the selected date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Function to export table to Excel
function exportTableToCSV(filename) {
    let csv = [];
    const rows = document.querySelectorAll('table tr');
    
    // Create HTML for Excel
    let htmlContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    htmlContent += '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="ProgId" content="Excel.Sheet"><meta name="Generator" content="Microsoft Excel 11"></head>';
    htmlContent += '<style>table {border-collapse: collapse;} table, td, th {border: 1px solid black;}</style>';
    htmlContent += '<body><table>';
    
    // Add rows
    for (let i = 0; i < rows.length; i++) {
        const cols = rows[i].querySelectorAll('td, th');
        htmlContent += '<tr>';
        
        for (let j = 0; j < cols.length; j++) {
            // Get the text content and clean it
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s)/gm, ' ');
            const isHeader = cols[j].tagName.toLowerCase() === 'th';
            
            // Format header cells with bold and center alignment
            if (isHeader) {
                htmlContent += '<th style="background-color: #f2f2f2; font-weight: bold; text-align: center;">';
                htmlContent += data;
                htmlContent += '</th>';
            } else {
                htmlContent += '<td>';
                htmlContent += data;
                htmlContent += '</td>';
            }
        }
        
        htmlContent += '</tr>';
    }
    
    htmlContent += '</table></body></html>';

    // Ensure filename has .xls extension
    if (!filename.endsWith('.xls')) {
        filename = filename.replace('.xlsx', '.xls');
        filename = filename.replace('.csv', '.xls');
        if (!filename.endsWith('.xls')) {
            filename += '.xls';
        }
    }

    // Download the Excel file
    const blob = new Blob([htmlContent], {type: 'application/vnd.ms-excel'});
    const downloadLink = document.createElement('a');
    downloadLink.href = URL.createObjectURL(blob);
    downloadLink.download = filename;
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// Function to export table to PDF (requires a PDF generation library)
function exportTableToPDF(filename) {
    // This function will be implemented later using a library like jsPDF or pdfmake
    alert('PDF export functionality is not yet implemented.');
    console.log('Attempting to export table to PDF with filename:', filename);
}

// Initialize datepickers
document.addEventListener('DOMContentLoaded', function() {
    initializeDatepickers();
});

// Function to update sales persons dropdown based on selected store
function updateSalesPersons(storeId) {
    const salespersonSelect = document.querySelector('select[name="salesperson_id"]');
    if (!salespersonSelect) return;
    
    // Clear existing options except the first one
    salespersonSelect.innerHTML = '<option value="">All Sales Persons</option>';
    
    if (!storeId) {
        // If no store selected, show all sales persons
        fetch('ajax/get_salespersons.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.salespersons) {
                    data.salespersons.forEach(sp => {
                        const option = document.createElement('option');
                        option.value = sp.id;
                        option.textContent = sp.full_name + (sp.store_name ? ' (' + sp.store_name + ')' : '');
                        salespersonSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading sales persons:', error));
    } else {
        // If store selected, show only sales persons from that store
        fetch('ajax/get_salespersons.php?store_id=' + storeId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.salespersons) {
                    data.salespersons.forEach(sp => {
                        const option = document.createElement('option');
                        option.value = sp.id;
                        option.textContent = sp.full_name;
                        salespersonSelect.appendChild(option);
                    });
                }
            })
            .catch(error => console.error('Error loading sales persons:', error));
    }
}
</script>

<?php require_once '../includes/footer.php'; ?> 
