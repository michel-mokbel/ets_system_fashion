/**
 * Store expense listing controller.
 *
 * Responsibilities:
 * - Fetch store-level expenses filtered by date, category, and status from `../ajax/store_expenses.php`.
 * - Render the results into the table body and provide placeholders for view/print actions.
 * - Refresh the listing in response to filter submissions without reloading the page.
 *
 * Dependencies:
 * - jQuery for event handling and AJAX; Bootstrap styling for table badges.
 * - Backend endpoint `../ajax/store_expenses.php`.
 */
$(document).ready(function() {
    function loadExpenses() {
        const params = {
            action: 'list',
            from_date: $('#filterFromDate').val(),
            to_date: $('#filterToDate').val(),
            status: $('#filterStatus').val(),
            category: $('#filterCategory').val()
        };
        $.ajax({
            url: '../ajax/store_expenses.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(exp) {
                        html += `<tr>
                            <td>${exp.expense_number}</td>
                            <td>${exp.expense_date}</td>
                            <td>${exp.category}</td>
                            <td>${exp.description}</td>
                            <td>${parseFloat(exp.amount).toFixed(2)}</td>
                            <td><span class="badge bg-${exp.status === 'approved' ? 'success' : (exp.status === 'pending' ? 'warning' : 'secondary')}">${exp.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-info view-expense-btn" data-id="${exp.id}"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-sm btn-primary print-expense-btn" data-id="${exp.id}"><i class="bi bi-printer"></i></button>
                            </td>
                        </tr>`;
                    });
                    $('#storeExpensesTable tbody').html(html);
                } else {
                    $('#storeExpensesTable tbody').html('<tr><td colspan="7">No expenses found.</td></tr>');
                }
            },
            error: function() {
                $('#storeExpensesTable tbody').html('<tr><td colspan="7">Error loading expenses.</td></tr>');
            }
        });
    }

    $('#expenseFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadExpenses();
    });

    // Initial load
    loadExpenses();

    // TODO: Implement view/print actions
}); 