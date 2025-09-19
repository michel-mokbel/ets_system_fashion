<?php
/**
 * Store Inventory Oversight
 * ------------------------
 * Allows admins, inventory managers, and authorized store managers to review
 * per-store stock levels, adjust assignments, and inspect item health metrics.
 * The page enforces role-based access, determines the default store scope, and
 * renders cards plus DataTables powered by `assets/js/store_items.js` and
 * related scripts. Data is sourced from endpoints like
 * `ajax/get_store_inventory_enhanced.php` and actions route to
 * `ajax/process_store_inventory.php` for adjustments.
 */
ob_start();
require_once '../includes/session_config.php';
session_start();
require_once '../includes/header.php';

// Only admin, inventory managers, and store managers can access this page
if (!has_role(['admin', 'inventory_manager', 'store_manager'])) {
    redirect('../index.php');
}

// Get current store for store managers and inventory managers
$current_store_id = (is_admin() || is_inventory_manager()) ? ($_GET['store_id'] ?? '') : $_SESSION['store_id'];
$store_filter_required = !is_admin() && !is_inventory_manager() && !$current_store_id;

if ($store_filter_required) {
    redirect('../index.php');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-shop me-2"></i>Store Inventory Management</h1>
    <!-- <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
            <i class="bi bi-box-arrow-in-down me-1"></i> Adjust Stock
        </button>
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#minimumStockModal">
            <i class="bi bi-exclamation-triangle me-1"></i> Update Minimum Stock
        </button>
    </div> -->
</div>

<!-- Store Statistics Cards -->
<div class="row mb-4" id="storeStatsCards">
    <div class="col-md-3">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Items</h5>
                <h2 id="statTotalItems">-</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-danger text-white">
            <div class="card-body">
                <h5 class="card-title">Out of Stock</h5>
                <h2 id="statOutOfStock">-</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">Low Stock</h5>
                <h2 id="statLowStock">-</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Total Value</h5>
                <h2 id="statTotalValue">-</h2>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="inventoryFilterForm" class="row g-3">
            <?php if (is_admin() || is_inventory_manager()): ?>
            <div class="col-md-3">
                <label class="form-label">Store</label>
                <select class="form-select" name="store_id" id="storeFilter">
                    <option value="">All Stores</option>
                    <?php
                    $stores_query = "SELECT id, name, store_code FROM stores WHERE status = 'active'  ORDER BY name";
                    $stores_result = $conn->query($stores_query);
                    while ($store = $stores_result->fetch_assoc()) {
                        $selected = ($current_store_id == $store['id']) ? 'selected' : '';
                        echo "<option value='{$store['id']}' $selected>" . htmlspecialchars($store['name']) . " ({$store['store_code']})</option>";
                    }
                    ?>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" name="store_id" id="storeFilter" value="<?php echo $current_store_id; ?>">
            <?php endif; ?>
            
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select class="form-select" name="category_id" id="categoryFilter">
                    <option value="">All Categories</option>
                    <?php
                    $categories_query = "SELECT id, name FROM categories ORDER BY name";
                    $categories_result = $conn->query($categories_query);
                    while ($category = $categories_result->fetch_assoc()) {
                        echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Stock Status</label>
                <select class="form-select" name="stock_status" id="stockStatusFilter">
                    <option value="">All Items</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>

            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-secondary w-100">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Inventory Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" id="storeInventoryTable">
                <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Container</th>
                        <th>Store</th>
                        <th>Stock</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data loaded via AJAX -->
                </tbody>
            </table>
        </div>
        <!-- Mobile Cards container (used on small screens) -->
        <div id="storeInventoryCards" class="mobile-card-container"></div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="adjustStockForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="store_inventory_id" id="adjustStoreInventoryId">
                
                <div class="modal-body">
                    <div id="adjustItemInfo" class="mb-3">
                        <!-- Item info will be populated -->
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select class="form-select" name="adjustment_type" id="adjustmentType" required>
                            <option value="">Select Type</option>
                            <option value="add">Add Stock</option>
                            <option value="remove">Remove Stock</option>
                            <option value="set">Set Stock Level</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" id="adjustQuantity" min="0" required>
                        <div class="form-text">Current stock: <span id="currentStockDisplay">0</span></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Reason for adjustment"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Adjust Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Minimum Stock Modal -->
<div class="modal fade" id="minimumStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Minimum Stock Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="minimumStockForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="store_inventory_id" id="minimumStockStoreInventoryId">
                
                <div class="modal-body">
                    <div id="minimumStockItemInfo" class="mb-3">
                        <!-- Item info will be populated -->
                    </div>
<!--                     
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Current Stock</label>
                            <input type="text" class="form-control" id="currentStockDisplay" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Minimum Stock</label>
                            <input type="text" class="form-control" id="currentMinimumStockDisplay" readonly>
                        </div>
                    </div> -->
                    
                    <div class="mt-3">
                        <label class="form-label">New Minimum Stock Level *</label>
                        <input type="number" class="form-control" name="minimum_stock" id="newMinimumStock" min="0" required>
                        <div class="form-text">Set the minimum stock level for this item. When stock falls below this level, it will be marked as "Low Stock".</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Minimum Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    const csrfToken = $('input[name="csrf_token"]').val();
    let currentStoreId = <?php echo $current_store_id ? $current_store_id : 'null'; ?>;
            const isAdmin = <?php echo (is_admin() || is_inventory_manager()) ? 'true' : 'false'; ?>;
    
    // Initialize DataTable
    const storeInventoryTable = $('#storeInventoryTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '../ajax/get_store_inventory_enhanced.php',
            type: 'POST',
            data: function(d) {
                const selectedStoreId = $('#storeFilter').val();
                let storeId;
                
                // Handle "All Stores" selection (empty string) properly
                if (selectedStoreId !== null && selectedStoreId !== undefined) {
                    storeId = selectedStoreId === '' ? 0 : parseInt(selectedStoreId); // Empty string = All Stores (0)
                } else {
                    storeId = currentStoreId || 0; // Fallback for initial load
                }
                
                return {
                    csrf_token: csrfToken,
                    store_id: storeId,
                    category_id: $('#categoryFilter').val(),
                    stock_status: $('#stockStatusFilter').val(),
                    search: $('#searchFilter').val(),
                    include_zero_stock: true
                };
            },
            dataSrc: function(json) {
                if (json.success) {
                    updateStatistics(json.statistics);
                    return json.items;
                } else {
                    console.error('Error loading inventory:', json.message);
                    return [];
                }
            }
        },
        columns: [
            { 
                data: null,
                render: function(data, type, row) {
                    let itemHtml = `<div class="d-flex align-items-center">`;
                    if (row.image_path) {
                        itemHtml += `<img src="../${row.image_path}" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">`;
                    }
                    itemHtml += `<div>
                                   <strong>${row.name}</strong><br>
                                   <small class="text-muted">${row.item_code}</small>
                                   ${row.size ? `<span class="badge bg-secondary ms-1">${row.size}</span>` : ''}
                                   ${row.color ? `<span class="badge bg-info ms-1">${row.color}</span>` : ''}
                                 </div>
                               </div>`;
                    return itemHtml;
                }
            },
            { 
                data: 'category_name',
                render: function(data, type, row) {
                    return data ? `${data}${row.subcategory_name ? `<br><small>${row.subcategory_name}</small>` : ''}` : '-';
                }
            },
            { 
                data: 'container_number',
                render: function(data, type, row) {
                    return data ? `<span class="badge bg-primary">${data}</span>` : '-';
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    return `<div>
                              <strong>${row.store_name}</strong><br>
                              <small class="text-muted">${row.store_code}</small>
                            </div>`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    const stockClass = row.stock_status === 'out_of_stock' ? 'danger' : 
                                     row.stock_status === 'low_stock' ? 'warning' : 'success';
                    return `<span class="badge bg-${stockClass}">${row.current_stock}</span>
                            ${row.minimum_stock > 0 ? `<br><small class="text-muted">Min: ${row.minimum_stock}</small>` : ''}`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    let location = [];
                    if (row.aisle) location.push(`A:${row.aisle}`);
                    if (row.shelf) location.push(`S:${row.shelf}`);
                    if (row.bin) location.push(`B:${row.bin}`);
                    
                    let locationHtml = location.length > 0 ? location.join(' ') : '';
                    if (row.location_in_store) {
                        locationHtml += locationHtml ? `<br><small>${row.location_in_store}</small>` : row.location_in_store;
                    }
                    return locationHtml || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'selling_price',
                render: function(data) {
                    return `CFA ${parseFloat(data).toFixed(2)}`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    const value = row.current_stock * row.selling_price;
                    return `CFA ${value.toFixed(2)}`;
                }
            },
            { 
                data: 'stock_status',
                render: function(data) {
                    const statusMap = {
                        'in_stock': { class: 'success', text: 'In Stock' },
                        'low_stock': { class: 'warning', text: 'Low Stock' },
                        'out_of_stock': { class: 'danger', text: 'Out of Stock' }
                    };
                    const status = statusMap[data] || { class: 'secondary', text: 'Unknown' };
                    return `<span class="badge bg-${status.class}">${status.text}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `

 
                    `;
                }
            }
        ],
        order: [[0, 'asc']],
        initComplete: function() {
            // Handle initial viewport setup after table is fully initialized
            setTimeout(handleViewportChange, 100);
        },
        drawCallback: function() {
            // Handle viewport changes after data redraw
            setTimeout(handleViewportChange, 100);
        }
    });

    // Filter form submission
    $('#inventoryFilterForm').on('submit', function(e) {
        e.preventDefault();
        storeInventoryTable.ajax.reload();
    });
    
    // Store filter change handler
    $('#storeFilter').on('change', function() {
        storeInventoryTable.ajax.reload();
    });
    
    // Other filter change handlers
    $('#categoryFilter, #stockStatusFilter').on('change', function() {
        storeInventoryTable.ajax.reload();
    });
    
    // Search filter with debounce
    let searchTimeout;
    $('#searchFilter').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            storeInventoryTable.ajax.reload();
        }, 500);
    });

    // Update statistics
    function updateStatistics(stats) {
        $('#statTotalItems').text(stats.total_items || 0);
        $('#statOutOfStock').text(stats.out_of_stock_count || 0);
        $('#statLowStock').text(stats.low_stock_count || 0);
        $('#statTotalValue').text('CFA ' + (stats.total_inventory_value || 0).toFixed(2));
    }

    // Adjust stock function
    window.adjustStock = function(storeInventoryId) {
        // Get item details
        const row = storeInventoryTable.rows().data().toArray().find(r => r.store_inventory_id == storeInventoryId);
        if (!row) return;

        $('#adjustStoreInventoryId').val(storeInventoryId);
        $('#currentStockDisplay').text(row.current_stock);
        
        const itemInfo = `
            <div class="d-flex align-items-center p-3 bg-light rounded">
                ${row.image_path ? `<img src="../${row.image_path}" class="me-3" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">` : ''}
                <div>
                    <strong>${row.name}</strong><br>
                    <small class="text-muted">${row.item_code} | Current Stock: ${row.current_stock}</small>
                </div>
            </div>
        `;
        $('#adjustItemInfo').html(itemInfo);
        $('#adjustStockModal').modal('show');
    };

    // Update minimum stock function
    window.updateMinimumStock = function(storeInventoryId) {
    
        console.log('updateMinimumStock called with ID:', storeInventoryId);
        
        // Check if modal exists
        if ($('#minimumStockModal').length === 0) {
            console.error('Modal not found!');
            alert('Modal not found!');
            return;
        }
        
        const row = storeInventoryTable.rows().data().toArray().find(r => r.store_inventory_id == storeInventoryId);
        console.log('Found row:', row);
        
        if (!row) {
            console.error('No row found for store_inventory_id:', storeInventoryId);
            alert('No row found for ID: ' + storeInventoryId);
            return;
        }

        console.log('Setting modal fields...');
        $('#minimumStockStoreInventoryId').val(storeInventoryId);
        $('#currentStockDisplay').text(row.current_stock || 0);
        $('#currentMinimumStockDisplay').text(row.minimum_stock || 0);
        $('#newMinimumStock').val(''); // Start empty for user input
        
        const itemInfo = `
            <div class="d-flex align-items-center p-3 bg-light rounded">
                ${row.image_path ? `<img src="../${row.image_path}" class="me-3" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">` : ''}
                <div>
                    <strong>${row.name}</strong><br>
                    <small class="text-muted">${row.item_code}</small>
                </div>
            </div>
        `;
        $('#minimumStockItemInfo').html(itemInfo);
        
        console.log('Showing modal...');
        $('#minimumStockModal').modal('show');
        console.log('Modal should be visible now');
    };

    // Handle stock adjustment
    $('#adjustStockForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '../ajax/process_store_inventory.php',
            type: 'POST',
            data: formData + '&action=adjust_stock',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Stock adjusted successfully!', 'success');
                    $('#adjustStockModal').modal('hide');
                    storeInventoryTable.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to adjust stock', 'error');
            }
        });
    });

    // Handle minimum stock update
    $('#minimumStockForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '../ajax/process_store_inventory.php',
            type: 'POST',
            data: formData + '&action=update_minimum_stock',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Minimum stock level updated successfully!', 'success');
                    $('#minimumStockModal').modal('hide');
                    storeInventoryTable.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update minimum stock', 'error');
            }
        });
    });

    // Utility function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Mobile card functionality
    function renderStoreInventoryCards(rows) {
        // Check if the table is initialized
        if (typeof storeInventoryTable === 'undefined' || !storeInventoryTable) {
            return;
        }
        
        const container = document.getElementById('storeInventoryCards');
        if (!container) return;
        container.innerHTML = '';
        if (!rows || rows.length === 0) {
            container.innerHTML = '<div class="p-3 text-center text-muted">No items found</div>';
            return;
        }
        rows.forEach(row => {
            const card = document.createElement('div');
            card.className = 'mobile-table-card';
            card.innerHTML = `
                <div class="mobile-card-header">
                    <strong>Item Code:</strong> ${escapeHtml(row.item_code || '')}
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Name</span>
                    <span class="mobile-card-value">${escapeHtml(row.name || '')}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Category</span>
                    <span class="mobile-card-value">${escapeHtml(row.category || '-')}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Container</span>
                    <span class="mobile-card-value">${row.container_number ? `<span class="badge bg-primary">${escapeHtml(row.container_number)}</span>` : '-'}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Store</span>
                    <span class="mobile-card-value">${escapeHtml(row.store_name || '-')}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Stock</span>
                    <span class="mobile-card-value">${row.current_stock != null ? row.current_stock : '-'}</span>
                </div>
                <div class="mobile-card-row">
                    <span class="mobile-card-label">Price</span>
                    <span class="mobile-card-value">CFA ${parseFloat(row.selling_price || 0).toFixed(2)}</span>
                </div>
                <div class="mobile-card-actions d-flex gap-1">
                    <button class="btn btn-sm btn-info view-details" data-id="${row.store_inventory_id}"><i class="bi bi-eye"></i></button>
                </div>
            `;
            container.appendChild(card);
        });
        
        // Add custom pagination below cards for mobile
        if (window.innerWidth <= 767) {
            renderMobilePagination();
        }
    }

    function renderMobilePagination() {
        // Check if the table is initialized
        if (typeof storeInventoryTable === 'undefined' || !storeInventoryTable) {
            return;
        }
        
        const container = document.getElementById('storeInventoryCards');
        if (!container) return;
        
        const info = storeInventoryTable.page.info();
        const totalPages = info.pages;
        const currentPage = info.page;
        
        if (totalPages <= 1) return; // No pagination needed
        
        const paginationDiv = document.createElement('div');
        paginationDiv.className = 'mobile-pagination mt-3 d-flex justify-content-center align-items-center gap-2';
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.className = `btn btn-sm btn-outline-primary ${currentPage === 0 ? 'disabled' : ''}`;
        prevBtn.innerHTML = '<i class="bi bi-chevron-left"></i> Previous';
        prevBtn.onclick = () => {
            if (currentPage > 0) {
                storeInventoryTable.page(currentPage - 1).draw('page');
            }
        };
        
        // Page info
        const pageInfo = document.createElement('span');
        pageInfo.className = 'text-muted mx-2';
        pageInfo.textContent = `Page ${currentPage + 1} of ${totalPages}`;
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.className = `btn btn-sm btn-outline-primary ${currentPage === totalPages - 1 ? 'disabled' : ''}`;
        nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right"></i>';
        nextBtn.onclick = () => {
            if (currentPage < totalPages - 1) {
                storeInventoryTable.page(currentPage + 1).draw('page');
            }
        };
        
        paginationDiv.appendChild(prevBtn);
        paginationDiv.appendChild(pageInfo);
        paginationDiv.appendChild(nextBtn);
        container.appendChild(paginationDiv);
    }

    // Handle orientation changes and window resizing
    function handleViewportChange() {
        // Check if the table is initialized
        if (typeof storeInventoryTable === 'undefined' || !storeInventoryTable) {
            return;
        }
        
        const isMobile = window.innerWidth <= 767;
        const tableContainer = $('#storeInventoryTable').closest('.table-responsive');
        const cardsContainer = document.getElementById('storeInventoryCards');
        
        if (isMobile) {
            // Show cards, hide table content
            if (cardsContainer) {
                cardsContainer.style.display = '';
                renderStoreInventoryCards(storeInventoryTable.rows({ page: 'current' }).data().toArray());
            }
            // Hide the entire table container on mobile
            if (tableContainer.length) tableContainer.hide();
        } else {
            // Show table, hide cards
            if (tableContainer.length) tableContainer.show();
            if (cardsContainer) cardsContainer.style.display = 'none';
        }
    }

    // Listen for orientation changes and window resize
    window.addEventListener('orientationchange', function() {
        // Wait for orientation change to complete
        setTimeout(handleViewportChange, 100);
    });
    
    window.addEventListener('resize', function() {
        // Debounce resize events
        clearTimeout(window.resizeTimer);
        window.resizeTimer = setTimeout(handleViewportChange, 250);
    });



    // Handle mobile card action buttons
    $(document).on('click', '#storeInventoryCards .view-details', function() {
        const storeInventoryId = $(this).data('id');
        viewStoreInventoryDetails(storeInventoryId);
    });

    $(document).on('click', '#storeInventoryCards .adjust-stock', function() {
        const storeInventoryId = $(this).data('id');
        openAdjustStockModal(storeInventoryId);
    });

    $(document).on('click', '#storeInventoryCards .edit-item', function() {
        const storeInventoryId = $(this).data('id');
        openEditStoreInventoryModal(storeInventoryId);
    });

    $(document).on('click', '#storeInventoryCards .delete-item', function() {
        const storeInventoryId = $(this).data('id');
        deleteStoreInventory(storeInventoryId);
    });

    // Auto-load data
    if (isAdmin || <?php echo is_inventory_manager() ? 'true' : 'false'; ?>) {
        // For admins and inventory managers, load all stores by default
        storeInventoryTable.ajax.reload();
    } else if (currentStoreId) {
        // For store managers, load their specific store
        storeInventoryTable.ajax.reload();
    }
});
</script> 