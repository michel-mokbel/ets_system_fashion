<?php
require_once '../includes/header.php';
if (!is_admin()) {
    redirect('../index.php');
}
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <h1 class="mb-3 mb-md-0">Expense Tracking</h1>
  </div>
  <div class="card mb-4 w-100">
    <div class="card-body">
      <form id="expenseFilterForm" class="row g-3 mb-0">
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
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
          <label class="form-label">From</label>
          <input type="date" class="form-control" name="from_date" id="filterFromDate">
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
          <label class="form-label">To</label>
          <input type="date" class="form-control" name="to_date" id="filterToDate">
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
      <div class="mb-3 d-flex justify-content-end">
        <button class="btn btn-primary" id="addExpenseBtn"><i class="bi bi-plus-circle me-1"></i> Add New Expense</button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0" id="adminExpensesTable">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Store</th>
              <th>Category</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Receipt</th>
              <th>Notes</th>
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
<!-- Add/Edit Expense Modal -->
<div class="modal fade" id="addEditExpenseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="expenseModalTitle">Add Expense</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="expenseForm" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="expense_id" id="expenseId">
          <div class="mb-3">
            <label class="form-label">Category</label>
            <input type="text" class="form-control" name="category" id="expenseCategory" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" id="expenseDescription" rows="2" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Amount</label>
            <input type="number" class="form-control" name="amount" id="expenseAmount" min="0" step="0.01" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Expense Date</label>
            <input type="date" class="form-control" name="expense_date" id="expenseDate" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Receipt Image</label>
            <input type="file" class="form-control" name="receipt_image" id="expenseReceipt">
            <div id="expenseReceiptPreview" class="mt-2"></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Notes</label>
            <input type="text" class="form-control" name="notes" id="expenseNotes">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
<script src="../assets/js/admin_expenses.js"></script> 