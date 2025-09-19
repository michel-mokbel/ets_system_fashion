<?php
// Turn off error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Set timezone to GMT for Mali operations
date_default_timezone_set('GMT');

/**
 * Session Configuration
 * This file sets a unique session name for the mali-inventory application
 * to prevent conflicts with other applications in the same domain.
 */

// Set a unique session name for this application
session_name('INVENTORY_APP_SESSION');

// Set session cookie parameters for better security and compatibility
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; // Only set secure flag if HTTPS is on
$httponly = true; // Prevent JavaScript access to session cookie
$samesite = 'Lax'; // Allows session cookies on same-site requests

// Set session cookie parameters before starting the session
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => '',  // Current domain
    'secure' => $secure,
    'httponly' => $httponly,
    'samesite' => $samesite
]);

// Make sure sessions are saved properly
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_lifetime', '86400');

// Only regenerate session ID if a session is already active
// This prevents the warning "Session ID cannot be regenerated when there is no active session"
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id();
}
?> 