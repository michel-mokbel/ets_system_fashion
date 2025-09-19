<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
    $role = $_GET['role'] ?? '';
    $store_id = $_GET['store_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $where = '1=1';
    $params = [];
    $types = '';
    if ($role) {
        $where .= ' AND u.role = ?';
        $params[] = $role;
        $types .= 's';
    }
    if ($store_id) {
        $where .= ' AND u.store_id = ?';
        $params[] = $store_id;
        $types .= 'i';
    }
    if ($status) {
        $where .= ' AND u.status = ?';
        $params[] = $status;
        $types .= 's';
    }
    $sql = "SELECT u.id, u.username, u.full_name, u.role, u.status, u.store_id, s.name as store_name FROM users u LEFT JOIN stores s ON u.store_id = s.id WHERE $where ORDER BY u.username";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $users]);
    exit;
}

if ($action === 'edit') {
    $id = $_POST['id'] ?? 0;
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $store_id = $_POST['store_id'] ?? null;
    $status = trim($_POST['status'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!$id || !$username || !$role || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Handle null store_id properly
    if ($store_id === '' || $store_id === null) {
        $store_id = null;
    }
    
    // If password is provided, update it too
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username=?, full_name=?, email=?, role=?, store_id=?, password=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssissi', $username, $full_name, $email, $role, $store_id, $hashed_password, $status, $id);
    } else {
        // Update without changing password
        $sql = "UPDATE users SET username=?, full_name=?, email=?, role=?, store_id=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssisi', $username, $full_name, $email, $role, $store_id, $status, $id);
    }
    
    $success = $stmt->execute();
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $conn->error]);
    }
    exit;
}

if ($action === 'create' || $action === 'add') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $store_id = $_POST['store_id'] ?? null;
    $password = trim($_POST['password'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    
    if (!$username || !$role || !$password) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields: username, role, and password are required']);
        exit;
    }
    
    // Check if username already exists
    $check_sql = "SELECT id FROM users WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    
    // Insert new user
    $sql = "INSERT INTO users (username, full_name, email, role, store_id, password, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    // Handle null store_id properly
    if ($store_id === '' || $store_id === null) {
        $store_id = null;
    }
    
    $stmt->bind_param('ssssiss', $username, $full_name, $email, $role, $store_id, $password, $status);
    $success = $stmt->execute();
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'User created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
    }
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? 0;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID']);
        exit;
    }
    $sql = "DELETE FROM users WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success]);
    exit;
}

if ($action === 'reset_password') {
    $id = $_POST['id'] ?? 0;
    $new_password = $_POST['new_password'] ?? '';
    if (!$id || !$new_password) {
        echo json_encode(['success' => false, 'message' => 'Missing user ID or password']);
        exit;
    }
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $hashed, $id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success]);
    exit;
}

// Default: invalid action

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit; 