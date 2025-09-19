$(document).ready(function() {
    function loadReturns() {
        const params = {
            action: 'list',
            from_date: $('#filterFromDate').val(),
            to_date: $('#filterToDate').val(),
            status: $('#filterStatus').val()
        };
        $.ajax({
            url: '../ajax/store_returns.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(ret) {
                        html += `<tr>
                            <td>${ret.return_number}</td>
                            <td>${ret.return_date}</td>
                            <td>${ret.original_invoice_number || ''}</td>
                            <td>${parseFloat(ret.total_amount).toFixed(2)}</td>
                            <td><span class="badge bg-${ret.status === 'processed' ? 'success' : (ret.status === 'pending' ? 'warning' : 'secondary')}">${ret.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-info view-return-btn" data-id="${ret.id}"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-primary print-return-btn" data-id="${ret.id}"><i class="bi bi-printer"></i></button>
                            </td>
                        </tr>`;
                    });
                    $('#storeReturnsTable tbody').html(html);
                } else {
                    $('#storeReturnsTable tbody').html('<tr><td colspan="6">No returns found.</td></tr>');
                }
            },
            error: function() {
                $('#storeReturnsTable tbody').html('<tr><td colspan="6">Error loading returns.</td></tr>');
            }
        });
    }

    $('#returnFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadReturns();
    });

    // Initial load
    loadReturns();

    // TODO: Implement view/print actions
}); 