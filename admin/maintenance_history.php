<?php
/**
 * Maintenance History Viewer
 * --------------------------
 * Surfaces historical maintenance records for assets and schedules, enabling
 * administrators to audit completed tasks, technician notes, and costs. The
 * page can be pre-filtered via query parameters, retrieves associated names for
 * context, and renders an interactive timeline via `assets/js/maintenance_history.js`.
 * History entries are loaded from `ajax/get_maintenance_history.php`, while
 * follow-up updates leverage `ajax/process_maintenance_history.php` for
 * consistency with the scheduler module.
 */
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Check if specific asset or schedule is requested
$asset_id = isset($_GET['asset_id']) ? (int)$_GET['asset_id'] : 0;
$schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
$asset_name = '';
$schedule_name = '';

// Get asset name if asset_id is provided
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

// Get schedule details if schedule_id is provided
if ($schedule_id > 0) {
    $schedule_query = "SELECT ms.id, a.name as asset_name, ms.schedule_type, ms.frequency_value, ms.frequency_unit 
                      FROM maintenance_schedules ms
                      JOIN assets a ON ms.asset_id = a.id
                      WHERE ms.id = ?";
    $schedule_stmt = $conn->prepare($schedule_query);
    $schedule_stmt->bind_param('i', $schedule_id);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    
    if ($schedule_result && $schedule_result->num_rows > 0) {
        $schedule_data = $schedule_result->fetch_assoc();
        
        // Format schedule name
        $frequency = '';
        switch ($schedule_data['schedule_type']) {
            case 'daily':
                $frequency = 'Daily';
                break;
            case 'weekly':
                $frequency = 'Weekly';
                break;
            case 'monthly':
                $frequency = 'Monthly';
                break;
            case 'quarterly':
                $frequency = 'Quarterly';
                break;
            case 'yearly':
                $frequency = 'Yearly';
                break;
            case 'custom':
                $frequency = 'Every ' . $schedule_data['frequency_value'] . ' ' . $schedule_data['frequency_unit'];
                break;
        }
        
        $schedule_name = $schedule_data['asset_name'] . ' - ' . $frequency;
    } else {
        // Schedule not found, reset schedule_id
        $schedule_id = 0;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo getTranslation('maintenance.history_title'); ?></h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceHistoryModal">
        <i class="bi bi-plus-circle me-1"></i> <?php echo getTranslation('maintenance.add_history'); ?>
    </button>
</div>

<?php if (!empty($asset_name) || !empty($schedule_name)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <?php if (!empty($asset_name)): ?>
        <?php echo sprintf(getTranslation('maintenance.viewing_for_asset'), htmlspecialchars($asset_name)); ?>
    <?php elseif (!empty($schedule_name)): ?>
        <?php echo sprintf(getTranslation('maintenance.viewing_for_schedule'), htmlspecialchars($schedule_name)); ?>
    <?php endif; ?>
    <a href="maintenance_history.php" class="ms-2"><?php echo getTranslation('maintenance.view_all'); ?></a>
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
                <label class="form-label"><?php echo getTranslation('maintenance.schedule'); ?></label>
                <select class="form-select" name="schedule_id">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <?php
                    $schedules_query = "SELECT ms.id, a.name as asset_name, ms.schedule_type, ms.frequency_value, ms.frequency_unit 
                                      FROM maintenance_schedules ms
                                      JOIN assets a ON ms.asset_id = a.id
                                      ORDER BY a.name, ms.schedule_type";
                    $schedules_result = $conn->query($schedules_query);
                    
                    if ($schedules_result && $schedules_result->num_rows > 0) {
                        while ($schedule = $schedules_result->fetch_assoc()) {
                            // Format schedule name
                            $frequency = '';
                            switch ($schedule['schedule_type']) {
                                case 'daily':
                                    $frequency = 'Daily';
                                    break;
                                case 'weekly':
                                    $frequency = 'Weekly';
                                    break;
                                case 'monthly':
                                    $frequency = 'Monthly';
                                    break;
                                case 'quarterly':
                                    $frequency = 'Quarterly';
                                    break;
                                case 'yearly':
                                    $frequency = 'Yearly';
                                    break;
                                case 'custom':
                                    $frequency = 'Every ' . $schedule['frequency_value'] . ' ' . $schedule['frequency_unit'];
                                    break;
                            }
                            
                            $schedule_display = $schedule['asset_name'] . ' - ' . $frequency;
                            $selected = ($schedule_id == $schedule['id']) ? 'selected' : '';
                            echo "<option value='" . $schedule['id'] . "' $selected>" . htmlspecialchars($schedule_display) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo getTranslation('maintenance.status'); ?></label>
                <select class="form-select" name="status">
                    <option value=""><?php echo getTranslation('common.all'); ?></option>
                    <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.completed'); ?>
                    </option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>
                        <?php echo getTranslation('maintenance.pending'); ?>
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

<!-- Maintenance History Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="maintenanceHistoryTable">
                <thead class="table-light">
                    <tr>
                        <th><?php echo getTranslation('maintenance.asset'); ?></th>
                        <th><?php echo getTranslation('maintenance.schedule_type'); ?></th>
                        <th><?php echo getTranslation('maintenance.completion_date'); ?></th>
                        <th><?php echo getTranslation('maintenance.completed_by'); ?></th>
                        <th><?php echo getTranslation('maintenance.status'); ?></th>
                        <th><?php echo getTranslation('maintenance.notes'); ?></th>
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

<!-- Add Maintenance History Modal -->
<div class="modal fade" id="addMaintenanceHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('maintenance.add_history'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_maintenance_history.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="scheduleId" class="form-label"><?php echo getTranslation('maintenance.schedule'); ?> *</label>
                        <select class="form-select" id="scheduleId" name="maintenance_schedule_id" required>
                            <option value=""><?php echo getTranslation('maintenance.select_schedule'); ?></option>
                            <?php
                            $schedules_result = $conn->query($schedules_query);
                            if ($schedules_result && $schedules_result->num_rows > 0) {
                                while ($schedule = $schedules_result->fetch_assoc()) {
                                    // Format schedule name
                                    $frequency = '';
                                    switch ($schedule['schedule_type']) {
                                        case 'daily':
                                            $frequency = 'Daily';
                                            break;
                                        case 'weekly':
                                            $frequency = 'Weekly';
                                            break;
                                        case 'monthly':
                                            $frequency = 'Monthly';
                                            break;
                                        case 'quarterly':
                                            $frequency = 'Quarterly';
                                            break;
                                        case 'yearly':
                                            $frequency = 'Yearly';
                                            break;
                                        case 'custom':
                                            $frequency = 'Every ' . $schedule['frequency_value'] . ' ' . $schedule['frequency_unit'];
                                            break;
                                    }
                                    
                                    $schedule_display = $schedule['asset_name'] . ' - ' . $frequency;
                                    $selected = ($schedule_id == $schedule['id']) ? 'selected' : '';
                                    echo "<option value='" . $schedule['id'] . "' $selected>" . htmlspecialchars($schedule_display) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="completionDate" class="form-label"><?php echo getTranslation('maintenance.completion_date'); ?> *</label>
                        <input type="text" class="form-control datepicker" id="completionDate" name="completion_date" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="completedBy" class="form-label"><?php echo getTranslation('maintenance.completed_by'); ?></label>
                        <input type="text" class="form-control" id="completedBy" name="completed_by" placeholder="<?php echo getTranslation('maintenance.technician_name'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="maintenanceStatus" class="form-label"><?php echo getTranslation('maintenance.status'); ?> *</label>
                        <select class="form-select" id="maintenanceStatus" name="status" required>
                            <option value="completed"><?php echo getTranslation('maintenance.completed'); ?></option>
                            <option value="pending"><?php echo getTranslation('maintenance.pending'); ?></option>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label"><?php echo getTranslation('maintenance.notes'); ?></label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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

<!-- Edit Maintenance History Modal -->
<div class="modal fade" id="editMaintenanceHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo getTranslation('maintenance.edit_history'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../ajax/process_maintenance_history.php" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="history_id" id="editHistoryId">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editScheduleId" class="form-label"><?php echo getTranslation('maintenance.schedule'); ?> *</label>
                        <select class="form-select" id="editScheduleId" name="maintenance_schedule_id" required>
                            <option value=""><?php echo getTranslation('maintenance.select_schedule'); ?></option>
                            <?php
                            $schedules_result = $conn->query($schedules_query);
                            if ($schedules_result && $schedules_result->num_rows > 0) {
                                while ($schedule = $schedules_result->fetch_assoc()) {
                                    // Format schedule name
                                    $frequency = '';
                                    switch ($schedule['schedule_type']) {
                                        case 'daily':
                                            $frequency = 'Daily';
                                            break;
                                        case 'weekly':
                                            $frequency = 'Weekly';
                                            break;
                                        case 'monthly':
                                            $frequency = 'Monthly';
                                            break;
                                        case 'quarterly':
                                            $frequency = 'Quarterly';
                                            break;
                                        case 'yearly':
                                            $frequency = 'Yearly';
                                            break;
                                        case 'custom':
                                            $frequency = 'Every ' . $schedule['frequency_value'] . ' ' . $schedule['frequency_unit'];
                                            break;
                                    }
                                    
                                    $schedule_display = $schedule['asset_name'] . ' - ' . $frequency;
                                    echo "<option value='" . $schedule['id'] . "'>" . htmlspecialchars($schedule_display) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCompletionDate" class="form-label"><?php echo getTranslation('maintenance.completion_date'); ?> *</label>
                        <input type="text" class="form-control datepicker" id="editCompletionDate" name="completion_date" required>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editCompletedBy" class="form-label"><?php echo getTranslation('maintenance.completed_by'); ?></label>
                        <input type="text" class="form-control" id="editCompletedBy" name="completed_by" placeholder="<?php echo getTranslation('maintenance.technician_name'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editMaintenanceStatus" class="form-label"><?php echo getTranslation('maintenance.status'); ?> *</label>
                        <select class="form-select" id="editMaintenanceStatus" name="status" required>
                            <option value="completed"><?php echo getTranslation('maintenance.completed'); ?></option>
                            <option value="pending"><?php echo getTranslation('maintenance.pending'); ?></option>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo getTranslation('common.required'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editNotes" class="form-label"><?php echo getTranslation('maintenance.notes'); ?></label>
                        <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
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

<script src="../assets/js/maintenance_history.js"></script>
<?php require_once '../includes/footer.php'; ?> 