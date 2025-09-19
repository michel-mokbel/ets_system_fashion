/**
 * Preventive maintenance scheduler controller.
 *
 * Responsibilities:
 * - Populate the maintenance schedule DataTable with filter parameters, CSRF tokens, and status badges highlighting overdue work.
 * - Manage the add/edit schedule modal, including recurrence dropdowns, asset autocomplete wiring, and server submissions.
 * - Coordinate follow-up actions such as generating work orders directly from a schedule row.
 *
 * Dependencies:
 * - jQuery, DataTables, SweetAlert, Bootstrap modals, and Select2/autocomplete widgets referenced later in the file.
 * - Backend endpoints: `../ajax/get_maintenance.php` (listing) and `../ajax/process_maintenance.php` (mutations).
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with server-side processing
    const maintenanceTable = $('#maintenanceTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '../ajax/get_maintenance.php',
            type: 'POST',
            data: function(d) {
                // Add filter parameters from the form
                const filterForm = document.getElementById('filterForm');
                if (filterForm) {
                    const formData = new FormData(filterForm);
                    for (const [key, value] of formData.entries()) {
                        d[key] = value;
                    }
                }
                
                // Add CSRF token
                d.csrf_token = $('input[name="csrf_token"]').val();
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables error:', error, thrown);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load maintenance data. Please refresh the page.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: 'asset_name' },
            { data: 'schedule_type' },
            { data: 'frequency' },
            { 
                data: 'last_maintenance',
                render: function(data) {
                    return data ? formatDate(data) : '-';
                }
            },
            { 
                data: 'next_maintenance',
                render: function(data) {
                    if (!data) return '-';
                    
                    const today = new Date();
                    const nextDate = new Date(data);
                    
                    if (nextDate < today) {
                        return '<span class="text-danger">' + formatDate(data) + ' (Overdue)</span>';
                    }
                    
                    // Calculate days until next maintenance
                    const diffTime = Math.abs(nextDate - today);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    
                    if (diffDays <= 7) {
                        return '<span class="text-warning">' + formatDate(data) + ' (' + diffDays + ' days)</span>';
                    }
                    
                    return formatDate(data);
                }
            },
            { data: 'technician_name' },
            {
                data: 'status',
                render: function(data) {
                    const badgeClass = data === 'active' ? 'bg-success' : 'bg-secondary';
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    let buttons = `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary edit-schedule" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Schedule">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-schedule" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Delete Schedule">
                                <i class="bi bi-trash"></i>
                            </button>
                    `;
                    
                    // Add "Complete Maintenance" button if it's an active schedule
                    if (row.status === 'active') {
                        buttons += `
                            <button class="btn btn-sm btn-success complete-maintenance" 
                                    data-id="${data}" data-asset-id="${row.asset_id}"
                                    data-bs-toggle="tooltip" title="Complete Maintenance">
                                <i class="bi bi-check-circle"></i>
                            </button>
                        `;
                    }
                    
                    buttons += `</div>`;
                    return buttons;
                }
            }
        ],
        // Fix for DataTables width calculation issues
        columnDefs: [
            { width: "15%", targets: 0 },
            { width: "10%", targets: 1 },
            { width: "10%", targets: 2 },
            { width: "10%", targets: 3 },
            { width: "15%", targets: 4 },
            { width: "10%", targets: 5 },
            { width: "10%", targets: 6 },
            { width: "20%", targets: 7 }
        ],
        // Additional options for better performance
        deferRender: true,
        responsive: true,
        // Localization
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
            emptyTable: 'No maintenance schedules found',
            zeroRecords: 'No matching schedules found',
            info: 'Showing _START_ to _END_ of _TOTAL_ schedules',
            infoEmpty: 'Showing 0 to 0 of 0 schedules',
            infoFiltered: '(filtered from _MAX_ total schedules)',
            search: '',
            searchPlaceholder: 'Search schedules...',
            lengthMenu: '_MENU_ per page',
            paginate: {
                first: '<i class="bi bi-chevron-double-left"></i>',
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>',
                last: '<i class="bi bi-chevron-double-right"></i>'
            }
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>',
        // Initialize tooltips after table draws
        drawCallback: function() {
            initializeTooltipsPopovers();
        }
    });
    
    // Apply filters when the filter form is submitted
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        maintenanceTable.ajax.reload();
    });
    
    // Initialize datepickers
    initializeDatepickers();
    
    // Toggle custom frequency options based on schedule type selection
    $('#scheduleType').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#customFrequencyGroup').show();
        } else {
            $('#customFrequencyGroup').hide();
        }
    });
    
    $('#editScheduleType').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#editCustomFrequencyGroup').show();
        } else {
            $('#editCustomFrequencyGroup').hide();
        }
    });
    
    // Handle add maintenance form submission
    $('#addMaintenanceModal form').on('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }
        
        // Confirm with SweetAlert
        Swal.fire({
            title: 'Confirm New Schedule',
            text: 'Are you sure you want to create this maintenance schedule?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, create it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Saving...',
                    html: 'Creating maintenance schedule',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit via AJAX
                $.ajax({
                    url: '../ajax/process_maintenance.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                title: 'Success!',
                                text: response.message || 'Maintenance schedule added successfully',
                                icon: 'success',
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                // Reset form and close modal
                                $('#addMaintenanceModal form')[0].reset();
                                $('#addMaintenanceModal').modal('hide');
                                
                                // Reload the table
                                $('#maintenanceTable').DataTable().ajax.reload();
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                title: 'Error!',
                                text: response.message || 'Failed to add maintenance schedule',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    },
                    error: function() {
                        Swal.close();
                        
                        // Show error message
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to connect to the server',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    });
    
    // Handle edit button click
    $(document).on('click', '.edit-schedule', function() {
        const scheduleId = $(this).data('id');
        loadScheduleData(scheduleId);
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-schedule', function() {
        const scheduleId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this maintenance schedule?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteSchedule(scheduleId);
            }
        });
    });
    
    // Handle complete maintenance button click
    $(document).on('click', '.complete-maintenance', function() {
        const scheduleId = $(this).data('id');
        
        // Get today's date
        const today = new Date();
        const formattedDate = today.getFullYear() + '-' + 
                             String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                             String(today.getDate()).padStart(2, '0');
        
        // Show completion form
        Swal.fire({
            title: 'Complete Maintenance',
            html: `
                <form id="completeMaintenanceForm" class="text-start">
                    <input type="hidden" name="maintenance_schedule_id" value="${scheduleId}">
                    <input type="hidden" name="status" value="completed">
                    
                    <div class="mb-3">
                        <label for="completionDate" class="form-label">Completion Date</label>
                        <input type="date" class="form-control" id="completionDate" name="completion_date" value="${formattedDate}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="completedBy" class="form-label">Completed By</label>
                        <input type="text" class="form-control" id="completedBy" name="completed_by" placeholder="Technician name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Maintenance notes..."></textarea>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Complete',
            cancelButtonText: 'Cancel',
            focusConfirm: false,
            preConfirm: () => {
                const form = document.getElementById('completeMaintenanceForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                const formData = new FormData(form);
                const data = {};
                for (const [key, value] of formData.entries()) {
                    data[key] = value;
                }
                
                // Add CSRF token
                data.csrf_token = $('input[name="csrf_token"]').val();
                data.action = 'add';
                
                return data;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Saving...',
                    html: 'Completing maintenance',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit via AJAX
                $.ajax({
                    url: '../ajax/process_maintenance_history.php',
                    type: 'POST',
                    data: result.value,
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                title: 'Success!',
                                text: 'Maintenance completed successfully. The schedule has been updated.',
                                icon: 'success',
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                // Reload the table
                                $('#maintenanceTable').DataTable().ajax.reload();
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                title: 'Error!',
                                text: response.message || 'Failed to complete maintenance',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    },
                    error: function() {
                        Swal.close();
                        
                        // Show error message
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to connect to the server',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    });
    
    // Handle edit form submission
    $('#editMaintenanceModal form').on('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }
        
        // Confirm with SweetAlert
        Swal.fire({
            title: 'Confirm Update',
            text: 'Are you sure you want to update this maintenance schedule?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, update it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Show loading state
                Swal.fire({
                    title: 'Saving...',
                    html: 'Updating maintenance schedule',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit via AJAX
                $.ajax({
                    url: '../ajax/process_maintenance.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                title: 'Success!',
                                text: response.message || 'Maintenance schedule updated successfully',
                                icon: 'success',
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                // Close modal
                                $('#editMaintenanceModal').modal('hide');
                                
                                // Reload the table
                                $('#maintenanceTable').DataTable().ajax.reload();
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                title: 'Error!',
                                text: response.message || 'Failed to update maintenance schedule',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    },
                    error: function() {
                        Swal.close();
                        
                        // Show error message
                        Swal.fire({
                            title: 'Error!',
                            text: 'Failed to connect to the server',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    });
    
    // Add button to view maintenance history
    $('#viewHistoryBtn').on('click', function() {
        window.location.href = 'maintenance_history.php';
    });
});

/**
 * Load maintenance schedule data for editing
 * @param {number} scheduleId - The ID of the schedule to edit
 */
function loadScheduleData(scheduleId) {
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we load the schedule data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch schedule data via AJAX
    $.ajax({
        url: '../ajax/get_maintenance_schedule.php',
        type: 'POST',
        data: {
            schedule_id: scheduleId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const schedule = response.data;
                
                // Fill edit form with schedule data
                $('#editScheduleId').val(schedule.id);
                $('#editAssetId').val(schedule.asset_id);
                $('#editScheduleType').val(schedule.schedule_type);
                
                // Handle custom frequency
                if (schedule.schedule_type === 'custom') {
                    $('#editCustomFrequencyGroup').show();
                    $('#editFrequencyValue').val(schedule.frequency_value);
                    $('#editFrequencyUnit').val(schedule.frequency_unit);
                } else {
                    $('#editCustomFrequencyGroup').hide();
                }
                
                $('#editLastMaintenance').val(schedule.last_maintenance);
                $('#editNextMaintenance').val(schedule.next_maintenance);
                $('#editTechnicianId').val(schedule.assigned_technician);
                $('#editMaintenanceStatus').val(schedule.status);
                
                // Reinitialize datepickers for edit form
                $('#editLastMaintenance, #editNextMaintenance').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    yearRange: 'c-5:c+5'
                });
                
                // Show edit modal
                const editModal = new bootstrap.Modal(document.getElementById('editMaintenanceModal'));
                editModal.show();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to load schedule data',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        },
        error: function() {
            Swal.close();
            Swal.fire({
                title: 'Error!',
                text: 'Failed to connect to the server',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        }
    });
}

/**
 * Delete a maintenance schedule
 * @param {number} scheduleId - The ID of the schedule to delete
 */
function deleteSchedule(scheduleId) {
    Swal.fire({
        title: 'Confirm Deletion',
        text: 'Are you sure you want to delete this maintenance schedule?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                html: 'Deleting maintenance schedule',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            $.ajax({
                url: '../ajax/process_maintenance.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    schedule_id: scheduleId,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        // Reload the table
                        $('#maintenanceTable').DataTable().ajax.reload();
                        Swal.fire({
                            title: 'Success!',
                            text: response.message || 'Schedule deleted successfully',
                            icon: 'success',
                            confirmButtonColor: '#198754'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message || 'Failed to delete schedule',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to connect to the server',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            });
        }
    });
} 