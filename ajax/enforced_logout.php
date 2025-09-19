<?php
/**
 * Enforced logout data provider.
 *
 * When a store user attempts to log out, this endpoint ensures any open shift
 * is summarized and optionally closed. Returns totals for sales, expenses,
 * returns, and shift duration so the logout modal can display the information
 * and require acknowledgement before the session ends.
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

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

try {
    // Only enforce shift closure for store managers and sales persons
    if (!in_array($user_role, ['store_manager', 'sales_person'])) {
        echo json_encode([
            'success' => true,
            'message' => 'No shift to close',
            'can_logout' => true
        ]);
        exit;
    }
    
    // Check for active shift
    $active_shift = get_active_shift($user_id);
    
    if (!$active_shift) {
        echo json_encode([
            'success' => true,
            'message' => 'No active shift found',
            'can_logout' => true
        ]);
        exit;
    }
    
    // Generate shift report data before closing
    $store_id = $_SESSION['store_id'];
    $current_time = time();
    $login_time = strtotime($active_shift['start_time']);
    $session_duration_seconds = $current_time - $login_time;
    
    // Convert to datetime format for database queries
    $start_datetime = date('Y-m-d H:i:s', $login_time);
    $end_datetime = date('Y-m-d H:i:s', $current_time);
    
    // Get sales by payment method (same as shift report)
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
    $payment_methods = [];
    
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
        
        $payment_methods[] = $payment_data;
    }
    
    // Get expenses (same as shift report)
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
    $expenses = [];
    while ($expenses_row = $expenses_result->fetch_assoc()) {
        $amount = (float)$expenses_row['amount'];
        $total_expenses += $amount;
        $expenses[] = [
            'category' => $expenses_row['category'],
            'description' => $expenses_row['description'],
            'amount' => $amount,
            'created_at' => $expenses_row['created_at']
        ];
    }
    
    // Close the shift
    error_log("Enforced logout: Attempting to close shift {$active_shift['id']} for user $user_id");
    $closed = close_shift($active_shift['id']);
    
    if (!$closed) {
        error_log("Enforced logout: Failed to close shift {$active_shift['id']} for user $user_id");
        echo json_encode([
            'success' => false,
            'message' => 'Failed to close shift. Please try again.',
            'can_logout' => false,
            'debug_info' => [
                'shift_id' => $active_shift['id'],
                'user_id' => $user_id,
                'error' => 'close_shift returned false'
            ]
        ]);
        exit;
    }
    
    // Prepare shift summary for response (same structure as shift report)
    $shift_summary = [
        'shift_id' => $active_shift['id'],
        'start_time' => $active_shift['start_time'],
        'end_time' => date('Y-m-d H:i:s'),
        'duration' => format_shift_duration($session_duration_seconds),
        'net_sales' => $total_net_sales,
        'total_cash_amount' => $total_cash_amount,
        'total_mobile_amount' => $total_mobile_amount,
        'payment_methods' => $payment_methods,
        'expenses' => $expenses,
        'total_expenses' => $total_expenses,
        'net_total' => $total_net_sales - $total_expenses,
        'store_name' => $_SESSION['store_name'] ?? 'Store',
        'user_name' => $_SESSION['full_name'] ?? $_SESSION['username']
    ];
    
    // Clear shift-related session data
    unset($_SESSION['shift_id']);
    unset($_SESSION['shift_start_time']);
    unset($_SESSION['shift_is_new']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Shift closed successfully',
        'can_logout' => true,
        'shift_summary' => $shift_summary
    ]);
    
    error_log("Enforced logout: Closed shift {$active_shift['id']} for user $user_id");
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error during logout process: ' . $e->getMessage(),
        'can_logout' => false
    ]);
    error_log("Enforced logout error: " . $e->getMessage());
}
?>
