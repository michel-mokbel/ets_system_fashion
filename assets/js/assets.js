/**
 * Admin asset catalogue controller.
 *
 * Responsibilities:
 * - Initialize the server-side DataTable for the asset list with filter propagation and CSRF protection.
 * - Handle CRUD modal workflows (add/edit/delete) including client-side validation and SweetAlert confirmations.
 * - Provide UX helpers such as formatted warranty status, previewing uploaded images, and resetting forms.
 *
 * Dependencies:
 * - jQuery and DataTables for DOM manipulation and grid rendering.
 * - SweetAlert for error/confirmation dialogs, Bootstrap modals for the CRUD UI, and `formatDate` helper defined at bottom of file.
 * - Backend endpoints: `../ajax/get_assets.php` for listings and `../ajax/process_asset.php` for mutations.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with server-side processing
    const assetsTable = $('#assetsTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        ajax: {
            url: '../ajax/get_assets.php',
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
                    text: 'Failed to load assets data. Please refresh the page.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: 'asset_code' },
            { data: 'name' },
            { data: 'category' },
            { data: 'location' },
            { 
                data: 'purchase_date',
                render: function(data) {
                    return data ? formatDate(data) : '';
                }
            },
            { 
                data: 'warranty_expiry',
                render: function(data) {
                    if (!data) return '';
                    
                    const today = new Date();
                    const warrantyDate = new Date(data);
                    
                    if (warrantyDate < today) {
                        return '<span class="text-danger">' + formatDate(data) + ' (Expired)</span>';
                    }
                    return formatDate(data);
                }
            },
            {
                data: 'status',
                render: function(data) {
                    let badgeClass = '';
                    switch (data) {
                        case 'operational': 
                            badgeClass = 'bg-success'; 
                            break;
                        case 'maintenance': 
                            badgeClass = 'bg-warning text-dark'; 
                            break;
                        case 'retired': 
                            badgeClass = 'bg-secondary'; 
                            break;
                        default: 
                            badgeClass = 'bg-info';
                    }
                    
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function(data) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary edit-asset" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Asset">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-asset" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Delete Asset">
                                <i class="bi bi-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info schedule-maintenance" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Schedule Maintenance">
                                <i class="bi bi-tools"></i>
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
            { width: "10%", targets: 4 },
            { width: "10%", targets: 5 },
            { width: "10%", targets: 6 },
            { width: "20%", targets: 7 }
        ],
        // Additional options for better performance
        deferRender: true,
        scroller: true,
        scrollY: 500,
        scrollCollapse: true,
        // Responsive configuration
        responsive: true,
        // Localization
        language: {
            processing: '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
            emptyTable: 'No assets found',
            zeroRecords: 'No matching assets found',
            info: 'Showing _START_ to _END_ of _TOTAL_ assets',
            infoEmpty: 'Showing 0 to 0 of 0 assets',
            infoFiltered: '(filtered from _MAX_ total assets)',
            search: '',
            searchPlaceholder: 'Search assets...',
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
            // Initialize tooltips for action buttons
            initializeTooltipsPopovers();
        }
    });
    
    // Apply filters when the filter form is submitted
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        assetsTable.ajax.reload();
    });
    
    // Initialize datepickers
    initializeDatepickers();
    
    // Handle edit button click
    $(document).on('click', '.edit-asset', function() {
        const assetId = $(this).data('id');
        loadAssetData(assetId);
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-asset', function() {
        const assetId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this asset?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteAsset(assetId);
            }
        });
    });
    
    // Handle schedule maintenance button click
    $(document).on('click', '.schedule-maintenance', function() {
        const assetId = $(this).data('id');
        // Redirect to maintenance scheduling page or show maintenance modal
        window.location.href = 'maintenance.php?asset_id=' + assetId;
    });
});

/**
 * Load asset data for editing
 * @param {number} assetId - The ID of the asset to edit
 */
function loadAssetData(assetId) {
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we load the asset data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch asset data via AJAX
    $.ajax({
        url: '../ajax/get_asset.php',
        type: 'POST',
        data: {
            asset_id: assetId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const asset = response.data;
                
                // Fill edit form with asset data
                $('#editAssetId').val(asset.id);
                $('#editAssetCode').val(asset.asset_code);
                $('#editAssetName').val(asset.name);
                $('#editAssetDescription').val(asset.description);
                $('#editAssetCategory').val(asset.category_id);
                $('#editAssetLocation').val(asset.location);
                $('#editAssetPurchaseDate').val(asset.purchase_date);
                $('#editAssetWarranty').val(asset.warranty_expiry);
                $('#editAssetStatus').val(asset.status);
                
                // Update CSRF token in the form - ensure it's fresh
                $('#editAssetModal input[name="csrf_token"]').val($('input[name="csrf_token"]').val());
                
                // Reinitialize datepickers for edit form
                $('#editAssetPurchaseDate, #editAssetWarranty').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true,
                    yearRange: 'c-50:c+10'
                });
                
                // Show edit modal
                const editModal = new bootstrap.Modal(document.getElementById('editAssetModal'));
                editModal.show();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to load asset data',
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
 * Delete an asset
 * @param {number} assetId - The ID of the asset to delete
 */
function deleteAsset(assetId) {
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we delete the asset',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: '../ajax/process_asset.php',
        type: 'POST',
        data: {
            action: 'delete',
            asset_id: assetId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                // Reload the table
                $('#assetsTable').DataTable().ajax.reload();
                Swal.fire({
                    title: 'Success!',
                    text: response.message || 'Asset deleted successfully',
                    icon: 'success',
                    confirmButtonColor: '#28a745',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to delete asset',
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