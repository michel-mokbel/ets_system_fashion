/**
 * Admin dashboard controller.
 *
 * Responsibilities:
 * - Fetch top-level KPI metrics, recent sales activity, and low-stock warnings for the admin landing page.
 * - Populate Bootstrap cards and DataTable bodies with formatted values so the PHP template only needs empty markup placeholders.
 *
 * Dependencies:
 * - jQuery for DOM readiness, Ajax transport, and DOM mutations.
 * - `../ajax/admin_dashboard.php` which accepts an `action` flag (`stats`, `recent_sales`, `low_stock`) and returns JSON payloads.
 *
 * Side effects:
 * - Mutates `#stat*` counters and `<tbody>` elements in the dashboard tables once the asynchronous calls complete.
 */
$(document).ready(function() {
    function loadStats() {
        $.ajax({
            url: '../ajax/admin_dashboard.php',
            type: 'GET',
            data: { action: 'stats' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#statTotalItems').text(response.data.total_items);
                    $('#statLowStock').text(response.data.low_stock);
                    $('#statTotalSales').text(response.data.total_sales_today);
                    $('#statPendingContainers').text(response.data.pending_containers);
                }
            }
        });
    }
    function loadRecentSales() {
        $.ajax({
            url: '../ajax/admin_dashboard.php',
            type: 'GET',
            data: { action: 'recent_sales' },
            dataType: 'json',
            success: function(response) {
                let html = '';
                if (response.success && response.data.length) {
                    response.data.forEach(function(sale) {
                        html += `<tr>
                            <td>${sale.invoice_number}</td>
                            <td>${sale.created_at}</td>
                            <td>${sale.store_name}</td>
                            <td>${sale.customer_name || ''}</td>
                            <td>${parseFloat(sale.total_amount).toFixed(2)}</td>
                        </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="5">No recent sales found.</td></tr>';
                }
                $('#recentSalesTable tbody').html(html);
            }
        });
    }
    function loadLowStock() {
        $.ajax({
            url: '../ajax/admin_dashboard.php',
            type: 'GET',
            data: { action: 'low_stock' },
            dataType: 'json',
            success: function(response) {
                let html = '';
                if (response.success && response.data.length) {
                    response.data.forEach(function(item) {
                        html += `<tr>
                            <td>${item.item_code}</td>
                            <td>${item.name}</td>
                            <td class="text-danger">${item.current_stock}</td>
                            <td>${item.minimum_stock}</td>
                        </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="4">No low stock items found.</td></tr>';
                }
                $('#lowStockTable tbody').html(html);
            }
        });
    }
    loadStats();
    loadRecentSales();
    loadLowStock();
}); 