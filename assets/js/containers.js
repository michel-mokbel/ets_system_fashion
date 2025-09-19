/**
 * Simplified Container Management JavaScript
 * Single bulk mode with total weight and total price only
 */

document.addEventListener('DOMContentLoaded', function() {
    // Check if modals exist for debugging
    if ($('#addItemDuringCreationModal').length === 0) {
        console.warn('addItemDuringCreationModal not found');
    }
    
    // Initialize modals
    $(document).ready(function() {
        // Ensure all modals are properly initialized
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modalElement => {
            if (!modalElement.classList.contains('bs-modal')) {
                new bootstrap.Modal(modalElement);
            }
        });
        console.log('Modals initialized:', modals.length);
        
        // Load existing inventory codes for duplicate checking
        loadExistingInventoryCodes();
        
        // Add real-time duplicate code checking for new items
        $('#newItemCode').on('input', function() {
            const code = $(this).val().trim();
            const $feedback = $('.duplicate-code-feedback');
            
            if (code && isDuplicateItemCode(code)) {
                $feedback.show();
                $(this).addClass('is-invalid');
            } else {
                $feedback.hide();
                $(this).removeClass('is-invalid');
            }
        });
        
        // Also prevent form submission if duplicate code exists
        $('#addNewItemModal form').on('submit', function(e) {
            const code = $('#newItemCode').val().trim();
            
            // If inventory codes haven't loaded yet, show error
            if (!window.existingInventoryCodes) {
                e.preventDefault();
                Swal.fire('Error', 'Inventory data is still loading. Please wait a moment and try again.', 'error');
                return false;
            }
            
            if (code && isDuplicateItemCode(code)) {
                e.preventDefault();
                Swal.fire('Error', `Item code "${code}" already exists in the container or inventory. Please use a unique code.`, 'error');
                return false;
            }
        });
        
        // Debug: Add a test button to check if codes are loaded
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.log('Development mode: Adding debug button');
            setTimeout(() => {
                if ($('#addNewItemModal').length > 0) {
                    $('#addNewItemModal .modal-body').prepend(`
                        <div class="alert alert-info mb-3">
                            <button type="button" class="btn btn-sm btn-info" onclick="testDuplicateCheck()">Test Duplicate Check</button>
                            <small class="ms-2">Debug: Check if inventory codes are loaded</small>
                        </div>
                    `);
                }
            }, 1000);
        }
        
        // Clear duplicate code feedback when new item modal is shown
        $('#addNewItemModal').on('show.bs.modal', function() {
            $('.duplicate-code-feedback').hide();
            $('#newItemCode').removeClass('is-invalid');
        });
        
        // Also clear feedback when add item buttons are clicked in manage items modal
        $(document).on('click', '.add-item', function() {
            if ($(this).data('item-type') === 'new_item') {
                setTimeout(() => {
                    $('.duplicate-code-feedback').hide();
                    $('#newItemCode').removeClass('is-invalid');
                }, 100);
            }
        });
    });
    
    // Initialize DataTable
    const containersTable = $('#containersTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        searching: false,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        pagingType: 'full_numbers',
        ajax: {
            url: '../ajax/get_containers.php',
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
                Swal.fire('Error', 'Failed to load container data. Please refresh the page.', 'error');
            }
        },
        columns: [
            { data: 'container_number' },
            { data: 'supplier_name' },
            { 
                data: 'total_weight_kg',
                render: function(data) {
                    return parseFloat(data).toFixed(2) + ' KG';
                }
            },
            { 
                data: 'total_price',
                render: function(data) {
                    return 'CFA ' + parseFloat(data || 0).toFixed(2);
                }
            },
            { 
                data: 'amount_paid',
                render: function(data) {
                    return 'CFA ' + parseFloat(data).toFixed(2);
                }
            },
            { 
                data: 'remaining_balance',
                render: function(data) {
                    const balance = parseFloat(data);
                    const color = balance > 0 ? 'text-danger' : 'text-success';
                    return '<span class="' + color + '">CFA ' + balance.toFixed(2) + '</span>';
                }
            },
            { 
                data: 'actual_profit',
                render: function(data, type, row) {
                    const actualProfit = parseFloat(data || 0);
                    const totalCosts = parseFloat(row.total_all_costs || 0);
                    
                    // Calculate ROI based on actual profit
                    const roi = (totalCosts > 0 && actualProfit > 0) ? (actualProfit / totalCosts * 100) : 0;
                    
                    const color = actualProfit > 0 ? 'text-success' : actualProfit < 0 ? 'text-danger' : 'text-muted';
                    const displayText = actualProfit === 0 ? 'Not Set' : `CFA ${actualProfit.toFixed(2)}`;
                    
                    return `<span class="${color}">${displayText}
                            ${actualProfit > 0 ? `<small class="d-block">(${roi.toFixed(1)}% ROI)</small>` : ''}
                            <small class="text-muted d-block">Manual Entry</small></span>`;
                }
            },
            { 
                data: 'arrival_date',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString() : '';
                }
            },
            {
                data: 'status',
                render: function(data) {
                    let badgeClass = 'bg-secondary';
                    switch(data) {
                        case 'pending': badgeClass = 'bg-warning'; break;
                        case 'received': badgeClass = 'bg-info'; break;
                        case 'processed': badgeClass = 'bg-primary'; break;
                        case 'completed': badgeClass = 'bg-success'; break;
                    }
                    return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    let actions = `
                        <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary edit-container" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Container">
                                <i class="bi bi-pencil"></i>
                            </button>`;
                    
                    // Manage Items button (for pending and received containers)
                    if (row.status === 'pending' || row.status === 'received') {
                        actions += `
                            <button class="btn btn-sm btn-outline-success manage-items" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Manage Items">
                                <i class="bi bi-box-seam"></i>
                            </button>`;
                    }
                    
                    // Status action button
                    if (row.status === 'pending') {
                        actions += `
                            <button class="btn btn-sm btn-warning status-action-btn" data-id="${data}" data-status="pending" 
                                    data-bs-toggle="tooltip" title="Mark as Received">
                                <i class="bi bi-truck"></i>
                            </button>`;
                    } else if (row.status === 'received') {
                        actions += `
                            <button class="btn btn-sm btn-info process-container" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Process Container">
                                <i class="bi bi-gear"></i>
                            </button>`;
                    }
                    
                    if (row.status === 'pending') {
                        actions += `
                            <button class="btn btn-sm btn-outline-danger delete-container" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Delete Container">
                                <i class="bi bi-trash"></i>
                            </button>`;
                    }
                    actions += `</div>`;
                    return actions;
                }
            }
        ],
        initComplete: function() {
            initializeTooltipsPopovers();
            // Apply Bootstrap classes to pagination
            applyBootstrapPagination();
        },
        drawCallback: function() {
            initializeTooltipsPopovers();
            // Apply Bootstrap classes to pagination after each draw
            applyBootstrapPagination();
        },
        language: {
            searchPlaceholder: "Search containers...",
            search: "",
            lengthMenu: "_MENU_ containers per page",
            emptyTable: "No containers found",
            zeroRecords: "No matching containers found",
            info: "Showing _START_ to _END_ of _TOTAL_ containers",
            infoEmpty: "Showing 0 to 0 of 0 containers",
            infoFiltered: "(filtered from _MAX_ total containers)"
        }
    });

    // Real-time cost calculations for add modal
    $('#totalWeight, #totalPrice, #shipmentCost, #profitMargin, #amountPaid').on('input', function() {
        recalculateAll();
    });

    // Add Item button during container creation
    $('#addItemDuringCreationBtn').on('click', function() {
        openAddItemDuringCreation();
    });

    // Load warehouse boxes when add box modal is shown
    $('#addBoxItemModal').on('show.bs.modal', function() {
        loadWarehouseBoxes();
        // Reset form and show selection buttons
        resetBoxModal();
    });

    // Load existing items when add existing item modal is shown
    $('#addExistingItemModal').on('show.bs.modal', function() {
        loadExistingItems();
    });

    // Show box information when warehouse box is selected
    $(document).on('change', '#warehouseBoxId', function() {
        const $option = $(this).find('option:selected');
        const boxQuantity = $option.data('available') || 0;
        
        // Display box quantity (this is not a stock limit, just informational)
        $('#boxAvailableQuantity').text(boxQuantity);
        
        // No validation needed - box quantity doesn't limit container additions
        // The quantity field is for how many of this box to add to the container
        // Box quantities are only updated after container processing
    });
    
    // Box selection buttons
    $(document).on('click', '#selectExistingBoxBtn', function() {
        showExistingBoxSection();
    });
    
    $(document).on('click', '#createNewBoxBtn', function() {
        showNewBoxSection();
    });

    // Calculate total cost for new items
    $(document).on('input', '#newItemQuantity, #newItemUnitCost', function() {
        const quantity = parseInt($('#newItemQuantity').val()) || 0;
        const unitCost = parseFloat($('#newItemUnitCost').val()) || 0;
        const total = quantity * unitCost;
        $('#newItemTotalCost').text('CFA ' + total.toFixed(2));
    });

    // Item type selection during container creation
    $(document).on('click', '.add-item-during-creation', function() {
        const itemType = $(this).data('item-type');
        openAddItemDuringCreationModal(itemType);
    });

    // Remove item during container creation
    $(document).on('click', '.remove-item-during-creation', function() {
        const itemId = $(this).data('item-id');
        removeItemDuringCreation(itemId);
    });

    // Real-time cost calculations for edit modal
    $('#editTotalWeight, #editTotalPrice, #editShipmentCost, #editProfitMargin, #editAmountPaid').on('input', function() {
        recalculateAllEdit();
    });

    // Event handlers
    $(document).on('click', '.edit-container', function() {
        const containerId = $(this).data('id');
        loadContainerData(containerId);
    });

    $(document).on('click', '.delete-container', function() {
        const containerId = $(this).data('id');
        confirmDelete(containerId);
    });

    $(document).on('click', '.process-container', function() {
        const containerId = $(this).data('id');
        openProcessModal(containerId);
    });

    $(document).on('click', '.financial-summary', function() {
        const containerId = $(this).data('id');
        openFinancialSummary(containerId);
    });

    $(document).on('click', '.manage-items', function() {
        const containerId = $(this).data('id');
        openManageItems(containerId);
    });

    // Item management event handlers - using event delegation for dynamically created elements
    $(document).on('click', '.add-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('=== ADD ITEM TYPE BUTTON CLICKED ===');
        
        // Find which modal we're in
        const $modal = $(this).closest('.modal');
        const modalId = $modal.attr('id');
        console.log('Modal ID:', modalId);
        
        const itemType = $(this).data('item-type');
        console.log('Add item clicked:', { modalId, itemType });
        console.log('Button data:', $(this).data());
        
        if (modalId === 'addContainerModal') {
            // We're in the create container modal - open the appropriate add item modal
            console.log('Opening add item modal for container creation');
            openAddItemDuringCreationModal(itemType);
        } else if (modalId === 'manageItemsModal') {
            // We're in the manage items modal - need container ID
            const containerId = $modal.data('container-id');
            if (!containerId) {
                console.error('No container ID found in modal data!');
                Swal.fire('Error', 'Container ID not found. Please refresh and try again.', 'error');
                return;
            }
            console.log('Opening add item modal for existing container:', containerId);
            openAddItemModal(containerId, itemType);
        } else {
            console.error('Unknown modal context:', modalId);
            Swal.fire('Error', 'Unknown modal context. Please refresh and try again.', 'error');
            return;
        }
    });

    // Edit item handler moved to the end of the file to avoid conflicts

    $(document).on('click', '.remove-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('=== REMOVE ITEM BUTTON CLICKED ===');
        console.log('Button element:', this);
        console.log('Button HTML:', $(this).prop('outerHTML'));
        
        const itemId = $(this).data('item-id');
        
        // Find the closest modal to get the container ID
        const $modal = $(this).closest('.modal');
        let containerId = null;
        
        if ($modal.attr('id') === 'manageItemsModal') {
            containerId = $modal.data('container-id');
        } else {
            console.error('Remove item button clicked outside manage items modal');
            return;
        }
        
        console.log('Item ID:', itemId);
        console.log('Container ID:', containerId);
        console.log('Modal ID:', $modal.attr('id'));
        
        if (!itemId) {
            console.error('No item ID found in button data');
            return;
        }
        
        if (!containerId) {
            console.error('No container ID found in modal data');
            return;
        }
        
        confirmRemoveItem(containerId, itemId);
    });

    // Add Item button click handler - works for both create container and manage items modals
    $(document).on('click', '#addItemBtn', function() {
        console.log('=== ADD ITEM BUTTON CLICKED ===');
        
        // Find which modal we're in
        const $modal = $(this).closest('.modal');
        const modalId = $modal.attr('id');
        console.log('Modal ID:', modalId);
        
        // Get the add item buttons div within this modal
        const $addItemButtons = $modal.find('#addItemButtons');
        console.log('Add Item Buttons div:', $addItemButtons);
        console.log('Add Item Buttons visibility before:', $addItemButtons.is(':visible'));
        
        // Toggle the add item buttons
        if ($addItemButtons.is(':visible')) {
            $addItemButtons.hide();
            console.log('Hiding add item buttons');
        } else {
            // Show the buttons
            $addItemButtons.show();
            console.log('Showing add item buttons');
        }
        
        console.log('Add Item Buttons visibility after toggle:', $addItemButtons.is(':visible'));
        console.log('Add Item Buttons CSS display after:', $addItemButtons.css('display'));
    });

    $(document).on('click', '.status-action-btn', function() {
        const containerId = $(this).data('id');
        const currentStatus = $(this).data('status');
        let newStatus = currentStatus === 'pending' ? 'received' : 'processed';
        let confirmMsg = currentStatus === 'pending' ? 
            'Mark this container as received?' : 
            'Mark this container as processed?';
        
        Swal.fire({
            title: 'Confirm Status Change',
            text: confirmMsg,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, change it!'
        }).then((result) => {
            if (result.isConfirmed) {
                updateContainerStatus(containerId, newStatus);
            }
        });
    });

    // Handle filter form submission
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        containersTable.ajax.reload();
    });

        // Handle Add Container form submit
    $('#addContainerForm').on('submit', function(e) {
        console.log('=== CONTAINER FORM SUBMISSION STARTED ===');
        e.preventDefault();
        const $form = $(this);
        
        console.log('Container form element:', $form[0]);
        console.log('Container form action:', $form.attr('action'));
        console.log('Container form method:', $form.attr('method'));
        console.log('Container form validation result:', validateForm($form[0]));
        
        if (!validateForm($form[0])) {
            console.log('Container form validation failed, adding was-validated class');
            $form.addClass('was-validated');
            return;
        }
        
        console.log('Container form validation passed, proceeding with submission');
        
        // Show loading
        Swal.fire({
            title: 'Creating Container...',
            text: 'Please wait while we create the container',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        console.log('Loading dialog shown, preparing form data...');
        
        // Prepare form data
        const formData = new FormData($form[0]);
        
        // Add items data if any were added
        if (window.containerCreationItems && window.containerCreationItems.length > 0) {
            formData.append('items_data', JSON.stringify(window.containerCreationItems.map(item => item.data)));
        }
        
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message || 'Container created successfully.',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    });
                    $('#addContainerModal').modal('hide');
                    resetAddForm();
                    containersTable.ajax.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Failed to create container.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to create container.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });
    
    // Backup: Also handle the Save Container button click directly
    $('#saveContainerBtn').on('click', function(e) {
        console.log('=== SAVE CONTAINER BUTTON CLICKED ===');
        e.preventDefault();
        e.stopPropagation();
        
        const $form = $('#addContainerForm');
        console.log('Form found:', $form.length > 0);
        
        if ($form.length > 0) {
            console.log('Triggering form submission...');
            $form.submit();
        } else {
            console.error('Form not found!');
        }
    });

    // Handle Edit Container form submit
    $('#editContainerModal form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        
        if (!validateForm($form[0])) {
            $form.addClass('was-validated');
            return;
        }

        const formData = $form.serialize();
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message || 'Container updated successfully.',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    });
                    $('#editContainerModal').modal('hide');
                    containersTable.ajax.reload();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Failed to update container.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to update container.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });

    // Analytics modal handler
    $(document).on('click', '[data-bs-target="#containerAnalyticsModal"]', function() {
        loadContainerAnalytics();
    });

    // Reset forms when modals are hidden
    $('#addContainerModal').on('hidden.bs.modal', function() {
        resetAddForm();
    });
    
    // Ensure proper cleanup when create container modal is shown
    $('#addContainerModal').on('shown.bs.modal', function() {
        console.log('=== CREATE CONTAINER MODAL SHOWN ===');
        // Force reset to ensure no old data
        resetAddForm();
        
        // Clear any container items that might have been loaded
        $('#createContainerItemsTableBody').html(`
            <tr id="noItemsRow">
                <td colspan="6" class="text-center text-muted">
                    <em>No items added yet. Click "Add Item" to get started.</em>
                </td>
        </tr>
        `);
        
        // Clear stored items
        window.containerCreationItems = [];
        
        // Ensure the Add Item button is visible
        $('#addItemBtn').show();
        $('#addItemButtons').hide();
        
        console.log('Create container modal reset completed');
        console.log('Add Item button should be visible now');
    });

    $('#editContainerModal').on('hidden.bs.modal', function() {
        const $form = $(this).find('form');
        $form[0].reset();
        $form.removeClass('was-validated');
    });

    // Handle Add Item form submissions
    $('#addBoxItemModal form').on('submit', function(e) {
        e.preventDefault();
        console.log('=== BOX FORM SUBMITTED ===');
        console.log('Form element:', this);
        console.log('Form data:', new FormData(this));
        console.log('Container ID field value:', $(this).find('input[name="container_id"]').val());
        console.log('addContainerModal show class:', $('#addContainerModal').hasClass('show'));
        
        if ($('#addContainerModal').hasClass('show')) {
            // Adding item during container creation
            console.log('Handling during container creation');
            handleAddItemDuringCreation($(this), 'box');
        } else {
            // Adding item to existing container
            console.log('Handling for existing container');
            handleAddItemForm($(this), 'box');
        }
    });

    $('#addExistingItemModal form').on('submit', function(e) {
        e.preventDefault();
        if ($('#addContainerModal').hasClass('show')) {
            // Adding item during container creation
            handleAddItemDuringCreation($(this), 'existing_item');
        } else {
            // Adding item to existing container
            handleAddItemForm($(this), 'existing_item');
        }
    });

    $('#addNewItemModal form').on('submit', function(e) {
        e.preventDefault();
        if ($('#addContainerModal').hasClass('show')) {
            // Adding item during container creation
            handleAddItemDuringCreation($(this), 'new_item');
        } else {
            // Adding item to existing container
            handleAddItemForm($(this), 'new_item');
        }
    });

    // Reset add item forms when modals are hidden
    $('#addBoxItemModal, #addExistingItemModal, #addNewItemModal').on('hidden.bs.modal', function() {
        const $form = $(this).find('form');
        $form[0].reset();
        $form.removeClass('was-validated');
        console.log('Modal hidden, form reset:', this.id);
    });
    
    // Box selection button handlers
    $('#selectExistingBoxBtn').on('click', function() {
        $('#existingBoxSection').show();
        $('#newBoxSection').hide();
        console.log('Showing existing box section');
    });
    
    $('#createNewBoxBtn').on('click', function() {
        $('#newBoxSection').show();
        $('#existingBoxSection').hide();
        console.log('Showing new box section');
    });
});

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Simplified cost calculation for add modal
 */
function recalculateAll() {
    const weight = parseFloat($('#totalWeight').val()) || 0;
    const totalPrice = parseFloat($('#totalPrice').val()) || 0;
    const shipmentCost = parseFloat($('#shipmentCost').val()) || 0;
    const profitMargin = parseFloat($('#profitMargin').val()) || 0;
    
    const totalAllCosts = totalPrice + shipmentCost;
    const expectedRevenue = totalAllCosts * (1 + profitMargin / 100);
    const expectedProfit = expectedRevenue - totalAllCosts;
    
    $('#totalAllCosts').val(totalAllCosts.toFixed(2));
    $('#remainingBalance').val(totalAllCosts.toFixed(2));
    
    // Update summary if visible
    if ($('#summaryBaseCost').length) {
        $('#summaryBaseCost').text('$' + totalPrice.toFixed(2));
        $('#summaryTotalCosts').text('$' + totalAllCosts.toFixed(2));
        $('#summaryExpectedRevenue').text('$' + expectedRevenue.toFixed(2));
        $('#summaryExpectedProfit').text('$' + expectedProfit.toFixed(2));
    }
}

/**
 * Cost calculation for edit modal
 */
function recalculateAllEdit() {
    const weight = parseFloat($('#editTotalWeight').val()) || 0;
    const totalPrice = parseFloat($('#editTotalPrice').val()) || 0;
    const shipmentCost = parseFloat($('#editShipmentCost').val()) || 0;
    const amountPaid = parseFloat($('#editAmountPaid').val()) || 0;
    
    const totalAllCosts = totalPrice + shipmentCost;
    const remainingBalance = totalAllCosts - amountPaid;
    
    $('#editRemainingBalance').val(remainingBalance.toFixed(2));
}

/**
 * Validate form fields
 */
function validateForm(form) {
    console.log('Validating form:', form);
    const isValid = form.checkValidity();
    console.log('Form validity:', isValid);
    
    if (!isValid) {
        console.log('Form validation failed. Invalid elements:');
        const invalidElements = form.querySelectorAll(':invalid');
        invalidElements.forEach(element => {
            console.log('-', element.name, ':', element.validationMessage);
        });
    }
    
    return isValid;
}

/**
 * Reset add container form
 */
function resetAddForm() {
    const $form = $('#addContainerModal form');
    $form[0].reset();
    $form.removeClass('was-validated');
    
    // Reset calculated fields
    $('#totalAllCosts').val('0.00');
    $('#remainingBalance').val('0.00');
    $('#summaryBaseCost').text('$0.00');
    $('#summaryTotalCosts').text('$0.00');
    $('#summaryExpectedRevenue').text('$0.00');
    $('#summaryExpectedProfit').text('$0.00');
    
            // Reset items table
        $('#createContainerItemsTableBody').html(`
            <tr id="noItemsRow">
                <td colspan="6" class="text-center text-muted">
                <em>No items added yet. Click "Add Item" to get started.</em>
            </td>
        </tr>
        `);
    
    // Reset total value
    $('#totalItemsValue').text('CFA 0.00');
    
    // Clear stored items
    window.containerCreationItems = [];
}

// ============================================================================
// CONTAINER MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Load container data for editing
 */
function loadContainerData(containerId) {
    $.ajax({
        url: '../ajax/get_container.php',
        type: 'POST',
        data: {
            container_id: containerId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const container = response.data;
                
                // Populate edit form fields
                $('#editContainerId').val(container.id);
                $('#editContainerNumber').val(container.container_number);
                $('#editSupplierId').val(container.supplier_id);
                $('#editArrivalDate').val(container.arrival_date);
                $('#editTotalWeight').val(container.total_weight_kg);
                $('#editTotalPrice').val(container.total_cost);
                $('#editShipmentCost').val(container.shipment_cost);
                $('#editProfitMargin').val(container.profit_margin_percentage);
                $('#editAmountPaid').val(container.amount_paid);
                $('#editStatus').val(container.status);
                $('#editContainerNotes').val(container.notes);
                
                // Show edit modal using Bootstrap 5 syntax
                const modalElement = document.getElementById('editContainerModal');
                if (modalElement) {
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();
                } else {
                    console.error('Edit Container Modal not found!');
                    Swal.fire('Error', 'Modal not found. Please refresh the page.', 'error');
                }
            } else {
                Swal.fire('Error', response.message || 'Failed to load container data', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading container data:', {xhr, status, error});
            Swal.fire('Error', 'Failed to load container data', 'error');
        }
    });
}

/**
 * Confirm container deletion
 */
function confirmDelete(containerId) {
    Swal.fire({
        title: 'Delete Container?',
        text: 'This action cannot be undone. All container data will be permanently deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            deleteContainer(containerId);
        }
    });
}

/**
 * Delete container
 */
function deleteContainer(containerId) {
    $.ajax({
        url: '../ajax/process_container.php',
        type: 'POST',
        data: {
            action: 'delete',
            container_id: containerId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire('Success', response.message, 'success');
                $('#containersTable').DataTable().ajax.reload();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to delete container', 'error');
        }
    });
}

/**
 * Open process container modal
 */
function openProcessModal(containerId) {
    // Show the processing modal
    $('#processContainerModal').modal('show');
    
    // Start the processing
    processContainer(containerId);
}

/**
 * Process container with progress indication
 */
function processContainer(containerId) {
    const progressBar = $('#processContainerModal .progress-bar');
    const cancelBtn = $('#cancelProcessBtn');
    let progressInterval;
    
    // Show cancel button and start progress
    cancelBtn.show();
    progressBar.css('width', '0%');
    
    // Simulate progress (since we can't track actual server progress)
    let progress = 0;
    progressInterval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90; // Don't go to 100% until server responds
        progressBar.css('width', progress + '%');
    }, 200);
    
    // Handle cancel button click
    cancelBtn.off('click').on('click', function() {
        clearInterval(progressInterval);
        $('#processContainerModal').modal('hide');
        // Reset progress bar
        setTimeout(() => {
            progressBar.css('width', '0%');
            cancelBtn.hide();
        }, 300);
    });
    
    // Handle modal hidden event to reset state
    $('#processContainerModal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
        clearInterval(progressInterval);
        progressBar.css('width', '0%');
        cancelBtn.hide();
    });
    
    // Make the AJAX request
    $.ajax({
        url: '../ajax/process_container.php',
        type: 'POST',
        data: {
            action: 'update_status',
            container_id: containerId,
            new_status: 'processed',
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            clearInterval(progressInterval);
            progressBar.css('width', '100%');
            
            setTimeout(() => {
                $('#processContainerModal').modal('hide');
                
                if (response.success) {
                    Swal.fire('Success', response.message, 'success');
                    $('#containersTable').DataTable().ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }, 500);
        },
        error: function() {
            clearInterval(progressInterval);
            progressBar.css('width', '100%');
            
            setTimeout(() => {
                $('#processContainerModal').modal('hide');
                Swal.fire('Error', 'Failed to process container. Please try again.', 'error');
            }, 500);
        }
    });
}

/**
 * Update container status
 */
function updateContainerStatus(containerId, newStatus) {
    $.ajax({
        url: '../ajax/process_container.php',
        type: 'POST',
        data: {
            action: 'update_status',
            container_id: containerId,
            new_status: newStatus,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire('Success', response.message, 'success');
                $('#containersTable').DataTable().ajax.reload();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to update container status', 'error');
        }
    });
}

// ============================================================================
// FINANCIAL SUMMARY FUNCTIONS
// ============================================================================

/**
 * Open financial summary modal
 */
function openFinancialSummary(containerId) {
    $('#financialSummaryModal').modal('show');
    loadFinancialSummary(containerId);
}

/**
 * Load financial summary data
 */
function loadFinancialSummary(containerId) {
    $.ajax({
        url: '../ajax/process_container.php',
        type: 'POST',
        data: {
            action: 'get_financial_summary',
            container_id: containerId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#financialSummaryContent').html(createFinancialSummaryHTML(response.data));
            } else {
                $('#financialSummaryContent').html('<div class="alert alert-danger">' + response.message + '</div>');
            }
        },
        error: function() {
            $('#financialSummaryContent').html('<div class="alert alert-danger">Failed to load financial summary</div>');
        }
    });
}

/**
 * Create financial summary HTML
 */
function createFinancialSummaryHTML(data) {
    return `
        <div class="row text-center">
            <div class="col-md-3">
                <div class="h5 text-primary mb-1">CFA ${parseFloat(data.base_cost || 0).toFixed(2)}</div>
                <div class="small text-muted">Base Cost</div>
            </div>
            <div class="col-md-3">
                <div class="h5 text-warning mb-1">CFA ${parseFloat(data.shipment_cost || 0).toFixed(2)}</div>
                <div class="small text-muted">Shipment Cost</div>
            </div>
            <div class="col-md-3">
                <div class="h5 text-info mb-1">CFA ${parseFloat(data.total_all_costs || 0).toFixed(2)}</div>
                <div class="small text-muted">Total Costs</div>
            </div>
            <div class="col-md-3">
                <div class="h5 text-success mb-1">CFA ${parseFloat(data.actual_profit || 0).toFixed(2)}</div>
                <div class="small text-muted">Actual Profit</div>
            </div>
        </div>
        <hr>
        <div class="row text-center">
            <div class="col-md-4">
                <div class="h6 text-muted mb-1">Expected Revenue</div>
                <div class="h5 text-success">CFA ${parseFloat(data.expected_selling_total || 0).toFixed(2)}</div>
            </div>
            <div class="col-md-4">
                <div class="h6 text-muted mb-1">ROI</div>
                <div class="h5 text-info">${parseFloat(data.roi_percentage || 0).toFixed(1)}%</div>
            </div>
            <div class="col-md-4">
                <div class="h6 text-muted mb-1">Cost per KG</div>
                <div class="h5 text-warning">CFA ${parseFloat(data.cost_per_kg || 0).toFixed(2)}</div>
            </div>
        </div>
    `;
}

// ============================================================================
// ANALYTICS FUNCTIONS
// ============================================================================

/**
 * Load container analytics
 */
function loadContainerAnalytics() {
    $('#containerAnalyticsContent').html('<div class="text-center"><i class="bi bi-hourglass-split"></i> Loading analytics...</div>');
    // This would load container analytics data
    // For now, show a placeholder
    setTimeout(() => {
        $('#containerAnalyticsContent').html(`
            <div class="alert alert-info">
                <h6><i class="bi bi-info-circle me-2"></i>Container Analytics</h6>
                <p>Analytics dashboard will be implemented in future updates.</p>
            </div>
        `);
    }, 1000);
}

// ============================================================================
// ITEM MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Open manage items modal for a container
 */
function openManageItems(containerId) {
    console.log('=== OPENING MANAGE ITEMS MODAL ===');
    console.log('Container ID:', containerId);
    
    // Check if modal exists
    const modalElement = document.getElementById('manageItemsModal');
    if (!modalElement) {
        console.error('Manage Items Modal not found!');
        Swal.fire('Error', 'Modal not found. Please refresh the page.', 'error');
        return;
    }
    
    // Store container ID immediately for debugging
    $('#manageItemsModal').data('container-id', containerId);
    console.log('Container ID stored in modal:', $('#manageItemsModal').data('container-id'));
    
    // Load container items and show modal
    loadContainerItems(containerId);
    
    // Use Bootstrap 5 modal show method
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
}

/**
 * Load container items and populate the modal
 */
function loadContainerItems(containerId) {
    console.log('=== LOADING CONTAINER ITEMS ===');
    console.log('Container ID:', containerId);
    console.log('CSRF Token:', $('input[name="csrf_token"]').val());
    
    $.ajax({
        url: '../ajax/process_container.php',
        type: 'POST',
        data: {
            action: 'get_container_items',
            container_id: containerId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        beforeSend: function() {
            console.log('AJAX request starting...');
        },
        success: function(response) {
            console.log('AJAX response received:', response);
            if (response.success) {
                try {
                    // The response structure from process_container.php is different
                    // It returns {success: true, data: items} where items includes both items and boxes
                    populateContainerItemsModal({id: containerId}, response.data);
                } catch (error) {
                    console.error('Error in populateContainerItemsModal:', error);
                    Swal.fire('Error', 'Failed to populate modal: ' + error.message, 'error');
                }
            } else {
                console.error('API returned error:', response.message);
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', {xhr, status, error});
            console.error('Response text:', xhr.responseText);
            Swal.fire('Error', 'Failed to load container items: ' + error, 'error');
        }
    });
}

/**
 * Populate the container items modal with data
 */
function populateContainerItemsModal(container, items) {
    // Update modal title and container info
    $('#manageItemsModal .modal-title').html(`
        <i class="bi bi-box-seam me-2"></i>Manage Items - Container #${container.id || 'Unknown'}
        <span class="badge bg-secondary ms-2">Active</span>
    `);
    
    // Update container summary with safe defaults
    const totalItems = items ? items.length : 0;
    
    // Calculate total value from items and boxes
    let totalValue = 0;
    if (items && items.length > 0) {
        items.forEach(item => {
            if (item.item_type_display === 'box') {
                // For boxes, calculate total cost
                const boxUnitCost = parseFloat(item.warehouse_box_unit_cost || 0);
                const boxQuantity = parseInt(item.quantity || 1);
                totalValue += boxUnitCost * boxQuantity;
            } else {
                // For items, calculate total cost
                let itemUnitCost = 0;
                if (item.item_type === 'existing_item') {
                    itemUnitCost = parseFloat(item.existing_item_base_price || 0);
                } else {
                    itemUnitCost = parseFloat(item.new_item_unit_cost || 0);
                }
                const itemQuantity = parseInt(item.quantity_in_container || 1);
                totalValue += itemUnitCost * itemQuantity;
            }
        });
    }
    
    $('#containerItemsSummary').html(`
        <div class="row text-center">
            <div class="col-md-3">
                <div class="h5 text-primary mb-1">${totalItems}</div>
                <div class="small text-muted">Total Items</div>
            </div>
            <div class="col-md-3">
                <div class="h5 text-success mb-1">CFA ${totalValue.toFixed(2)}</div>
                <div class="small text-muted">Total Value</div>
            </div>
            <div class="col-md-3">
                <div class="h5 text-info mb-1">${items ? items.filter(item => item.item_type_display === 'box').length : 0}</div>
                <div class="small text-muted">Boxes</div>
            </div>
            <div class="col-md-3">
                <div class="h5 text-warning mb-1">${items ? items.filter(item => item.item_type_display !== 'box').length : 0}</div>
                <div class="small text-muted">Items</div>
            </div>
        </div>
    `);
    
    // Debug: Log the items data
    console.log('=== POPULATING CONTAINER ITEMS MODAL ===');
    console.log('Container:', container);
    console.log('Items:', items);
    console.log('Items length:', items.length);
    
    // Populate items table
    const tbody = $('#containerItemsTable tbody');
    tbody.empty();
    
    if (items.length === 0) {
        tbody.html('<tr><td colspan="7" class="text-center text-muted">No items in this container</td></tr>');
    } else {
        items.forEach((item, index) => {
            console.log(`Item ${index}:`, item);
            const row = createItemTableRow(item, 'manage');
            tbody.append(row);
        });
    }
    
    // Show/hide add item button based on container status
            if (container.can_add_items) {
            $('#addItemBtn').show();
            $('#addItemButtons').hide(); // Always start hidden
            console.log('Add Item Buttons hidden initially');
        } else {
            $('#addItemBtn').hide();
            $('#addItemButtons').hide();
            console.log('Add Item Buttons hidden (no permission)');
        }
    
    // Store container ID for add item operations
    $('#manageItemsModal').data('container-id', container.id);
    console.log('Stored container ID in modal:', container.id);
    console.log('Modal data after storing:', $('#manageItemsModal').data('container-id'));
    
    // Debug initial state of add item buttons
    console.log('Initial addItemButtons display:', $('#addItemButtons').css('display'));
    console.log('Initial addItemButtons visibility:', $('#addItemButtons').is(':visible'));
}

/**
 * Create a table row for an item or box
 */
function createItemTableRow(item, context = 'manage') {
    console.log('=== CREATING TABLE ROW ===');
    console.log('Item data:', item);
    console.log('Item type display:', item.item_type_display);
    console.log('Box type:', item.box_type);
    console.log('Warehouse box ID:', item.warehouse_box_id);
    console.log('Context:', context);
    
    // For container items, always allow edit and remove
    const canEdit = true; // Always allow editing
    const canRemove = true; // Always allow removing
    
    // Use different button classes based on context
    const removeButtonClass = context === 'create' ? 'remove-item-during-creation' : 'remove-item';
    const editButtonClass = context === 'create' ? 'edit-item-during-creation' : 'edit-item';
    
    // Handle different item types
    let displayType, displayName, quantity, unitCost, totalCost, sellingPrice, itemCode;
    
    if (item.item_type_display === 'box') {
        // This is a box
        displayType = 'Box';
        if (item.box_type === 'existing' && item.warehouse_box_id) {
            displayName = item.warehouse_box_name || `Box ID: ${item.warehouse_box_id}`;
        } else {
            displayName = item.new_box_name || 'New Box';
        }
        quantity = item.quantity || 1; // For boxes, use 'quantity' field
        unitCost = parseFloat(item.warehouse_box_unit_cost || 0); // Use box unit cost
        totalCost = unitCost * quantity; // Calculate total cost for boxes
        sellingPrice = 0; // Boxes don't have selling price
        itemCode = null; // Boxes don't have item codes
    } else {
        // This is a regular item
        displayType = item.item_type === 'existing_item' ? 'Existing Item' : 'New Item';
        displayName = item.existing_item_name || item.new_item_name || 'Unknown Item';
        quantity = item.quantity_in_container || 1;
        
        if (item.item_type === 'existing_item') {
            // For existing items, use the base price and selling price from inventory_items
            unitCost = item.existing_item_base_price || 0;
            sellingPrice = item.existing_item_selling_price || 0;
            itemCode = item.existing_item_code || null; // Get code from existing item
        } else {
            // For new items, use the new item values
            unitCost = item.new_item_unit_cost || 0;
            sellingPrice = item.new_item_selling_price || 0;
            itemCode = item.new_item_code || null; // Get code from new item
        }
        
        totalCost = parseFloat(unitCost) * quantity;
    }
    
    const rowHtml = `
        <tr data-item-id="${item.id}" data-item-type="${item.item_type_display || 'item'}" data-item-data='${JSON.stringify({code: itemCode})}'>
            <td>
                <div class="d-flex align-items-center">
                    <span class="badge bg-${item.item_type_display === 'box' ? 'success' : 'primary'} me-2">${displayType}</span>
                    <strong>${escapeHtml(displayName)}</strong>
                </div>
            </td>
            <td>${quantity}</td>
            <td>CFA ${parseFloat(unitCost).toFixed(2)}</td>
            <td>CFA ${parseFloat(totalCost).toFixed(2)}</td>
            <td>CFA ${parseFloat(sellingPrice).toFixed(2)}</td>
            <td>${item.created_at ? new Date(item.created_at).toLocaleDateString() : '-'}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    ${canEdit ? `
                        <button class="btn btn-outline-primary btn-sm ${editButtonClass}" data-item-id="${item.id}" title="Edit Item">
                            <i class="bi bi-pencil"></i>
                        </button>
                    ` : ''}
                    ${canRemove ? `
                        <button class="btn btn-outline-danger btn-sm ${removeButtonClass}" data-item-id="${item.id}" title="Remove Item">
                            <i class="bi bi-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `;
    
    console.log('Generated row HTML:', rowHtml);
    console.log('Can edit:', canEdit);
    console.log('Can remove:', canRemove);
    
    return rowHtml;
}

/**
 * Open add item modal during container creation
 */
function openAddItemDuringCreation() {
    // Show the add item type selection modal
    $('#addItemDuringCreationModal').modal('show');
}

/**
 * Open specific add item modal during container creation
 */
function openAddItemDuringCreationModal(itemType) {
    console.log('=== OPENING ADD ITEM DURING CREATION MODAL ===');
    console.log('Item type:', itemType);
    
    // Show the appropriate add item modal
    let modalId;
    switch (itemType) {
        case 'box':
            modalId = '#addBoxItemModal';
            break;
        case 'existing_item':
            modalId = '#addExistingItemModal';
            break;
        case 'new_item':
            modalId = '#addNewItemModal';
            break;
        default:
            console.error('Unknown item type:', itemType);
            return;
    }
    
    console.log('Opening modal:', modalId);
    
    // Check if modal exists
    const $modal = $(modalId);
    if ($modal.length === 0) {
        console.error(`Modal ${modalId} not found`);
        return;
    }
    
    // Reset form
    const $form = $modal.find('form');
    if ($form.length > 0) {
        $form[0].reset();
        console.log('Form reset for modal:', modalId);
    }
    
    // Show the modal using Bootstrap 5 syntax
    const modal = new bootstrap.Modal($modal[0]);
    modal.show();
}

/**
 * Open add item modal for a specific item type
 */
function openAddItemModal(containerId, itemType) {
    // Get the modal ID
    let modalId;
    switch (itemType) {
        case 'box':
            modalId = '#addBoxItemModal';
            break;
        case 'existing_item':
            modalId = '#addExistingItemModal';
            break;
        case 'new_item':
            modalId = '#addNewItemModal';
            break;
        default:
            console.error('Unknown item type:', itemType);
            return;
    }
    
    const $modal = $(modalId);
    
    // Check if modal exists
    if ($modal.length === 0) {
        console.error(`Modal ${modalId} not found`);
        return;
    }
    
    // Reset form and set container ID
    const $form = $modal.find('form');
    if ($form.length > 0) {
        $form[0].reset();
        // Set container ID in the form's hidden field
        const $containerIdField = $form.find('input[name="container_id"]');
        $containerIdField.val(containerId);
        console.log('Setting container_id in modal', modalId, 'to:', containerId);
        console.log('Container ID field found:', $containerIdField.length > 0);
        console.log('Container ID field value after setting:', $containerIdField.val());
        console.log('Container ID field HTML:', $containerIdField.prop('outerHTML'));
        console.log('Form HTML:', $form.prop('outerHTML'));
    }
    
    // Show the appropriate modal using Bootstrap 5 syntax
    const modal = new bootstrap.Modal($modal[0]);
    modal.show();
}

/**
 * Open edit item modal
 */
function openEditItemModal(containerId, itemId) {
    // Load item data and show edit modal
    loadItemData(containerId, itemId);
    $('#editItemModal').modal('show');
}

/**
 * Load item data for editing
 */
function loadItemData(containerId, itemId) {
    // This would load the specific item data
    // For now, we'll implement a simple version
    console.log('Loading item data for editing:', { containerId, itemId });
}

/**
 * Confirm removal of an item
 */
function confirmRemoveItem(containerId, itemId) {
    Swal.fire({
        title: 'Remove Item?',
        text: 'Are you sure you want to remove this item from the container?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            removeItemFromContainer(containerId, itemId);
        }
    });
}

/**
 * Remove item from container
 */
function removeItemFromContainer(containerId, itemId) {
    $.ajax({
        url: '../ajax/process_container.php',
        type: 'POST',
        data: {
            action: 'remove_item',
            container_id: containerId,
            item_id: itemId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire('Success', response.message, 'success');
                // Reload container items
                loadContainerItems(containerId);
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to remove item', 'error');
        }
    });
}

/**
 * Handle add item during container creation
 */
function handleAddItemDuringCreation($form, itemType) {
    if (!validateForm($form[0])) {
        $form.addClass('was-validated');
        return;
    }

    // Get form data
    const formData = new FormData($form[0]);
    let itemData = {};

    // Process data based on item type
    switch (itemType) {
        case 'box':
            // Check if this is a new box or existing box
            if (formData.get('new_box_number')) {
                // New box creation
                itemData = {
                    type: 'box',
                    warehouse_box_id: null,
                    new_box_data: {
                        box_number: formData.get('new_box_number'),
                        box_name: formData.get('new_box_name'),
                        box_type: formData.get('new_box_type'),
                        quantity: parseInt(formData.get('new_box_quantity')),
                        notes: formData.get('new_box_notes')
                    },
                    quantity: parseInt(formData.get('new_box_quantity')),
                    display_name: formData.get('new_box_name') + ' (' + formData.get('new_box_number') + ')',
                    unit_cost: 0,
                    total_cost: 0,
                    selling_price: 0
                };
            } else {
                // Existing box selection
                itemData = {
                    type: 'box',
                    warehouse_box_id: formData.get('warehouse_box_id'),
                    quantity: parseInt(formData.get('quantity')),
                    display_name: $(`#warehouseBoxId option:selected`).text(),
                    unit_cost: 0,
                    total_cost: 0,
                    selling_price: 0
                };
            }
            break;
        case 'existing_item':
            const selectedOption = $(`#existingItemId option:selected`);
            const itemCode = selectedOption.text().split(' - ')[1] || ''; // Extract code from "Name - Code" format
            
            itemData = {
                type: 'existing_item',
                item_id: formData.get('item_id'),
                code: itemCode, // Add the item code
                quantity: parseInt(formData.get('quantity')),
                display_name: selectedOption.text(),
                unit_cost: parseFloat(selectedOption.data('base-price') || 0),
                total_cost: 0,
                selling_price: parseFloat(selectedOption.data('selling-price') || 0)
            };
            // Calculate total cost
            itemData.total_cost = itemData.unit_cost * itemData.quantity;
            break;
        case 'new_item':
            itemData = {
                type: 'new_item',
                name: formData.get('name'),
                code: formData.get('code'),
                description: formData.get('description'),
                category_id: formData.get('category_id'),
                brand: formData.get('brand'),
                size: formData.get('size'),
                color: formData.get('color'),
                material: formData.get('material'),
                quantity: parseInt(formData.get('quantity')),
                display_name: formData.get('name') + ' (' + formData.get('code') + ')',
                unit_cost: parseFloat(formData.get('unit_cost')),
                total_cost: parseFloat(formData.get('unit_cost')) * parseInt(formData.get('quantity')),
                selling_price: parseFloat(formData.get('selling_price'))
            };
            break;
    }

    // Check for duplicate item codes before adding
    if (itemData.code && isDuplicateItemCode(itemData.code)) {
        Swal.fire({
            title: 'Duplicate Item Code!',
            text: `An item with code "${itemData.code}" already exists in this container. Please use a unique item code.`,
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }

    // Add item to the temporary table
    addItemToCreationTable(itemData);

    // Close the add item modal
    $form.closest('.modal').modal('hide');

    // Reset the form
    $form[0].reset();
    $form.removeClass('was-validated');

    // Show success message
    Swal.fire({
        title: 'Item Added!',
        text: 'Item has been added to the container. You can add more items or create the container.',
        icon: 'success',
        confirmButtonText: 'OK'
    });
}

/**
 * Add item to the creation table
 */
function addItemToCreationTable(itemData) {
    // Remove the "no items" row if it exists
    $('#noItemsRow').remove();

    // Create a unique ID for the item row
    const itemId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    // Create the table row
    const row = `
        <tr data-item-id="${itemId}" data-item-data='${JSON.stringify(itemData)}'>
            <td>
                <div class="d-flex align-items-center">
                    <span class="badge bg-${getItemTypeBadgeClass(itemData.type)} me-2">${getItemTypeDisplayName(itemData.type)}</span>
                    <strong>${escapeHtml(itemData.display_name)}</strong>
                </div>
            </td>
            <td>${getItemTypeDisplayName(itemData.type)}</td>
            <td>${itemData.quantity}</td>
            <td>CFA ${itemData.unit_cost.toFixed(2)}</td>
            <td>CFA ${itemData.total_cost.toFixed(2)}</td>
            <td>
                <button type="button" class="btn btn-outline-danger btn-sm remove-item-during-creation" data-item-id="${itemId}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `;

    // Add the row to the table
    $('#createContainerItemsTableBody').append(row);

    // Update the total value
    updateCreationTableTotal();

    // Store the item data for later use
    if (!window.containerCreationItems) {
        window.containerCreationItems = [];
    }
    window.containerCreationItems.push({
        id: itemId,
        data: itemData
    });
}

/**
 * Update the total value in the creation table
 */
function updateCreationTableTotal() {
    let total = 0;
    $('#createContainerItemsTableBody tr').each(function() {
        const itemData = $(this).data('item-data');
        if (itemData && itemData.total_cost) {
            total += itemData.total_cost;
        }
    });

    $('#totalItemsValue').text('CFA ' + total.toFixed(2));
}

/**
 * Get display name for item type
 */
function getItemTypeDisplayName(itemType) {
    switch (itemType) {
        case 'box': return 'Warehouse Box';
        case 'existing_item': return 'Existing Item';
        case 'new_item': return 'New Item';
        default: return 'Unknown';
    }
}

/**
 * Load warehouse boxes for the dropdown
 */
function loadWarehouseBoxes() {
    $.ajax({
        url: '../ajax/get_warehouse_boxes.php',
        type: 'POST',
        data: {
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.boxes && Array.isArray(response.boxes)) {
                const $select = $('#warehouseBoxId');
                $select.empty();
                $select.append('<option value="">Choose a warehouse box...</option>');
                
                response.boxes.forEach(box => {
                    $select.append(`<option value="${box.id}" data-available="${box.quantity || 0}">${box.box_number} - ${box.box_name} (${box.box_type || 'No Type'})</option>`);
                });
            } else {
                console.error('Invalid response format:', response);
                $('#warehouseBoxId').html('<option value="">Error loading warehouse boxes</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load warehouse boxes:', {xhr, status, error});
            $('#warehouseBoxId').html('<option value="">Error loading warehouse boxes</option>');
        }
    });
}

/**
 * Load existing items for the dropdown
 */
function loadExistingItems() {
    console.log('loadExistingItems called');
    console.log('CSRF token:', $('input[name="csrf_token"]').val());
    
    $.ajax({
        url: '../ajax/get_inventory.php',
        type: 'POST',
        data: {
            csrf_token: $('input[name="csrf_token"]').val(),
            draw: 1,
            start: 0,
            length: 1000, // Get up to 1000 items
            search: { value: '' }, // Empty search
            category: '',
            item_code: '',
            name: '',
            stock_status: '',
            status: 'active' // Only active items
        },
        dataType: 'json',
        success: function(response) {
            console.log('loadExistingItems response:', response);
            if (response.data && Array.isArray(response.data)) {
                const $select = $('#existingItemId');
                $select.empty();
                $select.append('<option value="">Choose an existing item...</option>');
                
                console.log('Adding', response.data.length, 'items to dropdown');
                response.data.forEach(item => {
                    $select.append(`<option value="${item.id}" data-base-price="${item.base_price || 0}" data-selling-price="${item.selling_price || 0}">${item.name} - ${item.item_code || 'No Code'}</option>`);
                });
            } else {
                console.error('Invalid response format:', response);
                $('#existingItemId').html('<option value="">Error loading existing items</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load existing items:', {xhr, status, error});
            console.error('Response text:', xhr.responseText);
            $('#existingItemId').html('<option value="">Error loading existing items</option>');
        }
    });
}

/**
 * Remove item during container creation
 */
function removeItemDuringCreation(itemId) {
    // Remove the row from the table
    $(`tr[data-item-id="${itemId}"]`).remove();

    // Remove from the stored items array
    if (window.containerCreationItems) {
        window.containerCreationItems = window.containerCreationItems.filter(item => item.id !== itemId);
    }

    // Update the total value
    updateCreationTableTotal();

    // If no items left, show the "no items" row
    if ($('#createContainerItemsTableBody tr').length === 0) {
        $('#createContainerItemsTableBody').html(`
            <tr id="noItemsRow">
                <td colspan="6" class="text-center text-muted">
                    <em>No items added yet. Click "Add Item" to get started.</em>
                </td>
            </tr>
        `);
    }
}

/**
 * Handle add item form submission
 */
function handleAddItemForm($form, itemType) {
    console.log('handleAddItemForm called with itemType:', itemType);
    console.log('Form data:', new FormData($form[0]));
    
    // Prevent multiple submissions
    if ($form.data('submitting')) {
        console.log('Form already submitting, ignoring duplicate call');
        return;
    }
    
    if (!validateForm($form[0])) {
        $form.addClass('was-validated');
        console.log('Form validation failed for box form');
        console.log('Form validation errors:', $form[0].checkValidity());
        console.log('Form elements with validation errors:');
        $form.find(':invalid').each(function() {
            console.log('- Invalid element:', this.name, this.validity);
        });
        return;
    }
    
    // Mark form as submitting
    $form.data('submitting', true);

    // Get container ID from the form field
    const $containerIdField = $form.find('input[name="container_id"]');
    const containerId = $containerIdField.val();
    
    console.log('Container ID field found:', $containerIdField.length > 0);
    console.log('Container ID field value:', containerId);
    console.log('Container ID field HTML:', $containerIdField.prop('outerHTML'));
    
    // Validate container ID
    if (!containerId || containerId === 'undefined') {
        console.error('Container ID validation failed:', { containerId, fieldExists: $containerIdField.length > 0 });
        Swal.fire({
            title: 'Error!',
            text: 'Container ID is missing. Please try again.',
            icon: 'error',
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    // Create form data (container_id is already in the form)
    const formData = new FormData($form[0]);
    
    // Debug: Check what's in the form data
    console.log('Form elements:', $form.find('input, select, textarea').map(function() {
        return { name: this.name, value: this.value, type: this.type };
    }).get());
    
    console.log('CSRF token from form:', $form.find('input[name="csrf_token"]').val());
    console.log('CSRF token from page:', $('input[name="csrf_token"]').val());

    // Show loading
    Swal.fire({
        title: 'Adding Item...',
        text: 'Please wait while we add the item to the container',
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    console.log('Making AJAX request to process_container.php');
    console.log('Form data being sent:', Object.fromEntries(formData));
    
    $.ajax({
        url: '../ajax/process_container.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        beforeSend: function() {
            console.log('AJAX request starting...');
        },
        success: function(response) {
            Swal.close();
            // Reset submitting flag
            $form.data('submitting', false);
            
            if (response.success) {
                Swal.fire({
                    title: 'Success!',
                    text: response.message || 'Item added successfully.',
                    icon: 'success',
                    confirmButtonColor: '#28a745'
                });
                
                // Close the add item modal using Bootstrap 5 syntax
                const modalElement = $form.closest('.modal')[0];
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
                
                // Reload container items
                loadContainerItems(containerId);
                
                // Reload main containers table
                $('#containersTable').DataTable().ajax.reload();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to add item.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX error occurred:', {xhr, status, error});
            console.log('Response text:', xhr.responseText);
            Swal.close();
            // Reset submitting flag
            $form.data('submitting', false);
            Swal.fire({
                title: 'Error!',
                text: 'Failed to add item.',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
        }
    });
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get badge class for item type
 */
function getItemTypeBadgeClass(itemType) {
    switch (itemType) {
        case 'box': return 'success';
        case 'existing_item': return 'info';
        case 'new_item': return 'warning';
        default: return 'secondary';
    }
}

/**
 * Get badge class for status
 */
function getStatusBadgeClass(status) {
    switch (status) {
        case 'pending': return 'warning';
        case 'received': return 'info';
        case 'processed': return 'primary';
        case 'completed': return 'success';
        default: return 'secondary';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================================
// BOOTSTRAP UTILITY FUNCTIONS
// ============================================================================

/**
 * Initialize tooltips and popovers
 */
function initializeTooltipsPopovers() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Apply Bootstrap styling to DataTables pagination
 */
function applyBootstrapPagination() {
    // Wait for DOM to be ready
    setTimeout(function() {
        // Add Bootstrap classes to pagination wrapper
        $('.dataTables_paginate').addClass('d-flex justify-content-end align-items-center');
        
        // Style the pagination container
        $('.dataTables_paginate .paging_full_numbers').addClass('d-flex flex-wrap gap-1');
        
        // Style pagination buttons
        $('.dataTables_paginate .paginate_button').each(function() {
            const $btn = $(this);
            
            // Skip if already processed
            if ($btn.hasClass('btn')) {
                return;
            }
            
            // Add base button styles
            $btn.addClass('btn btn-sm');
            
            // Add specific styling based on state
            if ($btn.hasClass('current')) {
                $btn.addClass('btn-primary').removeClass('btn-outline-primary');
            } else if ($btn.hasClass('disabled')) {
                $btn.addClass('btn-outline-secondary disabled');
            } else {
                $btn.addClass('btn-outline-primary');
            }
            
            // Improve accessibility
            if ($btn.hasClass('previous')) {
                $btn.attr('aria-label', 'Previous page');
            } else if ($btn.hasClass('next')) {
                $btn.attr('aria-label', 'Next page');
            }
            
            // Handle click events properly
            $btn.on('click', function(e) {
                e.preventDefault();
                // Let DataTables handle the actual pagination
            });
        });
        
        // Style length menu
        $('.dataTables_length select').addClass('form-select form-select-sm d-inline-block w-auto');
        
        // Style search input
        $('.dataTables_filter input').addClass('form-control form-control-sm');
        
        // Add responsive classes
        $('.dataTables_info').addClass('small text-muted');
        
    }, 100);
}

// ============================================================================
// BOX MODAL HELPER FUNCTIONS
// ============================================================================

/**
 * Reset the box modal to initial state
 */
function resetBoxModal() {
    // Hide both sections
    $('#existingBoxSection').hide();
    $('#newBoxSection').hide();
    
    // Show selection buttons
    $('.d-grid').show();
    
    // Reset form fields
    $('#addBoxItemModal form')[0].reset();
    
    // Remove required attributes temporarily
    $('#warehouseBoxId').removeAttr('required');
    $('#boxQuantity').removeAttr('required');
    $('#newBoxNumber').removeAttr('required');
    $('#newBoxName').removeAttr('required');
    $('#newBoxQuantity').removeAttr('required');
}

/**
 * Show the existing box selection section
 */
function showExistingBoxSection() {
    // Hide selection buttons and new box section
    $('.d-grid').hide();
    $('#newBoxSection').hide();
    
    // Show existing box section
    $('#existingBoxSection').show();
    
    // Add required attributes
    $('#warehouseBoxId').attr('required', 'required');
    $('#boxQuantity').attr('required', 'required');
    
    // Reset quantity field
    $('#boxQuantity').val(1);
    
    // Focus on first field
    $('#warehouseBoxId').focus();
}

/**
 * Show the new box creation section
 */
function showNewBoxSection() {
    // Hide selection buttons and existing box section
    $('.d-grid').hide();
    $('#existingBoxSection').hide();
    
    // Show new box section
    $('#newBoxSection').show();
    
    // Add required attributes
    $('#newBoxNumber').attr('required', 'required');
    $('#newBoxName').attr('required', 'required');
    $('#newBoxQuantity').attr('required', 'required');
    
    // Focus on first field
    $('#newBoxNumber').focus();
}

// ============================================================================
// EDIT ITEM FUNCTIONALITY
// ============================================================================

// Wrap jQuery-dependent code in document ready
$(document).ready(function() {
    /**
     * Load item details for editing
     */
    window.loadItemForEdit = function(itemId, containerId) {
        console.log('=== LOADING ITEM FOR EDIT ===');
        console.log('Item ID:', itemId);
        console.log('Container ID:', containerId);
        console.log('CSRF Token:', $('input[name="csrf_token"]').val());
        
        // Show loading indicator
        Swal.fire({
            title: 'Loading...',
            text: 'Please wait while we load the item details',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '../ajax/get_container_item_details.php',
            type: 'POST',
            data: {
                item_id: itemId,
                container_id: containerId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                Swal.close();
                console.log('AJAX Response:', response);
                if (response.success && response.data) {
                    console.log('Item data received:', response.data);
                    populateEditModal(response.data, containerId);
                    // Use setTimeout to ensure DOM is updated before showing modal
                    setTimeout(function() {
                        const modalElement = document.getElementById('editItemModal');
                        if (modalElement) {
                            const modal = new bootstrap.Modal(modalElement);
                            modal.show();
                        } else {
                            console.error('Edit modal element not found');
                            Swal.fire('Error', 'Modal not found. Please refresh the page.', 'error');
                        }
                    }, 100);
                } else {
                    console.error('Backend error:', response.message);
                    Swal.fire('Error', response.message || 'Failed to load item details', 'error');
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                console.error('Load item for edit error:', {xhr, status, error});
                console.error('Response text:', xhr.responseText);
                Swal.fire('Error', 'Failed to load item details: ' + error, 'error');
            }
        });
    };

    /**
     * Populate the edit modal with item data
     */
    function populateEditModal(item, containerId) {
        console.log('=== POPULATING EDIT MODAL ===');
        console.log('Item data:', item);
        console.log('Container ID:', containerId);
        
        // Check if modal elements exist
        if ($('#editItemModal').length === 0) {
            console.error('Edit modal not found in DOM');
            return;
        }
        
        // Reset form validation and clear any previous errors
        $('#editItemForm')[0].reset();
        $('#editItemForm').removeClass('was-validated');
        $('.form-control').removeClass('is-invalid is-valid');
        
        // Remove all required attributes first to avoid validation conflicts
        $('#editItemForm input, #editItemForm select, #editItemForm textarea').removeAttr('required');
        
        // Set hidden fields
        $('#editItemId').val(item.id);
        $('#editItemContainerId').val(containerId);
        $('#editItemType').val(item.item_type);
        
        // Show/hide sections based on item type
        console.log('Item type for display:', item.item_type);
        if (item.item_type === 'box') {
            // Show box section, hide item section
            console.log('Showing box section, hiding item section');
            $('#editBoxSection').show();
            $('#editItemSection').hide();
        } else {
            // Show item section, hide box section
            console.log('Showing item section, hiding box section');
            $('#editItemSection').show();
            $('#editBoxSection').hide();
        }
        
        // Populate form fields based on item type
        if (item.item_type === 'box') {
            // Populate box fields
            $('#editBoxCode').val(item.item_code || '');
            $('#editBoxName').val(item.name || '');
            $('#editBoxDescription').val(item.description || '');
            $('#editBoxType').val(item.brand || '');
            $('#editBoxUnitCost').val(item.unit_cost || 0);
            $('#editBoxQuantity').val(item.quantity_in_container || 1);
        } else {
            // Populate item fields
            $('#editItemCode').val(item.item_code || '');
            $('#editItemName').val(item.name || '');
            $('#editItemDescription').val(item.description || '');
            $('#editItemCategory').val(item.category_id || '');
            $('#editItemSubcategory').val(item.subcategory_id || '');
            $('#editItemBrand').val(item.brand || '');
            $('#editItemSize').val(item.size || '');
            $('#editItemColor').val(item.color || '');
            $('#editItemMaterial').val(item.material || '');
            $('#editItemQuantity').val(item.quantity_in_container || 1);
            $('#editItemUnitCost').val(item.unit_cost || 0);
            $('#editItemSellingPrice').val(item.selling_price || 0);
        }
        
        console.log('Form fields populated:');
        if (item.item_type === 'box') {
            console.log('- Box Number:', $('#editBoxCode').val());
            console.log('- Box Name:', $('#editBoxName').val());
            console.log('- Box Unit Cost:', $('#editBoxUnitCost').val());
        } else {
            console.log('- Item Code:', $('#editItemCode').val());
            console.log('- Name:', $('#editItemName').val());
            console.log('- Unit Cost:', $('#editItemUnitCost').val());
            console.log('- Selling Price:', $('#editItemSellingPrice').val());
        }
        
        // Load subcategories if category is selected
        if (item.category_id) {
            loadSubcategoriesForEdit(item.category_id, item.subcategory_id);
        }
    }

    /**
     * Load subcategories for edit modal
     */
    function loadSubcategoriesForEdit(categoryId, selectedSubcategoryId) {
        $.ajax({
            url: '../ajax/get_subcategories.php',
            type: 'POST',
            data: {
                category_id: categoryId,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                const subcategorySelect = $('#editItemSubcategory');
                subcategorySelect.empty().append('<option value="">Select Subcategory</option>');
                
                if (response.success && response.data) {
                    response.data.forEach(function(subcategory) {
                        const selected = subcategory.id == selectedSubcategoryId ? 'selected' : '';
                        subcategorySelect.append(`<option value="${subcategory.id}" ${selected}>${subcategory.name}</option>`);
                    });
                }
            },
            error: function() {
                console.error('Failed to load subcategories for edit');
            }
        });
    }

    // Edit Item button click handler
    $(document).on('click', '.edit-item', function() {
        console.log('=== EDIT ITEM BUTTON CLICKED ===');
        const itemId = $(this).data('item-id');
        const containerId = $('#manageItemsModal').data('container-id');
        
        console.log('Button element:', this);
        console.log('Item ID from data:', itemId);
        console.log('Container ID from modal:', containerId);
        
        if (!itemId || !containerId) {
            console.error('Missing IDs - Item ID:', itemId, 'Container ID:', containerId);
            Swal.fire('Error', 'Item or container ID not found', 'error');
            return;
        }
        
        // Load item details and show edit modal
        loadItemForEdit(itemId, containerId);
    });

    // Edit Item form submission
    $(document).on('submit', '#editItemForm', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const containerId = formData.get('container_id');
        
        $.ajax({
            url: '../ajax/process_container.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', response.message, 'success');
                    $('#editItemModal').modal('hide');
                    // Reload container items
                    loadContainerItems(containerId);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Edit item error:', {xhr, status, error});
                Swal.fire('Error', 'Failed to update item: ' + error, 'error');
            }
        });
    });

    // Category change handler for edit modal
    $(document).on('change', '#editItemCategory', function() {
        const categoryId = $(this).val();
        if (categoryId) {
            loadSubcategoriesForEdit(categoryId, null);
        } else {
            $('#editItemSubcategory').empty().append('<option value="">Select Subcategory</option>');
        }
    });

    // Reset edit modal when hidden
    $('#editItemModal').on('hidden.bs.modal', function() {
        console.log('Edit modal hidden, resetting form');
        const $form = $(this).find('form');
        $form[0].reset();
        $form.removeClass('was-validated');
        $('.form-control').removeClass('is-invalid is-valid');
        
        // Hide both sections
        $('#editBoxSection').hide();
        $('#editItemSection').hide();
        
        // Clear hidden fields
        $('#editItemId').val('');
        $('#editItemContainerId').val('');
        $('#editItemType').val('');
    });
}); 

/**
 * Check if an item code already exists in the current container or inventory
 */
function isDuplicateItemCode(code) {
    console.log('=== CHECKING DUPLICATE CODE ===');
    console.log('Code to check:', code);
    console.log('window.existingInventoryCodes:', window.existingInventoryCodes);
    
    if (!code || code === 'No Code' || code.trim() === '') {
        console.log('Code is empty or "No Code", returning false');
        return false;
    }
    
    // Check if code exists in current container items (for new containers)
    if (window.containerCreationItems && Array.isArray(window.containerCreationItems)) {
        const existsInContainer = window.containerCreationItems.some(item => 
            item.data.code && item.data.code === code
        );
        console.log('Exists in current container:', existsInContainer);
        if (existsInContainer) return true;
    }
    
    // Check if code exists in current container's existing items (for managing containers)
    const currentContainerItems = getCurrentContainerItems();
    if (currentContainerItems && Array.isArray(currentContainerItems)) {
        const existsInCurrentContainer = currentContainerItems.some(item => 
            item.code && item.code === code
        );
        console.log('Exists in current container items:', existsInCurrentContainer);
        if (existsInCurrentContainer) return true;
    }
    
    // Check if code exists in inventory_items table
    const existsInInventory = window.existingInventoryCodes && 
        window.existingInventoryCodes.includes(code);
    console.log('Exists in inventory:', existsInInventory);
    console.log('Final result:', existsInInventory);
    
    return existsInInventory;
}

/**
 * Get current container items for duplicate checking
 */
function getCurrentContainerItems() {
    // Check if we're in manage items modal
    const containerId = $('#manageItemsModal').data('container-id');
    if (containerId) {
        // Return items from the current container table
        const items = [];
        $('#containerItemsTable tbody tr').each(function() {
            const itemData = $(this).data('item-data');
            if (itemData && itemData.code) {
                items.push({ code: itemData.code });
            }
        });
        return items;
    }
    return null;
}

/**
 * Load existing inventory codes for duplicate checking
 */
function loadExistingInventoryCodes() {
    console.log('=== LOADING EXISTING INVENTORY CODES ===');
    console.log('CSRF token:', $('input[name="csrf_token"]').val());
    
    $.ajax({
        url: '../ajax/get_inventory.php',
        type: 'POST',
        data: {
            csrf_token: $('input[name="csrf_token"]').val(),
            draw: 1,
            start: 0,
            length: 10000, // Get all items
            search: { value: '' },
            category: '',
            item_code: '',
            name: '',
            stock_status: '',
            status: 'active'
        },
        dataType: 'json',
        success: function(response) {
            console.log('Inventory response:', response);
            if (response.data && Array.isArray(response.data)) {
                window.existingInventoryCodes = response.data
                    .map(item => item.item_code)
                    .filter(code => code && code !== 'No Code');
                console.log('Loaded inventory codes:', window.existingInventoryCodes);
                console.log('Total codes loaded:', window.existingInventoryCodes.length);
            } else {
                console.error('Invalid response format:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load existing inventory codes:', {xhr, status, error});
            console.error('Response text:', xhr.responseText);
        }
    });
}

/**
 * Test function to debug duplicate checking
 */
function testDuplicateCheck() {
    console.log('=== TESTING DUPLICATE CHECK ===');
    console.log('window.existingInventoryCodes:', window.existingInventoryCodes);
    console.log('window.existingInventoryCodes length:', window.existingInventoryCodes ? window.existingInventoryCodes.length : 'undefined');
    console.log('window.containerCreationItems:', window.containerCreationItems);
    
    // Test with a sample code
    const testCode = '001';
    const result = isDuplicateItemCode(testCode);
    console.log(`Test with code "${testCode}":`, result);
    
    // Show result to user
    Swal.fire('Debug Info', `
        <div class="text-left">
            <strong>Inventory Codes Loaded:</strong> ${window.existingInventoryCodes ? window.existingInventoryCodes.length : 'None'}<br>
            <strong>Test Code "${testCode}":</strong> ${result ? 'DUPLICATE FOUND' : 'No Duplicate'}<br>
            <strong>Codes:</strong> ${window.existingInventoryCodes ? window.existingInventoryCodes.slice(0, 10).join(', ') : 'None'}
        </div>
    `, 'info');
}