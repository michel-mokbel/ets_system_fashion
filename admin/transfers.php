<?php
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';



// Only admin, inventory managers, transfer managers, and store managers can access this page
if (!can_access_transfers()) {
    redirect('../index.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-arrow-left-right me-2"></i>Transfer Management</h1>
    <div>
        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#storeToStoreModal">
            <i class="bi bi-arrow-left-right me-1"></i> Store to Store Transfer
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTransferModal">
            <i class="bi bi-plus-circle me-1"></i> Create Transfer
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="transferFilterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="received">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Transfer Type</label>
                <select class="form-select" name="transfer_type" id="transferTypeFilter">
                    <option value="">All Types</option>
                    <option value="box">Box Transfer</option>
                    <option value="direct">Direct Transfer</option>
                    <option value="legacy">Legacy</option>
                </select>
            </div>
            <?php if (is_admin()): ?>
            <div class="col-md-3">
                <label class="form-label">Source Store</label>
                <select class="form-select" name="source_store_id" id="sourceStoreFilter">
                    <option value="">All Sources</option>
                    <?php
                    $stores_query = "SELECT id, name FROM stores WHERE status = 'active' ORDER BY name";
                    $stores_result = $conn->query($stores_query);
                    if ($stores_result) {
                    while ($store = $stores_result->fetch_assoc()) {
                        echo "<option value='{$store['id']}'>" . htmlspecialchars($store['name']) . "</option>";
                        }
                    } else {
                        error_log("Stores query failed: " . $conn->error);
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Destination Store</label>
                <select class="form-select" name="destination_store_id" id="destStoreFilter">
                    <option value="">All Destinations</option>
                    <?php
                    $stores_result = $conn->query($stores_query);
                    if ($stores_result) {
                    while ($store = $stores_result->fetch_assoc()) {
                        echo "<option value='{$store['id']}'>" . htmlspecialchars($store['name']) . "</option>";
                        }
                    } else {
                        error_log("Stores query (destination) failed: " . $conn->error);
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Transfers Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" id="transfersTable">
                <thead class="table-light">
                    <tr>
                        <th>Shipment #</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th>Type</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Transfer Modal -->
    <div class="modal fade" id="createTransferModal" tabindex="-1">
        <div class="modal-dialog" style="max-width: 95%; width: 95%;">
            <style>
                /* Prevent horizontal scrolling and optimize modal layout */
                #createTransferModal .modal-body {
                    font-size: 0.9rem;
                }
                
                #createTransferModal .table {
                    font-size: 0.85rem;
                }
                
                #createTransferModal .table th,
                #createTransferModal .table td {
                    padding: 0.5rem 0.4rem;
                    vertical-align: middle;
                }
                
                #createTransferModal .table th {
                    font-size: 0.8rem;
                    font-weight: 600;
                }
                
                #createTransferModal .badge {
                    font-size: 0.75rem;
                    padding: 0.25rem 0.5rem;
                }
                
                #createTransferModal .btn-sm {
                    font-size: 0.8rem;
                    padding: 0.25rem 0.5rem;
                }
                
                #createTransferModal .form-control,
                #createTransferModal .form-select {
                    font-size: 0.9rem;
                    padding: 0.375rem 0.5rem;
                }
                
                #createTransferModal .form-label {
                    font-size: 0.9rem;
                    font-weight: 600;
                    margin-bottom: 0.25rem;
                }
                
                #createTransferModal h6 {
                    font-size: 1rem;
                    font-weight: 600;
                }
                
                #createTransferModal .small {
                    font-size: 0.8rem;
                }
                
                /* Ensure tables don't overflow */
                #createTransferModal .table-responsive {
                    overflow-x: auto;
                }
                
                /* Optimize column widths for the available items table */
                #availableItemsTable th:nth-child(1) { width: 15%; } /* Store */
                #availableItemsTable th:nth-child(2) { width: 25%; } /* Item */
                #availableItemsTable th:nth-child(3) { width: 12%; } /* Stock */
                #availableItemsTable th:nth-child(4) { width: 12%; } /* Price */
                #availableItemsTable th:nth-child(5) { width: 12%; } /* Base Price */
                #availableItemsTable th:nth-child(6) { width: 12%; } /* Action */
                
                /* Prevent text wrapping in critical columns */
                #availableItemsTable td:nth-child(1),
                #availableItemsTable td:nth-child(3),
                #availableItemsTable td:nth-child(4),
                #availableItemsTable td:nth-child(5),
                #availableItemsTable td:nth-child(6) {
                    white-space: nowrap;
                }
                
                /* Allow text wrapping in item column */
                #availableItemsTable td:nth-child(2) {
                    white-space: normal;
                    word-wrap: break-word;
                }
                
                /* Special handling for warehouse items table */
                #warehouseItemsTable th:nth-child(1) { width: 20%; } /* Item */
                #warehouseItemsTable th:nth-child(2) { width: 12%; } /* Warehouse Stock */
                #warehouseItemsTable th:nth-child(3) { width: 12%; } /* Store Stock */
                #warehouseItemsTable th:nth-child(4) { width: 12%; } /* Price */
                #warehouseItemsTable th:nth-child(5) { width: 12%; } /* Average Cost */
                #warehouseItemsTable th:nth-child(6) { width: 12%; } /* Action */
                
                /* Optimize item column in warehouse table */
                #warehouseItemsTable td:nth-child(1) {
                    white-space: normal;
                    word-wrap: break-word;
                    word-break: break-word;
                    max-width: 0;
                    min-width: 150px;
                }
                
                /* Ensure all other columns don't wrap */
                #warehouseItemsTable td:not(:nth-child(1)) {
                    white-space: nowrap;
                }
                
                /* Force text wrapping in all table cells */
                #warehouseItemsTable td {
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                }
                
                /* Responsive adjustments for smaller screens */
                @media (max-width: 1200px) {
                    #createTransferModal .modal-dialog {
                        max-width: 95%;
                        margin: 1rem;
                    }
                    
                    #createTransferModal .table {
                        font-size: 0.8rem;
                    }
                    
                    #createTransferModal .table th,
                    #createTransferModal .table td {
                        padding: 0.4rem 0.3rem;
                    }
                }
            </style>
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createTransferForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="create_shipment">
                
                <div class="modal-body">
                    <!-- Step 1: Select Destination Store -->
                    <div id="step1" class="transfer-step">
                        <h6 class="mb-3">Step 1: Select Destination Store</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Source</label>
                                <div class="form-control bg-light">
                                    <i class="bi bi-building me-2"></i>
                                    <strong>Main Warehouse</strong>
                                </div>
                                <input type="hidden" name="source_store_id" value="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Destination Store(s) *</label>
                                <div id="destinationStoresContainer" class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                                    <?php
                                    $stores_query = "SELECT id, name, store_code FROM stores WHERE status = 'active' AND id != 1 ORDER BY name";
                                    $stores_result = $conn->query($stores_query);
                                    if ($stores_result) {
                                    while ($store = $stores_result->fetch_assoc()) {
                                            $id = (int)$store['id'];
                                            $label = htmlspecialchars($store['name']) . " (" . $store['store_code'] . ")";
                                            echo "<div class=\"form-check\">";
                                            echo "  <input class=\"form-check-input destination-store-checkbox\" type=\"checkbox\" id=\"dest_store_$id\" value=\"$id\">";
                                            echo "  <label class=\"form-check-label\" for=\"dest_store_$id\">$label</label>";
                                            echo "</div>";
                                        }
                                    } else {
                                        error_log("Stores query (destination modal) failed: " . $conn->error);
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes about this transfer"></textarea>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-primary" id="nextToTransferType" disabled>
                                Next: Choose Transfer Type <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- NEW STEP 2: Transfer Type Selection -->
                    <div id="step2" class="transfer-step" style="display: none;">
                        <h6 class="mb-3">Step 2: Choose Transfer Method</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card transfer-type-card h-100" data-type="box" style="cursor: pointer;">
                                    <div class="selection-indicator">
                                        <i class="bi bi-check"></i>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="bi bi-boxes display-4 text-primary mb-3"></i>
                                        <h5 class="card-title">Box Transfer</h5>
                                        <p class="text-muted mb-3">Transfer pre-organized warehouse boxes</p>
                                        <!-- <div class="text-start">
                                            <small class="text-muted">
                                                <i class="bi bi-check-circle text-success me-1"></i> Select from existing warehouse boxes<br>
                                                <i class="bi bi-check-circle text-success me-1"></i> Items already organized and packed<br>
                                                <i class="bi bi-check-circle text-success me-1"></i> Ideal for bulk transfers<br>
                                                <i class="bi bi-check-circle text-success me-1"></i> Faster processing
                                            </small>
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card transfer-type-card h-100" data-type="direct" style="cursor: pointer;">
                                    <div class="selection-indicator">
                                        <i class="bi bi-check"></i>
                                    </div>
                                    <div class="card-body text-center">
                                        <i class="bi bi-list-check display-4 text-success mb-3"></i>
                                        <h5 class="card-title">Direct Item Transfer</h5>
                                        <p class="text-muted mb-3">Select specific items from warehouse inventory</p>
                                        <!-- <div class="text-start">
                                            <small class="text-muted">
                                                <i class="bi bi-check-circle text-success me-1"></i> Browse all warehouse inventory<br>
                                                <i class="bi bi-check-circle text-success me-1"></i> Specify exact quantities needed<br>
                                                <i class="bi bi-check-circle text-success me-1"></i> Real-time stock checking<br>
                                                <i class="bi bi-check-circle text-success me-1"></i> Flexible item selection
                                            </small>
                                        </div> -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="transfer_type" id="transferType">
                        
                        <div class="mt-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" id="backToDestination">
                                <i class="bi bi-arrow-left me-1"></i> Back to Destination
                            </button>
                            <button type="button" class="btn btn-primary" id="nextFromType" disabled>
                                Continue <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 3A: Select Warehouse Boxes -->
                    <div id="step3a" class="transfer-step" style="display: none;">
                        <h6 class="mb-3">Step 3: Select Warehouse Boxes</h6>
                        
                        <!-- Search and Filter -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <form id="boxSearchForm" class="d-flex">
                                    <input type="text" class="form-control" id="boxSearch" placeholder="Search boxes...">
                                    <button type="submit" class="btn btn-outline-secondary ms-2">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="boxTypeFilter">
                                    <option value="">All Types</option>
                                    <!-- Types will be loaded dynamically -->
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-secondary w-100" id="loadWarehouseBoxes">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Load Boxes
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success w-100" id="selectAllBoxes">
                                    <i class="bi bi-check-all me-1"></i> Select All
                                </button>
                            </div>
                        </div>

                        <!-- Available Boxes -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="bi bi-boxes me-2"></i>Available Warehouse Boxes
                                </h6>
                                <small class="text-muted" id="availableBoxesCount">0 boxes</small>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0" id="availableBoxesTable">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th width="10%">
                                                    <input type="checkbox" id="selectAllBoxesCheckbox" class="form-check-input">
                                                </th>
                                                <th width="15%">Box Number</th>
                                                <th width="20%">Box Name</th>
                                                <th width="15%">Type</th>
                                                <th width="15%">Available</th>
                                                <th width="15%">Request Qty</th>
                                                <th width="10%">Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Boxes loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Boxes Summary -->
                        <div class="card mt-3" id="selectedBoxesCard" style="display: none;">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-check-circle me-2"></i>Selected Boxes 
                                    <span class="badge bg-light text-dark ms-2" id="selectedBoxesCount">0</span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="selectedBoxesList">
                                    <!-- Selected boxes will be displayed here -->
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" id="backToDestination">
                                <i class="bi bi-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" id="nextToItems" disabled>
                                Next: Select Items <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Step 4A: Select Items from Boxes -->
                    <div id="step4a" class="transfer-step" style="display: none;">
                        <h6 class="mb-3">Step 4: Select Items from Boxes</h6>
                        
                        <!-- Search and Filter -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="itemSearch" placeholder="Search items...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php
                                    $categories_query = "SELECT id, name FROM categories ORDER BY name";
                                    $categories_result = $conn->query($categories_query);
                                    if ($categories_result) {
                                    while ($category = $categories_result->fetch_assoc()) {
                                        echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                                        }
                                    } else {
                                        error_log("Categories query (step4a) failed: " . $conn->error);
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-secondary w-100" id="loadDestinationItems">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Load Items
                                </button>
                            </div>
                            <div class="col-md-2">
                                <div class="text-center">
                                    <small class="text-muted">Items from destination store(s)</small>
                                </div>
                            </div>
                        </div>

                        <!-- Transfer Layout -->
                        <div class="row">
                            <!-- Available Items from Destination Store (Left Panel) -->
                            <div class="col-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6>Items Available in Destination Store(s)</h6>
                                    <small class="text-muted" id="availableItemsCount">0 items</small>
                                </div>
                                <div class="table-responsive border rounded" style="max-height: 500px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0" id="availableItemsTable">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Store</th>
                                                <th>Item</th>
                                                <th>Current Stock</th>
                                                <th>Price</th>
                                                <th>Base Price</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Items loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Selected Boxes and Items (Right Panel) -->
                            <div class="col-md-7">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6>Selected Boxes & Items to Transfer</h6>
                                    <div>
                                        <small class="text-muted me-3">
                                            <span id="totalBoxesCount">0</span> boxes, 
                                            <span id="totalItemsCount">0</span> items
                                        </small>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="previewPrint">
                                            <i class="bi bi-printer me-1"></i> Preview Print
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Boxes Container -->
                                <div id="transferBoxesContainer" style="max-height: 500px; overflow-y: auto;">
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-box-seam display-4 mb-3"></i>
                                        <p>No boxes selected yet.<br>
                                        <small>Go back to Step 2 to select warehouse boxes first.</small></p>
                                            </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" id="backToBoxes">
                                <i class="bi bi-arrow-left"></i> Back to Boxes
                            </button>
                            <div>
                                <button type="button" class="btn btn-info me-2" id="printPackingSlips" disabled>
                                    <i class="bi bi-printer me-1"></i> Print Packing Slips
                                </button>
                                <button type="button" class="btn btn-success" id="createTransferBtn" disabled>
                                    <i class="bi bi-check-circle me-1"></i> Create Transfer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3B: Direct Item Selection -->
                    <div id="step3b" class="transfer-step" style="display: none;">
                        <h6 class="mb-3">Step 3: Select Items from Warehouse Inventory</h6>
                        
                        <!-- Search and Filter -->
                        <div class="row mb-3 search-form">
                            <div class="col-md-4">
                                <form id="warehouseItemSearchForm" class="d-flex">
                                    <input type="text" class="form-control" id="warehouseItemSearch" placeholder="Search items...">
                                    <button type="submit" class="btn btn-outline-secondary ms-2">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="warehouseCategoryFilter">
                                    <option value="">All Categories</option>
                                    <?php
                                    $categories_query = "SELECT id, name FROM categories ORDER BY name";
                                    $categories_result = $conn->query($categories_query);
                                    if ($categories_result) {
                                        while ($category = $categories_result->fetch_assoc()) {
                                            echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                                        }
                                    } else {
                                        error_log("Categories query failed: " . $conn->error);
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="warehouseStockFilter">
                                    <option value="">All Stock Levels</option>
                                    <option value="available">Available Only</option>
                                    <option value="low">Low Stock</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-outline-primary w-100" id="searchWarehouseItems">
                                    <i class="bi bi-search"></i> Search
                                </button>
                            </div>
                        </div>

                        <!-- Transfer Layout -->
                        <div class="row transfer-panels">
                            <!-- Available Warehouse Items (Left Panel) -->
                            <div class="col-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6>Items Available in Warehouse</h6>
                                    <small class="text-muted" id="warehouseItemsCount">0 items</small>
                                </div>
                                <div class="table-responsive border rounded" style="max-height: 500px; overflow-y: auto;">
                                    <table class="table table-sm table-hover mb-0" id="warehouseItemsTable">
                                        <thead class="table-light sticky-top">
                                            <tr>
                                                <th>Item</th>
                                                <th>Warehouse Stock</th>
                                                <th>Store Stock</th>
                                                <th>Price</th>
                                                <th>Average Cost</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Items loaded via AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Selected Items to Transfer (Right Panel) -->
                            <div class="col-md-7">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6>Selected Items to Transfer</h6>
                                    <div>
                                        <small class="text-muted me-3">
                                            <span id="directSelectedCount">0</span> items selected
                                        </small>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="clearDirectSelection">
                                            <i class="bi bi-x-circle me-1"></i> Clear All
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Selected Items Container -->
                                <div id="directSelectedItems" style="max-height: 500px; overflow-y: auto;">
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-list-check display-4 mb-3"></i>
                                        <p>No items selected yet.<br>
                                        <small>Select items from the warehouse inventory on the left.</small></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="mt-4 d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" id="backToTransferType">
                                <i class="bi bi-arrow-left me-1"></i> Back to Transfer Type
                            </button>
                            <button type="button" class="btn btn-success" id="createDirectTransfer" disabled>
                                <i class="bi bi-check-circle me-1"></i> Complete Direct Transfer
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- REMOVED: Old Transfer Modal - replaced with new detailed view modal below -->

<!-- REMOVED: Receive Transfer Modal - no longer needed with simplified workflow -->

<!-- Print Packing Slips Modal -->
<div class="modal fade" id="printPackingModal" tabindex="-1">
    <div class="modal-dialog modal-xl mobile-modal-responsive">
        <div class="modal-content">
            <div class="modal-header mobile-modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-printer me-2"></i>Packing Slips Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body mobile-modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <strong>Transfer Route:</strong> 
                        <span id="printTransferRoute"></span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary" id="printAllSlips">
                            <i class="bi bi-printer me-1"></i> Print All Slips
                        </button>
                    </div>
                </div>
                <div id="packingSlipsContainer">
                    <!-- Packing slips will be generated here -->
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto">
                    <button type="button" class="btn btn-danger" id="deleteTransferBtn" style="display: none;">
                        <i class="bi bi-trash me-1"></i>Delete Transfer
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Transfer Details Modal -->
<div class="modal fade" id="viewTransferModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-eye me-2"></i>Transfer Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Transfer Summary -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Transfer Information</h6>
                            </div>
                            <div class="card-body">
                                <div id="transferInfo">
                                    <!-- Transfer details will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Summary Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div id="transferSummary">
                                    <!-- Summary statistics will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transfer Boxes -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="bi bi-boxes me-2"></i>Transfer Boxes</h6>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="printTransferBoxes">
                            <i class="bi bi-printer me-1"></i>Print Boxes
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="transferBoxesDetails">
                            <!-- Box details will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="me-auto">
                    <button type="button" class="btn btn-danger" id="deleteTransferBtn" style="display: none;">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse Transfer
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Transfer Modal - GUARANTEED TO WORK! -->
<div class="modal fade" id="customTransferModal" tabindex="-1" aria-labelledby="customTransferModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customTransferModalTitle">Add Item to Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 id="customTransferItemName" class="text-primary">Item Name</h6>
                    <p class="mb-2">Current stock in destination: <strong id="customTransferCurrentStock">0</strong></p>
                    <p class="text-muted small">This will increase the stock in the destination store.</p>
                </div>
                
                <div class="mb-3">
                    <label for="customTransferQuantityInput" class="form-label">Quantity to Transfer</label>
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="customTransferQuantityInput" 
                           placeholder="Enter quantity" 
                           min="1" 
                           step="1" 
                           value="1"
                           style="font-size: 18px; padding: 12px;">
                </div>
                
                <div id="customTransferBoxSelection" class="mb-3" style="display: none;">
                    <label for="customTransferBoxSelect" class="form-label">Target Box</label>
                    <select id="customTransferBoxSelect" class="form-select">
                        <!-- Box options will be populated by JavaScript -->
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="customTransferConfirm">Add to Transfer</button>
            </div>
        </div>
    </div>
</div>

<!-- Store to Store Transfer Modal -->
<div class="modal fade" id="storeToStoreModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Store to Store Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Store Selection -->
                <div id="storeTransferStep1" class="store-transfer-step">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Source Store</label>
                            <select class="form-select" id="sourceStoreSelect" required>
                                <option value="">Select Source Store</option>
                                <?php
                                $stores_query = "SELECT id, name FROM stores WHERE status = 'active' ORDER BY name";
                                $stores_result = $conn->query($stores_query);
                                if ($stores_result) {
                                    while ($store = $stores_result->fetch_assoc()) {
                                        echo "<option value='{$store['id']}'>" . htmlspecialchars($store['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destination Store</label>
                            <select class="form-select" id="destinationStoreSelect" required>
                                <option value="">Select Destination Store</option>
                                <?php
                                $stores_result = $conn->query($stores_query);
                                if ($stores_result) {
                                    while ($store = $stores_result->fetch_assoc()) {
                                        echo "<option value='{$store['id']}'>" . htmlspecialchars($store['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Item Selection -->
                <div id="storeTransferStep2" class="store-transfer-step" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-box-seam me-2"></i>Available Items</h6>
                            <div class="mb-3">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="itemSearchInput" placeholder="Search items...">
                                    <button class="btn btn-outline-secondary" type="button" id="searchItemsBtn">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    <button class="btn btn-outline-info" type="button" id="testApiBtn" title="Test API">
                                        <i class="bi bi-bug"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="storeTransferAvailableItemsTable">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">
                                                <i class="bi bi-search display-4 mb-2"></i>
                                                <p>Select stores and search for items</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-cart-plus me-2"></i>Items to Transfer</h6>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Qty</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transferItemsTable">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">
                                                <i class="bi bi-cart display-4 mb-2"></i>
                                                <p>No items selected</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Confirmation -->
                <div id="storeTransferStep3" class="store-transfer-step" style="display: none;">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle me-2"></i>Transfer Summary</h6>
                        <p class="mb-0">Review the items below before executing the transfer.</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th>Quantity</th>
                                    <th>Selling Price</th>
                                </tr>
                            </thead>
                            <tbody id="transferSummaryTable">
                                <!-- Summary items will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="storeTransferBackBtn" style="display: none;">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </button>
                <button type="button" class="btn btn-primary" id="storeTransferNextBtn" disabled>
                    Next <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <button type="button" class="btn btn-success" id="executeTransferBtn" style="display: none;">
                    <i class="bi bi-check-circle me-1"></i> Execute Transfer
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    window.csrfToken = $('input[name="csrf_token"]').val();
    let availableItems = [];
    let availableBoxes = [];
    let selectedBoxes = [];
    let transferItems = []; // Items to transfer with quantities
    let transferBoxes = []; // Boxes with their items for transfer
    
    // Set source store to main warehouse when modal opens
    $('#createTransferModal').on('show.bs.modal', function() {
        $('#destinationStore').val('');
        // Reset all data
        availableItems = [];
        availableBoxes = [];
        selectedBoxes = [];
        transferItems = [];
        transferBoxes = [];
        
        // Reset UI
        $('#availableItemsTable tbody').empty();
        $('#availableItemsCount').text('0 items');
        $('#availableBoxesTable tbody').empty();
        $('#availableBoxesCount').text('0 boxes');
        $('#transferBoxesContainer').html(`
            <div class="text-center text-muted py-5">
                <i class="bi bi-box-seam display-4 mb-3"></i>
                <p>No boxes selected yet.<br>
                <small>Go back to Step 2 to select warehouse boxes first.</small></p>
            </div>
        `);
        updateSelectedBoxesUI();
        updateCounts();
        
        // Show step 1
        $('#step1').show();
        $('#step2').hide();
        $('#step3').hide();
        
        // Disable buttons
        $('#nextToBoxes').prop('disabled', true);
        $('#nextToItems').prop('disabled', true);
        $('#createTransferBtn').prop('disabled', true);
    });
    
    // Prevent form submission to avoid page refresh
    $('#createTransferForm').on('submit', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Prevent Enter key from submitting form
    $('#createTransferForm input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            return false;
        }
    });
    
    // Initialize DataTable
    const transfersTable = $('#transfersTable').DataTable({
        processing: true,
        ajax: {
            url: '../ajax/get_transfer_shipments.php',
            type: 'POST',
            data: function(d) {
                d.csrf_token = csrfToken;
                d.action = 'list';
                d.status = $('#statusFilter').val();
                d.transfer_type = $('#transferTypeFilter').val();
                d.source_store_id = $('#sourceStoreFilter').val();
                d.destination_store_id = $('#destStoreFilter').val();
            },
            dataSrc: function(json) {
                if (json.success) {
                    return json.shipments;
                } else {
                    console.error('Error loading transfers:', json.message);
                    return [];
                }
            }
        },
        columns: [
            { 
                data: 'shipment_number',
                render: function(data, type, row) {
                    return `<span class="fw-bold">${data}</span>`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    return `
                            <small>${row.source_store.name}</small>`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    // Show multiple destinations if available
                    if (Array.isArray(row.destinations) && row.destinations.length > 1) {
                        const chips = row.destinations.slice(0, 3).map(function(d){
                            return `<span class=\"badge bg-info me-1\">${d.code || 'N/A'}</span>`;
                        }).join('');
                        const more = row.destinations.length > 3 ? `+${row.destinations.length - 3}` : '';
                        return `${chips}${more ? `<span class=\"badge bg-secondary\">${more}</span>` : ''}`;
                    }
                    const d = (row.destinations && row.destinations[0]) || row.destination_store || {};
                    return `<span class=\"badge bg-info\">${d.code || 'N/A'}</span><br><small>${d.name || ''}</small>`;
                }
            },
            { 
                data: 'transfer_type',
                render: function(data, type, row) {
                    const typeClasses = {
                        'box': 'bg-primary',
                        'direct': 'bg-success',
                        'legacy': 'bg-secondary'
                    };
                    const typeLabels = {
                        'box': 'Box',
                        'direct': 'Direct',
                        'legacy': 'Legacy'
                    };
                    return `<span class="badge ${typeClasses[data] || 'bg-secondary'}">${typeLabels[data] || data}</span>`;
                }
            },
            { data: 'total_items' },
            { 
                data: 'status',
                render: function(data, type, row) {
                    const statusClasses = {
                        'pending': 'bg-warning text-dark',
                        'received': 'bg-success',
                        'cancelled': 'bg-danger'
                    };
                    const statusLabels = {
                        'pending': 'PENDING',
                        'received': 'COMPLETED',
                        'cancelled': 'CANCELLED'
                    };
                    return `<span class="badge ${statusClasses[data] || 'bg-secondary'}">${statusLabels[data] || data.toUpperCase()}</span>`;
                }
            },
            { 
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleDateString();
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    let actions = `<button class="btn btn-sm btn-outline-primary me-1" onclick="viewTransferDetails(${row.id})">
                                     <i class="bi bi-eye"></i>
                                   </button>`;
                    
                    if (row.status === 'pending') {
                        actions += `<button class="btn btn-sm btn-success me-1" onclick="packShipment(${row.id})" title="Complete Transfer">
                                      <i class="bi bi-check-circle-fill me-1"></i>Complete
                                    </button>`;
                        actions += `<button class="btn btn-sm btn-outline-danger me-1" onclick="cancelShipment(${row.id})" title="Cancel Transfer">
                                      <i class="bi bi-x-circle"></i>
                                    </button>`;
                    }
                    
                    // Delete button for all transfers (can reverse any status)
                    actions += `<button class="btn btn-sm btn-danger" onclick="deleteTransferFromTable(${row.id})" title="Reverse Transfer">
                                  <i class="bi bi-arrow-counterclockwise"></i>
                                </button>`;
                    
                    return actions;
                }
            }
        ],
        order: [[5, 'desc']],
    });

    // Filter form submission
    $('#transferFilterForm').on('submit', function(e) {
        e.preventDefault();
        transfersTable.ajax.reload();
    });

    // Auto-reload table when filters change
    $('#statusFilter, #transferTypeFilter, #sourceStoreFilter, #destStoreFilter').on('change', function() {
        transfersTable.ajax.reload();
    });

    // Function to reload transfers table (for external calls)
    window.loadTransfers = function() {
        transfersTable.ajax.reload();
    };

    // Step 1: Destination store selection
    function getSelectedDestinationStoreIds() {
        const ids = [];
        $('.destination-store-checkbox:checked').each(function(){
            const v = parseInt($(this).val());
            if (!isNaN(v)) ids.push(v);
        });
        return ids;
    }

    $(document).on('change', '.destination-store-checkbox', function() {
        const destIds = getSelectedDestinationStoreIds();
        $('#nextToTransferType').prop('disabled', destIds.length === 0);
    });

    $('#nextToTransferType').on('click', function() {
        const destIds = getSelectedDestinationStoreIds();
        if (destIds.length === 0) {
            Swal.fire('Error', 'Please select a destination store', 'error');
            return;
        }
        
        $('#step1').hide();
        $('#step2').show();
    });

    // Step 2: Transfer Type Selection
    $('.transfer-type-card').on('click', function() {
        $('.transfer-type-card').removeClass('border-primary selected');
        $(this).addClass('border-primary selected');
        
        const transferType = $(this).data('type');
        $('#transferType').val(transferType);
        $('#nextFromType').prop('disabled', false);
    });

    $('#backToDestination').on('click', function() {
        $('#step2').hide();
        $('#step1').show();
        $('.transfer-type-card').removeClass('border-primary selected');
        $('#transferType').val('');
        $('#nextFromType').prop('disabled', true);
    });

    $('#nextFromType').on('click', function() {
        const transferType = $('#transferType').val();
        if (!transferType) {
            Swal.fire('Error', 'Please select a transfer type', 'error');
            return;
        }
        
        $('#step2').hide();
        
        if (transferType === 'box') {
            $('#step3a').show();
            loadWarehouseBoxes();
        } else if (transferType === 'direct') {
            $('#step3b').show();
            loadWarehouseInventory();
        }
    });

    // Step 3A: Box selection navigation
    $('#step3a .btn-secondary').on('click', function() {
        $('#step3a').hide();
        $('#step2').show();
    });

    $('#nextToItems').on('click', function() {
        // Validate that all selected boxes have quantity > 0
        const invalidBoxes = selectedBoxes.filter(box => !box.quantity || box.quantity <= 0);
        if (invalidBoxes.length > 0) {
            Swal.fire('Error', 'Some selected boxes have 0 quantity and cannot be used for transfers. Please review your selection.', 'error');
            return;
        }
        
        if (selectedBoxes.length === 0) {
            Swal.fire('Error', 'Please select at least one box', 'error');
            return;
        }
        
        $('#step3a').hide();
        $('#step4a').show();
        loadDestinationItems();
    });

    // Step 4A: Item selection from boxes
    $('#backToBoxes').on('click', function() {
        $('#step4a').hide();
        $('#step3a').show();
    });

    // Step 3B: Direct transfer navigation
    $('#backToTransferType').on('click', function() {
        $('#step3b').hide();
        $('#step2').show();
        clearDirectTransferSelections();
    });

    // Load warehouse boxes for selection
    function loadWarehouseBoxes() {
        const search = $('#boxSearch').val();
        const type = $('#boxTypeFilter').val();
        
        $.ajax({
            url: '../ajax/get_warehouse_boxes.php',
            type: 'GET',
            data: { search: search, type: type },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    availableBoxes = response.boxes;
                    
                    // Remove any selected boxes that now have 0 quantity
                    selectedBoxes = selectedBoxes.filter(box => {
                        const availableBox = availableBoxes.find(ab => ab.id === box.id);
                        return availableBox && availableBox.quantity && availableBox.quantity > 0;
                    });
                    
                    renderAvailableBoxes();
                    updateSelectedBoxesUI();
                    
                    // Update type filter options
                    updateBoxTypeFilter(response.box_types);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to load warehouse boxes', 'error');
            }
        });
    }

    // Load items from destination store
    function loadDestinationItems() {
        const destStoreIds = getSelectedDestinationStoreIds();
        const search = $('#itemSearch').val();
        const categoryId = $('#categoryFilter').val();
        
        if (destStoreIds.length === 0) return;

        availableItems = [];
        let pending = destStoreIds.length;
        destStoreIds.forEach(function(storeId) {
        $.ajax({
            url: '../ajax/get_destination_store_items.php',
            type: 'GET',
            data: { 
                    store_id: storeId,
                search: search,
                category_id: categoryId
            },
                dataType: 'json'
            }).done(function(response) {
                if (response.success) {
                    const storeName = response.store_info ? (response.store_info.name + ' (' + response.store_info.store_code + ')') : ('Store #' + storeId);
                    const augmented = (response.items || []).map(function(it){
                        return Object.assign({}, it, { destination_store_id: parseInt(storeId), destination_store_label: storeName });
                    });
                    availableItems = availableItems.concat(augmented);
                }
            }).always(function(){
                pending -= 1;
                if (pending === 0) {
                    renderAvailableItems();
                }
            });
        });
    }



    // Box selection handlers
    $('#loadWarehouseBoxes').on('click', loadWarehouseBoxes);
    $('#boxSearchForm').on('submit', function(e) {
        e.preventDefault();
        loadWarehouseBoxes();
    });
    $('#boxTypeFilter').on('change', loadWarehouseBoxes);
    
    // Select all boxes checkbox
    $('#selectAllBoxesCheckbox').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('#availableBoxesTable tbody input[type="checkbox"]').prop('checked', isChecked);
        updateSelectedBoxes();
    });
    
    // Individual box selection
    $(document).on('change', '.box-checkbox', function() {
        updateSelectedBoxes();
        // Update select all checkbox state
    });
    
    // Box quantity input change
    $(document).on('input', '.box-quantity-input', function() {
        const boxId = parseInt($(this).data('box-id'));
        const checkbox = $(`input[data-box-id="${boxId}"].box-checkbox`);
        
        // If quantity is changed and box is selected, update the selection
        if (checkbox.is(':checked')) {
            updateSelectedBoxes();
        }
    });
    
    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        const totalBoxes = $('#availableBoxesTable tbody input[type="checkbox"]').length;
        const checkedBoxes = $('#availableBoxesTable tbody input[type="checkbox"]:checked').length;
        $('#selectAllBoxesCheckbox').prop('checked', totalBoxes > 0 && checkedBoxes === totalBoxes);
    }

    // Render available boxes
    function renderAvailableBoxes() {
        const tbody = $('#availableBoxesTable tbody');
        tbody.empty();

        if (availableBoxes.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="7" class="text-center text-muted py-3">
                        <i class="bi bi-box me-2"></i>No boxes found
                    </td>
                </tr>
            `);
        } else {
            availableBoxes.forEach(function(box) {
                const isSelected = selectedBoxes.some(sb => sb.id === box.id);
                const hasQuantity = box.quantity && box.quantity > 0;
                const selectedBox = selectedBoxes.find(sb => sb.id === box.id);
                const requestQuantity = selectedBox ? selectedBox.request_quantity || 1 : 1;
                
                const row = `
                    <tr class="${!hasQuantity ? 'table-warning' : ''}">
                        <td>
                            <input type="checkbox" class="form-check-input box-checkbox" 
                                   data-box-id="${box.id}" ${isSelected ? 'checked' : ''}
                                   ${!hasQuantity ? 'disabled' : ''}>
                        </td>
                        <td><strong>${escapeHtml(box.box_number)}</strong></td>
                        <td>${escapeHtml(box.box_name)}</td>
                        <td>${escapeHtml(box.box_type || '-')}</td>
                        <td>
                            <span class="badge ${hasQuantity ? 'bg-success' : 'bg-warning'}">
                                ${box.quantity || 0}
                            </span>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm box-quantity-input" 
                                   data-box-id="${box.id}" 
                                   value="${requestQuantity}" 
                                   min="1" 
                                   max="${box.quantity || 1}" 
                                   style="width: 80px;"
                                   ${!hasQuantity ? 'disabled' : ''}>
                        </td>
                        <td>${box.formatted_date}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        $('#availableBoxesCount').text(`${availableBoxes.length} boxes`);
    }

    // Update box type filter
    function updateBoxTypeFilter(boxTypes) {
        const filter = $('#boxTypeFilter');
        const currentValue = filter.val();
        
        filter.find('option:not(:first)').remove();
        boxTypes.forEach(type => {
            filter.append(`<option value="${escapeHtml(type)}">${escapeHtml(type)}</option>`);
        });
        
        filter.val(currentValue);
    }

    // Update selected boxes
    function updateSelectedBoxes() {
        selectedBoxes = [];
        $('#availableBoxesTable tbody input[type="checkbox"]:checked').each(function() {
            const boxId = parseInt($(this).data('box-id'));
            const box = availableBoxes.find(b => b.id === boxId);
            if (box && box.quantity && box.quantity > 0) {
                // Get the requested quantity from the input
                const quantityInput = $(`input[data-box-id="${boxId}"].box-quantity-input`);
                const requestQuantity = parseInt(quantityInput.val()) || 1;
                
                // Validate quantity
                if (requestQuantity > box.quantity) {
                    quantityInput.val(box.quantity);
                    Swal.fire('Warning', `Quantity reduced to available amount (${box.quantity}) for box ${box.box_number}`, 'warning');
                }
                
                // Add box with requested quantity
                selectedBoxes.push({
                    ...box,
                    request_quantity: Math.min(requestQuantity, box.quantity)
                });
                
                console.log('Added box to selection:', {
                    id: box.id,
                    name: box.box_name,
                    request_quantity: Math.min(requestQuantity, box.quantity)
                });
            } else {
                // Uncheck boxes with 0 quantity
                $(this).prop('checked', false);
            }
        });
        
        console.log('Updated selectedBoxes array:', selectedBoxes);
        
        updateSelectedBoxesUI();
        $('#nextToItems').prop('disabled', selectedBoxes.length === 0);
    }

    // Update selected boxes UI
    function updateSelectedBoxesUI() {
        const card = $('#selectedBoxesCard');
        const list = $('#selectedBoxesList');
        const count = $('#selectedBoxesCount');
        
        count.text(selectedBoxes.length);
        
        if (selectedBoxes.length === 0) {
            card.hide();
        } else {
            card.show();
            list.empty();
            
            selectedBoxes.forEach(box => {
                const item = `
                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                        <div>
                            <strong>${escapeHtml(box.box_number)}</strong> - ${escapeHtml(box.box_name)}
                            ${box.box_type ? `<small class="text-muted ms-2">(${escapeHtml(box.box_type)})</small>` : ''}
                            <br><small class="text-success"><strong>Available: ${box.quantity || 0} items</strong></small>
                            <br><small class="text-primary"><strong>Requested: ${box.request_quantity || 1} boxes</strong></small>
                        </div>
                        <button class="btn btn-sm btn-outline-danger remove-selected-box" data-box-id="${box.id}">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                `;
                list.append(item);
            });
        }
    }

    // Remove selected box
    $(document).on('click', '.remove-selected-box', function() {
        const boxId = parseInt($(this).data('box-id'));
        $(`input[data-box-id="${boxId}"]`).prop('checked', false);
        updateSelectedBoxes();
    });

    // Item selection handlers
    $('#loadDestinationItems').on('click', loadDestinationItems);
    $('#itemSearchForm').on('submit', function(e) {
        e.preventDefault();
        loadDestinationItems();
    });
    $('#categoryFilter').on('change', loadDestinationItems);

    // Render available items for transfer
    function renderAvailableItems() {
        const tbody = $('#availableItemsTable tbody');
        tbody.empty();

        if (availableItems.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">
                        <i class="bi bi-box me-2"></i>No items found in destination store
                    </td>
                </tr>
            `);
        } else {
            availableItems.forEach(function(item) {
                const isAlreadySelected = transferItems.some(ti => 
                    ti.item_id === item.item_id && ti.barcode_id === item.barcode_id && ti.destination_store_id === item.destination_store_id
                );
                
                const row = `
                    <tr>
                        <td><span class="badge bg-info">${escapeHtml(item.destination_store_label || 'Store')}</span></td>
                        <td>
                            <strong>${escapeHtml(item.item_name)}</strong><br>
                            <small class="text-muted">${escapeHtml(item.item_code)}</small>
                        </td>
                        <td><span class="badge bg-secondary">${item.current_stock}</span></td>
                        <td>CFA ${parseFloat(item.selling_price).toFixed(2)}</td>
                        <td>
                            <span class="badge bg-info text-dark" title="Original base price">
                                CFA ${parseFloat(item.base_price || item.cost_price || 0).toFixed(2)}
                            </span>
                        </td>
                        <td>
                            ${isAlreadySelected ? 
                                `<span class="badge bg-success">Selected</span>` :
                                `<button class="btn btn-sm btn-primary add-item-to-transfer" 
                                        data-item-id="${item.item_id}" 
                                        data-barcode-id="${item.barcode_id}"
                                        data-dest="${item.destination_store_id || ''}"
                                        title="Add to Transfer">
                                    <i class="bi bi-plus"></i> Add
                                </button>`
                            }
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        $('#availableItemsCount').text(`${availableItems.length} items`);
        renderTransferItems();
    }

    // Add item to transfer
    $(document).on('click', '.add-item-to-transfer', function(e) {
        e.preventDefault();
        
        const itemId = parseInt($(this).data('item-id'));
        const barcodeId = parseInt($(this).data('barcode-id'));
        const destStoreId = parseInt($(this).data('dest'));
        
        const item = availableItems.find(i => i.item_id === itemId && i.barcode_id === barcodeId && i.destination_store_id === destStoreId);
        if (!item) {
            console.error('Item not found');
            return;
        }

        // Build expanded box options from selectedBoxes (including multiple instances)
        let boxOptions = '<option value="" disabled selected>Select a box…</option>';
        if (selectedBoxes.length > 0) {
            let boxCounter = 1;
            selectedBoxes.forEach((b, idx) => {
                const requestedQuantity = b.request_quantity || 1;
                
                // Create multiple box instances based on requested quantity
                for (let i = 1; i <= requestedQuantity; i++) {
                    const name = (b.box_name || '').toString();
                    const type = (b.box_type || '').toString();
                    const warehouseNum = (b.box_number || '').toString();
                    const parts = [];
                    if (warehouseNum) parts.push(`#${warehouseNum}`);
                    if (name) parts.push(name);
                    if (type) parts.push(`(${type})`);
                    
                    // Add instance identifier if multiple instances
                    if (requestedQuantity > 1) {
                        parts.push(`Instance ${i}`);
                    }
                    
                    const full = parts.join(' ');
                    const shortLabel = truncateText(full, 40);
                    boxOptions += `<option value="${boxCounter}" title="${escapeHtml(full)}">Box #${boxCounter} — ${escapeHtml(shortLabel)}</option>`;
                    boxCounter++;
                }
            });
        }

        // CUSTOM MODAL SOLUTION - No more SweetAlert2 headaches!
        showCustomTransferModal(item, selectedBoxes);
    });

    // Custom Transfer Modal Function - GUARANTEED TO WORK!
    function showCustomTransferModal(item, selectedBoxes) {
        console.log('Opening custom modal for item:', item);
        
        // Build box options if available
        let boxOptionsHtml = '';
        if (selectedBoxes.length > 0) {
            let boxCounter = 1;
            selectedBoxes.forEach(box => {
                for (let i = 0; i < (box.requestQuantity || 1); i++) {
                    boxOptionsHtml += `<option value="${boxCounter}">Box ${box.box_number} (Instance ${i + 1})</option>`;
                    boxCounter++;
                }
            });
        }

        // Set modal content
        $('#customTransferModalTitle').text('Add Item to Transfer');
        $('#customTransferItemName').text(item.item_name);
        $('#customTransferCurrentStock').text(item.current_stock);
        $('#customTransferQuantityInput').val(1).focus();
        
        // Show/hide box selection
        if (selectedBoxes.length > 0) {
            $('#customTransferBoxSelection').show();
            $('#customTransferBoxSelect').html(boxOptionsHtml);
        } else {
            $('#customTransferBoxSelection').hide();
        }
        
        // Store item data for later use
        $('#customTransferModal').data('item', item);
        $('#customTransferModal').data('selectedBoxes', selectedBoxes);
        
        // Show the modal
        const modal = new bootstrap.Modal(document.getElementById('customTransferModal'));
        modal.show();
        
        // Focus the input after modal is shown
        $('#customTransferModal').on('shown.bs.modal', function() {
            $('#customTransferQuantityInput').focus().select();
        });
    }

    // Handle custom modal confirm
    $(document).on('click', '#customTransferConfirm', function() {
        const modal = $('#customTransferModal');
        const item = modal.data('item');
        const selectedBoxes = modal.data('selectedBoxes');
        const quantity = parseInt($('#customTransferQuantityInput').val());
        const boxNumber = selectedBoxes.length > 0 ? parseInt($('#customTransferBoxSelect').val()) : 1;
        
        // Validate
        if (!quantity || quantity < 1) {
            alert('Please enter a valid quantity');
            return;
        }
        
        if (selectedBoxes.length > 0 && (!boxNumber || boxNumber < 1)) {
            alert('Please select a box');
            return;
        }
        
        // Close modal
        bootstrap.Modal.getInstance(modal[0]).hide();
        
        // Add item to transfer
        addItemToTransfer(item, quantity, boxNumber);
    });

    // Calculate average cost for an item
    function calculateItemAverageCost(boxId, itemId, quantity, transferItem) {
        $.ajax({
            url: '../ajax/process_transfer_shipment.php',
            type: 'POST',
            data: {
                csrf_token: csrfToken,
                action: 'calculate_average_cost',
                box_id: boxId,
                item_id: itemId,
                quantity: quantity
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    transferItem.average_cost = response.average_cost;
                    transferItems.push(transferItem);
                    renderAvailableItems();
                    updateCounts();
                } else {
                    console.error('Failed to calculate average cost:', response.message);
                    // Fallback to existing cost price
                    transferItem.average_cost = transferItem.cost_price || 0;
                    transferItems.push(transferItem);
                    renderAvailableItems();
                    updateCounts();
                }
            },
            error: function() {
                console.error('Failed to calculate average cost');
                // Fallback to existing cost price
                transferItem.average_cost = transferItem.cost_price || 0;
                transferItems.push(transferItem);
                renderAvailableItems();
                updateCounts();
            }
        });
    }

    // Add item to transfer list
    function addItemToTransfer(item, quantity, boxNumber) {
        const transferItem = {
            item_id: item.item_id,
            barcode_id: item.barcode_id,
            item_name: item.item_name,
            item_code: item.item_code,
            quantity: quantity,
            selling_price: item.selling_price,
            cost_price: item.cost_price,
            destination_store_id: item.destination_store_id,
            destination_store_label: item.destination_store_label,
            box_number: boxNumber || 1,
            average_cost: 0 // Will be calculated below
        };

        // Calculate average cost for this item
        // Find the warehouse box based on the expanded box number
        let warehouseBoxId = null;
        let currentBoxCounter = 1;
        
        for (const selectedBox of selectedBoxes) {
            const requestedQuantity = selectedBox.request_quantity || 1;
            
            // Check if this box number falls within the range of this selected box
            if (boxNumber >= currentBoxCounter && boxNumber < currentBoxCounter + requestedQuantity) {
                warehouseBoxId = selectedBox.id;
                break;
            }
            
            currentBoxCounter += requestedQuantity;
        }
        
        if (warehouseBoxId) {
            calculateItemAverageCost(warehouseBoxId, item.item_id, quantity, transferItem);
        } else {
            // If no box selected, use existing cost price
            transferItem.average_cost = item.cost_price || 0;
            transferItems.push(transferItem);
            renderAvailableItems();
            updateCounts();
        }
    }

    // Render transfer items in the right panel
    function renderTransferItems() {
        const container = $('#transferBoxesContainer');
        
        if (selectedBoxes.length === 0) {
            container.html(`
                <div class="text-center text-muted py-5">
                    <i class="bi bi-box-seam display-4 mb-3"></i>
                    <p>No boxes selected yet.<br>
                    <small>Go back to Step 2 to select warehouse boxes first.</small></p>
                </div>
            `);
            return;
        }

        if (transferItems.length === 0) {
            container.html(`
                <div class="text-center text-muted py-5">
                    <i class="bi bi-list-ul display-4 mb-3"></i>
                    <p>No items selected for transfer yet.<br>
                    <small>Select items from the left panel to add them to the transfer.</small></p>
                </div>
            `);
            return;
        }

        let html = `
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-arrow-right-circle me-2"></i>Items to Transfer
                        <span class="badge bg-light text-dark ms-2">${transferItems.length} items</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Destination</th>
                                    <th>Box</th>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Average Cost</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
        `;

                transferItems.forEach((item, index) => {
            const total = item.quantity * item.selling_price;
            const averageCost = item.average_cost || item.cost_price || 0;
            
            // Get box information for display
            let boxDisplay = `Box #${item.box_number || 1}`;
            let currentBoxCounter = 1;
            
            for (const selectedBox of selectedBoxes) {
                const requestedQuantity = selectedBox.request_quantity || 1;
                
                // Check if this box number falls within the range of this selected box
                if (item.box_number >= currentBoxCounter && item.box_number < currentBoxCounter + requestedQuantity) {
                    const instanceNumber = item.box_number - currentBoxCounter + 1;
                    if (requestedQuantity > 1) {
                        boxDisplay = `Box #${item.box_number} (${selectedBox.box_name} - Instance ${instanceNumber})`;
                    } else {
                        boxDisplay = `Box #${item.box_number} (${selectedBox.box_name})`;
                    }
                    break;
                }
                
                currentBoxCounter += requestedQuantity;
            }
            
            html += `
                <tr>
                    <td><span class="badge bg-info">${escapeHtml(item.destination_store_label || '')}</span></td>
                    <td><span class="badge bg-secondary" title="Box assignment">${escapeHtml(boxDisplay)}</span></td>
                    <td>
                        <strong>${escapeHtml(item.item_name)}</strong><br>
                        <small class="text-muted">${escapeHtml(item.item_code)}</small>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm quantity-input" 
                               value="${item.quantity}" min="1" 
                               data-item-index="${index}" style="width: 80px;">
                    </td>
                    <td>CFA ${parseFloat(item.selling_price).toFixed(2)}</td>
                    <td>
                        <span class="badge bg-warning text-dark" title="Calculated average cost (box cost split per item quantity + base price)">
                            CFA ${parseFloat(averageCost).toFixed(2)}
                        </span>
                    </td>
                    <td><strong>CFA ${total.toFixed(2)}</strong></td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger remove-transfer-item"
                                data-item-index="${index}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

        // Add selected boxes summary
        html += `
            <div class="card mt-3">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-boxes me-2"></i>Selected Boxes
                        <span class="badge bg-light text-dark ms-2">${selectedBoxes.length} boxes</span>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
        `;

        selectedBoxes.forEach(box => {
            html += `
                <div class="col-md-6 mb-2">
                    <div class="p-2 bg-light rounded">
                        <strong>${escapeHtml(box.box_number)}</strong> - ${escapeHtml(box.box_name)}
                        ${box.box_type ? `<br><small class="text-muted">${escapeHtml(box.box_type)}</small>` : ''}
                        
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;

        container.html(html);
    }

    // Remove item from transfer
    $(document).on('click', '.remove-transfer-item', function() {
        const itemIndex = parseInt($(this).data('item-index'));
        if (itemIndex >= 0 && itemIndex < transferItems.length) {
            transferItems.splice(itemIndex, 1);
        renderAvailableItems();
            updateCounts();
        }
    });

    // Update quantity in transfer
    $(document).on('input', '.quantity-input', function() {
        const itemIndex = parseInt($(this).data('item-index'));
        const quantity = parseInt($(this).val()) || 1;
        
        if (itemIndex >= 0 && itemIndex < transferItems.length) {
            transferItems[itemIndex].quantity = Math.max(1, quantity);
            renderTransferItems(); // Re-render to update totals
            updateCounts();
        }
    });

    // Render items in a specific box
    function renderBoxItems(boxId) {
        const box = transferBoxes.find(b => b.id === boxId);
        if (!box) return;

        const tbody = $(`.transfer-box[data-box-id="${boxId}"] .box-items-table tbody`);
        tbody.empty();

        if (box.items.length === 0) {
            tbody.append(`
                <tr class="empty-box-message">
                    <td colspan="4" class="text-center text-muted py-3">
                        <i class="bi bi-box me-2"></i>No items in this box
                    </td>
                </tr>
            `);
        } else {
            box.items.forEach((item, itemIndex) => {
                const row = `
                    <tr>
                        <td>
                            <strong>${escapeHtml(item.name)}</strong><br>
                            <small class="text-muted">${escapeHtml(item.item_code)}</small>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm quantity-input" 
                                   value="${item.quantity}" min="1" 
                                   data-box-id="${boxId}" data-item-index="${itemIndex}" style="width: 80px;">
                        </td>
                        <td>CFA ${parseFloat(item.selling_price).toFixed(2)}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger remove-item" 
                                    data-box-id="${boxId}" data-item-index="${itemIndex}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        // Update box item count badge
        $(`.transfer-box[data-box-id="${boxId}"] .box-item-count`).text(`${box.items.length} items`);
    }

    // Remove item from box
    $(document).on('click', '.remove-item', function() {
        const boxId = parseInt($(this).data('box-id'));
        const itemIndex = parseInt($(this).data('item-index'));
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        
        if (boxIndex !== -1 && transferBoxes[boxIndex].items[itemIndex]) {
            transferBoxes[boxIndex].items.splice(itemIndex, 1);
            renderBoxItems(boxId);
            renderAvailableItems();
            updateBoxCounts();
        }
    });

    // Update quantity in box
    $(document).on('input', '.quantity-input', function() {
        const boxId = parseInt($(this).data('box-id'));
        const itemIndex = parseInt($(this).data('item-index'));
        const quantity = parseInt($(this).val()) || 1;
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        
        if (boxIndex !== -1 && transferBoxes[boxIndex].items[itemIndex]) {
            const item = transferBoxes[boxIndex].items[itemIndex];
            const originalItem = availableItems.find(i => 
                (i.item_id || i.id) == item.item_id && i.barcode_id == item.barcode_id
            );
            
            if (originalItem) {
                const maxAvailable = getRemainingStock(originalItem, boxId, itemIndex);
                if (quantity > maxAvailable) {
                    $(this).val(maxAvailable);
                    transferBoxes[boxIndex].items[itemIndex].quantity = maxAvailable;
                } else {
                    transferBoxes[boxIndex].items[itemIndex].quantity = Math.max(1, quantity);
                }
                renderAvailableItems();
            }
        }
    });

    // Helper functions
    function isItemFullyAllocated(item) {
        const totalAllocated = transferBoxes.reduce((total, box) => {
            return total + box.items.reduce((boxTotal, boxItem) => {
                if ((boxItem.item_id === (item.item_id || item.id)) && 
                    (boxItem.barcode_id === item.barcode_id)) {
                    return boxTotal + boxItem.quantity;
                }
                return boxTotal;
            }, 0);
        }, 0);
        
        return totalAllocated >= item.current_stock;
    }

    function getRemainingStock(item, excludeBoxId = null, excludeItemIndex = null) {
        let totalAllocated = 0;
        
        transferBoxes.forEach((box, boxIndex) => {
            box.items.forEach((boxItem, itemIndex) => {
                if (excludeBoxId !== null && box.id === excludeBoxId && itemIndex === excludeItemIndex) {
                    return; // Skip this item when calculating remaining stock for quantity update
                }
                
                if ((boxItem.item_id === (item.item_id || item.id)) && 
                    (boxItem.barcode_id === item.barcode_id)) {
                    totalAllocated += boxItem.quantity;
                }
            });
        });
        
        return item.current_stock - totalAllocated;
    }

    function returnItemToAvailable(boxItem) {
        // This function is called when removing items from boxes
        // The renderAvailableItems() function will automatically show available quantities
    }

    // Update counts
    function updateCounts() {
        const totalQuantity = transferItems.reduce((total, item) => total + item.quantity, 0);
        // Calculate total expanded box count (including multiple instances)
    let totalExpandedBoxes = 0;
    selectedBoxes.forEach(box => {
        totalExpandedBoxes += (box.request_quantity || 1);
    });
    $('#totalBoxesCount').text(totalExpandedBoxes);
        $('#totalItemsCount').text(transferItems.length);
        
        // Enable/disable buttons
        const hasItems = transferItems.length > 0;
        $('#createTransferBtn').prop('disabled', !hasItems);
        $('#printPackingSlips').prop('disabled', !hasItems);
    }

    // Print functionality
    $('#previewPrint').on('click', function() {
        if (transferBoxes.every(box => box.items.length === 0)) {
            Swal.fire('Warning', 'No items to print. Please add items to boxes first.', 'warning');
            return;
        }
        showPrintPreview();
    });

    $('#printPackingSlips').on('click', function() {
        if (transferBoxes.every(box => box.items.length === 0)) {
            Swal.fire('Warning', 'No items to print. Please add items to boxes first.', 'warning');
            return;
        }
        showPrintPreview();
    });

    // Show print preview
    function showPrintPreview() {
        const boxesWithItems = transferBoxes.filter(box => box.items.length > 0);
        
        if (boxesWithItems.length === 0) {
            Swal.fire('Error', 'No boxes with items found', 'error');
            return;
        }
        
        const sourceStore = $('#sourceStore option:selected').text();
        const destStore = $('#destinationStore option:selected').text();
        $('#printTransferRoute').text(`${sourceStore} → ${destStore}`);
        
        const container = $('#packingSlipsContainer');
        container.empty();
        
        boxesWithItems.forEach(box => {
            const slip = generatePackingSlip(box, sourceStore, destStore);
            container.append(slip);
        });
        
        $('#printPackingModal').modal('show');
    }

    function generatePackingSlip(box, sourceStore, destStore) {
        const totalItems = box.items.reduce((sum, item) => sum + item.quantity, 0);
        const totalValue = box.items.reduce((sum, item) => sum + (item.quantity * item.selling_price), 0);

        return `
            <div class="packing-slip mb-4 p-4 border" style="page-break-after: always;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4>PACKING SLIP</h4>
                        <p class="mb-0"><strong>Box #${box.id}</strong></p>
                        ${box.label ? `<p class="mb-0 text-muted">${box.label}</p>` : ''}
                    </div>
                    <div class="text-end">
                        <p class="mb-0"><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                        <p class="mb-0"><strong>Time:</strong> ${new Date().toLocaleTimeString()}</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>FROM:</h6>
                        <p class="mb-0">${sourceStore}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>TO:</h6>
                        <p class="mb-0">${destStore}</p>
                    </div>
                </div>
                
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th>Code</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${box.items.map(item => `
                            <tr>
                                <td>${escapeHtml(item.name)}</td>
                                <td>${escapeHtml(item.item_code)}</td>
                                <td>${item.quantity}</td>
                                <td>CFA ${parseFloat(item.selling_price).toFixed(2)}</td>
                                <td>CFA ${(item.quantity * item.selling_price).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2">TOTALS:</th>
                            <th>${totalItems}</th>
                            <th>-</th>
                            <th>CFA ${totalValue.toFixed(2)}</th>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Packed by:</strong> _________________</p>
                            <p><strong>Date:</strong> _________________</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Received by:</strong> _________________</p>
                            <p><strong>Date:</strong> _________________</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Print all slips
    $('#printAllSlips').on('click', function() {
        const printContent = $('#packingSlipsContainer').html();
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Packing Slips</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .packing-slip { page-break-after: always; }
                        .packing-slip:last-child { page-break-after: avoid; }
                    }
                    body { font-size: 12px; }
                    .table-sm th, .table-sm td { padding: 0.25rem; }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    });

    // Print transfer boxes from view modal
    $('#printTransferBoxes').on('click', function() {
        printTransferBoxesDetails();
    });

    // Function to print transfer boxes details
    function printTransferBoxesDetails() {
        const transferBoxesContent = $('#transferBoxesDetails').html();
        const transferInfo = $('#transferInfo').html();
        
        if (!transferBoxesContent || transferBoxesContent.trim() === '') {
            Swal.fire('Error', 'No transfer boxes data to print', 'error');
            return;
        }

        // Create a temporary container to process the content
        const tempContainer = $('<div>').html(transferBoxesContent);
        
        // Remove price-related content
        tempContainer.find('th:nth-child(4), td:nth-child(4)').remove(); // Unit Cost
        tempContainer.find('th:nth-child(4), td:nth-child(4)').remove(); // Selling Price (now 4th after removing previous)
        tempContainer.find('th:nth-child(4), td:nth-child(4)').remove(); // Total (now 4th after removing previous)
        
        // Remove price badges from box headers
        tempContainer.find('.badge').each(function() {
            if ($(this).text().includes('$')) {
                $(this).remove();
            }
        });
        
        // Remove any elements containing dollar signs
        tempContainer.find('*').each(function() {
            const text = $(this).text();
            if (text.includes('$') && !text.includes('Item') && !text.includes('Barcode')) {
                // Only remove if it's likely a price, not part of item names
                if (text.match(/\$[\d,]+\.?\d*/)) {
                    $(this).remove();
                }
            }
        });
        
        const processedContent = tempContainer.html();
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Transfer Boxes Details</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .no-print { display: none !important; }
                        .page-break { page-break-after: always; }
                    }
                    body { 
                        font-size: 12px; 
                        margin: 20px;
                    }
                    .header-section {
                        border-bottom: 2px solid #007bff;
                        padding-bottom: 15px;
                        margin-bottom: 20px;
                    }
                    .box-section {
                        margin-bottom: 25px;
                        border: 1px solid #dee2e6;
                        border-radius: 8px;
                        overflow: hidden;
                    }
                    .box-header {
                        background-color: #f8f9fa;
                        padding: 12px 15px;
                        border-bottom: 1px solid #dee2e6;
                        font-weight: bold;
                    }
                    .box-content {
                        padding: 15px;
                    }
                    .table-sm th, .table-sm td { 
                        padding: 0.4rem 0.5rem; 
                        font-size: 11px;
                    }
                    .table-sm th {
                        background-color: #f8f9fa;
                        font-weight: bold;
                    }
                    .print-date {
                        text-align: right;
                        font-size: 10px;
                        color: #6c757d;
                        margin-top: 20px;
                    }
                    .no-prices-notice {
                        background-color: #f8f9fa;
                        border: 1px solid #dee2e6;
                        padding: 8px 12px;
                        margin-bottom: 15px;
                        border-radius: 4px;
                        font-size: 10px;
                        color: #6c757d;
                        text-align: center;
                    }
                </style>
            </head>
            <body>
                <div class="header-section">
                    <h3><i class="bi bi-boxes me-2"></i>Transfer Boxes Details</h3>
                    <div class="mt-2">
                        ${transferInfo || ''}
                    </div>
                  
                </div>
                
                <div class="boxes-content">
                    ${processedContent}
                </div>
                
                <div class="print-date">
                    Printed on: ${new Date().toLocaleString()}
                </div>
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.focus();
        
        // Add a small delay to ensure content is loaded before printing
        setTimeout(() => {
            printWindow.print();
        }, 500);
    }

    // Create transfer with selected boxes and items
    $('#createTransferBtn').on('click', function() {
        if (selectedBoxes.length === 0) {
            Swal.fire('Error', 'Please select at least one box', 'error');
            return;
        }
        
        if (transferItems.length === 0) {
            Swal.fire('Error', 'Please add items to the transfer', 'error');
            return;
        }

        // Calculate total expanded box count
        let totalExpandedBoxes = 0;
        selectedBoxes.forEach(box => {
            totalExpandedBoxes += (box.request_quantity || 1);
        });
        
        // Show loading indicator
        Swal.fire({
            title: 'Creating Transfer...',
            text: `Creating transfer with ${totalExpandedBoxes} box instance(s) and ${transferItems.length} items`,
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Disable the create button to prevent multiple submissions
        $('#createTransferBtn').prop('disabled', true);

        // Decide action based on destination selection
        const selectedDestinations = getSelectedDestinationStoreIds();
        const isMultiDestination = selectedDestinations.length > 1 || (transferItems.some(it => it.destination_store_id));

        // Debug: Log the data being sent
        console.log('Selected boxes:', selectedBoxes);
        console.log('Transfer items:', transferItems);
        
        const formData = {
            csrf_token: csrfToken,
            action: isMultiDestination ? 'create_box_transfer_multi' : 'create_box_transfer',
            source_store_id: 1, // Main warehouse
            destination_store_id: isMultiDestination ? '' : (selectedDestinations[0] || ''),
            notes: $('textarea[name="notes"]').val(),
            selected_boxes: JSON.stringify(selectedBoxes.map(box => ({
                id: box.id,
                box_number: box.box_number,
                box_name: box.box_name,
                box_type: box.box_type,
                request_quantity: box.request_quantity || 1
            }))),
            transfer_items: JSON.stringify(transferItems)
        };
        
        console.log('Form data being sent:', formData);

        $.ajax({
            url: '../ajax/process_transfer_shipment.php',
            type: 'POST',
            data: formData,
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                // Calculate total expanded box count for display
                let totalExpandedBoxes = 0;
                selectedBoxes.forEach(box => {
                    totalExpandedBoxes += (box.request_quantity || 1);
                });
                
                Swal.fire({
                    title: 'Success!',
                    html: `
                        <div class="text-center">
                            <p><strong>Transfer Created Successfully!</strong></p>
                            <p>Shipment Number: <strong>${response.shipment_number}</strong></p>
                            <p>Box Instances: <strong>${totalExpandedBoxes}</strong></p>
                            <p>Items to Transfer: <strong>${transferItems.length}</strong></p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    $('#createTransferModal').modal('hide');
                    window.loadTransfers(); // Refresh the transfers table
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }).fail(function(xhr, status, error) {
            console.error('Transfer creation failed:', {xhr, status, error});
            console.error('Response text:', xhr.responseText);
            
            let errorMessage = 'Failed to create transfer. Please try again.';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                errorMessage = `Server error: ${xhr.status} ${xhr.statusText}`;
            }
            
            Swal.fire('Error', errorMessage, 'error');
        }).always(function() {
            // Re-enable the button
            $('#createTransferBtn').prop('disabled', false);
        });
    });

    // Reset transfer form
    function resetTransferForm() {
        // Hide all steps and show step 1
        $('#step3').hide();
        $('#step2').hide();
        $('#step1').show();
        
        // Reset form
        $('#createTransferForm')[0].reset();
        $('.destination-store-checkbox').prop('checked', false);
        
        // Reset data
        availableItems = [];
        availableBoxes = [];
        selectedBoxes = [];
        transferItems = [];
        
        // Reset UI
        $('#availableItemsTable tbody').empty();
        $('#availableItemsCount').text('0 items');
        $('#availableBoxesTable tbody').empty();
        $('#availableBoxesCount').text('0 boxes');
        $('#transferBoxesContainer').html(`
            <div class="text-center text-muted py-5">
                <i class="bi bi-box-seam display-4 mb-3"></i>
                <p>No boxes selected yet.<br>
                <small>Go back to Step 2 to select warehouse boxes first.</small></p>
            </div>
        `);
        updateSelectedBoxesUI();
        updateCounts();
        
        // Disable buttons
        $('#nextToBoxes').prop('disabled', true);
        $('#nextToItems').prop('disabled', true);
        $('#createTransferBtn').prop('disabled', true);
        
        // Reload transfers table
        transfersTable.ajax.reload();
    }

    // Search and filter handlers
    $('#warehouseItemSearchForm').on('submit', function(e) {
        e.preventDefault();
        loadSourceItems();
    });

    $('#categoryFilter').on('change', function() {
        loadSourceItems();
    });

    // Existing transfer management functions (viewTransfer, packShipment, cancelShipment)
    // These remain unchanged from the original implementation...
    
    // View transfer details
    window.viewTransfer = function(shipmentId) {
        $.ajax({
            url: '../ajax/get_transfer_shipments.php',
            type: 'POST',
            data: { 
                csrf_token: csrfToken,
                action: 'details',
                shipment_id: shipmentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayTransferDetails(response.shipment);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    };

    // OLD FUNCTIONS REMOVED: displayTransferDetails and loadTransferActions 
    // These were designed for the old modal structure and have been replaced 
    // with the new detailed view functions below

    // Complete transfer (pack and receive in one step)
    window.packShipment = function(shipmentId) {
        Swal.fire({
            title: 'Complete Transfer?',
            text: 'This will move items from the source store to the destination store and complete the transfer.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Complete Transfer!',
            confirmButtonColor: '#28a745'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading indicator
                Swal.fire({
                    title: 'Completing Transfer...',
                    text: 'Please wait while we move items between stores',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Disable all pack buttons to prevent multiple clicks
                $('button[onclick*="packShipment"]').prop('disabled', true);
                
                $.ajax({
                    url: '../ajax/process_transfer_shipment.php',
                    type: 'POST',
                    data: {
                        csrf_token: csrfToken,
                        action: 'pack_shipment',
                        shipment_id: shipmentId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Transfer Complete!',
                                text: 'Items have been successfully moved between stores.',
                                icon: 'success',
                                timer: 3000,
                                showConfirmButton: false
                            });
                            transfersTable.ajax.reload();
                            $('#viewTransferModal').modal('hide');
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to pack shipment. Please try again.', 'error');
                    },
                    complete: function() {
                        // Re-enable pack buttons
                        $('button[onclick*="packShipment"]').prop('disabled', false);
                    }
                });
            }
        });
    };

    // Cancel shipment
    window.cancelShipment = function(shipmentId) {
        Swal.fire({
            title: 'Cancel Shipment?',
            input: 'textarea',
            inputLabel: 'Reason for cancellation',
            inputPlaceholder: 'Enter reason...',
            showCancelButton: true,
            confirmButtonText: 'Cancel Shipment',
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading indicator
                Swal.fire({
                    title: 'Cancelling Shipment...',
                    text: 'Please wait while we process the cancellation',
                    icon: 'info',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Disable cancel buttons to prevent multiple clicks
                $('button[onclick*="cancelShipment"]').prop('disabled', true);
                
                $.ajax({
                    url: '../ajax/process_transfer_shipment.php',
                    type: 'POST',
                    data: {
                        csrf_token: csrfToken,
                        action: 'cancel_shipment',
                        shipment_id: shipmentId,
                        reason: result.value || 'No reason provided'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Shipment cancelled successfully!',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            transfersTable.ajax.reload();
                            $('#viewTransferModal').modal('hide');
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to cancel shipment. Please try again.', 'error');
                    },
                    complete: function() {
                        // Re-enable cancel buttons
                        $('button[onclick*="cancelShipment"]').prop('disabled', false);
                    }
                });
            }
        });
    };

    // View transfer details
    function viewTransferDetails(shipmentId) {
        // Show loading
        Swal.fire({
            title: 'Loading Transfer Details...',
            text: 'Please wait while we fetch the transfer information',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '../ajax/get_transfer_with_boxes.php',
            type: 'GET',
            data: { shipment_id: shipmentId },
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                displayTransferDetails(response);
                Swal.close();
                $('#viewTransferModal').modal('show');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        }).fail(function(xhr, status, error) {
            console.error('Failed to load transfer details:', {xhr, status, error});
            Swal.fire('Error', 'Failed to load transfer details. Please try again.', 'error');
        });
    }

    // Display transfer details in modal
    function displayTransferDetails(data) {
        const shipment = data.shipment;
        const destinations = data.destinations || [];
        const boxes = data.boxes;
        const summary = data.summary;

        // Transfer Information
        // Destination display (supports multiple)
        let destHtml = '';
        if (Array.isArray(destinations) && destinations.length > 1) {
            destHtml = destinations.map(d => `<span class=\"badge bg-info me-1\">${escapeHtml(d.name)} (${escapeHtml(d.code)})</span>`).join('');
        } else {
            destHtml = `${escapeHtml(shipment.destination_store_name)} (${escapeHtml(shipment.destination_store_code)})`;
        }

        const transferInfo = `
            <div class="row g-2">
                <div class="col-6"><strong>Shipment Number:</strong></div>
                <div class="col-6">${escapeHtml(shipment.shipment_number)}</div>
                
                <div class="col-6"><strong>Source Store:</strong></div>
                <div class="col-6">${escapeHtml(shipment.source_store_name)} (${escapeHtml(shipment.source_store_code)})</div>
                
                <div class="col-6"><strong>Destination Store(s):</strong></div>
                <div class="col-6">${destHtml}</div>
                
                <div class="col-6"><strong>Status:</strong></div>
                <div class="col-6">
                    <span class="badge ${getStatusBadgeClass(shipment.status)}">${escapeHtml(shipment.status.toUpperCase())}</span>
                </div>
                
                <div class="col-6"><strong>Created By:</strong></div>
                <div class="col-6">${escapeHtml(shipment.created_by_username)}</div>
                
                <div class="col-6"><strong>Created Date:</strong></div>
                <div class="col-6">${formatDateTime(shipment.created_at)}</div>
                
                ${shipment.notes ? `
                <div class="col-12 mt-2"><strong>Notes:</strong></div>
                <div class="col-12"><div class="alert alert-info mb-0">${escapeHtml(shipment.notes)}</div></div>
                ` : ''}
            </div>
        `;
        $('#transferInfo').html(transferInfo);

        // Summary Statistics
        const summaryInfo = `
            <div class="row g-2">
                <div class="col-6"><strong>Total Boxes:</strong></div>
                <div class="col-6"><span class="badge bg-primary">${summary.total_boxes}</span></div>
                
                <div class="col-6"><strong>Unique Items:</strong></div>
                <div class="col-6"><span class="badge bg-info">${summary.total_unique_items}</span></div>
                
                <div class="col-6"><strong>Total Quantity:</strong></div>
                <div class="col-6"><span class="badge bg-success">${summary.total_quantity}</span></div>
                
                <div class="col-6"><strong>Total Value:</strong></div>
                <div class="col-6"><span class="badge bg-warning text-dark">$${parseFloat(summary.total_value).toFixed(2)}</span></div>
            </div>
        `;
        $('#transferSummary').html(summaryInfo);

        // Transfer Boxes
        let boxesHtml = '';
        
        boxes.forEach((box, index) => {
            // Create a meaningful box display name
            let boxDisplayName = `Box #${box.box_number}`;
            
            if (box.warehouse_box_name) {
                // If we have warehouse box info, use it
                boxDisplayName = `${box.warehouse_box_name}`;
                if (box.warehouse_box_type) {
                    boxDisplayName += ` (${box.warehouse_box_type})`;
                }
                if (box.warehouse_box_number) {
                    boxDisplayName += ` - #${box.warehouse_box_number}`;
                }
            } else if (box.box_label && box.box_label !== 'Default Box') {
                // Fallback to box_label if no warehouse box info
                boxDisplayName = box.box_label;
            }
            
            boxesHtml += `
                <div class="accordion mb-3" id="transferBoxAccordion${index}">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#boxCollapse${index}" aria-expanded="true">
                                <div class="d-flex w-100 justify-content-between align-items-center me-3">
                                    <div>
                                        <i class="bi bi-box me-2"></i>
                                        <strong>${escapeHtml(boxDisplayName)}</strong>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-secondary">${box.item_count} items</span>
                                        <span class="badge bg-primary">${box.total_quantity} qty</span>
                                        <span class="badge bg-success">$${parseFloat(box.total_value || 0).toFixed(2)}</span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="boxCollapse${index}" class="accordion-collapse collapse show">
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Destination</th>
                                                <th>Item</th>
                                                <th>Barcode</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Selling Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
            `;
            
            box.items.forEach(item => {
                const total = parseFloat(item.quantity_requested) * parseFloat(item.selling_price);
                boxesHtml += `
                    <tr>
                        <td>
                            ${item.dest_store_name ? `<span class=\"badge bg-info\">${escapeHtml(item.dest_store_name)} (${escapeHtml(item.dest_store_code)})</span>` : `<span class=\"badge bg-secondary\">${escapeHtml(shipment.destination_store_name)} (${escapeHtml(shipment.destination_store_code)})</span>`}
                        </td>
                        <td>
                            <strong>${escapeHtml(item.item_name)}</strong><br>
                            <small class="text-muted">${escapeHtml(item.item_code)}</small>
                        </td>
                        <td><code>${escapeHtml(item.barcode)}</code></td>
                        <td><span class="badge bg-secondary">${item.quantity_requested}</span></td>
                        <td>$${parseFloat(item.unit_cost || 0).toFixed(2)}</td>
                        <td>$${parseFloat(item.selling_price || 0).toFixed(2)}</td>
                        <td><strong>$${total.toFixed(2)}</strong></td>
                    </tr>
                `;
            });
            
            boxesHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $('#transferBoxesDetails').html(boxesHtml);

        // Show/hide delete button based on status
        const deleteBtn = $('#deleteTransferBtn');
        // Show delete button for all transfers (can reverse any status)
        deleteBtn.show();
        deleteBtn.attr('data-shipment-id', shipment.id);
    }

    // Helper functions
    function getStatusBadgeClass(status) {
        const classes = {
            'pending': 'bg-warning text-dark',
            'in_transit': 'bg-info',
            'received': 'bg-success',
            'cancelled': 'bg-danger'
        };
        return classes[status] || 'bg-secondary';
    }

    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleString();
    }

    // Make function globally available
    window.viewTransferDetails = viewTransferDetails;

    // Utility function
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? String(text).replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }

    function truncateText(text, max) {
        if (!text) return '';
        const t = String(text);
        return t.length > max ? t.slice(0, max - 1) + '…' : t;
    }

    // ===== DIRECT TRANSFER FUNCTIONS =====
    
    // Variables for direct transfer
    let warehouseItems = [];
    let selectedDirectItems = [];

    // Load warehouse inventory for direct transfer
    function loadWarehouseInventory() {
        const search = $('#warehouseItemSearch').val();
        const categoryId = $('#warehouseCategoryFilter').val();
        const stockFilter = $('#warehouseStockFilter').val();
        const destinationStoreId = (getSelectedDestinationStoreIds()[0] || '');
        
        if (!destinationStoreId) {
            Swal.fire('Error', 'Please select a destination store first', 'warning');
            return;
        }
        
        $.ajax({
            url: '../ajax/get_warehouse_inventory.php',
            type: 'POST',
            data: {
                csrf_token: $('input[name="csrf_token"]').val(),
                search: search,
                category_id: categoryId,
                stock_filter: stockFilter,
                destination_store_id: destinationStoreId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    warehouseItems = response.items;
                    renderWarehouseItems();
                } else {
                    Swal.fire('Error', response.message || 'Failed to load warehouse inventory', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to load warehouse inventory', 'error');
            }
        });
    }

    // Render warehouse items for selection
    function renderWarehouseItems() {
        const tableBody = $('#warehouseItemsTable tbody');
        tableBody.empty();
        
        // Update items count
        $('#warehouseItemsCount').text(`${warehouseItems.length} items`);
        
        if (warehouseItems.length === 0) {
            tableBody.html('<tr><td colspan="6" class="text-center text-muted py-4">No items found</td></tr>');
            return;
        }
        
        warehouseItems.forEach(item => {
            const isSelected = selectedDirectItems.some(selected => 
                selected.item_id === item.id && selected.barcode_id === item.barcode_id
            );
            
            const itemRow = `
                <tr class="${isSelected ? 'table-active' : ''}" data-item-id="${item.id}" data-barcode-id="${item.barcode_id}">
                    <td>
                        <div class="fw-bold">${escapeHtml(item.name)}</div>
                        <div class="text-muted small">
                            <span class="me-2"><strong>Code:</strong> ${escapeHtml(item.item_code)}</span>
                            <span class="me-2"><strong>Container:</strong> ${item.container_number ? `<span class="badge bg-primary">${escapeHtml(item.container_number)}</span>` : '<span class="text-muted">-</span>'}</span>
                        </div>
                        <div class="text-muted small">
                            <span class="me-2">${escapeHtml(item.category_name || 'N/A')}</span>
                            ${item.size ? `<span class="me-2">Size: ${escapeHtml(item.size)}</span>` : ''}
                            ${item.color ? `<span>Color: ${escapeHtml(item.color)}</span>` : ''}
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="fw-bold text-primary">${item.warehouse_stock}</span>
                    </td>
                    <td class="text-center">
                        <span class="fw-bold text-secondary">${item.destination_stock}</span>
                    </td>
                    <td class="text-center">
                        <span class="text-success fw-bold">$${parseFloat(item.selling_price).toFixed(2)}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-warning text-dark" title="Base price">
                            $${parseFloat(item.cost_price || item.base_price || 0).toFixed(2)}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary add-direct-item" 
                                data-item='${JSON.stringify(item)}'
                                ${item.warehouse_stock <= 0 ? 'disabled' : ''}>
                            <i class="bi bi-plus me-1"></i>
                            Add
                        </button>
                    </td>
                </tr>
            `;
            tableBody.append(itemRow);
        });
    }

    // Get stock status badge (kept for backward compatibility)
    function getStockBadge(status, stock) {
        switch(status) {
            case 'out_of_stock':
                return '<span class="badge bg-danger">Out of Stock</span>';
            case 'low_stock':
                return '<span class="badge bg-warning">Low Stock</span>';
            case 'in_stock':
                return '<span class="badge bg-success">In Stock</span>';
            default:
                return '<span class="badge bg-secondary">Unknown</span>';
        }
    }

    // Get minimal stock status text
    function getStockStatusText(status) {
        switch(status) {
            case 'out_of_stock':
                return 'Out of stock';
            case 'low_stock':
                return 'Low stock';
            case 'in_stock':
                return 'Available';
            default:
                return 'Unknown';
        }
    }

    // Get selected quantity for an item
    function getSelectedQuantity(itemId, barcodeId) {
        const selected = selectedDirectItems.find(item => 
            item.item_id === itemId && item.barcode_id === barcodeId
        );
        return selected ? selected.quantity : 1;
    }

    // Add item to direct transfer selection
    $(document).on('click', '.add-direct-item', function() {
        const itemData = JSON.parse($(this).attr('data-item'));
        
        // Check if item already selected
        const existingIndex = selectedDirectItems.findIndex(item => 
            item.item_id === itemData.id && item.barcode_id === itemData.barcode_id
        );
        
        if (existingIndex >= 0) {
            Swal.fire('Info', 'Item already selected. You can adjust the quantity in the transfer summary.', 'info');
            return;
        }
        
        // Add new selection with default quantity of 1
        selectedDirectItems.push({
            item_id: itemData.id,
            barcode_id: itemData.barcode_id,
            item_name: itemData.name,
            item_code: itemData.item_code,
            barcode: itemData.barcode,
            quantity: 1,
            cost_price: itemData.cost_price,
            selling_price: itemData.selling_price,
            average_cost: itemData.cost_price || itemData.base_price || 0,
            size: itemData.size,
            color: itemData.color
        });
        
        updateDirectTransferSummary();
        renderWarehouseItems(); // Refresh to show updated state
    });

    // Update direct transfer summary
    function updateDirectTransferSummary() {
        const container = $('#directSelectedItems');
        const countBadge = $('#directSelectedCount');
        
        countBadge.text(selectedDirectItems.length);
        
        if (selectedDirectItems.length === 0) {
            container.html(`
                <div class="text-center text-muted py-5">
                    <i class="bi bi-list-check display-4 mb-3"></i>
                    <p>No items selected yet.<br>
                    <small>Select items from the warehouse inventory on the left.</small></p>
                </div>
            `);
            $('#createDirectTransfer').prop('disabled', true);
            return;
        }
        
        let html = `
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-arrow-right-circle me-2"></i>Items to Transfer
                        <span class="badge bg-light text-dark ms-2">${selectedDirectItems.length} items</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Average Cost</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
        `;

        selectedDirectItems.forEach((item, index) => {
            const total = item.quantity * item.selling_price;
            const averageCost = item.average_cost || item.cost_price || 0;
            html += `
                <tr>
                    <td>
                        <strong>${escapeHtml(item.item_name)}</strong><br>
                        <small class="text-muted">${escapeHtml(item.item_code)}</small>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm direct-quantity-input" 
                               value="${item.quantity}" min="1" 
                               data-item-index="${index}" style="width: 80px;">
                    </td>
                    <td>$${parseFloat(item.selling_price).toFixed(2)}</td>
                    <td>
                                                 <span class="badge bg-warning text-dark" title="Base price / Average cost (box cost split per item quantity + base price)">
                             $${parseFloat(averageCost).toFixed(2)}
                         </span>
                    </td>
                    <td><strong>$${total.toFixed(2)}</strong></td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger remove-direct-item" 
                                data-index="${index}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        container.html(html);
        $('#createDirectTransfer').prop('disabled', false);
    }

    // Remove item from direct transfer selection
    $(document).on('click', '.remove-direct-item', function() {
        const index = parseInt($(this).data('index'));
        selectedDirectItems.splice(index, 1);
        updateDirectTransferSummary();
        renderWarehouseItems(); // Refresh to show updated state
    });

    // Handle quantity changes in direct transfer summary
    $(document).on('change', '.direct-quantity-input', function() {
        const index = parseInt($(this).data('item-index'));
        const newQuantity = parseInt($(this).val()) || 1;
        
        if (newQuantity > 0 && selectedDirectItems[index]) {
            selectedDirectItems[index].quantity = newQuantity;
            updateDirectTransferSummary(); // Refresh to update totals
        }
    });

    // Clear all selected items
    $('#clearDirectSelection').on('click', function() {
        if (selectedDirectItems.length === 0) {
            return;
        }
        
        Swal.fire({
            title: 'Clear All Items?',
            text: 'This will remove all selected items from the transfer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, clear all',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                clearDirectTransferSelections();
                renderWarehouseItems(); // Refresh to show updated state
                Swal.fire('Cleared!', 'All items have been removed from the transfer.', 'success');
            }
        });
    });

    // Clear direct transfer selections
    function clearDirectTransferSelections() {
        selectedDirectItems = [];
        updateDirectTransferSummary();
    }

    // Search warehouse items
    $('#searchWarehouseItems').on('click', loadWarehouseInventory);
    $('#warehouseItemSearch').on('keypress', function(e) {
        if (e.which === 13) {
            loadWarehouseInventory();
        }
    });
    $('#warehouseCategoryFilter, #warehouseStockFilter').on('change', loadWarehouseInventory);

    // Create direct transfer
    $('#createDirectTransfer').on('click', function() {
        if (selectedDirectItems.length === 0) {
            Swal.fire('Error', 'Please select at least one item', 'error');
            return;
        }
        
        const destinationStoreId = (getSelectedDestinationStoreIds()[0] || '');
        const notes = $('textarea[name="notes"]').val();
        
        // Show loading indicator
        Swal.fire({
            title: 'Creating Direct Transfer...',
            text: `Creating transfer with ${selectedDirectItems.length} items`,
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '../ajax/process_transfer_shipment.php',
            type: 'POST',
            data: {
                csrf_token: $('input[name="csrf_token"]').val(),
                action: 'create_direct_transfer',
                destination_store_id: destinationStoreId,
                notes: notes,
                selected_items: JSON.stringify(selectedDirectItems)
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: `Direct transfer completed successfully! Transfer #${response.shipment_number}`,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        $('#createTransferModal').modal('hide');
                        window.loadTransfers(); // Refresh the transfers table
                    });
                } else {
                    Swal.fire('Error', response.message || 'Failed to create direct transfer', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to create direct transfer', 'error');
            }
        });
    });

    // Delete transfer functionality
    $(document).on('click', '#deleteTransferBtn', function() {
        const shipmentId = $(this).attr('data-shipment-id');
        
        if (!shipmentId) {
            Swal.fire('Error', 'Invalid shipment ID', 'error');
            return;
        }
        
        Swal.fire({
            title: 'Reverse Transfer?',
            text: 'This will permanently reverse the transfer and restore all inventory to its original state. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reverse transfer',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteTransfer(shipmentId);
            }
        });
    });
    
    // Delete transfer from table (same functionality, different entry point)
    function deleteTransferFromTable(shipmentId) {
        Swal.fire({
            title: 'Reverse Transfer?',
            text: 'This will permanently reverse the transfer and restore all inventory to its original state. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, reverse transfer',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteTransfer(shipmentId);
            }
        });
    }
    
    // Make function globally available
    window.deleteTransferFromTable = deleteTransferFromTable;
    
    function deleteTransfer(shipmentId) {
        Swal.fire({
            title: 'Reversing Transfer...',
            text: 'Please wait while we reverse the transfer and restore inventory...',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '../ajax/delete_transfer.php',
            type: 'POST',
            data: {
                csrf_token: $('input[name="csrf_token"]').val(),
                shipment_id: shipmentId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        $('#viewTransferModal').modal('hide');
                        window.loadTransfers(); // Refresh the transfers table
                    });
                } else {
                    Swal.fire('Error', response.message || 'Failed to delete transfer', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to delete transfer', 'error');
            }
        });
    }

});

// Store to Store Transfer Functions
let currentStep = 1;
let availableItems = [];
let transferItems = [];

// Initialize modal when opened
$('#storeToStoreModal').on('show.bs.modal', function() {
    resetStoreTransferForm();
});

// Step navigation
function showStoreTransferStep(step) {
    $('.store-transfer-step').hide();
    $(`#storeTransferStep${step}`).show();
    
    // Update buttons
    if (step === 1) {
        $('#storeTransferBackBtn').hide();
        $('#storeTransferNextBtn').show().text('Next').prop('disabled', !canProceedToStep2());
        $('#executeTransferBtn').hide();
    } else if (step === 2) {
        $('#storeTransferBackBtn').show();
        $('#storeTransferNextBtn').show().text('Review').prop('disabled', transferItems.length === 0);
        $('#executeTransferBtn').hide();
        
        // Ensure items are loaded when step 2 is shown
        console.log('Step 2 shown, checking if items need to be loaded'); // Debug log
        if (availableItems.length === 0) {
            console.log('No items loaded yet, calling loadAvailableItems'); // Debug log
            loadAvailableItems();
        } else {
            console.log('Items already loaded, refreshing display'); // Debug log
            displayAvailableItems();
        }
    } else if (step === 3) {
        $('#storeTransferBackBtn').show();
        $('#storeTransferNextBtn').hide();
        $('#executeTransferBtn').show();
        updateTransferSummary();
    }
}

// Check if can proceed to step 2
function canProceedToStep2() {
    const sourceStore = $('#sourceStoreSelect').val();
    const destStore = $('#destinationStoreSelect').val();
    return sourceStore && destStore && sourceStore !== destStore;
}

// Step 1: Store selection handlers
$('#sourceStoreSelect, #destinationStoreSelect').on('change', function() {
    $('#storeTransferNextBtn').prop('disabled', !canProceedToStep2());
});

$('#storeTransferNextBtn').on('click', function() {
    console.log('Next button clicked, currentStep:', currentStep); // Debug log
    if (currentStep === 1 && canProceedToStep2()) {
        console.log('Moving to step 2, calling loadAvailableItems'); // Debug log
        currentStep = 2;
        showStoreTransferStep(2);
        loadAvailableItems();
    } else if (currentStep === 2 && transferItems.length > 0) {
        console.log('Moving to step 3'); // Debug log
        currentStep = 3;
        showStoreTransferStep(3);
    }
});

$('#storeTransferBackBtn').on('click', function() {
    if (currentStep > 1) {
        currentStep--;
        showStoreTransferStep(currentStep);
    }
});

// Load available items
function loadAvailableItems() {
    const sourceStoreId = $('#sourceStoreSelect').val();
    const searchTerm = $('#itemSearchInput').val();
    
    console.log('Loading items for store:', sourceStoreId, 'search:', searchTerm); // Debug log
    
    $.ajax({
        url: '../ajax/get_store_items_for_transfer.php',
        type: 'GET',
        data: {
            store_id: sourceStoreId,
            search: searchTerm
        },
        dataType: 'json',
        success: function(response) {
            console.log('API Response:', response); // Debug log
            if (response.success) {
                availableItems = response.items;
                console.log('Available items set:', availableItems); // Debug log
                displayAvailableItems();
            } else {
                console.error('API Error:', response.message); // Debug log
                Swal.fire('Error', response.message || 'Failed to load items', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error); // Debug log
            Swal.fire('Error', 'Failed to load items', 'error');
        }
    });
}

// Display available items
function displayAvailableItems() {
    const tbody = $('#storeTransferAvailableItemsTable');
    
    console.log('Displaying available items:', availableItems); // Debug log
    
    if (!availableItems || availableItems.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="5" class="text-center text-muted py-3">
                    <i class="bi bi-exclamation-circle display-4 mb-2"></i>
                    <p>No items found</p>
                </td>
            </tr>
        `);
    } else {
        let html = '';
        availableItems.forEach(item => {
            const isAlreadyAdded = transferItems.some(ti => ti.item_id === item.item_id && ti.barcode_id === item.barcode_id);
            html += `
                <tr>
                    <td>${item.item_code || 'N/A'}</td>
                    <td>${item.item_name || 'N/A'}</td>
                    <td>$${(item.selling_price || 0).toFixed(2)}</td>
                    <td>${item.current_stock || 0}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary add-item-btn" 
                                data-item-id="${item.item_id}" 
                                data-barcode-id="${item.barcode_id}"
                                data-item-code="${item.item_code}"
                                data-item-name="${item.item_name}"
                                data-selling-price="${item.selling_price}"
                                data-stock="${item.current_stock}"
                                ${isAlreadyAdded ? 'disabled' : ''}>
                            <i class="bi bi-plus"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.html(html);
    }
}

// Add item to transfer
$(document).on('click', '.add-item-btn', function() {
    const item = {
        item_id: $(this).data('item-id'),
        barcode_id: $(this).data('barcode-id'),
        item_code: $(this).data('item-code'),
        item_name: $(this).data('item-name'),
        selling_price: $(this).data('selling-price'),
        stock: $(this).data('stock'),
        quantity: 1
    };
    
    // Check if item already exists
    const existingIndex = transferItems.findIndex(ti => ti.item_id === item.item_id && ti.barcode_id === item.barcode_id);
    if (existingIndex >= 0) {
        transferItems[existingIndex].quantity += 1;
    } else {
        transferItems.push(item);
    }
    
    displayTransferItems();
    displayAvailableItems(); // Refresh to update disabled state
    $('#storeTransferNextBtn').prop('disabled', transferItems.length === 0);
});

// Display transfer items
function displayTransferItems() {
    const tbody = $('#transferItemsTable');
    
    if (transferItems.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="4" class="text-center text-muted py-3">
                    <i class="bi bi-cart display-4 mb-2"></i>
                    <p>No items selected</p>
                </td>
            </tr>
        `);
    } else {
        let html = '';
        transferItems.forEach((item, index) => {
            html += `
                <tr>
                    <td>${item.item_code}</td>
                    <td>${item.item_name}</td>
                    <td>
                        <div class="input-group input-group-sm">
                            <button class="btn btn-outline-secondary" type="button" onclick="updateQuantity(${index}, -1)">-</button>
                            <input type="number" class="form-control text-center" value="${item.quantity}" min="1" max="${item.stock}" onchange="updateQuantity(${index}, 0, this.value)">
                            <button class="btn btn-outline-secondary" type="button" onclick="updateQuantity(${index}, 1)">+</button>
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeTransferItem(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.html(html);
    }
}

// Update quantity
function updateQuantity(index, change, newValue) {
    if (newValue !== undefined) {
        transferItems[index].quantity = Math.max(1, Math.min(transferItems[index].stock, parseInt(newValue) || 1));
    } else {
        transferItems[index].quantity = Math.max(1, Math.min(transferItems[index].stock, transferItems[index].quantity + change));
    }
    displayTransferItems();
}

// Remove transfer item
function removeTransferItem(index) {
    transferItems.splice(index, 1);
    displayTransferItems();
    displayAvailableItems(); // Refresh to update disabled state
    $('#storeTransferNextBtn').prop('disabled', transferItems.length === 0);
}

// Search items
$('#searchItemsBtn').on('click', function() {
    console.log('Search button clicked'); // Debug log
    loadAvailableItems();
});

$('#itemSearchInput').on('keypress', function(e) {
    if (e.which === 13) {
        console.log('Enter key pressed in search'); // Debug log
        loadAvailableItems();
    }
});

// Test API button
$('#testApiBtn').on('click', function() {
    const sourceStoreId = $('#sourceStoreSelect').val();
    console.log('Test API button clicked, sourceStoreId:', sourceStoreId);
    
    if (!sourceStoreId) {
        alert('Please select a source store first');
        return;
    }
    
    // Direct API test
    $.ajax({
        url: '../ajax/get_store_items_for_transfer.php',
        type: 'GET',
        data: {
            store_id: sourceStoreId,
            search: ''
        },
        dataType: 'json',
        success: function(response) {
            console.log('Test API Response:', response);
            alert('API Test Success! Check console for details. Items found: ' + (response.items ? response.items.length : 0));
        },
        error: function(xhr, status, error) {
            console.error('Test API Error:', xhr.responseText);
            alert('API Test Failed! Check console for details.');
        }
    });
});

// Update transfer summary
function updateTransferSummary() {
    const tbody = $('#transferSummaryTable');
    let html = '';
    let totalValue = 0;
    
    transferItems.forEach(item => {
        const itemValue = item.quantity * item.selling_price;
        totalValue += itemValue;
        html += `
            <tr>
                <td>${item.item_code}</td>
                <td>${item.item_name}</td>
                <td>${item.quantity}</td>
                <td>$${item.selling_price.toFixed(2)}</td>
            </tr>
        `;
    });
    
    html += `
        <tr class="table-light">
            <td colspan="3"><strong>Total Value:</strong></td>
            <td><strong>$${totalValue.toFixed(2)}</strong></td>
        </tr>
    `;
    
    tbody.html(html);
}

// Execute transfer
$('#executeTransferBtn').on('click', function() {
    if (transferItems.length === 0) {
        Swal.fire('Error', 'No items to transfer', 'error');
        return;
    }
    
    const sourceStoreId = $('#sourceStoreSelect').val();
    const destStoreId = $('#destinationStoreSelect').val();
    
    Swal.fire({
        title: 'Confirm Transfer',
        text: `Transfer ${transferItems.length} item(s) from source to destination store?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Transfer',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            executeMultipleTransfers(sourceStoreId, destStoreId);
        }
    });
});

// Execute multiple transfers
function executeMultipleTransfers(sourceStoreId, destStoreId) {
    let completed = 0;
    let failed = 0;
    const total = transferItems.length;
    
    Swal.fire({
        title: 'Processing Transfer...',
        text: 'Please wait while we process the transfer',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    transferItems.forEach((item, index) => {
        $.ajax({
            url: '../ajax/process_store_to_store_transfer.php',
            type: 'POST',
            data: {
                csrf_token: window.csrfToken,
                source_store_id: sourceStoreId,
                destination_store_id: destStoreId,
                item_id: item.item_id,
                barcode_id: item.barcode_id,
                quantity: item.quantity
            },
            dataType: 'json',
            success: function(response) {
                completed++;
                if (completed + failed === total) {
                    Swal.close();
                    if (failed === 0) {
                        Swal.fire({
                            title: 'Success!',
                            text: `Successfully transferred ${completed} item(s)`,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            $('#storeToStoreModal').modal('hide');
                            window.loadTransfers();
                            resetStoreTransferForm();
                        });
                    } else {
                        Swal.fire({
                            title: 'Partial Success',
                            text: `Transferred ${completed} items successfully, ${failed} failed`,
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            $('#storeToStoreModal').modal('hide');
                            window.loadTransfers();
                            resetStoreTransferForm();
                        });
                    }
                }
            },
            error: function() {
                failed++;
                if (completed + failed === total) {
                    Swal.close();
                    Swal.fire({
                        title: 'Transfer Completed',
                        text: `Transferred ${completed} items successfully, ${failed} failed`,
                        icon: failed === total ? 'error' : 'warning',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        $('#storeToStoreModal').modal('hide');
                        window.loadTransfers();
                        resetStoreTransferForm();
                    });
                }
            }
        });
    });
}

// Reset form when modal is hidden
$('#storeToStoreModal').on('hidden.bs.modal', function() {
    // Don't reset here since we reset on show
});

function resetStoreTransferForm() {
    currentStep = 1;
    availableItems = [];
    transferItems = [];
    
    // Reset form elements
    $('#sourceStoreSelect').val('');
    $('#destinationStoreSelect').val('');
    $('#itemSearchInput').val('');
    
    // Clear tables
    $('#storeTransferAvailableItemsTable').html(`
        <tr>
            <td colspan="5" class="text-center text-muted py-3">
                <i class="bi bi-search display-4 mb-2"></i>
                <p>Select stores and search for items</p>
            </td>
        </tr>
    `);
    
    $('#transferItemsTable').html(`
        <tr>
            <td colspan="4" class="text-center text-muted py-3">
                <i class="bi bi-cart display-4 mb-2"></i>
                <p>No items selected</p>
            </td>
        </tr>
    `);
    
    showStoreTransferStep(1);
}

</script> 

<style>
    .transfer-step {
        display: none;
    }
    
    .store-transfer-step {
        display: none;
    }
    
    .sticky-top {
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .input-group-sm .form-control {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    
    .input-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    
    .transfer-step.active {
        display: block;
    }
    
    .transfer-type-card {
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .transfer-type-card:hover {
        border-color: #007bff;
        transform: translateY(-2px);
    }
    
    .transfer-type-card.selected {
        border-color: #007bff;
        background-color: #f8f9fa;
    }
    
    .selection-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 24px;
        height: 24px;
        background-color: #007bff;
        color: white;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
    
    .transfer-type-card.selected .selection-indicator {
        display: flex;
    }
    
    /* Box quantity styling */
    .table-warning {
        background-color: #fff3cd !important;
    }
    
    .table-warning td {
        color: #856404;
    }
    
    .box-checkbox:disabled + td {
        opacity: 0.6;
    }
    
    .quantity-badge {
        font-size: 0.85em;
        padding: 0.25em 0.6em;
    }
</style>