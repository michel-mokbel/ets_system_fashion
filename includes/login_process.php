<?php
require_once 'session_config.php';
session_start();
require_once 'db.php';
require_once 'functions.php';
require_once 'shift_functions.php';

// Log login attempts for debugging
error_log("Login attempt from: " . $_SERVER['REMOTE_ADDR']);

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get username and password from form
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Username and password are required";
        header("Location: ../index.php");
        exit();
    }
    
    // Check user in database with role and store information
    $sql = "SELECT u.id, u.username, u.password, u.role, u.store_id, u.full_name, 
                   s.name as store_name, s.store_code 
            FROM users u 
            LEFT JOIN stores s ON u.store_id = s.id 
            WHERE u.username = ? AND u.status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password (support both hashed and plain text for backward compatibility)
        $password_valid = false;
        if (password_verify($password, $user['password'])) {
            // New hashed password
            $password_valid = true;
        } elseif ($password == $user['password']) {
            // Legacy plain text password - upgrade it to hashed
            $password_valid = true;
            // Update password to hashed version
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param('si', $hashed_password, $user['id']);
            $update_stmt->execute();
        }
        
        if ($password_valid) {
            // Password is correct, clear and regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['store_id'] = $user['store_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['store_name'] = $user['store_name'];
            $_SESSION['store_code'] = $user['store_code'];
            $_SESSION['login_time'] = time();
            
            // Start or resume shift for store managers and sales persons
            if (in_array($user['role'], ['store_manager', 'sales_person'])) {
                $shift_data = start_or_resume_shift($user['id'], $user['store_id']);
                if ($shift_data) {
                    $_SESSION['shift_id'] = $shift_data['shift_id'];
                    $_SESSION['shift_start_time'] = $shift_data['start_time'];
                    $_SESSION['shift_is_new'] = $shift_data['is_new'];
                    
                    if ($shift_data['is_new']) {
                        error_log("Login: Started new shift {$shift_data['shift_id']} for user {$user['username']}");
                    } else {
                        error_log("Login: Resumed shift {$shift_data['shift_id']} for user {$user['username']}");
                    }
                } else {
                    error_log("Login: Failed to start/resume shift for user {$user['username']}");
                }
            }
            
            // Log successful login
            error_log("Successful login for user: $username (ID: {$user['id']}, Role: {$user['role']}, Store: {$user['store_id']})");
            
            // Ensure session data is written before redirecting
            session_write_close();
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: ../admin/dashboard.php");
                    break;
                case 'inventory_manager':
                    header("Location: ../admin/store_items.php");
                    break;
                case 'transfer_manager':
                    header("Location: ../admin/transfers.php");
                    break;
                case 'store_manager':
                    header("Location: ../store/pos.php");
                    break;
                case 'sales_person':
                    header("Location: ../store/pos.php");
                    break;
                default:
                    header("Location: ../admin/dashboard.php");
                    break;
            }
            exit();
        } else {
            // Invalid password
            error_log("Failed login - invalid password for user: $username");
            $_SESSION['error'] = "Invalid username or password";
            header("Location: ../index.php");
            exit();
        }
    } else {
        // User not found
        error_log("Failed login - user not found: $username");
        $_SESSION['error'] = "Invalid username or password";
        header("Location: ../index.php");
        exit();
    }
} else {
    // Direct access to this file
    header("Location: ../index.php");
    exit();
}
?> 