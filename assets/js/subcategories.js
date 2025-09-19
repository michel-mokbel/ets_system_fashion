/**
 * Subcategory administration controller.
 *
 * Responsibilities:
 * - Initialize the subcategory DataTable with parent-category filters and item counts.
 * - Manage add/edit modals, cascading category dropdowns, and delete confirmations via SweetAlert.
 * - Call `../ajax/process_subcategory.php` to persist changes and reload the grid on success.
 *
 * Dependencies:
 * - jQuery, DataTables, SweetAlert, Bootstrap modals.
 * - Backend endpoints `../ajax/get_subcategories.php` and `../ajax/process_subcategory.php`.
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log("Subcategories.js loaded");
    
    // Get CSRF token
    const csrfToken = document.getElementById('csrf_token').value;
    console.log("CSRF token loaded:", csrfToken);
    
    // Track the subcategory ID to be deleted
    let subcategoryToDelete = null;
    
    // Initialize DataTable
    const subcategoriesTable = $('#subcategoriesTable').DataTable({
        processing: true,
        searching: false,
        serverSide: true,
        ajax: {
            url: '../ajax/get_subcategories.php',
            type: 'POST',
            data: function(d) {
                // Add CSRF token
                d.csrf_token = csrfToken;
                
                // Add filter values
                const filterForm = document.getElementById('filterForm');
                if (filterForm) {
                    const formData = new FormData(filterForm);
                    for (const [key, value] of formData.entries()) {
                        d[key] = value;
                    }
                }
                
                return d;
            },
            dataSrc: function(json) {
                console.log("DataTables response:", json);
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error("DataTables error:", error, thrown);
                console.error("Response:", xhr.responseText);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load subcategories. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: 'name' },
            { 
                data: 'description',
                render: function(data) {
                    return data ? (data.length > 50 ? data.substring(0, 50) + '...' : data) : '';
                }
            },
            { data: 'category_name' },
            { 
                data: 'item_count',
                render: function(data) {
                    return `<span class="badge bg-primary">${data}</span>`;
                }
            },
            {
                data: 'created_at_formatted',
                render: function(data) {
                    return data || '';
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary edit-btn" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Subcategory">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Delete Subcategory">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        responsive: true,
        language: {
            processing: '<div class="spinner-border text-primary" role="status"></div>',
            emptyTable: 'No subcategories found',
            zeroRecords: 'No matching subcategories found',
            search: "",
            searchPlaceholder: "Search subcategories...",
            lengthMenu: "_MENU_ records per page",
            info: "Showing _START_ to _END_ of _TOTAL_ subcategories",
            infoEmpty: "Showing 0 to 0 of 0 subcategories",
            infoFiltered: "(filtered from _MAX_ total subcategories)"
        },
        drawCallback: function() {
            // Re-initialize tooltips for newly drawn buttons
            initializeTooltipsPopovers();
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>'
    });

    // Apply filters when the filter form is submitted
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        subcategoriesTable.ajax.reload();
    });

    // Clear filters
    $('#filterForm').on('reset', function(e) {
        setTimeout(() => {
            subcategoriesTable.ajax.reload();
        }, 10);
    });

    // Edit button click handler
    $(document).on('click', '.edit-btn', function() {
        const subcategoryId = $(this).data('id');
        loadSubcategoryData(subcategoryId);
    });

    // Delete button click handler
    $(document).on('click', '.delete-btn', function() {
        const subcategoryId = $(this).data('id');
        showDeleteConfirmation(subcategoryId);
    });

    /**
     * Load subcategory data for editing
     */
    function loadSubcategoryData(subcategoryId) {
        Swal.fire({
            title: 'Loading...',
            html: '<div class="spinner-border text-primary" role="status"></div>',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        $.ajax({
            url: '../ajax/get_subcategory.php',
            type: 'POST',
            data: {
                csrf_token: csrfToken,
                subcategory_id: subcategoryId
            },
            success: function(response) {
                Swal.close();
                if (response.success) {
                    // Populate edit form
                    $('#editSubcategoryId').val(response.data.id);
                    $('#editSubcategoryName').val(response.data.name);
                    $('#editSubcategoryDescription').val(response.data.description);
                    $('#editParentCategory').val(response.data.category_id);
                    
                    // Show edit modal
                    $('#editSubcategoryModal').modal('show');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'Failed to load subcategory data', 'error');
            }
        });
    }

    /**
     * Show delete confirmation
     */
    function showDeleteConfirmation(subcategoryId) {
        subcategoryToDelete = subcategoryId;
        
        Swal.fire({
            title: 'Are you sure?',
            text: 'You are about to delete this subcategory. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteSubcategory(subcategoryToDelete);
            }
        });
    }

    /**
     * Delete subcategory
     */
    function deleteSubcategory(subcategoryId) {
        Swal.fire({
            title: 'Deleting...',
            html: '<div class="spinner-border text-primary" role="status"></div>',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        $.ajax({
            url: '../ajax/process_subcategory.php',
            type: 'POST',
            data: {
                csrf_token: csrfToken,
                action: 'delete',
                subcategory_id: subcategoryId
            },
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire('Success', response.message, 'success');
                    subcategoriesTable.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'Failed to delete subcategory', 'error');
            }
        });
    }
}); 