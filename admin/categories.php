<?php
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Only admins and inventory managers can manage categories
if (!can_access_inventory()) {
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
    <h1 class="mb-3 mb-md-0"><?php echo getTranslation('categories.title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('categories.add_category'); ?>
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="col-md-6">
                <label for="name" class="form-label"><?php echo getTranslation('categories.category_name'); ?></label>
                <input type="text" class="form-control" id="name" name="name" placeholder="<?php echo getTranslation('categories.search_by_name'); ?>">
            </div>
            
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary me-2">
                    <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('inventory.apply_filters'); ?>
                </button>
                <button type="reset" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i> <?php echo getTranslation('common.clear'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Categories table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="categoriesTable" class="table table-striped table-hover mb-0" style="width:100%">
                <thead class="table-light">
                <tr>
                        <th style="width:40px"></th> <!-- Expand/Collapse icon column -->
                        <th><?php echo getTranslation('categories.name'); ?></th>
                        <th><?php echo getTranslation('categories.description'); ?></th>
                        <th><?php echo getTranslation('categories.subcategories'); ?></th>
                        <th><?php echo getTranslation('categories.items_count'); ?></th>
                        <th><?php echo getTranslation('categories.created_at'); ?></th>
                        <th width="120"><?php echo getTranslation('inventory.actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Table body will be populated by DataTables -->
                <tr id="subcategoryPlaceholder">
                    <td colspan="6" class="text-center py-3">
                        <i class="bi bi-list-task me-2"></i> <?php echo getTranslation('categories.no_subcategories'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('categories.add_category'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_category.php" method="POST" class="needs-validation" data-ajax="true" data-reload-table="#categoriesTable" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label"><?php echo getTranslation('categories.name'); ?> *</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label"><?php echo getTranslation('categories.description'); ?></label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
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

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('categories.edit_category'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_category.php" method="POST" class="needs-validation" data-ajax="true" data-reload-table="#categoriesTable" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" id="editCategoryId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label"><?php echo getTranslation('categories.name'); ?> *</label>
                        <input type="text" class="form-control" id="editCategoryName" name="name" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCategoryDescription" class="form-label"><?php echo getTranslation('categories.description'); ?></label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
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

<!-- View Subcategories Modal -->
<div class="modal fade" id="viewSubcategoriesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('categories.subcategories'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="subcategoriesContent">
                    <!-- Content will be loaded via AJAX -->
            </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/categories.js"></script>
<?php require_once '../includes/footer.php'; ?> 