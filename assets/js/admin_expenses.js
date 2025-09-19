$(document).ready(function() {
    function loadAdminExpenses() {
        const params = {
            action: 'list',
            store_id: $('#filterStoreId').val(),
            status: $('#filterStatus').val()
        };
        $.ajax({
            url: '../ajax/process_expense.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(exp) {
                        html += `<tr>
                            <td>${exp.expense_date}</td>
                            <td>${exp.store_name || exp.store_id}</td>
                            <td>${exp.category}</td>
                            <td>${exp.description}</td>
                            <td>${parseFloat(exp.amount).toFixed(2)}</td>
                            <td><span class="badge bg-${exp.status === 'approved' ? 'success' : (exp.status === 'rejected' ? 'danger' : 'secondary')}">${exp.status}</span></td>
                            <td>${exp.receipt_image ? `<a href="../${exp.receipt_image}" target="_blank">View</a>` : ''}</td>
                            <td>${exp.notes || ''}</td>
                            <td>`;
                        if (exp.status === 'pending') {
                            html += `<button class="btn btn-sm btn-success approve-expense-btn" data-id="${exp.id}"><i class="bi bi-check"></i></button> `;
                            html += `<button class="btn btn-sm btn-danger reject-expense-btn" data-id="${exp.id}"><i class="bi bi-x"></i></button>`;
                        }
                        html += `</td></tr>`;
                    });
                    $('#adminExpensesTable tbody').html(html);
                } else {
                    $('#adminExpensesTable tbody').html('<tr><td colspan="9">No expenses found.</td></tr>');
                }
            },
            error: function() {
                $('#adminExpensesTable tbody').html('<tr><td colspan="9">Error loading expenses.</td></tr>');
            }
        });
    }

    $('#expenseFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadAdminExpenses();
    });

    // Add New Expense button handler
    $('#addExpenseBtn').on('click', function() {
        // Reset form
        $('#expenseForm')[0].reset();
        $('#expenseId').val('');
        $('#expenseModalTitle').text('Add Expense');
        $('#expenseReceiptPreview').empty();
        
        // Set default date to today
        $('#expenseDate').val(new Date().toISOString().split('T')[0]);
        
        // Show modal
        $('#addEditExpenseModal').modal('show');
    });

    // Expense form submission handler
    $('#expenseForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', $('#expenseId').val() ? 'edit' : 'add');
        
        $.ajax({
            url: '../ajax/process_expense.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#addEditExpenseModal').modal('hide');
                    loadAdminExpenses();
                    Swal.fire('Success', response.message || 'Expense saved successfully!', 'success');
                } else {
                    Swal.fire('Error', response.message || 'Failed to save expense', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to connect to server', 'error');
            }
        });
    });

    $(document).on('click', '.approve-expense-btn', function() {
        const id = $(this).data('id');
        if (!confirm('Approve this expense?')) return;
        $.ajax({
            url: '../ajax/process_expense.php',
            type: 'POST',
            data: { action: 'approve', expense_id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadAdminExpenses();
                } else {
                    alert(response.message);
                }
            }
        });
    });

    $(document).on('click', '.reject-expense-btn', function() {
        const id = $(this).data('id');
        if (!confirm('Reject this expense?')) return;
        $.ajax({
            url: '../ajax/process_expense.php',
            type: 'POST',
            data: { action: 'reject', expense_id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadAdminExpenses();
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // Initial load
    loadAdminExpenses();
}); 