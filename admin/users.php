<?php
/**
 * User Administration Portal
 * --------------------------
 * Restricts access to administrators responsible for onboarding and managing
 * system accounts. The page reuses the shared header for authentication,
 * renders filter controls, and delegates modal-driven CRUD to
 * `assets/js/admin_users.js`. Back-end data is provided by
 * `ajax/admin_users.php`, which handles listing, creation, updates, and
 * password resets.
 */
require_once '../includes/header.php';
if (!is_admin()) {
    redirect('../index.php');
}
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <h1 class="mb-3 mb-md-0">User Management</h1>
    <button class="btn btn-primary" id="addUserBtn"><i class="bi bi-plus-circle me-1"></i> Add User</button>
  </div>
  <div class="card mb-4 w-100">
    <div class="card-body">
      <form id="userFilterForm" class="row g-3 mb-0">
        <div class="col-lg-3 col-md-6 col-sm-12">
          <label class="form-label">Role</label>
          <select class="form-select" name="role" id="filterRole">
            <option value="">All</option>
            <option value="admin">Admin</option>
            <option value="inventory_manager">Inventory Manager</option>
            <option value="transfer_manager">Transfer Manager</option>
            <option value="store_manager">Store Manager</option>
            <option value="sales_person">Sales Person</option>
          </select>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
          <label class="form-label">Store</label>
          <select class="form-select" name="store_id" id="filterStoreId">
            <option value="">All Stores</option>
            <?php
            $stores_query = "SELECT id, name FROM stores ORDER BY name";
            $stores_result = $conn->query($stores_query);
            if ($stores_result && $stores_result->num_rows > 0) {
              while ($store = $stores_result->fetch_assoc()) {
                echo "<option value='" . $store['id'] . "'>" . htmlspecialchars($store['name']) . "</option>";
              }
            }
            ?>
          </select>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
          <label class="form-label">Status</label>
          <select class="form-select" name="status" id="filterStatus">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12 d-flex align-items-end">
          <button type="submit" class="btn btn-secondary w-100">
            <i class="bi bi-funnel me-1"></i> Apply Filters
          </button>
        </div>
      </form>
    </div>
  </div>
  <div class="card w-100">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0" id="usersTable">
          <thead class="table-light">
            <tr>
              <th>Username</th>
              <th>Full Name</th>
              <th>Role</th>
              <th>Store</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- Data will be loaded by JS -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalTitle">Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="userForm">
        <input type="hidden" name="id" id="userId">
        <div class="modal-body">
          <div class="mb-3">
            <label for="username" class="form-label">Username *</label>
            <input type="text" class="form-control" id="username" name="username" required>
          </div>
          <div class="mb-3">
            <label for="full_name" class="form-label">Full Name</label>
            <input type="text" class="form-control" id="full_name" name="full_name">
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email">
          </div>
          <div class="mb-3" id="passwordField">
            <label for="password" class="form-label">Password *</label>
            <input type="password" class="form-control" id="password" name="password" required>
            <small class="text-muted">Leave blank when editing to keep current password</small>
          </div>
          <div class="mb-3">
            <label for="role" class="form-label">Role *</label>
            <select class="form-select" id="role" name="role" required>
              <option value="admin">Admin</option>
              <option value="inventory_manager">Inventory Manager</option>
              <option value="transfer_manager">Transfer Manager</option>
              <option value="store_manager">Store Manager</option>
              <option value="sales_person">Sales Person</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="store_id" class="form-label">Store</label>
            <select class="form-select" id="store_id" name="store_id">
              <option value="">None</option>
              <?php
              $stores_query = "SELECT id, name FROM stores ORDER BY name";
              $stores_result = $conn->query($stores_query);
              if ($stores_result && $stores_result->num_rows > 0) {
                while ($store = $stores_result->fetch_assoc()) {
                  echo "<option value='" . $store['id'] . "'>" . htmlspecialchars($store['name']) . "</option>";
                }
              }
              ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="status" class="form-label">Status *</label>
            <select class="form-select" id="status" name="status" required>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="resetPasswordForm">
        <input type="hidden" name="id" id="resetUserId">
        <div class="modal-body">
          <div class="mb-3">
            <label for="new_password" class="form-label">New Password *</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
<script src="../assets/js/admin_users.js"></script> 