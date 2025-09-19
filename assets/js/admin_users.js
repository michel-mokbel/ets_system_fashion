/**
 * Admin user management controller.
 *
 * Responsibilities:
 * - Fetch, filter, and render the administrator user list table with action buttons for CRUD operations.
 * - Drive the add/edit modal by hydrating form fields from the table row and submitting to `../ajax/admin_users.php`.
 * - Handle destructive flows such as delete and password resets with confirmation prompts and Ajax status feedback.
 *
 * Dependencies:
 * - jQuery for event delegation, Ajax calls, and dynamic form manipulation.
 * - Bootstrap modals and SweetAlert (wired in `script.js`) for modal dialogs and confirmation toasts.
 * - `../ajax/admin_users.php` which responds to `list`, `create`, `update`, `delete`, and `reset_password` actions.
 */
$(document).ready(function() {
    function loadUsers() {
        const params = {
            action: 'list',
            role: $('#filterRole').val(),
            store_id: $('#filterStoreId').val(),
            status: $('#filterStatus').val()
        };
        $.ajax({
            url: '../ajax/admin_users.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                let html = '';
                if (response.success && response.data.length) {
                    response.data.forEach(function(user) {
                        html += `<tr>
                            <td>${user.username}</td>
                            <td>${user.full_name || ''}</td>
                            <td>${user.role}</td>
                            <td data-store-id="${user.store_id || ''}">${user.store_name || ''}</td>
                            <td><span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${user.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-info edit-user-btn" data-id="${user.id}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-danger delete-user-btn" data-id="${user.id}"><i class="bi bi-trash"></i></button>
                                <button class="btn btn-sm btn-warning reset-password-btn" data-id="${user.id}"><i class="bi bi-key"></i></button>
                            </td>
                        </tr>`;
                    });
                } else {
                    html = '<tr><td colspan="6">No users found.</td></tr>';
                }
                $('#usersTable tbody').html(html);
            },
            error: function() {
                $('#usersTable tbody').html('<tr><td colspan="6">Error loading users.</td></tr>');
            }
        });
    }

    $('#userFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadUsers();
    });

    // Initial load
    loadUsers();

    // Handle Edit User button
    $(document).on('click', '.edit-user-btn', function() {
        const userId = $(this).data('id');
        // Find user data from the table row
        const row = $(this).closest('tr');
        $('#userModalTitle').text('Edit User');
        $('#userId').val(userId);
        $('#username').val(row.find('td:eq(0)').text().trim());
        $('#full_name').val(row.find('td:eq(1)').text().trim());
        $('#email').val(''); // Clear email field for editing
        $('#role').val(row.find('td:eq(2)').text().trim());
        $('#store_id').val(row.find('td:eq(3)').data('store-id') || '');
        $('#status').val(row.find('span.badge').text().trim().toLowerCase());
        
        // Make password optional for editing
        $('#password').removeAttr('required');
        $('#passwordField small').text('Leave blank to keep current password');
        
        $('#userModal').modal('show');
    });

    // Handle Add User button (reuse modal, clear fields)
    $('#addUserBtn').on('click', function() {
        $('#userModalTitle').text('Add User');
        $('#userForm')[0].reset();
        $('#userId').val('');
        
        // Make password required for new users
        $('#password').attr('required', 'required');
        $('#passwordField small').text('Password is required for new users');
        
        $('#userModal').modal('show');
    });

    // Handle Save (Add/Edit) User form submit
    $('#userForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serializeArray();
        let data = {};
        formData.forEach(f => data[f.name] = f.value);
        
        // Determine if this is create or edit based on whether ID is present
        data['action'] = data['id'] && data['id'] !== '' ? 'edit' : 'create';
        $.ajax({
            url: '../ajax/admin_users.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#userModal').modal('hide');
                    loadUsers();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Failed to save user.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error saving user.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });

    // Handle Delete User button
    $(document).on('click', '.delete-user-btn', function() {
        const userId = $(this).data('id');
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure you want to delete this user?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../ajax/admin_users.php',
                    type: 'POST',
                    data: { action: 'delete', id: userId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadUsers();
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.message || 'Failed to delete user.',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Error deleting user.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                    }
                });
            }
        });
    });

    // Handle Reset Password button
    $(document).on('click', '.reset-password-btn', function() {
        const userId = $(this).data('id');
        $('#resetUserId').val(userId);
        $('#new_password').val('');
        $('#resetPasswordModal').modal('show');
    });

    // Handle Reset Password form submit
    $('#resetPasswordForm').on('submit', function(e) {
        e.preventDefault();
        const userId = $('#resetUserId').val();
        const newPassword = $('#new_password').val();
        if (!newPassword) {
            Swal.fire({
                title: 'Error!',
                text: 'Please enter a new password.',
                icon: 'error',
                confirmButtonColor: '#dc3545'
            });
            return;
        }
        $.ajax({
            url: '../ajax/admin_users.php',
            type: 'POST',
            data: { action: 'reset_password', id: userId, new_password: newPassword },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#resetPasswordModal').modal('hide');
                    Swal.fire({
                        title: 'Success!',
                        text: 'Password reset successfully.',
                        icon: 'success',
                        confirmButtonColor: '#28a745'
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Failed to reset password.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error resetting password.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    });
}); 