<?php
/**
 * Box Quantity Comparison Dashboard
 * ---------------------------------
 * Provides procurement and warehouse teams with a real-time comparison between
 * the quantities recorded when containers were unpacked and the current stock
 * levels stored in warehouse boxes. The page leverages the header bootstrap for
 * authentication, exposes CSRF tokens for AJAX calls, and relies on
 * `assets/js/boxes.js` in combination with `ajax/get_box_comparison.php` to
 * populate statistic cards, charts, and discrepancy tables for reconciliation
 * work.
 */
ob_start();
require_once '../includes/header.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

// Only admins and inventory managers can view box comparison
if (!can_access_inventory()) {
    redirect('../index.php');
}
?>

<body>
    <!-- Hidden CSRF token for AJAX requests -->
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div class="container-fluid py-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-2">
            <h1 class="mb-3 mb-md-0">
                <i class="bi bi-box-seam me-2"></i>Box Quantity Comparison
            </h1>
            <div class="d-flex gap-2">
                <button class="btn btn-info" id="refreshComparison">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Box Types</h6>
                                <h3 class="mb-0" id="totalBoxTypes">-</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-boxes fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Boxes in Stock</h6>
                                <h3 class="mb-0" id="boxesInStock">-</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Out of Stock</h6>
                                <h3 class="mb-0" id="outOfStock">-</h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-exclamation-triangle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box Comparison Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-table me-2"></i>Box Quantity Comparison
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="boxComparisonTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Box Name</th>
                                <th>Original Quantity</th>
                                <th>Current Quantity</th>
                                <th>Status</th>
                                <th>Container</th>
                                <th>Box Type</th>
                            </tr>
                        </thead>
                        <tbody id="boxComparisonTableBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading box data...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Include footer -->
    <?php include '../includes/footer.php'; ?>

    <script>
        $(document).ready(function() {
            // Load box comparison data
            loadBoxComparison();
            
            // Refresh button
            $('#refreshComparison').click(function() {
                loadBoxComparison();
            });
        });

        function loadBoxComparison() {
            $.ajax({
                url: '../ajax/get_box_comparison.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response.success) {
                        displayBoxComparison(response.data);
                        updateStatistics(response.data);
                        if (response.debug) {
                            console.log('Debug info:', response.debug);
                        }
                    } else {
                        showAlert('Error loading box data: ' + response.message, 'danger');
                        console.error('Server error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    showAlert('Error loading box data. Status: ' + xhr.status + '. Please check console for details.', 'danger');
                }
            });
        }

        function displayBoxComparison(data) {
            const tbody = $('#boxComparisonTableBody');
            tbody.empty();

            if (data.length === 0) {
                tbody.append(`
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No box data found
                        </td>
                    </tr>
                `);
                return;
            }

            data.forEach(function(box) {
                const statusClass = getStatusClass(box.original_qty, box.current_qty);
                const statusText = getStatusText(box.original_qty, box.current_qty);
                
                tbody.append(`
                    <tr>
                        <td><strong>${escapeHtml(box.box_name)}</strong></td>
                        <td><span class="badge bg-info">${box.original_qty}</span></td>
                        <td><span class="badge bg-primary">${box.current_qty}</span></td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td>${escapeHtml(box.container_number || 'N/A')}</td>
                        <td><span class="badge bg-secondary">${escapeHtml(box.box_type)}</span></td>
                    </tr>
                `);
            });
        }

        function getStatusClass(originalQty, currentQty) {
            if (currentQty === 0) {
                return 'bg-danger';
            } else if (currentQty < originalQty) {
                return 'bg-warning';
            } else {
                return 'bg-success';
            }
        }

        function getStatusText(originalQty, currentQty) {
            if (currentQty === 0) {
                return 'Out of Stock';
            } else if (currentQty < originalQty) {
                return 'Low Stock';
            } else {
                return 'In Stock';
            }
        }

        function updateStatistics(data) {
            const totalBoxTypes = data.length;
            const boxesInStock = data.filter(box => box.current_qty > 0).length;
            const outOfStock = data.filter(box => box.current_qty === 0).length;

            $('#totalBoxTypes').text(totalBoxTypes);
            $('#boxesInStock').text(boxesInStock);
            $('#outOfStock').text(outOfStock);
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('.container-fluid').prepend(alertHtml);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }
    </script>
</body>
</html>
