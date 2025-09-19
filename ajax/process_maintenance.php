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
        addMaintenanceSchedule();
        break;
    case 'edit':
        editMaintenanceSchedule();
        break;
    case 'delete':
        deleteMaintenanceSchedule();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new maintenance schedule
 */
function addMaintenanceSchedule() {
    global $conn;
    
    // Get form data
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $schedule_type = sanitize_input($_POST['schedule_type'] ?? '');
    $frequency_value = null;
    $frequency_unit = null;
    
    if ($schedule_type === 'custom') {
        $frequency_value = (int)($_POST['frequency_value'] ?? 1);
        $frequency_unit = sanitize_input($_POST['frequency_unit'] ?? 'days');
    }
    
    $last_maintenance = !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null;
    $next_maintenance = sanitize_input($_POST['next_maintenance'] ?? '');
    $assigned_technician = sanitize_input($_POST['assigned_technician'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'paused']) ? $_POST['status'] : 'active';
    
    // Validate required fields
    if ($asset_id <= 0 || empty($schedule_type) || empty($next_maintenance)) {
        echo json_encode(['success' => false, 'message' => 'Asset, schedule type, and next maintenance date are required']);
        exit;
    }
    
    // Validate schedule type
    $valid_types = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'];
    if (!in_array($schedule_type, $valid_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid schedule type']);
        exit;
    }
    
    // Validate custom frequency
    if ($schedule_type === 'custom') {
        if ($frequency_value <= 0 || !in_array($frequency_unit, ['days', 'weeks', 'months'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid frequency value or unit']);
            exit;
        }
    }
    
    // Validate dates
    if (!empty($last_maintenance) && !validateDate($last_maintenance)) {
        echo json_encode(['success' => false, 'message' => 'Invalid last maintenance date format']);
        exit;
    }
    
    if (!validateDate($next_maintenance)) {
        echo json_encode(['success' => false, 'message' => 'Invalid next maintenance date format']);
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
    
    // Insert new maintenance schedule
    $insert_query = "INSERT INTO maintenance_schedules (asset_id, schedule_type, frequency_value, frequency_unit, 
                                                      last_maintenance, next_maintenance, assigned_technician, 
                                                      status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param('isisssss', $asset_id, $schedule_type, $frequency_value, $frequency_unit, 
                     $last_maintenance, $next_maintenance, $assigned_technician, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Maintenance schedule added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add maintenance schedule: ' . $conn->error]);
    }
}

/**
 * Edit an existing maintenance schedule
 */
function editMaintenanceSchedule() {
    global $conn;
    
    // Get form data
    $schedule_id = (int)($_POST['schedule_id'] ?? 0);
    $asset_id = (int)($_POST['asset_id'] ?? 0);
    $schedule_type = sanitize_input($_POST['schedule_type'] ?? '');
    $frequency_value = null;
    $frequency_unit = null;
    
    if ($schedule_type === 'custom') {
        $frequency_value = (int)($_POST['frequency_value'] ?? 1);
        $frequency_unit = sanitize_input($_POST['frequency_unit'] ?? 'days');
    }
    
    $last_maintenance = !empty($_POST['last_maintenance']) ? $_POST['last_maintenance'] : null;
    $next_maintenance = sanitize_input($_POST['next_maintenance'] ?? '');
    $assigned_technician = sanitize_input($_POST['assigned_technician'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'paused']) ? $_POST['status'] : 'active';
    
    // Validate required fields
    if ($schedule_id <= 0 || $asset_id <= 0 || empty($schedule_type) || empty($next_maintenance)) {
        echo json_encode(['success' => false, 'message' => 'Schedule ID, asset, schedule type, and next maintenance date are required']);
        exit;
    }
    
    // Validate schedule type
    $valid_types = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'];
    if (!in_array($schedule_type, $valid_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid schedule type']);
        exit;
    }
    
    // Validate custom frequency
    if ($schedule_type === 'custom') {
        if ($frequency_value <= 0 || !in_array($frequency_unit, ['days', 'weeks', 'months'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid frequency value or unit']);
            exit;
        }
    }
    
    // Validate dates
    if (!empty($last_maintenance) && !validateDate($last_maintenance)) {
        echo json_encode(['success' => false, 'message' => 'Invalid last maintenance date format']);
        exit;
    }
    
    if (!validateDate($next_maintenance)) {
        echo json_encode(['success' => false, 'message' => 'Invalid next maintenance date format']);
        exit;
    }
    
    // Check if schedule exists
    $check_schedule = "SELECT id FROM maintenance_schedules WHERE id = ?";
    $check_stmt = $conn->prepare($check_schedule);
    $check_stmt->bind_param('i', $schedule_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Maintenance schedule not found']);
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
    
    // Update maintenance schedule
    $update_query = "UPDATE maintenance_schedules 
                     SET asset_id = ?, schedule_type = ?, frequency_value = ?, frequency_unit = ?, 
                         last_maintenance = ?, next_maintenance = ?, assigned_technician = ?, 
                         status = ? 
                     WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('isissssis', $asset_id, $schedule_type, $frequency_value, $frequency_unit, 
                     $last_maintenance, $next_maintenance, $assigned_technician, $status, $schedule_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Maintenance schedule updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update maintenance schedule: ' . $conn->error]);
    }
}

/**
 * Delete a maintenance schedule
 */
function deleteMaintenanceSchedule() {
    global $conn;
    
    // Get schedule ID
    $schedule_id = (int)($_POST['schedule_id'] ?? 0);
    
    if ($schedule_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
        exit;
    }
    
    // Check if schedule exists
    $check_schedule = "SELECT id FROM maintenance_schedules WHERE id = ?";
    $check_stmt = $conn->prepare($check_schedule);
    $check_stmt->bind_param('i', $schedule_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Maintenance schedule not found']);
        exit;
    }
    
    // Check if schedule has work orders associated
    $check_work_orders = "SELECT COUNT(*) as count FROM work_orders WHERE maintenance_schedule_id = ?";
    $check_wo_stmt = $conn->prepare($check_work_orders);
    $check_wo_stmt->bind_param('i', $schedule_id);
    $check_wo_stmt->execute();
    $check_wo_result = $check_wo_stmt->get_result();
    $work_orders_count = $check_wo_result->fetch_assoc()['count'];
    
    if ($work_orders_count > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete maintenance schedule. It has ' . $work_orders_count . ' work orders associated with it.'
        ]);
        exit;
    }
    
    // Delete maintenance schedule
    $delete_query = "DELETE FROM maintenance_schedules WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $schedule_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Maintenance schedule deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete maintenance schedule: ' . $conn->error]);
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