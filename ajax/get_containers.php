<?php
/**
 * Server-side listing for procurement containers.
 *
 * Applies supplier, status, and date filters; composes statistics; and returns
 * DataTables-compatible JSON used by `admin/containers.php`. Restricted to
 * administrative roles.
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
        'draw' => intval($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

try {
    // Get DataTables parameters
    $draw = $_POST['draw'] ?? 1;
    $start = $_POST['start'] ?? 0;
    $length = $_POST['length'] ?? 10;
    $search = $_POST['search']['value'] ?? '';

    // Get filter parameters
    $supplier_id = $_POST['supplier_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    // Build query
    $where = [];
    $params = [];
    $param_types = '';

    // Simplified base query with financial summary and fallback calculations
    $query = "SELECT c.*, 
                     s.name as supplier_name,
                     COALESCE(cfs.base_cost, c.total_cost) as base_cost,
                     COALESCE(cfs.shipment_cost, c.shipment_cost, 0) as financial_shipment_cost,
                     COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) as total_all_costs,
                     COALESCE(cfs.profit_margin_percentage, c.profit_margin_percentage, 0) as financial_profit_margin,
                     COALESCE(cfs.expected_selling_total, 0) as expected_selling_total,
                     COALESCE(cfs.actual_selling_total, 0) as actual_selling_total,
                     COALESCE(cfs.actual_profit, 0) as actual_profit,
                     (CASE 
                        WHEN COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) > 0 
                        THEN (COALESCE(cfs.actual_profit, 0) / COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) * 100)
                        ELSE 0 
                     END) as roi_percentage,
                     (CASE 
                        WHEN COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) > 0 
                        THEN (COALESCE(cfs.actual_profit, 0) / COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) * 100)
                        ELSE 0 
                     END) as roi_percentage_raw,
                     (CASE 
                        WHEN c.total_weight_kg > 0 THEN (COALESCE(cfs.total_all_costs, (c.total_cost + COALESCE(c.shipment_cost, 0))) / c.total_weight_kg)
                        ELSE 0 
                     END) as cost_per_kg_calculated
              FROM containers c
              LEFT JOIN suppliers s ON c.supplier_id = s.id
              LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id";

    // Apply search
    if (!empty($search)) {
        $where[] = "(c.container_number LIKE ? OR s.name LIKE ? OR c.notes LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'sss';
    }

    // Apply supplier filter
    if (!empty($supplier_id)) {
        $where[] = "c.supplier_id = ?";
        $params[] = $supplier_id;
        $param_types .= 'i';
    }

    // Apply status filter
    if (!empty($status)) {
        $where[] = "c.status = ?";
        $params[] = $status;
        $param_types .= 's';
    }

    // Apply date filters
    if (!empty($start_date)) {
        $where[] = "c.arrival_date >= ?";
        $params[] = $start_date;
        $param_types .= 's';
    }

    if (!empty($end_date)) {
        $where[] = "c.arrival_date <= ?";
        $params[] = $end_date;
        $param_types .= 's';
    }

    // Combine all conditions
    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }

    // Get total count before pagination
    $count_query = "SELECT COUNT(*) as total FROM containers c";
    if (!empty($where)) {
        $count_query .= " LEFT JOIN suppliers s ON c.supplier_id = s.id WHERE " . implode(' AND ', $where);
    }

    $count_stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];

    // Add sorting
    $order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 0;
    $order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

    $columns = [
        'c.container_number', 
        's.name', 
        'c.total_weight_kg', 
        'c.total_price',
        'cfs.total_all_costs', 
        'c.amount_paid', 
        'c.remaining_balance', 
        'cfs.actual_profit',
        'c.arrival_date', 
        'c.status'
    ];
    
    if (isset($columns[$order_column])) {
        $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
    } else {
        $query .= " ORDER BY c.created_at DESC";
    }

    // Add pagination
    $query .= " LIMIT ?, ?";
    $params[] = $start;
    $params[] = $length;
    $param_types .= 'ii';

    // Prepare and execute the final query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Format data for DataTables
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id' => $row['id'],
            'container_number' => htmlspecialchars($row['container_number']),
            'supplier_name' => htmlspecialchars($row['supplier_name'] ?? 'Unknown'),
            'total_weight_kg' => floatval($row['total_weight_kg']),
            'price_per_kg' => floatval($row['price_per_kg']),
            'total_price' => floatval($row['total_cost'] ?? 0),
            
            // Enhanced financial data
            'base_cost' => floatval($row['base_cost'] ?? 0),
            'shipment_cost' => floatval($row['shipment_cost'] ?? 0),
            'total_cost' => floatval($row['total_cost'] ?? 0),
            'total_all_costs' => floatval($row['total_all_costs'] ?? 0),
            'amount_paid' => floatval($row['amount_paid']),
            'remaining_balance' => floatval($row['remaining_balance']),
            
            // Profit and margin data
            'profit_margin_percentage' => floatval($row['profit_margin_percentage'] ?? 0),
            'expected_selling_total' => floatval($row['expected_selling_total'] ?? 0),
            'actual_selling_total' => floatval($row['actual_selling_total'] ?? 0),
            'actual_profit' => floatval($row['actual_profit'] ?? 0),
            'expected_profit' => floatval($row['expected_selling_total'] ?? 0) - floatval($row['total_all_costs'] ?? 0),
            'roi_percentage' => floatval($row['roi_percentage'] ?? 0),
            'cost_per_kg' => floatval($row['cost_per_kg_calculated'] ?? 0),
            
            // Standard fields
            'arrival_date' => $row['arrival_date'],
            'status' => $row['status'],
            'notes' => htmlspecialchars($row['notes'] ?? ''),
            'created_at' => $row['created_at'],
            
            // Raw values for calculations
            'total_weight_kg_raw' => floatval($row['total_weight_kg']),
            'price_per_kg_raw' => floatval($row['price_per_kg']),
            'total_price_raw' => floatval($row['total_cost'] ?? 0),
            'base_cost_raw' => floatval($row['base_cost'] ?? 0),
            'shipment_cost_raw' => floatval($row['shipment_cost'] ?? 0),
            'total_all_costs_raw' => floatval($row['total_all_costs'] ?? 0),
            'actual_profit_raw' => floatval($row['actual_profit'] ?? 0),
            'roi_percentage_raw' => floatval($row['roi_percentage'] ?? 0)
        ];
    }

    echo json_encode([
        'draw' => intval($draw),
        'recordsTotal' => $total_records,
        'recordsFiltered' => $total_records,
        'data' => $data
    ]);
} catch (Exception $e) {
    error_log("DataTables error: " . $e->getMessage());
    echo json_encode([
        'draw' => intval($_POST['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ]);
}
?> 