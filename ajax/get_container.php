<?php
/**
 * Retrieves procurement container header details.
 *
 * Supplies the edit modal in `admin/containers.php` with supplier linkage,
 * status, cost breakdown, and scheduling metadata. Access is limited to admin
 * users and results are returned as JSON.
 */
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
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get container ID from POST
$container_id = isset($_POST['container_id']) ? (int)$_POST['container_id'] : 0;

if ($container_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid container ID']);
    exit;
}

try {
    // Prepare and execute query
    $query = "SELECT c.*, s.name as supplier_name, 
                     COALESCE(cfs.actual_profit, 0) as actual_profit
              FROM containers c
              LEFT JOIN suppliers s ON c.supplier_id = s.id
              LEFT JOIN container_financial_summary cfs ON c.id = cfs.container_id
              WHERE c.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $container_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $container = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $container]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Container not found']);
    }
} catch (Exception $e) {
    error_log("Error retrieving container: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error retrieving container data']);
}
?> 