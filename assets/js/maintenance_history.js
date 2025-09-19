/**
 * Maintenance history log controller.
 *
 * Responsibilities:
 * - Render completed maintenance entries with filterable DataTable columns and contextual status badges.
 * - Power the add/edit modal for logging technician notes, completion evidence, and file attachments.
 * - Handle delete confirmations and refresh the grid after any mutation so audit trails remain current.
 *
 * Dependencies:
 * - jQuery, DataTables (responsive), SweetAlert, Bootstrap modals, and datepicker widgets used within the modal forms.
 * - Backend endpoints `../ajax/get_maintenance_history.php` (list) and `../ajax/process_maintenance_history.php` (CRUD).
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with server-side processing
    const maintenanceHistoryTable = $('#maintenanceHistoryTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '../ajax/get_maintenance_history.php',
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
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load maintenance history data. Please refresh the page.',
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
            { 
                data: 'completion_date',
                render: function(data) {
                    return data ? formatDate(data) : '-';
                }
            },
            { data: 'completed_by' },
            {
                data: 'status',
                render: function(data) {
                    const badgeClass = data === 'completed' ? 'bg-success' : 'bg-warning';
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                }
            },
            { data: 'notes' },
            { 
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary edit-history" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Entry">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-history" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Delete Entry">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        // Additional options for better performance
        deferRender: true,
        responsive: true,
        // Localization
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
            emptyTable: 'No maintenance history found',
            zeroRecords: 'No matching history entries found',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'Showing 0 to 0 of 0 entries',
            infoFiltered: '(filtered from _MAX_ total entries)',
            search: '',
            searchPlaceholder: 'Search history...',
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
        maintenanceHistoryTable.ajax.reload();
    });
    
    // Initialize datepickers
    initializeDatepickers();
    
    // Handle add maintenance history form submission
    $('#addMaintenanceHistoryModal form').on('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }
        
        // Check if status is completed
        const isCompleted = $('#maintenanceStatus').val() === 'completed';
        
        // Prepare confirmation message
        let confirmMsg = 'Are you sure you want to create this maintenance history entry?';
        if (isCompleted) {
            confirmMsg += ' This will update the last and next maintenance dates for this schedule.';
        }
        
        // Confirm with SweetAlert
        Swal.fire({
            title: 'Confirm New Entry',
            text: confirmMsg,
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
                    html: 'Creating maintenance history entry',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit via AJAX
                $.ajax({
                    url: '../ajax/process_maintenance_history.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                title: 'Success!',
                                text: response.message || 'Maintenance history added successfully',
                                icon: 'success',
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                // Reset form and close modal
                                $('#addMaintenanceHistoryModal form')[0].reset();
                                $('#addMaintenanceHistoryModal').modal('hide');
                                
                                // Reload the table
                                $('#maintenanceHistoryTable').DataTable().ajax.reload();
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                title: 'Error!',
                                text: response.message || 'Failed to add maintenance history',
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
    $(document).on('click', '.edit-history', function() {
        const historyId = $(this).data('id');
        loadHistoryData(historyId);
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-history', function() {
        const historyId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this maintenance history entry?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteHistoryEntry(historyId);
            }
        });
    });
    
    // Handle edit form submission
    $('#editMaintenanceHistoryModal form').on('submit', function(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.checkValidity()) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }
        
        // Check if status is changed to completed
        const oldStatus = $(this).data('old-status');
        const newStatus = $('#editMaintenanceStatus').val();
        const statusChanged = oldStatus !== 'completed' && newStatus === 'completed';
        
        // Prepare confirmation message
        let confirmMsg = 'Are you sure you want to update this maintenance history entry?';
        if (statusChanged) {
            confirmMsg += ' This will update the last and next maintenance dates for this schedule.';
        }
        
        // Confirm with SweetAlert
        Swal.fire({
            title: 'Confirm Update',
            text: confirmMsg,
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
                    html: 'Updating maintenance history entry',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Submit via AJAX
                $.ajax({
                    url: '../ajax/process_maintenance_history.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success) {
                            // Show success message
                            Swal.fire({
                                title: 'Success!',
                                text: response.message || 'Maintenance history updated successfully',
                                icon: 'success',
                                confirmButtonColor: '#198754'
                            }).then(() => {
                                // Close modal
                                $('#editMaintenanceHistoryModal').modal('hide');
                                
                                // Reload the table
                                $('#maintenanceHistoryTable').DataTable().ajax.reload();
                            });
                        } else {
                            // Show error message
                            Swal.fire({
                                title: 'Error!',
                                text: response.message || 'Failed to update maintenance history',
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
    
    // Auto-initialize datepicker when the modal is shown
    $('#addMaintenanceHistoryModal, #editMaintenanceHistoryModal').on('shown.bs.modal', function() {
        $('.datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: 'c-5:c+5'
        });
        
        // Set today's date for completion date if it's a new entry
        if ($(this).attr('id') === 'addMaintenanceHistoryModal' && !$('#completionDate').val()) {
            const today = new Date();
            const formattedDate = today.getFullYear() + '-' + 
                                 String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                                 String(today.getDate()).padStart(2, '0');
            $('#completionDate').val(formattedDate);
        }
    });
});

/**
 * Load maintenance history data for editing
 * @param {number} historyId - The ID of the history entry to edit
 */
function loadHistoryData(historyId) {
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we load the history data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch history data via AJAX
    $.ajax({
        url: '../ajax/get_maintenance_history_entry.php',
        type: 'POST',
        data: {
            history_id: historyId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const historyData = response.data;
                
                // Fill edit form with history data
                $('#editHistoryId').val(historyData.id);
                $('#editScheduleId').val(historyData.maintenance_schedule_id);
                $('#editCompletionDate').val(historyData.completion_date);
                $('#editCompletedBy').val(historyData.completed_by);
                $('#editMaintenanceStatus').val(historyData.status);
                $('#editNotes').val(historyData.notes);
                
                // Store old status for checking if status changes
                $('#editMaintenanceHistoryModal form').data('old-status', historyData.status);
                
                // Show edit modal
                const editModal = new bootstrap.Modal(document.getElementById('editMaintenanceHistoryModal'));
                editModal.show();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to load history data',
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
 * Delete a maintenance history entry
 * @param {number} historyId - The ID of the history entry to delete
 */
function deleteHistoryEntry(historyId) {
    Swal.fire({
        title: 'Confirm Deletion',
        text: 'Are you sure you want to delete this maintenance history entry?',
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
                html: 'Deleting maintenance history entry',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            $.ajax({
                url: '../ajax/process_maintenance_history.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    history_id: historyId,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                dataType: 'json',
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        // Reload the table
                        $('#maintenanceHistoryTable').DataTable().ajax.reload();
                        Swal.fire({
                            title: 'Success!',
                            text: response.message || 'History entry deleted successfully',
                            icon: 'success',
                            confirmButtonColor: '#198754'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message || 'Failed to delete history entry',
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