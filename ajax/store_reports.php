<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_store_manager()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$store_id = $_SESSION['store_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'sales':
        // Sales report: total sales per day for this store
        $sql = "SELECT DATE(created_at) as sale_date, SUM(total_amount) as total_sales FROM invoices WHERE store_id = ? AND payment_status = 'paid' GROUP BY sale_date ORDER BY sale_date DESC LIMIT 30";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    case 'inventory':
        // Inventory report: show all items for this store, including those with stock = 0
        $sql = "SELECT ii.name as item_name, ii.item_code, IFNULL(si.current_stock, 0) as current_stock
                FROM inventory_items ii
                LEFT JOIN store_inventory si ON si.item_id = ii.id AND si.store_id = ?
                WHERE ii.status = 'active'
                ORDER BY ii.name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    case 'invoices':
        // Invoices report: detailed invoice information with payment breakdown
        $sql = "SELECT 
                    invoice_number,
                    customer_name,
                    customer_phone,
                    subtotal,
                    tax_amount,
                    discount_amount,
                    total_amount,
                    amount_paid,
                    cash_amount,
                    mobile_amount,
                    change_due,
                    payment_method,
                    payment_status,
                    status,
                    sales_person_id,
                    notes,
                    created_at,
                    updated_at
                FROM invoices 
                WHERE store_id = ? 
                AND (status = 'completed' OR status = 'paid' OR status IS NULL OR status = '')
                AND (payment_status = 'paid' OR payment_status = 'partial' OR payment_status IS NULL OR payment_status = '')
                ORDER BY created_at DESC 
                LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    case 'expenses':
        // Expenses report: date, category, amount for this store
        $sql = "SELECT expense_date, category, amount FROM expenses WHERE store_id = ? ORDER BY expense_date DESC, created_at DESC LIMIT 30";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
} 