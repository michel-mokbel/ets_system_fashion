<?php
/**
 * Purchase Order Management Workspace
 * -----------------------------------
 * Hosts the administrative interface for creating, editing, and monitoring
 * purchase orders. After the shared session bootstrap, the page renders
 * supplier/status/date filters, a server-side DataTable, and modal forms that
 * capture PO headers and line items. The interactive behavior is implemented in
 * `assets/js/purchase_orders.js`, which calls `ajax/get_purchase_orders.php`
 * for listings, `ajax/get_purchase_order.php` for detail views, and
 * `ajax/process_purchase_order.php` to persist changes.
 */
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo getTranslation('purchase_orders.title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPurchaseOrderModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('purchase_orders.add_purchase_order'); ?>
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('purchase_orders.supplier'); ?></label>
                <select class="form-select" name="supplier_id">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <?php 
                    $suppliers_query = "SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name";
                    $suppliers_result = $conn->query($suppliers_query);
                    
                    if ($suppliers_result && $suppliers_result->num_rows > 0) {
                        while ($supplier = $suppliers_result->fetch_assoc()) {
                            $selected = (isset($_GET['supplier_id']) && $_GET['supplier_id'] == $supplier['id']) ? 'selected' : '';
                            echo '<option value="' . $supplier['id'] . '" ' . $selected . '>' . htmlspecialchars($supplier['name']) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('purchase_orders.status'); ?></label>
                <select class="form-select" name="status">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="draft" <?php echo (isset($_GET['status']) && $_GET['status'] == 'draft') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('purchase_orders.draft'); ?>
                    </option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('purchase_orders.pending'); ?>
                    </option>
                    <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('purchase_orders.approved'); ?>
                    </option>
                    <option value="received" <?php echo (isset($_GET['status']) && $_GET['status'] == 'received') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('purchase_orders.received'); ?>
                    </option>
                    <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('purchase_orders.cancelled'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('purchase_orders.date_range'); ?></label>
                <input type="text" class="form-control date-range" name="date_range" value="<?php echo htmlspecialchars($_GET['date_range'] ?? ''); ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('purchase_orders.apply_filters'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Purchase Orders Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="purchaseOrdersTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('purchase_orders.po_number'); ?></th>
                        <th><?php echo getTranslation('purchase_orders.supplier'); ?></th>
                        <th><?php echo getTranslation('purchase_orders.date'); ?></th>
                        <th><?php echo getTranslation('purchase_orders.expected_delivery'); ?></th>
                        <th><?php echo getTranslation('purchase_orders.total_amount'); ?></th>
                        <th><?php echo getTranslation('purchase_orders.status'); ?></th>
                        <th width="120"><?php echo getTranslation('purchase_orders.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Purchase Order Modal -->
<div class="modal fade" id="addPurchaseOrderModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('purchase_orders.add_purchase_order'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPurchaseOrderForm" action="../ajax/process_purchase_order.php" method="POST" class="needs-validation" novalidate data-ajax="true" data-reload-table="#purchaseOrdersTable">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label for="poNumber" class="form-label"><?php echo getTranslation('purchase_orders.po_number'); ?> *</label>
                            <input type="text" class="form-control" id="poNumber" name="po_number" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="supplier" class="form-label"><?php echo getTranslation('purchase_orders.supplier'); ?> *</label>
                            <select class="form-select" id="supplier" name="supplier_id" required>
                                <option value=""><?php echo getTranslation('purchase_orders.select_supplier'); ?></option>
                                <?php 
                                if ($suppliers_result && $suppliers_result->num_rows > 0) {
                                    $suppliers_result->data_seek(0);
                                    while ($supplier = $suppliers_result->fetch_assoc()) {
                                        echo '<option value="' . $supplier['id'] . '">' . htmlspecialchars($supplier['name']) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="orderDate" class="form-label"><?php echo getTranslation('purchase_orders.date'); ?> *</label>
                            <input type="text" class="form-control datepicker" id="orderDate" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="expectedDelivery" class="form-label"><?php echo getTranslation('purchase_orders.expected_delivery'); ?> *</label>
                            <input type="text" class="form-control datepicker" id="expectedDelivery" name="expected_delivery" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="notes" class="form-label"><?php echo getTranslation('purchase_orders.notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 mb-3"><?php echo getTranslation('purchase_orders.items'); ?></h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="poItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="40%"><?php echo getTranslation('purchase_orders.item'); ?> *</th>
                                    <th width="15%"><?php echo getTranslation('purchase_orders.quantity'); ?> *</th>
                                    <th width="15%"><?php echo getTranslation('purchase_orders.unit_price'); ?> *</th>
                                    <th width="15%"><?php echo getTranslation('purchase_orders.total'); ?></th>
                                    <th width="15%"><?php echo getTranslation('purchase_orders.actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="item-row">
                                    <td>
                                        <select class="form-select item-select" name="items[0][item_id]" required>
                                            <option value=""><?php echo getTranslation('purchase_orders.select_item'); ?></option>
                                            <?php 
                                            $items_query = "SELECT id, name, item_code FROM inventory_items WHERE status = 'active' ORDER BY name";
                                            $items_result = $conn->query($items_query);
                                            
                                            if ($items_result && $items_result->num_rows > 0) {
                                                while ($item = $items_result->fetch_assoc()) {
                                                    echo '<option value="' . $item['id'] . '" data-code="' . htmlspecialchars($item['item_code']) . '">' 
                                                         . htmlspecialchars($item['name']) . ' (' . htmlspecialchars($item['item_code']) . ')</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <div class="invalid-feedback">
                                            <?php echo getTranslation('common.required'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control item-quantity" name="items[0][quantity]" min="1" value="1" required>
                                        <div class="invalid-feedback">
                                            <?php echo getTranslation('common.required'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control item-price" name="items[0][unit_price]" min="0" step="0.01" value="0.00" required>
                                        <div class="invalid-feedback">
                                            <?php echo getTranslation('common.required'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control item-total" readonly>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-item" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5">
                                        <button type="button" class="btn btn-success btn-sm" id="addItemRow">
                                            <i class="bi bi-plus-circle me-1"></i> 
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong><?php echo getTranslation('purchase_orders.total_amount'); ?>:</strong></td>
                                    <td>
                                        <input type="text" class="form-control" id="totalAmount" name="total_amount" readonly>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo getTranslation('common.cancel'); ?>
                    </button>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="status" id="po_status" value="draft">
                    <button type="button" class="btn btn-primary submit-po" data-status="draft">
                        <?php echo getTranslation('purchase_orders.save_as_draft'); ?>
                    </button>
                    <button type="button" class="btn btn-success submit-po" data-status="pending">
                        <?php echo getTranslation('purchase_orders.save_and_submit'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Purchase Order Modal -->
<div class="modal fade" id="viewPurchaseOrderModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('purchase_orders.view_purchase_order'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5><?php echo getTranslation('purchase_orders.purchase_order_details'); ?></h5>
                        <div class="row mt-3">
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.po_number'); ?>:</strong>
                                <div id="viewPoNumber"></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.date'); ?>:</strong>
                                <div id="viewOrderDate"></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.expected_delivery'); ?>:</strong>
                                <div id="viewExpectedDelivery"></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.status'); ?>:</strong>
                                <div id="viewStatus"></div>
                            </div>
                            <div class="col-12 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.notes'); ?>:</strong>
                                <div id="viewNotes"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5><?php echo getTranslation('purchase_orders.supplier_information'); ?></h5>
                        <div class="row mt-3">
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.supplier_name'); ?>:</strong>
                                <div id="viewSupplierName"></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.contact_person'); ?>:</strong>
                                <div id="viewContactPerson"></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.email'); ?>:</strong>
                                <div id="viewEmail"></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.phone'); ?>:</strong>
                                <div id="viewPhone"></div>
                            </div>
                            <div class="col-12 mb-2">
                                <strong><?php echo getTranslation('purchase_orders.address'); ?>:</strong>
                                <div id="viewAddress"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h5 class="mt-4 mb-3"><?php echo getTranslation('purchase_orders.items'); ?></h5>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="10%"><?php echo getTranslation('purchase_orders.item_code'); ?></th>
                                <th width="40%"><?php echo getTranslation('purchase_orders.item_name'); ?></th>
                                <th width="15%"><?php echo getTranslation('purchase_orders.quantity'); ?></th>
                                <th width="15%"><?php echo getTranslation('purchase_orders.unit_price'); ?></th>
                                <th width="15%"><?php echo getTranslation('purchase_orders.total'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="viewItemsTable">
                            <!-- Will be populated dynamically -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end"><strong><?php echo getTranslation('purchase_orders.total_amount'); ?>:</strong></td>
                                <td id="viewTotalAmount" class="fw-bold"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div id="receiveSection" class="d-none mt-4">
                    <h5 class="mb-3"><?php echo getTranslation('purchase_orders.receive_items'); ?></h5>
                    <form id="receiveItemsForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="po_id" id="receivePurchaseOrderId">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th><?php echo getTranslation('purchase_orders.item_name'); ?></th>
                                        <th><?php echo getTranslation('purchase_orders.ordered_quantity'); ?></th>
                                        <th><?php echo getTranslation('purchase_orders.received_quantity'); ?> *</th>
                                    </tr>
                                </thead>
                                <tbody id="receiveItemsTable">
                                    <!-- Will be populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="receiveDate" class="form-label"><?php echo getTranslation('purchase_orders.received_date'); ?> *</label>
                                    <input type="text" class="form-control datepicker" id="receiveDate" name="receive_date" value="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">
                                        <?php echo getTranslation('common.required'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="receiveNotes" class="form-label"><?php echo getTranslation('purchase_orders.notes'); ?></label>
                                    <textarea class="form-control" id="receiveNotes" name="notes" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success mt-2">
                            <i class="bi bi-check-circle me-1"></i> <?php echo getTranslation('purchase_orders.confirm_receipt'); ?>
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-danger me-2" id="cancelPurchaseOrderBtn">
                        <i class="bi bi-x-circle me-1"></i> <?php echo getTranslation('purchase_orders.cancel_order'); ?>
                    </button>
                    <button type="button" class="btn btn-success me-2" id="approvePurchaseOrderBtn">
                        <i class="bi bi-check-circle me-1"></i> <?php echo getTranslation('purchase_orders.approve'); ?>
                    </button>
                    <button type="button" class="btn btn-primary me-2" id="receiveItemsBtn">
                        <i class="bi bi-box-seam me-1"></i> <?php echo getTranslation('purchase_orders.receive_items'); ?>
                    </button>
                    <button type="button" class="btn btn-info me-2" id="printPurchaseOrderBtn">
                        <i class="bi bi-printer me-1"></i> <?php echo getTranslation('purchase_orders.print'); ?>
                    </button>
                </div>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo getTranslation('common.close'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/purchase_orders.js"></script>
<?php require_once '../includes/footer.php'; ?> 
