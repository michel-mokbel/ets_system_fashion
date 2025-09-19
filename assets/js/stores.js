/**
 * Store directory management controller.
 *
 * Responsibilities:
 * - Initialize the stores DataTable with CSRF-protected server-side queries and responsive layout.
 * - Drive add/edit/delete workflows including manager assignment dropdowns and POS shortcut links.
 * - Use SweetAlert for confirmation prompts and integrate with `../ajax/process_store.php` for mutations.
 *
 * Dependencies:
 * - jQuery, DataTables, SweetAlert, Bootstrap modals, and helper functions defined in `script.js`.
 * - Backend endpoints `../ajax/get_stores.php` and `../ajax/process_store.php`, plus `../ajax/admin_users.php` for manager lists.
 */
$(document).ready(function() {
    const csrfToken = $('#csrf_token').val();
    // Initialize DataTable
    const storesTable = $('#storesTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        ajax: {
            url: '../ajax/get_stores.php',
            type: 'POST',
            data: function(d) {
                d.csrf_token = csrfToken;
                return d;
            },
            error: function(xhr, error, thrown) {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to load stores. Please try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        },
        columns: [
            { data: 'store_code' },
            { data: 'name' },
            { data: 'address' },
            { data: 'phone' },
            { data: 'manager_name' },
            { 
                data: 'status',
                render: function(data) {
                    const badgeClass = data === 'active' ? 'bg-success' : 'bg-secondary';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-primary edit-store-btn" data-id="${data}"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-danger delete-store-btn" data-id="${data}"><i class="bi bi-trash"></i></button>
                            <a class="btn btn-sm btn-success" href="/ets_system_fashion/store/pos.php?store_id=${data}" target="_blank" title="Go to POS"><i class="bi bi-shop"></i></a>
                        </div>
                    `;
                }
            }
        ],
        responsive: true,
        language: {
            processing: '<div class="spinner-border text-primary" role="status"></div>',
            emptyTable: 'No stores found',
            zeroRecords: 'No matching stores found',
            search: "",
            searchPlaceholder: "Search stores...",
            lengthMenu: "_MENU_ records per page",
            info: "Showing _START_ to _END_ of _TOTAL_ stores",
            infoEmpty: "Showing 0 to 0 of 0 stores",
            infoFiltered: "(filtered from _MAX_ total stores)"
        },
        drawCallback: function() {
            initializeTooltipsPopovers && initializeTooltipsPopovers();
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>'
    });

    // Populate manager dropdowns
    function loadManagers(selectId, selectedId) {
        // Use existing endpoint that lists users with filters
        $.ajax({
            url: '../ajax/admin_users.php',
            type: 'GET',
            data: { action: 'list', role: 'store_manager' },
            dataType: 'json',
            success: function(response) {
                const select = $(selectId);
                select.empty();
                select.append('<option value="">Select Manager</option>');
                if (response.success && response.data) {
                    response.data.filter(u => u.status === 'active').forEach(function(user) {
                        const selected = (String(user.id) === String(selectedId)) ? 'selected' : '';
                        select.append(`<option value="${user.id}" ${selected}>${user.full_name || user.username}</option>`);
                    });
                }
            },
            error: function() {
                // Fallback: keep existing options if any
                console.error('Failed to load managers');
            }
        });
    }

    // Show add modal and load managers
    $('#addStoreModal').on('show.bs.modal', function() {
        loadManagers('#storeManager');
        $('#addStoreForm')[0].reset();
        $('#addStoreForm').removeClass('was-validated');
    });

    // Show edit modal and load store data
    $(document).on('click', '.edit-store-btn', function() {
        const storeId = $(this).data('id');
        $.ajax({
            url: '../ajax/get_store.php',
            type: 'POST',
            data: { store_id: storeId, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const s = response.data;
                    $('#editStoreId').val(s.id);
                    $('#editStoreCode').val(s.store_code);
                    $('#editStoreName').val(s.name);
                    $('#editStoreAddress').val(s.address);
                    $('#editStorePhone').val(s.phone);
                    loadManagers('#editStoreManager', s.manager_id);
                    $('#editStoreStatus').val(s.status);
                    const editModal = new bootstrap.Modal(document.getElementById('editStoreModal'));
                    editModal.show();
                } else {
                    showToast('Error', response.message, 'danger');
                }
            },
            error: function() {
                showToast('Error', 'Failed to load store data', 'danger');
            }
        });
    });

    // AJAX form submission handled by common.js
    // Reload table on success
    $('#addStoreForm, #editStoreForm').on('ajaxSuccess', function() {
        storesTable.ajax.reload();
    });

    // Delete store
    $(document).on('click', '.delete-store-btn', function() {
        const storeId = $(this).data('id');
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this store? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../ajax/process_store.php',
                    type: 'POST',
                    data: { action: 'delete', store_id: storeId, csrf_token: csrfToken },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast('Success', response.message, 'success');
                            storesTable.ajax.reload();
                        } else {
                            showToast('Error', response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Error', 'Failed to delete store', 'danger');
                    }
                });
            }
        });
    });

    // Toggle status
    $(document).on('click', '.toggle-status-btn', function() {
        const storeId = $(this).data('id');
        const currentStatus = $(this).data('status');
        $.ajax({
            url: '../ajax/process_store.php',
            type: 'POST',
            data: { action: 'toggle_status', store_id: storeId, csrf_token: csrfToken },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Success', response.message, 'success');
                    storesTable.ajax.reload();
                } else {
                    showToast('Error', response.message, 'danger');
                }
            },
            error: function() {
                showToast('Error', 'Failed to update status', 'danger');
            }
        });
    });

    // Toast helper
    function showToast(title, message, type) {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        $('.toast-container').append(toastHtml);
        $('.toast-container .toast:last').toast('show');
        $('.toast-container .toast:last').on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
}); 