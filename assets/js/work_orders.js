/**
 * Work Orders Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with server-side processing
    const workOrdersTable = $('#workOrdersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '../ajax/get_work_orders.php',
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
                    text: 'Failed to load work orders data. Please refresh the page.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: 'work_order_number' },
            { data: 'asset_name' },
            { 
                data: 'maintenance_type',
                render: function(data) {
                    return data.charAt(0).toUpperCase() + data.slice(1);
                }
            },
            { 
                data: 'priority',
                className: 'text-center',
                render: function(data) {
                    let badgeClass = '';
                    switch (data) {
                        case 'low': 
                            badgeClass = 'bg-info'; 
                            break;
                        case 'medium': 
                            badgeClass = 'bg-primary'; 
                            break;
                        case 'high': 
                            badgeClass = 'status-pending'; 
                            break;
                        case 'critical': 
                            badgeClass = 'status-cancelled'; 
                            break;
                        default: 
                            badgeClass = 'bg-secondary';
                    }
                    
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                }
            },
            { 
                data: 'scheduled_date',
                render: function(data) {
                    return data ? formatDate(data) : '-';
                }
            },
            {
                data: 'status',
                className: 'text-center',
                render: function(data) {
                    let statusClass = '';
                    switch (data) {
                        case 'pending': 
                            statusClass = 'status-pending'; 
                            break;
                        case 'in_progress': 
                            statusClass = 'bg-primary'; 
                            break;
                        case 'completed': 
                            statusClass = 'status-approved'; 
                            break;
                        case 'cancelled': 
                            statusClass = 'status-cancelled'; 
                            break;
                        default: 
                            statusClass = 'badge-secondary';
                    }
                    
                    return '<span class="badge ' + statusClass + '">' + data.replace('_', ' ') + '</span>';
                }
            },
            { 
                data: 'id',
                orderable: false,
                className: 'text-center',
                render: function(data) {
                    return `
                        <div class="action-buttons">
                            <button class="btn btn-view view-work-order" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-edit edit-work-order" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Work Order">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-delete delete-work-order" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Delete Work Order">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        // Fix for DataTables width calculation issues
        columnDefs: [
            { width: "15%", targets: 0 },
            { width: "15%", targets: 1 },
            { width: "10%", targets: 2 },
            { width: "10%", targets: 3 },
            { width: "15%", targets: 4 },
            { width: "15%", targets: 5 },
            { width: "20%", targets: 6 }
        ],
        // Additional options for better performance
        deferRender: true,
        responsive: true,
        // Localization
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
            emptyTable: 'No work orders found',
            zeroRecords: 'No matching work orders found',
            info: 'Showing _START_ to _END_ of _TOTAL_ work orders',
            infoEmpty: 'Showing 0 to 0 of 0 work orders',
            infoFiltered: '(filtered from _MAX_ total work orders)',
            search: '',
            searchPlaceholder: 'Search work orders...',
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
        workOrdersTable.ajax.reload();
    });
    
    // Initialize datepickers
    initializeDatepickers();
    
    // Handle view button click
    $(document).on('click', '.view-work-order', function() {
        const workOrderId = $(this).data('id');
        loadWorkOrderDetails(workOrderId, 'view');
    });
    
    // Handle edit button click
    $(document).on('click', '.edit-work-order', function() {
        const workOrderId = $(this).data('id');
        loadWorkOrderDetails(workOrderId, 'edit');
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-work-order', function() {
        const workOrderId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this work order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteWorkOrder(workOrderId);
            }
        });
    });
    
    // Handle edit button in view modal
    $('#editWorkOrderBtn').on('click', function() {
        const workOrderId = $('#viewWorkOrderModal').data('id');
        loadWorkOrderDetails(workOrderId, 'edit');
        $('#viewWorkOrderModal').modal('hide');
    });
    
    // Auto-update work order status when completed date is filled
    $('#editCompletedDate').on('change', function() {
        if ($(this).val()) {
            $('#editStatus').val('completed');
        }
    });
    
    // Change scheduled date format when status changes to completed
    $('#editStatus').on('change', function() {
        if ($(this).val() === 'completed' && !$('#editCompletedDate').val()) {
            // Set completed date to today if not already set
            const today = new Date();
            const formattedDate = today.getFullYear() + '-' + 
                                 String(today.getMonth() + 1).padStart(2, '0') + '-' + 
                                 String(today.getDate()).padStart(2, '0');
            $('#editCompletedDate').val(formattedDate);
        }
    });
});

/**
 * Load work order details for viewing or editing
 * @param {number} workOrderId - The ID of the work order to load
 * @param {string} mode - 'view' or 'edit'
 */
function loadWorkOrderDetails(workOrderId, mode) {
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we load the work order data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch work order data via AJAX
    $.ajax({
        url: '../ajax/get_work_order.php',
        type: 'POST',
        data: {
            work_order_id: workOrderId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const workOrder = response.data;
                
                if (mode === 'view') {
                    // Fill view modal with work order data
                    $('#viewWorkOrderNumber').text(workOrder.work_order_number);
                    $('#viewAssetName').text(workOrder.asset_name);
                    $('#viewMaintenanceType').text(workOrder.maintenance_type);
                    $('#viewPriority').text(workOrder.priority);
                    $('#viewScheduledDate').text(formatDate(workOrder.scheduled_date));
                    $('#viewCompletedDate').text(workOrder.completed_date ? formatDate(workOrder.completed_date) : 'Not completed');
                    $('#viewStatus').text(workOrder.status.replace('_', ' '));
                    $('#viewCreatedAt').text(formatDate(workOrder.created_at));
                    $('#viewDescription').text(workOrder.description || 'No description provided');
                    $('#viewNotes').text(workOrder.notes || 'No notes available');
                    
                    // Store the work order ID in the modal for reference
                    $('#viewWorkOrderModal').data('id', workOrder.id);
                    
                    // Show view modal
                    const viewModal = new bootstrap.Modal(document.getElementById('viewWorkOrderModal'));
                    viewModal.show();
                } else if (mode === 'edit') {
                    // Fill edit form with work order data
                    $('#editWorkOrderId').val(workOrder.id);
                    $('#editWorkOrderNumber').val(workOrder.work_order_number);
                    $('#editAssetId').val(workOrder.asset_id);
                    $('#editMaintenanceType').val(workOrder.maintenance_type);
                    $('#editPriority').val(workOrder.priority);
                    $('#editDescription').val(workOrder.description);
                    $('#editScheduledDate').val(workOrder.scheduled_date);
                    $('#editCompletedDate').val(workOrder.completed_date || '');
                    $('#editStatus').val(workOrder.status);
                    $('#editNotes').val(workOrder.notes || '');
                    
                    // Reinitialize datepickers for edit form
                    $('#editScheduledDate, #editCompletedDate').datepicker({
                        dateFormat: 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true,
                        yearRange: 'c-5:c+5'
                    });
                    
                    // Show edit modal
                    const editModal = new bootstrap.Modal(document.getElementById('editWorkOrderModal'));
                    editModal.show();
                }
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to load work order data',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        error: function() {
            Swal.close();
            Swal.fire({
                title: 'Error!',
                text: 'Failed to connect to the server',
                icon: 'error',
                confirmButtonColor: '#dc3545',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

/**
 * Delete a work order
 * @param {number} workOrderId - The ID of the work order to delete
 */
function deleteWorkOrder(workOrderId) {
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we process the deletion',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: '../ajax/process_work_order.php',
        type: 'POST',
        data: {
            action: 'delete',
            work_order_id: workOrderId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                // Reload the table
                $('#workOrdersTable').DataTable().ajax.reload();
                Swal.fire({
                    title: 'Success!',
                    text: response.message || 'Work order deleted successfully',
                    icon: 'success',
                    confirmButtonColor: '#28a745',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to delete work order',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        error: function() {
            Swal.close();
            Swal.fire({
                title: 'Error!',
                text: 'Failed to connect to the server',
                icon: 'error',
                confirmButtonColor: '#dc3545',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
} 