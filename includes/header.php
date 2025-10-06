<?php
require_once __DIR__ . '/session_config.php';
// Only start a session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/language.php';

// Set base URL for assets and links
$base_url = get_base_url();

// Check if user is logged in
if (!is_logged_in()) {
    redirect($base_url . 'index.php');
}

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Determine current page for menu highlighting
$current_page = basename($_SERVER['SCRIPT_FILENAME']);

// Get user role and store information
$user_role = $_SESSION['user_role'] ?? '';
$store_name = $_SESSION['store_name'] ?? '';
$user_name =$_SESSION['username']??'User';
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'en'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.9">
    <title><?php echo getTranslation('site.title'); ?></title>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icon-css@6.11.0/css/flag-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.4.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/style.css">
</head>

<body>
    <!-- Mobile menu toggle button (legacy - will be hidden) -->
    <button class="mobile-menu-toggle d-none" id="mobileSidebarToggle">
        <i class="bi bi-list"></i>
    </button>

    <div class="d-flex" id="wrapper">
        <!-- Mobile Backdrop -->
        <div class="mobile-backdrop" id="mobileBackdrop"></div>
        
        <!-- Universal Sidebar Toggle Button -->
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>

        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <!-- Sidebar Toggle (Inside Sidebar) -->
            <div class="sidebar-toggle-inside">
                <button class="sidebar-toggle-btn" id="sidebarToggleInside">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </div>
            
            <div class="sidebar-header">
                <h4 class="sidebar-title"><?php echo getTranslation('site.title'); ?></h4>
                <div class="sidebar-title-short">ETS</div>
            </div>
            <div class="sidebar-user">
                <div class="user-icon">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <small class="user-role"><?php echo ucfirst(str_replace('_', ' ', $user_role)); ?></small>
                    <?php if (!empty($store_name)): ?>
                        <small class="user-store"><?php echo htmlspecialchars($store_name); ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <ul class="sidebar-menu">
                <?php 
                // Determine which sections should be expanded based on current page
                $inventory_pages = ['store_inventory.php', 'store_items.php', 'boxes.php', 'box_comparison.php', 'transfers.php'];
                $procurement_pages = ['containers.php', 'purchase_orders.php', 'suppliers.php'];
                $sales_pages = ['invoices.php', 'expenses.php'];
                $catalog_pages = ['categories.php', 'subcategories.php'];
                $system_pages = ['stores.php', 'users.php'];
                ?>

                <?php if (is_admin() || is_view_only()): ?>
                    <!-- Dashboard - Standalone -->
                    <li>
                        <a href="<?php echo $base_url; ?>admin/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="bi bi-speedometer2"></i>
                            <span><?php echo getTranslation('menu.dashboard'); ?></span>
                        </a>
                    </li>

                    <!-- Inventory Management Group -->
                    <li class="menu-group">
                        <a href="#inventoryGroup" class="menu-group-toggle <?php echo in_array($current_page, $inventory_pages) ? 'expanded' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $inventory_pages) ? 'true' : 'false'; ?>">
                            <i class="bi bi-boxes"></i>
                            <span>Inventory Management</span>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </a>
                        <div class="collapse <?php echo in_array($current_page, $inventory_pages) ? 'show' : ''; ?>" id="inventoryGroup">
                            <ul class="submenu">
                                <!-- <li>
                                    <a href="<?php echo $base_url; ?>admin/inventory.php" class="<?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-box-seam"></i>
                                        <span><?php echo getTranslation('menu.inventory'); ?></span>
                                    </a>
                                </li> -->
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/boxes.php" class="<?php echo $current_page == 'boxes.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-box"></i>
                                        <span>Warehouse Boxes</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/box_comparison.php" class="<?php echo $current_page == 'box_comparison.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-box-seam"></i>
                                        <span>Box Comparison</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/store_items.php" class="<?php echo $current_page == 'store_items.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-diagram-3"></i>
                                        <span>Inventory</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/store_inventory.php" class="<?php echo $current_page == 'store_inventory.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-shop"></i>
                                        <span>Store Inventory</span>
                                    </a>
                                </li>
 

                                <li>
                                    <a href="<?php echo $base_url; ?>admin/transfers.php" class="<?php echo $current_page == 'transfers.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-arrow-left-right"></i>
                                        <span>Transfers</span>
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>
                    <?php if (is_admin() || is_inventory_manager()): ?>
                    <!-- Procurement Group -->
                    <li class="menu-group">
                        <a href="#procurementGroup" class="menu-group-toggle <?php echo in_array($current_page, $procurement_pages) ? 'expanded' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $procurement_pages) ? 'true' : 'false'; ?>">
                            <i class="bi bi-truck"></i>
                            <span>Procurement</span>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </a>
                        <div class="collapse <?php echo in_array($current_page, $procurement_pages) ? 'show' : ''; ?>" id="procurementGroup">
                            <ul class="submenu">
                                
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/containers.php" class="<?php echo $current_page == 'containers.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-box"></i>
                                        <span><?php echo getTranslation('menu.containers'); ?></span>
                                    </a>
                                </li>
                                
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/purchase_orders.php" class="<?php echo $current_page == 'purchase_orders.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-cart"></i>
                                        <span><?php echo getTranslation('menu.purchase_orders'); ?></span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/suppliers.php" class="<?php echo $current_page == 'suppliers.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-building"></i>
                                        <span><?php echo getTranslation('menu.suppliers'); ?></span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                    <?php if (is_admin()): ?>
                    <!-- Sales & Operations Group -->
                    <li class="menu-group">
                        <a href="#salesGroup" class="menu-group-toggle <?php echo in_array($current_page, $sales_pages) ? 'expanded' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $sales_pages) ? 'true' : 'false'; ?>">
                            <i class="bi bi-cash-stack"></i>
                            <span>Sales & Operations</span>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </a>
                        <div class="collapse <?php echo in_array($current_page, $sales_pages) ? 'show' : ''; ?>" id="salesGroup">
                            <ul class="submenu">
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/invoices.php" class="<?php echo $current_page == 'invoices.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-receipt"></i>
                                        <span>Invoices</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/expenses.php" class="<?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-credit-card"></i>
                                        <span>Expenses</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Catalog Management Group -->
                    <li class="menu-group">
                        <a href="#catalogGroup" class="menu-group-toggle <?php echo in_array($current_page, $catalog_pages) ? 'expanded' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $catalog_pages) ? 'true' : 'false'; ?>">
                            <i class="bi bi-tags"></i>
                            <span>Catalog Management</span>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </a>
                        <div class="collapse <?php echo in_array($current_page, $catalog_pages) ? 'show' : ''; ?>" id="catalogGroup">
                            <ul class="submenu">
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/categories.php" class="<?php echo $current_page == 'categories.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-tags"></i>
                                        <span><?php echo getTranslation('menu.categories'); ?></span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/subcategories.php" class="<?php echo $current_page == 'subcategories.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-tag"></i>
                                        <span><?php echo getTranslation('menu.subcategories'); ?></span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                   

                    <!-- Reports - Standalone -->
                    <li>
                        <a href="<?php echo $base_url; ?>admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span><?php echo getTranslation('menu.reports'); ?></span>
                        </a>
                    </li>

                    <!-- System Management Group -->
                    <?php if (is_admin()): ?>
                    <li class="menu-group">
                        <a href="#systemGroup" class="menu-group-toggle <?php echo in_array($current_page, $system_pages) ? 'expanded' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $system_pages) ? 'true' : 'false'; ?>">
                            <i class="bi bi-gear"></i>
                            <span>System Management</span>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </a>
                        <div class="collapse <?php echo in_array($current_page, $system_pages) ? 'show' : ''; ?>" id="systemGroup">
                            <ul class="submenu">
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/stores.php" class="<?php echo $current_page == 'stores.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-shop"></i>
                                        <span>Stores</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-people"></i>
                                        <span>Users</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                <?php elseif (is_inventory_manager()): ?>
                    <!-- Inventory Management for Inventory Manager -->
                    <li class="menu-group">
                        <a href="#inventoryGroup" class="menu-group-toggle <?php echo in_array($current_page, $inventory_pages) ? 'expanded' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, $inventory_pages) ? 'true' : 'false'; ?>">
                            <i class="bi bi-boxes"></i>
                            <span>Inventory Management</span>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </a>
                        <div class="collapse <?php echo in_array($current_page, $inventory_pages) ? 'show' : ''; ?>" id="inventoryGroup">
                            <ul class="submenu">
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/boxes.php" class="<?php echo $current_page == 'boxes.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-box"></i>
                                        <span>Warehouse Boxes</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/store_items.php" class="<?php echo $current_page == 'store_items.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-diagram-3"></i>
                                        <span>Inventory</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/store_inventory.php" class="<?php echo $current_page == 'store_inventory.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-shop"></i>
                                        <span>Store Inventory</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>admin/transfers.php" class="<?php echo $current_page == 'transfers.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-arrow-left-right"></i>
                                        <span>Transfers</span>
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </li>

                <?php elseif (is_transfer_manager()): ?>
                    <!-- Transfer Management for Transfer Manager -->
                    <li>
                        <a href="<?php echo $base_url; ?>admin/transfers.php" class="<?php echo $current_page == 'transfers.php' ? 'active' : ''; ?>">
                            <i class="bi bi-arrow-left-right"></i>
                            <span>Transfers</span>
                        </a>
                    </li>

                <?php elseif (is_store_manager()): ?>
                    <!-- Store Manager Dashboard -->


                    <!-- Point of Sale - Standalone -->
                    <li>
                        <a href="<?php echo $base_url; ?>store/pos.php" class="<?php echo $current_page == 'pos.php' ? 'active' : ''; ?>">
                            <i class="bi bi-cash-coin"></i>
                            <span><?php echo getTranslation('menu.pos'); ?></span>
                        </a>
                    </li>


                    <!-- Sales & Operations Group -->
                    <li class="menu-group">
                        <a href="#storeSalesGroup" class="menu-group-toggle <?php echo in_array($current_page, ['invoices.php', 'returns.php', 'expenses.php']) ? 'expanded' : ''; ?>" data-bs-toggle="collapse" aria-expanded="<?php echo in_array($current_page, ['invoices.php', 'returns.php', 'expenses.php']) ? 'true' : 'false'; ?>">
                            <i class="bi bi-cash-stack"></i>
                            <span>Sales & Operations</span>
                            <i class="bi bi-chevron-down toggle-icon"></i>
                        </a>
                        <div class="collapse <?php echo in_array($current_page, ['invoices.php', 'returns.php', 'expenses.php']) ? 'show' : ''; ?>" id="storeSalesGroup">
                            <ul class="submenu">
                                <li>
                                    <a href="<?php echo $base_url; ?>store/invoices.php" class="<?php echo $current_page == 'invoices.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-receipt"></i>
                                        <span><?php echo getTranslation('menu.invoices'); ?></span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>store/returns.php" class="<?php echo $current_page == 'returns.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-arrow-return-left"></i>
                                        <span><?php echo getTranslation('menu.returns'); ?></span>
                                    </a>
                                </li>
                                <li>
                                    <a href="<?php echo $base_url; ?>store/expenses.php" class="<?php echo $current_page == 'expenses.php' ? 'active' : ''; ?>">
                                        <i class="bi bi-credit-card"></i>
                                        <span><?php echo getTranslation('menu.expenses'); ?></span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <!-- Reports - Standalone -->
                    <li>
                        <a href="<?php echo $base_url; ?>store/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <span><?php echo getTranslation('menu.reports'); ?></span>
                        </a>
                    </li>
                <?php elseif (is_sales_person()): ?>
                    <!-- Sales Person Menu Items -->

                    <li>
                        <a href="<?php echo $base_url; ?>store/pos.php" class="<?php echo $current_page == 'pos.php' ? 'active' : ''; ?>">
                            <i class="bi bi-shop"></i>
                            <span><?php echo getTranslation('menu.pos'); ?></span>
                        </a>
                    </li>

                <?php endif; ?>

                <!-- Logout - Available to all roles -->
                <li>
                    <?php if (in_array($user_role, ['store_manager', 'sales_person'])): ?>
                    <a href="#" id="enforcedLogoutBtn" onclick="handleEnforcedLogout(); return false;">
                        <i class="bi bi-box-arrow-right"></i>
                        <span><?php echo getTranslation('menu.logout'); ?></span>
                    </a>
                    <?php else: ?>
                    <a href="<?php echo $base_url; ?>logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span><?php echo getTranslation('menu.logout'); ?></span>
                    </a>
                    <?php endif; ?>
                </li>
            </ul>
            <!-- Language switcher at bottom -->
            <div class="language-switcher-container mt-auto p-3">
                <?php echo language_switcher(); ?>
            </div>
        </div>

        <!-- Page Content -->
        <div class="main-content">
            <!-- Toast notifications container -->
            <div class="toast-container position-fixed top-0 end-0 p-3">
                <?php if (isset($_SESSION['toast'])): ?>
                    <div class="toast align-items-center text-white bg-<?php echo $_SESSION['toast']['type']; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <strong><?php echo $_SESSION['toast']['title']; ?></strong><br>
                                <?php echo $_SESSION['toast']['message']; ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                    <?php unset($_SESSION['toast']); ?>
                <?php endif; ?>
            </div>

            <!-- Logout Summary Modal (for store managers and sales persons) -->
            <?php if (in_array($user_role, ['store_manager', 'sales_person'])): ?>
            <div class="modal fade" id="logoutSummaryModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-file-earmark-text me-2"></i>Shift Summary Report
                            </h5>
                        </div>
                        <div class="modal-body">
                            <!-- Loading State -->
                            <div id="logoutSummaryLoading" class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Generating summary...</span>
                                </div>
                                <p class="mt-2">Closing shift and generating summary...</p>
                            </div>

                            <!-- Error State -->
                            <div id="logoutSummaryError" style="display: none;">
                                <div class="alert alert-danger">
                                    <h6 class="alert-heading">Error Closing Shift</h6>
                                    <p id="logoutSummaryErrorMessage">An error occurred while closing your shift.</p>
                                    <hr>
                                    <p class="mb-0">You can still logout, but your shift may not be properly recorded.</p>
                                </div>
                            </div>

                            <!-- Summary Content -->
                            <div id="logoutSummaryContent" style="display: none;">
                                <!-- Report Header -->
                                <div class="text-center mb-4 d-print-block">
                                    <h3>Shift Summary Report</h3>
                                    <p class="mb-1"><strong id="summaryStoreName">Store Name</strong></p>
                                    <p class="mb-1">Employee: <span id="summaryUserName">User Name</span></p>
                                    <p class="mb-3">Generated: <span id="summaryGeneratedAt"></span></p>
                                    <hr>
                                </div>

                                <!-- Shift Information -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5><i class="bi bi-clock me-2"></i>Shift Information</h5>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card bg-primary text-white">
                                                    <div class="card-body text-center">
                                                        <h6 id="summaryStartTime">--:--</h6>
                                                        <small>Start Time</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-secondary text-white">
                                                    <div class="card-body text-center">
                                                        <h6 id="summaryEndTime">--:--</h6>
                                                        <small>End Time</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-info text-white">
                                                    <div class="card-body text-center">
                                                        <h6 id="summaryDuration">--</h6>
                                                        <small>Duration</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sales by Payment Method -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5><i class="bi bi-cash-coin me-2"></i>Sales by Payment Method</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm" id="logoutSalesByPaymentTable">
                                                <thead>
                                                    <tr>
                                                        <th>Payment Method</th>
                                                        <th>Count</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Sales by payment method data -->
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-success fw-bold">
                                                        <td colspan="2">TOTAL SALES:</td>
                                                        <td id="logoutTotalSalesAmount">CFA 0.00</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Expenses Breakdown -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5><i class="bi bi-receipt me-2"></i>Expenses Breakdown</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm" id="logoutExpensesTable">
                                                <thead>
                                                    <tr>
                                                        <th>Category</th>
                                                        <th>Description</th>
                                                        <th>Amount</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Expenses data -->
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-warning fw-bold">
                                                        <td colspan="3">TOTAL EXPENSES:</td>
                                                        <td id="logoutTotalExpensesAmount">CFA 0.00</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Final Summary -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h5><i class="bi bi-calculator me-2"></i>Final Summary</h5>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card bg-success text-white">
                                                    <div class="card-body text-center">
                                                        <h4 id="logoutTotalSalesSummary">CFA 0.00</h4>
                                                        <small>Total Sales</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-warning text-dark">
                                                    <div class="card-body text-center">
                                                        <h4 id="logoutTotalExpensesSummary">CFA 0.00</h4>
                                                        <small>Total Expenses</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card bg-primary text-white">
                                                    <div class="card-body text-center">
                                                        <h4 id="logoutNetTotalSummary">CFA 0.00</h4>
                                                        <small>Net Total</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Thank You Message -->
                                <div class="text-center p-3 bg-light rounded">
                                    <h5 class="text-success mb-2">
                                        <i class="bi bi-check-circle me-2"></i>Shift Completed Successfully!
                                    </h5>
                                    <p class="mb-0">Thank you for your hard work today. Your shift has been properly recorded.</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="printLogoutSummary" style="display: none;">
                                <i class="bi bi-printer me-1"></i> Print Summary
                            </button>
                            <button type="button" class="btn btn-primary" id="completeLogoutBtn" style="display: none;">
                                <i class="bi bi-box-arrow-right me-1"></i> Complete Logout
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="forceLogoutBtn" style="display: none;">
                                <i class="bi bi-exclamation-triangle me-1"></i> Logout Anyway
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Page content -->
            <div class="container-fluid mt-4">