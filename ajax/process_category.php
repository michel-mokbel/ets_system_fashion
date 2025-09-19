<?php
/**
 * Category management endpoint.
 *
 * Handles add, update, and delete operations for product categories, including
 * duplicate checks and CSRF validation, and responds with JSON status messages.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Error logging
error_log("process_category.php called with POST data: " . json_encode($_POST));

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has inventory access (admin or inventory manager)
if (!can_access_inventory()) {
    echo json_encode(['success' => false, 'message' => 'Access denied - inventory management role required']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token'])) {
    error_log("CSRF token missing in process_category.php request");
    echo json_encode(['success' => false, 'message' => 'Missing security token']);
    exit;
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token mismatch in process_category.php: received '" . $_POST['csrf_token'] . "' but expected '" . $_SESSION['csrf_token'] . "'");
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add':
        addCategory();
        break;
    case 'edit':
        editCategory();
        break;
    case 'delete':
        deleteCategory();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new category
 */
function addCategory() {
    global $conn;
    
    // Get form data
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Validate required fields
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }
    
    // Validate parent ID to prevent circular references
    if (!empty($parent_id)) {
        $query = "SELECT id FROM categories WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $parent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parent category']);
            exit;
        }
    }
    
    // Insert new category
    $query = "INSERT INTO categories (name, description, parent_id, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    
    if ($parent_id === null) {
        $stmt->bind_param('ssd', $name, $description, $parent_id);
    } else {
        $stmt->bind_param('ssi', $name, $description, $parent_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add category: ' . $conn->error]);
    }
}

/**
 * Edit an existing category
 */
function editCategory() {
    global $conn;
    
    // Get form data
    $category_id = (int)($_POST['category_id'] ?? 0);
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    // Validate required fields
    if ($category_id <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category ID and name are required']);
        exit;
    }
    
    // Check if category exists
    $check_query = "SELECT id FROM categories WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }
    
    // Prevent category from being its own parent or child
    if ($parent_id == $category_id) {
        echo json_encode(['success' => false, 'message' => 'A category cannot be its own parent']);
        exit;
    }
    
    // Check for circular references (a category can't have one of its descendants as its parent)
    if (!empty($parent_id)) {
        $current_parent = $parent_id;
        
        while ($current_parent !== null) {
            if ($current_parent == $category_id) {
                echo json_encode(['success' => false, 'message' => 'Circular reference detected in category hierarchy']);
                exit;
            }
            
            $parent_query = "SELECT parent_id FROM categories WHERE id = ?";
            $parent_stmt = $conn->prepare($parent_query);
            $parent_stmt->bind_param('i', $current_parent);
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();
            
            if ($parent_result->num_rows === 0) {
                break;
            }
            
            $parent_row = $parent_result->fetch_assoc();
            $current_parent = $parent_row['parent_id'];
        }
    }
    
    // Update category
    $update_query = "UPDATE categories SET name = ?, description = ?, parent_id = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    
    if ($parent_id === null) {
        $stmt->bind_param('ssdi', $name, $description, $parent_id, $category_id);
    } else {
        $stmt->bind_param('ssii', $name, $description, $parent_id, $category_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update category: ' . $conn->error]);
    }
}

/**
 * Delete a category
 */
function deleteCategory() {
    global $conn;
    
    // Get category ID
    $category_id = (int)($_POST['category_id'] ?? 0);
    
    if ($category_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        exit;
    }
    
    // Check if category exists
    $check_query = "SELECT id FROM categories WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }
    
    // Check if category has items or assets associated with it
    $check_items_query = "SELECT COUNT(*) as count FROM inventory_items WHERE category_id = ?";
    $check_items_stmt = $conn->prepare($check_items_query);
    $check_items_stmt->bind_param('i', $category_id);
    $check_items_stmt->execute();
    $check_items_result = $check_items_stmt->get_result();
    $items_count = $check_items_result->fetch_assoc()['count'];
    
    $check_assets_query = "SELECT COUNT(*) as count FROM assets WHERE category_id = ?";
    $check_assets_stmt = $conn->prepare($check_assets_query);
    $check_assets_stmt->bind_param('i', $category_id);
    $check_assets_stmt->execute();
    $check_assets_result = $check_assets_stmt->get_result();
    $assets_count = $check_assets_result->fetch_assoc()['count'];
    
    $total_count = $items_count + $assets_count;
    
    if ($total_count > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete category. It has ' . $total_count . ' items or assets associated with it.'
        ]);
        exit;
    }
    
    // Check if category has child categories
    $check_children_query = "SELECT COUNT(*) as count FROM categories WHERE parent_id = ?";
    $check_children_stmt = $conn->prepare($check_children_query);
    $check_children_stmt->bind_param('i', $category_id);
    $check_children_stmt->execute();
    $check_children_result = $check_children_stmt->get_result();
    $children_count = $check_children_result->fetch_assoc()['count'];
    
    if ($children_count > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete category. It has ' . $children_count . ' child categories.'
        ]);
        exit;
    }
    
    // Delete category
    $delete_query = "DELETE FROM categories WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $category_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete category: ' . $conn->error]);
    }
} 