<?php
/**
 * Legacy Inventory Management Console
 * -----------------------------------
 * Provides the classic inventory grid used by administrators and inventory
 * managers to inspect catalog items, trigger incoming stock workflows, and
 * launch transfer modals. While largely superseded by `admin/store_items.php`,
 * the page still wires up the shared header for authentication, exposes
 * DataTable filters, and delegates client-side orchestration to
 * `assets/js/inventory.js` together with endpoints like
 * `ajax/get_inventory.php` and `ajax/process_inventory.php`.
 */
ob_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Only admins and inventory managers can access inventory management
if (!can_access_inventory()) {
    redirect('../index.php');
}
?>

<body>
    <div class="container-fluid py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
            <h1 class="mb-3 mb-md-0"><?php echo getTranslation('inventory.title'); ?></h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('inventory.add_item'); ?>
                </button>
                <button class="btn btn-success" id="openIncomingStockModal">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Incoming Stock
                </button>
                <button class="btn btn-warning" id="openTransferStockModal">
                    <i class="bi bi-arrow-left-right me-1"></i> Transfer Stock
                </button>
            </div>
        </div>
        <div class="card mb-4 w-100">
            <div class="card-body">
                <form id="filterForm" class="row g-3 mb-0">
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
                    <?php if (is_admin() || is_inventory_manager()): ?>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label"><?php echo getTranslation('inventory.store'); ?></label>
                        <select class="form-select" name="store_id">
                            <option value=""><?php echo getTranslation('inventory.all_stores'); ?></option>
                            <?php
                            $stores_query = "SELECT * FROM stores ORDER BY name";
                            $stores_result = $conn->query($stores_query);
                            
                            if ($stores_result && $stores_result->num_rows > 0) {
                                while ($store = $stores_result->fetch_assoc()) {
                                    $selected = (isset($_GET['store_id']) && $_GET['store_id'] == $store['id']) ? 'selected' : '';
                                    echo "<option value='" . $store['id'] . "' $selected>" . htmlspecialchars($store['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
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
        <div class="card w-100">
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
                                <th width="150"><?php echo getTranslation('inventory.actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTable will populate this -->
                        </tbody>
                    </table>
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

        <!-- Adjust Stock Modal -->
        <div class="modal fade" id="adjustStockModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Adjust Stock (Main Warehouse)</h5>
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

        <!-- Incoming Stock Modal -->
        <div class="modal fade" id="incomingStockModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Incoming Stock (Batch)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="incomingStockForm">
                        <div class="modal-body">
                            <div id="incomingStockRows">
                                <!-- Rows will be added dynamically -->
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="addIncomingStockRow">
                                <i class="bi bi-plus"></i> Add Item
                            </button>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Save Incoming Stock</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                                    <div class="col-md-4 text-muted"><?php echo getTranslation('inventory.subcategory'); ?>:</div>
                                    <div class="col-md-8" id="detailItemSubcategory"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted"><?php echo getTranslation('inventory.current_stock'); ?>:</div>
                                    <div class="col-md-8" id="detailItemStock"></div>
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
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Barcode:</div>
                                    <div class="col-md-8 d-flex align-items-center gap-2">
                                        <span id="detailItemBarcode"></span>
                                        <button id="openBarcodeBtn" class="btn btn-outline-secondary btn-sm" style="display:none;" target="_blank">
                                            <i class="bi bi-upc"></i> View Barcode
                                        </button>
                                    </div>
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

        <script src="../assets/js/inventory.js"></script>
        <script src="../assets/js/debug.js"></script>
        <?php require_once '../includes/footer.php'; ?> 

        <!-- Stock Transfer Modal -->
        <div class="modal fade" id="transferStockModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Transfer Stock from Warehouse</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="transferStockForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="create_shipment">
                        
                        <div class="modal-body">
                            <!-- Step 1: Select Destination -->
                            <div id="transferStep1" class="transfer-step">
                                <h6 class="mb-3">Step 1: Select Destination Store</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Source Store</label>
                                        <div class="form-control bg-light">
                                            <i class="bi bi-building me-2"></i>
                                            <strong>Main Warehouse</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Destination Store *</label>
                                        <select class="form-select" name="destination_store_id" id="transferDestinationStore" required>
                                            <option value="">Select Destination Store</option>
                                            <?php
                                            $stores_query = "SELECT * FROM stores WHERE id != 1 ORDER BY name";
                                            $stores_result = $conn->query($stores_query);
                                            if ($stores_result && $stores_result->num_rows > 0) {
                                                while ($store = $stores_result->fetch_assoc()) {
                                                    echo "<option value='{$store['id']}'>" . htmlspecialchars($store['name']) . "</option>";
                                                }
                                            }
                                            ?>
                                </select>
                            </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes about this transfer"></textarea>
                                </div>
                                <div class="mt-3 text-end">
                                    <button type="button" class="btn btn-primary" id="transferNextToItems" disabled>
                                        Next: Select Items <i class="bi bi-arrow-right"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Select Items -->
                            <div id="transferStep2" class="transfer-step" style="display: none;">
                                <h6 class="mb-3">Step 2: Select Items and Organize into Boxes</h6>
                                
                                <!-- Search and Filter -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" id="transferItemSearch" placeholder="Search items...">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" id="transferCategoryFilter">
                                            <option value="">All Categories</option>
                                            <?php
                                            $categories_query = "SELECT id, name FROM categories ORDER BY name";
                                            $categories_result = $conn->query($categories_query);
                                            if ($categories_result && $categories_result->num_rows > 0) {
                                                while ($category = $categories_result->fetch_assoc()) {
                                                    echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="btn btn-secondary w-100" id="loadTransferSourceItems">
                                            <i class="bi bi-arrow-clockwise me-1"></i> Load Items
                                        </button>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-success w-100" id="addNewTransferBox">
                                            <i class="bi bi-plus-circle me-1"></i> Add Box
                                        </button>
                                    </div>
                                </div>
        
                                <!-- Transfer Layout -->
                                <div class="row">
                                    <!-- Available Items (Left Panel) -->
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6>Available Items</h6>
                                            <small class="text-muted" id="transferAvailableItemsCount">0 items</small>
                                        </div>
                                        <div class="table-responsive border rounded" style="max-height: 500px; overflow-y: auto;">
                                            <table class="table table-sm table-hover mb-0" id="transferAvailableItemsTable">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Stock</th>
                                                        <th>Price</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Items loaded via AJAX -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
        
                                    <!-- Transfer Boxes (Right Panel) -->
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6>Transfer Boxes</h6>
                                            <div>
                                                <small class="text-muted me-3">
                                                    <span id="transferTotalBoxesCount">1</span> boxes, 
                                                    <span id="transferTotalItemsCount">0</span> items
                                                </small>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="transferPreviewPrint">
                                                    <i class="bi bi-printer me-1"></i> Preview Print
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Boxes Container -->
                                        <div id="transferBoxesContainer" style="max-height: 500px; overflow-y: auto;">
                                            <!-- Initial Box -->
                                            <div class="card mb-3 transfer-box" data-box-id="1">
                                                <div class="card-header d-flex justify-content-between align-items-center py-2">
                                                    <div>
                                                        <strong>Box #1</strong>
                                                        <span class="badge bg-info ms-2 box-item-count">0 items</span>
                                                    </div>
                                                    <div>
                                                        <input type="text" class="form-control form-control-sm d-inline-block box-label" 
                                                               placeholder="Box label (optional)" style="width: 150px;">
                                                        <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-box" 
                                                                data-box-id="1" style="display: none;">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="card-body p-2">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-0 box-items-table">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th width="35%">Item</th>
                                                                    <th width="15%">Quantity</th>
                                                                    <th width="15%">Price</th>
                                                                    <th width="20%">Average Cost</th>
                                                                    <th width="15%">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr class="empty-box-message">
                                                                    <td colspan="5" class="text-center text-muted py-3">
                                                                        <i class="bi bi-box me-2"></i>No items in this box
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" id="transferBackToStores">
                                        <i class="bi bi-arrow-left"></i> Back
                                    </button>
                                    <div>
                                        <button type="button" class="btn btn-info me-2" id="transferPrintPackingSlips" disabled>
                                            <i class="bi bi-printer me-1"></i> Print Packing Slips
                                        </button>
                                        <button type="button" class="btn btn-success" id="createTransferShipment" disabled>
                                            <i class="bi bi-check-circle me-1"></i> Create Transfer
                                        </button>
                                    </div>
                            </div>
                            </div>
                        </div>
                    </form>
                </div>
                </div>
            </div>
        </div> 

    <!-- Print Packing Slips Modal -->
    <div class="modal fade" id="transferPrintPackingModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-printer me-2"></i>Packing Slips Preview
                    </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <strong>Transfer Route:</strong> 
                            <span id="transferPrintRoute"></span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-primary" id="transferPrintAllSlips">
                                <i class="bi bi-printer me-1"></i> Print All Slips
                            </button>
                        </div>
                    </div>
                    <div id="transferPackingSlipsContainer">
                        <!-- Packing slips will be generated here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div> 
    </div>
</body> 