<?php
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

if (!is_admin()) {
    redirect('../index.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Invoices</h1>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-lg-2 col-md-6 col-sm-12">
                <label class="form-label">Invoice Number</label>
                <input type="text" class="form-control" name="invoice_number" placeholder="e.g. INV-001">
                <small class="text-muted">Press Enter or click Apply Filters to search</small>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-12">
                <label class="form-label">Customer Name</label>
                <input type="text" class="form-control" name="customer_name" placeholder="Customer Name">
                <small class="text-muted">Press Enter or click Apply Filters to search</small>
            </div>
            <div class="col-lg-2 col-md-6 col-sm-12">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-12">
                <label class="form-label">Date Range</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="start_date" id="startDate">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control" name="end_date" id="endDate">
                </div>

                <small class="text-muted">Select start and end dates or use quick filters</small>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-12 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> Apply Filters
                </button>
            </div>
        </form>
        <div class="row mt-2">
            <div class="col-12">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFilters">
                    <i class="bi bi-x-circle me-1"></i> Clear All Filters
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Active Filters Summary -->
<div class="card mb-3" id="activeFiltersSummary" style="display: none;">
    <div class="card-body py-2">
        <div class="d-flex align-items-center">
            <i class="bi bi-funnel-fill text-primary me-2"></i>
            <span class="text-muted me-2">Active Filters:</span>
            <div id="activeFiltersList"></div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="invoicesTable">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Invoice Details Modal -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="invoiceDetailsContent">
                <!-- Details will be loaded by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token -->
<input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

<?php require_once '../includes/footer.php'; ?>
<script src="../assets/js/invoices.js"></script> 