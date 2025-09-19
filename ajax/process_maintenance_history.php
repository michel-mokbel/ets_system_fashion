<?php
/**
 * Maintenance history entry handler.
 *
 * Records or updates completed maintenance actions, including technician notes,
 * cost tracking, and attachment handling. Requires maintenance permissions and
 * returns JSON feedback.
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
        addMaintenanceHistory();
        break;
    case 'edit':
        editMaintenanceHistory();
        break;
    case 'delete':
        deleteMaintenanceHistory();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Add a new maintenance history entry
 */
function addMaintenanceHistory() {
    global $conn;
    
    // Get form data
    $schedule_id = (int)($_POST['maintenance_schedule_id'] ?? 0);
    $completion_date = sanitize_input($_POST['completion_date'] ?? '');
    $completed_by = sanitize_input($_POST['completed_by'] ?? '');
    $status = sanitize_input($_POST['status'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate required fields
    if ($schedule_id <= 0 || empty($completion_date) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Schedule, completion date, and status are required']);
        exit;
    }
    
    // Validate date
    if (!validateDate($completion_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid completion date format']);
        exit;
    }
    
    // Validate status
    if (!in_array($status, ['completed', 'pending'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Check if schedule exists
    $check_schedule = "SELECT id, schedule_type, frequency_value, frequency_unit FROM maintenance_schedules WHERE id = ?";
    $check_stmt = $conn->prepare($check_schedule);
    $check_stmt->bind_param('i', $schedule_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Maintenance schedule not found']);
        exit;
    }
    
    $schedule_data = $check_result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert new maintenance history entry
        $insert_query = "INSERT INTO maintenance_history (maintenance_schedule_id, completion_date, completed_by, status, notes, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param('issss', $schedule_id, $completion_date, $completed_by, $status, $notes);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to add maintenance history: ' . $conn->error);
        }
        
        // If status is 'completed', update the maintenance schedule
        if ($status === 'completed') {
            // Calculate next maintenance date based on schedule type
            $next_maintenance_date = calculateNextMaintenanceDate($completion_date, $schedule_data['schedule_type'], 
                                                               $schedule_data['frequency_value'], $schedule_data['frequency_unit']);
            
            // Update maintenance schedule
            $update_query = "UPDATE maintenance_schedules 
                           SET last_maintenance = ?, next_maintenance = ? 
                           WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('ssi', $completion_date, $next_maintenance_date, $schedule_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update maintenance schedule: ' . $conn->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Maintenance history added successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Edit an existing maintenance history entry
 */
function editMaintenanceHistory() {
    global $conn;
    
    // Get form data
    $history_id = (int)($_POST['history_id'] ?? 0);
    $schedule_id = (int)($_POST['maintenance_schedule_id'] ?? 0);
    $completion_date = sanitize_input($_POST['completion_date'] ?? '');
    $completed_by = sanitize_input($_POST['completed_by'] ?? '');
    $status = sanitize_input($_POST['status'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate required fields
    if ($history_id <= 0 || $schedule_id <= 0 || empty($completion_date) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'History ID, schedule, completion date, and status are required']);
        exit;
    }
    
    // Validate date
    if (!validateDate($completion_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid completion date format']);
        exit;
    }
    
    // Validate status
    if (!in_array($status, ['completed', 'pending'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Check if history entry exists
    $check_history = "SELECT id, status FROM maintenance_history WHERE id = ?";
    $check_stmt = $conn->prepare($check_history);
    $check_stmt->bind_param('i', $history_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Maintenance history entry not found']);
        exit;
    }
    
    $old_history_data = $check_result->fetch_assoc();
    $old_status = $old_history_data['status'];
    
    // Check if schedule exists
    $check_schedule = "SELECT id, schedule_type, frequency_value, frequency_unit FROM maintenance_schedules WHERE id = ?";
    $check_stmt = $conn->prepare($check_schedule);
    $check_stmt->bind_param('i', $schedule_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Maintenance schedule not found']);
        exit;
    }
    
    $schedule_data = $check_result->fetch_assoc();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update maintenance history entry
        $update_query = "UPDATE maintenance_history 
                       SET maintenance_schedule_id = ?, completion_date = ?, completed_by = ?, status = ?, notes = ? 
                       WHERE id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('issssi', $schedule_id, $completion_date, $completed_by, $status, $notes, $history_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update maintenance history: ' . $conn->error);
        }
        
        // If status changed to 'completed', update the maintenance schedule
        if ($status === 'completed' && $old_status !== 'completed') {
            // Calculate next maintenance date based on schedule type
            $next_maintenance_date = calculateNextMaintenanceDate($completion_date, $schedule_data['schedule_type'], 
                                                               $schedule_data['frequency_value'], $schedule_data['frequency_unit']);
            
            // Update maintenance schedule
            $update_query = "UPDATE maintenance_schedules 
                           SET last_maintenance = ?, next_maintenance = ? 
                           WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('ssi', $completion_date, $next_maintenance_date, $schedule_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update maintenance schedule: ' . $conn->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Maintenance history updated successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete a maintenance history entry
 */
function deleteMaintenanceHistory() {
    global $conn;
    
    // Get history ID
    $history_id = (int)($_POST['history_id'] ?? 0);
    
    if ($history_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid history ID']);
        exit;
    }
    
    // Check if history entry exists
    $check_history = "SELECT id FROM maintenance_history WHERE id = ?";
    $check_stmt = $conn->prepare($check_history);
    $check_stmt->bind_param('i', $history_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Maintenance history entry not found']);
        exit;
    }
    
    // Delete maintenance history entry
    $delete_query = "DELETE FROM maintenance_history WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param('i', $history_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Maintenance history entry deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete maintenance history: ' . $conn->error]);
    }
}

/**
 * Calculate next maintenance date based on schedule type and frequency
 * 
 * @param string $last_date Last maintenance date (YYYY-MM-DD)
 * @param string $schedule_type Schedule type (daily, weekly, monthly, quarterly, yearly, custom)
 * @param int $frequency_value Frequency value (for custom schedule)
 * @param string $frequency_unit Frequency unit (days, weeks, months)
 * @return string Next maintenance date (YYYY-MM-DD)
 */
function calculateNextMaintenanceDate($last_date, $schedule_type, $frequency_value = null, $frequency_unit = null) {
    $date = new DateTime($last_date);
    
    switch ($schedule_type) {
        case 'daily':
            $date->add(new DateInterval('P1D'));
            break;
        case 'weekly':
            $date->add(new DateInterval('P1W'));
            break;
        case 'monthly':
            $date->add(new DateInterval('P1M'));
            break;
        case 'quarterly':
            $date->add(new DateInterval('P3M'));
            break;
        case 'yearly':
            $date->add(new DateInterval('P1Y'));
            break;
        case 'custom':
            if ($frequency_value <= 0) {
                $frequency_value = 1;
            }
            
            switch ($frequency_unit) {
                case 'days':
                    $date->add(new DateInterval('P' . $frequency_value . 'D'));
                    break;
                case 'weeks':
                    $date->add(new DateInterval('P' . $frequency_value . 'W'));
                    break;
                case 'months':
                    $date->add(new DateInterval('P' . $frequency_value . 'M'));
                    break;
                default:
                    $date->add(new DateInterval('P' . $frequency_value . 'D'));
                    break;
            }
            break;
        default:
            $date->add(new DateInterval('P1D'));
            break;
    }
    
    return $date->format('Y-m-d');
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