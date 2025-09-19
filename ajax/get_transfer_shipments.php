<?php
/**
 * Lists transfer shipments for the transfer management dashboard.
 *
 * Returns shipment metadata, statuses, and related store information with
 * pagination support. Restricted to users with transfer permissions and
 * responses are DataTables-compatible JSON.
 */
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user has access to transfers
if (!can_access_transfers()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        getShipmentsList();
        break;
    case 'details':
        getShipmentDetails();
        break;
    case 'items':
        getShipmentItems();
        break;
    case 'boxes':
        getShipmentBoxes();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Get list of transfer shipments
 */
function getShipmentsList() {
    global $conn;
    
    $store_id = $_SESSION['store_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? '';
    $status_filter = $_POST['status'] ?? '';
    $transfer_type_filter = $_POST['transfer_type'] ?? '';
    $source_filter = $_POST['source_store_id'] ?? '';
    $destination_filter = $_POST['destination_store_id'] ?? '';
    
    // Build query based on user role
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Role-based filtering
    if ($user_role !== 'admin' && $store_id) {
        $where_conditions[] = "(ts.source_store_id = ? OR ts.destination_store_id = ?)";
        $params[] = $store_id;
        $params[] = $store_id;
        $param_types .= 'ii';
    }
    
    // Status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "ts.status = ?";
        $params[] = $status_filter;
        $param_types .= 's';
    }
    
    // Transfer type filter
    if (!empty($transfer_type_filter)) {
        if ($transfer_type_filter === 'legacy') {
            $where_conditions[] = "ts.transfer_type IS NULL";
        } else {
            $where_conditions[] = "ts.transfer_type = ?";
            $params[] = $transfer_type_filter;
            $param_types .= 's';
        }
    }
    
    // Source store filter
    if (!empty($source_filter)) {
        $where_conditions[] = "ts.source_store_id = ?";
        $params[] = $source_filter;
        $param_types .= 'i';
    }
    
    // Destination store filter
    if (!empty($destination_filter)) {
        $where_conditions[] = "ts.destination_store_id = ?";
        $params[] = $destination_filter;
        $param_types .= 'i';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "SELECT 
                ts.id,
                ts.shipment_number,
                ts.source_store_id,
                ts.destination_store_id,
                s1.name as source_store_name,
                s1.store_code as source_store_code,
                s2.name as destination_store_name,
                s2.store_code as destination_store_code,
                ts.total_items,
                ts.status,
                COALESCE(ts.transfer_type, 'legacy') as transfer_type,
                ts.notes,
                ts.created_at,
                ts.packed_at,
                ts.shipped_at,
                ts.received_at,
                u1.full_name as created_by_name,
                u2.full_name as packed_by_name,
                u3.full_name as received_by_name
              FROM transfer_shipments ts
              LEFT JOIN stores s1 ON ts.source_store_id = s1.id
              LEFT JOIN stores s2 ON ts.destination_store_id = s2.id
              LEFT JOIN users u1 ON ts.created_by = u1.id
              LEFT JOIN users u2 ON ts.packed_by = u2.id
              LEFT JOIN users u3 ON ts.received_by = u3.id
              $where_clause
              ORDER BY ts.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $shipments = [];
    while ($row = $result->fetch_assoc()) {
        // Derive destinations from transactions for multi-destination shipments
        $destinations = [];
        $dest_q = $conn->prepare("SELECT DISTINCT it.store_id, s.name, s.store_code
                                  FROM inventory_transactions it
                                  JOIN stores s ON s.id = it.store_id
                                  WHERE it.reference_type = 'shipment' AND it.transfer_type = 'inbound' AND it.shipment_id = ?");
        if ($dest_q) {
            $dest_q->bind_param('i', $row['id']);
            $dest_q->execute();
            $dest_r = $dest_q->get_result();
            while ($d = $dest_r->fetch_assoc()) {
                $destinations[] = [
                    'id' => (int)$d['store_id'],
                    'name' => $d['name'],
                    'code' => $d['store_code']
                ];
            }
        }

        $shipments[] = [
            'id' => $row['id'],
            'shipment_number' => $row['shipment_number'],
            'source_store' => [
                'id' => $row['source_store_id'],
                'name' => $row['source_store_name'],
                'code' => $row['source_store_code']
            ],
            'destination_store' => [
                'id' => $row['destination_store_id'],
                'name' => $row['destination_store_name'],
                'code' => $row['destination_store_code']
            ],
            'destinations' => $destinations,
            'total_items' => $row['total_items'],
            'status' => $row['status'],
            'transfer_type' => $row['transfer_type'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            'packed_at' => $row['packed_at'],
            'shipped_at' => $row['shipped_at'],
            'received_at' => $row['received_at'],
            'created_by_name' => $row['created_by_name'],
            'packed_by_name' => $row['packed_by_name'],
            'received_by_name' => $row['received_by_name']
        ];
    }
    
    echo json_encode(['success' => true, 'shipments' => $shipments]);
}

/**
 * Get shipment details
 */
function getShipmentDetails() {
    global $conn;
    
    $shipment_id = (int)($_POST['shipment_id'] ?? 0);
    
    if ($shipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
        exit;
    }
    
    // Check access rights
    $store_id = $_SESSION['store_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? '';
    
    $access_check = "";
    $params = [$shipment_id];
    $param_types = 'i';
    
    if ($user_role !== 'admin' && $store_id) {
        $access_check = " AND (ts.source_store_id = ? OR ts.destination_store_id = ?)";
        $params[] = $store_id;
        $params[] = $store_id;
        $param_types .= 'ii';
    }
    
    $query = "SELECT 
                ts.*,
                s1.name as source_store_name,
                s1.store_code as source_store_code,
                s2.name as destination_store_name,
                s2.store_code as destination_store_code,
                u1.full_name as created_by_name,
                u2.full_name as packed_by_name,
                u3.full_name as received_by_name
              FROM transfer_shipments ts
              LEFT JOIN stores s1 ON ts.source_store_id = s1.id
              LEFT JOIN stores s2 ON ts.destination_store_id = s2.id
              LEFT JOIN users u1 ON ts.created_by = u1.id
              LEFT JOIN users u2 ON ts.packed_by = u2.id
              LEFT JOIN users u3 ON ts.received_by = u3.id
              WHERE ts.id = ?" . $access_check;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Shipment not found or access denied']);
        exit;
    }
    
    $shipment = $result->fetch_assoc();

    // Derive destinations for details
    $destinations = [];
    $dest_q = $conn->prepare("SELECT DISTINCT it.store_id, s.name, s.store_code
                              FROM inventory_transactions it
                              JOIN stores s ON s.id = it.store_id
                              WHERE it.reference_type = 'shipment' AND it.transfer_type = 'inbound' AND it.shipment_id = ?");
    if ($dest_q) {
        $dest_q->bind_param('i', $shipment_id);
        $dest_q->execute();
        $dest_r = $dest_q->get_result();
        while ($d = $dest_r->fetch_assoc()) {
            $destinations[] = [
                'id' => (int)$d['store_id'],
                'name' => $d['name'],
                'code' => $d['store_code']
            ];
        }
    }

    echo json_encode(['success' => true, 'shipment' => $shipment, 'destinations' => $destinations]);
}

/**
 * Get shipment items
 */
function getShipmentItems() {
    global $conn;
    
    $shipment_id = (int)($_POST['shipment_id'] ?? 0);
    
    if ($shipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
        exit;
    }
    
    // Check access rights
    $store_id = $_SESSION['store_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? '';
    
    $access_check = "";
    $access_params = [];
    $access_types = '';
    
    if ($user_role !== 'admin' && $store_id) {
        $access_check = " AND (ts.source_store_id = ? OR ts.destination_store_id = ?)";
        $access_params = [$store_id, $store_id];
        $access_types = 'ii';
    }
    
    $query = "SELECT 
                ti.*,
                i.name as item_name,
                i.item_code,
                i.size,
                i.color,
                i.brand,
                c.name as category_name,
                sc.name as subcategory_name,
                b.barcode,
                tb.box_number,
                tb.box_label
              FROM transfer_items ti
              JOIN transfer_shipments ts ON ti.shipment_id = ts.id
              JOIN inventory_items i ON ti.item_id = i.id
              LEFT JOIN categories c ON i.category_id = c.id
              LEFT JOIN subcategories sc ON i.subcategory_id = sc.id
              JOIN barcodes b ON ti.barcode_id = b.id
              LEFT JOIN transfer_boxes tb ON ti.box_id = tb.id
              WHERE ti.shipment_id = ?" . $access_check . "
              ORDER BY tb.box_number, i.name";
    
    $params = array_merge([$shipment_id], $access_params);
    $param_types = 'i' . $access_types;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => $row['id'],
            'item_id' => $row['item_id'],
            'barcode_id' => $row['barcode_id'],
            'item_name' => $row['item_name'],
            'item_code' => $row['item_code'],
            'size' => $row['size'],
            'color' => $row['color'],
            'brand' => $row['brand'],
            'category_name' => $row['category_name'],
            'subcategory_name' => $row['subcategory_name'],
            'barcode' => $row['barcode'],
            'quantity_requested' => $row['quantity_requested'],
            'quantity_packed' => $row['quantity_packed'],
            'quantity_received' => $row['quantity_received'],
            'selling_price' => $row['selling_price'],
            'unit_cost' => $row['unit_cost'],
            'notes' => $row['notes'],
            'box_number' => $row['box_number'],
            'box_label' => $row['box_label']
        ];
    }
    
    echo json_encode(['success' => true, 'items' => $items]);
}

/**
 * Get shipment boxes
 */
function getShipmentBoxes() {
    global $conn;
    
    $shipment_id = (int)($_POST['shipment_id'] ?? 0);
    
    if ($shipment_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
        exit;
    }
    
    // Check access rights
    $store_id = $_SESSION['store_id'] ?? null;
    $user_role = $_SESSION['user_role'] ?? '';
    
    $access_check = "";
    $access_params = [];
    $access_types = '';
    
    if ($user_role !== 'admin' && $store_id) {
        $access_check = " AND (ts.source_store_id = ? OR ts.destination_store_id = ?)";
        $access_params = [$store_id, $store_id];
        $access_types = 'ii';
    }
    
    $query = "SELECT 
                tb.*,
                COUNT(ti.id) as item_count
              FROM transfer_boxes tb
              JOIN transfer_shipments ts ON tb.shipment_id = ts.id
              LEFT JOIN transfer_items ti ON tb.id = ti.box_id
              WHERE tb.shipment_id = ?" . $access_check . "
              GROUP BY tb.id
              ORDER BY tb.box_number";
    
    $params = array_merge([$shipment_id], $access_params);
    $param_types = 'i' . $access_types;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $boxes = [];
    while ($row = $result->fetch_assoc()) {
        $boxes[] = [
            'id' => $row['id'],
            'box_number' => $row['box_number'],
            'box_label' => $row['box_label'],
            'total_items' => $row['total_items'],
            'item_count' => $row['item_count'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'boxes' => $boxes]);
}
?> 