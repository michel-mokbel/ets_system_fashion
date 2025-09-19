<?php
/**
 * Supplier Relationship Management
 * --------------------------------
 * Provides the administrative interface for maintaining supplier records,
 * including contact details, status flags, and notes that feed procurement
 * workflows. After authentication via the shared header, the page renders a
 * filterable DataTable and modals driven by `assets/js/suppliers.js`, which in
 * turn communicate with `ajax/get_suppliers.php`, `ajax/get_supplier.php`, and
 * `ajax/process_supplier.php` for CRUD operations.
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
    <h1><?php echo getTranslation('suppliers.title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('suppliers.add_supplier'); ?>
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?php echo getTranslation('suppliers.name'); ?></label>
                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_GET['name'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo getTranslation('suppliers.status'); ?></label>
                <select class="form-select" name="status">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('common.active'); ?>
                    </option>
                    <option value="inactive" <?php echo (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('suppliers.inactive'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('suppliers.apply_filters'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="suppliersTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('suppliers.name'); ?></th>
                        <th><?php echo getTranslation('suppliers.contact_person'); ?></th>
                        <th><?php echo getTranslation('suppliers.email'); ?></th>
                        <th><?php echo getTranslation('suppliers.phone'); ?></th>
                        <th><?php echo getTranslation('suppliers.status'); ?></th>
                        <th width="120"><?php echo getTranslation('suppliers.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('suppliers.add_supplier'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_supplier.php" method="POST" class="needs-validation" novalidate data-ajax="true" data-reload-table="#suppliersTable">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="supplierName" class="form-label"><?php echo getTranslation('suppliers.name'); ?> *</label>
                        <input type="text" class="form-control" id="supplierName" name="name" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="contactPerson" class="form-label"><?php echo getTranslation('suppliers.contact_person'); ?></label>
                            <input type="text" class="form-control" id="contactPerson" name="contact_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label"><?php echo getTranslation('suppliers.email'); ?></label>
                            <input type="email" class="form-control" id="email" name="email">
                            <div class="invalid-feedback">
                                <?php echo getTranslation('suppliers.invalid_email'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label"><?php echo getTranslation('suppliers.phone'); ?></label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label"><?php echo getTranslation('suppliers.status'); ?></label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" selected><?php echo getTranslation('common.active'); ?></option>
                                <option value="inactive"><?php echo getTranslation('suppliers.inactive'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label"><?php echo getTranslation('suppliers.address'); ?></label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo getTranslation('common.cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo getTranslation('common.save'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('suppliers.edit_supplier'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_supplier.php" method="POST" class="needs-validation" novalidate data-ajax="true" data-reload-table="#suppliersTable">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="supplier_id" id="editSupplierId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editSupplierName" class="form-label"><?php echo getTranslation('suppliers.name'); ?> *</label>
                        <input type="text" class="form-control" id="editSupplierName" name="name" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editContactPerson" class="form-label"><?php echo getTranslation('suppliers.contact_person'); ?></label>
                            <input type="text" class="form-control" id="editContactPerson" name="contact_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEmail" class="form-label"><?php echo getTranslation('suppliers.email'); ?></label>
                            <input type="email" class="form-control" id="editEmail" name="email">
                            <div class="invalid-feedback">
                                <?php echo getTranslation('suppliers.invalid_email'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editPhone" class="form-label"><?php echo getTranslation('suppliers.phone'); ?></label>
                            <input type="text" class="form-control" id="editPhone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editStatus" class="form-label"><?php echo getTranslation('suppliers.status'); ?></label>
                            <select class="form-select" id="editStatus" name="status">
                                <option value="active"><?php echo getTranslation('common.active'); ?></option>
                                <option value="inactive"><?php echo getTranslation('suppliers.inactive'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAddress" class="form-label"><?php echo getTranslation('suppliers.address'); ?></label>
                        <textarea class="form-control" id="editAddress" name="address" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo getTranslation('common.cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo getTranslation('common.save'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Supplier Modal -->
<div class="modal fade" id="viewSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('suppliers.view_supplier'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><?php echo getTranslation('suppliers.name'); ?>:</strong> <span id="viewSupplierName"></span></p>
                        <p><strong><?php echo getTranslation('suppliers.contact_person'); ?>:</strong> <span id="viewContactPerson"></span></p>
                        <p><strong><?php echo getTranslation('suppliers.email'); ?>:</strong> <a href="mailto:" id="viewEmailLink"><span id="viewEmail"></span></a></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><?php echo getTranslation('suppliers.phone'); ?>:</strong> <span id="viewPhone"></span></p>
                        <p><strong><?php echo getTranslation('suppliers.status'); ?>:</strong> <span id="viewStatus"></span></p>
                        <p><strong><?php echo getTranslation('suppliers.created_at'); ?>:</strong> <span id="viewCreatedAt"></span></p>
                    </div>
                </div>
                <div class="mt-3">
                    <p><strong><?php echo getTranslation('suppliers.address'); ?>:</strong></p>
                    <div class="p-3 bg-light rounded" id="viewAddress"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo getTranslation('common.close'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="editSupplierBtn">
                    <i class="bi bi-pencil me-1"></i> <?php echo getTranslation('common.edit'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/suppliers.js"></script>
<?php require_once '../includes/footer.php'; ?> 
