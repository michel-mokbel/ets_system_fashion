<?php
/**
 * POS secondary authorization check.
 *
 * Validates a manager’s password to approve sensitive POS actions initiated by
 * salespersons. Returns JSON flags indicating whether the action may proceed.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$user_role = $_SESSION['user_role'];
$store_id = $_SESSION['store_id'];
$password = $_POST['password'] ?? '';

// Only allow sales persons to use this endpoint (store managers can access returns directly)
if ($user_role !== 'sales_person') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password required']);
    exit;
}

if (!$store_id) {
    echo json_encode(['success' => false, 'message' => 'Store ID not found in session']);
    exit;
}

try {
    // Find the store manager for this store
    $query = "SELECT id, username, password FROM users WHERE role = 'store_manager' AND store_id = ? AND status = 'active' LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No active store manager found for this store']);
        exit;
    }

    $manager = $result->fetch_assoc();

    // Verify the password
    if (password_verify($password, $manager['password'])) {
        // Log this authorization attempt for security
        error_log("Return authorization granted for sales person (ID: {$_SESSION['user_id']}) by manager password verification at store {$store_id}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Manager password verified successfully',
            'manager_name' => $manager['username']
        ]);
    } else {
        // Log failed authorization attempt
        error_log("Failed return authorization attempt for sales person (ID: {$_SESSION['user_id']}) at store {$store_id} - incorrect manager password");
        
        echo json_encode(['success' => false, 'message' => 'Invalid manager password']);
    }

} catch (Exception $e) {
    error_log("Error in verify_manager_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?> 