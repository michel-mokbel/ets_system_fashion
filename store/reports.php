<?php
require_once '../includes/header.php';
if (!is_store_manager()) {
    redirect('../index.php');
}
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <h1 class="mb-3 mb-md-0">Store Reports</h1>
  </div>
  <div class="card mb-4 w-100">
    <div class="card-body">
      <ul class="nav nav-tabs" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#salesReport" type="button" role="tab">Sales</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventoryReport" type="button" role="tab">Inventory</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoicesReport" type="button" role="tab">Sales per Invoice</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expensesReport" type="button" role="tab">Expenses</button>
        </li>
      </ul>
      <div class="tab-content pt-3" id="reportTabsContent">
        <div class="tab-pane fade show active" id="salesReport" role="tabpanel">
          <!-- Sales Report Filters and Table -->
          <div id="salesReportFilters"></div>
          <div id="salesReportTable"></div>
        </div>
        <div class="tab-pane fade" id="inventoryReport" role="tabpanel">
          <!-- Inventory Report Filters and Table -->
          <div id="inventoryReportFilters"></div>
          <div id="inventoryReportTable"></div>
        </div>
        <div class="tab-pane fade" id="invoicesReport" role="tabpanel">
          <!-- Invoices Report Filters and Table -->
          <div id="invoicesReportFilters"></div>
          <div id="invoicesReportTable"></div>
        </div>
        <div class="tab-pane fade" id="expensesReport" role="tabpanel">
          <!-- Expenses Report Filters and Table -->
          <div id="expensesReportFilters"></div>
          <div id="expensesReportTable"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
<script src="../assets/js/store_reports.js"></script> 