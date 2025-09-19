<?php
require_once '../includes/session_config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'user' => null,
    'csrf_token' => null,
    'session_active' => false
];

try {
    // Check if user is logged in
    if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        // Generate CSRF token
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Get user info from database
        $sql = "SELECT u.id, u.username, u.role, u.store_id, u.full_name, 
                       s.name as store_name, s.store_code 
                FROM users u 
                LEFT JOIN stores s ON u.store_id = s.id 
                WHERE u.id = ? AND u.status = 'active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            $response['success'] = true;
            $response['message'] = 'Session active';
            $response['session_active'] = true;
            $response['csrf_token'] = $_SESSION['csrf_token'];
            $response['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'store_id' => $user['store_id'] ? (int)$user['store_id'] : null,
                'store_name' => $user['store_name'],
                'store_code' => $user['store_code']
            ];
        } else {
            // User not found in database, clear session
            session_destroy();
            $response['message'] = 'User not found, session cleared';
        }
    } else {
        $response['message'] = 'No active session';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error checking session: ' . $e->getMessage();
    error_log("Flutter session error: " . $e->getMessage());
}

echo json_encode($response);
?>

