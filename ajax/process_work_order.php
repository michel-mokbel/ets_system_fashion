<?php
/**
 * Work order lifecycle handler.
 *
 * Supports creation, updates, and closure of work orders, managing technician
 * feedback, scheduling data, and status transitions before responding with JSON
 * messages.
 */
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
        addWorkOrder();
        break;
    case 'edit':
        editWorkOrder();
        break;
    case 'delete':
        deleteWorkOrder();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new work order
 */
function addWorkOrder() {
    global $conn;
    
    // Get form data
    $work_order_number = sanitize_input($_POST['work_order_number'] ?? '');
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $maintenance_schedule_id = !empty($_POST['maintenance_schedule_id']) ? (int)$_POST['maintenance_schedule_id'] : null;
    $maintenance_type = sanitize_input($_POST['maintenance_type'] ?? 'preventive');
    $priority = sanitize_input($_POST['priority'] ?? 'medium');
    $description = sanitize_input($_POST['description'] ?? '');
    $status = sanitize_input($_POST['status'] ?? 'pending');
    $scheduled_date = sanitize_input($_POST['scheduled_date'] ?? '');
    $completed_date = !empty($_POST['completed_date']) ? $_POST['completed_date'] : null;
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate required fields
    if (empty($work_order_number) || $asset_id <= 0 || empty($description) || empty($scheduled_date)) {
        echo json_encode(['success' => false, 'message' => 'Work order number, asset, description, and scheduled date are required']);
        exit;
    }
    
    // Validate maintenance type
    $valid_types = ['preventive', 'corrective', 'emergency'];
    if (!in_array($maintenance_type, $valid_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid maintenance type']);
        exit;
    }
    
    // Validate priority
    $valid_priorities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($priority, $valid_priorities)) {
        echo json_encode(['success' => false, 'message' => 'Invalid priority']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Validate dates
    if (!validateDate($scheduled_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid scheduled date format']);
        exit;
    }
    
    if (!empty($completed_date) && !validateDate($completed_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid completed date format']);
        exit;
    }
    
    // Check if work order number already exists
    $check_query = "SELECT id FROM work_orders WHERE work_order_number = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $work_order_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Work order number already exists']);
        exit;
    }
    
    // Check if asset exists
    $check_asset = "SELECT id FROM assets WHERE id = ?";
    $check_stmt = $conn->prepare($check_asset);
    $check_stmt->bind_param('i', $asset_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert new work order
        $insert_query = "INSERT INTO work_orders (work_order_number, asset_id, maintenance_schedule_id,
                                                maintenance_type, priority, description, status,
                                                scheduled_date, completed_date, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('siissssss', $work_order_number, $asset_id, $maintenance_schedule_id,
                         $maintenance_type, $priority, $description, $status,
                         $scheduled_date, $completed_date, $notes);
        $stmt->execute();
        
        // If completed, update the maintenance schedule's last maintenance date
        if ($status === 'completed' && $maintenance_schedule_id) {
            updateMaintenanceSchedule($maintenance_schedule_id, $completed_date ?: date('Y-m-d'));
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Work order added successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to add work order: ' . $e->getMessage()]);
    }
}

/**
 * Edit an existing work order
 */
function editWorkOrder() {
    global $conn;
    
    // Get form data
    $work_order_id = (int)($_POST['work_order_id'] ?? 0);
    $work_order_number = sanitize_input($_POST['work_order_number'] ?? '');
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $maintenance_type = sanitize_input($_POST['maintenance_type'] ?? 'preventive');
    $priority = sanitize_input($_POST['priority'] ?? 'medium');
    $description = sanitize_input($_POST['description'] ?? '');
    $status = sanitize_input($_POST['status'] ?? 'pending');
    $scheduled_date = sanitize_input($_POST['scheduled_date'] ?? '');
    $completed_date = !empty($_POST['completed_date']) ? $_POST['completed_date'] : null;
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate required fields
    if ($work_order_id <= 0 || empty($work_order_number) || $asset_id <= 0 || empty($description) || empty($scheduled_date)) {
        echo json_encode(['success' => false, 'message' => 'Work order ID, number, asset, description, and scheduled date are required']);
        exit;
    }
    
    // Validate maintenance type
    $valid_types = ['preventive', 'corrective', 'emergency'];
    if (!in_array($maintenance_type, $valid_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid maintenance type']);
        exit;
    }
    
    // Validate priority
    $valid_priorities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($priority, $valid_priorities)) {
        echo json_encode(['success' => false, 'message' => 'Invalid priority']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Validate dates
    if (!validateDate($scheduled_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid scheduled date format']);
        exit;
    }
    
    if (!empty($completed_date) && !validateDate($completed_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid completed date format']);
        exit;
    }
    
    // Check if work order exists
    $check_query = "SELECT id, status, maintenance_schedule_id FROM work_orders WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $work_order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Work order not found']);
        exit;
    }
    
    $current_work_order = $check_result->fetch_assoc();
    $previous_status = $current_work_order['status'];
    $maintenance_schedule_id = $current_work_order['maintenance_schedule_id'];
    
    // Check if work order number exists for a different work order
    $check_number_query = "SELECT id FROM work_orders WHERE work_order_number = ? AND id != ?";
    $check_number_stmt = $conn->prepare($check_number_query);
    $check_number_stmt->bind_param('si', $work_order_number, $work_order_id);
    $check_number_stmt->execute();
    $check_number_result = $check_number_stmt->get_result();
    
    if ($check_number_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Work order number already exists for another work order']);
        exit;
    }
    
    // Check if asset exists
    $check_asset = "SELECT id FROM assets WHERE id = ?";
    $check_stmt = $conn->prepare($check_asset);
    $check_stmt->bind_param('i', $asset_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Asset not found']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update work order
        $update_query = "UPDATE work_orders
                        SET work_order_number = ?, asset_id = ?, maintenance_type = ?,
                            priority = ?, description = ?, status = ?, scheduled_date = ?,
                            completed_date = ?, notes = ?, updated_at = NOW()
                        WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sissssssi', $work_order_number, $asset_id, $maintenance_type,
                         $priority, $description, $status, $scheduled_date,
                         $completed_date, $notes, $work_order_id);
        $stmt->execute();
        
        // If status changed to completed and has a maintenance schedule, update the schedule
        if ($status === 'completed' && $previous_status !== 'completed' && $maintenance_schedule_id) {
            updateMaintenanceSchedule($maintenance_schedule_id, $completed_date ?: date('Y-m-d'));
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Work order updated successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update work order: ' . $e->getMessage()]);
    }
}

/**
 * Delete a work order
 */
function deleteWorkOrder() {
    global $conn;
    
    // Get work order ID
    $work_order_id = (int)($_POST['work_order_id'] ?? 0);
    
    if ($work_order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid work order ID']);
        exit;
    }
    
    // Check if work order exists
    $check_query = "SELECT id FROM work_orders WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $work_order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Work order not found']);
        exit;
    }
    
    // Delete work order
    $delete_query = "DELETE FROM work_orders WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $work_order_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Work order deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete work order: ' . $conn->error]);
    }
}

/**
 * Update maintenance schedule after work order completion
 * @param int $schedule_id Maintenance schedule ID
 * @param string $completion_date Date of work order completion
 */
function updateMaintenanceSchedule($schedule_id, $completion_date) {
    global $conn;
    
    // Get schedule details
    $schedule_query = "SELECT * FROM maintenance_schedules WHERE id = ?";
    $schedule_stmt = $conn->prepare($schedule_query);
    $schedule_stmt->bind_param('i', $schedule_id);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    
    if ($schedule_result->num_rows === 0) {
        return false;
    }
    
    $schedule = $schedule_result->fetch_assoc();
    
    // Calculate next maintenance date based on schedule type and frequency
    $next_date = '';
    
    switch ($schedule['schedule_type']) {
        case 'daily':
            $next_date = date('Y-m-d', strtotime($completion_date . ' + 1 day'));
            break;
        case 'weekly':
            $next_date = date('Y-m-d', strtotime($completion_date . ' + 1 week'));
            break;
        case 'monthly':
            $next_date = date('Y-m-d', strtotime($completion_date . ' + 1 month'));
            break;
        case 'quarterly':
            $next_date = date('Y-m-d', strtotime($completion_date . ' + 3 months'));
            break;
        case 'yearly':
            $next_date = date('Y-m-d', strtotime($completion_date . ' + 1 year'));
            break;
        case 'custom':
            $value = $schedule['frequency_value'];
            $unit = $schedule['frequency_unit'];
            $next_date = date('Y-m-d', strtotime($completion_date . " + $value $unit"));
            break;
        default:
            $next_date = date('Y-m-d', strtotime($completion_date . ' + 1 month'));
    }
    
    // Update schedule
    $update_query = "UPDATE maintenance_schedules 
                    SET last_maintenance = ?, next_maintenance = ? 
                    WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param('ssi', $completion_date, $next_date, $schedule_id);
    
    return $update_stmt->execute();
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