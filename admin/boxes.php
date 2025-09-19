<?php
/**
 * Warehouse Box Management
 * ------------------------
 * Enables inventory administrators to review and maintain warehouse box
 * records, including status, container linkage, and item capacity. After the
 * shared header enforces authentication, the page renders filters, statistics,
 * and modal-driven CRUD forms orchestrated by `assets/js/boxes.js`. All data
 * interactions are delegated to endpoints such as `ajax/get_boxes.php` and
 * `ajax/process_boxes.php`, while a CSRF token is embedded for the JavaScript
 * client to include in each request.
 */
ob_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Only admins and inventory managers can manage boxes
if (!can_access_inventory()) {
    redirect('../index.php');
}
?>

<body>
    <!-- Hidden CSRF token for AJAX requests -->
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div class="container-fluid py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
            <h1 class="mb-3 mb-md-0">
                <i class="bi bi-box me-2"></i>Warehouse Boxes Management
            </h1>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBoxModal">
                    <i class="bi bi-plus-circle me-1"></i> Add New Box
                </button>
                <button class="btn btn-info" id="refreshBoxes">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Card -->
        <div class="row mb-4" style="display: none;">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Boxes</h6>
                                <h3 class="mb-0" id="totalBoxes">-</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-boxes fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4 w-100">
            <div class="card-body">
                <form id="filterForm" class="row g-3 mb-0">
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" id="searchInput" placeholder="Search by box number, name, or type...">
                            <button type="button" class="btn btn-outline-secondary" id="clearSearch" title="Clear search">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" id="typeFilter">
                            <option value="">All Types</option>
                            <!-- Types will be loaded dynamically -->
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Boxes Table -->
        <div class="card w-100">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" id="boxesTable">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th>Box Number</th>
                                <th>Box Name</th>
                                <th>Type</th>
                                <th>Container</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Created</th>
                                <th width="200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTable will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Box Modal -->
        <div class="modal fade" id="addBoxModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Box</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="../ajax/process_boxes.php" method="POST" class="needs-validation" data-reload-table="#boxesTable" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="boxNumber" class="form-label">Box Number *</label>
                                <input type="text" class="form-control" id="boxNumber" name="box_number" required>
                                <div class="invalid-feedback">Box number is required</div>
                                <small class="text-muted">Unique identifier for the box (e.g., BOX-001)</small>
                            </div>
                            <div class="mb-3">
                                <label for="boxName" class="form-label">Box Name *</label>
                                <input type="text" class="form-control" id="boxName" name="box_name" required>
                                <div class="invalid-feedback">Box name is required</div>
                                <small class="text-muted">Descriptive name for the box</small>
                            </div>
                            <div class="mb-3">
                                <label for="boxType" class="form-label">Box Type</label>
                                <input type="text" class="form-control" id="boxType" name="box_type" placeholder="e.g., Electronics, Clothing, Mixed">
                                <small class="text-muted">Optional: Category or type of items in this box</small>
                            </div>

                            <div class="mb-3">
                                <label for="boxContainer" class="form-label">Container</label>
                                <select class="form-select" id="boxContainer" name="container_id">
                                    <option value="">Select Container (Optional)</option>
                                    <?php
                                    $containers_query = "SELECT id, container_number FROM containers ORDER BY container_number DESC";
                                    $containers_result = $conn->query($containers_query);
                                    if ($containers_result && $containers_result->num_rows > 0) {
                                        while ($container = $containers_result->fetch_assoc()) {
                                            echo "<option value='" . $container['id'] . "'>" . htmlspecialchars($container['container_number']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Optional: Associate this box with a container</small>
                            </div>

                            <div class="mb-3">
                                <label for="boxQuantity" class="form-label">Box Quantity</label>
                                <input type="number" class="form-control" id="boxQuantity" name="quantity" min="0" value="0">
                                <small class="text-muted">Number of boxes</small>
                            </div>

                            <div class="mb-3">
                                <label for="boxUnitCost" class="form-label">Unit Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">CFA</span>
                                    <input type="number" class="form-control" id="boxUnitCost" name="unit_cost" min="0" step="0.01" value="0.00">
                                </div>
                                <small class="text-muted">Cost per individual box</small>
                            </div>

                            <div class="mb-3">
                                <label for="boxNotes" class="form-label">Notes</label>
                                <textarea class="form-control" id="boxNotes" name="notes" rows="3" placeholder="Additional notes about this box..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Box</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Box Modal -->
        <div class="modal fade" id="editBoxModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Box</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="../ajax/process_boxes.php" method="POST" class="needs-validation" data-reload-table="#boxesTable" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="box_id" id="editBoxId">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="editBoxNumber" class="form-label">Box Number *</label>
                                <input type="text" class="form-control" id="editBoxNumber" name="box_number" required>
                                <div class="invalid-feedback">Box number is required</div>
                            </div>
                            <div class="mb-3">
                                <label for="editBoxName" class="form-label">Box Name *</label>
                                <input type="text" class="form-control" id="editBoxName" name="box_name" required>
                                <div class="invalid-feedback">Box name is required</div>
                            </div>
                            <div class="mb-3">
                                <label for="editBoxType" class="form-label">Box Type</label>
                                <input type="text" class="form-control" id="editBoxType" name="box_type">
                            </div>

                            <div class="mb-3">
                                <label for="editBoxContainer" class="form-label">Container</label>
                                <select class="form-select" id="editBoxContainer" name="container_id">
                                    <option value="">Select Container (Optional)</option>
                                    <?php
                                    $containers_result = $conn->query($containers_query);
                                    if ($containers_result && $containers_result->num_rows > 0) {
                                        while ($container = $containers_result->fetch_assoc()) {
                                            echo "<option value='" . $container['id'] . "'>" . htmlspecialchars($container['container_number']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Optional: Associate this box with a container</small>
                            </div>

                            <div class="mb-3">
                                <label for="editBoxQuantity" class="form-label">Box Quantity</label>
                                <input type="number" class="form-control" id="editBoxQuantity" name="quantity" min="0">
                            </div>

                            <div class="mb-3">
                                <label for="editBoxUnitCost" class="form-label">Unit Cost</label>
                                <div class="input-group">
                                    <span class="input-group-text">CFA</span>
                                    <input type="number" class="form-control" id="editBoxUnitCost" name="unit_cost" min="0" step="0.01">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="editBoxNotes" class="form-label">Notes</label>
                                <textarea class="form-control" id="editBoxNotes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Box</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Box Details Modal -->
        <div class="modal fade" id="boxDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Box Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h4 id="detailBoxName" class="mb-3"></h4>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Box Number:</div>
                                    <div class="col-md-8" id="detailBoxNumber"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Type:</div>
                                    <div class="col-md-8" id="detailBoxType"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Quantity:</div>
                                    <div class="col-md-8" id="detailBoxQuantity"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Unit Cost:</div>
                                    <div class="col-md-8" id="detailBoxUnitCost"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Created:</div>
                                    <div class="col-md-8" id="detailBoxCreated"></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-md-4 text-muted">Updated:</div>
                                    <div class="col-md-8" id="detailBoxUpdated"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h5>Notes</h5>
                            <p id="detailBoxNotes" class="text-muted"></p>
                        </div>
                        <div class="mb-3">
                            <h5>Box Information</h5>
                            <p class="text-muted">This box is ready for use in warehouse transfers.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="editBoxFromDetails">
                            <i class="bi bi-pencil me-1"></i> Edit Box
                        </button>
                    </div>
                </div>
            </div>
        </div>



    <script src="../assets/js/boxes.js"></script>
    <?php require_once '../includes/footer.php'; ?>
</body> 