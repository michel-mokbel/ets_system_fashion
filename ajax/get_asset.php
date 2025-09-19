<?php
/**
 * Fetches a single asset record for editing dialogs.
 *
 * Requires admin authentication, validates the provided asset ID, and returns
 * the asset fields as JSON so the admin UI can populate the edit modal.
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

// Get asset ID
$asset_id = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;

if ($asset_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid asset ID']);
    exit;
}

// Query asset data
$query = "SELECT * FROM assets WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $asset_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Asset not found']);
    exit;
}

$asset = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'data' => $asset
]);
?> 