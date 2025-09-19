/**
 * Store assignment management client.
 *
 * Responsibilities:
 * - Provide modals for assigning inventory items to stores, including bulk assignment flows and search/filter utilities.
 * - Synchronize selected item state with the main inventory DataTable (initialized in `inventory.js`).
 * - Coordinate AJAX calls to `../ajax/process_store_items.php` to fetch assignments, persist changes, and report errors.
 *
 * Dependencies:
 * - jQuery (for some selectors), Fetch API, Bootstrap modals, SweetAlert for error messaging, and global helpers like `showToast`.
 * - Backend endpoint `../ajax/process_store_items.php` with actions such as `get_store_assignments`, `save_assignment`, and bulk variants.
 */

document.addEventListener('DOMContentLoaded', function() {
    let stores = [];
    let selectedItems = new Set();
    
    // Initialize the interface - inventory table will be initialized by inventory.js
    
    // Event listeners for store assignments
    document.getElementById('manageAssignmentsBtn').addEventListener('click', openStoreAssignmentsModal);
    
    // Event listeners for assignment modal
    document.getElementById('loadAssignments').addEventListener('click', loadStoreAssignments);
    document.getElementById('bulkAssignBtn').addEventListener('click', openBulkAssignModal);
    document.getElementById('selectAllItems').addEventListener('change', toggleSelectAll);
    document.getElementById('bulkAssignForm').addEventListener('submit', handleBulkAssign);
    
    // Search input with debounce for assignments modal
    let searchTimeout;
    document.getElementById('assignmentSearchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadStoreAssignments();
        }, 500);
    });
    
    /**
     * Open the store assignments modal
     */
    function openStoreAssignmentsModal() {
        const modal = new bootstrap.Modal(document.getElementById('storeAssignmentsModal'));
        modal.show();
        
        // Load assignments when modal opens
        setTimeout(() => {
            loadStoreAssignments();
        }, 300);
    }
    
    /**
     * Load store assignments data
     */
    function loadStoreAssignments() {
        showLoading(true);
        
        const formData = new FormData();
        formData.append('action', 'get_store_assignments');
        
        // Get CSRF token from meta tag or hidden input
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || 
                         document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
        } else {
            console.warn('CSRF token not found');
        }
        
        // Add filter parameters from assignment modal
        const search = document.getElementById('assignmentSearchInput').value;
        const categoryId = document.getElementById('assignmentCategoryFilter').value;
        
        if (search) formData.append('search', search);
        if (categoryId) formData.append('category_id', categoryId);
        
        fetch('../ajax/process_store_items.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                stores = data.stores;
                renderAssignmentsTable(data.items, data.stores);
            } else {
                Swal.fire('Error', data.message || 'Failed to load assignments', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading assignments:', error);
            Swal.fire('Error', 'Failed to load store assignments', 'error');
        })
        .finally(() => {
            showLoading(false);
        });
    }
    
    /**
     * Render the assignments table
     */
    function renderAssignmentsTable(items, stores) {
        const table = document.getElementById('assignmentsTable');
        const tbody = document.getElementById('assignmentsTableBody');
        
        // Update table header with store columns
        const headerRow = table.querySelector('thead tr');
        // Remove existing store columns
        const existingStoreCols = headerRow.querySelectorAll('.store-column');
        existingStoreCols.forEach(col => col.remove());
        
        // Add store columns
        stores.forEach(store => {
            const th = document.createElement('th');
            th.className = 'store-column text-center';
            th.style.width = '100px';
            th.innerHTML = `
                <div class="d-flex flex-column align-items-center">
                    <small class="fw-bold">${store.name}</small>
                    <input type="checkbox" class="form-check-input store-select-all" data-store-id="${store.id}">
                </div>
            `;
            headerRow.appendChild(th);
        });
        
        // Clear tbody
        tbody.innerHTML = '';
        
        // Add data rows
        items.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="form-check-input item-checkbox" 
                           data-item-id="${item.item_id}" data-item-code="${item.item_code}" 
                           data-item-name="${item.item_name}">
                </td>
                <td>
                    <code>${item.item_code}</code>
                </td>
                <td>
                    <div>
                        <strong>${item.item_name}</strong>
                    </div>
                </td>
                <td>
                    <span class="badge bg-secondary">${item.category_name || 'No Category'}</span>
                </td>
            `;
            
            // Add store assignment cells
            stores.forEach(store => {
                const td = document.createElement('td');
                td.className = 'text-center';
                
                const assignment = item.assignments[store.id];
                const isAssigned = assignment && assignment.is_active;
                
                td.innerHTML = `
                    <input type="checkbox" class="form-check-input assignment-checkbox" 
                           data-item-id="${item.item_id}" data-store-id="${store.id}"
                           ${isAssigned ? 'checked' : ''}>
                `;
                
                row.appendChild(td);
            });
            
            tbody.appendChild(row);
        });
        
        // Add event listeners for assignment checkboxes
        tbody.querySelectorAll('.assignment-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleAssignmentToggle);
        });
        
        // Add event listeners for item checkboxes
        tbody.querySelectorAll('.item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedItems);
        });
        
        // Add event listeners for store select all
        headerRow.querySelectorAll('.store-select-all').forEach(checkbox => {
            checkbox.addEventListener('change', handleStoreSelectAll);
        });
        
        updateBulkAssignButton();
    }
    
    /**
     * Handle assignment toggle
     */
    function handleAssignmentToggle(event) {
        const checkbox = event.target;
        const itemId = checkbox.dataset.itemId;
        const storeId = checkbox.dataset.storeId;
        const isActive = checkbox.checked;
        
        const formData = new FormData();
        formData.append('action', 'toggle_assignment');
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        formData.append('item_id', itemId);
        formData.append('store_id', storeId);
        formData.append('is_active', isActive ? '1' : '0');
        
        // Disable checkbox while processing
        checkbox.disabled = true;
        
        fetch('../ajax/process_store_items.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // Revert checkbox state on error
                checkbox.checked = !isActive;
                Swal.fire('Error', data.message || 'Failed to update assignment', 'error');
            } else {
                // Show success message briefly
                showToast(data.message, 'success');
            }
        })
        .catch(error => {
            console.error('Error updating assignment:', error);
            checkbox.checked = !isActive;
            Swal.fire('Error', 'Failed to update assignment', 'error');
        })
        .finally(() => {
            checkbox.disabled = false;
        });
    }
    
    /**
     * Handle store select all
     */
    function handleStoreSelectAll(event) {
        const storeCheckbox = event.target;
        const storeId = storeCheckbox.dataset.storeId;
        const isChecked = storeCheckbox.checked;
        
        // Get all assignment checkboxes for this store
        const storeAssignments = document.querySelectorAll(`input[data-store-id="${storeId}"].assignment-checkbox`);
        
        storeAssignments.forEach(checkbox => {
            if (checkbox.checked !== isChecked) {
                checkbox.checked = isChecked;
                // Trigger the change event to update the assignment
                checkbox.dispatchEvent(new Event('change'));
            }
        });
    }
    
    /**
     * Update selected items set
     */
    function updateSelectedItems() {
        selectedItems.clear();
        
        document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
            selectedItems.add({
                id: checkbox.dataset.itemId,
                code: checkbox.dataset.itemCode,
                name: checkbox.dataset.itemName
            });
        });
        
        updateBulkAssignButton();
    }
    
    /**
     * Toggle select all items
     */
    function toggleSelectAll(event) {
        const isChecked = event.target.checked;
        
        document.querySelectorAll('.item-checkbox').forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        
        updateSelectedItems();
    }
    
    /**
     * Update bulk assign button state
     */
    function updateBulkAssignButton() {
        const bulkBtn = document.getElementById('bulkAssignBtn');
        const selectedCount = selectedItems.size;
        
        if (selectedCount > 0) {
            bulkBtn.disabled = false;
            bulkBtn.innerHTML = `<i class="bi bi-check-square me-1"></i> Bulk Assign (${selectedCount})`;
        } else {
            bulkBtn.disabled = true;
            bulkBtn.innerHTML = '<i class="bi bi-check-square me-1"></i> Bulk Assign';
        }
    }
    
    /**
     * Open bulk assign modal
     */
    function openBulkAssignModal() {
        if (selectedItems.size === 0) {
            Swal.fire('Info', 'Please select items to assign', 'info');
            return;
        }
        
        // Populate selected items list
        const itemsList = document.getElementById('selectedItemsList');
        itemsList.innerHTML = '';
        
        selectedItems.forEach(item => {
            const div = document.createElement('div');
            div.className = 'mb-1';
            div.innerHTML = `<code>${item.code}</code> - ${item.name}`;
            itemsList.appendChild(div);
        });
        
        // Populate store checkboxes
        const storeCheckboxes = document.getElementById('storeCheckboxes');
        storeCheckboxes.innerHTML = '';
        
        stores.forEach(store => {
            const div = document.createElement('div');
            div.className = 'form-check';
            div.innerHTML = `
                <input class="form-check-input" type="checkbox" id="store_${store.id}" 
                       name="store_ids[]" value="${store.id}">
                <label class="form-check-label" for="store_${store.id}">
                    ${store.name}
                </label>
            `;
            storeCheckboxes.appendChild(div);
        });
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('bulkAssignModal'));
        modal.show();
    }
    
    /**
     * Handle bulk assign form submission
     */
    function handleBulkAssign(event) {
        event.preventDefault();
        
        const formData = new FormData(event.target);
        
        // Add selected item IDs
        selectedItems.forEach(item => {
            formData.append('item_ids[]', item.id);
        });
        
        fetch('../ajax/process_store_items.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success', data.message, 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('bulkAssignModal'));
                modal.hide();
                
                // Clear selections
                selectedItems.clear();
                document.getElementById('selectAllItems').checked = false;
                document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false);
                updateBulkAssignButton();
                
                // Reload data
                loadStoreAssignments();
            } else {
                Swal.fire('Error', data.message || 'Bulk assignment failed', 'error');
            }
        })
        .catch(error => {
            console.error('Error in bulk assign:', error);
            Swal.fire('Error', 'Bulk assignment failed', 'error');
        });
    }
    
         /**
      * Show/hide loading indicator
      */
     function showLoading(show) {
         const loading = document.getElementById('assignmentLoadingIndicator');
         const table = document.getElementById('assignmentsTable');
         
         if (show) {
             loading.style.display = 'block';
             table.style.opacity = '0.5';
         } else {
             loading.style.display = 'none';
             table.style.opacity = '1';
         }
     }
    
    /**
     * Show toast notification
     */
    function showToast(message, type = 'info') {
        // Create a simple toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : 'info'} position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 3000);
    }
}); 