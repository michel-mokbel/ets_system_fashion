<?php
// Turn off error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

/**
 * AJAX Session Initializer
 * Include this file at the beginning of all AJAX handlers
 */
require_once dirname(__DIR__) . '/includes/session_config.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in for security
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}
?> 