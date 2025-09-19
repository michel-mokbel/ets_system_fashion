$(document).ready(function() {
    function loadInvoices() {
        const params = {
            action: 'list',
            from_date: $('#filterFromDate').val(),
            to_date: $('#filterToDate').val(),
            status: $('#filterStatus').val()
        };
        $.ajax({
            url: '../ajax/store_invoices.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(inv) {
                        html += `<tr>
                            <td>${inv.invoice_number}</td>
                            <td>${inv.created_at}</td>
                            <td>${inv.customer_name || ''}</td>
                            <td>${parseFloat(inv.total_amount).toFixed(2)}</td>
                            <td><span class="badge bg-${inv.payment_status === 'paid' ? 'success' : (inv.payment_status === 'pending' ? 'warning' : 'secondary')}">${inv.payment_status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-info view-invoice-btn" data-id="${inv.id}"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-primary print-invoice-btn" data-id="${inv.id}"><i class="bi bi-printer"></i></button>
                            </td>
                        </tr>`;
                    });
                    $('#storeInvoicesTable tbody').html(html);
                } else {
                    $('#storeInvoicesTable tbody').html('<tr><td colspan="6">No invoices found.</td></tr>');
                }
            },
            error: function() {
                $('#storeInvoicesTable tbody').html('<tr><td colspan="6">Error loading invoices.</td></tr>');
            }
        });
    }

    $('#invoiceFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadInvoices();
    });

    // Initial load
    loadInvoices();

    // TODO: Implement view/print actions
}); 