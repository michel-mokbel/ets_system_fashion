<?php
/**
 * Simple Shift Management Functions
 * Provides persistent shift tracking that survives session timeouts
 */

/**
 * Start a new shift for a user or resume an existing active shift
 * @param int $user_id
 * @param int $store_id
 * @return array|false Returns shift data or false on error
 */
function start_or_resume_shift($user_id, $store_id) {
    global $conn;
    
    try {
        // First, check if user has an active shift
        $check_stmt = $conn->prepare("SELECT id, start_time FROM shifts WHERE user_id = ? AND status = 'active' LIMIT 1");
        $check_stmt->bind_param('i', $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Resume existing active shift
            error_log("Shift Management: Resuming active shift ID {$row['id']} for user $user_id");
            return [
                'shift_id' => $row['id'],
                'start_time' => $row['start_time'],
                'is_new' => false
            ];
        }
        
        // No active shift found, create a new one
        $start_time = date('Y-m-d H:i:s');
        $insert_stmt = $conn->prepare("INSERT INTO shifts (user_id, store_id, start_time, status) VALUES (?, ?, ?, 'active')");
        $insert_stmt->bind_param('iis', $user_id, $store_id, $start_time);
        
        if ($insert_stmt->execute()) {
            $shift_id = $conn->insert_id;
            error_log("Shift Management: Created new shift ID $shift_id for user $user_id");
            return [
                'shift_id' => $shift_id,
                'start_time' => $start_time,
                'is_new' => true
            ];
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Shift Management Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current active shift for a user
 * @param int $user_id
 * @return array|null Returns shift data or null if no active shift
 */
function get_active_shift($user_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT id, user_id, store_id, start_time, status FROM shifts WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Shift Management Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Close a shift
 * @param int $shift_id
 * @return bool Success status
 */
function close_shift($shift_id) {
    global $conn;
    
    try {
        error_log("Close shift: Starting closure for shift ID $shift_id");
        
        // First check if the shift exists and is active
        $check_stmt = $conn->prepare("SELECT id, status FROM shifts WHERE id = ?");
        $check_stmt->bind_param('i', $shift_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            error_log("Close shift: Shift ID $shift_id not found");
            return false;
        }
        
        $shift_data = $check_result->fetch_assoc();
        error_log("Close shift: Found shift ID $shift_id with status '{$shift_data['status']}'");
        
        if ($shift_data['status'] !== 'active') {
            error_log("Close shift: Shift ID $shift_id is not active (status: {$shift_data['status']})");
            return false;
        }
        
        // Now close the shift
        $end_time = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE shifts SET end_time = ?, status = 'closed' WHERE id = ? AND status = 'active'");
        $stmt->bind_param('si', $end_time, $shift_id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            error_log("Close shift: Update executed, affected rows: $affected_rows");
            
            if ($affected_rows > 0) {
                error_log("Shift Management: Closed shift ID $shift_id");
                return true;
            } else {
                error_log("Close shift: No rows affected when closing shift ID $shift_id");
                return false;
            }
        } else {
            error_log("Close shift: Failed to execute update for shift ID $shift_id - " . $stmt->error);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Shift Management Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get shift duration in seconds
 * @param string $start_time
 * @param string|null $end_time If null, uses current time
 * @return int Duration in seconds
 */
function get_shift_duration($start_time, $end_time = null) {
    $start = strtotime($start_time);
    $end = $end_time ? strtotime($end_time) : time();
    return $end - $start;
}

/**
 * Format shift duration for display
 * @param int $duration_seconds
 * @return string Formatted duration (e.g., "2 hours, 30 minutes")
 */
function format_shift_duration($duration_seconds) {
    $hours = floor($duration_seconds / 3600);
    $minutes = floor(($duration_seconds % 3600) / 60);
    
    if ($hours === 0) {
        return "$minutes minutes";
    } elseif ($minutes === 0) {
        return "$hours hours";
    } else {
        return "$hours hours, $minutes minutes";
    }
}

/**
 * Close all active shifts for a user (cleanup function)
 * @param int $user_id
 * @return bool Success status
 */
function close_all_user_shifts($user_id) {
    global $conn;
    
    try {
        $end_time = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE shifts SET end_time = ?, status = 'closed' WHERE user_id = ? AND status = 'active'");
        $stmt->bind_param('si', $end_time, $user_id);
        
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            if ($affected > 0) {
                error_log("Shift Management: Closed $affected active shifts for user $user_id");
            }
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Shift Management Error: " . $e->getMessage());
        return false;
    }
}
?>
