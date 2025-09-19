<?php
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo getTranslation('assets.title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('assets.add_asset'); ?>
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('assets.category'); ?></label>
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
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('assets.location'); ?></label>
                <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('assets.status'); ?></label>
                <select class="form-select" name="status">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="operational" <?php echo (isset($_GET['status']) && $_GET['status'] == 'operational') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('assets.operational'); ?>
                    </option>
                    <option value="maintenance" <?php echo (isset($_GET['status']) && $_GET['status'] == 'maintenance') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('assets.in_maintenance'); ?>
                    </option>
                    <option value="retired" <?php echo (isset($_GET['status']) && $_GET['status'] == 'retired') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('assets.retired'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('assets.apply_filters'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assets Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="assetsTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('assets.asset_code'); ?></th>
                        <th><?php echo getTranslation('assets.name'); ?></th>
                        <th><?php echo getTranslation('assets.category'); ?></th>
                        <th><?php echo getTranslation('assets.location'); ?></th>
                        <th><?php echo getTranslation('assets.purchase_date'); ?></th>
                        <th><?php echo getTranslation('assets.warranty'); ?></th>
                        <th><?php echo getTranslation('assets.status'); ?></th>
                        <th width="120"><?php echo getTranslation('assets.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Asset Modal -->
<div class="modal fade" id="addAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('assets.add_asset'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_asset.php" method="POST" class="needs-validation" data-ajax="true" data-reload-table="#assetsTable" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="assetCode" class="form-label"><?php echo getTranslation('assets.asset_code'); ?> *</label>
                            <input type="text" class="form-control" id="assetCode" name="asset_code" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="assetName" class="form-label"><?php echo getTranslation('assets.name'); ?> *</label>
                            <input type="text" class="form-control" id="assetName" name="name" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assetDescription" class="form-label"><?php echo getTranslation('assets.description'); ?></label>
                        <textarea class="form-control" id="assetDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="assetCategory" class="form-label"><?php echo getTranslation('assets.category'); ?></label>
                            <select class="form-select" id="assetCategory" name="category_id">
                                <option value=""><?php echo getTranslation('assets.select_category'); ?></option>
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
                            <label for="assetLocation" class="form-label"><?php echo getTranslation('assets.location'); ?></label>
                            <input type="text" class="form-control" id="assetLocation" name="location">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="assetPurchaseDate" class="form-label"><?php echo getTranslation('assets.purchase_date'); ?></label>
                            <input type="text" class="form-control datepicker" id="assetPurchaseDate" name="purchase_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="assetWarranty" class="form-label"><?php echo getTranslation('assets.warranty'); ?></label>
                            <input type="text" class="form-control datepicker" id="assetWarranty" name="warranty_expiry">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assetStatus" class="form-label"><?php echo getTranslation('assets.status'); ?></label>
                        <select class="form-select" id="assetStatus" name="status">
                            <option value="operational"><?php echo getTranslation('assets.operational'); ?></option>
                            <option value="maintenance"><?php echo getTranslation('assets.in_maintenance'); ?></option>
                            <option value="retired"><?php echo getTranslation('assets.retired'); ?></option>
                        </select>
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

<!-- Edit Asset Modal -->
<div class="modal fade" id="editAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('assets.edit_asset'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_asset.php" method="POST" class="needs-validation" data-ajax="true" data-reload-table="#assetsTable" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="asset_id" id="editAssetId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editAssetCode" class="form-label"><?php echo getTranslation('assets.asset_code'); ?> *</label>
                            <input type="text" class="form-control" id="editAssetCode" name="asset_code" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editAssetName" class="form-label"><?php echo getTranslation('assets.name'); ?> *</label>
                            <input type="text" class="form-control" id="editAssetName" name="name" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAssetDescription" class="form-label"><?php echo getTranslation('assets.description'); ?></label>
                        <textarea class="form-control" id="editAssetDescription" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editAssetCategory" class="form-label"><?php echo getTranslation('assets.category'); ?></label>
                            <select class="form-select" id="editAssetCategory" name="category_id">
                                <option value=""><?php echo getTranslation('assets.select_category'); ?></option>
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
                            <label for="editAssetLocation" class="form-label"><?php echo getTranslation('assets.location'); ?></label>
                            <input type="text" class="form-control" id="editAssetLocation" name="location">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editAssetPurchaseDate" class="form-label"><?php echo getTranslation('assets.purchase_date'); ?></label>
                            <input type="text" class="form-control datepicker" id="editAssetPurchaseDate" name="purchase_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editAssetWarranty" class="form-label"><?php echo getTranslation('assets.warranty'); ?></label>
                            <input type="text" class="form-control datepicker" id="editAssetWarranty" name="warranty_expiry">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAssetStatus" class="form-label"><?php echo getTranslation('assets.status'); ?></label>
                        <select class="form-select" id="editAssetStatus" name="status">
                            <option value="operational"><?php echo getTranslation('assets.operational'); ?></option>
                            <option value="maintenance"><?php echo getTranslation('assets.in_maintenance'); ?></option>
                            <option value="retired"><?php echo getTranslation('assets.retired'); ?></option>
                        </select>
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

<script src="../assets/js/assets.js"></script>
<?php require_once '../includes/footer.php'; ?> 
