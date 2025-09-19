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
        addAsset();
        break;
    case 'edit':
        editAsset();
        break;
    case 'delete':
        deleteAsset();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new asset
 */
function addAsset() {
    global $conn;
    
    // Get form data
    $asset_code = sanitize_input($_POST['asset_code'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $location = sanitize_input($_POST['location'] ?? '');
    $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $status = in_array($_POST['status'] ?? '', ['operational', 'maintenance', 'retired']) ? $_POST['status'] : 'operational';
    
    // Validate required fields
    if (empty($asset_code) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Asset code and name are required']);
        exit;
    }
    
    // Validate dates
    if (!empty($purchase_date) && !validateDate($purchase_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase date format']);
        exit;
    }
    
    if (!empty($warranty_expiry) && !validateDate($warranty_expiry)) {
        echo json_encode(['success' => false, 'message' => 'Invalid warranty expiry date format']);
        exit;
    }
    
    // Check if asset code already exists
    $check_query = "SELECT id FROM assets WHERE asset_code = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $asset_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Asset code already exists']);
        exit;
    }
    
    // Insert new asset
    $insert_query = "INSERT INTO assets (asset_code, name, description, category_id, location, 
                                       purchase_date, warranty_expiry, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('sssissss', $asset_code, $name, $description, $category_id, $location, 
                     $purchase_date, $warranty_expiry, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Asset added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add asset: ' . $conn->error]);
    }
}

/**
 * Edit an existing asset
 */
function editAsset() {
    global $conn;
    
    // Get form data
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $asset_code = sanitize_input($_POST['asset_code'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $location = sanitize_input($_POST['location'] ?? '');
    $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null;
    $warranty_expiry = !empty($_POST['warranty_expiry']) ? $_POST['warranty_expiry'] : null;
    $status = in_array($_POST['status'] ?? '', ['operational', 'maintenance', 'retired']) ? $_POST['status'] : 'operational';
    
    // Validate required fields
    if (empty($asset_code) || empty($name) || $asset_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Asset code, name, and ID are required']);
        exit;
    }
    
    // Validate dates
    if (!empty($purchase_date) && !validateDate($purchase_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase date format']);
        exit;
    }
    
    if (!empty($warranty_expiry) && !validateDate($warranty_expiry)) {
        echo json_encode(['success' => false, 'message' => 'Invalid warranty expiry date format']);
        exit;
    }
    
    // Check if asset exists
    $check_query = "SELECT id FROM assets WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $asset_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit;
    }
    
    // Check if asset code already exists for a different asset
    $check_code_query = "SELECT id FROM assets WHERE asset_code = ? AND id != ?";
    $check_code_stmt = $conn->prepare($check_code_query);
    $check_code_stmt->bind_param('si', $asset_code, $asset_id);
    $check_code_stmt->execute();
    $check_code_result = $check_code_stmt->get_result();
    
    if ($check_code_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Asset code already exists for another asset']);
        exit;
    }
    
    // Update asset
    $update_query = "UPDATE assets 
                     SET asset_code = ?, name = ?, description = ?, category_id = ?, 
                         location = ?, purchase_date = ?, warranty_expiry = ?, 
                         status = ? 
                     WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('sssissssi', $asset_code, $name, $description, $category_id, 
                     $location, $purchase_date, $warranty_expiry, $status, $asset_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Asset updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update asset: ' . $conn->error]);
    }
}

/**
 * Delete an asset
 */
function deleteAsset() {
    global $conn;
    
    // Get asset ID
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    
    if ($asset_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid asset ID']);
        exit;
    }
    
    // Check if asset exists
    $check_query = "SELECT id FROM assets WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $asset_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit;
    }
    
    // Check if asset is referenced in work orders or maintenance schedules
    $check_references_query = "SELECT COUNT(*) as count FROM work_orders WHERE asset_id = ?";
    $check_refs_stmt = $conn->prepare($check_references_query);
    $check_refs_stmt->bind_param('i', $asset_id);
    $check_refs_stmt->execute();
    $check_refs_result = $check_refs_stmt->get_result();
    $work_orders_count = $check_refs_result->fetch_assoc()['count'];
    
    $check_maint_query = "SELECT COUNT(*) as count FROM maintenance_schedules WHERE asset_id = ?";
    $check_maint_stmt = $conn->prepare($check_maint_query);
    $check_maint_stmt->bind_param('i', $asset_id);
    $check_maint_stmt->execute();
    $check_maint_result = $check_maint_stmt->get_result();
    $maint_count = $check_maint_result->fetch_assoc()['count'];
    
    if ($work_orders_count > 0 || $maint_count > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete asset. It has ' . $work_orders_count . ' work orders and ' . $maint_count . ' maintenance schedules associated with it.'
        ]);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete asset
        $delete_query = "DELETE FROM assets WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('i', $asset_id);
        $delete_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Asset deleted successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete asset: ' . $e->getMessage()]);
    }
}

/**
 * Validate date format (YYYY-MM-DD)
 * @param string $date Date string to validate
 * @return bool True if valid date
 */
function validateDate($date) {
    $format = 'Y-m-d';
    $dt = DateTime::createFromFormat($format, $date);
    return $dt && $dt->format($format) === $date;
} 