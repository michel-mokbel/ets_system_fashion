<?php
require_once '../includes/session_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // First, let's check if the tables exist
    $checkTables = "SHOW TABLES LIKE 'container_boxes'";
    $result = $conn->query($checkTables);
    $containerBoxesExists = $result->num_rows > 0;
    
    $checkTables = "SHOW TABLES LIKE 'warehouse_boxes'";
    $result = $conn->query($checkTables);
    $warehouseBoxesExists = $result->num_rows > 0;
    
    if (!$containerBoxesExists || !$warehouseBoxesExists) {
        echo json_encode([
            'success' => false,
            'message' => 'Required tables do not exist. Container boxes exists: ' . ($containerBoxesExists ? 'yes' : 'no') . ', Warehouse boxes exists: ' . ($warehouseBoxesExists ? 'yes' : 'no')
        ]);
        exit;
    }
    
    // Simple query to get box comparison data
    $query = "
        SELECT 
            COALESCE(wb.box_name, cb.new_box_name, 'Unknown Box') as box_name,
            COALESCE(cb.quantity, 0) as original_qty,
            COALESCE(wb.quantity, 0) as current_qty,
            COALESCE(cb.box_type, 'unknown') as box_type,
            COALESCE(cb.container_id, 0) as container_id,
            COALESCE(c.container_number, 'N/A') as container_number
        FROM container_boxes cb
        LEFT JOIN warehouse_boxes wb ON cb.warehouse_box_id = wb.id
        LEFT JOIN containers c ON cb.container_id = c.id
        ORDER BY box_name
    ";
    
    $result = $conn->query($query);
    $boxes = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $boxes[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $boxes,
        'debug' => [
            'container_boxes_exists' => $containerBoxesExists,
            'warehouse_boxes_exists' => $warehouseBoxesExists,
            'total_records' => count($boxes)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching box data: ' . $e->getMessage(),
        'debug' => [
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]
    ]);
}
?>
