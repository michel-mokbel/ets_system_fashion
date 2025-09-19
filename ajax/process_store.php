<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}
$action = $_POST['action'] ?? '';
if ($action === 'add') {
    $store_code = sanitize_input($_POST['store_code'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $status = sanitize_input($_POST['status'] ?? 'active');
    if (empty($store_code) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Store code and name are required']);
        exit;
    }
    $check = $conn->prepare("SELECT id FROM stores WHERE store_code = ?");
    $check->bind_param('s', $store_code);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Store code already exists']);
        exit;
    }
    $query = "INSERT INTO stores (store_code, name, address, phone, manager_id, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssis', $store_code, $name, $address, $phone, $manager_id, $status);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Store added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add store: ' . $conn->error]);
    }
    exit;
} elseif ($action === 'edit') {
    $store_id = intval($_POST['store_id'] ?? 0);
    $store_code = sanitize_input($_POST['store_code'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $status = sanitize_input($_POST['status'] ?? 'active');
    if ($store_id <= 0 || empty($store_code) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Store ID, code, and name are required']);
        exit;
    }
    $check = $conn->prepare("SELECT id FROM stores WHERE store_code = ? AND id != ?");
    $check->bind_param('si', $store_code, $store_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Store code already exists']);
        exit;
    }
    $query = "UPDATE stores SET store_code = ?, name = ?, address = ?, phone = ?, manager_id = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssisi', $store_code, $name, $address, $phone, $manager_id, $status, $store_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Store updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update store: ' . $conn->error]);
    }
    exit;
} elseif ($action === 'delete') {
    $store_id = intval($_POST['store_id'] ?? 0);
    if ($store_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid store ID']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM stores WHERE id = ?");
    $stmt->bind_param('i', $store_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Store deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete store: ' . $conn->error]);
    }
    exit;
} elseif ($action === 'toggle_status') {
    $store_id = intval($_POST['store_id'] ?? 0);
    if ($store_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid store ID']);
        exit;
    }
    $get = $conn->prepare("SELECT status FROM stores WHERE id = ?");
    $get->bind_param('i', $store_id);
    $get->execute();
    $result = $get->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $new_status = ($row['status'] === 'active') ? 'inactive' : 'active';
        $update = $conn->prepare("UPDATE stores SET status = ? WHERE id = ?");
        $update->bind_param('si', $new_status, $store_id);
        if ($update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Store status updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Store not found']);
    }
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
} 