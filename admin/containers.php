<?php
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

// Only admin can access this page
require_role('admin');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo getTranslation('containers.title'); ?></h1>
    <div>
        <button class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#containerAnalyticsModal" style="display: none;">
            <i class="bi bi-bar-chart me-1"></i> Analytics
        </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContainerModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('containers.add_container'); ?>
    </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('containers.supplier'); ?></label>
                <select class="form-select" name="supplier_id">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <?php
                    $suppliers_query = "SELECT * FROM suppliers WHERE status = 'active' ORDER BY name";
                    $suppliers_result = $conn->query($suppliers_query);
                    
                    if ($suppliers_result && $suppliers_result->num_rows > 0) {
                        while ($supplier = $suppliers_result->fetch_assoc()) {
                            $selected = (isset($_GET['supplier_id']) && $_GET['supplier_id'] == $supplier['id']) ? 'selected' : '';
                            echo "<option value='" . $supplier['id'] . "' $selected>" . htmlspecialchars($supplier['name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('containers.status'); ?></label>
                <select class="form-select" name="status">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('containers.pending'); ?>
                    </option>
                    <option value="received" <?php echo (isset($_GET['status']) && $_GET['status'] == 'received') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('containers.received'); ?>
                    </option>
                    <option value="processed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'processed') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('containers.processed'); ?>
                    </option>
                    <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('containers.completed'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo getTranslation('reports.start_date'); ?></label>
                <input type="date" class="form-control" name="start_date" value="<?php echo $_GET['start_date'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo getTranslation('reports.end_date'); ?></label>
                <input type="date" class="form-control" name="end_date" value="<?php echo $_GET['end_date'] ?? ''; ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('inventory.apply_filters'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Containers Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="containersTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('containers.container_number'); ?></th>
                        <th><?php echo getTranslation('containers.supplier'); ?></th>
                        <th><?php echo getTranslation('containers.total_weight'); ?></th>
                        <th>Total Price</th>
                        <th><?php echo getTranslation('containers.amount_paid'); ?></th>
                        <th><?php echo getTranslation('containers.remaining_balance'); ?></th>
                        <th>Actual Profit</th>
                        <th><?php echo getTranslation('containers.arrival_date'); ?></th>
                        <th><?php echo getTranslation('containers.status'); ?></th>
                        <th width="180"><?php echo getTranslation('inventory.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Container Modal -->
<div class="modal fade" id="addContainerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('containers.add_container'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addContainerForm" action="../ajax/process_container.php" method="POST" class="needs-validation" data-reload-table="#containersTable">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 1.5rem;">
                    <!-- Basic Information -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-3 mb-4">
                                <i class="bi bi-info-circle me-2"></i>Basic Information
                            </h6>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="containerNumber" class="form-label fw-bold"><?php echo getTranslation('containers.container_number'); ?> *</label>
                            <input type="text" class="form-control form-control-lg" id="containerNumber" name="container_number" placeholder="Enter container number" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="supplierId" class="form-label fw-bold"><?php echo getTranslation('containers.supplier'); ?> *</label>
                            <select class="form-select form-select-lg" id="supplierId" name="supplier_id" required>
                                <option value=""><?php echo getTranslation('purchase_orders.select_supplier'); ?></option>
                                <?php
                                $suppliers_result = $conn->query($suppliers_query);
                                if ($suppliers_result && $suppliers_result->num_rows > 0) {
                                    while ($supplier = $suppliers_result->fetch_assoc()) {
                                        echo "<option value='" . $supplier['id'] . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="arrivalDate" class="form-label fw-bold"><?php echo getTranslation('containers.arrival_date'); ?></label>
                            <input type="date" class="form-control form-control-lg" id="arrivalDate" name="arrival_date">
                        </div>
                    </div>
                    
                    <!-- Weight and Base Cost -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-3 mb-4">
                                <i class="bi bi-scales me-2"></i>Weight & Base Cost
                            </h6>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="totalWeight" class="form-label fw-bold"><?php echo getTranslation('containers.total_weight'); ?> (KG) *</label>
                            <input type="number" class="form-control form-control-lg" id="totalWeight" name="total_weight_kg" step="0.01" min="0" placeholder="0.00" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="totalPrice" class="form-label fw-bold">Total Price (CFA) *</label>
                            <input type="number" class="form-control form-control-lg" id="totalPrice" name="total_price" step="0.01" min="0" placeholder="0.00" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Information -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-3 mb-4">
                                <i class="bi bi-currency-dollar me-2"></i>Financial Information
                            </h6>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label for="shipmentCost" class="form-label fw-bold">Shipment Cost (CFA)</label>
                            <input type="number" class="form-control" id="shipmentCost" name="shipment_cost" step="0.01" min="0" value="0" placeholder="0.00">
                            <small class="text-muted">Transportation & logistics</small>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label for="profitMargin" class="form-label fw-bold">Profit Margin (%)</label>
                            <input type="number" class="form-control" id="profitMargin" name="profit_margin_percentage" step="0.01" min="0" max="100" value="0" placeholder="0.00">
                            <small class="text-muted">Expected profit percentage</small>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label for="totalAllCosts" class="form-label fw-bold">Total All Costs (CFA)</label>
                            <input type="number" class="form-control" id="totalAllCosts" step="0.01" min="0" readonly>
                            <small class="text-muted">Base + Shipment costs</small>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-3 mb-4">
                                <i class="bi bi-credit-card me-2"></i>Payment Information
                            </h6>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="amountPaid" class="form-label fw-bold"><?php echo getTranslation('containers.amount_paid'); ?> (CFA)</label>
                            <input type="number" class="form-control" id="amountPaid" name="amount_paid" step="0.01" min="0" value="0" placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="remainingBalance" class="form-label fw-bold"><?php echo getTranslation('containers.remaining_balance'); ?> (CFA)</label>
                            <input type="number" class="form-control" id="remainingBalance" step="0.01" min="0" readonly>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-3 mb-4">
                                <i class="bi bi-chat-dots me-2"></i>Additional Information
                            </h6>
                        </div>
                        <div class="col-12 mb-4">
                            <label for="containerNotes" class="form-label fw-bold"><?php echo getTranslation('containers.notes'); ?></label>
                            <textarea class="form-control" id="containerNotes" name="notes" rows="4" placeholder="Enter any additional notes or comments about this container..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Container Items Section -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-3 mb-4">
                                <i class="bi bi-box me-2"></i>Container Items
                            </h6>
                            <p class="text-muted mb-4">Add items that will be included in this container</p>
                        </div>
                        <div class="col-12 mb-4">
                            <div class="table-responsive border rounded">
                                <table class="table table-sm table-hover mb-0" id="createContainerItemsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="px-3 py-2">Item</th>
                                            <th class="px-3 py-2">Type</th>
                                            <th class="px-3 py-2">Quantity</th>
                                            <th class="px-3 py-2">Base Price</th>
                                            <th class="px-3 py-2">Total Cost</th>
                                            <th class="px-3 py-2" width="120">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="createContainerItemsTableBody">
                                        <tr id="noItemsRow">
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="bi bi-inbox display-6 text-muted mb-3 d-block"></i>
                                                <em>No items added yet. Click "Add Item" to get started.</em>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-4 p-3 bg-light rounded">
                                <div class="h5 text-primary mb-0">
                                    <i class="bi bi-calculator me-2"></i>Total Items Value: <span id="totalItemsValue" class="fw-bold">CFA 0.00</span>
                                </div>
                                <button type="button" class="btn btn-success btn-lg" id="addItemBtn">
                                    <i class="bi bi-plus-circle me-2"></i>Add Item
                                </button>
                            </div>
                            
                            <!-- Add Item Buttons -->
                            <div class="mt-4" id="addItemButtons" style="display: none;">
                                <div class="d-flex gap-3 justify-content-center">
                                    <button type="button" class="btn btn-outline-success btn-lg add-item" data-item-type="box">
                                        <i class="bi bi-box me-2"></i>Add Warehouse Box
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-lg add-item" data-item-type="existing_item">
                                        <i class="bi bi-archive me-2"></i>Add Existing Item
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-lg add-item" data-item-type="new_item">
                                        <i class="bi bi-plus-circle me-2"></i>Add New Item
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Summary -->
                    <div class="row mb-4" style="display: none;">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-3 mb-4">
                                <i class="bi bi-calculator me-2"></i>Financial Summary
                            </h6>
                            <div class="row text-center">
                                <div class="col-md-3 mb-4">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body py-3">
                                            <div class="h4 text-primary mb-1" id="summaryBaseCost">CFA 0.00</div>
                                            <div class="small text-muted">Base Cost</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body py-3">
                                            <div class="h4 text-info mb-1" id="summaryTotalCosts">CFA 0.00</div>
                                            <div class="small text-muted">Total Costs</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body py-3">
                                            <div class="h4 text-success mb-1" id="summaryExpectedRevenue">CFA 0.00</div>
                                            <div class="small text-muted">Expected Revenue</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-4">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body py-3">
                                            <div class="h4 text-warning mb-1" id="summaryExpectedProfit">CFA 0.00</div>
                                            <div class="small text-muted">Expected Profit</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="position: relative; z-index: 1056; background: white; border-top: 1px solid #dee2e6; padding: 1.5rem;">
                    <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg" id="saveContainerBtn" style="position: relative; z-index: 1057;">
                        <i class="bi bi-save me-2"></i>Save Container
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Container Modal -->
<div class="modal fade" id="editContainerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('containers.edit_container'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_container.php" method="POST" class="needs-validation" data-ajax="true" data-reload-table="#containersTable" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="container_id" id="editContainerId">
                
                <div class="modal-body">
                    <!-- Basic Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-info-circle me-2"></i>Basic Information
                            </h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editContainerNumber" class="form-label"><?php echo getTranslation('containers.container_number'); ?> *</label>
                            <input type="text" class="form-control" id="editContainerNumber" name="container_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editSupplierId" class="form-label"><?php echo getTranslation('containers.supplier'); ?> *</label>
                            <select class="form-select" id="editSupplierId" name="supplier_id" required>
                                <option value=""><?php echo getTranslation('purchase_orders.select_supplier'); ?></option>
                                <?php
                                $suppliers_result = $conn->query($suppliers_query);
                                if ($suppliers_result && $suppliers_result->num_rows > 0) {
                                    while ($supplier = $suppliers_result->fetch_assoc()) {
                                        echo "<option value='" . $supplier['id'] . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editArrivalDate" class="form-label"><?php echo getTranslation('containers.arrival_date'); ?></label>
                            <input type="date" class="form-control" id="editArrivalDate" name="arrival_date">
                        </div>
                    </div>
                    
                    <!-- Weight & Cost Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                <i class="bi bi-scales me-2"></i>Weight & Cost Information
                            </h6>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editTotalWeight" class="form-label"><?php echo getTranslation('containers.total_weight'); ?> *</label>
                            <input type="number" class="form-control" id="editTotalWeight" name="total_weight_kg" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editTotalPrice" class="form-label">Total Price *</label>
                            <input type="number" class="form-control" id="editTotalPrice" name="total_price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="editShipmentCost" class="form-label">Shipment Cost</label>
                            <input type="number" class="form-control" id="editShipmentCost" name="shipment_cost" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <label for="editProfitMargin" class="form-label">Profit Margin (%)</label>
                            <input type="number" class="form-control" id="editProfitMargin" name="profit_margin_percentage" step="0.01" min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="editActualProfit" class="form-label">Actual Profit</label>
                            <input type="number" class="form-control" id="editActualProfit" name="actual_profit" step="0.01" placeholder="Enter actual profit">
                            <div class="form-text">Manual entry for accountant</div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="editAmountPaid" class="form-label"><?php echo getTranslation('containers.amount_paid'); ?></label>
                            <input type="number" class="form-control" id="editAmountPaid" name="amount_paid" step="0.01" min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="editStatus" class="form-label"><?php echo getTranslation('containers.status'); ?></label>
                            <select class="form-select" id="editStatus" name="status">
                                <option value="pending"><?php echo getTranslation('containers.pending'); ?></option>
                                <option value="received"><?php echo getTranslation('containers.received'); ?></option>
                                <option value="processed"><?php echo getTranslation('containers.processed'); ?></option>
                                <option value="completed"><?php echo getTranslation('containers.completed'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label for="editRemainingBalance" class="form-label"><?php echo getTranslation('containers.remaining_balance'); ?></label>
                            <input type="number" class="form-control" id="editRemainingBalance" step="0.01" min="0" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editContainerNotes" class="form-label"><?php echo getTranslation('containers.notes'); ?></label>
                        <textarea class="form-control" id="editContainerNotes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo getTranslation('inventory.cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo getTranslation('inventory.save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Financial Summary Modal -->
<div class="modal fade" id="financialSummaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-bar-chart me-2"></i>Financial Summary
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="financialSummaryContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Container Analytics Modal -->
<div class="modal fade" id="containerAnalyticsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-graph-up me-2"></i>Container Analytics Dashboard
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="containerAnalyticsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Process Container Modal -->
<div class="modal fade" id="processContainerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-gear me-2"></i>Processing Container
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Processing...</span>
                    </div>
                    <h6 class="mb-2">Processing Container...</h6>
                    <p class="text-muted mb-0">Please wait while we process the container items and update the inventory.</p>
                    <div class="progress mt-3" style="height: 8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="text-muted mt-2 d-block">This may take a few moments depending on the number of items.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelProcessBtn" style="display: none;">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
#processContainerModal .modal-header {
    border-bottom: none;
}

#processContainerModal .modal-content {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

#processContainerModal .progress {
    background-color: #e9ecef;
    border-radius: 10px;
}

#processContainerModal .progress-bar {
    border-radius: 10px;
    transition: width 0.3s ease;
}

#processContainerModal .spinner-border {
    animation: spinner-border 1s linear infinite;
}

#processContainerModal .btn-close-white {
    filter: invert(1) grayscale(100%) brightness(200%);
}
</style>

<!-- Manage Items Modal -->
<div class="modal fade" id="manageItemsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-box-seam me-2"></i>Manage Container Items
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Container Summary -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div id="containerItemsSummary">
                            <!-- Container summary will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Items Table -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">
                        <i class="bi bi-list-ul me-2"></i>Container Items
                    </h6>
                    <button type="button" class="btn btn-success btn-sm" id="addItemBtn">
                        <i class="bi bi-plus-circle me-1"></i>Add Item
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="containerItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Base Price</th>
                                <th>Total Cost</th>
                                <th>Selling Price</th>
                                <th>Added Date</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Add Item Buttons -->
                <div class="mt-3 text-center" id="addItemButtons">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-success add-item" data-item-type="box">
                            <i class="bi bi-box me-1"></i>Add Warehouse Box
                        </button>
                        <button type="button" class="btn btn-outline-info add-item" data-item-type="existing_item">
                            <i class="bi bi-archive me-1"></i>Add Existing Item
                        </button>
                        <button type="button" class="btn btn-outline-warning add-item" data-item-type="new_item">
                            <i class="bi bi-plus-circle me-1"></i>Add New Item
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Warehouse Box Modal -->
<div class="modal fade" id="addBoxItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-box me-2"></i>Add Warehouse Box
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="#" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="item_type" value="box">
                <input type="hidden" name="container_id" id="addBoxContainerId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Box Selection *</label>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary" id="selectExistingBoxBtn">
                                <i class="bi bi-search me-2"></i>Select Existing Box
                            </button>
                            <button type="button" class="btn btn-outline-success" id="createNewBoxBtn">
                                <i class="bi bi-plus-circle me-2"></i>Create New Box
                            </button>
                        </div>
                    </div>
                    
                    <!-- Existing Box Selection Section -->
                    <div id="existingBoxSection" style="display: none;">
                        <div class="mb-3">
                            <label for="warehouseBoxId" class="form-label">Select Warehouse Box *</label>
                            <select class="form-select" id="warehouseBoxId" name="warehouse_box_id">
                                <option value="">Choose a warehouse box...</option>
                                <!-- Options will be loaded dynamically -->
                            </select>
                            <div class="invalid-feedback">Please select a warehouse box</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="boxQuantity" class="form-label">Quantity to Add *</label>
                            <input type="number" class="form-control" id="boxQuantity" name="quantity" min="1"  required>
                            <div class="invalid-feedback">Please enter a valid quantity</div>
                            <small class="text-muted">Current stock: <span id="boxAvailableQuantity">-</span> (informational only)</small>
                        </div>
                    </div>
                    
                    <!-- New Box Creation Section -->
                    <div id="newBoxSection" style="display: none;">
                        <div class="mb-3">
                            <label for="newBoxNumber" class="form-label">Box Number *</label>
                            <input type="text" class="form-control" id="newBoxNumber" name="new_box_number" placeholder="e.g., BOX-001" required>
                            <div class="form-text">Unique identifier for the box (e.g., BOX-001).</div>
                            <div class="invalid-feedback">Please enter a box number</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newBoxName" class="form-label">Box Name *</label>
                            <input type="text" class="form-control" id="newBoxName" name="new_box_name" placeholder="e.g., Premium Box" required>
                            <div class="form-text">Descriptive name for the box.</div>
                            <div class="invalid-feedback">Please enter a box name</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newBoxType" class="form-label">Box Type</label>
                            <input type="text" class="form-control" id="newBoxType" name="new_box_type" placeholder="e.g., SHEIN, ZARA, etc.">
                            <div class="form-text">Optional: Category or type of items in this box.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newBoxUnitCost" class="form-label">Unit Cost (CFA)</label>
                            <input type="number" class="form-control" id="newBoxUnitCost" name="new_box_unit_cost" step="0.01" min="0" value="0" placeholder="0.00">
                            <div class="form-text">Cost per box unit in CFA currency.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newBoxQuantity" class="form-label">Box Quantity *</label>
                            <input type="number" class="form-control" id="newBoxQuantity" name="new_box_quantity" min="1" value="1" required>
                            <div class="form-text">How many of this box type to create and add to your inventory.</div>
                            <div class="invalid-feedback">Please enter a valid quantity</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newBoxNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="newBoxNotes" name="new_box_notes" rows="3" placeholder="Additional notes about this box..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Add Box
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Existing Item Modal -->
<div class="modal fade" id="addExistingItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-archive me-2"></i>Add Existing Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="#" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="item_type" value="existing_item">
                <input type="hidden" name="container_id" id="addExistingItemContainerId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="existingItemId" class="form-label">Select Item *</label>
                        <select class="form-select" id="existingItemId" name="item_id" required>
                            <option value="">Choose an existing item...</option>
                            <!-- Options will be loaded dynamically -->
                        </select>
                        <div class="invalid-feedback">Please select an item</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="existingItemQuantity" class="form-label">Quantity to Add *</label>
                        <input type="number" class="form-control" id="existingItemQuantity" name="quantity" min="1" value="1" required>
                        <div class="invalid-feedback">Please enter a valid quantity</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-plus-circle me-1"></i>Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item During Container Creation Modal -->
<div class="modal fade" id="addItemDuringCreationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add Item to Container
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Choose the type of item you want to add to this container:</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-success btn-lg add-item-during-creation" data-item-type="box">
                        <i class="bi bi-box me-2"></i>
                        <div><strong>Warehouse Box</strong></div>
                        <small class="text-muted">Select existing boxes or create new ones</small>
                    </button>
                    <button type="button" class="btn btn-outline-info btn-lg add-item-during-creation" data-item-type="existing_item">
                        <i class="bi bi-archive me-2"></i>
                        <div><strong>Existing Item</strong></div>
                        <small class="text-muted">Select from current inventory</small>
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-lg add-item-during-creation" data-item-type="new_item">
                        <i class="bi bi-plus-circle me-2"></i>
                        <div><strong>New Item</strong></div>
                        <small class="text-muted">Create a new item on-the-fly</small>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Add New Item Modal -->
<div class="modal fade" id="addNewItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Add New Item
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="#" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="item_type" value="new_item">
                <input type="hidden" name="container_id" id="addNewItemContainerId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="newItemName" class="form-label">Item Name *</label>
                            <input type="text" class="form-control" id="newItemName" name="name" required>
                            <div class="invalid-feedback">Please enter an item name</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="newItemCode" class="form-label">Item Code *</label>
                            <input type="text" class="form-control" id="newItemCode" name="code" required>
                            <div class="invalid-feedback">Please enter an item code</div>
                            <div class="duplicate-code-feedback text-danger small mt-1" style="display: none;">
                                <i class="bi bi-exclamation-triangle me-1"></i>This item code already exists in the container or inventory
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="newItemCategory" class="form-label">Category</label>
                            <select class="form-select" id="newItemCategory" name="category_id">
                                <option value="">Choose category...</option>
                                <?php
                                $categories_query = "SELECT * FROM categories ORDER BY name";
                                $categories_result = $conn->query($categories_query);
                                if ($categories_result && $categories_result->num_rows > 0) {
                                    while ($category = $categories_result->fetch_assoc()) {
                                        echo "<option value='" . $category['id'] . "'>" . htmlspecialchars($category['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="newItemDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="newItemDescription" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="newItemBrand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="newItemBrand" name="brand">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="newItemSize" class="form-label">Size</label>
                            <input type="text" class="form-control" id="newItemSize" name="size">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="newItemColor" class="form-label">Color</label>
                            <input type="text" class="form-control" id="newItemColor" name="color">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="newItemMaterial" class="form-label">Material</label>
                            <input type="text" class="form-control" id="newItemMaterial" name="material">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="newItemQuantity" class="form-label">Quantity *</label>
                            <input type="number" class="form-control" id="newItemQuantity" name="quantity" min="1" value="1" required>
                            <div class="invalid-feedback">Please enter a valid quantity</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="newItemUnitCost" class="form-label">Unit Cost (CFA) *</label>
                            <input type="number" class="form-control" id="newItemUnitCost" name="unit_cost" step="0.01" min="0" required>
                            <div class="invalid-feedback">Please enter a valid unit cost</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="newItemSellingPrice" class="form-label">Selling Price (CFA) *</label>
                            <input type="number" class="form-control" id="newItemSellingPrice" name="selling_price" step="0.01" min="0" required>
                            <div class="invalid-feedback">Please enter a selling price</div>
                            <small class="text-muted">Total: <span id="newItemTotalCost">CFA 0.00</span></small>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-plus-circle me-1"></i>Add New Item
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
                <h5 class="modal-title">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editItemForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="update_item">
                <input type="hidden" name="item_id" id="editItemId">
                <input type="hidden" name="container_id" id="editItemContainerId">
                <input type="hidden" name="item_type" id="editItemType">
                
                <div class="modal-body">
                    <!-- Box Edit Section -->
                    <div id="editBoxSection" style="display: none;">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Box Item:</strong> Edit box details below.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editBoxCode" class="form-label">Box Number *</label>
                                <input type="text" class="form-control" id="editBoxCode" name="box_item_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editBoxName" class="form-label">Box Name *</label>
                                <input type="text" class="form-control" id="editBoxName" name="box_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editBoxDescription" class="form-label">Box Notes</label>
                            <textarea class="form-control" id="editBoxDescription" name="box_description" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editBoxType" class="form-label">Box Type</label>
                                <input type="text" class="form-control" id="editBoxType" name="box_brand">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editBoxUnitCost" class="form-label">Unit Cost (CFA)</label>
                                <input type="number" class="form-control" id="editBoxUnitCost" name="box_unit_cost" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editBoxQuantity" class="form-label">Quantity *</label>
                                <input type="number" class="form-control" id="editBoxQuantity" name="box_quantity_in_container" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Item Edit Section -->
                    <div id="editItemSection" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editItemCode" class="form-label">Item Code *</label>
                                <input type="text" class="form-control" id="editItemCode" name="item_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editItemName" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="editItemName" name="name" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editItemDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editItemDescription" name="description" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editItemCategory" class="form-label">Category</label>
                                <select class="form-select" id="editItemCategory" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php
                                    $categories = $conn->query("SELECT id, name FROM categories ORDER BY name");
                                    while ($cat = $categories->fetch_assoc()) {
                                        echo "<option value='{$cat['id']}'>{$cat['name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editItemSubcategory" class="form-label">Subcategory</label>
                                <select class="form-select" id="editItemSubcategory" name="subcategory_id">
                                    <option value="">Select Subcategory</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="editItemBrand" class="form-label">Brand</label>
                                <input type="text" class="form-control" id="editItemBrand" name="brand">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="editItemSize" class="form-label">Size</label>
                                <input type="text" class="form-control" id="editItemSize" name="size">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="editItemColor" class="form-label">Color</label>
                                <input type="text" class="form-control" id="editItemColor" name="color">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="editItemMaterial" class="form-label">Material</label>
                                <input type="text" class="form-control" id="editItemMaterial" name="material">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editItemUnitCost" class="form-label">Unit Cost (CFA) *</label>
                                <input type="number" class="form-control" id="editItemUnitCost" name="unit_cost" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editItemSellingPrice" class="form-label">Selling Price (CFA) *</label>
                                <input type="number" class="form-control" id="editItemSellingPrice" name="selling_price" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editItemQuantity" class="form-label">Quantity *</label>
                                <input type="number" class="form-control" id="editItemQuantity" name="quantity_in_container" min="1" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
<script src="../assets/js/containers.js"></script> 