<?php
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Check if specific asset or maintenance schedule is requested
$asset_id = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
$create_mode = isset($_GET['create']) && $_GET['create'] == 1;

$asset_name = '';
$schedule_details = null;

// If asset ID is provided, get asset details
if ($asset_id > 0) {
    $asset_query = "SELECT name FROM assets WHERE id = ?";
    $asset_stmt = $conn->prepare($asset_query);
    $asset_stmt->bind_param('i', $asset_id);
    $asset_stmt->execute();
    $asset_result = $asset_stmt->get_result();
    
    if ($asset_result && $asset_result->num_rows > 0) {
        $asset_name = $asset_result->fetch_assoc()['name'];
    } else {
        // Asset not found, reset asset_id
        $asset_id = 0;
    }
}

// If schedule ID is provided, get schedule details
if ($schedule_id > 0) {
    $schedule_query = "SELECT ms.*, a.name as asset_name 
                      FROM maintenance_schedules ms 
                      JOIN assets a ON ms.asset_id = a.id 
                      WHERE ms.id = ?";
    $schedule_stmt = $conn->prepare($schedule_query);
    $schedule_stmt->bind_param('i', $schedule_id);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    
    if ($schedule_result && $schedule_result->num_rows > 0) {
        $schedule_details = $schedule_result->fetch_assoc();
        $asset_id = $schedule_details['asset_id'];
        $asset_name = $schedule_details['asset_name'];
    } else {
        // Schedule not found, reset schedule_id
        $schedule_id = 0;
    }
}

// Generate a new work order number if in create mode
$new_work_order_number = '';
if ($create_mode) {
    $year = date('Y');
    $month = date('m');
    
    // Get the latest work order number for this year/month
    $latest_query = "SELECT work_order_number FROM work_orders 
                    WHERE work_order_number LIKE 'WO-$year-$month-%' 
                    ORDER BY CAST(SUBSTRING_INDEX(work_order_number, '-', -1) AS UNSIGNED) DESC 
                    LIMIT 1";
    $latest_result = $conn->query($latest_query);
    
    if ($latest_result && $latest_result->num_rows > 0) {
        $latest_number = $latest_result->fetch_assoc()['work_order_number'];
        $number_part = (int)explode('-', $latest_number)[3];
        $next_number = $number_part + 1;
    } else {
        $next_number = 1;
    }
    
    $new_work_order_number = "WO-$year-$month-" . sprintf('%03d', $next_number);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo getTranslation('work_orders.title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWorkOrderModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('work_orders.add_work_order'); ?>
    </button>
</div>

<?php if (!empty($asset_name)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <?php echo sprintf(getTranslation('work_orders.viewing_for_asset'), htmlspecialchars($asset_name)); ?>
    <a href="work_orders.php" class="ms-2"><?php echo getTranslation('work_orders.view_all'); ?></a>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('work_orders.asset'); ?></label>
                <select class="form-select" name="asset_id">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <?php
                    $assets_query = "SELECT id, name FROM assets ORDER BY name";
                    $assets_result = $conn->query($assets_query);
                    
                    if ($assets_result && $assets_result->num_rows > 0) {
                        while ($asset = $assets_result->fetch_assoc()) {
                            $selected = ($asset_id == $asset['id']) ? 'selected' : '';
                            echo "<option value='" . $asset['id'] . "' $selected>" . htmlspecialchars($asset['name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('work_orders.maintenance_type'); ?></label>
                <select class="form-select" name="maintenance_type">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="preventive" <?php echo (isset($_GET['maintenance_type']) && $_GET['maintenance_type'] == 'preventive') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('work_orders.preventive'); ?>
                    </option>
                    <option value="corrective" <?php echo (isset($_GET['maintenance_type']) && $_GET['maintenance_type'] == 'corrective') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('work_orders.corrective'); ?>
                    </option>
                    <option value="emergency" <?php echo (isset($_GET['maintenance_type']) && $_GET['maintenance_type'] == 'emergency') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('work_orders.emergency'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('work_orders.status'); ?></label>
                <select class="form-select" name="status">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('work_orders.pending'); ?>
                    </option>
                    <option value="in_progress" <?php echo (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('work_orders.in_progress'); ?>
                    </option>
                    <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('work_orders.completed'); ?>
                    </option>
                    <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status'] == 'cancelled') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('work_orders.cancelled'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('work_orders.apply_filters'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Work Orders Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="workOrdersTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('work_orders.work_order_number'); ?></th>
                        <th><?php echo getTranslation('work_orders.asset'); ?></th>
                        <th><?php echo getTranslation('work_orders.maintenance_type'); ?></th>
                        <th><?php echo getTranslation('work_orders.priority'); ?></th>
                        <th><?php echo getTranslation('work_orders.scheduled_date'); ?></th>
                        <th><?php echo getTranslation('work_orders.status'); ?></th>
                        <th width="120"><?php echo getTranslation('work_orders.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Work Order Modal -->
<div class="modal fade" id="addWorkOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('work_orders.add_work_order'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_work_order.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                <?php if ($schedule_id > 0): ?>
                <input type="hidden" name="maintenance_schedule_id" value="<?php echo $schedule_id; ?>">
                <?php endif; ?>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="workOrderNumber" class="form-label"><?php echo getTranslation('work_orders.work_order_number'); ?> *</label>
                            <input type="text" class="form-control" id="workOrderNumber" name="work_order_number" value="<?php echo $new_work_order_number; ?>" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="assetId" class="form-label"><?php echo getTranslation('work_orders.asset'); ?> *</label>
                            <select class="form-select" id="assetId" name="asset_id" required>
                                <option value=""><?php echo getTranslation('work_orders.select_asset'); ?></option>
                                <?php
                                $assets_result = $conn->query($assets_query);
                                if ($assets_result && $assets_result->num_rows > 0) {
                                    while ($asset = $assets_result->fetch_assoc()) {
                                        $selected = ($asset_id == $asset['id']) ? 'selected' : '';
                                        echo "<option value='" . $asset['id'] . "' $selected>" . htmlspecialchars($asset['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="maintenanceType" class="form-label"><?php echo getTranslation('work_orders.maintenance_type'); ?></label>
                            <select class="form-select" id="maintenanceType" name="maintenance_type">
                                <option value="preventive" <?php echo ($schedule_id > 0) ? 'selected' : ''; ?>><?php echo getTranslation('work_orders.preventive'); ?></option>
                                <option value="corrective"><?php echo getTranslation('work_orders.corrective'); ?></option>
                                <option value="emergency"><?php echo getTranslation('work_orders.emergency'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label"><?php echo getTranslation('work_orders.priority'); ?></label>
                            <select class="form-select" id="priority" name="priority">
                                <option value="low"><?php echo getTranslation('work_orders.low'); ?></option>
                                <option value="medium" selected><?php echo getTranslation('work_orders.medium'); ?></option>
                                <option value="high"><?php echo getTranslation('work_orders.high'); ?></option>
                                <option value="critical"><?php echo getTranslation('work_orders.critical'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label"><?php echo getTranslation('work_orders.description'); ?> *</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="scheduledDate" class="form-label"><?php echo getTranslation('work_orders.scheduled_date'); ?> *</label>
                            <input type="text" class="form-control datepicker" id="scheduledDate" name="scheduled_date" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label"><?php echo getTranslation('work_orders.status'); ?></label>
                            <select class="form-select" id="status" name="status">
                                <option value="pending" selected><?php echo getTranslation('work_orders.pending'); ?></option>
                                <option value="in_progress"><?php echo getTranslation('work_orders.in_progress'); ?></option>
                                <option value="completed"><?php echo getTranslation('work_orders.completed'); ?></option>
                                <option value="cancelled"><?php echo getTranslation('work_orders.cancelled'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?php echo getTranslation('work_orders.notes'); ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo getTranslation('common.cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo getTranslation('common.save'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Work Order Modal -->
<div class="modal fade" id="editWorkOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('work_orders.edit_work_order'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_work_order.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="work_order_id" id="editWorkOrderId">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editWorkOrderNumber" class="form-label"><?php echo getTranslation('work_orders.work_order_number'); ?> *</label>
                            <input type="text" class="form-control" id="editWorkOrderNumber" name="work_order_number" required readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editAssetId" class="form-label"><?php echo getTranslation('work_orders.asset'); ?> *</label>
                            <select class="form-select" id="editAssetId" name="asset_id" required>
                                <option value=""><?php echo getTranslation('work_orders.select_asset'); ?></option>
                                <?php
                                $assets_result = $conn->query($assets_query);
                                if ($assets_result && $assets_result->num_rows > 0) {
                                    while ($asset = $assets_result->fetch_assoc()) {
                                        echo "<option value='" . $asset['id'] . "'>" . htmlspecialchars($asset['name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editMaintenanceType" class="form-label"><?php echo getTranslation('work_orders.maintenance_type'); ?></label>
                            <select class="form-select" id="editMaintenanceType" name="maintenance_type">
                                <option value="preventive"><?php echo getTranslation('work_orders.preventive'); ?></option>
                                <option value="corrective"><?php echo getTranslation('work_orders.corrective'); ?></option>
                                <option value="emergency"><?php echo getTranslation('work_orders.emergency'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editPriority" class="form-label"><?php echo getTranslation('work_orders.priority'); ?></label>
                            <select class="form-select" id="editPriority" name="priority">
                                <option value="low"><?php echo getTranslation('work_orders.low'); ?></option>
                                <option value="medium"><?php echo getTranslation('work_orders.medium'); ?></option>
                                <option value="high"><?php echo getTranslation('work_orders.high'); ?></option>
                                <option value="critical"><?php echo getTranslation('work_orders.critical'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editDescription" class="form-label"><?php echo getTranslation('work_orders.description'); ?> *</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editScheduledDate" class="form-label"><?php echo getTranslation('work_orders.scheduled_date'); ?> *</label>
                            <input type="text" class="form-control datepicker" id="editScheduledDate" name="scheduled_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editCompletedDate" class="form-label"><?php echo getTranslation('work_orders.completed_date'); ?></label>
                            <input type="text" class="form-control datepicker" id="editCompletedDate" name="completed_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editStatus" class="form-label"><?php echo getTranslation('work_orders.status'); ?></label>
                        <select class="form-select" id="editStatus" name="status">
                            <option value="pending"><?php echo getTranslation('work_orders.pending'); ?></option>
                            <option value="in_progress"><?php echo getTranslation('work_orders.in_progress'); ?></option>
                            <option value="completed"><?php echo getTranslation('work_orders.completed'); ?></option>
                            <option value="cancelled"><?php echo getTranslation('work_orders.cancelled'); ?></option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editNotes" class="form-label"><?php echo getTranslation('work_orders.notes'); ?></label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo getTranslation('common.cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo getTranslation('common.save'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Work Order Modal -->
<div class="modal fade" id="viewWorkOrderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('work_orders.view_work_order'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><?php echo getTranslation('work_orders.work_order_number'); ?>:</strong> <span id="viewWorkOrderNumber"></span></p>
                        <p><strong><?php echo getTranslation('work_orders.asset'); ?>:</strong> <span id="viewAssetName"></span></p>
                        <p><strong><?php echo getTranslation('work_orders.maintenance_type'); ?>:</strong> <span id="viewMaintenanceType"></span></p>
                        <p><strong><?php echo getTranslation('work_orders.priority'); ?>:</strong> <span id="viewPriority"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><?php echo getTranslation('work_orders.scheduled_date'); ?>:</strong> <span id="viewScheduledDate"></span></p>
                        <p><strong><?php echo getTranslation('work_orders.completed_date'); ?>:</strong> <span id="viewCompletedDate"></span></p>
                        <p><strong><?php echo getTranslation('work_orders.status'); ?>:</strong> <span id="viewStatus"></span></p>
                        <p><strong><?php echo getTranslation('work_orders.created_at'); ?>:</strong> <span id="viewCreatedAt"></span></p>
                    </div>
                </div>
                <div class="mt-3">
                    <p><strong><?php echo getTranslation('work_orders.description'); ?>:</strong></p>
                    <div class="p-3 bg-light rounded" id="viewDescription"></div>
                </div>
                <div class="mt-3">
                    <p><strong><?php echo getTranslation('work_orders.notes'); ?>:</strong></p>
                    <div class="p-3 bg-light rounded" id="viewNotes"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <?php echo getTranslation('common.close'); ?>
                </button>
                <button type="button" class="btn btn-primary" id="editWorkOrderBtn">
                    <i class="bi bi-pencil me-1"></i> <?php echo getTranslation('common.edit'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/work_orders.js"></script>
<?php require_once '../includes/footer.php'; ?> 
