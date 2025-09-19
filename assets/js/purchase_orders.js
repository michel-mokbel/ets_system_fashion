/**
 * Purchase Orders Management JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with server-side processing
    const purchaseOrdersTable = $('#purchaseOrdersTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        ajax: {
            url: '../ajax/get_purchase_orders.php',
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
                    text: 'Failed to load purchase orders data. Please refresh the page.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: 'po_number' },
            { data: 'supplier_name' },
            { data: 'order_date' },
            { data: 'expected_delivery_date' },
            { 
                data: 'total_amount',
                render: function(data) {
                    return formatCurrency(data);
                }
            },
            {
                data: 'status',
                render: function(data) {
                    let badgeClass = '';
                    switch (data) {
                        case 'draft':
                            badgeClass = 'bg-secondary';
                            break;
                        case 'pending':
                            badgeClass = 'bg-warning text-dark';
                            break;
                        case 'approved':
                            badgeClass = 'bg-info';
                            break;
                        case 'received':
                            badgeClass = 'bg-success';
                            break;
                        case 'cancelled':
                            badgeClass = 'bg-danger';
                            break;
                        default:
                            badgeClass = 'bg-secondary';
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
                            <button class="btn btn-sm btn-info view-po" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary edit-po" data-id="${data}" 
                                    data-bs-toggle="tooltip" title="Edit Purchase Order">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-po" data-id="${data}"
                                    data-bs-toggle="tooltip" title="Delete Purchase Order">
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
            emptyTable: 'No purchase orders found',
            zeroRecords: 'No matching purchase orders found',
            info: 'Showing _START_ to _END_ of _TOTAL_ purchase orders',
            infoEmpty: 'Showing 0 to 0 of 0 purchase orders',
            infoFiltered: '(filtered from _MAX_ total purchase orders)',
            search: '',
            searchPlaceholder: 'Search purchase orders...',
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
        },
        columnDefs: [
            {
                targets: -1, // Last column (actions)
                className: 'text-center',
                orderable: false,
                render: function(data, type, row) {
                    return `<div class="action-buttons">
                                <button class="btn btn-view view-po" data-id="${row.id}" data-bs-toggle="tooltip" title="View">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-edit edit-po" data-id="${row.id}" data-bs-toggle="tooltip" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-delete delete-po" data-id="${row.id}" data-bs-toggle="tooltip" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>`;
                }
            },
            {
                targets: -2, // Status column
                className: 'text-center',
                render: function(data, type, row) {
                    let statusClass = '';
                    switch(data.toLowerCase()) {
                        case 'approved':
                            statusClass = 'status-approved';
                            break;
                        case 'pending':
                            statusClass = 'status-pending';
                            break;
                        case 'draft':
                            statusClass = 'status-draft';
                            break;
                        case 'cancelled':
                            statusClass = 'status-cancelled';
                            break;
                        default:
                            statusClass = 'badge-secondary';
                    }
                    return `<span class="badge ${statusClass}">${data}</span>`;
                }
            }
        ]
    });
    
    // Apply filters when the filter form is submitted
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        purchaseOrdersTable.ajax.reload();
    });

    // Initialize date range picker
    if ($.fn.daterangepicker) {
        $('.date-range').daterangepicker({
            opens: 'left',
            autoUpdateInput: false,
            locale: {
                cancelLabel: 'Clear',
                format: 'YYYY-MM-DD'
            }
        });

        $('.date-range').on('apply.daterangepicker', function(ev, picker) {
            $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        });

        $('.date-range').on('cancel.daterangepicker', function() {
            $(this).val('');
        });
    }

    // Initialize datepickers
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: 'c-10:c+10'
        });
    }

    // Handle view button click
    $(document).on('click', '.view-po', function() {
        const poId = $(this).data('id');
        loadPurchaseOrderDetails(poId);
    });
    
    // Handle edit button click
    $(document).on('click', '.edit-po', function() {
        const poId = $(this).data('id');
        editPurchaseOrder(poId);
    });
    
    // Handle delete button click
    $(document).on('click', '.delete-po', function() {
        const poId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this purchase order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                deletePurchaseOrder(poId);
            }
        });
    });

    // Handle status action buttons
    $('#approvePurchaseOrderBtn').on('click', function() {
        const poId = $(this).data('id');
        updatePurchaseOrderStatus(poId, 'approved');
    });

    $('#cancelPurchaseOrderBtn').on('click', function() {
        const poId = $(this).data('id');
        
        Swal.fire({
            title: 'Confirm Cancellation',
            text: 'Are you sure you want to cancel this purchase order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, cancel it',
            cancelButtonText: 'No'
        }).then((result) => {
            if (result.isConfirmed) {
                updatePurchaseOrderStatus(poId, 'cancelled');
            }
        });
    });

    $('#receiveItemsBtn').on('click', function() {
        const poId = $(this).data('id');
        $('#receiveSection').removeClass('d-none');
        $('html, body').animate({
            scrollTop: $('#receiveSection').offset().top - 100
        }, 500);
    });

    $('#printPurchaseOrderBtn').on('click', function() {
        const poId = $(this).data('id');
        printPurchaseOrder(poId);
    });

    // Handle items form
    $('#addItemRow').on('click', function() {
        addItemRow();
    });

    $(document).on('click', '.remove-item', function() {
        $(this).closest('tr').remove();
        updateItemIndexes();
        calculateTotals();
    });

    $(document).on('change', '.item-select, .item-quantity, .item-price', function() {
        calculateRowTotal($(this).closest('tr'));
        calculateTotals();
    });

    // Handle receiving items form submission
    $('#receiveItemsForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'receive');
        
        $.ajax({
            url: '../ajax/process_purchase_order.php',
            type: 'POST',
            data: Object.fromEntries(formData),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#viewPurchaseOrderModal').modal('hide');
                        // Refresh the page after receiving items
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to connect to the server',
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    });

    // Handle Purchase Order form submission using the new buttons
    $('.submit-po').on('click', function() {
        const status = $(this).data('status');
        const form = $('#addPurchaseOrderForm')[0];
        
        // Set the status value in the hidden field
        $('#po_status').val(status);
        
        console.log('Button clicked, status set to:', status);
        
        // Validate form
        if (!form.checkValidity()) {
            $(form).addClass('was-validated');
            return;
        }
        
        // Check if there are items
        if ($('.item-row').length === 0) {
            Swal.fire({
                title: 'Error!',
                text: 'Please add at least one item to the purchase order',
                icon: 'error',
                timer: 2000,
                showConfirmButton: false
            });
            return;
        }
        
        // Create FormData from the form
        const formData = new FormData(form);
        
        console.log('Action:', formData.get('action'));
        console.log('Status being submitted:', formData.get('status'));
        
        // Format dates properly before submission
        const orderDate = $('#orderDate').val();
        const expectedDelivery = $('#expectedDelivery').val();
        
        // Check if expected_delivery is in MM/DD/YYYY format and convert to YYYY-MM-DD
        if (expectedDelivery && expectedDelivery.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
            const parts = expectedDelivery.split('/');
            const formattedDate = `${parts[2]}-${parts[0]}-${parts[1]}`;
            formData.set('expected_delivery', formattedDate);
        }
        
        // Also check order_date format and convert if needed
        if (orderDate && orderDate.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
            const parts = orderDate.split('/');
            const formattedDate = `${parts[2]}-${parts[0]}-${parts[1]}`;
            formData.set('order_date', formattedDate);
        }
        
        // Submit the form via AJAX
        $.ajax({
            url: '../ajax/process_purchase_order.php',
            type: 'POST',
            data: Object.fromEntries(formData),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Refresh the page after success
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to connect to the server',
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    });
});

/**
 * Add a new item row to the purchase order
 */
function addItemRow() {
    const rowCount = $('.item-row').length;
    const newIndex = rowCount;
    
    // Clone the first row template
    const newRow = $('.item-row:first').clone();
    
    // Reset form elements
    newRow.find('select.item-select').val('').attr('name', `items[${newIndex}][item_id]`);
    newRow.find('input.item-quantity').val(1).attr('name', `items[${newIndex}][quantity]`);
    newRow.find('input.item-price').val('0.00').attr('name', `items[${newIndex}][unit_price]`);
    newRow.find('input.item-total').val('');
    newRow.find('button.remove-item').prop('disabled', false);
    
    // Append the new row
    $('#poItemsTable tbody').append(newRow);
}

/**
 * Update item indexes after removing an item
 */
function updateItemIndexes() {
    $('.item-row').each(function(index) {
        $(this).find('select.item-select').attr('name', `items[${index}][item_id]`);
        $(this).find('input.item-quantity').attr('name', `items[${index}][quantity]`);
        $(this).find('input.item-price').attr('name', `items[${index}][unit_price]`);
    });
}

/**
 * Calculate the total for a row
 * @param {Object} row - The row element
 */
function calculateRowTotal(row) {
    const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
    const price = parseFloat(row.find('.item-price').val()) || 0;
    const total = quantity * price;
    row.find('.item-total').val(formatCurrency(total, false));
}

/**
 * Calculate all totals
 */
function calculateTotals() {
    let grandTotal = 0;
    
    $('.item-row').each(function() {
        const quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
        const price = parseFloat($(this).find('.item-price').val()) || 0;
        const rowTotal = quantity * price;
        grandTotal += rowTotal;
    });
    
    $('#totalAmount').val(formatCurrency(grandTotal, false));
}

/**
 * Format currency
 * @param {number} amount - The amount to format
 * @param {boolean} symbol - Whether to include the currency symbol
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, symbol = true) {
    const value = parseFloat(amount || 0).toFixed(2);
    return symbol ? `CFA ${value}` : value;
}

/**
 * Load purchase order details
 * @param {number} poId - The ID of the purchase order to load
 */
function loadPurchaseOrderDetails(poId) {
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we load the purchase order data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch purchase order data via AJAX
    $.ajax({
        url: '../ajax/get_purchase_order.php',
        type: 'POST',
        data: {
            po_id: poId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const po = response.data.purchase_order;
                const supplier = response.data.supplier;
                const items = response.data.items;
                
                // Set purchase order details
                $('#viewPoNumber').text(po.po_number);
                $('#viewOrderDate').text(formatDate(po.order_date));
                $('#viewExpectedDelivery').text(formatDate(po.expected_delivery_date));
                
                // Set status with appropriate badge
                let statusBadgeClass = '';
                switch (po.status) {
                    case 'draft':
                        statusBadgeClass = 'bg-secondary';
                        break;
                    case 'pending':
                        statusBadgeClass = 'bg-warning text-dark';
                        break;
                    case 'approved':
                        statusBadgeClass = 'bg-info';
                        break;
                    case 'received':
                        statusBadgeClass = 'bg-success';
                        break;
                    case 'cancelled':
                        statusBadgeClass = 'bg-danger';
                        break;
                }
                $('#viewStatus').html(`<span class="badge ${statusBadgeClass}">${po.status}</span>`);
                
                $('#viewNotes').text(po.notes || 'No notes provided');
                
                // Set supplier details
                $('#viewSupplierName').text(supplier.name);
                $('#viewContactPerson').text(supplier.contact_person || 'Not specified');
                $('#viewEmail').text(supplier.email || 'Not specified');
                $('#viewPhone').text(supplier.phone || 'Not specified');
                $('#viewAddress').text(supplier.address || 'No address provided');
                
                // Populate items table
                let itemsHtml = '';
                let receiveItemsHtml = '';
                
                items.forEach(item => {
                    itemsHtml += `
                        <tr>
                            <td>${item.item_code}</td>
                            <td>${item.item_name}</td>
                            <td>${item.quantity}</td>
                            <td>${formatCurrency(item.unit_price)}</td>
                            <td>${formatCurrency(item.total_price)}</td>
                        </tr>
                    `;
                    
                    // Only add to receive items if PO is approved
                    if (po.status === 'approved') {
                        receiveItemsHtml += `
                            <tr>
                                <td>${item.item_name} (${item.item_code})</td>
                                <td>${item.quantity}</td>
                                <td>
                                    <input type="hidden" name="items[${item.id}][item_id]" value="${item.item_id}">
                                    <input type="number" class="form-control" name="items[${item.id}][received_quantity]" 
                                           min="1" max="${item.quantity}" value="${item.quantity}" required>
                                </td>
                            </tr>
                        `;
                    }
                });
                
                $('#viewItemsTable').html(itemsHtml);
                $('#receiveItemsTable').html(receiveItemsHtml);
                $('#viewTotalAmount').text(formatCurrency(po.total_amount));
                
                // Set action button states based on status
                $('#receivePurchaseOrderId').val(po.id);
                $('#approvePurchaseOrderBtn').data('id', po.id).toggle(po.status === 'pending');
                $('#cancelPurchaseOrderBtn').data('id', po.id).toggle(['draft', 'pending', 'approved'].includes(po.status));
                $('#receiveItemsBtn').data('id', po.id).toggle(po.status === 'approved');
                $('#printPurchaseOrderBtn').data('id', po.id);
                
                // Hide receive section by default
                $('#receiveSection').addClass('d-none');
                
                // Show view modal
                const viewModal = new bootstrap.Modal(document.getElementById('viewPurchaseOrderModal'));
                viewModal.show();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to load purchase order data',
                    icon: 'error',
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
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

/**
 * Edit a purchase order
 * @param {number} poId - The ID of the purchase order to edit
 */
function editPurchaseOrder(poId) {
    // Show loading indicator
    Swal.fire({
        title: 'Loading...',
        html: 'Please wait while we load the purchase order data',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Fetch purchase order data via AJAX
    $.ajax({
        url: '../ajax/get_purchase_order.php',
        type: 'POST',
        data: {
            po_id: poId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            Swal.close();
            
            if (response.success) {
                const po = response.data.purchase_order;
                
                // Create a simple edit form
                Swal.fire({
                    title: 'Edit Purchase Order',
                    html: `
                        <form id="editPOForm" class="text-start">
                            <input type="hidden" name="po_id" value="${po.id}">
                            <input type="hidden" name="csrf_token" value="${$('input[name="csrf_token"]').val()}">
                            <input type="hidden" name="action" value="edit">
                            
                            <div class="mb-3">
                                <label for="editPoNumber" class="form-label">PO Number</label>
                                <input type="text" class="form-control" id="editPoNumber" name="po_number" value="${po.po_number}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editSupplier" class="form-label">Supplier</label>
                                <select class="form-control" id="editSupplier" name="supplier_id" required>
                                    ${$('#supplier').html()}
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editOrderDate" class="form-label">Order Date</label>
                                <input type="text" class="form-control datepicker" id="editOrderDate" name="order_date" value="${po.order_date}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editExpectedDelivery" class="form-label">Expected Delivery Date</label>
                                <input type="text" class="form-control datepicker" id="editExpectedDelivery" name="expected_delivery" value="${po.expected_delivery_date}" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editStatus" class="form-label">Status</label>
                                <select class="form-control" id="editStatus" name="status">
                                    <option value="draft" ${po.status === 'draft' ? 'selected' : ''}>Draft</option>
                                    <option value="pending" ${po.status === 'pending' ? 'selected' : ''}>Pending</option>
                                    <option value="approved" ${po.status === 'approved' ? 'selected' : ''}>Approved</option>
                                    <option value="received" ${po.status === 'received' ? 'selected' : ''}>Received</option>
                                    <option value="cancelled" ${po.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="editNotes" class="form-label">Notes</label>
                                <textarea class="form-control" id="editNotes" name="notes" rows="3">${po.notes || ''}</textarea>
                            </div>
                        </form>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Save Changes',
                    cancelButtonText: 'Cancel',
                    didOpen: () => {
                        // Set selected supplier
                        $('#editSupplier').val(po.supplier_id);
                        
                        // Initialize datepickers
                        $('.datepicker').datepicker({
                            dateFormat: 'yy-mm-dd',
                            changeMonth: true,
                            changeYear: true,
                            yearRange: 'c-10:c+10'
                        });
                    },
                    preConfirm: () => {
                        // Validate form
                        const form = document.getElementById('editPOForm');
                        if (!form.checkValidity()) {
                            Swal.showValidationMessage('Please fill all required fields');
                            return false;
                        }
                        
                        // Gather form data
                        const formData = new FormData(form);
                        
                        // Format dates if needed
                        const orderDate = $('#editOrderDate').val();
                        const expectedDelivery = $('#editExpectedDelivery').val();
                        
                        // Check if dates are in MM/DD/YYYY format and convert to YYYY-MM-DD
                        if (orderDate && orderDate.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                            const parts = orderDate.split('/');
                            const formattedDate = `${parts[2]}-${parts[0]}-${parts[1]}`;
                            formData.set('order_date', formattedDate);
                        }
                        
                        if (expectedDelivery && expectedDelivery.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                            const parts = expectedDelivery.split('/');
                            const formattedDate = `${parts[2]}-${parts[0]}-${parts[1]}`;
                            formData.set('expected_delivery', formattedDate);
                        }
                        
                        // Submit via AJAX
                        return $.ajax({
                            url: '../ajax/process_purchase_order.php',
                            type: 'POST',
                            data: Object.fromEntries(formData),
                            dataType: 'json'
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: result.value.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Refresh the page after editing
                            window.location.reload();
                        });
                    } else if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Error',
                            text: result.value.message,
                            icon: 'error',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message || 'Failed to load purchase order data',
                    icon: 'error',
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
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

/**
 * Delete a purchase order
 * @param {number} poId - The ID of the purchase order to delete
 */
function deletePurchaseOrder(poId) {
    $.ajax({
        url: '../ajax/process_purchase_order.php',
        type: 'POST',
        data: {
            action: 'delete',
            po_id: poId,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'Success!',
                    text: response.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Refresh the page after deleting
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message,
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to connect to the server',
                icon: 'error',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

/**
 * Update purchase order status
 * @param {number} poId - The ID of the purchase order
 * @param {string} status - The new status
 */
function updatePurchaseOrderStatus(poId, status) {
    $.ajax({
        url: '../ajax/process_purchase_order.php',
        type: 'POST',
        data: {
            action: 'update_status',
            po_id: poId,
            status: status,
            csrf_token: $('input[name="csrf_token"]').val()
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    title: 'Success!',
                    text: response.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    $('#viewPurchaseOrderModal').modal('hide');
                    // Refresh the page after updating status
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: response.message,
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error!',
                text: 'Failed to connect to the server',
                icon: 'error',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

/**
 * Print purchase order
 * @param {number} poId - The ID of the purchase order to print
 */
function printPurchaseOrder(poId) {
    window.open(`../print/purchase_order.php?id=${poId}`, '_blank');
}

/**
 * Format date for display
 * @param {string} dateString - The date string to format
 * @returns {string} Formatted date string
 */
function formatDate(dateString) {
    if (!dateString) return 'Not specified';
    
    const date = new Date(dateString);
    return date.toISOString().split('T')[0];
} 