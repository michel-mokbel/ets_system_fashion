/**
 * Warehouse box administration controller.
 *
 * Responsibilities:
 * - Configure the server-side DataTable for warehouse boxes, inject filter criteria, and surface live summary statistics.
 * - Manage nested detail rows that display item contents, and expose CRUD modals backed by `../ajax/process_boxes.php`.
 * - Provide diagnostic logging hooks that help debug mismatched payloads when admins troubleshoot box data.
 *
 * Dependencies:
 * - jQuery, DataTables (with responsive extension), and SweetAlert for confirmations.
 * - Bootstrap modals for form presentation and backend endpoints `../ajax/get_boxes.php` (list) and `../ajax/process_boxes.php` (mutations).
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    const boxesTable = $('#boxesTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        searching: false,
        ajax: {
            url: '../ajax/get_boxes.php',
            type: 'POST',
            data: function(d) {
                // Add filter parameters
                const filterForm = document.getElementById('filterForm');
                if (filterForm) {
                    const formData = new FormData(filterForm);
                    console.log('=== FORM DATA BEING SENT ===');
                    for (const [key, value] of formData.entries()) {
                        d[key] = value;
                        console.log(`Form field: ${key} = ${value}`);
                    }
                    console.log('Final data object:', d);
                }
            },
            dataSrc: function(json) {
                console.log('=== BOXES DATA RECEIVED ===');
                console.log('Full response:', json);
                console.log('Data array:', json.data);
                if (json.data && json.data.length > 0) {
                    console.log('First row:', json.data[0]);
                    console.log('Quantity field (index 4):', json.data[0][4]);
                }
                
                // Update statistics
                if (json.stats) {
                    updateStatistics(json.stats);
                }
                
                // Update filter options
                if (json.filter_options) {
                    updateFilterOptions(json.filter_options);
                }
                
                return json.data;
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables error:', error, thrown);
                Swal.fire('Error', 'Failed to load boxes data. Please refresh the page.', 'error');
            }
        },
        columns: [
            {
                data: null,
                orderable: false,
                className: 'details-control text-center',
                defaultContent: '<button class="btn btn-link p-0 expand-box" title="Expand"><i class="bi bi-chevron-down"></i></button>',
                width: '40px'
            },
            { data: 1, title: 'Box Number' },
            { data: 2, title: 'Box Name' },
            { data: 3, title: 'Type' },
            { data: 4, title: 'Container' },
            { 
                data: 5, 
                title: 'Quantity',
                render: function(data, type, row) {
                    // Ensure quantity is displayed as a number
                    const quantity = parseInt(data) || 0;
                    return quantity.toString();
                }
            },
            { data: 6, title: 'Unit Cost' },
            { data: 7, title: 'Created' },
            { data: 8, title: 'Actions', orderable: false }
        ],
        order: [[1, 'asc']], // Order by box number
        pageLength: 25,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"d>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            search: "Search boxes:",
            lengthMenu: "Show _MENU_ boxes per page",
            info: "Showing _START_ to _END_ of _TOTAL_ boxes",
            infoEmpty: "No boxes found",
            infoFiltered: "(filtered from _MAX_ total boxes)"
        }
    });

    // Event listeners
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('=== FILTER FORM SUBMITTED ===');
        console.log('Form submitted, reloading table...');
        boxesTable.ajax.reload();
    });

    document.getElementById('refreshBoxes').addEventListener('click', function() {
        boxesTable.ajax.reload();
    });

    // Custom search functionality - only search on Enter key or Filter button click
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        // Search on Enter key press
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                console.log('=== ENTER KEY PRESSED IN SEARCH ===');
                console.log('Search value:', this.value);
                updateSearchVisualFeedback();
                boxesTable.ajax.reload();
            }
        });

        // Clear search on Escape key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                updateSearchVisualFeedback();
                boxesTable.ajax.reload();
            }
        });

        // Update visual feedback on input change
        searchInput.addEventListener('input', updateSearchVisualFeedback);
    }

    // Function to update search input visual feedback
    function updateSearchVisualFeedback() {
        if (searchInput) {
            const hasSearchTerm = searchInput.value.trim().length > 0;
            if (hasSearchTerm) {
                searchInput.classList.add('is-valid');
                searchInput.classList.remove('is-invalid');
            } else {
                searchInput.classList.remove('is-valid', 'is-invalid');
            }
        }
    }

    // Clear search button functionality
    const clearSearchBtn = document.getElementById('clearSearch');
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            updateSearchVisualFeedback();
            boxesTable.ajax.reload();
        });
    }

    // Box actions event delegation
    $('#boxesTable tbody').on('click', '.view-box', function() {
        const boxId = $(this).data('id');
        viewBoxDetails(boxId);
    });

    $('#boxesTable tbody').on('click', '.edit-box', function() {
        const boxId = $(this).data('id');
        editBox(boxId);
    });

    $('#boxesTable tbody').on('click', '.delete-box', function() {
        const boxId = $(this).data('id');
        deleteBox(boxId);
    });

    // Edit from details modal
    document.getElementById('editBoxFromDetails').addEventListener('click', function() {
        const boxId = this.dataset.boxId;
        if (boxId) {
            // Close details modal and open edit modal
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('boxDetailsModal'));
            detailsModal.hide();
            setTimeout(() => editBox(boxId), 300);
        }
    });

    // Form submissions
    document.querySelectorAll('form[data-reload-table]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmission(this);
        });
    });



    // Search with debounce
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            boxesTable.ajax.reload();
        }, 500);
    });

    /**
     * Update statistics cards
     */
    function updateStatistics(stats) {
        document.getElementById('totalBoxes').textContent = stats.total_boxes || '0';
    }

    /**
     * Update filter options
     */
    function updateFilterOptions(options) {
        // Update type filter
        const typeFilter = document.getElementById('typeFilter');
        const currentTypeValue = typeFilter.value;
        typeFilter.innerHTML = '<option value="">All Types</option>';
        
        if (options.types && options.types.length > 0) {
            options.types.forEach(type => {
                const option = document.createElement('option');
                option.value = type;
                option.textContent = type;
                if (type === currentTypeValue) option.selected = true;
                typeFilter.appendChild(option);
            });
        }
    }

    /**
     * View box details
     */
    function viewBoxDetails(boxId) {
        const formData = new FormData();
        formData.append('action', 'get_box');
        formData.append('box_id', boxId);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        fetch('../ajax/process_boxes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateBoxDetails(data.box);
                loadBoxItems(boxId);
                
                // Store box ID for edit button
                document.getElementById('editBoxFromDetails').dataset.boxId = boxId;
                
                const modal = new bootstrap.Modal(document.getElementById('boxDetailsModal'));
                modal.show();
            } else {
                Swal.fire('Error', data.message || 'Failed to load box details', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading box details:', error);
            Swal.fire('Error', 'Failed to load box details', 'error');
        });
    }

    /**
     * Populate box details modal
     */
    function populateBoxDetails(box) {
        document.getElementById('detailBoxName').textContent = box.box_name;
        document.getElementById('detailBoxNumber').textContent = box.box_number;
        document.getElementById('detailBoxType').textContent = box.box_type || '-';
        document.getElementById('detailBoxQuantity').textContent = box.quantity || '0';
        document.getElementById('detailBoxUnitCost').textContent = 'CFA ' + (parseFloat(box.unit_cost || 0).toFixed(2));
        document.getElementById('detailBoxCreated').textContent = new Date(box.created_at).toLocaleDateString();
        document.getElementById('detailBoxUpdated').textContent = new Date(box.updated_at).toLocaleDateString();
        document.getElementById('detailBoxNotes').textContent = box.notes || 'No notes available';
    }

    /**
     * Load items currently stored in a box
     * This function can be enhanced later to show actual items when box-item relationships are implemented
     */
    function loadBoxItems(boxId) {
        // For now, this is a placeholder function
        // In the future, this could load items from a box_items table or similar
        console.log('Loading items for box ID:', boxId);
        
        // You can implement actual item loading logic here when needed
        // For example:
        // - Check if there's a box_items table
        // - Query items assigned to this box
        // - Display them in the details modal
        
        // For now, just log that the function was called
        // This prevents the ReferenceError from occurring
    }


    /**
     * Edit box
     */
    function editBox(boxId) {
        const formData = new FormData();
        formData.append('action', 'get_box');
        formData.append('box_id', boxId);
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

        fetch('../ajax/process_boxes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateEditForm(data.box);
                const modal = new bootstrap.Modal(document.getElementById('editBoxModal'));
                modal.show();
            } else {
                Swal.fire('Error', data.message || 'Failed to load box data', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading box for edit:', error);
            Swal.fire('Error', 'Failed to load box data', 'error');
        });
    }

    /**
     * Populate edit form
     */
    function populateEditForm(box) {
        document.getElementById('editBoxId').value = box.id;
        document.getElementById('editBoxNumber').value = box.box_number;
        document.getElementById('editBoxName').value = box.box_name;
        document.getElementById('editBoxType').value = box.box_type || '';
        document.getElementById('editBoxContainer').value = box.container_id || '';
        document.getElementById('editBoxQuantity').value = box.quantity || '0';
        document.getElementById('editBoxUnitCost').value = box.unit_cost || '0.00';
        document.getElementById('editBoxNotes').value = box.notes || '';
    }

    /**
     * Delete box
     */
    function deleteBox(boxId) {
        Swal.fire({
            title: 'Delete Box?',
            text: 'This action cannot be undone. Make sure the box is empty before deleting.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('box_id', boxId);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                fetch('../ajax/process_boxes.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Deleted!', data.message, 'success');
                        boxesTable.ajax.reload();
                    } else {
                        Swal.fire('Error', data.message || 'Failed to delete box', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting box:', error);
                    Swal.fire('Error', 'Failed to delete box', 'error');
                });
            }
        });
    }

    /**
     * Handle form submissions
     */
    function handleFormSubmission(form) {
        const formData = new FormData(form);
        
        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Success', data.message, 'success');
                
                // Close modal
                const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
                if (modal) {
                    modal.hide();
                }
                
                // Reset form
                form.reset();
                
                // Reload table
                const tableSelector = form.dataset.reloadTable;
                if (tableSelector) {
                    boxesTable.ajax.reload();
                }
            } else {
                Swal.fire('Error', data.message || 'Operation failed', 'error');
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            Swal.fire('Error', 'Operation failed', 'error');
        });
    }


}); 