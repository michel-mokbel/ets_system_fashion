<?php
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

// Check if this is for a specific category (subcategory viewing) or DataTables listing
if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
    // This is for viewing subcategories of a specific category
    viewCategorySubcategories();
} else {
    // This is for DataTables server-side processing of all subcategories
    getAllSubcategories();
}

/**
 * Get subcategories for a specific category (for modal viewing)
 */
function viewCategorySubcategories() {
    global $conn;
    
    $category_id = intval($_POST['category_id']);
    
    try {
        // Get subcategories for the specified category with item counts
        $query = "SELECT 
                    sc.id,
                    sc.name,
                    sc.description,
                    sc.created_at,
                    COUNT(DISTINCT i.id) as item_count
                  FROM subcategories sc
                  LEFT JOIN inventory_items i ON sc.id = i.subcategory_id
                  WHERE sc.category_id = ?
                  GROUP BY sc.id, sc.name, sc.description, sc.created_at
                  ORDER BY sc.name ASC";
        
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        
        $stmt->bind_param('i', $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $subcategories = [];
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name'])
            ];
        }
        $response = [
            'success' => true,
            'subcategories' => $subcategories
        ];
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log("Error in viewCategorySubcategories: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get all subcategories for DataTables server-side processing
 */
function getAllSubcategories() {
    global $conn;
    
    try {
        // Get DataTables parameters
        $draw = $_POST['draw'] ?? 1;
        $start = $_POST['start'] ?? 0;
        $length = $_POST['length'] ?? 10;
        $search = $_POST['search']['value'] ?? '';

        // Get filter parameters
        $name = $_POST['name'] ?? '';
        $category_id = $_POST['category_id'] ?? '';

        // Get total count of all subcategories
        $total_query = "SELECT COUNT(*) as total FROM subcategories";
        $total_stmt = $conn->prepare($total_query);
        if ($total_stmt === false) {
            throw new Exception("Failed to prepare total count query: " . $conn->error);
        }
        $total_stmt->execute();
        $total_result = $total_stmt->get_result();
        $total_records = $total_result->fetch_assoc()['total'];

        // Build query with category information and item counts
        $where = [];
        $params = [];
        $param_types = '';

        // Base query
        $query = "SELECT 
                    sc.id,
                    sc.name,
                    sc.description,
                    sc.created_at,
                    c.name as category_name,
                    COUNT(DISTINCT i.id) as item_count
                  FROM subcategories sc
                  LEFT JOIN categories c ON sc.category_id = c.id
                  LEFT JOIN inventory_items i ON sc.id = i.subcategory_id
                  ";

        // Apply search
        if (!empty($search)) {
            $where[] = "(sc.name LIKE ? OR sc.description LIKE ? OR c.name LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $param_types .= 'sss';
        }

        // Apply name filter
        if (!empty($name)) {
            $where[] = "sc.name LIKE ?";
            $params[] = "%$name%";
            $param_types .= 's';
        }

        // Apply category filter
        if (!empty($category_id)) {
            $where[] = "sc.category_id = ?";
            $params[] = $category_id;
            $param_types .= 'i';
        }

        // Add WHERE clause if conditions exist
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }

        // Group by subcategory
        $query .= " GROUP BY sc.id, sc.name, sc.description, sc.created_at, c.name";

        // Get filtered count
        $filtered_count = $total_records; // Default to total if no filters
        if (!empty($where)) {
            $count_query = "SELECT COUNT(DISTINCT sc.id) as total FROM subcategories sc LEFT JOIN categories c ON sc.category_id = c.id";
            if (!empty($where)) {
                $count_query .= " WHERE " . implode(' AND ', $where);
            }
            
            $count_stmt = $conn->prepare($count_query);
            if ($count_stmt === false) {
                throw new Exception("Failed to prepare filtered count query: " . $conn->error);
            }
            
            if (!empty($params)) {
                $count_stmt->bind_param($param_types, ...$params);
            }
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $filtered_count = $count_result->fetch_assoc()['total'];
        }

        // Add sorting
        $order_column = isset($_POST['order'][0]['column']) ? $_POST['order'][0]['column'] : 0;
        $order_dir = isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

        // Columns for sorting
        $columns = ['sc.name', 'sc.description', 'c.name', 'item_count', 'sc.created_at'];
        if (isset($columns[$order_column])) {
            $query .= " ORDER BY " . $columns[$order_column] . " " . $order_dir;
        } else {
            $query .= " ORDER BY sc.name ASC";
        }

        // Add pagination
        $query .= " LIMIT ?, ?";
        $params[] = $start;
        $params[] = $length;
        $param_types .= 'ii';

        // Prepare and execute the final query
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Failed to prepare main query: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Format data for DataTables
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name']),
                'description' => htmlspecialchars($row['description'] ?? ''),
                'category_name' => htmlspecialchars($row['category_name'] ?? ''),
                'item_count' => intval($row['item_count']),
                'created_at' => $row['created_at'],
                'created_at_formatted' => date('Y-m-d H:i', strtotime($row['created_at']))
            ];
        }

        $response = [
            'draw' => intval($draw),
            'recordsTotal' => intval($total_records),
            'recordsFiltered' => intval($filtered_count),
            'data' => $data
        ];
        
        echo json_encode($response);

    } catch (Exception $e) {
        error_log("Error in getAllSubcategories: " . $e->getMessage());
        echo json_encode([
            'error' => 'Database error: ' . $e->getMessage(),
            'draw' => intval($_POST['draw'] ?? 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => []
        ]);
    }
} 