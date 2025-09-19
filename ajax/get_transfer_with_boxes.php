<?php
require_once 'ajax_session_init.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$shipment_id = (int)($_GET['shipment_id'] ?? 0);

if ($shipment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid shipment ID']);
    exit;
}

try {
    // Get transfer shipment details
    $shipment_query = "
        SELECT ts.*, 
               s1.name as source_store_name, s1.store_code as source_store_code,
               s2.name as destination_store_name, s2.store_code as destination_store_code,
               u1.username as created_by_username,
               u2.username as packed_by_username,
               u3.username as received_by_username
        FROM transfer_shipments ts
        JOIN stores s1 ON ts.source_store_id = s1.id
        JOIN stores s2 ON ts.destination_store_id = s2.id
        LEFT JOIN users u1 ON ts.created_by = u1.id
        LEFT JOIN users u2 ON ts.packed_by = u2.id
        LEFT JOIN users u3 ON ts.received_by = u3.id
        WHERE ts.id = ?
    ";
    
    $shipment_stmt = $conn->prepare($shipment_query);
    if (!$shipment_stmt) {
        error_log("Failed to prepare shipment query: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Database query failed']);
        exit;
    }
    $shipment_stmt->bind_param('i', $shipment_id);
    $shipment_stmt->execute();
    $shipment_result = $shipment_stmt->get_result();
    
    if ($shipment_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Transfer not found']);
        exit;
    }
    
    $shipment = $shipment_result->fetch_assoc();
    
    // Check if transfer_boxes table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'transfer_boxes'");
    $boxes_table_exists = ($table_check && $table_check->num_rows > 0);
    
    $boxes = [];
    $total_boxes = 0;
    $total_unique_items = 0;
    $total_quantity = 0;
    $total_value = 0;
    
    if ($boxes_table_exists) {
        // New multi-box system: Get transfer boxes with their items
        $boxes_query = "
            SELECT tb.*,
                   wb.box_name as warehouse_box_name,
                   wb.box_type as warehouse_box_type,
                   wb.box_number as warehouse_box_number,
                   COUNT(ti.id) as item_count,
                   SUM(ti.quantity_requested) as total_quantity,
                   SUM(ti.quantity_requested * ti.selling_price) as total_value
            FROM transfer_boxes tb
            LEFT JOIN warehouse_boxes wb ON tb.warehouse_box_id = wb.id
            LEFT JOIN transfer_items ti ON tb.id = ti.box_id
            WHERE tb.shipment_id = ?
            GROUP BY tb.id
            ORDER BY tb.box_number
        ";
        
        $boxes_stmt = $conn->prepare($boxes_query);
        if (!$boxes_stmt) {
            error_log("Failed to prepare boxes query: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database query failed']);
            exit;
        }
        $boxes_stmt->bind_param('i', $shipment_id);
        $boxes_stmt->execute();
        $boxes_result = $boxes_stmt->get_result();
        
        while ($box = $boxes_result->fetch_assoc()) {
            // Get items for this box
            $items_query = "
                SELECT ti.*, 
                       ii.name as item_name, ii.item_code,
                       b.barcode,
                       COALESCE(ti.destination_store_id, it.store_id) AS dest_store_id,
                       COALESCE(s_ti.name, s_it.name) AS dest_store_name,
                       COALESCE(s_ti.store_code, s_it.store_code) AS dest_store_code
                FROM transfer_items ti
                JOIN inventory_items ii ON ti.item_id = ii.id
                JOIN barcodes b ON ti.barcode_id = b.id
                LEFT JOIN (
                    SELECT MAX(id) AS it_id, item_id, barcode_id
                    FROM inventory_transactions
                    WHERE reference_type = 'shipment' AND transfer_type = 'inbound' AND shipment_id = ?
                    GROUP BY item_id, barcode_id
                ) itg ON itg.item_id = ti.item_id AND itg.barcode_id = ti.barcode_id
                LEFT JOIN inventory_transactions it ON it.id = itg.it_id
                LEFT JOIN stores s_it ON s_it.id = it.store_id
                LEFT JOIN stores s_ti ON s_ti.id = ti.destination_store_id
                WHERE ti.box_id = ?
                ORDER BY ii.name
            ";
            
            $items_stmt = $conn->prepare($items_query);
            if (!$items_stmt) {
                error_log("Failed to prepare items query: " . $conn->error);
                continue;
            }
            $items_stmt->bind_param('ii', $shipment_id, $box['id']);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            
            $box['items'] = $items;
            $boxes[] = $box;
            
            $total_unique_items += count($items);
            $total_quantity += (int)$box['total_quantity'];
            $total_value += (float)$box['total_value'];
        }
        
        $total_boxes = count($boxes);
        
    } else {
        // Fallback: Old system without boxes - create a single default box
        error_log("transfer_boxes table not found, using fallback for shipment $shipment_id");
        
        $items_query = "
            SELECT ti.*, 
                   ii.name as item_name, ii.item_code,
                   b.barcode,
                   COALESCE(ti.destination_store_id, it.store_id) AS dest_store_id,
                   COALESCE(s_ti.name, s_it.name) AS dest_store_name,
                   COALESCE(s_ti.store_code, s_it.store_code) AS dest_store_code
            FROM transfer_items ti
            JOIN inventory_items ii ON ti.item_id = ii.id
            JOIN barcodes b ON ti.barcode_id = b.id
            LEFT JOIN (
                SELECT MAX(id) AS it_id, item_id, barcode_id
                FROM inventory_transactions
                WHERE reference_type = 'shipment' AND transfer_type = 'inbound' AND shipment_id = ?
                GROUP BY item_id, barcode_id
            ) itg ON itg.item_id = ti.item_id AND itg.barcode_id = ti.barcode_id
            LEFT JOIN inventory_transactions it ON it.id = itg.it_id
            LEFT JOIN stores s_it ON s_it.id = it.store_id
            LEFT JOIN stores s_ti ON s_ti.id = ti.destination_store_id
            WHERE ti.shipment_id = ?
            ORDER BY ii.name
        ";
        
        $items_stmt = $conn->prepare($items_query);
        if (!$items_stmt) {
            error_log("Failed to prepare fallback items query: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Database query failed']);
            exit;
        }
        $items_stmt->bind_param('ii', $shipment_id, $shipment_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        $items = [];
        while ($item = $items_result->fetch_assoc()) {
            $items[] = $item;
            $total_quantity += (int)$item['quantity_requested'];
            $total_value += (float)($item['quantity_requested'] * $item['selling_price']);
        }
        
        // Create a single default box
        $boxes = [[
            'id' => 1,
            'box_number' => 1,
            'box_label' => 'Default Box',
            'item_count' => count($items),
            'total_quantity' => $total_quantity,
            'total_value' => $total_value,
            'items' => $items
        ]];
        
        $total_boxes = 1;
        $total_unique_items = count($items);
    }
    
    // Derive per-destination breakdown from inventory_transactions
    $destinations = [];
    $dest_stmt = $conn->prepare("SELECT it.store_id, s.name, s.store_code,
                                        SUM(it.quantity) as total_qty,
                                        SUM(it.quantity * COALESCE(it.unit_price, 0)) as total_value
                                 FROM inventory_transactions it
                                 JOIN stores s ON s.id = it.store_id
                                 WHERE it.reference_type = 'shipment' AND it.transfer_type = 'inbound' AND it.shipment_id = ?
                                 GROUP BY it.store_id, s.name, s.store_code");
    if ($dest_stmt) {
        $dest_stmt->bind_param('i', $shipment_id);
        $dest_stmt->execute();
        $dest_res = $dest_stmt->get_result();
        while ($d = $dest_res->fetch_assoc()) {
            $destinations[] = [
                'id' => (int)$d['store_id'],
                'name' => $d['name'],
                'code' => $d['store_code'],
                'total_quantity' => (int)$d['total_qty'],
                'total_value' => (float)$d['total_value']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'shipment' => $shipment,
        'boxes' => $boxes,
        'destinations' => $destinations,
        'summary' => [
            'total_boxes' => $total_boxes,
            'total_unique_items' => $total_unique_items,
            'total_quantity' => $total_quantity,
            'total_value' => $total_value
        ],
        'using_boxes_table' => $boxes_table_exists
    ]);
    
} catch (Exception $e) {
    error_log("Get transfer with boxes error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to fetch transfer details: ' . $e->getMessage()]);
}
?> 