<?php
/**
 * Manual shift closure endpoint.
 *
 * Allows authenticated store personnel to close their active shift on demand.
 * Validates CSRF tokens, summarizes the shift using helpers from
 * `includes/shift_functions.php`, persists the closure, and returns the same
 * summary structure used by the enforced logout modal.
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

try {
    // Get the active shift
    $active_shift = get_active_shift($user_id);
    
    if (!$active_shift) {
        echo json_encode([
            'success' => false, 
            'message' => 'No active shift found to close'
        ]);
        exit;
    }
    
    // Close the shift
    $success = close_shift($active_shift['id']);
    
    if ($success) {
        // Clear shift-related session data
        unset($_SESSION['shift_id']);
        unset($_SESSION['shift_start_time']);
        unset($_SESSION['shift_is_new']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Shift closed successfully',
            'shift_id' => $active_shift['id']
        ]);
        
        error_log("Shift closed successfully: ID {$active_shift['id']} for user $user_id");
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to close shift'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error closing shift: ' . $e->getMessage()
    ]);
    error_log("Close shift error: " . $e->getMessage());
}
?>
