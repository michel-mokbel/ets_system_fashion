<?php
/**
 * Store-Level Invoice Browser
 * ---------------------------
 * Lets store managers audit the invoices generated within their location,
 * including statuses, payments, and return activity. The page reuses the shared
 * header to enforce authentication, renders date/status filters, and leverages
 * `assets/js/store_invoices.js` to load data from `ajax/store_invoices.php` and
 * fetch detailed invoice breakdowns for modal display.
 */
require_once '../includes/header.php';
if (!is_store_manager()) {
    redirect('../index.php');
}
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <h1 class="mb-3 mb-md-0">Store Invoices</h1>
  </div>
  <div class="card mb-4 w-100">
    <div class="card-body">
      <form id="invoiceFilterForm" class="row g-3 mb-0">
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
            <option value="paid">Paid</option>
            <option value="pending">Pending</option>
            <option value="partial_refund">Partial Refund</option>
            <option value="refunded">Refunded</option>
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
        <table class="table table-hover table-striped align-middle mb-0" id="storeInvoicesTable">
          <thead class="table-light">
            <tr>
              <th>Invoice #</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Total</th>
              <th>Payment Status</th>
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
<script src="../assets/js/store_invoices.js"></script> 