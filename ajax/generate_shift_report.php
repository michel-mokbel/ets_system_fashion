<?php
/**
 * On-demand shift report endpoint.
 *
 * Produces the same summary metrics available at shift closure—sales by
 * payment method, returns, expenses, and duration—without changing the shift
 * state. Used by the POS to let managers review live performance before
 * closing out.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/shift_functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is store manager or sales person
if (!is_store_manager() && !is_sales_person()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get shift-based time range for report (database-persistent)
$current_time = time();
$shift_data = null;
$login_time = null;

// First, try to get active shift from database
$active_shift = get_active_shift($user_id);
if ($active_shift) {
    $shift_data = $active_shift;
    $login_time = strtotime($active_shift['start_time']);
    error_log("Shift Report Debug - Using active shift ID {$active_shift['id']}, started: {$active_shift['start_time']}");
} else {
    // Fallback: try session login_time or use start of today
    $login_time = $_SESSION['login_time'] ?? strtotime('today 00:00:00');
    error_log("Shift Report Debug - No active shift found, using fallback time: " . date('Y-m-d H:i:s', $login_time));
}

// Debug: Log session and shift information
error_log("Shift Report Debug - Session login_time exists: " . (isset($_SESSION['login_time']) ? 'YES' : 'NO'));
error_log("Shift Report Debug - Active shift exists: " . ($active_shift ? 'YES' : 'NO'));
error_log("Shift Report Debug - Using login_time: " . date('Y-m-d H:i:s', $login_time));

// Convert to datetime format for database queries
$start_datetime = date('Y-m-d H:i:s', $login_time);
$end_datetime = date('Y-m-d H:i:s', $current_time);

// For display purposes
$start_date = date('Y-m-d', $login_time);
$end_date = date('Y-m-d', $current_time);

try {
    // Debug: Log the time range being used
    error_log("Shift Report Debug - Login time: " . $login_time . " (" . date('Y-m-d H:i:s', $login_time) . ")");
    error_log("Shift Report Debug - Current time: " . $current_time . " (" . date('Y-m-d H:i:s', $current_time) . ")");
    error_log("Shift Report Debug - Start datetime: " . $start_datetime);
    error_log("Shift Report Debug - End datetime: " . $end_datetime);
    error_log("Shift Report Debug - Store ID: " . $store_id);
    error_log("Shift Report Debug - User ID: " . $user_id);
    
    // Debug: Check if there are any invoices for this store at all
    $debug_stmt = $conn->prepare("SELECT COUNT(*) as total_count, MIN(created_at) as earliest, MAX(created_at) as latest FROM invoices WHERE store_id = ?");
    $debug_stmt->bind_param('i', $store_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    if ($debug_row = $debug_result->fetch_assoc()) {
        error_log("Shift Report Debug - Total invoices for store: " . $debug_row['total_count']);
        error_log("Shift Report Debug - Earliest invoice: " . $debug_row['earliest']);
        error_log("Shift Report Debug - Latest invoice: " . $debug_row['latest']);
    }
    
    // Calculate session duration
    $session_duration_seconds = $current_time - $login_time;
    $session_duration_hours = floor($session_duration_seconds / 3600);
    $session_duration_minutes = floor(($session_duration_seconds % 3600) / 60);
    
    // Initialize report data
    $report_data = [
        'shift_info' => [
            'shift_id' => $shift_data ? $shift_data['id'] : null,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'login_time' => date('Y-m-d H:i:s', $login_time),
            'current_time' => date('Y-m-d H:i:s', $current_time),
            'session_duration_hours' => $session_duration_hours,
            'session_duration_minutes' => $session_duration_minutes,
            'session_duration_formatted' => format_shift_duration($session_duration_seconds),
            'store_id' => $store_id,
            'user_id' => $user_id,
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['full_name'] ?? $_SESSION['username'],
            'shift_source' => $shift_data ? 'database' : 'session_fallback'
        ],
        'net_sales' => 0,
        'payment_methods' => [],
        'expenses' => []
    ];

    // Get store information
    $store_stmt = $conn->prepare("SELECT name, store_code FROM stores WHERE id = ?");
    $store_stmt->bind_param('i', $store_id);
    $store_stmt->execute();
    $store_result = $store_stmt->get_result();
    if ($store_row = $store_result->fetch_assoc()) {
        $report_data['shift_info']['store_name'] = $store_row['name'];
        $report_data['shift_info']['store_code'] = $store_row['store_code'];
    }

    // 1. Net Sales by Payment Method - Only for logged-in user
    $payment_query = "
        SELECT 
            payment_method,
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_amount,
            SUM(COALESCE(cash_amount, 0)) as total_cash_amount,
            SUM(COALESCE(mobile_amount, 0)) as total_mobile_amount
        FROM invoices 
        WHERE store_id = ? 
        AND sales_person_id = ?
        AND (status = 'completed' OR status = 'paid' OR status IS NULL OR status = '')
        AND (payment_status = 'paid' OR payment_status = 'partial' OR payment_status IS NULL OR payment_status = '')
        AND created_at BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ";
    
    $payment_stmt = $conn->prepare($payment_query);
    $payment_stmt->bind_param('iiss', $store_id, $user_id, $start_datetime, $end_datetime);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    
    $total_net_sales = 0;
    $total_cash_amount = 0;
    $total_mobile_amount = 0;
    
    while ($payment_row = $payment_result->fetch_assoc()) {
        $amount = (float)$payment_row['total_amount'];
        $cash_amount = (float)$payment_row['total_cash_amount'];
        $mobile_amount = (float)$payment_row['total_mobile_amount'];
        
        $total_net_sales += $amount;
        $total_cash_amount += $cash_amount;
        $total_mobile_amount += $mobile_amount;
        
        $payment_data = [
            'method' => $payment_row['payment_method'],
            'count' => (int)$payment_row['transaction_count'],
            'amount' => $amount
        ];
        
        // Add cash and mobile breakdown for cash_mobile payments
        if ($payment_row['payment_method'] === 'cash_mobile') {
            $payment_data['cash_amount'] = $cash_amount;
            $payment_data['mobile_amount'] = $mobile_amount;
        }
        
        $report_data['payment_methods'][] = $payment_data;
    }
    
    // Add total cash and mobile amounts to report data
    $report_data['total_cash_amount'] = $total_cash_amount;
    $report_data['total_mobile_amount'] = $total_mobile_amount;
    
    $report_data['net_sales'] = $total_net_sales;

    // 2. Expenses - Only for logged-in user
    $expenses_query = "
        SELECT 
            category,
            description,
            amount,
            created_at
        FROM expenses 
        WHERE store_id = ? 
        AND added_by = ?
        AND created_at BETWEEN ? AND ?
        ORDER BY created_at DESC
    ";
    
    $expenses_stmt = $conn->prepare($expenses_query);
    $expenses_stmt->bind_param('iiss', $store_id, $user_id, $start_datetime, $end_datetime);
    $expenses_stmt->execute();
    $expenses_result = $expenses_stmt->get_result();
    
    $total_expenses = 0;
    while ($expenses_row = $expenses_result->fetch_assoc()) {
        $amount = (float)$expenses_row['amount'];
        $total_expenses += $amount;
        $report_data['expenses'][] = [
            'category' => $expenses_row['category'],
            'description' => $expenses_row['description'],
            'amount' => $amount,
            'created_at' => $expenses_row['created_at']
        ];
    }
    
    $report_data['total_expenses'] = $total_expenses;

    // Debug: Log final report data
    error_log("Shift Report Debug - Final report data: " . json_encode($report_data));

    echo json_encode([
        'success' => true, 
        'data' => $report_data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error generating shift report: ' . $e->getMessage()
    ]);
}
?>
