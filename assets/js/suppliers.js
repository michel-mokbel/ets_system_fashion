/**
 * Suppliers Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with server-side processing
    const suppliersTable = $('#suppliersTable').DataTable({
        processing: true,
        searching: false,
        serverSide: true,
        ajax: {
            url: '../ajax/get_suppliers.php',
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
                    title: 'Error',
                    text: 'Failed to load suppliers data. Please refresh the page.',
                    icon: 'danger'
                });
            }
        },
        columns: [
            { data: 'name' },
            { data: 'contact_person' },
            { 
                data: 'email',
                render: function(data) {
                    return data ? '<a href="mailto:' + data + '">' + data + '</a>' : '';
                }
            },
            { data: 'phone' },
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
                render: function(data) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-info view-supplier" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary edit-supplier" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Supplier">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-supplier" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Delete Supplier">
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
            emptyTable: 'No suppliers found',
            zeroRecords: 'No matching suppliers found',
            info: 'Showing _START_ to _END_ of _TOTAL_ suppliers',
            infoEmpty: 'Showing 0 to 0 of 0 suppliers',
            infoFiltered: '(filtered from _MAX_ total suppliers)',
            search: '',
            searchPlaceholder: 'Search suppliers...',
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
        suppliersTable.ajax.reload();
    });
    
    // Handle view button click
    $(document).on('click', '.view-supplier', function() {
        const supplierId = $(this).data('id');
        loadSupplierDetails(supplierId, 'view');
    });
    
    // Handle edit button click
    $(document).on('click', '.edit-supplier', function() {
        const supplierId = $(this).data('id');
        loadSupplierDetails(supplierId, 'edit');
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-supplier', function() {
        const supplierId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this supplier?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteSupplier(supplierId);
            }
        });
    });
    
    // Handle edit button in view modal
    $('#editSupplierBtn').on('click', function() {
        const supplierId = $('#viewSupplierModal').data('id');
        loadSupplierDetails(supplierId, 'edit');
        $('#viewSupplierModal').modal('hide');
    });
});

/**
 * Load supplier details for viewing or editing
 * @param {number} supplierId - The ID of the supplier to load
 * @param {string} mode - 'view' or 'edit'
 */
function loadSupplierDetails(supplierId, mode) {
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we load the supplier data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch supplier data via AJAX
    $.ajax({
        url: '../ajax/get_supplier.php',
        type: 'POST',
        data: {
            supplier_id: supplierId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const supplier = response.data;
                
                if (mode === 'view') {
                    // Fill view modal with supplier data
                    $('#viewSupplierName').text(supplier.name);
                    $('#viewContactPerson').text(supplier.contact_person || 'Not specified');
                    $('#viewEmail').text(supplier.email || 'Not specified');
                    $('#viewEmailLink').attr('href', 'mailto:' + (supplier.email || ''));
                    $('#viewPhone').text(supplier.phone || 'Not specified');
                    $('#viewStatus').text(supplier.status);
                    $('#viewCreatedAt').text(formatDate(supplier.created_at));
                    $('#viewAddress').text(supplier.address || 'No address provided');
                    
                    // Store the supplier ID in the modal for reference
                    $('#viewSupplierModal').data('id', supplier.id);
                    
                    // Show view modal
                    const viewModal = new bootstrap.Modal(document.getElementById('viewSupplierModal'));
                    viewModal.show();
                } else if (mode === 'edit') {
                    // Fill edit form with supplier data
                    $('#editSupplierId').val(supplier.id);
                    $('#editSupplierName').val(supplier.name);
                    $('#editContactPerson').val(supplier.contact_person || '');
                    $('#editEmail').val(supplier.email || '');
                    $('#editPhone').val(supplier.phone || '');
                    $('#editStatus').val(supplier.status);
                    $('#editAddress').val(supplier.address || '');
                    
                    // Show edit modal
                    const editModal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
                    editModal.show();
                }
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message || 'Failed to load supplier data',
                    icon: 'danger'
                });
            }
        },
        error: function() {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'Failed to connect to the server',
                icon: 'danger'
            });
        }
    });
}

/**
 * Delete a supplier
 * @param {number} supplierId - The ID of the supplier to delete
 */
function deleteSupplier(supplierId) {
    $.ajax({
        url: '../ajax/process_supplier.php',
        type: 'POST',
        data: {
            action: 'delete',
            supplier_id: supplierId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#suppliersTable').DataTable().ajax.reload();
                Swal.fire({
                    title: 'Success!',
                    text: response.message || 'Supplier deleted successfully',
                    icon: 'success',
                    confirmButtonColor: '#28a745'
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to delete supplier',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to connect to the server',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        }
    });
} 