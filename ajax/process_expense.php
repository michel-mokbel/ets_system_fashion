<?php
/**
 * Expense submission and approval handler.
 *
 * Receives expense create/update/delete requests from both admin and store
 * interfaces, manages receipt uploads, enforces permissions, and returns JSON
 * confirmation messages.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        addExpense();
        break;
    case 'edit':
        editExpense();
        break;
    case 'delete':
        deleteExpense();
        break;
    case 'approve':
        approveExpense();
        break;
    case 'reject':
        rejectExpense();
        break;
    case 'list':
        listExpenses();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function addExpense() {
    global $conn, $user_id, $store_id, $role;
    if (!in_array($role, ['store_manager', 'admin', 'sales_person'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $status = 'pending';
    $receipt_image = '';
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
        $filename = 'expense_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $target = '../uploads/expenses/' . $filename;
        if (!is_dir('../uploads/expenses')) mkdir('../uploads/expenses', 0777, true);
        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target)) {
            $receipt_image = 'uploads/expenses/' . $filename;
        }
    }
    // Generate expense number
    $expense_number = 'EXP-' . strtoupper(uniqid());
    $stmt = $conn->prepare("INSERT INTO expenses (expense_number, store_id, category, description, amount, expense_date, receipt_image, status, added_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sissdsssis', $expense_number, $store_id, $category, $description, $amount, $expense_date, $receipt_image, $status, $user_id, $notes);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Expense added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add expense']);
    }
}

function editExpense() {
    global $conn, $user_id, $role;
    if (!in_array($role, ['store_manager', 'admin', 'sales_person'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    $expense_id = intval($_POST['expense_id'] ?? 0);
    
    // Check if sales person is trying to edit someone else's expense
    if ($role === 'sales_person') {
        $check_stmt = $conn->prepare("SELECT added_by FROM expenses WHERE id = ?");
        $check_stmt->bind_param('i', $expense_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_row = $check_result->fetch_assoc()) {
            if ($check_row['added_by'] != $user_id) {
                echo json_encode(['success' => false, 'message' => 'You can only edit your own expenses']);
                return;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Expense not found']);
            return;
        }
    }
    
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $receipt_image = '';
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
        $filename = 'expense_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $target = '../uploads/expenses/' . $filename;
        if (!is_dir('../uploads/expenses')) mkdir('../uploads/expenses', 0777, true);
        if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $target)) {
            $receipt_image = 'uploads/expenses/' . $filename;
        }
    }
    $set_image = $receipt_image ? ", receipt_image = ?" : '';
    $sql = "UPDATE expenses SET category = ?, description = ?, amount = ?, expense_date = ?, notes = ?$set_image WHERE id = ?";
    $params = [$category, $description, $amount, $expense_date, $notes];
    $types = 'ssdssi';
    if ($receipt_image) {
        $params[] = $receipt_image;
        $types = 'ssdssis';
    }
    $params[] = $expense_id;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Expense updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update expense']);
    }
}

function deleteExpense() {
    global $conn, $user_id, $role;
    if (!in_array($role, ['store_manager', 'admin', 'sales_person'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    $expense_id = intval($_POST['expense_id'] ?? 0);
    
    // Check if sales person is trying to delete someone else's expense
    if ($role === 'sales_person') {
        $check_stmt = $conn->prepare("SELECT added_by FROM expenses WHERE id = ?");
        $check_stmt->bind_param('i', $expense_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_row = $check_result->fetch_assoc()) {
            if ($check_row['added_by'] != $user_id) {
                echo json_encode(['success' => false, 'message' => 'You can only delete your own expenses']);
                return;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Expense not found']);
            return;
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param('i', $expense_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Expense deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete expense']);
    }
}

function approveExpense() {
    global $conn, $user_id, $role;
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only admin can approve expenses']);
        return;
    }
    $expense_id = intval($_POST['expense_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE expenses SET status = 'approved', approved_by = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $user_id, $expense_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Expense approved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve expense']);
    }
}

function rejectExpense() {
    global $conn, $user_id, $role;
    if ($role !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Only admin can reject expenses']);
        return;
    }
    $expense_id = intval($_POST['expense_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE expenses SET status = 'rejected', approved_by = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $user_id, $expense_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Expense rejected']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject expense']);
    }
}

function listExpenses() {
    global $conn, $user_id, $role, $store_id;
    $where = '1=1';
    $params = [];
    $types = '';
    if ($role === 'store_manager') {
        $where .= ' AND store_id = ?';
        $params[] = $store_id;
        $types .= 'i';
    } else if ($role === 'sales_person') {
        // Sales person can only see their own expenses
        $where .= ' AND added_by = ?';
        $params[] = $user_id;
        $types .= 'i';
    } else if ($role === 'admin' && isset($_GET['store_id']) && $_GET['store_id']) {
        $where .= ' AND store_id = ?';
        $params[] = intval($_GET['store_id']);
        $types .= 'i';
    }
    if (isset($_GET['status']) && $_GET['status']) {
        $where .= ' AND status = ?';
        $params[] = $_GET['status'];
        $types .= 's';
    }
    $sql = "SELECT e.*, s.name AS store_name FROM expenses e LEFT JOIN stores s ON e.store_id = s.id WHERE $where ORDER BY expense_date DESC, e.created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $expenses]);
} 