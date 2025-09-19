<?php
/**
 * Preventive Maintenance Scheduler
 * --------------------------------
 * Lets administrators coordinate recurring maintenance tasks across assets.
 * The page validates authentication, optionally hydrates context for a specific
 * asset, and surfaces forms for defining recurrence, technician assignments, and
 * reminders. `assets/js/maintenance.js` powers the client experience, pulling
 * schedule data from `ajax/get_maintenance.php`, fetching asset metadata, and
 * submitting CRUD operations to `ajax/process_maintenance.php`.
 */
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Check if specific asset is requested
$asset_id = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
$asset_name = '';

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
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo getTranslation('maintenance.title'); ?></h1>
    <div class="d-flex gap-2">
        <button id="viewHistoryBtn" class="btn btn-secondary">
            <i class="bi bi-clock-history me-1"></i> <?php echo getTranslation('maintenance.view_history'); ?>
        </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('maintenance.add_schedule'); ?>
    </button>
    </div>
</div>

<?php if (!empty($asset_name)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <?php echo sprintf(getTranslation('maintenance.viewing_for_asset'), htmlspecialchars($asset_name)); ?>
    <a href="maintenance.php" class="ms-2"><?php echo getTranslation('maintenance.view_all'); ?></a>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('maintenance.asset'); ?></label>
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
                <label class="form-label"><?php echo getTranslation('maintenance.type'); ?></label>
                <select class="form-select" name="schedule_type">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="daily" <?php echo (isset($_GET['schedule_type']) && $_GET['schedule_type'] == 'daily') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.daily'); ?>
                    </option>
                    <option value="weekly" <?php echo (isset($_GET['schedule_type']) && $_GET['schedule_type'] == 'weekly') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.weekly'); ?>
                    </option>
                    <option value="monthly" <?php echo (isset($_GET['schedule_type']) && $_GET['schedule_type'] == 'monthly') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.monthly'); ?>
                    </option>
                    <option value="quarterly" <?php echo (isset($_GET['schedule_type']) && $_GET['schedule_type'] == 'quarterly') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.quarterly'); ?>
                    </option>
                    <option value="yearly" <?php echo (isset($_GET['schedule_type']) && $_GET['schedule_type'] == 'yearly') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.yearly'); ?>
                    </option>
                    <option value="custom" <?php echo (isset($_GET['schedule_type']) && $_GET['schedule_type'] == 'custom') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.custom'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('maintenance.status'); ?></label>
                <select class="form-select" name="status">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('common.active'); ?>
                    </option>
                    <option value="paused" <?php echo (isset($_GET['status']) && $_GET['status'] == 'paused') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.paused'); ?>
                    </option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> <?php echo getTranslation('maintenance.apply_filters'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Maintenance Schedules Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="maintenanceTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('maintenance.asset'); ?></th>
                        <th><?php echo getTranslation('maintenance.type'); ?></th>
                        <th><?php echo getTranslation('maintenance.frequency'); ?></th>
                        <th><?php echo getTranslation('maintenance.last_maintenance'); ?></th>
                        <th><?php echo getTranslation('maintenance.next_maintenance'); ?></th>
                        <th><?php echo getTranslation('maintenance.technician'); ?></th>
                        <th><?php echo getTranslation('maintenance.status'); ?></th>
                        <th width="120"><?php echo getTranslation('maintenance.actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DataTable will populate this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Maintenance Schedule Modal -->
<div class="modal fade" id="addMaintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('maintenance.add_schedule'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_maintenance.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="assetId" class="form-label"><?php echo getTranslation('maintenance.asset'); ?> *</label>
                        <select class="form-select" id="assetId" name="asset_id" required>
                            <option value=""><?php echo getTranslation('maintenance.select_asset'); ?></option>
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
                    
                    <div class="mb-3">
                        <label for="scheduleType" class="form-label"><?php echo getTranslation('maintenance.type'); ?> *</label>
                        <select class="form-select" id="scheduleType" name="schedule_type" required>
                            <option value="daily"><?php echo getTranslation('maintenance.daily'); ?></option>
                            <option value="weekly"><?php echo getTranslation('maintenance.weekly'); ?></option>
                            <option value="monthly"><?php echo getTranslation('maintenance.monthly'); ?></option>
                            <option value="quarterly"><?php echo getTranslation('maintenance.quarterly'); ?></option>
                            <option value="yearly"><?php echo getTranslation('maintenance.yearly'); ?></option>
                            <option value="custom"><?php echo getTranslation('maintenance.custom'); ?></option>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div id="customFrequencyGroup" class="row" style="display: none;">
                        <div class="col-md-6 mb-3">
                            <label for="frequencyValue" class="form-label"><?php echo getTranslation('maintenance.frequency_value'); ?></label>
                            <input type="number" class="form-control" id="frequencyValue" name="frequency_value" min="1" value="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="frequencyUnit" class="form-label"><?php echo getTranslation('maintenance.frequency_unit'); ?></label>
                            <select class="form-select" id="frequencyUnit" name="frequency_unit">
                                <option value="days"><?php echo getTranslation('maintenance.days'); ?></option>
                                <option value="weeks"><?php echo getTranslation('maintenance.weeks'); ?></option>
                                <option value="months"><?php echo getTranslation('maintenance.months'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="lastMaintenance" class="form-label"><?php echo getTranslation('maintenance.last_maintenance'); ?></label>
                            <input type="text" class="form-control datepicker" id="lastMaintenance" name="last_maintenance">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nextMaintenance" class="form-label"><?php echo getTranslation('maintenance.next_maintenance'); ?> *</label>
                            <input type="text" class="form-control datepicker" id="nextMaintenance" name="next_maintenance" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="technicianId" class="form-label"><?php echo getTranslation('maintenance.technician'); ?></label>
                        <input type="text" class="form-control" id="technicianId" name="assigned_technician" placeholder="<?php echo getTranslation('maintenance.technician'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="maintenanceStatus" class="form-label"><?php echo getTranslation('maintenance.status'); ?></label>
                        <select class="form-select" id="maintenanceStatus" name="status">
                            <option value="active"><?php echo getTranslation('common.active'); ?></option>
                            <option value="paused"><?php echo getTranslation('maintenance.paused'); ?></option>
                        </select>
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

<!-- Edit Maintenance Schedule Modal -->
<div class="modal fade" id="editMaintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('maintenance.edit_schedule'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_maintenance.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="schedule_id" id="editScheduleId">
                
                <div class="modal-body">
                    <!-- Same form fields as Add modal but with "edit" prefix -->
                    <div class="mb-3">
                        <label for="editAssetId" class="form-label"><?php echo getTranslation('maintenance.asset'); ?> *</label>
                        <select class="form-select" id="editAssetId" name="asset_id" required>
                            <option value=""><?php echo getTranslation('maintenance.select_asset'); ?></option>
                            <?php
                            $assets_result = $conn->query($assets_query);
                            if ($assets_result && $assets_result->num_rows > 0) {
                                while ($asset = $assets_result->fetch_assoc()) {
                                    echo "<option value='" . $asset['id'] . "'>" . htmlspecialchars($asset['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editScheduleType" class="form-label"><?php echo getTranslation('maintenance.type'); ?> *</label>
                        <select class="form-select" id="editScheduleType" name="schedule_type" required>
                            <option value="daily"><?php echo getTranslation('maintenance.daily'); ?></option>
                            <option value="weekly"><?php echo getTranslation('maintenance.weekly'); ?></option>
                            <option value="monthly"><?php echo getTranslation('maintenance.monthly'); ?></option>
                            <option value="quarterly"><?php echo getTranslation('maintenance.quarterly'); ?></option>
                            <option value="yearly"><?php echo getTranslation('maintenance.yearly'); ?></option>
                            <option value="custom"><?php echo getTranslation('maintenance.custom'); ?></option>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div id="editCustomFrequencyGroup" class="row" style="display: none;">
                        <div class="col-md-6 mb-3">
                            <label for="editFrequencyValue" class="form-label"><?php echo getTranslation('maintenance.frequency_value'); ?></label>
                            <input type="number" class="form-control" id="editFrequencyValue" name="frequency_value" min="1" value="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editFrequencyUnit" class="form-label"><?php echo getTranslation('maintenance.frequency_unit'); ?></label>
                            <select class="form-select" id="editFrequencyUnit" name="frequency_unit">
                                <option value="days"><?php echo getTranslation('maintenance.days'); ?></option>
                                <option value="weeks"><?php echo getTranslation('maintenance.weeks'); ?></option>
                                <option value="months"><?php echo getTranslation('maintenance.months'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editLastMaintenance" class="form-label"><?php echo getTranslation('maintenance.last_maintenance'); ?></label>
                            <input type="text" class="form-control datepicker" id="editLastMaintenance" name="last_maintenance">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editNextMaintenance" class="form-label"><?php echo getTranslation('maintenance.next_maintenance'); ?> *</label>
                            <input type="text" class="form-control datepicker" id="editNextMaintenance" name="next_maintenance" required>
                            <div class="invalid-feedback">
                                <?php echo getTranslation('common.required'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editTechnicianId" class="form-label"><?php echo getTranslation('maintenance.technician'); ?></label>
                        <input type="text" class="form-control" id="editTechnicianId" name="assigned_technician" placeholder="<?php echo getTranslation('maintenance.technician'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editMaintenanceStatus" class="form-label"><?php echo getTranslation('maintenance.status'); ?></label>
                        <select class="form-select" id="editMaintenanceStatus" name="status">
                            <option value="active"><?php echo getTranslation('common.active'); ?></option>
                            <option value="paused"><?php echo getTranslation('maintenance.paused'); ?></option>
                        </select>
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

<script src="../assets/js/maintenance.js"></script>
<?php require_once '../includes/footer.php'; ?> 
