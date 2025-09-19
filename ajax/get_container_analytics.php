<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

try {
    $action = $_POST['action'] ?? 'summary';
    
    switch ($action) {
        case 'summary':
            getContainerSummary();
            break;
        case 'cost_breakdown':
            getCostBreakdown();
            break;
        case 'financial_analysis':
            getFinancialAnalysis();
            break;
        case 'performance_metrics':
            getPerformanceMetrics();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Container analytics error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while retrieving analytics']);
}

/**
 * Get overall container summary statistics (simplified)
 */
function getContainerSummary() {
    global $conn;
    
    try {
        // Get filters
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $supplier_id = $_POST['supplier_id'] ?? '';
        
        $where_conditions = [];
        $params = [];
        $param_types = '';
        
        // Build WHERE clause
        if (!empty($start_date)) {
            $where_conditions[] = "c.created_at >= ?";
            $params[] = $start_date;
            $param_types .= 's';
        }
        
        if (!empty($end_date)) {
            $where_conditions[] = "c.created_at <= ?";
            $params[] = $end_date;
            $param_types .= 's';
        }
        
        if (!empty($supplier_id)) {
            $where_conditions[] = "c.supplier_id = ?";  
            $params[] = $supplier_id;
            $param_types .= 'i';
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";
        
        // Check if container_financial_summary table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'container_financial_summary'");
        $table_exists = $table_check && $table_check->num_rows > 0;
        
        if ($table_exists) {
            // Full query with financial summary
            $sql = "SELECT 
                        COUNT(*) as total_containers,
                        COALESCE(SUM(c.total_weight_kg), 0) as total_weight,
                        COALESCE(SUM(cfs.base_cost), SUM(c.total_cost)) as total_base_cost,
                        COALESCE(SUM(cfs.shipment_cost), 0) as total_shipment_cost,
                        COALESCE(SUM(cfs.total_all_costs), SUM(c.total_cost)) as total_all_costs,
                        COALESCE(SUM(cfs.expected_selling_total), 0) as total_expected_revenue,
                        COALESCE(SUM(cfs.actual_selling_total), 0) as total_actual_revenue,
                        COALESCE(SUM(cfs.actual_profit), 0) as total_actual_profit,
                        COALESCE(AVG(cfs.profit_margin_percentage), 0) as avg_profit_margin,
                        
                        -- Status breakdown
                        SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN c.status = 'received' THEN 1 ELSE 0 END) as received_count,
                        SUM(CASE WHEN c.status = 'processed' THEN 1 ELSE 0 END) as processed_count,
                        SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                    FROM containers c
                    LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
                    $where_clause";
        } else {
            // Simplified query without financial summary table
            $sql = "SELECT 
                        COUNT(*) as total_containers,
                        COALESCE(SUM(c.total_weight_kg), 0) as total_weight,
                        COALESCE(SUM(c.total_cost), 0) as total_base_cost,
                        0 as total_shipment_cost,
                        COALESCE(SUM(c.total_cost), 0) as total_all_costs,
                        0 as total_expected_revenue,
                        0 as total_actual_revenue,
                        0 as total_actual_profit,
                        0 as avg_profit_margin,
                        
                        -- Status breakdown
                        SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                        SUM(CASE WHEN c.status = 'received' THEN 1 ELSE 0 END) as received_count,
                        SUM(CASE WHEN c.status = 'processed' THEN 1 ELSE 0 END) as processed_count,
                        SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                    FROM containers c
                    $where_clause";
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result === false) {
            throw new Exception("Failed to get result: " . $stmt->error);
        }
        
        $summary = $result->fetch_assoc();
        if ($summary === null) {
            throw new Exception("No data returned from query");
        }
        
        // Calculate additional metrics (with null checks)
        $summary['avg_cost_per_kg'] = ($summary['total_weight'] > 0) ? 
            ($summary['total_all_costs'] / $summary['total_weight']) : 0;
        $summary['overall_roi'] = ($summary['total_all_costs'] > 0) ? 
            ($summary['total_actual_profit'] / $summary['total_all_costs'] * 100) : 0;
        $summary['completion_rate'] = ($summary['total_containers'] > 0) ? 
            ($summary['processed_count'] / $summary['total_containers'] * 100) : 0;
        
        // Format numbers
        foreach ($summary as $key => $value) {
            if (is_numeric($value) && strpos($key, '_count') === false && $key !== 'total_containers') {
                $summary[$key . '_formatted'] = number_format($value, 2);
            }
        }
        
        echo json_encode(['success' => true, 'summary' => $summary]);
        
    } catch (Exception $e) {
        error_log("Container summary error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error retrieving container summary: ' . $e->getMessage()]);
    }
}

/**
 * Get detailed cost breakdown
 */
function getCostBreakdown() {
    global $conn;
    
    $container_id = $_POST['container_id'] ?? '';
    
    if (empty($container_id)) {
        // Get breakdown for all containers
        $sql = "SELECT 
                    c.id,
                    c.container_number,
                    s.name as supplier_name,
                    c.status,
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
                    (CASE 
                        WHEN cfs.total_all_costs > 0 THEN (cfs.actual_profit / cfs.total_all_costs * 100)
                        ELSE 0 
                    END) as roi_percentage,
                    (CASE 
                        WHEN c.total_weight_kg > 0 THEN (cfs.total_all_costs / c.total_weight_kg)
                        ELSE 0 
                    END) as cost_per_kg
                FROM containers c
                LEFT JOIN suppliers s ON c.supplier_id = s.id
                LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
                ORDER BY c.created_at DESC
                LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        // Get breakdown for specific container
        $sql = "SELECT 
                    c.*,
                    s.name as supplier_name,
                    cfs.*,
                    (CASE 
                        WHEN cfs.total_all_costs > 0 THEN (cfs.actual_profit / cfs.total_all_costs * 100)
                        ELSE 0 
                    END) as roi_percentage,
                    (CASE 
                        WHEN c.total_weight_kg > 0 THEN (cfs.total_all_costs / c.total_weight_kg)
                        ELSE 0 
                    END) as cost_per_kg
                FROM containers c
                LEFT JOIN suppliers s ON c.supplier_id = s.id
                LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
                WHERE c.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $container_id);
        $stmt->execute();
    }
    
    $result = $stmt->get_result();
    $containers = [];
    
    while ($row = $result->fetch_assoc()) {
        $containers[] = [
            'id' => $row['id'],
            'container_number' => htmlspecialchars($row['container_number']),
            'supplier_name' => htmlspecialchars($row['supplier_name'] ?? 'Unknown'),
            'status' => ucfirst($row['status']),
            'total_weight_kg' => number_format($row['total_weight_kg'], 2),
            'price_per_kg' => number_format($row['price_per_kg'], 2),
            'total_price' => number_format($row['total_price'] ?? 0, 2),
            'base_cost' => number_format($row['base_cost'] ?? 0, 2),
            'shipment_cost' => number_format($row['shipment_cost'] ?? 0, 2),
            'total_all_costs' => number_format($row['total_all_costs'] ?? 0, 2),
            'profit_margin_percentage' => number_format($row['profit_margin_percentage'] ?? 0, 2),
            'expected_selling_total' => number_format($row['expected_selling_total'] ?? 0, 2),
            'actual_selling_total' => number_format($row['actual_selling_total'] ?? 0, 2),
            'actual_profit' => number_format($row['actual_profit'] ?? 0, 2),
            'roi_percentage' => number_format($row['roi_percentage'] ?? 0, 2),
            'cost_per_kg' => number_format($row['cost_per_kg'] ?? 0, 2)
        ];
    }
    
    echo json_encode(['success' => true, 'cost_breakdown' => $containers]);
}

/**
 * Get financial analysis over time
 */
function getFinancialAnalysis() {
    global $conn;
    
    $period = $_POST['period'] ?? 'monthly'; // daily, weekly, monthly, yearly
    
    // Build date grouping based on period
    switch ($period) {
        case 'daily':
            $date_format = '%Y-%m-%d';
            $date_group = 'DATE(c.created_at)';
            break;
        case 'weekly':  
            $date_format = '%Y-%u';
            $date_group = 'YEARWEEK(c.created_at)';
            break;
        case 'yearly':
            $date_format = '%Y';
            $date_group = 'YEAR(c.created_at)';
            break;
        default: // monthly
            $date_format = '%Y-%m';
            $date_group = 'DATE_FORMAT(c.created_at, "%Y-%m")';
            break;
    }
    
    $sql = "SELECT 
                $date_group as period,
                DATE_FORMAT(c.created_at, '$date_format') as period_formatted,
                COUNT(*) as container_count,
                SUM(c.total_weight_kg) as total_weight,
                SUM(cfs.base_cost) as total_base_cost,
                SUM(cfs.shipment_cost) as total_shipment_cost,
                SUM(cfs.total_all_costs) as total_costs,
                SUM(cfs.expected_selling_total) as expected_revenue,
                SUM(cfs.actual_selling_total) as actual_revenue,
                SUM(cfs.actual_profit) as actual_profit,
                AVG(cfs.profit_margin_percentage) as avg_margin,
                SUM(CASE WHEN c.status = 'processed' THEN 1 ELSE 0 END) as processed_count
            FROM containers c
            LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
            WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY $date_group
            ORDER BY period DESC
            LIMIT 12";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $analysis = [];
    while ($row = $result->fetch_assoc()) {
        $analysis[] = [
            'period' => $row['period_formatted'],
            'container_count' => $row['container_count'],
            'total_weight' => number_format($row['total_weight'], 2),
            'total_costs' => number_format($row['total_costs'] ?? 0, 2),
            'expected_revenue' => number_format($row['expected_revenue'] ?? 0, 2),
            'actual_revenue' => number_format($row['actual_revenue'] ?? 0, 2),
            'actual_profit' => number_format($row['actual_profit'] ?? 0, 2),
            'avg_margin' => number_format($row['avg_margin'] ?? 0, 2),
            'roi_percentage' => $row['total_costs'] > 0 ? 
                number_format(($row['actual_profit'] / $row['total_costs'] * 100), 2) : '0.00',
            'completion_rate' => $row['container_count'] > 0 ? 
                number_format(($row['processed_count'] / $row['container_count'] * 100), 2) : '0.00'
        ];
    }
    
    echo json_encode(['success' => true, 'financial_analysis' => array_reverse($analysis)]);
}

/**
 * Get performance metrics comparison (simplified)
 */
function getPerformanceMetrics() {
    global $conn;
    
    // Top performing suppliers
    $supplier_sql = "SELECT 
                        s.id,
                        s.name,
                        COUNT(c.id) as container_count,
                        SUM(cfs.actual_profit) as total_profit,
                        AVG(cfs.profit_margin_percentage) as avg_margin,
                        AVG(CASE 
                            WHEN cfs.total_all_costs > 0 THEN (cfs.actual_profit / cfs.total_all_costs * 100)
                            ELSE 0 
                        END) as avg_roi
                    FROM suppliers s
                    JOIN containers c ON s.id = c.supplier_id
                    LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
                    WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY s.id, s.name
                    HAVING container_count >= 1
                    ORDER BY total_profit DESC
                    LIMIT 10";
    
    $supplier_stmt = $conn->prepare($supplier_sql);
    $supplier_stmt->execute();
    $supplier_result = $supplier_stmt->get_result();
    
    $top_suppliers = [];
    while ($row = $supplier_result->fetch_assoc()) {
        $top_suppliers[] = [
            'id' => $row['id'],
            'name' => htmlspecialchars($row['name']),
            'container_count' => $row['container_count'],
            'total_profit' => number_format($row['total_profit'] ?? 0, 2),
            'avg_margin' => number_format($row['avg_margin'] ?? 0, 2),
            'avg_roi' => number_format($row['avg_roi'] ?? 0, 2)
        ];
    }
    
    // Monthly performance trends
    $trend_sql = "SELECT 
                    DATE_FORMAT(c.created_at, '%Y-%m') as month,
                    COUNT(*) as container_count,
                    AVG(cfs.profit_margin_percentage) as avg_margin,
                    AVG(CASE 
                        WHEN cfs.total_all_costs > 0 THEN (cfs.actual_profit / cfs.total_all_costs * 100)
                        ELSE 0 
                    END) as avg_roi,
                    SUM(cfs.actual_profit) as total_profit
                FROM containers c
                LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
                WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(c.created_at, '%Y-%m')
                ORDER BY month ASC";
    
    $trend_stmt = $conn->prepare($trend_sql);
    $trend_stmt->execute();
    $trend_result = $trend_stmt->get_result();
    
    $monthly_trends = [];
    while ($row = $trend_result->fetch_assoc()) {
        $monthly_trends[] = [
            'month' => $row['month'],
            'container_count' => $row['container_count'],
            'avg_margin' => number_format($row['avg_margin'] ?? 0, 2),
            'avg_roi' => number_format($row['avg_roi'] ?? 0, 2),
            'total_profit' => number_format($row['total_profit'] ?? 0, 2)
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'performance_metrics' => [
            'top_suppliers' => $top_suppliers,
            'monthly_trends' => $monthly_trends
        ]
    ]);
}
?> 