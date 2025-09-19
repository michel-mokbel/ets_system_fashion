<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}
$item_id = intval($_POST['item_id'] ?? 0);
if ($item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}
$query = "SELECT s.name as store_name, COALESCE(si.current_stock, 0) as current_stock, si.location_in_store as location, COALESCE(si.selling_price, i.selling_price) as selling_price
          FROM stores s
          LEFT JOIN store_inventory si ON si.store_id = s.id AND si.item_id = ?
          LEFT JOIN inventory_items i ON i.id = ?
          ORDER BY s.name ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $item_id, $item_id);
$stmt->execute();
$result = $stmt->get_result();
$stocks = [];
while ($row = $result->fetch_assoc()) {
    $stocks[] = [
        'store_name' => $row['store_name'],
        'current_stock' => $row['current_stock'],
        'location' => $row['location'],
        'selling_price' => $row['selling_price']
    ];
}
echo json_encode(['success' => true, 'stocks' => $stocks]); 