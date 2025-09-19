<?php
ob_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Only admins and inventory managers can manage items and store assignments
if (!can_access_inventory()) {
    redirect('../index.php');
}
?>

<body>
    <!-- Hidden CSRF token for AJAX requests -->
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div class="container-fluid py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
            <h1 class="mb-3 mb-md-0"><?php echo getTranslation('inventory.title'); ?> & Store Assignments</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('inventory.add_item'); ?>
                </button>
                <button class="btn btn-success" id="manageAssignmentsBtn">
                    <i class="bi bi-diagram-3 me-1"></i> Manage Store Assignments
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4 w-100">
            <div class="card-body">
                <form id="filterForm" class="row g-3 mb-0">
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label">Search by Item Code</label>
                        <input type="text" class="form-control" name="item_code" placeholder="e.g. ITM-001">
                        <small class="text-muted">Press Enter or click Apply Filters to search</small>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label">Search by Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g. T-Shirt">
                        <small class="text-muted">Press Enter or click Apply Filters to search</small>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label"><?php echo getTranslation('inventory.category'); ?></label>
                        <select class="form-select" name="category">
                            <option value=""><?php echo getTranslation('common.all'); ?></option>
                            <?php
                            $categories_query = "SELECT * FROM categories ORDER BY name";
                            $categories_result = $conn->query($categories_query);
                            
                            if ($categories_result && $categories_result->num_rows > 0) {
                                while ($category = $categories_result->fetch_assoc()) {
                                    $selected = (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : '';
                                    echo "<option value='" . $category['id'] . "' $selected>" . htmlspecialchars($category['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label"><?php echo getTranslation('inventory.stock_status'); ?></label>
                        <select class="form-select" name="stock_status">
                            <option value=""><?php echo getTranslation('common.all'); ?></option>
                            <option value="low" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'low') ? 'selected' : ''; ?>>
                                <?php echo getTranslation('dashboard.low_stock'); ?>
                            </option>
                            <option value="out" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'out') ? 'selected' : ''; ?>>
                                <?php echo getTranslation('inventory.out_of_stock'); ?>
                            </option>
                            <option value="normal" <?php echo (isset($_GET['stock_status']) && $_GET['stock_status'] == 'normal') ? 'selected' : ''; ?>>
                                <?php echo getTranslation('inventory.normal_stock'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label"><?php echo getTranslation('inventory.status'); ?></label>
                        <select class="form-select" name="status">
                            <option value=""><?php echo getTranslation('common.all'); ?></option>
                            <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>
                                <?php echo getTranslation('common.active'); ?>
                            </option>
                            <option value="discontinued" <?php echo (isset($_GET['status']) && $_GET['status'] == 'discontinued') ? 'selected' : ''; ?>>
                                <?php echo getTranslation('inventory.discontinued'); ?>
                            </option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('inventory.apply_filters'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card w-100 mobile-optimized">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" id="inventoryTable">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th><?php echo getTranslation('inventory.item_code'); ?></th>
                                <th><?php echo getTranslation('inventory.name'); ?></th>
                                <th><?php echo getTranslation('inventory.category'); ?></th>
                                <th>Container</th>
                                <th><?php echo getTranslation('inventory.stock_status'); ?></th>
                                <th>Total Stock</th>
                                <th><?php echo getTranslation('inventory.base_price'); ?></th>
                                <th><?php echo getTranslation('inventory.selling_price'); ?></th>
                                <th><?php echo getTranslation('inventory.status'); ?></th>
                                <th width="200"><?php echo getTranslation('inventory.actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTable will populate this -->
                        </tbody>
                    </table>
                </div>
                <!-- Mobile Cards container (used on small screens) -->
                <div id="inventoryCards" class="mobile-card-container"></div>
            </div>
        </div>
    </div>

        <!-- Add Item Modal -->
        <div class="modal fade" id="addItemModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo getTranslation('inventory.add_item'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="../ajax/process_inventory.php" method="POST" class="needs-validation" data-reload-table="#inventoryTable" novalidate enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="itemCode" class="form-label"><?php echo getTranslation('inventory.item_code'); ?> *</label>
                                    <input type="text" class="form-control" id="itemCode" name="item_code" required>
                                    <div class="invalid-feedback">
                                        <?php echo getTranslation('common.required'); ?>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="itemName" class="form-label"><?php echo getTranslation('inventory.name'); ?> *</label>
                                    <input type="text" class="form-control" id="itemName" name="name" required>
                                    <div class="invalid-feedback">
                                        <?php echo getTranslation('common.required'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="itemDescription" class="form-label"><?php echo getTranslation('inventory.description'); ?></label>
                                <textarea class="form-control" id="itemDescription" name="description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="itemCategory" class="form-label"><?php echo getTranslation('inventory.category'); ?></label>
                                    <select class="form-select" id="itemCategory" name="category_id">
                                        <option value=""><?php echo getTranslation('inventory.select_category'); ?></option>
                                        <?php
                                        $categories_result = $conn->query($categories_query);
                                        if ($categories_result && $categories_result->num_rows > 0) {
                                            while ($category = $categories_result->fetch_assoc()) {
                                                echo "<option value='" . $category['id'] . "'>" . htmlspecialchars($category['name']) . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="itemSubcategory" class="form-label">Subcategory</label>
                                    <select class="form-select" id="itemSubcategory" name="subcategory_id">
                                        <option value="">Select Subcategory</option>
                                        <!-- Subcategories will be loaded dynamically -->
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="itemBasePrice" class="form-label">Base Price *</label>
                                    <input type="number" class="form-control" id="itemBasePrice" name="base_price" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="itemSellingPrice" class="form-label">Selling Price *</label>
                                    <input type="number" class="form-control" id="itemSellingPrice" name="selling_price" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="itemSize" class="form-label">Size</label>
                                    <input type="text" class="form-control" id="itemSize" name="size">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="itemMaterial" class="form-label">Material</label>
                                    <input type="text" class="form-control" id="itemMaterial" name="material">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="itemBrand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="itemBrand" name="brand">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="itemContainer" class="form-label">Container</label>
                                <select class="form-select" id="itemContainer" name="container_id">
                                    <option value="">Select Container (Optional)</option>
                                    <?php
                                    $containers_query = "SELECT id, container_number FROM containers ORDER BY container_number DESC";
                                    $containers_result = $conn->query($containers_query);
                                    if ($containers_result && $containers_result->num_rows > 0) {
                                        while ($container = $containers_result->fetch_assoc()) {
                                            echo "<option value='" . $container['id'] . "'>" . htmlspecialchars($container['container_number']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Optional: Associate this item with a container</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?php echo getTranslation('inventory.image'); ?></label>
                                <div class="row">
                                    <div class="col-md-3 mb-2" id="addItemImagePreviewContainer">
                                        <img id="addItemImagePreview" src="../assets/img/no-image.png" class="img-thumbnail" style="max-height: 150px; max-width: 100%;">
                                    </div>
                                    <div class="col-md-9">
                                        <input type="file" class="form-control mb-2" id="addItemImage" name="item_image" accept=".jpg,.jpeg,.png">
                                        <small class="text-muted">Supported formats: JPG, JPEG, PNG</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Store Assignments</label>
                                <div class="row">
                                    <?php
                                    $stores_query = "SELECT * FROM stores ORDER BY name";
                                    $stores_result = $conn->query($stores_query);
                                    if ($stores_result && $stores_result->num_rows > 0) {
                                        while ($store = $stores_result->fetch_assoc()) {
                                            $checked = 'checked';
                                            echo "<div class='col-md-4 mb-2'>";
                                            echo "<div class='form-check'>";
                                            echo "<input class='form-check-input' type='checkbox' name='store_assignments[]' value='{$store['id']}' id='store_{$store['id']}' $checked>";
                                            echo "<label class='form-check-label' for='store_{$store['id']}'>";
                                            echo htmlspecialchars($store['name']);
                                            echo "</label>";
                                            echo "</div>";
                                            echo "</div>";
                                        }
                                    }
                                    ?>
                                </div>
                                <small class="text-muted">Select which stores should have access to this item</small>
                            </div>
                            <div class="mb-3">
                                <label for="itemStatus" class="form-label"><?php echo getTranslation('inventory.status'); ?></label>
                                <select class="form-select" id="itemStatus" name="status">
                                    <option value="active"><?php echo getTranslation('common.active'); ?></option>
                                    <option value="discontinued"><?php echo getTranslation('inventory.discontinued'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <?php echo getTranslation('common.cancel'); ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <?php echo getTranslation('inventory.save'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Item Modal -->
        <div class="modal fade" id="editItemModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo getTranslation('inventory.edit_item'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="../ajax/process_inventory.php" method="POST" class="needs-validation" data-reload-table="#inventoryTable" novalidate enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="item_id" id="editItemId">
                        <input type="hidden" name="current_image" id="editItemCurrentImage">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editItemCode" class="form-label"><?php echo getTranslation('inventory.item_code'); ?> *</label>
                                    <input type="text" class="form-control" id="editItemCode" name="item_code" required>
                                    <div class="invalid-feedback">
                                        <?php echo getTranslation('common.required'); ?>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="editItemName" class="form-label"><?php echo getTranslation('inventory.name'); ?> *</label>
                                    <input type="text" class="form-control" id="editItemName" name="name" required>
                                    <div class="invalid-feedback">
                                        <?php echo getTranslation('common.required'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="editItemDescription" class="form-label"><?php echo getTranslation('inventory.description'); ?></label>
                                <textarea class="form-control" id="editItemDescription" name="description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editItemCategory" class="form-label"><?php echo getTranslation('inventory.category'); ?></label>
                                    <select class="form-select" id="editItemCategory" name="category_id">
                                        <option value=""><?php echo getTranslation('inventory.select_category'); ?></option>
                                        <?php
                                        $categories_result = $conn->query($categories_query);
                                        if ($categories_result && $categories_result->num_rows > 0) {
                                            while ($category = $categories_result->fetch_assoc()) {
                                                echo "<option value='" . $category['id'] . "'>" . htmlspecialchars($category['name']) . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="editItemSubcategory" class="form-label">Subcategory</label>
                                    <select class="form-select" id="editItemSubcategory" name="subcategory_id">
                                        <option value="">Select Subcategory</option>
                                        <!-- Subcategories will be loaded dynamically -->
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="editItemBasePrice" class="form-label">Base Price *</label>
                                    <input type="number" class="form-control" id="editItemBasePrice" name="base_price" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="editItemSellingPrice" class="form-label">Selling Price *</label>
                                    <input type="number" class="form-control" id="editItemSellingPrice" name="selling_price" min="0" step="0.01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="editItemSize" class="form-label">Size</label>
                                    <input type="text" class="form-control" id="editItemSize" name="size">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="editItemMaterial" class="form-label">Material</label>
                                    <input type="text" class="form-control" id="editItemMaterial" name="material">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="editItemBrand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="editItemBrand" name="brand">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="editItemContainer" class="form-label">Container</label>
                                <select class="form-select" id="editItemContainer" name="container_id">
                                    <option value="">Select Container (Optional)</option>
                                    <?php
                                    $containers_result = $conn->query($containers_query);
                                    if ($containers_result && $containers_result->num_rows > 0) {
                                        while ($container = $containers_result->fetch_assoc()) {
                                            echo "<option value='" . $container['id'] . "'>" . htmlspecialchars($container['container_number']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Optional: Associate this item with a container</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?php echo getTranslation('inventory.image'); ?></label>
                                <div class="row">
                                    <div class="col-md-3 mb-2" id="itemImagePreviewContainer">
                                        <img id="editItemImagePreview" src="../assets/img/no-image.png" class="img-thumbnail" style="max-height: 150px; max-width: 100%;">
                                    </div>
                                    <div class="col-md-9">
                                        <input type="file" class="form-control mb-2" id="editItemImage" name="item_image" accept=".jpg,.jpeg,.png">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="removeImage" name="remove_image" value="1">
                                            <label class="form-check-label" for="removeImage">
                                                <?php echo getTranslation('inventory.remove_image'); ?>
                                            </label>
                                        </div>
                                        <small class="text-muted">Supported formats: JPG, JPEG, PNG</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="editItemStatus" class="form-label"><?php echo getTranslation('inventory.status'); ?></label>
                                <select class="form-select" id="editItemStatus" name="status">
                                    <option value="active"><?php echo getTranslation('common.active'); ?></option>
                                    <option value="discontinued"><?php echo getTranslation('inventory.discontinued'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <?php echo getTranslation('common.cancel'); ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <?php echo getTranslation('inventory.save'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Item Details Modal -->
        <div class="modal fade" id="itemDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo getTranslation('inventory.item_details'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3 text-center">
                                <img id="detailItemImage" src="../assets/img/no-image.png" class="img-fluid rounded" style="max-height: 200px;">
                            </div>
                            <div class="col-md-8">
                                <h4 id="detailItemName" class="mb-3"></h4>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted"><?php echo getTranslation('inventory.item_code'); ?>:</div>
                                    <div class="col-md-8" id="detailItemCode"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted"><?php echo getTranslation('inventory.category'); ?>:</div>
                                    <div class="col-md-8" id="detailItemCategory"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Store Assignments:</div>
                                    <div class="col-md-8" id="detailItemStores"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted"><?php echo getTranslation('inventory.base_price'); ?>:</div>
                                    <div class="col-md-8" id="detailItemBasePrice"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted"><?php echo getTranslation('inventory.selling_price'); ?>:</div>
                                    <div class="col-md-8" id="detailItemSellingPrice"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted"><?php echo getTranslation('inventory.status'); ?>:</div>
                                    <div class="col-md-8" id="detailItemStatus"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h5><?php echo getTranslation('inventory.description'); ?></h5>
                            <p id="detailItemDescription" class="text-muted"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Assignments Modal -->
        <div class="modal fade" id="storeAssignmentsModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-diagram-3 me-2"></i>Store Item Assignments
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Search and Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="assignmentSearchInput" placeholder="Search items...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="assignmentCategoryFilter">
                                    <option value="">All Categories</option>
                                    <?php
                                    $categories_result = $conn->query($categories_query);
                                    if ($categories_result && $categories_result->num_rows > 0) {
                                        while ($category = $categories_result->fetch_assoc()) {
                                            echo "<option value='" . $category['id'] . "'>" . htmlspecialchars($category['name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-secondary w-100" id="loadAssignments">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Load Assignments
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success w-100" id="bulkAssignBtn" disabled>
                                    <i class="bi bi-check-square me-1"></i> Bulk Assign
                                </button>
                            </div>
                        </div>

                        <!-- Assignments Grid -->
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover table-striped align-middle mb-0" id="assignmentsTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="selectAllItems" class="form-check-input">
                                        </th>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <!-- Store columns will be added dynamically -->
                                    </tr>
                                </thead>
                                <tbody id="assignmentsTableBody">
                                    <!-- Data will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Loading indicator -->
                        <div id="assignmentLoadingIndicator" class="text-center p-4" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading store assignments...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Assignment Sub-Modal -->
        <div class="modal fade" id="bulkAssignModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Bulk Assign Items</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="bulkAssignForm">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="bulk_assign_items">
                            
                            <div class="mb-3">
                                <label class="form-label">Selected Items</label>
                                <div id="selectedItemsList" class="form-control" style="height: 100px; overflow-y: auto;">
                                    <!-- Selected items will be shown here -->
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Assign to Stores</label>
                                <div id="storeCheckboxes">
                                    <!-- Store checkboxes will be added here -->
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Assign Items</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <!-- Adjust Stock Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="adjustStockForm">
                    <div class="modal-body">
                        <input type="hidden" name="item_id" id="adjustStockItemId">
                        <div class="mb-3">
                            <label for="adjustStockQuantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="adjustStockQuantity" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="adjustStockType" class="form-label">Type</label>
                            <select class="form-select" name="transaction_type" id="adjustStockType" required>
                                <option value="in">Add</option>
                                <option value="out">Remove</option>
                                <option value="adjustment">Set Exact</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="adjustStockNotes" class="form-label">Notes</label>
                            <input type="text" class="form-control" name="notes" id="adjustStockNotes">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Adjust</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <script src="../assets/js/inventory.js"></script>
    <script src="../assets/js/store_items.js"></script>
    <?php require_once '../includes/footer.php'; ?>
</body> 