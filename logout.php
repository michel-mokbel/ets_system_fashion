<?php
require_once 'includes/session_config.php';
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/shift_functions.php';

// Check if user has an active shift that needs to be closed
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

if ($user_id && in_array($user_role, ['store_manager', 'sales_person'])) {
    $active_shift = get_active_shift($user_id);
    
    if ($active_shift) {
        // Close the shift automatically
        $closed = close_shift($active_shift['id']);
        
        if ($closed) {
            error_log("Logout: Automatically closed shift {$active_shift['id']} for user $user_id");
        } else {
            error_log("Logout: Failed to close shift {$active_shift['id']} for user $user_id");
        }
    }
}

// Unset all session variables
$_SESSION = array();

// If using session cookies, delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: index.php");
exit();
?> 