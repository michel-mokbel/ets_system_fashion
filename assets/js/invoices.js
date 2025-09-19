/**
 * Admin invoice browser controller.
 *
 * Responsibilities:
 * - Initialize the invoices DataTable, propagate filter form values, and render payment status badges.
 * - Bind filter submissions and row action buttons (view/export) to the appropriate AJAX endpoints and modals.
 * - Display SweetAlert error messaging when the API returns failures so administrators receive immediate feedback.
 *
 * Dependencies:
 * - jQuery, DataTables, SweetAlert, and Bootstrap modals.
 * - Backend endpoints: `../ajax/get_invoices.php` for listing and `../ajax/get_invoice.php` for the modal detail fetch triggered further down in the script.
 */
$(document).ready(function() {
    if (window.invoicesTableInitialized) {
        // Already initialized, do nothing
        return;
    }
    window.invoicesTableInitialized = true;

    const invoicesTable = $('#invoicesTable').DataTable({
        processing: true,
        serverSide: false,
        searching: false,
        ajax: {
            url: '../ajax/get_invoices.php',
            type: 'GET',
            data: function(d) {
                const formData = $('#filterForm').serializeArray();
                formData.forEach(f => { d[f.name] = f.value; });
                d.action = 'list';
            },
            dataSrc: function(json) {
                if (!json.success) {
                    Swal.fire({
                        title: 'Error!',
                        text: json.message || 'Failed to load invoices.',
                        icon: 'error',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    return [];
                }
                return json.data;
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load invoices.',
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: 'invoice_number' },
            { data: 'customer_name' },
            { data: 'total_amount', render: d => 'CFA ' + parseFloat(d).toFixed(2) },
            { data: 'payment_status', render: d => `<span class="badge bg-${d === 'paid' ? 'success' : d === 'refunded' ? 'warning' : 'secondary'}">${d}</span>` },
            { data: 'created_at' },
            { data: 'id', orderable: false, render: function(data, type, row) {
                return `<button class="btn btn-sm btn-info view-invoice-btn" data-id="${data}"><i class="bi bi-eye"></i></button>`;
            }}
        ],
        language: {
            processing: '<div class="spinner-border text-primary" role="status"></div>',
            emptyTable: 'No invoices found',
            zeroRecords: 'No matching invoices found',
            search: '',
            searchPlaceholder: 'Search invoices...',
            lengthMenu: '_MENU_ per page',
            info: 'Showing _START_ to _END_ of _TOTAL_ invoices',
            infoEmpty: 'Showing 0 to 0 of 0 invoices',
            infoFiltered: '(filtered from _MAX_ total invoices)'
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>'
    });

    // Filter form submit
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        console.log('=== FILTER FORM SUBMITTED ===');
        console.log('Form submitted, reloading table...');
        
        // Show loading state on the submit button
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Searching...');
        
        // Reload table
        invoicesTable.ajax.reload(function() {
            // Re-enable button after table reloads
            submitBtn.prop('disabled', false).html(originalText);
        });
    });

    // Add Enter key support for search inputs
    $('#filterForm input[name="invoice_number"], #filterForm input[name="customer_name"]').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#filterForm').submit();
        }
    });

    // Clear all filters
    $('#clearFilters').on('click', function() {
        $('#filterForm')[0].reset();
        // Set today's date as default for end date
        const today = new Date().toISOString().split('T')[0];
        $('#endDate').val(today);
        // Set 30 days ago as default for start date
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        $('#startDate').val(thirtyDaysAgo.toISOString().split('T')[0]);
        
        console.log('=== FILTERS CLEARED ===');
        invoicesTable.ajax.reload();
    });

    // Set default date range (last 30 days)
    function setDefaultDateRange() {
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        $('#endDate').val(today.toISOString().split('T')[0]);
        $('#startDate').val(thirtyDaysAgo.toISOString().split('T')[0]);
    }

    // Initialize default date range
    setDefaultDateRange();

    // Function to highlight active filters
    function highlightActiveFilters() {
        const form = $('#filterForm')[0];
        const formData = new FormData(form);
        
        // Remove all active filter highlights
        $('.form-control, .form-select').removeClass('border-primary');
        
        // Highlight fields with values
        for (const [key, value] of formData.entries()) {
            if (value && key !== 'csrf_token') {
                $(`[name="${key}"]`).addClass('border-primary');
            }
        }
    }

    // Highlight active filters on form change
    $('#filterForm input, #filterForm select').on('change input', highlightActiveFilters);
    
    // Initial highlight
    highlightActiveFilters();

    // Quick date range buttons
    $('[data-days]').on('click', function() {
        const days = parseInt($(this).data('days'));
        const today = new Date();
        const startDate = new Date();
        startDate.setDate(today.getDate() - days);
        
        $('#startDate').val(startDate.toISOString().split('T')[0]);
        $('#endDate').val(today.toISOString().split('T')[0]);
        
        console.log(`=== QUICK DATE RANGE SET: Last ${days} days ===`);
        $('#filterForm').submit();
    });

    // View invoice details
    $(document).on('click', '.view-invoice-btn', function() {
        const invoiceId = $(this).data('id');
        console.log('View invoice clicked for ID:', invoiceId); // Debug log
        
        if (!invoiceId) {
            Swal.fire('Error', 'Invalid invoice ID', 'error');
            return;
        }
        
        Swal.fire({
            title: 'Loading...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        $.ajax({
            url: '../ajax/get_invoice.php',
            type: 'POST',
            data: { 
                invoice_id: invoiceId, 
                csrf_token: $('#csrf_token').val() 
            },
            dataType: 'json',
            success: function(response) {
                Swal.close();
                console.log('Invoice response:', response); // Debug log
                
                if (response.success && response.data) {
                    const inv = response.data;
                    let html = `
                        <div class="mb-3">
                            <h6 class="text-primary">Invoice Information</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Invoice #:</strong> ${inv.invoice_number}<br>
                                    <strong>Customer:</strong> ${inv.customer_name || 'N/A'}<br>
                                    <strong>Store:</strong> ${inv.store_name || 'N/A'}
                                </div>
                                <div class="col-md-6">
                        <strong>Date:</strong> ${inv.created_at}<br>
                        <strong>Subtotal:</strong> CFA ${parseFloat(inv.subtotal).toFixed(2)}<br>
                        <strong>Total Discount:</strong> <span class="text-success">-CFA ${(inv.items.reduce((sum, item) => sum + (parseFloat(item.discount_amount) || 0), 0) + parseFloat(inv.discount_amount)).toFixed(2)}</span><br>
                        <strong>Net Total:</strong> CFA ${parseFloat(inv.total_amount).toFixed(2)}<br>
                        <strong>Status:</strong> <span class="badge bg-${inv.payment_status === 'paid' ? 'success' : inv.payment_status === 'refunded' ? 'warning' : 'secondary'}">${inv.payment_status}</span>
                                </div>
                            </div>
                        </div>`;
                    
                    if (inv.items && inv.items.length > 0) {
                        html += `
                            <hr>
                            <h6 class="text-primary">Items</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th>Code</th>
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th>Subtotal</th>
                                            <th>Discount</th>
                                            <th>Net Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                        inv.items.forEach(item => {
                            const subtotal = item.quantity * item.unit_price;
                            const discount = parseFloat(item.discount_amount || 0);
                            const discountPercentage = parseFloat(item.discount_percentage || 0);
                            const netTotal = subtotal - discount;
                            
                            html += `
                                <tr>
                                    <td>${item.name || 'N/A'}</td>
                                    <td>${item.item_code || 'N/A'}</td>
                                    <td>${item.quantity}</td>
                                    <td>CFA ${parseFloat(item.unit_price).toFixed(2)}</td>
                                    <td>CFA ${subtotal.toFixed(2)}</td>
                                    <td>${discount > 0 ? `<span class="text-success">-${((discount / subtotal) * 100).toFixed(2)}% <br>(-CFA ${discount.toFixed(2)})</span>` : '-'}</td>
                                    <td>CFA ${netTotal.toFixed(2)}</td>
                                </tr>`;
                        });
                        html += `</tbody></table></div>`;
                    } else {
                        html += '<div class="alert alert-warning">No items found for this invoice.</div>';
                    }
                    
                    $('#invoiceDetailsContent').html(html);
                    $('#invoiceDetailsModal').modal('show');
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Failed to load invoice details.',
                        icon: 'error',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.close();
                console.error('AJAX Error:', xhr, status, error); // Debug log
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load invoice details. Please try again.',
                    icon: 'error',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    });
}); 