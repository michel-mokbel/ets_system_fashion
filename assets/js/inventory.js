/**
 * Inventory Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Remove datatable class to prevent double initialization
    $('#inventoryTable').removeClass('datatable');
    
    // Initialize DataTable
    const inventoryTable = $('#inventoryTable').DataTable({
        processing: false,
        serverSide: true,
        responsive: true,
        searching: false,
        ajax: {
            url: '../ajax/get_inventory.php',
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
                
                // Add CSRF token for additional security
                d.csrf_token = $('input[name="csrf_token"]').val();
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables error:', error, thrown);
                Swal.fire('Error', 'Failed to load inventory data. Please refresh the page.', 'error');
            }
        },
        responsive: false,
        columns: [
            {
                data: null,
                orderable: false,
                className: 'details-control text-center',
                defaultContent: '<button class="btn btn-link p-0 expand-inventory" title="Expand"><i class="bi bi-chevron-down"></i></button>',
                width: '40px'
            },
            { data: 'item_code' },
            { data: 'name' },
            { data: 'category' },
            { data: 'container_number' },
            { 
                data: 'stock_status',
                orderable: false
            },
            { 
                data: 'current_stock',
                render: function(data, type, row) {
                    if (parseInt(data) <= parseInt(row.minimum_stock)) {
                        return '<span class="text-danger">' + data + '</span>';
                    }
                    return data;
                }
            },
            { 
                data: 'base_price',
                render: function(data) {
                    return 'CFA ' + parseFloat(data).toFixed(2);
                }
            },
            { 
                data: 'selling_price',
                render: function(data) {
                    return 'CFA ' + parseFloat(data).toFixed(2);
                }
            },
            { 
                data: 'status',
                render: function(data) {
                    const badgeClass = data === 'active' ? 'bg-success' : 'bg-secondary';
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                }
            },
            { 
                data: 'id',
                title: 'Actions',
                orderable: false,
                render: function(data, type, row) {
                    if (window.canEditInventory) {
                        return `
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-info view-details" data-id="${data}" 
                                        data-bs-toggle="tooltip" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-info adjust-stock" data-id="${data}" 
                                        data-bs-toggle="tooltip" title="Adjust Stock">
                                    <i class="bi bi-box-arrow-in-down"></i>
                                </button>
                                <button class="btn btn-sm btn-primary edit-item" data-id="${data}" 
                                        data-bs-toggle="tooltip" title="Edit Item">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-item" data-id="${data}"
                                        data-bs-toggle="tooltip" title="Delete Item">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-info view-details" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        initComplete: function() {
            // Initialize tooltips for action buttons
            initializeTooltipsPopovers();

            // Handle initial viewport setup
            handleViewportChange();
            
            // Ensure pagination is always visible
            $('.pagination-container').show();
        },
        drawCallback: function() {
            // Re-initialize tooltips for newly drawn buttons
            initializeTooltipsPopovers();

            // Handle viewport changes after data redraw
            handleViewportChange();
        },
        language: {
            searchPlaceholder: "Search...",
            search: "",
            lengthMenu: "_MENU_ records per page",
            emptyTable: "No inventory items found",
            zeroRecords: "No matching inventory items found",
            info: "Showing _START_ to _END_ of _TOTAL_ items",
            infoEmpty: "Showing 0 to 0 of 0 items",
            infoFiltered: "(filtered from _MAX_ total items)"
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>',
        pageLength: 10,
        columnDefs: [
            { targets: -1, visible: !!window.canEditInventory }
        ]
    });

    function renderInventoryCards(rows) {
        const container = document.getElementById('inventoryCards');
        if (!container) return;
        container.innerHTML = '';
        if (!rows || rows.length === 0) {
            container.innerHTML = '<div class="p-3 text-center text-muted">No items found</div>';
            return;
        }
        rows.forEach(row => {
            const card = document.createElement('div');
            card.className = 'mobile-table-card';
            // Header: item code + name
            card.innerHTML = `
                <div class="mobile-card-header">
                    <strong>Item Code:</strong> ${escapeHtml(row.item_code || '')}
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Name</span>
                    <span class="mobile-card-value">${escapeHtml(row.name || '')}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Category</span>
                    <span class="mobile-card-value">${escapeHtml(row.category || '-')}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Container</span>
                    <span class="mobile-card-value">${escapeHtml(row.container_number || '-')}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Stock</span>
                    <span class="mobile-card-value">${row.current_stock != null ? row.current_stock : '-'}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Selling</span>
                    <span class="mobile-card-value">CFA ${parseFloat(row.selling_price || 0).toFixed(2)}</span>
                </div>
                <div class="mobile-card-actions d-flex gap-1">
                    <button class="btn btn-sm btn-info view-details" data-id="${row.id}"><i class="bi bi-eye"></i></button>
                    ${window.canEditInventory ? `
                        <button class="btn btn-sm btn-info adjust-stock" data-id="${row.id}"><i class="bi bi-box-arrow-in-down"></i></button>
                        <button class="btn btn-sm btn-primary edit-item" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-danger delete-item" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                    ` : ''}
                </div>
            `;
            container.appendChild(card);
        });
        
        // Add custom pagination below cards for mobile
        if (window.innerWidth <= 767) {
            renderMobilePagination();
        }
    }
    
    function renderMobilePagination() {
        const container = document.getElementById('inventoryCards');
        if (!container) return;
        
        const info = inventoryTable.page.info();
        const totalPages = info.pages;
        const currentPage = info.page;
        
        if (totalPages <= 1) return; // No pagination needed
        
        const paginationDiv = document.createElement('div');
        paginationDiv.className = 'mobile-pagination mt-3 d-flex justify-content-center align-items-center gap-2';
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.className = `btn btn-sm btn-outline-primary ${currentPage === 0 ? 'disabled' : ''}`;
        prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i> Previous';
        prevBtn.onclick = () => {
            if (currentPage > 0) {
                inventoryTable.page(currentPage - 1).draw('page');
            }
        };
        
        // Page info
        const pageInfo = document.createElement('span');
        pageInfo.className = 'text-muted mx-2';
        pageInfo.textContent = `Page ${currentPage + 1} of ${totalPages}`;
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.className = `btn btn-sm btn-outline-primary ${currentPage === totalPages - 1 ? 'disabled' : ''}`;
        nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right"></i>';
        nextBtn.onclick = () => {
            if (currentPage < totalPages - 1) {
                inventoryTable.page(currentPage + 1).draw('page');
            }
        };
        
        paginationDiv.appendChild(prevBtn);
        paginationDiv.appendChild(pageInfo);
        paginationDiv.appendChild(nextBtn);
        container.appendChild(paginationDiv);
    }
    
    // Handle expand/collapse for child rows
    $('#inventoryTable tbody').on('click', 'td.details-control', function() {
        const tr = $(this).closest('tr');
        const row = inventoryTable.row(tr);
        const itemId = row.data().id;
        
        if (row.child.isShown()) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass('shown');
            $(this).find('i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
        } else {
            // Open this row
            row.child(formatStockByStoreRow(itemId)).show();
            tr.addClass('shown');
            $(this).find('i').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            
            // Load stock by store data
            loadStockByStoreData(itemId);
        }
    });

    // Format the child row content
    function formatStockByStoreRow(itemId) {
        return `
            <div class="stock-by-store-details p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Stock by Store</h6>
                    <button class="btn btn-sm btn-primary" onclick="openStoreAssignments(${itemId})">
                        <i class="bi bi-gear"></i> Manage Store Assignments
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="stockByStoreTable_${itemId}">
                        <thead class="table-light">
                            <tr>
                                <th>Store</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    Loading stock data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // Load stock by store data for a specific item
    function loadStockByStoreData(itemId) {
        $.ajax({
            url: '../ajax/get_stock_by_store.php',
            type: 'GET',
            data: { item_id: itemId },
            success: function(response) {
                if (response.success) {
                    renderStockByStoreTable(response.stocks, itemId);
                } else {
                    const tbody = $(`#stockByStoreTable_${itemId} tbody`);
                    tbody.html('<tr><td colspan="4" class="text-center text-danger">Failed to load stock data</td></tr>');
                }
            },
            error: function() {
                const tbody = $(`#stockByStoreTable_${itemId} tbody`);
                tbody.html('<tr><td colspan="4" class="text-center text-danger">Error loading stock data</td></tr>');
            }
        });
    }

    // Render the stock by store table
    function renderStockByStoreTable(stocks, itemId) {
        const tbody = $(`#stockByStoreTable_${itemId} tbody`);
        
        if (!stocks || stocks.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center text-muted">No store assignments found</td></tr>');
            return;
        }
        
        let html = '';
        stocks.forEach(stock => {
            const stockClass = stock.current_stock <= stock.minimum_stock ? 'text-danger' : 'text-success';
            const statusBadge = stock.current_stock <= stock.minimum_stock ? 
                '<span class="badge bg-danger">Low Stock</span>' : 
                '<span class="badge bg-success">In Stock</span>';
            
            html += `
                <tr>
                    <td>${escapeHtml(stock.store_name)}</td>
                    <td class="${stockClass}">${stock.current_stock}</td>
                    <td>${stock.minimum_stock}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
        
        tbody.html(html);
    }

    // Open store assignments modal
    function openStoreAssignments(itemId) {
        // Store the current item ID for the modal
        window.currentItemId = itemId;
        
        // Load current store assignments
        loadCurrentStoreAssignments(itemId);
        
        // Show the modal
        const storeAssignmentsModal = new bootstrap.Modal(document.getElementById('storeAssignmentsModal'));
        storeAssignmentsModal.show();
    }

    // Handle orientation changes and window resizing
    function handleViewportChange() {
        const isMobile = window.innerWidth <= 767;
        const tableContainer = $('#inventoryTable').closest('.table-responsive');
        const cardsContainer = document.getElementById('inventoryCards');
        
        if (isMobile) {
            // Show cards, hide table content
            if (cardsContainer) {
                cardsContainer.style.display = '';
                renderInventoryCards(inventoryTable.rows({ page: 'current' }).data().toArray());
            }
            // Hide the entire table container on mobile
            if (tableContainer.length) tableContainer.hide();
        } else {
            // Show table, hide cards
            if (tableContainer.length) tableContainer.show();
            if (cardsContainer) cardsContainer.style.display = 'none';
        }
    }

    // Listen for orientation changes and window resize
    window.addEventListener('orientationchange', function() {
        // Wait for orientation change to complete
        setTimeout(handleViewportChange, 100);
    });
    
    window.addEventListener('resize', function() {
        // Debounce resize events
        clearTimeout(window.resizeTimer);
        window.resizeTimer = setTimeout(handleViewportChange, 250);
    });

    // Apply filters only when form is submitted
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        console.log('=== FILTER FORM SUBMITTED ===');
        console.log('Form submitted, reloading table...');
        
        // Show loading state on the submit button
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Searching...');
        
        // Reload table
        inventoryTable.ajax.reload(function() {
            // Re-enable button after table reloads
            submitBtn.prop('disabled', false).html(originalText);
        });
    });

    // Remove automatic search on input/change - only search on form submission
    // $('#filterForm input[name="item_code"], #filterForm input[name="name"]').on('input', function() {
    //     clearTimeout(invFilterTimer);
    //     invFilterTimer = setTimeout(() => inventoryTable.ajax.reload(), 350);
    // });
    // $('#filterForm select[name="category"], #filterForm select[name="stock_status"], #filterForm select[name="status"]').on('change', function() {
    //     inventoryTable.ajax.reload();
    // });

    // Add Enter key support for search inputs
    $('#filterForm input[name="item_code"], #filterForm input[name="name"]').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#filterForm').submit();
        }
    });
    
    // Handle form submissions with FormData for image uploads
    $('#addItemModal form, #editItemModal form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const formData = new FormData(this);
        
        // Show loading indicator
        Swal.fire({
            title: 'Processing...',
            html: 'Please wait while we process your request',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Log form data for debugging
        console.log('Form action:', form.attr('action'));
        console.log('Has file input:', form.find('input[type="file"]').length > 0);
        
        // Check if there's a file to upload
        const fileInput = form.find('input[type="file"]')[0];
        if (fileInput && fileInput.files.length > 0) {
            console.log('File selected:', fileInput.files[0].name, 'Size:', fileInput.files[0].size);
        }
        
        // Send AJAX request
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                Swal.close();
                
                console.log('Server response:', response);
                
                if (response.success) {
                    // Show success message
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonColor: '#28a745',
                        timer: 2500,
                        showConfirmButton: false
                    }).then(() => {
                        // Hide modal
                        form.closest('.modal').modal('hide');
                        // Reset form for next entry
                        resetModalForm(form);
                        // Reload table
                        inventoryTable.ajax.reload();
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'An error occurred',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                
                // Show error message
                Swal.fire({
                    title: 'Server Error!',
                    text: 'Failed to process your request. Please check the console for details.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });
    
    /**
     * Reset modal form to initial state
     */
    function resetModalForm(form) {
        // Reset form fields
        form[0].reset();
        
        // Reset validation classes and states
        form.find('.is-valid').removeClass('is-valid');
        form.find('.is-invalid').removeClass('is-invalid');
        form.find('.was-validated').removeClass('was-validated');
        
        // Reset Bootstrap validation classes
        form.find('.form-control').removeClass('is-valid is-invalid');
        form.find('.form-select').removeClass('is-valid is-invalid');
        form.find('.form-check-input').removeClass('is-valid is-invalid');
        
        // Clear all validation feedback messages
        form.find('.valid-feedback').remove();
        form.find('.invalid-feedback').remove();
        
        // Reset image preview
        const imagePreview = form.find('#addItemImagePreview');
        if (imagePreview.length > 0) {
            imagePreview.attr('src', '../assets/img/no-image.png');
        }
        
        // Reset subcategory dropdown
        const subcategorySelect = form.find('#itemSubcategory');
        if (subcategorySelect.length > 0) {
            subcategorySelect.html('<option value="">Select Subcategory</option>');
        }
        
        // Reset file input
        const fileInput = form.find('input[type="file"]');
        if (fileInput.length > 0) {
            fileInput.val('');
        }
        
        // Reset store assignment checkboxes to checked (default state)
        const storeCheckboxes = form.find('input[name="store_assignments[]"]');
        storeCheckboxes.prop('checked', true);
        
        // Remove any custom validation styling
        form.find('input, select, textarea').each(function() {
            $(this).removeClass('is-valid is-invalid');
            $(this).removeAttr('style'); // Remove any inline styles
        });
        
        // Reset Bootstrap 5 validation state completely
        if (form[0].checkValidity) {
            form[0].setCustomValidity('');
        }
        
        // Force re-render of validation states
        setTimeout(() => {
            form.find('.form-control, .form-select').each(function() {
                $(this).removeClass('is-valid is-invalid');
            });
        }, 100);
        
        console.log('Modal form reset successfully');
    }
    
    // Handle edit button click
    $(document).on('click', '.edit-item', function() {
        const itemId = $(this).data('id');
        loadItemData(itemId);
    });
    
    // Reset validation state when add modal is opened
    $('#addItemModal').on('show.bs.modal', function() {
        const form = $(this).find('form');
        // Clear any existing validation state
        form.removeClass('was-validated');
        form.find('.form-control, .form-select').removeClass('is-valid is-invalid');
        form.find('.valid-feedback, .invalid-feedback').remove();
    });
    
    // Reset forms when modals are closed manually
    $('#addItemModal').on('hidden.bs.modal', function() {
        const form = $(this).find('form');
        resetModalForm(form);
        
        // Additional Bootstrap validation reset
        form.removeClass('was-validated');
        form.find('.form-control, .form-select').removeClass('is-valid is-invalid');
    });
    
    $('#editItemModal').on('hidden.bs.modal', function() {
        const form = $(this).find('form');
        // For edit modal, we don't reset everything, just clear validation classes
        form.find('.is-valid').removeClass('is-valid');
        form.find('.is-invalid').removeClass('is-invalid');
        form.removeClass('was-validated');
    });
    
    // Handle view details button click
    $(document).on('click', '.view-details', function() {
        const itemId = $(this).data('id');
        loadItemDetails(itemId);
    });
    
    // Handle edit button click in the details modal
    $(document).on('click', '#detailItemEdit', function() {
        const itemId = $(this).data('id');
        
        // Hide details modal and open edit modal
        $('#itemDetailsModal').modal('hide');
        
        // Small delay to allow the first modal to close
        setTimeout(() => {
            loadItemData(itemId);
        }, 200);
    });
    
    // Adjust stock button click
    $(document).on('click', '.adjust-stock', function() {
        const itemId = $(this).data('id');
        $('#adjustStockItemId').val(itemId);
        $('#adjustStockQuantity').val('');
        $('#adjustStockType').val('in');
        $('#adjustStockNotes').val('');
        const adjustModal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
        adjustModal.show();
    });
    // Handle adjust stock form submit
    $('#adjustStockForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize() + '&csrf_token=' + $('input[name="csrf_token"]').val();
        $.ajax({
            url: '../ajax/process_adjust_stock.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#adjustStockModal').modal('hide');
                    Swal.fire({
                        title: 'Success!',
                        text: 'Stock adjusted successfully!',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    });
                    $('#inventoryTable').DataTable().ajax.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Adjustment failed.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error processing adjustment.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-item', function() {
        const itemId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this item?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deleteItem(itemId);
            }
        });
    });
    
    // Image preview for new item
    $(document).on('change', '#itemImage', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#itemImagePreview').attr('src', e.target.result);
                $('#itemImagePreviewContainer').show();
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Image preview for edit item
    $(document).on('change', '#editItemImage', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#editItemImagePreview').attr('src', e.target.result);
                $('#removeImage').prop('checked', false);
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Handle remove image checkbox
    $(document).on('change', '#removeImage', function() {
        if ($(this).is(':checked')) {
            $('#editItemImagePreview').attr('src', '../assets/img/no-image.png');
            $('#editItemImage').val('');
        } else {
            if ($('#editItemCurrentImage').val()) {
                $('#editItemImagePreview').attr('src', '../' + $('#editItemCurrentImage').val());
            }
        }
    });
    
    // Add custom validation for the stock adjustment form
    $(document).on('change', '#transactionType', function() {
        const transactionType = $(this).val();
        const quantityInput = $('#adjustQuantity');
        
        if (transactionType === 'out') {
            const currentStock = parseInt($('#adjustCurrentStock').text());
            quantityInput.attr('max', currentStock);
        } else {
            quantityInput.removeAttr('max');
        }
    });
    
    // Add custom validation for quantity on stock adjustment form submission
    $('#stockAdjustmentForm').on('submit', function(e) {
        const transactionType = $('#transactionType').val();
        const quantity = parseInt($('#adjustQuantity').val());
        const currentStock = parseInt($('#adjustCurrentStock').text());
        
        if (transactionType === 'out' && quantity > currentStock) {
            e.preventDefault();
            e.stopPropagation();
            
            Swal.fire({
                title: 'Invalid Quantity',
                text: 'Cannot remove more items than current stock',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
            return false;
        }
    });

    // View stock by store
    $(document).on('click', '.view-stock-btn', function() {
        const itemId = $(this).data('id');
        $.ajax({
            url: '../ajax/get_stock_by_store.php',
            type: 'POST',
            data: { item_id: itemId, csrf_token: $('input[name="csrf_token"]').val() },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderStockByStoreTable(response.stocks, itemId);
                    const viewModal = new bootstrap.Modal(document.getElementById('viewStockModal'));
        viewModal.show();
                } else {
                    $('#stockByStoreTable tbody').html('<tr><td colspan="4">No data found</td></tr>');
                }
            },
            error: function() {
                $('#stockByStoreTable tbody').html('<tr><td colspan="4">Error loading stock data</td></tr>');
            }
        });
    });
    function renderStockByStoreTable(stocks, itemId) {
        let html = '';
        stocks.forEach(function(stock) {
            html += `<tr>
                <td>${stock.store_name}</td>
                <td>${stock.current_stock}</td>
                <td>${stock.location || ''}</td>
                <td>
                    <span class="selling-price-value">${parseFloat(stock.selling_price).toFixed(2)}</span>
                    <button class="btn btn-link btn-sm edit-selling-price-btn" data-item-id="${itemId}" data-store="${stock.store_name}" data-price="${parseFloat(stock.selling_price).toFixed(2)}">Edit</button>
                </td>
            </tr>`;
        });
        $('#stockByStoreTable tbody').html(html);
    }

    // DataTables child row logic with single expand and chevron design
    let expandedRow = null;
    let expandedRowIndex = null;
    $('#inventoryTable tbody').on('click', 'button.expand-inventory', function(e) {
        e.stopPropagation();
        var tr = $(this).closest('tr');
        var row = $('#inventoryTable').DataTable().row(tr);
        var rowIndex = row.index();
        // Collapse any expanded row that is not the current one
        if (expandedRow !== null && expandedRowIndex !== rowIndex) {
            expandedRow.child.hide();
            $(expandedRow.node()).find('button.expand-inventory i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            expandedRow = null;
            expandedRowIndex = null;
        }
        if (row.child.isShown()) {
            row.child.hide();
            $(this).find('i').removeClass('bi-chevron-up').addClass('bi-chevron-down');
            expandedRow = null;
            expandedRowIndex = null;
        } else {
            // Show loading spinner while fetching data
            $(this).find('i').removeClass('bi-chevron-down').addClass('bi-chevron-up');
            row.child('<div class="p-3 text-center"><div class="spinner-border text-primary"></div></div>').show();
            expandedRow = row;
            expandedRowIndex = rowIndex;
            const itemId = row.data().id;
            
            // Fetch stock by store only
                $.ajax({
                    url: '../ajax/get_stock_by_store.php',
                    type: 'POST',
                    data: { item_id: itemId, csrf_token: $('input[name="csrf_token"]').val() },
                    dataType: 'json'
            }).done(function(stockResp) {
                let stockHtml = '<h6>Stock by Store</h6>';
                stockHtml += '<div class="table-responsive"><table class="table table-bordered align-middle mb-3"><thead class="table-light"><tr><th>Store</th><th>Current Stock</th><th>Location</th><th>Selling Price</th></tr></thead><tbody>';
                if (stockResp.success && stockResp.stocks.length > 0) {
                    stockResp.stocks.forEach(function(stock) {
                        stockHtml += `<tr><td>${stock.store_name}</td><td>${stock.current_stock}</td><td>${stock.location || ''}</td><td>${parseFloat(stock.selling_price).toFixed(2)}</td></tr>`;
                    });
                } else {
                    stockHtml += '<tr><td colspan="4">No data found</td></tr>';
                }
                stockHtml += '</tbody></table></div>';
                row.child('<div class="p-3">' + stockHtml + '</div>').show();
            }).fail(function() {
                row.child('<div class="p-3 text-danger">Failed to load details.</div>').show();
            });
        }
    });

    // Ensure subcategories load in Add New Item modal
    $('#itemCategory').on('change', function() {
        const catId = $(this).val();
        if (catId) {
            loadSubcategoriesForAdd(catId);
        } else {
            $('#itemSubcategory').html('<option value="">Select Subcategory</option>');
        }
    });
    function loadSubcategoriesForAdd(categoryId) {
        $.ajax({
            url: '../ajax/get_subcategories.php',
            type: 'POST',
            data: { category_id: categoryId, csrf_token: $('input[name="csrf_token"]').val() },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">Select Subcategory</option>';
                    (response.subcategories || []).forEach(function(subcat) {
                        options += `<option value="${subcat.id}">${subcat.name}</option>`;
                    });
                    $('#itemSubcategory').html(options);
                }
            }
        });
    }

    // --- Stock Transfer Modal Logic (Enhanced with Multiple Boxes) ---
    let availableTransferItems = [];
    let transferBoxes = []; // Array to store multiple boxes
    let transferBoxCounter = 1;
    const transferCsrfToken = $('input[name="csrf_token"]').val();

    // Initialize with first box
    initializeFirstTransferBox();

    // Prevent form submission to avoid page refresh
    $('#transferStockForm').on('submit', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Prevent Enter key from submitting form
    $('#transferStockForm input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            return false;
        }
    });

    // Open transfer modal and reset state
    $(document).on('click', '#openTransferStockModal', function() {
        const transferModal = new bootstrap.Modal(document.getElementById('transferStockModal'));
        transferModal.show();
        resetTransferForm();
    });

    // Transfer creation steps - Next to items
    $('#transferNextToItems').on('click', function() {
        const sourceId = 1; // Fixed to warehouse
        const destId = $('#transferDestinationStore').val();
        
        if (!destId) {
            Swal.fire('Error', 'Please select a destination store', 'error');
            return;
        }
        
        if (sourceId === parseInt(destId)) {
            Swal.fire('Error', 'Cannot transfer to the same store', 'error');
            return;
        }
        
        $('#transferStep1').hide();
        $('#transferStep2').show();
        loadTransferSourceItems();
    });

    // Back to stores step
    $('#transferBackToStores').on('click', function() {
        $('#transferStep2').hide();
        $('#transferStep1').show();
    });

    // Enable next button when destination is selected
    $('#transferDestinationStore').on('change', function() {
        $('#transferNextToItems').prop('disabled', $(this).val() === '');
    });

    // Initialize first transfer box
    function initializeFirstTransferBox() {
        transferBoxes = [{
            id: 1,
            label: '',
            items: []
        }];
        updateTransferBoxCounts();
    }

    // Add new transfer box
    $('#addNewTransferBox').on('click', function() {
        transferBoxCounter++;
        const newBox = {
            id: transferBoxCounter,
            label: '',
            items: []
        };
        transferBoxes.push(newBox);
        
        const boxHtml = createTransferBoxHtml(newBox);
        $('#transferBoxesContainer').append(boxHtml);
        updateTransferBoxCounts();
        updateTransferRemoveButtons();
        
        // Re-render available items to show new box in dropdown
        renderTransferAvailableItems();
    });

    // Remove transfer box
    $(document).on('click', '.remove-box', function() {
        const boxId = parseInt($(this).data('box-id'));
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        
        if (boxIndex !== -1) {
            // Return items to available items
            const box = transferBoxes[boxIndex];
            box.items.forEach(item => {
                returnTransferItemToAvailable(item);
            });
            
            // Remove from array and DOM
            transferBoxes.splice(boxIndex, 1);
            $(`.transfer-box[data-box-id="${boxId}"]`).remove();
            
            updateTransferBoxCounts();
            updateTransferRemoveButtons();
            renderTransferAvailableItems();
        }
    });

    // Box label change
    $(document).on('input', '.box-label', function() {
        const boxId = parseInt($(this).closest('.transfer-box').data('box-id'));
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        if (boxIndex !== -1) {
            transferBoxes[boxIndex].label = $(this).val();
            // Re-render available items to show updated label in dropdown
            renderTransferAvailableItems();
        }
    });

    // Create transfer box HTML
    function createTransferBoxHtml(box) {
        return `
            <div class="card mb-3 transfer-box" data-box-id="${box.id}">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <div>
                        <strong>Box #${box.id}</strong>
                        <span class="badge bg-info ms-2 box-item-count">0 items</span>
                    </div>
                    <div>
                        <input type="text" class="form-control form-control-sm d-inline-block box-label" 
                               placeholder="Box label (optional)" style="width: 150px;" value="${box.label}">
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-box" 
                                data-box-id="${box.id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 box-items-table">
                            <thead class="table-light">
                                <tr>
                                    <th width="35%">Item</th>
                                    <th width="15%">Quantity</th>
                                    <th width="15%">Price</th>
                                    <th width="20%">Average Cost</th>
                                    <th width="15%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="empty-box-message">
                                    <td colspan="5" class="text-center text-muted py-3">
                                        <i class="bi bi-box me-2"></i>No items in this box
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    // Update remove buttons visibility
    function updateTransferRemoveButtons() {
        if (transferBoxes.length <= 1) {
            $('.remove-box').hide();
        } else {
            $('.remove-box').show();
        }
    }

    // Load items from source store (warehouse)
    function loadTransferSourceItems() {
        const sourceStoreId = 1; // Fixed to warehouse
        if (!sourceStoreId) return;

        $.ajax({
            url: '../ajax/get_store_inventory.php',
            type: 'POST',
            data: { 
                csrf_token: transferCsrfToken,
                store_id: sourceStoreId,
                category_id: $('#transferCategoryFilter').val(),
                search: $('#transferItemSearch').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    availableTransferItems = response.items;
                    renderTransferAvailableItems();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to load items', 'error');
            }
        });
    }

    // Load source items button handler
    $('#loadTransferSourceItems').on('click', loadTransferSourceItems);

    // Render available transfer items
    function renderTransferAvailableItems() {
        const tbody = $('#transferAvailableItemsTable tbody');
        tbody.empty();

        let availableCount = 0;
        availableTransferItems.forEach(function(item) {
            if (item.current_stock > 0 && !isTransferItemFullyAllocated(item)) {
                availableCount++;
                const remainingStock = getRemainingTransferStock(item);
                const row = `
                    <tr>
                        <td>
                            <strong>${escapeHtml(item.item_name || item.name)}</strong><br>
                            <small class="text-muted">${escapeHtml(item.item_code || item.barcode)}</small>
                        </td>
                        <td><span class="badge bg-secondary">${remainingStock}</span></td>
                        <td>$${parseFloat(item.selling_price).toFixed(2)}</td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown" aria-expanded="false" title="Add to Box">
                                    <i class="bi bi-plus"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    ${transferBoxes.map(box => 
                                        `<li><a class="dropdown-item transfer-add-to-box" href="#" 
                                              data-item-id="${item.item_id || item.id}" 
                                              data-barcode-id="${item.barcode_id}" 
                                              data-box-id="${box.id}">
                                            Add to Box #${box.id} ${box.label ? `(${box.label})` : ''}
                                          </a></li>`
                                    ).join('')}
                                </ul>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            }
        });

        $('#transferAvailableItemsCount').text(`${availableCount} items`);
        
        // Initialize Bootstrap dropdowns after DOM is updated
        setTimeout(function() {
            console.log('Initializing transfer dropdowns for', $('#transferAvailableItemsTable .dropdown-toggle').length, 'elements');
            
            $('#transferAvailableItemsTable .dropdown-toggle').each(function() {
                const element = this;
                console.log('Processing transfer dropdown element:', element);
                
                try {
                    // Destroy existing dropdown instance if it exists
                    if (bootstrap && bootstrap.Dropdown) {
                        const existingDropdown = bootstrap.Dropdown.getInstance(element);
                        if (existingDropdown) {
                            existingDropdown.dispose();
                        }
                        
                        // Create new dropdown instance
                        const newDropdown = new bootstrap.Dropdown(element);
                        console.log('Bootstrap transfer dropdown initialized successfully for:', element);
                    } else {
                        console.warn('Bootstrap Dropdown not available for transfer');
                    }
                } catch (error) {
                    console.error('Bootstrap transfer dropdown initialization failed:', error);
                }
            });
        }, 100);
    }

    // Add item to specific transfer box
    $(document).on('click', '.transfer-add-to-box', function(e) {
        e.preventDefault();
        
        console.log('Transfer add to box clicked'); // Debug log
        
        const itemId = parseInt($(this).data('item-id'));
        const barcodeId = parseInt($(this).data('barcode-id'));
        const boxId = parseInt($(this).data('box-id'));
        
        console.log('Transfer item data:', { itemId, barcodeId, boxId }); // Debug log
        
        const item = availableTransferItems.find(i => (i.item_id || i.id) == itemId && i.barcode_id == barcodeId);
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        
        console.log('Found transfer item:', item, 'Box index:', boxIndex); // Debug log
        
        if (!item || boxIndex === -1) {
            console.error('Transfer item or box not found', { item, boxIndex }); // Debug log
            return;
        }

        const remainingStock = getRemainingTransferStock(item);
        
        console.log('Transfer remaining stock:', remainingStock); // Debug log
        
        Swal.fire({
            title: 'Add to Box #' + boxId,
            html: `
                <p><strong>${item.item_name || item.name}</strong></p>
                <p>Available: <strong>${remainingStock}</strong></p>
                <input type="number" id="quantityInput" class="swal2-input" placeholder="Quantity" 
                       min="1" max="${remainingStock}" value="1">
            `,
            showCancelButton: true,
            confirmButtonText: 'Add to Box',
            preConfirm: () => {
                const quantity = parseInt(document.getElementById('quantityInput').value);
                if (!quantity || quantity < 1 || quantity > remainingStock) {
                    Swal.showValidationMessage('Please enter a valid quantity');
                    return false;
                }
                return quantity;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                console.log('Adding transfer item to box with quantity:', result.value); // Debug log
                addTransferItemToBox(item, boxId, result.value);
            }
        }).catch((error) => {
            console.error('Transfer SweetAlert error:', error); // Debug log
        });
    });

    // Add item to transfer box
    function addTransferItemToBox(item, boxId, quantity) {
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        if (boxIndex === -1) return;

        const boxItem = {
            item_id: item.item_id || item.id,
            barcode_id: item.barcode_id,
            name: item.item_name || item.name,
            item_code: item.item_code || item.barcode,
            quantity: quantity,
            selling_price: item.selling_price,
            unit_cost: item.selling_price * 0.8
        };

        transferBoxes[boxIndex].items.push(boxItem);
        renderTransferBoxItems(boxId);
        renderTransferAvailableItems();
        updateTransferBoxCounts();
    }

    // Render items in a specific transfer box
    function renderTransferBoxItems(boxId) {
        const box = transferBoxes.find(b => b.id === boxId);
        if (!box) return;

        const tbody = $(`.transfer-box[data-box-id="${boxId}"] .box-items-table tbody`);
        tbody.empty();

        if (box.items.length === 0) {
            tbody.append(`
                <tr class="empty-box-message">
                    <td colspan="5" class="text-center text-muted py-3">
                        <i class="bi bi-box me-2"></i>No items in this box
                    </td>
                </tr>
            `);
        } else {
            box.items.forEach((item, itemIndex) => {
                const averageCost = item.average_cost || item.cost_price || 0;
                const row = `
                    <tr>
                        <td>
                            <strong>${escapeHtml(item.name)}</strong><br>
                            <small class="text-muted">${escapeHtml(item.item_code)}</small>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm transfer-quantity-input" 
                                   value="${item.quantity}" min="1" 
                                   data-box-id="${boxId}" data-item-index="${itemIndex}" style="width: 80px;">
                        </td>
                        <td>$${parseFloat(item.selling_price).toFixed(2)}</td>
                        <td>
                                                         <span class="badge bg-warning text-dark" title="Calculated average cost (box cost split per item quantity + base price)">
                                 $${parseFloat(averageCost).toFixed(2)}
                             </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger transfer-remove-item" 
                                    data-box-id="${boxId}" data-item-index="${itemIndex}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            }

        // Update box item count badge
        $(`.transfer-box[data-box-id="${boxId}"] .box-item-count`).text(`${box.items.length} items`);
    }

    // Remove item from transfer box
    $(document).on('click', '.transfer-remove-item', function() {
        const boxId = parseInt($(this).data('box-id'));
        const itemIndex = parseInt($(this).data('item-index'));
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        
        if (boxIndex !== -1 && transferBoxes[boxIndex].items[itemIndex]) {
            transferBoxes[boxIndex].items.splice(itemIndex, 1);
            renderTransferBoxItems(boxId);
            renderTransferAvailableItems();
            updateTransferBoxCounts();
        }
    });

    // Update quantity in transfer box
    $(document).on('input', '.transfer-quantity-input', function() {
        const boxId = parseInt($(this).data('box-id'));
        const itemIndex = parseInt($(this).data('item-index'));
        const quantity = parseInt($(this).val()) || 1;
        const boxIndex = transferBoxes.findIndex(box => box.id === boxId);
        
        if (boxIndex !== -1 && transferBoxes[boxIndex].items[itemIndex]) {
            const item = transferBoxes[boxIndex].items[itemIndex];
            const originalItem = availableTransferItems.find(i => 
                (i.item_id || i.id) == item.item_id && i.barcode_id == item.barcode_id
            );
            
            if (originalItem) {
                const maxAvailable = getRemainingTransferStock(originalItem, boxId, itemIndex);
                if (quantity > maxAvailable) {
                    $(this).val(maxAvailable);
                    transferBoxes[boxIndex].items[itemIndex].quantity = maxAvailable;
                } else {
                    transferBoxes[boxIndex].items[itemIndex].quantity = Math.max(1, quantity);
                }
                renderTransferAvailableItems();
            }
        }
    });

    // Helper functions for transfer boxes
    function isTransferItemFullyAllocated(item) {
        const totalAllocated = transferBoxes.reduce((total, box) => {
            return total + box.items.reduce((boxTotal, boxItem) => {
                if ((boxItem.item_id === (item.item_id || item.id)) && 
                    (boxItem.barcode_id === item.barcode_id)) {
                    return boxTotal + boxItem.quantity;
                }
                return boxTotal;
            }, 0);
        }, 0);
        
        return totalAllocated >= item.current_stock;
    }

    function getRemainingTransferStock(item, excludeBoxId = null, excludeItemIndex = null) {
        let totalAllocated = 0;
        
        transferBoxes.forEach((box, boxIndex) => {
            box.items.forEach((boxItem, itemIndex) => {
                if (excludeBoxId !== null && box.id === excludeBoxId && itemIndex === excludeItemIndex) {
                    return; // Skip this item when calculating remaining stock for quantity update
                }
                
                if ((boxItem.item_id === (item.item_id || item.id)) && 
                    (boxItem.barcode_id === item.barcode_id)) {
                    totalAllocated += boxItem.quantity;
                }
            });
        });
        
        return item.current_stock - totalAllocated;
    }

    function returnTransferItemToAvailable(boxItem) {
        // This function is called when removing items from boxes
        // The renderTransferAvailableItems() function will automatically show available quantities
    }

    // Update transfer box counts
    function updateTransferBoxCounts() {
        const totalItems = transferBoxes.reduce((total, box) => total + box.items.length, 0);
        $('#transferTotalBoxesCount').text(transferBoxes.length);
        $('#transferTotalItemsCount').text(totalItems);
        
        // Enable/disable buttons
        const hasItems = totalItems > 0;
        $('#createTransferShipment').prop('disabled', !hasItems);
        $('#transferPrintPackingSlips').prop('disabled', !hasItems);
    }

    // Transfer print functionality
    $('#transferPreviewPrint').on('click', function() {
        if (transferBoxes.every(box => box.items.length === 0)) {
            Swal.fire('Warning', 'No items to print. Please add items to boxes first.', 'warning');
            return;
        }
        showTransferPrintPreview();
    });

    $('#transferPrintPackingSlips').on('click', function() {
        if (transferBoxes.every(box => box.items.length === 0)) {
            Swal.fire('Warning', 'No items to print. Please add items to boxes first.', 'warning');
            return;
        }
        showTransferPrintPreview();
    });

    function showTransferPrintPreview() {
        const sourceStore = "Main Warehouse";
        const destStore = $('#transferDestinationStore option:selected').text();
        
        $('#transferPrintRoute').text(`${sourceStore} → ${destStore}`);
        
        const container = $('#transferPackingSlipsContainer');
        container.empty();

        transferBoxes.forEach(box => {
            if (box.items.length > 0) {
                const packingSlip = generateTransferPackingSlip(box, sourceStore, destStore);
                container.append(packingSlip);
            }
        });

        const printModal = new bootstrap.Modal(document.getElementById('transferPrintPackingModal'));
        printModal.show();
    }

    function generateTransferPackingSlip(box, sourceStore, destStore) {
        const totalItems = box.items.reduce((sum, item) => sum + item.quantity, 0);
        const totalValue = box.items.reduce((sum, item) => sum + (item.quantity * item.selling_price), 0);

        return `
            <div class="packing-slip mb-4 p-4 border" style="page-break-after: always;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4>PACKING SLIP</h4>
                        <p class="mb-0"><strong>Box #${box.id}</strong></p>
                        ${box.label ? `<p class="mb-0 text-muted">${box.label}</p>` : ''}
                    </div>
                    <div class="text-end">
                        <p class="mb-0"><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                        <p class="mb-0"><strong>Time:</strong> ${new Date().toLocaleTimeString()}</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>FROM:</h6>
                        <p class="mb-0">${sourceStore}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>TO:</h6>
                        <p class="mb-0">${destStore}</p>
                    </div>
                </div>
                
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th>Code</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${box.items.map(item => `
                            <tr>
                                <td>${escapeHtml(item.name)}</td>
                                <td>${escapeHtml(item.item_code)}</td>
                                <td>${item.quantity}</td>
                                <td>$${parseFloat(item.selling_price).toFixed(2)}</td>
                                <td>$${(item.quantity * item.selling_price).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2">TOTALS:</th>
                            <th>${totalItems}</th>
                            <th>-</th>
                            <th>$${totalValue.toFixed(2)}</th>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Packed by:</strong> _________________</p>
                            <p><strong>Date:</strong> _________________</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Received by:</strong> _________________</p>
                            <p><strong>Date:</strong> _________________</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Print all transfer slips
    $('#transferPrintAllSlips').on('click', function() {
        const printContent = $('#transferPackingSlipsContainer').html();
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Transfer Packing Slips</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    @media print {
                        .packing-slip { page-break-after: always; }
                        .packing-slip:last-child { page-break-after: avoid; }
                    }
                    body { font-size: 12px; }
                    .table-sm th, .table-sm td { padding: 0.25rem; }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        printWindow.print();
    });

    // Create transfer with multiple boxes
    $('#createTransferShipment').on('click', function() {
        const boxesWithItems = transferBoxes.filter(box => box.items.length > 0);
        
        if (boxesWithItems.length === 0) {
            Swal.fire('Error', 'Please add items to at least one box', 'error');
            return;
        }

        // Show loading indicator
        Swal.fire({
            title: 'Creating Transfer...',
            text: `Creating transfer with ${boxesWithItems.length} box(es)`,
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Disable the create button to prevent multiple submissions
        $('#createTransferShipment').prop('disabled', true);

        // Create one transfer with all boxes
        const formData = {
            csrf_token: transferCsrfToken,
            action: 'create_shipment',
            source_store_id: 1, // Fixed to warehouse
            destination_store_id: $('#transferDestinationStore').val(),
            notes: $('textarea[name="notes"]').val(),
            boxes: JSON.stringify(boxesWithItems)
        };

        $.ajax({
            url: '../ajax/process_transfer_shipment.php',
            type: 'POST',
            data: formData,
            dataType: 'json'
        }).done(function(response) {
                if (response.success) {
                Swal.fire({
                    title: 'Success!',
                    html: `
                        <div class="text-center">
                            <p><strong>Transfer Created Successfully!</strong></p>
                            <p>Shipment Number: <strong>${response.shipment_number}</strong></p>
                            <p>Total Boxes: <strong>${response.total_boxes}</strong></p>
                            <p>Total Items: <strong>${response.total_items}</strong></p>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'Create Another Transfer'
                }).then(() => {
                    $('#transferStockModal').modal('hide');
                    
                    // Reload inventory table if it exists
                    if (typeof inventoryTable !== 'undefined' && inventoryTable.ajax) {
                        inventoryTable.ajax.reload();
                    }
                    
                    resetTransferForm();
                });
                } else {
                Swal.fire('Error', response.message, 'error');
            }
        }).fail(function(xhr, status, error) {
            console.error('Transfer creation failed:', {xhr, status, error});
            Swal.fire('Error', 'Failed to create transfer. Please try again.', 'error');
        }).always(function() {
            // Re-enable the button
            $('#createTransferShipment').prop('disabled', false);
        });
    });

    // Reset transfer form
    function resetTransferForm() {
        $('#transferStep2').hide();
        $('#transferStep1').show();
        $('#transferStockForm')[0].reset();
        $('#transferDestinationStore').val('');
        $('textarea[name="notes"]').val('');
        $('#transferNextToItems').prop('disabled', true);
        availableTransferItems = [];
        transferBoxCounter = 1;
        initializeFirstTransferBox();
        $('#transferBoxesContainer').html($('.transfer-box[data-box-id="1"]')[0].outerHTML);
        $('#transferAvailableItemsTable tbody').html('<tr><td colspan="4" class="text-center text-muted py-3">Click "Load Items" to view available items</td></tr>');
        updateTransferBoxCounts();
        updateTransferRemoveButtons();
    }

    // Search functionality (same as transfers.php)
    $('#transferItemSearch').on('input', function() {
        loadTransferSourceItems();
    });

    $('#transferCategoryFilter').on('change', function() {
        loadTransferSourceItems();
    });
    
    // Utility Functions
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // --- Incoming Stock Modal Logic ---
    let incomingStockRowIdx = 0;
    function createIncomingStockRow(idx, items) {
        return `
        <div class="row g-2 align-items-end incoming-stock-row" data-row="${idx}">
            <div class="col-md-6">
                <label class="form-label">Item</label>
                <select class="form-select incoming-item-select" name="item_id_${idx}" required style="width:100%"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantity</label>
                <input type="number" class="form-control incoming-qty-input" name="quantity_${idx}" min="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Notes</label>
                <input type="text" class="form-control incoming-notes-input" name="notes_${idx}">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm remove-incoming-row" title="Remove"><i class="bi bi-x"></i></button>
            </div>
        </div>`;
    }

    let allInventoryItems = [];
    function loadAllInventoryItems(callback) {
        if (allInventoryItems.length > 0) { callback(allInventoryItems); return; }
        $.ajax({
            url: '../ajax/get_inventory.php',
            type: 'POST',
            data: { csrf_token: $('input[name="csrf_token"]').val(), length: 1000, start: 0 },
            dataType: 'json',
            success: function(response) {
                if (response.data && response.data.length > 0) {
                    allInventoryItems = response.data;
                    callback(allInventoryItems);
                }
            }
        });
    }

    function addIncomingStockRow() {
        loadAllInventoryItems(function(items) {
            const idx = incomingStockRowIdx++;
            $('#incomingStockRows').append(createIncomingStockRow(idx, items));
            const $select = $(`.incoming-stock-row[data-row="${idx}"] .incoming-item-select`);
            $select.append('<option value="">Select Item</option>');
            items.forEach(function(item) {
                $select.append(`<option value="${item.id}">${item.name} (${item.item_code})</option>`);
            });
            // Optionally, use select2 for search
            if ($.fn.select2) $select.select2({ dropdownParent: $('#incomingStockModal') });
        });
    }

    $('#openIncomingStockModal').on('click', function() {
        $('#incomingStockRows').empty();
        incomingStockRowIdx = 0;
        addIncomingStockRow();
        const incomingModal = new bootstrap.Modal(document.getElementById('incomingStockModal'));
        incomingModal.show();
    });

    $('#addIncomingStockRow').on('click', function() {
        addIncomingStockRow();
    });

    $(document).on('click', '.remove-incoming-row', function() {
        $(this).closest('.incoming-stock-row').remove();
    });

    $('#incomingStockForm').on('submit', function(e) {
        e.preventDefault();
        let items = [];
        let valid = true;
        $('#incomingStockRows .incoming-stock-row').each(function() {
            const $row = $(this);
            const item_id = $row.find('.incoming-item-select').val();
            const quantity = $row.find('.incoming-qty-input').val();
            const notes = $row.find('.incoming-notes-input').val();
            if (!item_id || !quantity || quantity <= 0) {
                valid = false;
                $row.find('input,select').addClass('is-invalid');
            } else {
                $row.find('input,select').removeClass('is-invalid');
                items.push({ item_id: item_id, quantity: quantity, notes: notes });
            }
        });
        if (!valid || items.length === 0) return;
        // Submit batch incoming
        $.ajax({
            url: '../ajax/process_adjust_stock.php',
            type: 'POST',
            data: {
                action: 'batch_incoming',
                items: JSON.stringify(items),
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#incomingStockModal').modal('hide');
                    Swal.fire('Success', 'Incoming stock updated!', 'success');
                    $('#inventoryTable').DataTable().ajax.reload();
                } else {
                    Swal.fire('Error', 'Some items failed: ' + (response.results ? response.results.filter(r=>!r.success).map(r=>r.message).join(', ') : response.message), 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server error while saving incoming stock.', 'error');
            }
        });
    });
});

function loadItemData(itemId) {
    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    $.ajax({
        url: '../ajax/get_item.php',
        type: 'POST',
        data: {
            item_id: itemId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                const item = response.data;
                $('#editItemId').val(item.id);
                $('#editItemCode').val(item.item_code);
                $('#editItemName').val(item.name);
                $('#editItemDescription').val(item.description);
                $('#editItemCategory').val(item.category_id || '');
                $('#editItemSubcategory').val(item.subcategory_id || '');
                $('#editItemBasePrice').val(item.base_price);
                $('#editItemSellingPrice').val(item.selling_price || item.base_price || '');
                $('#editItemSize').val(item.size || '');
                $('#editItemColor').val(item.color || '');
                                  $('#editItemMaterial').val(item.material || '');
                  $('#editItemBrand').val(item.brand || '');
                  $('#editItemContainer').val(item.container_id || '');
                  $('#editItemStatus').val(item.status);
                  $('#editItemCurrentImage').val(item.image_path || '');
                if (item.image_path) {
                    $('#editItemImagePreview').attr('src', '../' + item.image_path);
                    $('#itemImagePreviewContainer').show();
                } else {
                    $('#editItemImagePreview').attr('src', '../assets/img/no-image.png');
                    $('#itemImagePreviewContainer').hide();
                }
                if (item.category_id) {
                    loadSubcategories(item.category_id, item.subcategory_id);
                }
                
                // Handle weekly pricing in edit modal
                if (item.has_weekly_pricing && item.weekly_prices) {
                    $('#editWeeklyPricingToggle').prop('checked', true);
                    $('#editWeeklyStoreSelect').val(item.weekly_store_id);
                    
                    // Populate weekly prices
                    for (let d = 0; d < 7; d++) {
                        const price = item.weekly_prices[d] || '';
                        $(`input[name="weekly_price_${d}"]`).val(price);
                    }
                    
                    // Show weekly pricing containers
                    $('#editWeeklyStoreContainer').show();
                    $('#editWeeklyPricesContainer').show();
                } else {
                    $('#editWeeklyPricingToggle').prop('checked', false);
                    $('#editWeeklyStoreContainer').hide();
                    $('#editWeeklyPricesContainer').hide();
                    
                    // Clear weekly prices
                    for (let d = 0; d < 7; d++) {
                        $(`input[name="weekly_price_${d}"]`).val('');
                    }
                }
                
                const editModal = new bootstrap.Modal(document.getElementById('editItemModal'));
        editModal.show();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message || 'Failed to load item details',
                    icon: 'error'
                });
            }
        },
        error: function() {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'Failed to connect to the server',
                icon: 'error'
            });
        }
    });
}

function loadSubcategories(categoryId, selectedSubcategoryId) {
    $.ajax({
        url: '../ajax/get_subcategories.php',
        type: 'POST',
        data: {
            category_id: categoryId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let options = '<option value="">Select Subcategory</option>';
                (response.subcategories || response.data || []).forEach(function(subcat) {
                    const selected = subcat.id == selectedSubcategoryId ? 'selected' : '';
                    options += `<option value="${subcat.id}" ${selected}>${subcat.name}</option>`;
                });
                $('#editItemSubcategory').html(options);
            }
        }
    });
}

function loadItemDetails(itemId) {
    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    $.ajax({
        url: '../ajax/get_item.php',
        type: 'POST',
        data: {
            item_id: itemId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            if (response.success) {
                const item = response.data;
                $('#detailItemName').text(item.name);
                $('#detailItemCode').text(item.item_code);
                $('#detailItemCategory').text(item.category_name || '');
                $('#detailItemSubcategory').text(item.subcategory_name || '');
                $('#detailItemDescription').text(item.description || '');
                $('#detailItemBasePrice').text(item.base_price_formatted ? 'CFA ' + item.base_price_formatted : '');
                $('#detailItemStock').text(item.total_stock !== undefined ? item.total_stock : '');
                $('#detailItemSellingPrice').text(item.selling_price ? 'CFA ' + parseFloat(item.selling_price).toFixed(2) : '');
                $('#detailItemStatus').text(item.status);
                $('#detailItemImage').attr('src', item.image_path ? '../' + item.image_path : '../assets/img/no-image.png');
                $('#detailItemEdit').data('id', item.id);
                
                // Handle weekly pricing display
                if (item.has_weekly_pricing && item.weekly_prices) {
                    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    let weeklyHtml = '<div class="row g-1">';
                    for (let d = 0; d < 7; d++) {
                        const price = item.weekly_prices[d] || 0;
                        weeklyHtml += `<div class="col-6 col-md-3"><small class="text-muted">${days[d]}:</small> ${parseFloat(price).toFixed(2)}</div>`;
                    }
                    weeklyHtml += '</div>';
                    $('#detailWeeklyPricing').html(weeklyHtml);
                    $('#detailWeeklyPricingRow').show();
                } else {
                    $('#detailWeeklyPricingRow').hide();
                }
                // Barcode logic
                if (item.barcodes_list && item.barcodes_list.length > 0 && item.barcodes_list[0]) {
                    $('#detailItemBarcode').text(item.barcodes_list[0]);
                    $('#openBarcodeBtn').show().off('click').on('click', function() {
                        window.open(`barcode.php?code=${encodeURIComponent(item.item_code)}`, '_blank');
                    });
                } else {
                    $('#detailItemBarcode').text('N/A');
                    $('#openBarcodeBtn').hide();
                }
                const detailsModal = new bootstrap.Modal(document.getElementById('itemDetailsModal'));
        detailsModal.show();
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message || 'Failed to load item details',
                    icon: 'error'
                });
            }
        },
        error: function() {
            Swal.close();
            Swal.fire({
                title: 'Error',
                text: 'Failed to connect to the server',
                icon: 'error'
            });
        }
    });
}

function deleteItem(itemId) {
    $.ajax({
        url: '../ajax/process_inventory.php',
        type: 'POST',
        data: {
            action: 'delete',
            item_id: itemId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire('Deleted!', response.message || 'Item deleted successfully.', 'success');
                $('#inventoryTable').DataTable().ajax.reload();
            } else {
                Swal.fire('Error', response.message || 'Failed to delete item.', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Server error while deleting item.', 'error');
        }
    });
}