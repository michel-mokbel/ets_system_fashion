<?php
require_once '../includes/header.php';
if (!is_store_manager()) {
    redirect('../index.php');
}
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <h1 class="mb-3 mb-md-0">Store Expenses</h1>
  </div>
  <div class="card mb-4 w-100">
    <div class="card-body">
      <form id="expenseFilterForm" class="row g-3 mb-0">
        <div class="col-lg-3 col-md-6 col-sm-12">
          <label class="form-label">From</label>
          <input type="date" class="form-control" name="from_date" id="filterFromDate">
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
          <label class="form-label">To</label>
          <input type="date" class="form-control" name="to_date" id="filterToDate">
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
          <label class="form-label">Category</label>
          <input type="text" class="form-control" name="category" id="filterCategory">
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
        <table class="table table-hover table-striped align-middle mb-0" id="storeExpensesTable">
          <thead class="table-light">
            <tr>
              <th>Expense #</th>
              <th>Date</th>
              <th>Category</th>
              <th>Description</th>
              <th>Amount</th>
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
<?php require_once '../includes/footer.php'; ?>
<script src="../assets/js/store_expenses.js"></script> 