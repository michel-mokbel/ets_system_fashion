<?php
/**
 * POS barcode lookup endpoint.
 *
 * Searches for active items assigned to the current store by barcode, enforces
 * stock availability, and returns pricing information for the POS cart.
 */
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
$barcode = trim($_POST['barcode'] ?? '');
$store_id = $_SESSION['store_id'] ?? null;
if (empty($barcode)) {
    echo json_encode(['success' => false, 'message' => 'Barcode is required']);
    exit;
}
$item = get_item_by_barcode($barcode, $store_id);
if ($item) {
    echo json_encode(['success' => true, 'data' => $item]);
} else {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
} 