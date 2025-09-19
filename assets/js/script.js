/**
 * Legacy global helpers bundled on most admin pages.
 *
 * Responsibilities:
 * - Provide default DataTable initialization, Bootstrap datepicker wiring, and client-side validation rules for standard forms.
 * - Offer reusable confirmation dialogs, tooltip/popover activation, and responsive sidebar tweaks for mobile layouts.
 * - Expose utility functions (`showToast`, `showLoader`, etc.) consumed by feature-specific modules.
 *
 * Dependencies:
 * - jQuery, DataTables, SweetAlert, Bootstrap, and flatpickr (or alternative datepicker) depending on the markup.
 * - Works alongside `common.js` – this file focuses on component initialization while `common.js` manages layout chrome.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables
    initializeDataTables();
    
    // Initialize Date Pickers
    initializeDatepickers();
    
    // Form validation
    initializeFormValidation();
    
    // Initialize tooltips and popovers
    initializeTooltipsPopovers();
    
    // Handle confirmations
    handleConfirmations();
    
    // Mobile sidebar toggle
    handleMobileSidebar();
});

/**
 * Initialize DataTables with common configuration
 */
function initializeDataTables() {
    const dataTables = document.querySelectorAll('.datatable');
    
    if (dataTables.length > 0) {
        dataTables.forEach(function(table) {
            // Skip tables that are initialized elsewhere
            if (table.id === 'categoriesTable') {
                console.log("Skipping initialization of categoriesTable in script.js");
                return;
            }
            
            $(table).DataTable({
                responsive: true,
                language: {
                    searchPlaceholder: "Search...",
                    search: "",
                    lengthMenu: "_MENU_ records per page",
                },
                dom: '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>',
                initComplete: function() {
                    // Add search icon
                    const searchInput = document.querySelector('.dataTables_filter input');
                    if (searchInput) {
                        searchInput.parentElement.classList.add('input-group');
                        searchInput.classList.add('form-control');
                        
                        const inputGroup = document.createElement('div');
                        inputGroup.classList.add('input-group');
                        
                        const inputGroupPrepend = document.createElement('div');
                        inputGroupPrepend.classList.add('input-group-text');
                        inputGroupPrepend.innerHTML = '<i class="bi bi-search"></i>';
                        
                        searchInput.parentNode.insertBefore(inputGroup, searchInput);
                        inputGroup.appendChild(inputGroupPrepend);
                        inputGroup.appendChild(searchInput);
                    }
                },
                // Responsive display configuration
                responsive: {
                    details: {
                        display: $.fn.dataTable.Responsive.display.childRowImmediate,
                        type: 'none',
                        target: ''
                    }
                }
            });
        });
    }
}

/**
 * Initialize datepickers for date input fields
 */
function initializeDatepickers() {
    const datepickers = document.querySelectorAll('.datepicker');
    
    if (datepickers.length > 0) {
        datepickers.forEach(function(datepicker) {
            $(datepicker).datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true,
                yearRange: 'c-50:c+10'
            });
        });
    }
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation:not([data-ajax="true"])');
    
    if (forms.length > 0) {
        Array.from(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    }
}

/**
 * Initialize tooltips and popovers
 */
function initializeTooltipsPopovers() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if (tooltips.length > 0) {
        tooltips.forEach(function(tooltip) {
            new bootstrap.Tooltip(tooltip);
        });
    }
    
    const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
    if (popovers.length > 0) {
        popovers.forEach(function(popover) {
            new bootstrap.Popover(popover);
        });
    }
}

/**
 * Handle confirmation dialogs
 */
function handleConfirmations() {
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    
    if (confirmButtons.length > 0) {
        confirmButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const message = button.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';
                const title = button.getAttribute('data-confirm-title') || 'Confirm Action';
                const url = button.getAttribute('href') || button.getAttribute('data-url');
                const formId = button.getAttribute('data-form-id');
                
                Swal.fire({
                    title: title,
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#2c3e50',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        if (url) {
                            window.location.href = url;
                        } else if (formId) {
                            document.getElementById(formId).submit();
                        }
                    }
                });
            });
        });
    }
}

/**
 * Show toast notification
 * @param {string} title - The toast title
 * @param {string} message - The toast message
 * @param {string} type - The toast type (success, danger, warning, info)
 */
function showToast(title, message, type = 'success') {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.classList.add('toast-container', 'position-fixed', 'top-0', 'end-0', 'p-3');
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastElement = document.createElement('div');
    toastElement.classList.add('toast', 'align-items-center', `text-white`, `bg-${type}`, 'border-0');
    toastElement.setAttribute('role', 'alert');
    toastElement.setAttribute('aria-live', 'assertive');
    toastElement.setAttribute('aria-atomic', 'true');
    
    // Create toast content
    toastElement.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    // Add toast to container
    toastContainer.appendChild(toastElement);
    
    // Initialize and show toast
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();
    
    // Remove toast from DOM after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastContainer.removeChild(toastElement);
    });
}

/**
 * Format date to display format
 * @param {string} dateString - The date string to format
 * @return {string} - The formatted date
 */
function formatDate(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    return `${day}/${month}/${year}`;
}

/**
 * Format currency value
 * @param {number} amount - The amount to format
 * @param {number} decimals - Number of decimal places
 * @return {string} - The formatted currency value
 */
function formatCurrency(amount, decimals = 2) {
    return parseFloat(amount).toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Handle mobile sidebar toggle
 */
function handleMobileSidebar() {
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnToggle = mobileToggle.contains(event.target);
            
            if (window.innerWidth < 576 && !isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
        
        // Handle resize events
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 576 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    }
} 