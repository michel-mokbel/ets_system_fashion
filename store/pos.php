<?php
require_once '../includes/header.php';
if (!is_store_manager() && !is_sales_person()) {
    redirect('../index.php');
}

// Force French language for POS page
$_SESSION['lang'] = 'fr';
// Set session store_id from GET if present, or ensure it's set from user's default store
if (isset($_GET['store_id']) && is_numeric($_GET['store_id'])) {
    $_SESSION['store_id'] = (int)$_GET['store_id'];
} elseif (!isset($_SESSION['store_id'])) {
    // If no store_id in session, try to get it from user's default store
    $user_id = $_SESSION['user_id'] ?? null;
    if ($user_id) {
        $stmt = $conn->prepare("SELECT store_id FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['store_id'] = $row['store_id'];
        }
    }
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

<div class="container-fluid py-4">
<?php if (is_store_manager()): ?>
<div class="d-flex gap-2 justify-content-end mb-3 mobile-pos-actions" style="margin-right: 55px;">
  <button class="btn btn-danger mobile-action-btn" id="openReturnModal">
    <i class="bi bi-arrow-counterclockwise me-1"></i> 
    <span class="d-none d-md-inline"><?php echo getTranslation('pos.process_return'); ?></span>
    <span class="d-md-none"><?php echo getTranslation('pos.return'); ?></span>
  </button>
  <button class="btn btn-secondary mobile-action-btn" id="openExpensesModal">
    <i class="bi bi-receipt me-1"></i> 
    <span class="d-none d-md-inline"><?php echo getTranslation('pos.expenses'); ?></span>
    <span class="d-md-none"><?php echo getTranslation('pos.expense'); ?></span>
  </button>
  <button class="btn btn-info mobile-action-btn" id="openShiftReportModal">
    <i class="bi bi-file-earmark-text me-1"></i> 
    <span class="d-none d-md-inline"><?php echo getTranslation('pos.shift_report'); ?></span>
    <span class="d-md-none"><?php echo getTranslation('pos.report'); ?></span>
  </button>
</div>
<?php elseif (is_sales_person()): ?>
<div class="d-flex gap-2 justify-content-end mb-3 mobile-pos-actions" style="margin-right: 55px;">
  <button class="btn btn-danger mobile-action-btn" id="openReturnModalSales">
    <i class="bi bi-arrow-counterclockwise me-1"></i> 
    <span class="d-none d-md-inline"><?php echo getTranslation('pos.process_return'); ?></span>
    <span class="d-md-none"><?php echo getTranslation('pos.return'); ?></span>
    <small class="d-none d-md-block" style="font-size: 0.7em; opacity: 0.8;"><?php echo getTranslation('pos.manager_authorization_required'); ?></small>
  </button>
  <button class="btn btn-secondary mobile-action-btn" id="openExpensesModalSales">
    <i class="bi bi-receipt me-1"></i> 
    <span class="d-none d-md-inline"><?php echo getTranslation('pos.expenses'); ?></span>
    <span class="d-md-none"><?php echo getTranslation('pos.expense'); ?></span>
  </button>
  <button class="btn btn-info mobile-action-btn" id="openShiftReportModal">
    <i class="bi bi-file-earmark-text me-1"></i> 
    <span class="d-none d-md-inline"><?php echo getTranslation('pos.shift_report'); ?></span>
    <span class="d-md-none"><?php echo getTranslation('pos.report'); ?></span>
  </button>
</div>
<?php endif; ?>

<div class="row g-4 justify-content-center pos-main-container">
    <!-- POS Section -->
  <div class="col-12 col-lg-6 col-xl-5 pos-panel" style="width: 50%;">
    <div class="card shadow rounded h-100 mobile-pos-card">
        <div class="card-body px-4 py-4">
        <h2 class="mb-4 mobile-pos-title">
          <i class="bi bi-cash-stack me-2"></i><?php echo getTranslation('pos.title'); ?>
        </h2>

        <!-- Mobile Barcode Section -->
        <div class="mobile-barcode-section mb-4">
          <!-- Barcode/Search Row -->
          <div class="row g-2 mb-3">
            <div class="col-12 col-md-4">
              <div class="barcode-field-container mobile-barcode-container">
                <input type="text" class="form-control form-control-lg barcode-ready mobile-barcode-input" id="barcodeInput" placeholder="<?php echo getTranslation('pos.scan_barcode'); ?>">
              </div>
            </div>
            <div class="col-12 col-md-4">
              <input type="text" class="form-control form-control-lg" id="itemSearchInput" placeholder="<?php echo getTranslation('common.search'); ?> <?php echo getTranslation('pos.item'); ?>">
            </div>
            <div class="col-6 col-md-2 d-grid">
              <button class="btn btn-success btn-lg w-100" id="addBarcodeBtn">
                <i class="bi bi-plus-circle me-1"></i>
              </button>
            </div>
            <div class="col-6 col-md-2 d-grid">
              <button class="btn btn-primary btn-lg w-100" id="addCustomItemBtn" title="<?php echo getTranslation('pos.add_custom_item'); ?>">
                <i class="bi bi-pencil-square me-1"></i>
                <span class="d-none d-lg-inline"><?php echo getTranslation('pos.custom'); ?></span>
              </button>
            </div>
          </div>
          <!-- Cart Table -->
          <div class="table-responsive mb-4 mobile-optimized">
            <table class="table table-hover align-middle bg-light rounded" id="cartTable">
              <thead class="table-light">
                <tr>
                  <th><?php echo getTranslation('pos.item_name'); ?></th>
                  <th><?php echo getTranslation('inventory.item_code'); ?></th>
                  <th><?php echo getTranslation('pos.unit_price'); ?></th>
                  <th><?php echo getTranslation('pos.quantity'); ?></th>
                  <th><?php echo getTranslation('pos.line_total'); ?></th>
                  <th><?php echo getTranslation('common.remove'); ?></th>
                </tr>
              </thead>
              <tbody>
                <!-- Cart items will be added here -->
              </tbody>
            </table>
          </div>
          <!-- Totals & Customer Info -->
          <div class="row g-3 mb-4 mobile-form-container">
            <div class="col-6 col-md-4 mb-2">
              <label class="form-label"><?php echo getTranslation('pos.subtotal'); ?></label>
              <input type="text" class="form-control" id="subtotal" readonly>
            </div>
            <!-- Tax field hidden for now
            <div class="col-6 col-md-4 mb-2">
              <label class="form-label"><?php echo getTranslation('pos.tax'); ?></label>
              <input type="text" class="form-control" id="tax" value="0" readonly>
            </div>
            -->
            <div class="col-6 col-md-4 mb-2">
              <label class="form-label"><?php echo getTranslation('pos.discount'); ?></label>
              <input type="text" class="form-control" id="discount" value="0" readonly>
            </div>
            <div class="col-6 col-md-4 mb-2">
              <label class="form-label fw-bold"><?php echo getTranslation('pos.total'); ?></label>
              <input type="text" class="form-control fw-bold" id="total" readonly>
            </div>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 mb-2">
              <label class="form-label"><?php echo getTranslation('pos.customer_name'); ?></label>
              <input type="text" class="form-control" id="customerName" placeholder="<?php echo getTranslation('pos.customer_name'); ?>">
            </div>
            <div class="col-12 col-md-6 mb-2">
              <label class="form-label"><?php echo getTranslation('pos.customer_phone'); ?></label>
              <input type="text" class="form-control" id="customerPhone" placeholder="<?php echo getTranslation('pos.customer_phone'); ?>">
            </div>
            <div class="col-12 col-md-6 mb-2">
              <label class="form-label"><?php echo getTranslation('pos.payment_method'); ?></label>
              <select class="form-select" id="paymentMethod">
                <option value="cash"><?php echo getTranslation('pos.cash'); ?></option>
                <option value="card"><?php echo getTranslation('pos.card'); ?></option>
                <option value="mobile"><?php echo getTranslation('pos.mobile'); ?></option>
                <option value="cash_mobile">Cash + Mobile</option>
                <option value="credit"><?php echo getTranslation('pos.credit'); ?></option>
              </select>
            </div>
          </div>
          
          <!-- Payment Amount Fields -->
          <div class="mb-4" id="paymentAmountFields" style="display: none;">
            <h6><i class="bi bi-currency-dollar me-2"></i>Payment Details</h6>
            <div class="row g-2">
              <div class="col-3">
                <label class="form-label small">Amount Paid (CFA)</label>
                <input type="number" class="form-control form-control-sm" id="amountPaid" min="0" step="0.01" placeholder="0.00">
              </div>
              <div class="col-3">
                <label class="form-label small">Change Due (CFA)</label>
                <input type="text" class="form-control form-control-sm" id="changeDue" readonly style="background-color: #f8f9fa;">
              </div>
              <div class="col-3" id="cashAmountField" style="display: none;">
                <label class="form-label small">Cash Amount (CFA)</label>
                <input type="number" class="form-control form-control-sm" id="cashAmount" min="0" step="0.01" placeholder="0.00">
              </div>
              <div class="col-3" id="mobileAmountField" style="display: none;">
                <label class="form-label small">Mobile Amount (CFA)</label>
                <input type="number" class="form-control form-control-sm" id="mobileAmount" min="0" step="0.01" placeholder="0.00">
              </div>
            </div>
          </div>
          
          <!-- Additional Customer Info Row -->
          <div class="row g-3 mb-4">
          </div>
          <!-- Checkout Button -->
          <div class="sticky-bottom bg-white py-3">
            <button class="btn btn-success btn-lg w-100 shadow" id="checkoutBtn">
              <i class="bi bi-cash-coin me-1"></i> <?php echo getTranslation('pos.process_sale'); ?>
            </button>
          </div>
          <div id="posMessage" class="mt-3"></div>
        </div>
        </div>
      </div>
    </div>
    <!-- Store Inventory Section -->
    <div class="col-12 col-lg-6 col-xl-5">
      <div class="card shadow rounded h-100">
        <div class="card-body px-4 py-4 d-flex flex-column">
          <h3 class="mb-4"><i class="bi bi-box-seam me-2"></i><?php echo getTranslation('pos.store_inventory'); ?></h3>
          <div class="table-responsive mobile-optimized flex-grow-1" style="max-height: calc(100vh - 200px); overflow-y: auto;">
            <table class="table table-hover align-middle bg-light rounded" id="inventoryTable" style="position: relative;">
              <thead class="table-light" style="position: sticky; top: 0; background: white; z-index: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <tr>
                  <th><?php echo getTranslation('pos.item_name'); ?></th>
                  <th><?php echo getTranslation('inventory.item_code'); ?></th>
                  <th><?php echo getTranslation('pos.unit_price'); ?></th>
                  <th><?php echo getTranslation('pos.stock'); ?></th>
                  <th><?php echo getTranslation('pos.qty'); ?></th>
                  <th><?php echo getTranslation('common.add'); ?></th>
                </tr>
              </thead>
              <tbody>
                <!-- Inventory items will be loaded here by JS -->
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo getTranslation('pos.print_receipt'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="receiptContent">
        <!-- Receipt will be rendered here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo getTranslation('common.close'); ?></button>
        <button type="button" class="btn btn-primary" id="printReceiptBtn">
          <i class="bi bi-printer me-1"></i> <?php echo getTranslation('pos.print_receipt'); ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Custom Item Modal -->
<div class="modal fade" id="customItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-cart-plus me-2 text-primary"></i><?php echo getTranslation('pos.add_custom_item'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="customItemForm">
          <div class="mb-3">
            <label for="customItemPrice" class="form-label"><?php echo getTranslation('pos.unit_price_cfa'); ?> <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="bi bi-currency-dollar"></i>
              </span>
              <span class="input-group-text">CFA</span>
              <input type="number" class="form-control" id="customItemPrice" placeholder="0.00" min="0" step="0.01" required autofocus>
            </div>
          </div>
          <div class="mb-3">
            <label for="customItemQuantity" class="form-label"><?php echo getTranslation('pos.quantity'); ?> <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="bi bi-123"></i>
              </span>
              <input type="number" class="form-control" id="customItemQuantity" min="1" value="1" required>
            </div>
          </div>
          <div class="mt-3">
            <small class="text-muted">
              <i class="bi bi-info-circle me-1"></i>
              <?php echo getTranslation('pos.item_name_auto_generated'); ?>
            </small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo getTranslation('pos.cancel'); ?></button>
        <button type="button" class="btn btn-primary" id="addCustomItemToCart">
          <i class="bi bi-cart-plus me-1"></i> <?php echo getTranslation('pos.add_to_cart'); ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Discount Modal -->
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-percent me-2 text-info"></i><?php echo getTranslation('pos.add_discount'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6><?php echo getTranslation('pos.item_details'); ?></h6>
                    <p class="mb-1"><?php echo getTranslation('pos.name'); ?>: <span id="discountItemName"></span></p>
                    <p class="mb-1"><?php echo getTranslation('pos.code'); ?>: <span id="discountItemCode"></span></p>
                    <p class="mb-1"><?php echo getTranslation('pos.unit_price'); ?>: <span id="discountOriginalPrice"></span></p>
                    <p class="mb-1"><?php echo getTranslation('pos.current_total'); ?>: <span id="discountCurrentTotal"></span></p>
                </div>
                
                <div class="mb-3">
                    <h6><?php echo getTranslation('pos.select_discount'); ?></h6>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button class="btn btn-outline-primary discount-option" data-discount="10">10%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="20">20%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="30">30%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="40">40%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="50">50%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="60">60%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="70">70%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="80">80%</button>
                        <button class="btn btn-outline-primary discount-option" data-discount="90">90%</button>
                        <button class="btn btn-outline-danger discount-option" data-discount="100">100%</button>
                    </div>
                    
                    <div class="mb-3">
                        <label for="customDiscountInput" class="form-label"><?php echo getTranslation('pos.custom_discount'); ?></label>
                        <input type="number" class="form-control" id="customDiscountInput" min="0" max="100" step="0.1" placeholder="<?php echo getTranslation('pos.enter_custom_discount'); ?>">
                    </div>
                </div>
                
                <div id="discountPreview" class="border-top pt-3">
                    <!-- Preview will be shown here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="removeDiscountBtn"><?php echo getTranslation('pos.remove_discount'); ?></button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo getTranslation('pos.cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="applyDiscountBtn"><?php echo getTranslation('pos.apply_discount'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Price Override Modal -->
<div class="modal fade" id="priceOverrideModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-pencil-square me-2 text-warning"></i><?php echo getTranslation('pos.price_override'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="priceOverrideForm">
          <!-- Item Info -->
          <div class="alert alert-info mb-3">
            <h6 class="alert-heading mb-2">
              <i class="bi bi-info-circle me-1"></i>Item Information
            </h6>
            <div class="row">
              <div class="col-sm-6">
                <strong>Item:</strong> <span id="priceOverrideItemName"></span>
              </div>
              <div class="col-sm-6">
                <strong>Code:</strong> <span id="priceOverrideItemCode"></span>
              </div>
              <div class="col-sm-6">
                <strong>Original Price:</strong> <span id="priceOverrideOriginalPrice"></span>
              </div>
              <div class="col-sm-6">
                <strong>Current Price:</strong> <span id="priceOverrideCurrentPrice"></span>
              </div>
            </div>
          </div>

          <!-- New Price Input -->
          <div class="mb-3">
            <label for="newPriceInput" class="form-label">New Price (CFA) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">CFA</span>
              <input type="number" class="form-control" id="newPriceInput" min="0" step="0.01" required autofocus>
            </div>
          </div>

          <!-- Manager Password -->
          <div class="mb-3">
            <label for="managerPasswordInput" class="form-label">Manager Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="bi bi-lock"></i>
              </span>
              <input type="password" class="form-control" id="managerPasswordInput" placeholder="Required for price override" required>
            </div>
          </div>

          <!-- Warning Message -->
          <div class="alert alert-warning">
            <div class="d-flex align-items-center">
              <i class="bi bi-exclamation-triangle me-2"></i>
              <small>Price changes require manager authorization and will be logged for audit.</small>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-outline-primary" id="resetPriceBtn">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to Original
        </button>
        <button type="button" class="btn btn-warning" id="overridePriceBtn">
          <i class="bi bi-check-circle me-1"></i> Override Price
        </button>
      </div>
    </div>
  </div>
</div>

<?php if (is_store_manager() || is_sales_person()): ?>
<!-- Manager Password Verification Modal (for sales persons) -->
<div class="modal fade" id="managerPasswordModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-shield-lock me-2"></i><?php echo getTranslation('pos.manager_authorization'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info mb-3">
          <i class="bi bi-info-circle me-2"></i>
          <strong>Authorization Required:</strong> Processing returns requires store manager authorization. Please enter the store manager's password to continue.
        </div>
        <div class="mb-3">
          <label for="managerPassword" class="form-label">Store Manager Password</label>
          <input type="password" class="form-control" id="managerPassword" placeholder="Enter store manager password">
          <div class="invalid-feedback" id="passwordError"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="verifyManagerPassword">
          <i class="bi bi-check-circle me-1"></i> Verify & Continue
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Process Return</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="returnInvoiceNumber" class="form-label">Invoice Number</label>
          <div class="input-group">
            <input type="text" class="form-control" id="returnInvoiceNumber" placeholder="Enter invoice number">
            <button class="btn btn-outline-secondary" type="button" id="searchInvoiceBtn">Search</button>
          </div>
        </div>
        <div id="returnInvoiceDetails" style="display:none;">
          <h6>Invoice Items</h6>
          <div class="table-responsive mb-3">
            <table class="table table-bordered align-middle" id="returnItemsTable">
              <thead class="table-light">
                <tr>
                  <th>Item</th>
                  <th>Code</th>
                  <th>Sold Qty</th>
                  <th>Return Qty</th>
                  <th>Unit Price</th>
                  <th>Condition Notes</th>
                </tr>
              </thead>
              <tbody>
                <!-- Rows will be filled by JS -->
              </tbody>
            </table>
          </div>
          <div class="mb-3">
            <label for="returnReason" class="form-label">Return Reason</label>
            <textarea class="form-control" id="returnReason" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label for="returnNotes" class="form-label">Notes</label>
            <input type="text" class="form-control" id="returnNotes">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="submitReturnBtn" style="display:none;">Submit Return</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if (is_store_manager() || is_sales_person()): ?>
<!-- Expenses Modal -->
<div class="modal fade" id="expensesModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Store Expenses</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 d-flex justify-content-end">
          <button class="btn btn-primary" id="addExpenseBtn"><i class="bi bi-plus-circle me-1"></i> Add Expense</button>
        </div>
        <div class="table-responsive mb-3">
          <table class="table table-bordered align-middle" id="expensesTable">
            <thead class="table-light">
              <tr>
                <th>Date</th>
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
              <!-- Rows will be filled by JS -->
            </tbody>
          </table>
        </div>
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

<!-- Shift Report Modal -->
<div class="modal fade" id="shiftReportModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-file-earmark-text me-2"></i>Shift Report
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Session Information -->
        <div class="alert alert-info mb-4">
          <h6 class="alert-heading mb-2">
            <i class="bi bi-clock me-1"></i>Current Session Information
          </h6>
          <div class="row">
            <div class="col-md-4">
              <strong>Login Time:</strong><br>
              <span id="sessionLoginTime">Loading...</span>
            </div>
            <div class="col-md-4">
              <strong>Current Time:</strong><br>
              <span id="sessionCurrentTime">Loading...</span>
            </div>
            <div class="col-md-4">
              <strong>Session Duration:</strong><br>
              <span id="sessionDuration">Loading...</span>
            </div>
          </div>
        </div>
        
        <!-- Generate Report Button -->
        <div class="text-center mb-4">
          <button class="btn btn-primary btn-lg me-3" id="generateShiftReport">
            <i class="bi bi-graph-up me-2"></i> Generate Shift Report
          </button>
          <button class="btn btn-success btn-lg me-3" id="printShiftReport" style="display: none;">
            <i class="bi bi-printer me-2"></i> Print Report
          </button>
          <button class="btn btn-danger btn-lg" id="endShiftBtn" style="display: none;">
            <i class="bi bi-box-arrow-right me-2"></i> End Shift & Logout
          </button>
        </div>
        
        <!-- Report Content -->
        <div id="shiftReportContent" style="display: none;">
          <!-- Report Header -->
          <div class="text-center mb-4 d-print-block">
            <h3 id="reportTitle">Shift Report</h3>
            <p class="mb-1"><strong id="reportStoreName">Store Name</strong></p>
            <p class="mb-1">Session Started: <span id="reportLoginTime"></span></p>
            <p class="mb-1">Report Generated: <span id="reportGeneratedAt"></span></p>
            <p class="mb-1">Session Duration: <span id="reportSessionDuration"></span></p>
            <p class="mb-3">By: <span id="reportGeneratedBy"></span></p>
            <hr>
          </div>

          <!-- Sales by Payment Method -->
          <div class="row mb-4">
            <div class="col-12">
              <h5><i class="bi bi-cash-coin me-2"></i>Sales by Payment Method</h5>
              <div class="table-responsive">
                <table class="table table-sm" id="salesByPaymentTable">
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
                      <td id="totalSalesAmount">CFA 0.00</td>
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
                <table class="table table-sm" id="expensesTable">
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
                      <td id="totalExpensesAmount">CFA 0.00</td>
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
                      <h4 id="totalSalesSummary">CFA 0.00</h4>
                      <small>Total Sales</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                      <h4 id="totalExpensesSummary">CFA 0.00</h4>
                      <small>Total Expenses</small>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                      <h4 id="netTotalSummary">CFA 0.00</h4>
                      <small>Net Total</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
        
        <div id="shiftReportLoading" style="display: none;">
          <div class="text-center">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Generating shift report...</p>
          </div>
        </div>
        
        <div id="shiftReportError" style="display: none;">
          <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <span id="shiftReportErrorMessage">Error generating report</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<script>
// JavaScript translations
const translations = {
    'out_of_stock': '<?php echo getTranslation("pos.out_of_stock"); ?>',
    'cannot_add_more_items': '<?php echo getTranslation("pos.cannot_add_more_items"); ?>',
    'available_stock': '<?php echo getTranslation("pos.available_stock"); ?>',
    'already_in_cart': '<?php echo getTranslation("pos.already_in_cart"); ?>',
    'item_added': '<?php echo getTranslation("pos.item_added"); ?>',
    'added_to_cart': '<?php echo getTranslation("pos.added_to_cart"); ?>',
    'item_not_found': '<?php echo getTranslation("pos.item_not_found"); ?>',
    'no_item_found_barcode': '<?php echo getTranslation("pos.no_item_found_barcode"); ?>',
    'scan_error': '<?php echo getTranslation("pos.scan_error"); ?>',
    'error_scanning_barcode': '<?php echo getTranslation("pos.error_scanning_barcode"); ?>',
    'stock_warning': '<?php echo getTranslation("pos.stock_warning"); ?>',
    'items_exceed_stock': '<?php echo getTranslation("pos.items_exceed_stock"); ?>',
    'stock_limit_exceeded': '<?php echo getTranslation("pos.stock_limit_exceeded"); ?>',
    'maximum_available_quantity': '<?php echo getTranslation("pos.maximum_available_quantity"); ?>',
    'quantity_adjusted': '<?php echo getTranslation("pos.quantity_adjusted"); ?>',
    'insufficient_stock': '<?php echo getTranslation("pos.insufficient_stock"); ?>',
    'cannot_add_qty_items': '<?php echo getTranslation("pos.cannot_add_qty_items"); ?>',
    'invalid_discount': '<?php echo getTranslation("pos.invalid_discount"); ?>',
    'select_discount_range': '<?php echo getTranslation("pos.select_discount_range"); ?>',
    'discount_applied': '<?php echo getTranslation("pos.discount_applied"); ?>',
    'discount_applied_to': '<?php echo getTranslation("pos.discount_applied_to"); ?>',
    'discount_removed': '<?php echo getTranslation("pos.discount_removed"); ?>',
    'discount_removed_from': '<?php echo getTranslation("pos.discount_removed_from"); ?>',
    'please_enter_manager_password': '<?php echo getTranslation("pos.please_enter_manager_password"); ?>',
    'verifying': '<?php echo getTranslation("pos.verifying"); ?>',
    'authorization_granted': '<?php echo getTranslation("pos.authorization_granted"); ?>',
    'invalid_manager_password': '<?php echo getTranslation("pos.invalid_manager_password"); ?>',
    'error_verifying_password': '<?php echo getTranslation("pos.error_verifying_password"); ?>',
    'not_found': '<?php echo getTranslation("pos.not_found"); ?>',
    'error': '<?php echo getTranslation("common.error"); ?>',
    'add_expense': '<?php echo getTranslation("pos.add_expense"); ?>',
    'edit_expense': '<?php echo getTranslation("expenses.edit_expense"); ?>',
    'delete_expense_confirm': '<?php echo getTranslation("pos.delete_expense_confirm"); ?>',
    'will_be_shown_in_report': '<?php echo getTranslation("pos.will_be_shown_in_report"); ?>',
    'will_be_calculated_in_report': '<?php echo getTranslation("pos.will_be_calculated_in_report"); ?>',
    'failed_to_generate_report': '<?php echo getTranslation("pos.failed_to_generate_report"); ?>',
    'network_error_report': '<?php echo getTranslation("pos.network_error_report"); ?>',
    'store': '<?php echo getTranslation("pos.store"); ?>',
    'magasin': '<?php echo getTranslation("pos.magasin"); ?>',
    'edit_price_manager_required': '<?php echo getTranslation("pos.edit_price_manager_required"); ?>',
    'add_discount': '<?php echo getTranslation("pos.add_discount"); ?>'
};

// Translation function for JavaScript
function t(key) {
    return translations[key] || key;
}
</script>
<?php require_once '../includes/footer.php'; ?> 