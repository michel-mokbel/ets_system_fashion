<?php
/**
 * Subcategory Administration
 * --------------------------
 * Complements the category manager by allowing administrators and inventory
 * managers to define subcategory taxonomy. The page enforces authentication via
 * the header, seeds CSRF tokens for JavaScript requests, and presents filters
 * plus modals coordinated by `assets/js/subcategories.js`. CRUD actions are
 * served through `ajax/process_subcategory.php`, while listings pull from
 * `ajax/get_subcategories.php` and `ajax/get_subcategory.php`.
 */
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Only admins and inventory managers can manage subcategories
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
    <h1 class="mb-3 mb-md-0"><?php echo getTranslation('subcategories.title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubcategoryModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('subcategories.add_subcategory'); ?>
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="col-md-4">
                <label for="name" class="form-label"><?php echo getTranslation('subcategories.subcategory_name'); ?></label>
                <input type="text" class="form-control" id="name" name="name" placeholder="<?php echo getTranslation('subcategories.search_by_name'); ?>">
            </div>
            
            <div class="col-md-4">
                <label for="category_id" class="form-label"><?php echo getTranslation('subcategories.parent_category'); ?></label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
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
            
            <div class="col-md-4 d-flex align-items-end">
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

<!-- Subcategories table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="subcategoriesTable" class="table table-striped table-hover mb-0" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('subcategories.name'); ?></th>
                        <th><?php echo getTranslation('subcategories.description'); ?></th>
                        <th><?php echo getTranslation('subcategories.parent_category'); ?></th>
                        <th><?php echo getTranslation('subcategories.items_count'); ?></th>
                        <th><?php echo getTranslation('subcategories.created_at'); ?></th>
                        <th width="120"><?php echo getTranslation('inventory.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Table body will be populated by DataTables -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Subcategory Modal -->
<div class="modal fade" id="addSubcategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('subcategories.add_subcategory'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_subcategory.php" method="POST" class="needs-validation" data-ajax="true" data-reload-table="#subcategoriesTable" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subcategoryName" class="form-label"><?php echo getTranslation('subcategories.name'); ?> *</label>
                        <input type="text" class="form-control" id="subcategoryName" name="name" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="parentCategory" class="form-label"><?php echo getTranslation('subcategories.parent_category'); ?> *</label>
                        <select class="form-select" id="parentCategory" name="category_id" required>
                            <option value=""><?php echo getTranslation('subcategories.select_category'); ?></option>
                            <?php
                            $categories_result = $conn->query($categories_query);
                            if ($categories_result && $categories_result->num_rows > 0) {
                                while ($category = $categories_result->fetch_assoc()) {
                                    echo "<option value='" . $category['id'] . "'>" . htmlspecialchars($category['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subcategoryDescription" class="form-label"><?php echo getTranslation('subcategories.description'); ?></label>
                        <textarea class="form-control" id="subcategoryDescription" name="description" rows="3"></textarea>
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

<!-- Edit Subcategory Modal -->
<div class="modal fade" id="editSubcategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('subcategories.edit_subcategory'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_subcategory.php" method="POST" class="needs-validation" data-ajax="true" data-reload-table="#subcategoriesTable" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="subcategory_id" id="editSubcategoryId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editSubcategoryName" class="form-label"><?php echo getTranslation('subcategories.name'); ?> *</label>
                        <input type="text" class="form-control" id="editSubcategoryName" name="name" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editParentCategory" class="form-label"><?php echo getTranslation('subcategories.parent_category'); ?> *</label>
                        <select class="form-select" id="editParentCategory" name="category_id" required>
                            <option value=""><?php echo getTranslation('subcategories.select_category'); ?></option>
                            <?php
                            $categories_result = $conn->query($categories_query);
                            if ($categories_result && $categories_result->num_rows > 0) {
                                while ($category = $categories_result->fetch_assoc()) {
                                    echo "<option value='" . $category['id'] . "'>" . htmlspecialchars($category['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editSubcategoryDescription" class="form-label"><?php echo getTranslation('subcategories.description'); ?></label>
                        <textarea class="form-control" id="editSubcategoryDescription" name="description" rows="3"></textarea>
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

<script src="../assets/js/subcategories.js"></script>
<?php require_once '../includes/footer.php'; ?> 