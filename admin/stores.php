<?php
/**
 * Store Directory Administration
 * ------------------------------
 * Provides administrators with CRUD controls for store metadata, including
 * address details, phone contacts, and manager assignments. Authentication and
 * CSRF protection are bootstrapped via the shared header, while
 * `assets/js/stores.js` manages the DataTable, modal lifecycle, and AJAX
 * submissions to `ajax/get_stores.php`, `ajax/get_store.php`, and
 * `ajax/process_store.php`.
 */
require_once '../includes/header.php';

if (!is_admin()) {
    redirect('../index.php');
}

// Make sure CSRF token is set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- Hidden CSRF token for AJAX requests -->
<input type="hidden" id="csrf_token" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h1 class="mb-3 mb-md-0"><?php echo getTranslation('stores.title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStoreModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('stores.add_store'); ?>
    </button>
</div>

<!-- Stores Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="storesTable" class="table table-striped table-hover mb-0" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('stores.store_code'); ?></th>
                        <th><?php echo getTranslation('stores.store_name'); ?></th>
                        <th><?php echo getTranslation('stores.address'); ?></th>
                        <th><?php echo getTranslation('stores.phone'); ?></th>
                        <th><?php echo getTranslation('stores.manager'); ?></th>
                        <th><?php echo getTranslation('common.status'); ?></th>
                        <th width="120"><?php echo getTranslation('common.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Store Modal -->
<div class="modal fade" id="addStoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('stores.add_store'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStoreForm" action="../ajax/process_store.php" method="POST" class="needs-validation" novalidate data-ajax="true" data-reload-table="#storesTable">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="storeCode" class="form-label"><?php echo getTranslation('stores.store_code'); ?> *</label>
                        <input type="text" class="form-control" id="storeCode" name="store_code" required>
                        <div class="invalid-feedback"><?php echo getTranslation('common.required'); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="storeName" class="form-label"><?php echo getTranslation('stores.store_name'); ?> *</label>
                        <input type="text" class="form-control" id="storeName" name="name" required>
                        <div class="invalid-feedback"><?php echo getTranslation('common.required'); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="storeAddress" class="form-label"><?php echo getTranslation('stores.address'); ?></label>
                        <textarea class="form-control" id="storeAddress" name="address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="storePhone" class="form-label"><?php echo getTranslation('stores.phone'); ?></label>
                        <input type="text" class="form-control" id="storePhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="storeManager" class="form-label"><?php echo getTranslation('stores.manager'); ?></label>
                        <select class="form-select" id="storeManager" name="manager_id">
                            <option value=""><?php echo getTranslation('stores.select_manager'); ?></option>
                            <?php
                            // Populate managers server-side as fallback
                            $managers_query = "SELECT id, full_name FROM users WHERE role = 'store_manager' AND status = 'active' ORDER BY full_name";
                            $managers_result = $conn->query($managers_query);
                            if ($managers_result && $managers_result->num_rows > 0) {
                                while ($manager = $managers_result->fetch_assoc()) {
                                    echo '<option value="' . $manager['id'] . '">' . htmlspecialchars($manager['full_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="storeStatus" class="form-label"><?php echo getTranslation('common.status'); ?></label>
                        <select class="form-select" id="storeStatus" name="status">
                            <option value="active" selected><?php echo getTranslation('common.active'); ?></option>
                            <option value="inactive"><?php echo getTranslation('common.inactive'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo getTranslation('common.cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo getTranslation('common.save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Store Modal -->
<div class="modal fade" id="editStoreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('stores.edit_store'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStoreForm" action="../ajax/process_store.php" method="POST" class="needs-validation" novalidate data-ajax="true" data-reload-table="#storesTable">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="store_id" id="editStoreId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editStoreCode" class="form-label"><?php echo getTranslation('stores.store_code'); ?> *</label>
                        <input type="text" class="form-control" id="editStoreCode" name="store_code" required>
                        <div class="invalid-feedback"><?php echo getTranslation('common.required'); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="editStoreName" class="form-label"><?php echo getTranslation('stores.store_name'); ?> *</label>
                        <input type="text" class="form-control" id="editStoreName" name="name" required>
                        <div class="invalid-feedback"><?php echo getTranslation('common.required'); ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="editStoreAddress" class="form-label"><?php echo getTranslation('stores.address'); ?></label>
                        <textarea class="form-control" id="editStoreAddress" name="address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="editStorePhone" class="form-label"><?php echo getTranslation('stores.phone'); ?></label>
                        <input type="text" class="form-control" id="editStorePhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="editStoreManager" class="form-label"><?php echo getTranslation('stores.manager'); ?></label>
                        <select class="form-select" id="editStoreManager" name="manager_id">
                            <option value=""><?php echo getTranslation('stores.select_manager'); ?></option>
                            <!-- Manager options will be loaded via AJAX or server-side -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editStoreStatus" class="form-label"><?php echo getTranslation('common.status'); ?></label>
                        <select class="form-select" id="editStoreStatus" name="status">
                            <option value="active"><?php echo getTranslation('common.active'); ?></option>
                            <option value="inactive"><?php echo getTranslation('common.inactive'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo getTranslation('common.cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo getTranslation('common.save'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?> 