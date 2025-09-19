<?php
/**
 * Admin dashboard data provider.
 *
 * Accepts an `action` query parameter (`stats`, `recent_sales`, `low_stock`) and
 * returns JSON responses that populate the administrator landing page cards and
 * tables. Each branch performs role checks, runs the relevant aggregate SQL
 * query, and standardizes the response structure to `{ success, data }` so the
 * front-end can render the payload without additional transformation.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'stats':
        // Dashboard stats: total items, low stock, total sales today, pending containers
        $total_items = 0;
        $low_stock = 0;
        $total_sales_today = 0;
        $pending_containers = 0;
        $q1 = $conn->query("SELECT COUNT(*) as count FROM inventory_items WHERE status = 'active'");
        if ($q1 && ($row = $q1->fetch_assoc())) $total_items = $row['count'];
        $q2 = $conn->query("SELECT COUNT(*) as count FROM inventory_items WHERE current_stock <= minimum_stock AND status = 'active'");
        if ($q2 && ($row = $q2->fetch_assoc())) $low_stock = $row['count'];
        $q3 = $conn->query("SELECT SUM(total_amount) as total FROM invoices WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
        if ($q3 && ($row = $q3->fetch_assoc())) $total_sales_today = $row['total'] ?? 0;
        $q4 = $conn->query("SELECT COUNT(*) as count FROM containers WHERE status = 'pending'");
        if ($q4 && ($row = $q4->fetch_assoc())) $pending_containers = $row['count'];
        echo json_encode(['success' => true, 'data' => [
            'total_items' => $total_items,
            'low_stock' => $low_stock,
            'total_sales_today' => number_format($total_sales_today, 2),
            'pending_containers' => $pending_containers
        ]]);
        break;
    case 'recent_sales':
        // Recent sales: last 10 paid invoices
        $sql = "SELECT i.invoice_number, i.created_at, s.name as store_name, i.customer_name, i.total_amount FROM invoices i LEFT JOIN stores s ON i.store_id = s.id WHERE i.payment_status = 'paid' ORDER BY i.created_at DESC LIMIT 10";
        $result = $conn->query($sql);
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    case 'low_stock':
        // Low stock items: top 10
        $sql = "SELECT item_code, name, current_stock, minimum_stock FROM inventory_items WHERE current_stock <= minimum_stock AND status = 'active' ORDER BY (minimum_stock - current_stock) DESC LIMIT 10";
        $result = $conn->query($sql);
        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
} 