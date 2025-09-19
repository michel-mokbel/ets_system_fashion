/**
 * Global UX bootstrap utilities shared across every page.
 *
 * Responsibilities:
 * - Wire sidebar toggle behaviour (desktop collapse persistence, mobile overlay, keyboard shortcut) and broadcast events for dependent modules.
 * - Initialize Bootstrap tooltips/popovers, DataTable defaults, AJAX form helpers, and sidebar menu interactions consumed by individual screens.
 * - Provide cross-cutting helpers (`toggleSidebar`, `updateToggleIcon`, etc.) that other scripts call via the global scope.
 *
 * Dependencies:
 * - jQuery for event handling and DOM manipulation, DataTables for shared grid defaults, and Bootstrap 5 JavaScript for tooltips/offcanvas components.
 * - Local storage for persisting sidebar collapsed state between sessions.
 */

// Initialize all common functionality when DOM is ready
$(document).ready(function() {
    // Initialize sidebar toggle functionality
    initializeSidebarToggle();
    
    // Initialize other components
    initializeBootstrapComponents();
    
    // Initialize DataTables
    initializeDataTables();
    
    // Initialize AJAX form handler
    initializeAjaxForms();
    
    // Initialize sidebar menu
    initializeSidebarMenu();
});

/**
 * Initialize Universal Sidebar Toggle Functionality
 */
function initializeSidebarToggle() {
    const sidebar = $('#sidebar');
    const sidebarToggle = $('#sidebarToggle');
    const sidebarToggleInside = $('#sidebarToggleInside');
    
    // Check for saved sidebar state (desktop only)
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    const isMobile = window.innerWidth <= 768;
    
    // Apply saved state (desktop only)
    if (sidebarCollapsed && !isMobile) {
        sidebar.addClass('collapsed');
        updateToggleIcon(true);
        updateTogglePosition(true);
    }
    
    // Toggle button click handlers
    sidebarToggle.off().on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        const sidebar = $('#sidebar');
        const backdrop = $('#mobileBackdrop');
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            const isVisible = sidebar.hasClass('show');
            
            if (isVisible) {
                sidebar.removeClass('show');
                backdrop.removeClass('show');
            } else {
                sidebar.addClass('show');
                backdrop.addClass('show');
            }
        } else {
            // Desktop behavior
            const isCollapsed = sidebar.hasClass('collapsed');
            if (isCollapsed) {
                sidebar.removeClass('collapsed');
                updateToggleIcon(false);
                updateTogglePosition(false);
            } else {
                sidebar.addClass('collapsed');
                updateToggleIcon(true);
                updateTogglePosition(true);
            }
        }
        
        return false;
    });
    
    sidebarToggleInside.off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        setTimeout(() => {
            toggleSidebar();
        }, 50);
        
        return false;
    });
    
    // Mobile backdrop click handler
    $('#mobileBackdrop').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (window.innerWidth <= 768) {
            const sidebar = $('#sidebar');
            const backdrop = $(this);
            if (sidebar.hasClass('show')) {
                sidebar.removeClass('show');
                backdrop.removeClass('show');
                $(document).trigger('sidebar:hidden');
            }
        }
    });
    
    // Keyboard shortcut (Ctrl/Cmd + B)
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            toggleSidebar();
        }
    });
    
    // Add data-title attributes for tooltips
    addTooltipAttributes();
    
    // Handle window resize for toggle button positioning and mobile/desktop transitions
    $(window).on('resize', function() {
        const sidebar = $('#sidebar');
        const isMobile = window.innerWidth <= 768;
        const isCollapsed = sidebar.hasClass('collapsed');
        
        if (isMobile) {
            // On mobile, remove collapsed class and hide sidebar by default
            sidebar.removeClass('collapsed');
            sidebar.removeClass('show');
            $('#mobileBackdrop').removeClass('show');
        } else {
            // On desktop, restore collapsed state from localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.addClass('collapsed');
                updateToggleIcon(true);
            }
            updateTogglePosition(isCollapsed);
        }
    });
}

/**
 * Toggle sidebar collapsed state
 */
function toggleSidebar() {
    console.log('toggleSidebar called');
    const sidebar = $('#sidebar');
    const sidebarToggle = $('#sidebarToggle');
    const isMobile = window.innerWidth <= 768;
    
    console.log('isMobile:', isMobile, 'window width:', window.innerWidth);
    
    if (isMobile) {
        // Mobile behavior: toggle show/hide
        const isVisible = sidebar.hasClass('show');
        const backdrop = $('#mobileBackdrop');
        
        console.log('Mobile mode - sidebar currently visible:', isVisible);
        console.log('Sidebar classes before:', sidebar.attr('class'));
        
        if (isVisible) {
            console.log('Hiding sidebar');
            sidebar.removeClass('show');
            backdrop.removeClass('show');
            // Trigger custom event
            $(document).trigger('sidebar:hidden');
        } else {
            console.log('Showing sidebar');
            sidebar.addClass('show');
            backdrop.addClass('show');
            // Trigger custom event
            $(document).trigger('sidebar:shown');
        }
        
        console.log('Sidebar classes after:', sidebar.attr('class'));
        console.log('Backdrop classes after:', backdrop.attr('class'));
    } else {
        // Desktop behavior: toggle collapsed/expanded
        const isCollapsed = sidebar.hasClass('collapsed');
        
        if (isCollapsed) {
            sidebar.removeClass('collapsed');
            localStorage.setItem('sidebarCollapsed', 'false');
            updateToggleIcon(false);
            updateTogglePosition(false);
            
            // Trigger custom event
            $(document).trigger('sidebar:expanded');
        } else {
            sidebar.addClass('collapsed');
            localStorage.setItem('sidebarCollapsed', 'true');
            updateToggleIcon(true);
            updateTogglePosition(true);
            
            // Close any open submenus when collapsing
            $('.sidebar-menu .collapse.show').collapse('hide');
            
            // Trigger custom event
            $(document).trigger('sidebar:collapsed');
        }
    }
    
    // Trigger window resize to help DataTables and other components adjust
    setTimeout(() => {
        $(window).trigger('resize');
    }, 300);
}

// Make toggleSidebar globally accessible
window.toggleSidebar = toggleSidebar;

/**
 * Update toggle button icons
 */
function updateToggleIcon(isCollapsed) {
    const sidebarToggle = $('#sidebarToggle i');
    const sidebarToggleInside = $('#sidebarToggleInside i');
    
    if (isCollapsed) {
        sidebarToggle.removeClass('bi-list').addClass('bi-chevron-right');
        sidebarToggleInside.removeClass('bi-chevron-left').addClass('bi-chevron-right');
    } else {
        sidebarToggle.removeClass('bi-chevron-right').addClass('bi-list');
        sidebarToggleInside.removeClass('bi-chevron-right').addClass('bi-chevron-left');
    }
}

/**
 * Update toggle button position
 */
function updateTogglePosition(isCollapsed) {
    const sidebarToggle = $('#sidebarToggle');
    
    // Skip on mobile
    if (window.innerWidth <= 768) {
        return;
    }
    
    if (isCollapsed) {
        // Position next to collapsed sidebar (60px + 15px = 75px)
        sidebarToggle.css('left', '75px');
    } else {
        // Position next to expanded sidebar (205px + 15px = 220px)  
        sidebarToggle.css('left', '220px');
    }
}

/**
 * Add data-title attributes for tooltip functionality
 */
function addTooltipAttributes() {
    // Add tooltips to menu items
    $('.sidebar-menu li a').each(function() {
        const $this = $(this);
        const text = $this.find('span').text().trim();
        if (text) {
            $this.attr('data-title', text);
        }
    });
    
    // Add tooltips to menu group toggles
    $('.sidebar-menu .menu-group-toggle').each(function() {
        const $this = $(this);
        const text = $this.find('span').text().trim();
        if (text) {
            $this.attr('data-title', text);
        }
    });
}

/**
 * Initialize sidebar menu functionality
 */
function initializeSidebarMenu() {
    // Handle menu group toggles
    $('.menu-group-toggle').on('click', function(e) {
        e.preventDefault();
        
        const $this = $(this);
        const sidebar = $('#sidebar');
        
        // Don't toggle submenus when sidebar is collapsed
        if (sidebar.hasClass('collapsed')) {
            return;
        }
        
        const target = $this.attr('href') || $this.data('bs-target');
        const $target = $(target);
        const $icon = $this.find('.toggle-icon');
        
        // Toggle the collapse
        $target.collapse('toggle');
        
        // Update the icon and expanded class
        $target.on('shown.bs.collapse', function() {
            $this.addClass('expanded');
            $icon.css('transform', 'rotate(180deg)');
        });
        
        $target.on('hidden.bs.collapse', function() {
            $this.removeClass('expanded');
            $icon.css('transform', 'rotate(0deg)');
        });
    });
    
    // Handle sidebar menu item clicks on mobile
    $('.sidebar-menu a:not(.menu-group-toggle)').on('click', function() {
        const sidebar = $('#sidebar');
        
        // Close sidebar on mobile when clicking menu items
        if (window.innerWidth <= 768 && sidebar.hasClass('show')) {
            sidebar.removeClass('show');
            $('#mobileBackdrop').removeClass('show');
        }
    });
    
    // Close sidebar when clicking outside on mobile - DISABLED to prevent conflicts
    // The backdrop click handler will handle closing the sidebar
    /*
    $(document).on('click', function(e) {
        const sidebar = $('#sidebar');
        const sidebarToggle = $('#sidebarToggle');
        const backdrop = $('#mobileBackdrop');
        
        if (window.innerWidth <= 768 && 
            sidebar.hasClass('show') && 
            !sidebar.is(e.target) && 
            sidebar.has(e.target).length === 0 && 
            !sidebarToggle.is(e.target) && 
            sidebarToggle.has(e.target).length === 0 &&
            !backdrop.is(e.target)) {
            
            sidebar.removeClass('show');
            backdrop.removeClass('show');
            $(document).trigger('sidebar:hidden');
        }
    });
    */
}

/**
 * Initialize all forms with AJAX submissions
 */
function initializeAjaxForms() {
    // Find all forms with the 'ajax-form' class
    document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }
            
            const formData = new FormData(this);
            const formAction = this.getAttribute('action');
            
            // Show loading
            Swal.fire({
                title: 'Processing...',
                html: 'Please wait while we process your request.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Check for any file input in the form
            const hasFileInput = !!this.querySelector('input[type="file"]');
            if (hasFileInput) {
                // Always use FormData for forms with file input
                $.ajax({
                    url: formAction,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        handleAjaxFormResponse(form, response);
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire({
                            title: 'Connection Error!',
                            text: 'Failed to connect to the server. Please try again.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                        console.error('AJAX Error:', status, error);
                    }
                });
            } else {
                // Convert FormData to object
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });
                // Submit as object
                $.ajax({
                    url: formAction,
                    type: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        Swal.close();
                        handleAjaxFormResponse(form, response);
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire({
                            title: 'Connection Error!',
                            text: 'Failed to connect to the server. Please try again.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545'
                        });
                        console.error('AJAX Error:', status, error);
                    }
                });
            }
        });
    });
}

/**
 * Initialize DataTables with common settings
 */
function initializeDataTables() {
    $('.datatable:not(.initialized)').each(function() {
        // Skip tables that are initialized elsewhere
        if (this.id === 'categoriesTable') {
            console.log("Skipping initialization of categoriesTable in common.js");
            return;
        }
        
        $(this).addClass('initialized');
        
        // Determine if we're on mobile/tablet
        const isMobile = window.innerWidth <= 767;
        const isTablet = window.innerWidth > 767 && window.innerWidth <= 991;
        
        const table = $(this).DataTable({
            responsive: {
                details: {
                    type: isMobile ? 'column' : 'inline',
                    target: isMobile ? 'tr' : 0,
                    renderer: function (api, rowIdx, columns) {
                        if (isMobile) {
                            // Mobile card-style renderer
                            var data = $.map(columns, function (col, i) {
                                return col.hidden ?
                                    '<div class="mobile-card-row">' +
                                        '<span class="mobile-card-label">' + col.title + '</span>' +
                                        '<span class="mobile-card-value">' + col.data + '</span>' +
                                    '</div>' : '';
                            }).join('');
                            return data ? '<div class="mobile-card-details">' + data + '</div>' : false;
                        } else {
                            // Desktop inline renderer
                            return $.fn.dataTable.Responsive.defaults.details.renderer(api, rowIdx, columns);
                        }
                    }
                }
            },
            pageLength: isMobile ? 5 : (isTablet ? 8 : 10),
            lengthMenu: isMobile ? [5, 10, 25] : (isTablet ? [8, 15, 30] : [10, 25, 50, 100]),
            language: {
                searchPlaceholder: isMobile ? "Search..." : "Search records...",
                search: "",
                lengthMenu: isMobile ? "_MENU_" : "_MENU_ records per page",
                emptyTable: "No data available",
                zeroRecords: "No matching records found",
                info: isMobile ? "_START_-_END_ of _TOTAL_" : "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: isMobile ? "0 of 0" : "Showing 0 to 0 of 0 entries",
                infoFiltered: isMobile ? "(filtered)" : "(filtered from _MAX_ total entries)",
                paginate: {
                    first: isMobile ? "First" : "First",
                    last: isMobile ? "Last" : "Last",
                    next: isMobile ? ">" : "Next",
                    previous: isMobile ? "<" : "Previous"
                }
            },
            dom: isMobile ? 
                '<"mobile-datatable-header"<"d-flex justify-content-between mb-2"<"mobile-length"l><"mobile-search"f>>>t<"mobile-datatable-footer"<"d-flex justify-content-between mt-2"<"mobile-info"i><"mobile-pagination"p>>>' :
                '<"d-flex justify-content-between align-items-center mb-3"<"d-flex align-items-center"l><"d-flex"f>>t<"d-flex justify-content-between align-items-center mt-3"<"text-muted"i><"pagination-container"p>>',
            columnDefs: [
                {
                    targets: 0,
                    className: isMobile ? 'dtr-control' : '',
                    orderable: false,
                    data: null,
                    defaultContent: isMobile ? '' : null,
                    // Keep first column visible for sticky identity; DataTables will hide based on responsive priorities
                    visible: true
                }
            ],
            initComplete: function(settings, json) {
                const $table = $(this);
                const $wrapper = $table.closest('.dataTables_wrapper');
                
                if (isMobile) {
                    // Add mobile-specific classes
                    $wrapper.addClass('mobile-datatable-wrapper');
                    
                    // Style mobile search input
                    $wrapper.find('.dataTables_filter input').addClass('form-control-sm');
                    
                    // Style mobile length select
                    $wrapper.find('.dataTables_length select').addClass('form-select form-select-sm');
                    
                    // Add mobile touch feedback
                    $table.on('click', 'tr', function() {
                        $(this).addClass('touching');
                        setTimeout(() => $(this).removeClass('touching'), 150);
                    });
                }
                
                // Initialize mobile responsive handler if available
                if (window.MobileResponsive && window.MobileResponsive.isMobileDevice()) {
                    window.MobileResponsive.convertTable($table.closest('.table-responsive'));
                }
            },
            drawCallback: function(settings) {
                const $wrapper = $(this).closest('.dataTables_wrapper');
                
                if (isMobile) {
                    // Enhance mobile pagination
                    $wrapper.find('.paginate_button').addClass('btn btn-sm btn-outline-secondary me-1 mb-1');
                    $wrapper.find('.paginate_button.current').removeClass('btn-outline-secondary').addClass('btn-primary');
                    $wrapper.find('.paginate_button.disabled').addClass('disabled');
                }
            }
        });
        
        // Add resize handler to recalculate responsive columns
        $(window).on('resize.datatable', function() {
            if (table.responsive) {
                table.responsive.recalc();
            }
        });
    });
}

/**
 * Initialize Bootstrap components
 */
function initializeBootstrapComponents() {
    // Initialize tooltips
    initializeTooltipsPopovers();
    
    // Fix aria-hidden accessibility issue with modals
    initializeModalAccessibilityFix();
}

/**
 * Initialize tooltips and popovers
 */
function initializeTooltipsPopovers() {
    // Dispose existing tooltips first
    $('[data-bs-toggle="tooltip"]').tooltip('dispose');
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover'
        });
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Show a confirmation dialog using SweetAlert
 * @param {Object} options - Configuration options
 * @param {string} options.title - Dialog title
 * @param {string} options.text - Dialog message
 * @param {string} options.icon - Dialog icon (warning, error, success, info, question)
 * @param {Function} options.confirmCallback - Function to call when confirmed
 * @param {Function} options.cancelCallback - Function to call when cancelled
 */
function showConfirmation(options) {
    const defaultOptions = {
        title: 'Are you sure?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2c3e50',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'Cancel'
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    Swal.fire(finalOptions).then((result) => {
        if (result.isConfirmed && typeof finalOptions.confirmCallback === 'function') {
            finalOptions.confirmCallback();
        } else if (result.dismiss && typeof finalOptions.cancelCallback === 'function') {
            finalOptions.cancelCallback();
        }
    });
}

/**
 * Fix aria-hidden accessibility issue with Bootstrap modals
 * Prevents the wrapper div and modals from having aria-hidden when they contain focusable elements
 */
function initializeModalAccessibilityFix() {
    // Monitor for modals being shown
    document.addEventListener('show.bs.modal', function(event) {
        // Remove aria-hidden from wrapper to fix accessibility issue
        const wrapper = document.getElementById('wrapper');
        if (wrapper) {
            wrapper.removeAttribute('aria-hidden');
        }
        
        // Also remove aria-hidden from the modal itself
        const modal = event.target;
        if (modal) {
            modal.removeAttribute('aria-hidden');
        }
    });
    
    // Monitor for modals being shown (after shown)
    document.addEventListener('shown.bs.modal', function(event) {
        // Ensure modal doesn't have aria-hidden after being shown
        const modal = event.target;
        if (modal && modal.hasAttribute('aria-hidden')) {
            console.log('Removing aria-hidden from modal for accessibility');
            modal.removeAttribute('aria-hidden');
        }
    });
    
    // Monitor for modals being hidden
    document.addEventListener('hidden.bs.modal', function(event) {
        // Ensure wrapper doesn't have aria-hidden after modal closes
        const wrapper = document.getElementById('wrapper');
        if (wrapper) {
            wrapper.removeAttribute('aria-hidden');
        }
    });
    
    // Also monitor for any mutations that add aria-hidden to wrapper or modals
    if (window.MutationObserver) {
        const wrapper = document.getElementById('wrapper');
        if (wrapper) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'aria-hidden') {
                        // If aria-hidden was added to wrapper, remove it
                        if (wrapper.hasAttribute('aria-hidden')) {
                            console.log('Removing aria-hidden from wrapper for accessibility');
                            wrapper.removeAttribute('aria-hidden');
                        }
                    }
                });
            });
            
            observer.observe(wrapper, {
                attributes: true,
                attributeFilter: ['aria-hidden']
            });
        }
        
        // Monitor all modals for aria-hidden changes
        document.querySelectorAll('.modal').forEach(function(modal) {
            const modalObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'aria-hidden') {
                        // If modal is visible but has aria-hidden, remove it
                        if (modal.classList.contains('show') && modal.hasAttribute('aria-hidden')) {
                            console.log('Removing aria-hidden from visible modal for accessibility');
                            modal.removeAttribute('aria-hidden');
                        }
                    }
                });
            });
            
            modalObserver.observe(modal, {
                attributes: true,
                attributeFilter: ['aria-hidden']
            });
        });
    }
} 

// GLOBAL SWEETALERT LOADER FOR ALL AJAX REQUESTS
$(document).ajaxStart(function() {
    if (!Swal.isVisible()) {
        Swal.fire({
            title: 'Processing...',
            html: 'Please wait while we process your request.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
});
$(document).ajaxStop(function() {
    if (Swal.isVisible()) {
        Swal.close();
    }
});

// SweetAlert loader for all DataTables
$(document).on('preXhr.dt', '.datatable', function () {
    Swal.fire({
        title: 'Loading...',
        html: '<div class="spinner-border text-primary" role="status"></div>',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
});
$(document).on('xhr.dt', '.datatable', function () {
    Swal.close();
});

function handleAjaxFormResponse(form, response) {
    if (response.success) {
        // Close parent modal if any
        const $form = $(form);
        const $modal = $form.closest('.modal');
        if ($modal.length) {
            const modalInstance = bootstrap.Modal.getInstance($modal[0]) || new bootstrap.Modal($modal[0]);
            modalInstance.hide();
        }
        // Refresh DataTable if target specified on form
        const reloadSelector = $form.data('reload-table');
        if (reloadSelector && $.fn.DataTable && $(reloadSelector).length) {
            $(reloadSelector).DataTable().ajax.reload(null, false);
        }
        // Show success toast
        Swal.fire({
            title: 'Success!',
            text: response.message || 'Operation completed successfully.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
        });
    } else {
        Swal.fire({
            title: 'Error!',
            text: response.message || 'An error occurred',
            icon: 'error',
            confirmButtonColor: '#dc3545'
        });
    }
} 