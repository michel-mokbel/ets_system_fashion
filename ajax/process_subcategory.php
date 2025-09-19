<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in and has inventory access
if (!is_logged_in() || !can_access_inventory()) {
    echo json_encode(['success' => false, 'message' => 'Access denied - inventory management role required']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add':
        addSubcategory();
        break;
    case 'edit':
        editSubcategory();
        break;
    case 'delete':
        deleteSubcategory();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new subcategory
 */
function addSubcategory() {
    global $conn;
    
    try {
        // Validate required fields
        $required_fields = ['name', 'category_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => "Field $field is required"]);
                return;
            }
        }
        
        // Sanitize inputs
        $name = sanitize_input($_POST['name']);
        $category_id = (int)$_POST['category_id'];
        $description = sanitize_input($_POST['description']);
        
        // Check if category exists
        $category_check = "SELECT id FROM categories WHERE id = ?";
        $category_stmt = $conn->prepare($category_check);
        $category_stmt->bind_param('i', $category_id);
        $category_stmt->execute();
        $category_result = $category_stmt->get_result();
        
        if ($category_result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category selected']);
            return;
        }
        
        // Check if subcategory name already exists in this category
        $check_sql = "SELECT id FROM subcategories WHERE name = ? AND category_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('si', $name, $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Subcategory name already exists in this category']);
            return;
        }
        
        // Insert subcategory
        $sql = "INSERT INTO subcategories (name, description, category_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $name, $description, $category_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Subcategory added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add subcategory']);
        }
        
    } catch (Exception $e) {
        error_log("Add subcategory error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while adding the subcategory']);
    }
}

/**
 * Edit an existing subcategory
 */
function editSubcategory() {
    global $conn;
    
    try {
        // Validate required fields
        $required_fields = ['subcategory_id', 'name', 'category_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => "Field $field is required"]);
                return;
            }
        }
        
        // Sanitize inputs
        $subcategory_id = (int)$_POST['subcategory_id'];
        $name = sanitize_input($_POST['name']);
        $category_id = (int)$_POST['category_id'];
        $description = sanitize_input($_POST['description']);
        
        // Check if subcategory exists
        $check_sql = "SELECT id FROM subcategories WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $subcategory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Subcategory not found']);
            return;
        }
        
        // Check if category exists
        $category_check = "SELECT id FROM categories WHERE id = ?";
        $category_stmt = $conn->prepare($category_check);
        $category_stmt->bind_param('i', $category_id);
        $category_stmt->execute();
        $category_result = $category_stmt->get_result();
        
        if ($category_result->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid category selected']);
            return;
        }
        
        // Check if subcategory name already exists in this category (excluding current subcategory)
        $check_sql = "SELECT id FROM subcategories WHERE name = ? AND category_id = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('sii', $name, $category_id, $subcategory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Subcategory name already exists in this category']);
            return;
        }
        
        // Update subcategory
        $sql = "UPDATE subcategories SET name = ?, description = ?, category_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssii', $name, $description, $category_id, $subcategory_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Subcategory updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update subcategory']);
        }
        
    } catch (Exception $e) {
        error_log("Edit subcategory error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating the subcategory']);
    }
}

/**
 * Delete a subcategory
 */
function deleteSubcategory() {
    global $conn;
    
    try {
        $subcategory_id = (int)$_POST['subcategory_id'];
        
        if ($subcategory_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid subcategory ID']);
            return;
        }
        
        // Check if subcategory has items
        $check_sql = "SELECT COUNT(*) as count FROM inventory_items WHERE subcategory_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $subcategory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count_row = $check_result->fetch_assoc();
        
        if ($count_row['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete subcategory that has items assigned to it']);
            return;
        }
        
        // Delete subcategory
        $sql = "DELETE FROM subcategories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $subcategory_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Subcategory deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Subcategory not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete subcategory']);
        }
        
    } catch (Exception $e) {
        error_log("Delete subcategory error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the subcategory']);
    }
} 