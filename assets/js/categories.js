/**
 * Categories Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log("Categories.js loaded");
    
    // Get CSRF token
    const csrfToken = document.getElementById('csrf_token').value;
    console.log("CSRF token loaded:", csrfToken);
    
    // Track the category ID to be deleted
    let categoryToDelete = null;
    
    // Initialize DataTable
    const categoriesTable = $('#categoriesTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        ajax: {
            url: '../ajax/get_categories.php',
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
                    text: 'Failed to load categories. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                className: 'details-control text-center',
                defaultContent: '<button class="btn btn-link p-0 expand-category" title="Expand"><i class="bi bi-chevron-down"></i></button>',
                width: '40px'
            },
            { data: 'name' },
            { 
                data: 'description',
                render: function(data) {
                    return data ? (data.length > 50 ? data.substring(0, 50) + '...' : data) : '';
                }
            },
            { 
                data: 'subcategory_count',
                render: function(data) {
                    return `<span class="badge bg-info">${data}</span>`;
                }
            },
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
                                    data-bs-toggle="tooltip" title="Edit Category">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Delete Category">
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
            emptyTable: 'No categories found',
            zeroRecords: 'No matching categories found',
            search: "",
            searchPlaceholder: "Search categories...",
            lengthMenu: "_MENU_ records per page",
            info: "Showing _START_ to _END_ of _TOTAL_ categories",
            infoEmpty: "Showing 0 to 0 of 0 categories",
            infoFiltered: "(filtered from _MAX_ total categories)"
        },
        drawCallback: function() {
            // Re-initialize tooltips for newly drawn buttons
            initializeTooltipsPopovers();
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>'
    });
    
    // Expand/collapse logic
    let expandedRow = null;
    let expandedRowIndex = null;
    $('#categoriesTable tbody').on('click', 'button.expand-category', function(e) {
        e.stopPropagation();
        const tr = $(this).closest('tr');
        const row = categoriesTable.row(tr);
        const rowData = row.data();
        const rowIndex = row.index();

        // Collapse any expanded row that is not the current one
        if (expandedRow !== null && expandedRowIndex !== rowIndex) {
            expandedRow.child.hide();
            $(expandedRow.node()).find('button.expand-category i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            expandedRow = null;
            expandedRowIndex = null;
        }

        if (row.child.isShown()) {
            // Collapse
            row.child.hide();
            $(this).find('i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            expandedRow = null;
            expandedRowIndex = null;
        } else {
            // Expand
            $(this).find('i').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            row.child('<div class="subcategory-loading text-center py-3"><div class="spinner-border text-primary"></div></div>').show();
            expandedRow = row;
            expandedRowIndex = rowIndex;
            // Load subcategories via AJAX
            $.ajax({
                url: '../ajax/get_subcategories.php',
                type: 'POST',
                data: {
                    csrf_token: csrfToken,
                    category_id: rowData.id
                },
                success: function(response) {
                    if (response.success) {
                        let content = '<div class="table-responsive"><table class="table table-sm mb-0">';
                        content += '<thead><tr><th>Name</th><th>Description</th><th>Items</th></tr></thead><tbody>';
                        if (response.data && response.data.length > 0) {
                            response.data.forEach(function(subcategory) {
                                content += `<tr>
                                    <td>${subcategory.name}</td>
                                    <td>${subcategory.description || ''}</td>
                                    <td><span class="badge bg-primary">${subcategory.item_count || 0}</span></td>
                                </tr>`;
                            });
                        } else {
                            content += '<tr><td colspan="3" class="text-center text-muted">No subcategories found</td></tr>';
                        }
                        content += '</tbody></table></div>';
                        row.child(content).show();
                    } else {
                        row.child(`<div class="alert alert-danger">${response.message}</div>`).show();
                    }
                },
                error: function() {
                    row.child('<div class="alert alert-danger">Failed to load subcategories</div>').show();
                }
            });
        }
    });
    
    // Apply filters when the filter form is submitted
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        categoriesTable.ajax.reload();
    });
    
    // Clear filters
    $('#filterForm').on('reset', function(e) {
        setTimeout(() => {
            categoriesTable.ajax.reload();
        }, 10);
    });

    // Edit button click handler
    $(document).on('click', '.edit-btn', function() {
        const categoryId = $(this).data('id');
        loadCategoryData(categoryId);
    });

    // Delete button click handler
    $(document).on('click', '.delete-btn', function() {
        const categoryId = $(this).data('id');
        showDeleteConfirmation(categoryId);
    });
        
    /**
     * Load subcategories for a category
     */
    function loadSubcategories(categoryId, categoryName) {
        // Set modal title
        $('#viewSubcategoriesModal .modal-title').text(`Subcategories for "${categoryName}"`);
        
        // Load subcategories content via AJAX
        $.ajax({
            url: '../ajax/get_subcategories.php',
            type: 'POST',
            data: {
                csrf_token: csrfToken,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    let content = '<div class="table-responsive"><table class="table table-sm">';
                    content += '<thead><tr><th>Name</th><th>Description</th><th>Items</th></tr></thead><tbody>';
                    
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(function(subcategory) {
                            content += `<tr>
                                <td>${subcategory.name}</td>
                                <td>${subcategory.description || ''}</td>
                                <td><span class="badge bg-primary">${subcategory.item_count || 0}</span></td>
                            </tr>`;
                        });
                    } else {
                        content += '<tr><td colspan="3" class="text-center text-muted">No subcategories found</td></tr>';
                    }
                    
                    content += '</tbody></table></div>';
                    $('#subcategoriesContent').html(content);
                } else {
                    $('#subcategoriesContent').html(`<div class="alert alert-danger">${response.message}</div>`);
                }
                
                // Show modal
                $('#viewSubcategoriesModal').modal('show');
            },
            error: function() {
                $('#subcategoriesContent').html('<div class="alert alert-danger">Failed to load subcategories</div>');
                $('#viewSubcategoriesModal').modal('show');
            }
        });
    }
    
    /**
     * Load category data for editing
     */
    function loadCategoryData(categoryId) {
        $.ajax({
            url: '../ajax/get_category.php',
            type: 'POST',
            data: {
                csrf_token: csrfToken,
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    // Populate edit form
                    $('#editCategoryId').val(response.data.id);
                    $('#editCategoryName').val(response.data.name);
                    $('#editCategoryDescription').val(response.data.description);
                    
                    // Show edit modal
                    $('#editCategoryModal').modal('show');
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load category data',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }
    
    /**
     * Show delete confirmation
     */
    function showDeleteConfirmation(categoryId) {
        categoryToDelete = categoryId;
        
        showConfirmation({
            title: 'Delete Category',
            text: 'Are you sure you want to delete this category? This action cannot be undone.',
            icon: 'warning',
            confirmCallback: function() {
                deleteCategory(categoryToDelete);
            }
        });
    }

    /**
     * Delete category
     */
    function deleteCategory(categoryId) {
        $.ajax({
            url: '../ajax/process_category.php',
            type: 'POST',
            data: {
                csrf_token: csrfToken,
                action: 'delete',
                category_id: categoryId
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#198754',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    categoriesTable.ajax.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete category',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    }

    /**
     * Show toast notification
     */
    function showToast(title, message, type) {
        // Create toast HTML
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        // Add to toast container
        $('.toast-container').append(toastHtml);
                    
        // Show the toast
        $('.toast-container .toast:last').toast('show');

        // Remove toast after it's hidden
        $('.toast-container .toast:last').on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
}); 