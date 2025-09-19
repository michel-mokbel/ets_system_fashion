<?php
/**
 * Lightweight database health check.
 *
 * Attempts to connect to MySQL using the shared connection helper and returns
 * a JSON status payload. Designed for uptime monitors or installer scripts to
 * verify connectivity without requiring authentication.
 */
header('Content-Type: application/json');

// Include database connection
require_once '../includes/db.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// Check if we can query the database
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory_items");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo json_encode(['success' => true, 'message' => 'Database connection successful', 'items_count' => $count]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}
?> 