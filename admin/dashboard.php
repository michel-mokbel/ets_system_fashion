<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();
require_once '../includes/header.php';
if (!is_admin()) {
    redirect('../index.php');
}
// Fetch stats (to be implemented with AJAX or PHP as needed)
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
    <h1 class="mb-3 mb-md-0">Admin Dashboard</h1>
  </div>
  
  <!-- Mobile-Optimized Stats Cards -->
  <div class="row mb-4 dashboard-stats">
    <div class="col-12 mb-3">
      <div class="card stats-card bg-primary text-white h-100 mobile-stats-card">
        <div class="card-body text-center">
          <div class="stats-icon mb-2">
            <i class="bi bi-box-seam display-4"></i>
          </div>
          <h5 class="card-title">Total Items</h5>
          <h2 id="statTotalItems" class="display-6 fw-bold">-</h2>
        </div>
      </div>
    </div>
    <div class="col-12 mb-3">
      <div class="card stats-card bg-warning text-dark h-100 mobile-stats-card">
        <div class="card-body text-center">
          <div class="stats-icon mb-2">
            <i class="bi bi-exclamation-triangle display-4"></i>
          </div>
          <h5 class="card-title">Low Stock</h5>
          <h2 id="statLowStock" class="display-6 fw-bold">-</h2>
        </div>
      </div>
    </div>
    <div class="col-12 mb-3">
      <div class="card stats-card bg-success text-white h-100 mobile-stats-card">
        <div class="card-body text-center">
          <div class="stats-icon mb-2">
            <i class="bi bi-currency-dollar display-4"></i>
          </div>
          <h5 class="card-title">Total Sales Today</h5>
          <h2 id="statTotalSales" class="display-6 fw-bold">-</h2>
        </div>
      </div>
    </div>
    <div class="col-12 mb-3">
      <div class="card stats-card bg-info text-white h-100 mobile-stats-card">
        <div class="card-body text-center">
          <div class="stats-icon mb-2">
            <i class="bi bi-truck display-4"></i>
          </div>
          <h5 class="card-title">Pending Containers</h5>
          <h2 id="statPendingContainers" class="display-6 fw-bold">-</h2>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Mobile-Optimized Dashboard Tables -->
  <div class="row dashboard-tables">
    <div class="col-12 col-xl-6 mb-4">
      <div class="card h-100 mobile-dashboard-card">
        <div class="card-header mobile-card-header">
          <h5 class="card-title mb-0">
            <i class="bi bi-graph-up me-2"></i>Recent Sales
          </h5>
        </div>
        <div class="card-body">
          <div class="table-responsive mobile-optimized">
            <table class="table table-striped table-hover" id="recentSalesTable">
              <thead>
                <tr>
                  <th>Invoice #</th>
                  <th>Date</th>
                  <th class="d-none d-md-table-cell">Store</th>
                  <th class="d-none d-lg-table-cell">Customer</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <!-- Recent sales will be loaded by JS -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-6 mb-4">
      <div class="card h-100 mobile-dashboard-card">
        <div class="card-header mobile-card-header">
          <h5 class="card-title mb-0">
            <i class="bi bi-exclamation-triangle me-2"></i>Low Stock Items
          </h5>
        </div>
        <div class="card-body">
          <div class="table-responsive mobile-optimized">
            <table class="table table-striped table-hover" id="lowStockTable">
              <thead>
                <tr>
                  <th>Item Code</th>
                  <th>Name</th>
                  <th class="d-none d-md-table-cell">Current Stock</th>
                  <th class="d-none d-lg-table-cell">Minimum Stock</th>
                </tr>
              </thead>
              <tbody>
                <!-- Low stock items will be loaded by JS -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
<script src="../assets/js/admin_dashboard.js"></script> 