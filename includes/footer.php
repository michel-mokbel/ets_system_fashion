            </div> <!-- End container-fluid -->
        </div> <!-- End main-content -->
    </div> <!-- End wrapper -->

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive@2.4.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-responsive-bs5@2.4.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo $base_url; ?>assets/js/common.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/script.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/mobile-responsive.js"></script>
    <?php
    $page = basename($_SERVER['SCRIPT_FILENAME']);
    if ($page === 'stores.php') {
        echo '<script src="' . $base_url . 'assets/js/stores.js"></script>';
    }
    if ($page === 'pos.php') {
        echo '<script src="' . $base_url . 'assets/js/pos.js"></script>';
    }
    ?>
    
    <script>
        // Enforced logout function for store managers and sales persons
        function handleEnforcedLogout() {
            console.log('handleEnforcedLogout called');
            
            // Show confirmation dialog
            if (!confirm('Are you sure you want to logout?\n\nYour shift will be automatically closed and a summary report will be generated.')) {
                console.log('User cancelled logout confirmation');
                return;
            }
            
            console.log('User confirmed logout, attempting to show modal');
            
            // Check if modal element exists
            const modalElement = document.getElementById('logoutSummaryModal');
            if (!modalElement) {
                console.error('Modal element logoutSummaryModal not found!');
                alert('Error: Logout modal not found. Please contact support.');
                return;
            }
            
            console.log('Modal element found, creating Bootstrap modal');
            
            // Show the modal with loading state
            try {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                console.log('Modal show() called successfully');
            } catch (error) {
                console.error('Error creating or showing modal:', error);
                alert('Error opening logout modal: ' + error.message);
                return;
            }
            
            // Reset modal state
            document.getElementById('logoutSummaryLoading').style.display = 'block';
            document.getElementById('logoutSummaryError').style.display = 'none';
            document.getElementById('logoutSummaryContent').style.display = 'none';
            document.getElementById('printLogoutSummary').style.display = 'none';
            document.getElementById('completeLogoutBtn').style.display = 'none';
            document.getElementById('forceLogoutBtn').style.display = 'none';
            
            console.log('Making AJAX call to enforced_logout.php');
            
            // Call enforced logout endpoint
            fetch('<?php echo $base_url; ?>ajax/enforced_logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => {
                console.log('Received response from enforced_logout.php:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Parsed response data:', data);
                
                // Hide loading
                document.getElementById('logoutSummaryLoading').style.display = 'none';
                
                if (data.success && data.can_logout) {
                    console.log('Logout successful, processing shift summary');
                    
                    if (data.shift_summary) {
                        console.log('Shift summary data:', data.shift_summary);
                        
                        // Populate the modal with shift summary
                        try {
                            populateLogoutSummary(data.shift_summary);
                            console.log('populateLogoutSummary completed successfully');
                        } catch (error) {
                            console.error('Error in populateLogoutSummary:', error);
                        }
                        
                        // Show summary content and buttons
                        document.getElementById('logoutSummaryContent').style.display = 'block';
                        document.getElementById('printLogoutSummary').style.display = 'inline-block';
                        document.getElementById('completeLogoutBtn').style.display = 'inline-block';
                        
                        console.log('Modal populated and buttons shown');
                    } else {
                        console.log('No shift summary, showing logout button only');
                        // No summary but can logout
                        document.getElementById('completeLogoutBtn').style.display = 'inline-block';
                    }
                } else {
                    console.log('Logout failed:', data.message);
                    
                    // Show error state
                    document.getElementById('logoutSummaryErrorMessage').textContent = data.message || 'Failed to close shift. Please try again.';
                    document.getElementById('logoutSummaryError').style.display = 'block';
                    document.getElementById('forceLogoutBtn').style.display = 'inline-block';
                    
                    console.log('Error state shown');
                }
            })
            .catch(error => {
                console.error('Logout error:', error);
                
                // Hide loading and show error
                document.getElementById('logoutSummaryLoading').style.display = 'none';
                document.getElementById('logoutSummaryErrorMessage').textContent = 'Network error occurred. Please check your connection and try again.';
                document.getElementById('logoutSummaryError').style.display = 'block';
                document.getElementById('forceLogoutBtn').style.display = 'inline-block';
            });
        }
        
        // Function to populate the logout summary modal
        function populateLogoutSummary(summary) {
            // Store the original summary data for printing
            window.logoutSummaryData = summary;
            
            // Store name and user info
            document.getElementById('summaryStoreName').textContent = summary.store_name || 'Store';
            document.getElementById('summaryUserName').textContent = summary.user_name || 'User';
            document.getElementById('summaryGeneratedAt').textContent = new Date().toLocaleString();
            
            // Shift information
            document.getElementById('summaryStartTime').textContent = formatTime(summary.start_time);
            document.getElementById('summaryEndTime').textContent = formatTime(summary.end_time);
            document.getElementById('summaryDuration').textContent = summary.duration;
            
            // Sales by Payment Method table
            let salesHtml = "";
            if (summary.payment_methods && summary.payment_methods.length > 0) {
                summary.payment_methods.forEach(function (method) {
                    let methodDisplay = capitalizeFirst(method.method);
                    let amountDisplay = `CFA ${method.amount.toFixed(2)}`;
                    
                    // Add cash/mobile breakdown for cash_mobile payments
                    if (method.method === 'cash_mobile' && method.cash_amount !== undefined && method.mobile_amount !== undefined) {
                        methodDisplay = 'Cash + Mobile';
                        const cashAmount = parseFloat(method.cash_amount) || 0;
                        const mobileAmount = parseFloat(method.mobile_amount) || 0;
                        amountDisplay = `
                            <div>Total: CFA ${method.amount.toFixed(2)}</div>
                            <div class="small text-muted">
                                Cash: CFA ${cashAmount.toFixed(2)} | Mobile: CFA ${mobileAmount.toFixed(2)}
                            </div>
                        `;
                    }
                    
                    salesHtml += `
                        <tr>
                            <td>${methodDisplay}</td>
                            <td>${method.count}</td>
                            <td>${amountDisplay}</td>
                        </tr>
                    `;
                });
            } else {
                salesHtml = '<tr><td colspan="3" class="text-center text-muted">No sales data</td></tr>';
            }
            document.getElementById('logoutSalesByPaymentTable').querySelector('tbody').innerHTML = salesHtml;
            document.getElementById('logoutTotalSalesAmount').textContent = 'CFA ' + (summary.net_sales || 0).toFixed(2);
            
            // Expenses table
            let expensesHtml = "";
            if (summary.expenses && summary.expenses.length > 0) {
                summary.expenses.forEach(function (expense) {
                    expensesHtml += `
                        <tr>
                            <td>${expense.category}</td>
                            <td>${expense.description}</td>
                            <td>CFA ${expense.amount.toFixed(2)}</td>
                            <td>${formatTime(expense.created_at)}</td>
                        </tr>
                    `;
                });
            } else {
                expensesHtml = '<tr><td colspan="4" class="text-center text-muted">No expenses recorded</td></tr>';
            }
            document.getElementById('logoutExpensesTable').querySelector('tbody').innerHTML = expensesHtml;
            document.getElementById('logoutTotalExpensesAmount').textContent = 'CFA ' + (summary.total_expenses || 0).toFixed(2);
            
            // Final Summary calculations
            document.getElementById('logoutTotalSalesSummary').textContent = 'CFA ' + (summary.net_sales || 0).toFixed(2);
            document.getElementById('logoutTotalExpensesSummary').textContent = 'CFA ' + (summary.total_expenses || 0).toFixed(2);
            document.getElementById('logoutNetTotalSummary').textContent = 'CFA ' + (summary.net_total || 0).toFixed(2);
        }
        
        // Helper function to capitalize first letter
        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }
        
        // Helper function to format time
        function formatTime(datetime) {
            const date = new Date(datetime);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }
        
        // Event handlers for logout summary modal buttons
        <?php if (in_array($user_role, ['store_manager', 'sales_person'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Complete logout button
            document.getElementById('completeLogoutBtn').addEventListener('click', function() {
                window.location.href = '<?php echo $base_url; ?>logout.php';
            });
            
            // Force logout button (for error cases)
            document.getElementById('forceLogoutBtn').addEventListener('click', function() {
                if (confirm('Are you sure you want to logout anyway? Your shift may not be properly recorded.')) {
                    window.location.href = '<?php echo $base_url; ?>logout.php';
                }
            });
            
            // Print summary button
            document.getElementById('printLogoutSummary').addEventListener('click', function() {
                printLogoutSummary();
            });
        });
        
        // Function to print the logout summary for 80mm thermal printer
        function printLogoutSummary() {
            // Get the summary data from the modal
            const summaryData = {
                store_name: document.getElementById('summaryStoreName').textContent || 'Store',
                user_name: document.getElementById('summaryUserName').textContent || 'User',
                generated_at: document.getElementById('summaryGeneratedAt').textContent || new Date().toLocaleString(),
                start_time: document.getElementById('summaryStartTime').textContent || '--:--',
                end_time: document.getElementById('summaryEndTime').textContent || '--:--',
                duration: document.getElementById('summaryDuration').textContent || '--',
                total_sales: document.getElementById('logoutTotalSalesAmount').textContent || 'CFA 0.00',
                total_expenses: document.getElementById('logoutTotalExpensesAmount').textContent || 'CFA 0.00',
                net_total: document.getElementById('logoutNetTotalSummary').textContent || 'CFA 0.00',
                payment_methods: window.logoutSummaryData?.payment_methods || getPaymentMethodsFromTable(),
                expenses: getExpensesFromTable()
            };
            
            // Generate 80mm optimized print layout
            const printContent = generate80mmLogoutLayout(summaryData);
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            
            // Wait for content to load then print
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
        
        // Helper function to get payment methods from table
        function getPaymentMethodsFromTable() {
            const table = document.getElementById('logoutSalesByPaymentTable');
            const rows = table.querySelectorAll('tbody tr');
            const methods = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length === 3 && !row.classList.contains('text-muted')) {
                    methods.push({
                        method: cells[0].textContent,
                        count: parseInt(cells[1].textContent),
                        amount: parseFloat(cells[2].textContent.replace('CFA ', '').replace(',', ''))
                    });
                }
            });
            
            return methods;
        }
        
        // Helper function to get expenses from table
        function getExpensesFromTable() {
            const table = document.getElementById('logoutExpensesTable');
            const rows = table.querySelectorAll('tbody tr');
            const expenses = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length === 4 && !row.classList.contains('text-muted')) {
                    expenses.push({
                        category: cells[0].textContent,
                        description: cells[1].textContent,
                        amount: parseFloat(cells[2].textContent.replace('CFA ', '').replace(',', ''))
                    });
                }
            });
            
            return expenses;
        }
        
        // Generate 80mm thermal printer optimized layout for logout summary
        function generate80mmLogoutLayout(data) {
            const currentDate = new Date().toLocaleDateString('fr-FR');
            const currentTime = new Date().toLocaleTimeString('fr-FR', { hour12: false });
            
            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Résumé de Shift</title>
                    <style>
                        @page {
                            size: 80mm auto;
                            margin: 2mm;
                        }
                        * {
                            margin: 0;
                            padding: 0;
                            box-sizing: border-box;
                        }
                        body {
                            font-family: 'Courier New', monospace;
                            font-size: 11px;
                            line-height: 1.2;
                            color: #000;
                            background: white;
                            width: 76mm;
                            margin: 0 auto;
                        }
                        .receipt-header {
                            text-align: center;
                            margin-bottom: 8px;
                            border-bottom: 1px dashed #000;
                            padding-bottom: 5px;
                        }
                        .store-name {
                            font-size: 14px;
                            font-weight: bold;
                            margin-bottom: 2px;
                        }
                        .receipt-title {
                            font-size: 12px;
                            font-weight: bold;
                            margin: 3px 0;
                        }
                        .receipt-section {
                            margin: 6px 0;
                            padding: 3px 0;
                        }
                        .section-title {
                            font-weight: bold;
                            font-size: 11px;
                            margin-bottom: 3px;
                            text-decoration: underline;
                        }
                        .info-line {
                            display: flex;
                            justify-content: space-between;
                            margin: 1px 0;
                            font-size: 10px;
                        }
                        .info-label {
                            flex: 1;
                        }
                        .info-value {
                            flex: 1;
                            text-align: right;
                            font-weight: bold;
                        }
                        .separator {
                            border-top: 1px dashed #000;
                            margin: 5px 0;
                        }
                        .total-line {
                            font-weight: bold;
                            font-size: 11px;
                            border-top: 1px solid #000;
                            border-bottom: 1px solid #000;
                            padding: 2px 0;
                            margin: 3px 0;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 8px;
                            font-size: 10px;
                            border-top: 1px dashed #000;
                            padding-top: 5px;
                        }
                        .thank-you {
                            font-weight: bold;
                            margin: 3px 0;
                        }
                        @media print {
                            body { 
                                width: 76mm !important;
                                font-size: 11px !important;
                            }
                            .receipt-header, .receipt-section, .footer {
                                page-break-inside: avoid;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-header">
                        <div class="store-name">${data.store_name}</div>
                        <div class="receipt-title">RESUME DE SHIFT</div>
                        <div style="font-size: 9px;">${currentDate} ${currentTime}</div>
                    </div>

                    <div class="receipt-section">
                        <div class="section-title">INFORMATIONS SHIFT</div>
                        <div class="info-line">
                            <span class="info-label">Employé:</span>
                            <span class="info-value">${data.user_name}</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Début:</span>
                            <span class="info-value">${data.start_time}</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Fin:</span>
                            <span class="info-value">${data.end_time}</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Durée:</span>
                            <span class="info-value">${data.duration}</span>
                        </div>
                    </div>

                    <div class="separator"></div>

                    <div class="receipt-section">
                        <div class="section-title">VENTES PAR MODE DE PAIEMENT</div>
                        ${data.payment_methods && data.payment_methods.length > 0 ? 
                            data.payment_methods.map(method => {
                                let methodName = capitalizeFirst(method.method);
                                let amountDisplay = `CFA ${method.amount.toFixed(2)}`;
                                
                                // Handle cash_mobile payments with breakdown
                                if (method.method === 'cash_mobile' && method.cash_amount !== undefined && method.mobile_amount !== undefined) {
                                    methodName = 'Espèces + Mobile';
                                    const cashAmount = parseFloat(method.cash_amount) || 0;
                                    const mobileAmount = parseFloat(method.mobile_amount) || 0;
                                    amountDisplay = `CFA ${method.amount.toFixed(2)}`;
                                    
                                    return `
                                        <div class="info-line">
                                            <span class="info-label">${methodName}:</span>
                                            <span class="info-value">${amountDisplay}</span>
                                        </div>
                                        <div class="info-line" style="margin-left: 3mm; font-size: 10px;">
                                            <span class="info-label">Espèces:</span>
                                            <span class="info-value">CFA ${cashAmount.toFixed(2)}</span>
                                        </div>
                                        <div class="info-line" style="margin-left: 3mm; font-size: 10px;">
                                            <span class="info-label">Mobile:</span>
                                            <span class="info-value">CFA ${mobileAmount.toFixed(2)}</span>
                                        </div>
                                    `;
                                } else {
                                    return `
                                        <div class="info-line">
                                            <span class="info-label">${methodName}:</span>
                                            <span class="info-value">${amountDisplay}</span>
                                        </div>
                                    `;
                                }
                            }).join('') : 
                            '<div class="info-line"><span class="info-label">Aucune vente</span><span class="info-value">CFA 0.00</span></div>'
                        }
                        <div class="total-line">
                            <div class="info-line">
                                <span class="info-label">TOTAL VENTES:</span>
                                <span class="info-value">${data.total_sales}</span>
                            </div>
                        </div>
                    </div>

                    <div class="separator"></div>

                    <div class="receipt-section">
                        <div class="section-title">DEPENSES</div>
                        ${data.expenses && data.expenses.length > 0 ? 
                            data.expenses.map(expense => `
                                <div class="info-line">
                                    <span class="info-label">${expense.category}:</span>
                                    <span class="info-value">CFA ${expense.amount.toFixed(2)}</span>
                                </div>
                                <div style="font-size: 9px; margin-left: 5px; margin-bottom: 2px;">
                                    ${expense.description.length > 20 ? expense.description.substring(0, 20) + '...' : expense.description}
                                </div>
                            `).join('') : 
                            '<div class="info-line"><span class="info-label">Aucune dépense</span><span class="info-value">CFA 0.00</span></div>'
                        }
                        <div class="total-line">
                            <div class="info-line">
                                <span class="info-label">TOTAL DEPENSES:</span>
                                <span class="info-value">${data.total_expenses}</span>
                            </div>
                        </div>
                    </div>

                    <div class="separator"></div>

                    <div class="total-line">
                        <div class="info-line">
                            <span class="info-label">TOTAL NET:</span>
                            <span class="info-value">${data.net_total}</span>
                        </div>
                    </div>

                    <div class="footer">
                        <div class="thank-you">SHIFT TERMINE AVEC SUCCES!</div>
                        <div style="font-size: 9px; margin-top: 3px;">
                            Merci pour votre travail
                        </div>
                        <div style="font-size: 8px; margin-top: 5px;">
                            Imprimé le ${currentDate} à ${currentTime}
                        </div>
                    </div>
                </body>
                </html>
            `;
        }
        <?php endif; ?>

        // Prevent accidental tab/window closing for store managers and sales persons
        <?php if (in_array($user_role, ['store_manager', 'sales_person'])): ?>
        window.addEventListener('beforeunload', function (e) {
            // Check if user has an active shift (this is a basic check)
            // The actual shift closure will be handled by the logout.php automatic closure
            const message = 'You have an active shift. Please use the logout button to properly close your shift.';
            e.preventDefault();
            e.returnValue = message;
            return message;
        });
        <?php endif; ?>

        // Initialize toasts
        var toastElList = [].slice.call(document.querySelectorAll('.toast'));
        var toastList = toastElList.map(function(toastEl) {
            return new bootstrap.Toast(toastEl, {
                autohide: true,
                delay: 5000
            }).show();
        });

        // Menu group functionality
        $(document).ready(function() {
            // Debug: Log current page for troubleshooting
            console.log('Current page:', '<?php echo $current_page; ?>');
            
            // Debug: Log which sections should be expanded
            $('.menu-group-toggle').each(function() {
                const $this = $(this);
                const target = $this.attr('href');
                const hasExpandedClass = $this.hasClass('expanded');
                const hasActiveSubPage = $(target).find('a.active').length > 0;
                console.log('Section:', target, 'PHP expanded:', hasExpandedClass, 'Has active page:', hasActiveSubPage);
            });
            // Handle menu group toggle clicks
            $('.menu-group-toggle').on('click', function(e) {
                e.preventDefault();
                
                const $this = $(this);
                const target = $this.attr('href');
                const $collapse = $(target);
                
                // Toggle the expanded class
                $this.toggleClass('expanded');
                
                // Use Bootstrap's collapse functionality
                $collapse.collapse('toggle');
                
                // Store collapsed state in localStorage
                const menuId = target.replace('#', '');
                const isExpanded = $this.hasClass('expanded');
                localStorage.setItem('menu_' + menuId, isExpanded ? '1' : '0');
            });
            
            // Handle Bootstrap collapse events for smooth icon rotation
            $('.collapse').on('show.bs.collapse', function() {
                const menuId = this.id;
                const $toggle = $('[href="#' + menuId + '"]');
                $toggle.addClass('expanded');
            });
            
            $('.collapse').on('hide.bs.collapse', function() {
                const menuId = this.id;
                const $toggle = $('[href="#' + menuId + '"]');
                $toggle.removeClass('expanded');
            });
            
            // Initialize menu states: Only expand the section with active page
            let activePageFound = false;
            
            $('.menu-group-toggle').each(function() {
                const $this = $(this);
                const target = $this.attr('href');
                const menuId = target.replace('#', '');
                const $collapse = $(target);
                
                // Check if this section contains the current active page
                const hasActivePage = $collapse.find('a.active').length > 0;
                
                if (hasActivePage && !activePageFound) {
                    // First section with active page - expand it
                    $this.addClass('expanded');
                    $collapse.addClass('show');
                    localStorage.setItem('menu_' + menuId, '1');
                    activePageFound = true;
                    console.log('Expanded section:', target, '(contains active page)');
                } else {
                    // All other sections - collapse them initially
                    $this.removeClass('expanded');
                    $collapse.removeClass('show');
                    
                    // Only apply localStorage for sections without active pages
                    if (!hasActivePage) {
                        const savedState = localStorage.getItem('menu_' + menuId);
                        if (savedState === '1') {
                            $this.addClass('expanded');
                            $collapse.addClass('show');
                            console.log('Restored section from localStorage:', target);
                        } else {
                            localStorage.setItem('menu_' + menuId, '0');
                        }
                    } else {
                        localStorage.setItem('menu_' + menuId, '0');
                    }
                }
            });
        });
    </script>
</body>
</html> 