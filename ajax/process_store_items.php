<?php
/**
 * Admin-focused store item management handler.
 *
 * Persists item updates originating from `admin/store_items.php`, including
 * store assignment adjustments, localized fields, and image management. Enforces
 * CSRF protection and returns JSON outcomes.
 */
require_once '../includes/session_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Debug session info
error_log("Session status: " . session_status());
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("User role in session: " . ($_SESSION['user_role'] ?? 'not set'));

// Check if user is logged in
if (!is_logged_in()) {
    error_log("Authentication failed - user not logged in");
    echo json_encode(['success' => false, 'message' => 'Not authenticated', 'debug' => [
        'session_status' => session_status(),
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'session_keys' => array_keys($_SESSION ?? [])
    ]]);
    exit;
}

// Check if user has inventory access (admin or inventory manager)
if (!can_access_inventory()) {
    error_log("Access denied - user does not have inventory access. Role: " . ($_SESSION['user_role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Access denied - inventory management role required']);
    exit;
}

// Check CSRF token for non-GET actions
$action = $_POST['action'] ?? '';
if ($action !== 'get_store_assignments') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_store_assignments':
        getStoreAssignments();
        break;
    case 'assign_item_to_store':
        assignItemToStore();
        break;
    case 'remove_item_from_store':
        removeItemFromStore();
        break;
    case 'bulk_assign_items':
        bulkAssignItems();
        break;
    case 'toggle_assignment':
        toggleAssignment();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get store assignments for items
 */
function getStoreAssignments() {
    global $conn;
    
    $search = $_POST['search'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $store_filter = $_POST['store_filter'] ?? '';
    
    // Build the query
    $query = "SELECT 
                i.id as item_id,
                i.item_code,
                i.name as item_name,
                i.category_id,
                c.name as category_name,
                i.status as item_status,
                GROUP_CONCAT(
                    CONCAT(sia.store_id, ':', s.name, ':', IFNULL(sia.is_active, 0))
                    ORDER BY s.id
                ) as store_assignments
              FROM inventory_items i
              LEFT JOIN categories c ON i.category_id = c.id
              LEFT JOIN store_item_assignments sia ON i.id = sia.item_id
              LEFT JOIN stores s ON sia.store_id = s.id
              WHERE i.status = 'active'";
    
    $params = [];
    $types = '';
    
    // Add search filter
    if (!empty($search)) {
        $query .= " AND (i.item_code LIKE ? OR i.name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= 'ss';
    }
    
    // Add category filter
    if (!empty($category_id)) {
        $query .= " AND i.category_id = ?";
        $params[] = $category_id;
        $types .= 'i';
    }
    
    $query .= " GROUP BY i.id ORDER BY i.item_code";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Parse store assignments
        $assignments = [];
        if ($row['store_assignments']) {
            $assignment_parts = explode(',', $row['store_assignments']);
            foreach ($assignment_parts as $part) {
                list($store_id, $store_name, $is_active) = explode(':', $part);
                $assignments[$store_id] = [
                    'store_name' => $store_name,
                    'is_active' => (bool)$is_active
                ];
            }
        }
        
        $row['assignments'] = $assignments;
        unset($row['store_assignments']);
        $items[] = $row;
    }
    
    // Get all stores for the header
    $stores_query = "SELECT id, name FROM stores ORDER BY id";
    $stores_result = $conn->query($stores_query);
    $stores = [];
    while ($store = $stores_result->fetch_assoc()) {
        $stores[] = $store;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'stores' => $stores
    ]);
}

/**
 * Assign an item to a store
 */
function assignItemToStore() {
    global $conn;
    
    $item_id = (int)($_POST['item_id'] ?? 0);
    $store_id = (int)($_POST['store_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if ($item_id <= 0 || $store_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item or store ID']);
        return;
    }
    
    // Check if assignment already exists
    $check_query = "SELECT id, is_active FROM store_item_assignments WHERE item_id = ? AND store_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('ii', $item_id, $store_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing assignment to active
        $existing = $check_result->fetch_assoc();
        $update_query = "UPDATE store_item_assignments SET is_active = 1, assigned_by = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ii', $user_id, $existing['id']);
        $success = $update_stmt->execute();
    } else {
        // Create new assignment
        $insert_query = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param('iii', $store_id, $item_id, $user_id);
        $success = $insert_stmt->execute();
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Item assigned to store successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to assign item to store']);
    }
}

/**
 * Remove an item from a store
 */
function removeItemFromStore() {
    global $conn;
    
    $item_id = (int)($_POST['item_id'] ?? 0);
    $store_id = (int)($_POST['store_id'] ?? 0);
    
    if ($item_id <= 0 || $store_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item or store ID']);
        return;
    }
    
    // Set assignment to inactive instead of deleting
    $query = "UPDATE store_item_assignments SET is_active = 0 WHERE item_id = ? AND store_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $item_id, $store_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Item removed from store successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove item from store']);
    }
}

/**
 * Toggle assignment status
 */
function toggleAssignment() {
    global $conn;
    
    $item_id = (int)($_POST['item_id'] ?? 0);
    $store_id = (int)($_POST['store_id'] ?? 0);
    $is_active = (bool)($_POST['is_active'] ?? false);
    $user_id = $_SESSION['user_id'];
    
    if ($item_id <= 0 || $store_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item or store ID']);
        return;
    }
    
    if ($is_active) {
        // Assign item to store
        $_POST['item_id'] = $item_id;
        $_POST['store_id'] = $store_id;
        assignItemToStore();
    } else {
        // Remove item from store
        $_POST['item_id'] = $item_id;
        $_POST['store_id'] = $store_id;
        removeItemFromStore();
    }
}

/**
 * Bulk assign items to stores
 */
function bulkAssignItems() {
    global $conn;
    
    $item_ids = $_POST['item_ids'] ?? [];
    $store_ids = $_POST['store_ids'] ?? [];
    $user_id = $_SESSION['user_id'];
    
    if (empty($item_ids) || empty($store_ids)) {
        echo json_encode(['success' => false, 'message' => 'No items or stores selected']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $success_count = 0;
        
        foreach ($item_ids as $item_id) {
            foreach ($store_ids as $store_id) {
                // Check if assignment exists
                $check_query = "SELECT id FROM store_item_assignments WHERE item_id = ? AND store_id = ?";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param('ii', $item_id, $store_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    // Update existing
                    $update_query = "UPDATE store_item_assignments SET is_active = 1, assigned_by = ? WHERE item_id = ? AND store_id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param('iii', $user_id, $item_id, $store_id);
                    if ($update_stmt->execute()) $success_count++;
                } else {
                    // Insert new
                    $insert_query = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by) VALUES (?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param('iii', $store_id, $item_id, $user_id);
                    if ($insert_stmt->execute()) $success_count++;
                }
            }
        }
        
        $conn->commit();
        echo json_encode([
            'success' => true, 
            'message' => "Successfully processed $success_count assignments"
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Bulk assignment failed: ' . $e->getMessage()]);
    }
}
?> 