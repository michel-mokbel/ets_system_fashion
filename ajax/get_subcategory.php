<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Check if subcategory_id is provided
if (!isset($_POST['subcategory_id']) || empty($_POST['subcategory_id'])) {
    echo json_encode(['success' => false, 'message' => 'Subcategory ID is required']);
    exit;
}

$subcategory_id = intval($_POST['subcategory_id']);

try {
    // Get subcategory details with category information and item count
    $query = "SELECT 
                sc.id,
                sc.name,
                sc.description,
                sc.category_id,
                sc.created_at,
                c.name as category_name,
                COUNT(DISTINCT i.id) as item_count
              FROM subcategories sc
              LEFT JOIN categories c ON sc.category_id = c.id
              LEFT JOIN inventory_items i ON sc.id = i.subcategory_id
              WHERE sc.id = ?
              GROUP BY sc.id, sc.name, sc.description, sc.category_id, sc.created_at, c.name";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param('i', $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Subcategory not found']);
        exit;
    }
    
    $subcategory = $result->fetch_assoc();
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'id' => $subcategory['id'],
            'name' => $subcategory['name'],
            'description' => $subcategory['description'],
            'category_id' => $subcategory['category_id'],
            'category_name' => $subcategory['category_name'],
            'item_count' => intval($subcategory['item_count']),
            'created_at' => $subcategory['created_at'],
            'created_at_formatted' => date('Y-m-d H:i', strtotime($subcategory['created_at']))
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_subcategory.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 