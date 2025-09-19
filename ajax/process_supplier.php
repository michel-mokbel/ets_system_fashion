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

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add':
        addSupplier();
        break;
    case 'edit':
        editSupplier();
        break;
    case 'delete':
        deleteSupplier();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new supplier
 */
function addSupplier() {
    global $conn;
    
    // Get form data
    $name = sanitize_input($_POST['name'] ?? '');
    $contact_person = sanitize_input($_POST['contact_person'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    
    // Validate required fields
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Supplier name is required']);
        exit;
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Insert new supplier
    $insert_query = "INSERT INTO suppliers (name, contact_person, email, phone, address, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('ssssss', $name, $contact_person, $email, $phone, $address, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supplier added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add supplier: ' . $conn->error]);
    }
}

/**
 * Edit an existing supplier
 */
function editSupplier() {
    global $conn;
    
    // Get form data
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $name = sanitize_input($_POST['name'] ?? '');
    $contact_person = sanitize_input($_POST['contact_person'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    
    // Validate required fields
    if ($supplier_id <= 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Supplier ID and name are required']);
        exit;
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Check if supplier exists
    $check_query = "SELECT id FROM suppliers WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $supplier_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Supplier not found']);
        exit;
    }
    
    // Update supplier
    $update_query = "UPDATE suppliers
                    SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ?
                    WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ssssssi', $name, $contact_person, $email, $phone, $address, $status, $supplier_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update supplier: ' . $conn->error]);
    }
}

/**
 * Delete a supplier
 */
function deleteSupplier() {
    global $conn;
    
    // Get supplier ID
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    
    if ($supplier_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid supplier ID']);
        exit;
    }
    
    // Check if supplier exists
    $check_query = "SELECT id FROM suppliers WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $supplier_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Supplier not found']);
        exit;
    }
    
    // Check if supplier has related purchase orders
    $check_po_query = "SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?";
    $check_po_stmt = $conn->prepare($check_po_query);
    $check_po_stmt->bind_param('i', $supplier_id);
    $check_po_stmt->execute();
    $check_po_result = $check_po_stmt->get_result();
    $po_count = $check_po_result->fetch_assoc()['count'];
    
    if ($po_count > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete supplier. It has ' . $po_count . ' purchase orders associated with it.'
        ]);
        exit;
    }
    
    // Delete supplier
    $delete_query = "DELETE FROM suppliers WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $supplier_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete supplier: ' . $conn->error]);
    }
} 