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

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'add':
        addItem();
        break;
    case 'edit':
        editItem();
        break;
    case 'delete':
        deleteItem();
        break;
    case 'adjust_stock':
        adjustStock();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

/**
 * Process and upload an image
 * 
 * @param array $image The $_FILES['item_image'] array
 * @return string|null The path to the saved image or null on failure
 */
function processImageUpload($image) {
    // Variable to store image path
    $image_path = null;
    
    // Check if image was uploaded properly
    if (!isset($image) || $image['error'] !== UPLOAD_ERR_OK) {
        error_log("Image upload error: " . ($image['error'] ?? 'Unknown error'));
        return null;
    }
    
    // Calculate the absolute path to the upload directory
    $project_root = realpath(dirname(__FILE__) . '/../');
    $upload_dir = $project_root . '/uploads/inventory/';
    
    // Log the directories for debugging
    error_log("Project root directory: " . $project_root);
    error_log("Upload directory: " . $upload_dir);
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create directory: " . $upload_dir);
            return null;
        }
    }
    
    // Debug directory permissions
    error_log("Upload directory: " . $upload_dir . " - Writable: " . (is_writable($upload_dir) ? 'Yes' : 'No'));
    
    // Get file info and generate a unique filename
    $file_info = pathinfo($image['name']);
    $file_extension = strtolower($file_info['extension']);
    
    // Check if the file is an allowed image type
    $allowed_extensions = ['jpg', 'jpeg', 'png'];
    if (!in_array($file_extension, $allowed_extensions)) {
        error_log("Invalid image format: " . $file_extension);
        return null;
    }
    
    // Generate a unique filename
    $new_filename = 'item_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // Debug file upload
    error_log("Attempting to move file: " . $image['tmp_name'] . " to " . $upload_path);
    
    // Move the uploaded file with error logging
    if (move_uploaded_file($image['tmp_name'], $upload_path)) {
        // Set the relative path to be stored in the database
        $image_path = 'uploads/inventory/' . $new_filename;
        error_log("File uploaded successfully: " . $image_path);
    } else {
        // Handle upload error with more detailed logging
        $upload_error = error_get_last();
        error_log("Upload failed: " . ($upload_error ? $upload_error['message'] : 'Unknown error'));
        
        // Try with chmod to ensure permissions
        @chmod($upload_dir, 0777);
        
        if (move_uploaded_file($image['tmp_name'], $upload_path)) {
            $image_path = 'uploads/inventory/' . $new_filename;
            error_log("File uploaded successfully after chmod: " . $image_path);
        } else {
            error_log("File upload failed even after chmod");
            return null;
        }
    }
    
    return $image_path;
}

/**
 * Add a new inventory item
 */
function addItem() {
    global $conn;
    
    // Full form data logging
    error_log("ADD ITEM - Raw POST data: " . json_encode($_POST));
    
    // Get form data
    $item_code = sanitize_input($_POST['item_code'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $base_price = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0.0;
    $selling_price = isset($_POST['selling_price']) && floatval($_POST['selling_price']) > 0
        ? floatval($_POST['selling_price'])
        : $base_price;
    $size = sanitize_input($_POST['size'] ?? '');
    $color = sanitize_input($_POST['color'] ?? '');
    $material = sanitize_input($_POST['material'] ?? '');
    $brand = sanitize_input($_POST['brand'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'discontinued']) ? $_POST['status'] : 'active';
    
    // Debug all sanitized form values
    error_log("ADD ITEM - Processed form data:");
    error_log("- item_code: " . $item_code);
    error_log("- name: " . $name);
    error_log("- description length: " . strlen($description));
    error_log("- category_id: " . ($category_id ?? 'NULL'));
    error_log("- subcategory_id: " . ($subcategory_id ?? 'NULL'));
    error_log("- base_price: " . $base_price);
    error_log("- selling_price: " . $selling_price);
    error_log("- size: " . $size);
    error_log("- color: " . $color);
    error_log("- material: " . $material);
    error_log("- brand: " . $brand);
    error_log("- status: " . $status);
    
    // Process image upload if present
    $image_path = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $image_path = processImageUpload($_FILES['item_image']);
        error_log("Processed image path: " . ($image_path ?? 'null'));
    }
    
    // Validate required fields
    if (empty($item_code) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Item code and name are required']);
        exit;
    }
    
    // Check if item code already exists
    $check_query = "SELECT id FROM inventory_items WHERE item_code = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $item_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Item code already exists']);
        exit;
    }
    
    // Insert new item (new schema)
    $container_id = !empty($_POST['container_id']) ? (int)$_POST['container_id'] : null;
    $insert_query = "INSERT INTO inventory_items (item_code, name, description, category_id, subcategory_id, base_price, selling_price, size, color, material, brand, image_path, status, container_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param('sssiddsssssssi', $item_code, $name, $description, $category_id, $subcategory_id, $base_price, $selling_price, $size, $color, $material, $brand, $image_path, $status, $container_id);    
    if ($insert_stmt->execute()) {
        $item_id = $conn->insert_id;
        error_log("Item inserted successfully with ID: " . $item_id);
        
        // Verify the inserted data
        $verify_query = "SELECT * FROM inventory_items WHERE id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param('i', $item_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $inserted_data = $verify_result->fetch_assoc();
        
        error_log("Verification of inserted data:");
        error_log("- Inserted item_code: " . ($inserted_data['item_code'] ?? 'NULL'));

        // Check if this should be a shared barcode (same price as existing items)
        $shared_check = $conn->prepare("SELECT barcode, is_shared FROM barcodes WHERE price = ? LIMIT 1");
        $shared_check->bind_param('d', $selling_price);
        $shared_check->execute();
        $shared_result = $shared_check->get_result();
        error_log("Found " . $shared_result->num_rows . " existing barcodes with price: " . $selling_price);
        
        if ($shared_result->num_rows > 0) {
            $existing_barcode = $shared_result->fetch_assoc();
            // Use existing shared barcode
            $barcode = $existing_barcode['barcode'];
            $is_shared = 1;
            error_log("Using existing shared barcode: " . $barcode);
        } else {
            // Generate new barcode only if no shared barcode exists
            $barcode = generate_barcode($item_id, $selling_price);
            $is_shared = 0;
            error_log("Creating new unique barcode: " . $barcode);
        }
        
        // Always insert barcode record for the new item
        // Check if this barcode-item combination already exists
        $barcode_check = $conn->prepare("SELECT id FROM barcodes WHERE barcode = ? AND item_id = ?");
        $barcode_check->bind_param('si', $barcode, $item_id);
        $barcode_check->execute();
        $barcode_result = $barcode_check->get_result();
        
        if ($barcode_result->num_rows == 0) {
            // Only insert if this barcode-item combination doesn't exist
            $barcode_sql = "INSERT INTO barcodes (barcode, item_id, price, is_shared) VALUES (?, ?, ?, ?)";
            $barcode_stmt = $conn->prepare($barcode_sql);
            $barcode_stmt->bind_param('sidi', $barcode, $item_id, $selling_price, $is_shared);
            $insert_result = $barcode_stmt->execute();
            error_log("Barcode insert result: " . ($insert_result ? 'SUCCESS' : 'FAILED') . " for item_id: " . $item_id);
            
            if (!$insert_result) {
                error_log("Barcode insert error: " . $barcode_stmt->error);
            }
        } else {
            error_log("Barcode-item combination already exists for item_id: " . $item_id);
        }

        // Handle store assignments if provided
        if (isset($_POST['store_assignments']) && is_array($_POST['store_assignments'])) {
            $store_assignments = $_POST['store_assignments'];
            error_log("Processing store assignments: " . json_encode($store_assignments));
            
            foreach ($store_assignments as $store_id) {
                $store_id = (int)$store_id;
                if ($store_id > 0) {
                    // Insert store assignment
                    $assignment_sql = "INSERT INTO store_item_assignments (store_id, item_id, assigned_by) VALUES (?, ?, ?)";
                    $assignment_stmt = $conn->prepare($assignment_sql);
                    $assignment_stmt->bind_param('iii', $store_id, $item_id, $_SESSION['user_id']);
                    $assignment_result = $assignment_stmt->execute();
                    
                    if ($assignment_result) {
                        error_log("Store assignment created: store_id=$store_id, item_id=$item_id");
                    } else {
                        error_log("Failed to create store assignment: " . $assignment_stmt->error);
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Item added successfully']);
    } else {
        error_log("SQL Error when inserting item: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Failed to add item: ' . $conn->error]);
    }
}

/**
 * Edit an existing inventory item
 */
function editItem() {
    global $conn;
    
    // Full form data logging
    error_log("EDIT ITEM - Raw POST data: " . json_encode($_POST));
    
    // Get form data
    $item_id = (int)($_POST['item_id'] ?? 0);
    $item_code = sanitize_input($_POST['item_code'] ?? '');
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $subcategory_id = !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $base_price = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 0.0;
    $selling_price = isset($_POST['selling_price']) && floatval($_POST['selling_price']) > 0
        ? floatval($_POST['selling_price'])
        : $base_price;
    $size = sanitize_input($_POST['size'] ?? '');
    $color = sanitize_input($_POST['color'] ?? '');
    $material = sanitize_input($_POST['material'] ?? '');
    $brand = sanitize_input($_POST['brand'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'discontinued']) ? $_POST['status'] : 'active';
    
    // Debug all sanitized form values
    error_log("EDIT ITEM - Processed form data:");
    error_log("- item_id: " . $item_id);
    error_log("- item_code: " . $item_code);
    error_log("- name: " . $name);
    error_log("- description length: " . strlen($description));
    error_log("- category_id: " . ($category_id ?? 'NULL'));
    error_log("- subcategory_id: " . ($subcategory_id ?? 'NULL'));
    error_log("- base_price: " . $base_price);
    error_log("- selling_price: " . $selling_price);
    error_log("- size: " . $size);
    error_log("- color: " . $color);
    error_log("- material: " . $material);
    error_log("- brand: " . $brand);
    error_log("- status: " . $status);
    
    // Validate required fields
    if (empty($item_code) || empty($name) || $item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Item code, name, and ID are required']);
        exit;
    }
    
    // Check if item exists and get current image path
    $check_query = "SELECT image_path FROM inventory_items WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $item_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $current_data = $check_result->fetch_assoc();
    $current_image_path = $current_data['image_path'];
    
    error_log("Current item data:");
    error_log("- Current image path: " . ($current_image_path ?? 'null'));
    
    // Check if item code already exists for a different item
    $check_code_query = "SELECT id FROM inventory_items WHERE item_code = ? AND id != ?";
    $check_code_stmt = $conn->prepare($check_code_query);
    $check_code_stmt->bind_param('si', $item_code, $item_id);
    $check_code_stmt->execute();
    $check_code_result = $check_code_stmt->get_result();
    
    if ($check_code_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Item code already exists for another item']);
        exit;
    }
    
    // Process image upload and update
    $image_path = $current_image_path;
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
    
    error_log("Remove image flag: " . ($remove_image ? 'Yes' : 'No'));
    
    // Check if we need to remove the current image
    if ($remove_image && !empty($current_image_path)) {
        // Delete the physical file
        if (file_exists('../' . $current_image_path)) {
            if (unlink('../' . $current_image_path)) {
                error_log("Deleted existing image: " . $current_image_path);
            } else {
                error_log("Failed to delete existing image: " . $current_image_path);
            }
        } else {
            error_log("Image file does not exist: " . $current_image_path);
        }
        $image_path = null;
    }
    // Check if a new image was uploaded
    elseif (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $new_image_path = processImageUpload($_FILES['item_image']);
        error_log("New image path after processing: " . ($new_image_path ?? 'null'));
        
        if ($new_image_path) {
            // Delete old image if it exists
            if (!empty($current_image_path) && file_exists('../' . $current_image_path)) {
                if (unlink('../' . $current_image_path)) {
                    error_log("Deleted old image before replacing: " . $current_image_path);
                } else {
                    error_log("Failed to delete old image before replacing: " . $current_image_path);
                }
            }
            $image_path = $new_image_path;
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // The robust, error-checked update block starts here:
        $container_id = !empty($_POST['container_id']) ? (int)$_POST['container_id'] : null;
        $update_query = "UPDATE inventory_items 
            SET item_code = ?, name = ?, description = ?, category_id = ?, subcategory_id = ?, 
                base_price = ?, selling_price = ?, size = ?, color = ?, material = ?, brand = ?, 
                image_path = ?, status = ?, container_id = ?, updated_at = NOW() 
            WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            error_log("SQL error (inventory_items update): " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Failed to update item: ' . $conn->error]);
            $conn->rollback();
            exit;
        }
        $update_stmt->bind_param(
            'sssiiddssssssii',
            $item_code, $name, $description, $category_id, $subcategory_id,
            $base_price, $selling_price, $size, $color, $material, $brand,
            $image_path, $status, $container_id, $item_id
        );
        if (!$update_stmt->execute()) {
            error_log("SQL error (inventory_items execute): " . $update_stmt->error);
            echo json_encode(['success' => false, 'message' => 'Failed to execute item update: ' . $update_stmt->error]);
            $conn->rollback();
            exit;
        }
        
        // Verify the updated data
        $verify_query = "SELECT * FROM inventory_items WHERE id = ?";
        $verify_stmt = $conn->prepare($verify_query);
        $verify_stmt->bind_param('i', $item_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        $updated_data = $verify_result->fetch_assoc();
        
        error_log("Verification of updated data:");
        error_log("- Updated location: " . ($updated_data['location'] ?? 'NULL'));
        
        // After updating the item, update all store_inventory rows for this item to match the new selling_price
        error_log("Preparing store_inventory update: UPDATE store_inventory SET selling_price = $selling_price WHERE item_id = $item_id");
        $update_store_price = $conn->prepare("UPDATE store_inventory SET selling_price = ? WHERE item_id = ?");
        if ($update_store_price) {
            $update_store_price->bind_param('di', $selling_price, $item_id);
            if (!$update_store_price->execute()) {
                error_log("SQL error (store_inventory execute): " . $update_store_price->error);
                echo json_encode(['success' => false, 'message' => 'Failed to execute store prices update: ' . $update_store_price->error]);
                $conn->rollback();
                exit;
            }
        } else {
            error_log("SQL error (store_inventory update): " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Failed to update store prices: ' . $conn->error]);
            $conn->rollback();
            exit;
        }
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Exception during update: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update item: ' . $e->getMessage()]);
    }
}

/**
 * Delete an inventory item
 */
function deleteItem() {
    global $conn;
    
    // Get item ID
    $item_id = (int)($_POST['item_id'] ?? 0);
    
    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
        exit;
    }
    
    // Check if item exists and get image path
    $check_query = "SELECT image_path FROM inventory_items WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $item_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $item = $check_result->fetch_assoc();
    $image_path = $item['image_path'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete related transactions
        $delete_transactions = "DELETE FROM inventory_transactions WHERE item_id = ?";
        $transaction_stmt = $conn->prepare($delete_transactions);
        $transaction_stmt->bind_param('i', $item_id);
        $transaction_stmt->execute();
        
        // Delete item
        $delete_query = "DELETE FROM inventory_items WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param('i', $item_id);
        $delete_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Delete image file if exists
        if (!empty($image_path) && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        
        echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to delete item: ' . $e->getMessage()]);
    }
}

/**
 * Adjust inventory stock
 */
function adjustStock() {
    global $conn;
    
    // Get form data
    $item_id = (int)($_POST['item_id'] ?? 0);
    $transaction_type = sanitize_input($_POST['transaction_type'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    // Validate required fields
    if ($item_id <= 0 || empty($transaction_type) || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID, transaction type, or quantity']);
        exit;
    }
    
    // Validate transaction type
    if (!in_array($transaction_type, ['in', 'out', 'adjustment'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction type']);
        exit;
    }
    
    // Get current stock
    $check_query = "SELECT current_stock FROM inventory_items WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('i', $item_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    $current_stock = (int)$check_result->fetch_assoc()['current_stock'];
    
    // Calculate new stock based on transaction type
        $new_stock = $current_stock;
        
        switch ($transaction_type) {
            case 'in':
                $new_stock = $current_stock + $quantity;
                break;
            case 'out':
            if ($quantity > $current_stock) {
                echo json_encode(['success' => false, 'message' => 'Cannot remove more items than current stock']);
                exit;
            }
                $new_stock = $current_stock - $quantity;
                break;
            case 'adjustment':
                $new_stock = $quantity; // Direct adjustment
            $quantity = abs($new_stock - $current_stock); // Calculate the actual change
            $transaction_type = ($new_stock > $current_stock) ? 'in' : 'out';
                break;
        }
        
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update stock
        $update_query = "UPDATE inventory_items SET current_stock = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ii', $new_stock, $item_id);
        $update_stmt->execute();
        
        // Log transaction
        $transaction_query = "INSERT INTO inventory_transactions (item_id, transaction_type, quantity, reference_type, 
                                                                user_id, notes, transaction_date) 
                             VALUES (?, ?, ?, 'manual', ?, ?, NOW())";
        $transaction_stmt = $conn->prepare($transaction_query);
        $user_id = $_SESSION['user_id'];
        $transaction_stmt->bind_param('isiss', $item_id, $transaction_type, $quantity, $user_id, $notes);
        $transaction_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Stock adjusted successfully']);
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to adjust stock: ' . $e->getMessage()]);
    }
} 