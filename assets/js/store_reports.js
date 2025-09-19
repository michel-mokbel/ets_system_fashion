$(document).ready(function() {
    // --- Sales Report ---
    function loadSalesReport() {
        // Example: fetch sales report data
        $('#salesReportTable').html('<div class="text-center">Loading...</div>');
        $.ajax({
            url: '../ajax/store_reports.php',
            type: 'GET',
            data: { action: 'sales' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '<table class="table table-bordered"><thead><tr><th>Date</th><th>Total Sales</th></tr></thead><tbody>';
                    response.data.forEach(function(row) {
                        html += `<tr><td>${row.sale_date}</td><td>${parseFloat(row.total_sales).toFixed(2)}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    $('#salesReportTable').html(html);
                } else {
                    $('#salesReportTable').html('<div class="text-danger">No data found.</div>');
                }
            },
            error: function() {
                $('#salesReportTable').html('<div class="text-danger">Error loading sales report.</div>');
            }
        });
    }

    // --- Inventory Report ---
    function loadInventoryReport() {
        $('#inventoryReportTable').html('<div class="text-center">Loading...</div>');
        $.ajax({
            url: '../ajax/store_reports.php',
            type: 'GET',
            data: { action: 'inventory' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '<table class="table table-bordered"><thead><tr><th>Item</th><th>Code</th><th>Stock</th></tr></thead><tbody>';
                    response.data.forEach(function(row) {
                        html += `<tr><td>${row.item_name}</td><td>${row.item_code}</td><td>${row.current_stock}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    $('#inventoryReportTable').html(html);
                } else {
                    $('#inventoryReportTable').html('<div class="text-danger">No data found.</div>');
                }
            },
            error: function() {
                $('#inventoryReportTable').html('<div class="text-danger">Error loading inventory report.</div>');
            }
        });
    }

    // --- Invoices Report ---
    function loadInvoicesReport() {
        $('#invoicesReportTable').html('<div class="text-center">Loading...</div>');
        $.ajax({
            url: '../ajax/store_reports.php',
            type: 'GET',
            data: { action: 'invoices' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = `
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Subtotal</th>
                                        <th>Discount</th>
                                        <th>Total</th>
                                        <th>Payment Method</th>
                                        <th>Amount Paid</th>
                                        <th>Change</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    response.data.forEach(function(row) {
                        const invoiceDate = new Date(row.created_at).toLocaleDateString();
                        const customerName = row.customer_name || 'Walk-in';
                        const subtotal = parseFloat(row.subtotal || 0).toFixed(2);
                        const discount = parseFloat(row.discount_amount || 0).toFixed(2);
                        const total = parseFloat(row.total_amount || 0).toFixed(2);
                        const amountPaid = parseFloat(row.amount_paid || 0).toFixed(2);
                        const changeDue = parseFloat(row.change_due || 0).toFixed(2);
                        
                        // Format payment method with cash/mobile breakdown
                        let paymentMethodDisplay = capitalizeFirst(row.payment_method || 'cash');
                        let paymentDetails = '';
                        
                        if (row.payment_method === 'cash_mobile' && row.cash_amount !== undefined && row.mobile_amount !== undefined) {
                            paymentMethodDisplay = 'Cash + Mobile';
                            const cashAmount = parseFloat(row.cash_amount || 0).toFixed(2);
                            const mobileAmount = parseFloat(row.mobile_amount || 0).toFixed(2);
                            paymentDetails = `
                                <div class="small text-muted">
                                    Cash: CFA ${cashAmount} | Mobile: CFA ${mobileAmount}
                                </div>
                            `;
                        }
                        
                        html += `
                            <tr>
                                <td><strong>${row.invoice_number}</strong></td>
                                <td>${invoiceDate}</td>
                                <td>${customerName}</td>
                                <td>CFA ${subtotal}</td>
                                <td>CFA ${discount}</td>
                                <td><strong>CFA ${total}</strong></td>
                                <td>
                                    ${paymentMethodDisplay}
                                    ${paymentDetails}
                                </td>
                                <td>CFA ${amountPaid}</td>
                                <td>CFA ${changeDue}</td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                    $('#invoicesReportTable').html(html);
                } else {
                    $('#invoicesReportTable').html('<div class="text-danger">No data found.</div>');
                }
            },
            error: function() {
                $('#invoicesReportTable').html('<div class="text-danger">Error loading invoices report.</div>');
            }
        });
    }

    // --- Expenses Report ---
    function loadExpensesReport() {
        $('#expensesReportTable').html('<div class="text-center">Loading...</div>');
        $.ajax({
            url: '../ajax/store_reports.php',
            type: 'GET',
            data: { action: 'expenses' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '<table class="table table-bordered"><thead><tr><th>Date</th><th>Category</th><th>Amount</th></tr></thead><tbody>';
                    response.data.forEach(function(row) {
                        html += `<tr><td>${row.expense_date}</td><td>${row.category}</td><td>${parseFloat(row.amount).toFixed(2)}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    $('#expensesReportTable').html(html);
                } else {
                    $('#expensesReportTable').html('<div class="text-danger">No data found.</div>');
                }
            },
            error: function() {
                $('#expensesReportTable').html('<div class="text-danger">Error loading expenses report.</div>');
            }
        });
    }

    // Helper function to capitalize first letter
    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    // Tab switching
    $('#reportTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('data-bs-target');
        if (target === '#salesReport') loadSalesReport();
        if (target === '#inventoryReport') loadInventoryReport();
        if (target === '#invoicesReport') loadInvoicesReport();
        if (target === '#expensesReport') loadExpensesReport();
    });

    // Initial load
    loadSalesReport();
}); 