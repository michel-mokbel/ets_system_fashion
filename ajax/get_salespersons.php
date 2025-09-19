<?php
/**
 * Returns salesperson options for reporting filters.
 *
 * Retrieves active users assigned to the requested store so report pages can
 * populate dropdowns. Accessible to authorized reporting roles and outputs
 * JSON.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : null;

try {
    if ($store_id) {
        // Get sales persons for specific store
        $salespersons = get_salespersons_by_store($store_id);
    } else {
        // Get all sales persons
        $salespersons = get_all_salespersons();
    }
    
    echo json_encode([
        'success' => true,
        'salespersons' => $salespersons
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_salespersons.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading sales persons'
    ]);
}
?>
