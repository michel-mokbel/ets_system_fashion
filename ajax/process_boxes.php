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
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if user has inventory access (admin or inventory manager)
if (!can_access_inventory()) {
    echo json_encode(['success' => false, 'message' => 'Access denied - inventory management role required']);
    exit;
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        addBox();
        break;
    case 'edit':
        editBox();
        break;
    case 'delete':
        deleteBox();
        break;
    case 'get_box':
        getBox();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new warehouse box
 */
function addBox() {
    global $conn;
    
    $box_number = sanitize_input($_POST['box_number'] ?? '');
    $box_name = sanitize_input($_POST['box_name'] ?? '');
    $box_type = sanitize_input($_POST['box_type'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit_cost = (float)($_POST['unit_cost'] ?? 0.00);
    $notes = sanitize_input($_POST['notes'] ?? '');
    $created_by = $_SESSION['user_id'];
    
    // Validate required fields
    if (empty($box_number) || empty($box_name)) {
        echo json_encode(['success' => false, 'message' => 'Box number and name are required']);
        return;
    }
    
    // Validate quantity
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be a non-negative number']);
        return;
    }
    
    // Validate unit cost
    if ($unit_cost < 0) {
        echo json_encode(['success' => false, 'message' => 'Unit cost must be a non-negative number']);
        return;
    }
    
    // Check if box number already exists
    $check_query = "SELECT id FROM warehouse_boxes WHERE box_number = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $box_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Box number already exists']);
        return;
    }
    
    // Insert new box
    $container_id = !empty($_POST['container_id']) ? (int)$_POST['container_id'] : null;
    $insert_query = "INSERT INTO warehouse_boxes (box_number, box_name, box_type, quantity, unit_cost, notes, container_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param('sssidsii', $box_number, $box_name, $box_type, $quantity, $unit_cost, $notes, $container_id, $created_by);
    
    if ($insert_stmt->execute()) {
        $box_id = $conn->insert_id;
        echo json_encode(['success' => true, 'message' => 'Box added successfully', 'box_id' => $box_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add box: ' . $conn->error]);
    }
}

/**
 * Edit an existing warehouse box
 */
function editBox() {
    global $conn;
    
    $box_id = (int)($_POST['box_id'] ?? 0);
    $box_number = sanitize_input($_POST['box_number'] ?? '');
    $box_name = sanitize_input($_POST['box_name'] ?? '');
    $box_type = sanitize_input($_POST['box_type'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $unit_cost = (float)($_POST['unit_cost'] ?? 0.00);
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate required fields
    if ($box_id <= 0 || empty($box_number) || empty($box_name)) {
        echo json_encode(['success' => false, 'message' => 'Box ID, number and name are required']);
        return;
    }
    
    // Validate quantity
    if ($quantity < 0) {
        echo json_encode(['success' => false, 'message' => 'Quantity must be a non-negative number']);
        return;
    }
    
    // Validate unit cost
    if ($unit_cost < 0) {
        echo json_encode(['success' => false, 'message' => 'Unit cost must be a non-negative number']);
        return;
    }
    
    // Check if box exists
    $check_query = "SELECT id FROM warehouse_boxes WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $box_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Box not found']);
        return;
    }
    
    // Check if box number already exists (excluding current box)
    $check_number_query = "SELECT id FROM warehouse_boxes WHERE box_number = ? AND id != ?";
    $check_number_stmt = $conn->prepare($check_number_query);
    $check_number_stmt->bind_param('si', $box_number, $box_id);
    $check_number_stmt->execute();
    $check_number_result = $check_number_stmt->get_result();
    
    if ($check_number_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Box number already exists']);
        return;
    }
    
    // Update box
    $container_id = !empty($_POST['container_id']) ? (int)$_POST['container_id'] : null;
    $update_query = "UPDATE warehouse_boxes SET box_number = ?, box_name = ?, box_type = ?, quantity = ?, unit_cost = ?, notes = ?, container_id = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('sssidsii', $box_number, $box_name, $box_type, $quantity, $unit_cost, $notes, $container_id, $box_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Box updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update box: ' . $conn->error]);
    }
}

/**
 * Delete a warehouse box
 */
function deleteBox() {
    global $conn;
    
    $box_id = (int)($_POST['box_id'] ?? 0);
    
    if ($box_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid box ID']);
        return;
    }
    
    // Simple delete - no item checking needed since boxes don't contain items
    
    // Delete box
    $delete_query = "DELETE FROM warehouse_boxes WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $box_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Box deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete box: ' . $conn->error]);
    }
}

/**
 * Get box details
 */
function getBox() {
    global $conn;
    
    $box_id = (int)($_POST['box_id'] ?? 0);
    
    if ($box_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid box ID']);
        return;
    }
    
    $query = "SELECT 
                wb.*,
                u.full_name as created_by_name,
                c.container_number,
                c.id as container_id
              FROM warehouse_boxes wb
              LEFT JOIN users u ON wb.created_by = u.id
              LEFT JOIN containers c ON wb.container_id = c.id
              WHERE wb.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $box_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $box = $result->fetch_assoc();
        echo json_encode(['success' => true, 'box' => $box]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Box not found']);
    }
}


?> 