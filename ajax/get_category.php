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

// Check if category_id is provided
if (!isset($_POST['category_id']) || empty($_POST['category_id'])) {
    echo json_encode(['success' => false, 'message' => 'Category ID is required']);
    exit;
}

$category_id = intval($_POST['category_id']);

try {
    // Get category details with subcategory and item counts
    $query = "SELECT 
                c.id,
                c.name,
                c.description,
                c.created_at,
                COUNT(DISTINCT sc.id) as subcategory_count,
                COUNT(DISTINCT i.id) as item_count
              FROM categories c 
              LEFT JOIN subcategories sc ON c.id = sc.category_id
              LEFT JOIN inventory_items i ON c.id = i.category_id
              WHERE c.id = ?
              GROUP BY c.id, c.name, c.description, c.created_at";
    
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }
    
    $category = $result->fetch_assoc();
    
    // Format the response
    $response = [
        'success' => true,
        'data' => [
            'id' => $category['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'created_at' => $category['created_at'],
            'created_at_formatted' => date('Y-m-d H:i', strtotime($category['created_at'])),
            'subcategory_count' => intval($category['subcategory_count']),
            'item_count' => intval($category['item_count'])
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_category.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} 