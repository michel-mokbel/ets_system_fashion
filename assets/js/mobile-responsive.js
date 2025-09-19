/**
 * Responsive behaviour orchestrator.
 *
 * Responsibilities:
 * - Detect viewport breakpoints/orientation changes and toggle between desktop tables and mobile-friendly card renderings.
 * - Inject touch optimizations (larger hit targets, swipe helpers), step navigation, and collapsible sections for constrained screens.
 * - Provide helper routines that other modules call to keep DataTables and card views synchronized during redraws.
 *
 * Dependencies:
 * - jQuery for DOM traversal/manipulation and DataTables instances passed in from other scripts.
 * - Bootstrap classes for responsive styling; no backend interaction.
 */

class MobileResponsive {
    constructor() {
        this.isMobile = window.innerWidth <= 767;
        this.isTablet = window.innerWidth > 767 && window.innerWidth <= 991;
        this.isLandscape = window.innerHeight < window.innerWidth;
        this.init();
    }

    init() {
        console.log('Initializing Mobile Responsive System...');
        this.handleResize();
        this.initMobileTableCards();
        this.initTouchOptimizations();
        this.initMobileModals();
        this.initMobileSearch();
        this.initMobileNavigation();
        this.initDataTableResponsive();
        
        // Listen for window resize
        window.addEventListener('resize', () => this.handleResize());
        
        // Listen for orientation change
        window.addEventListener('orientationchange', () => {
            setTimeout(() => this.handleResize(), 100);
        });
    }

    handleResize() {
        const newIsMobile = window.innerWidth <= 767;
        const newIsTablet = window.innerWidth > 767 && window.innerWidth <= 991;
        const newIsLandscape = window.innerHeight < window.innerWidth;
        
        if (newIsMobile !== this.isMobile || newIsTablet !== this.isTablet || newIsLandscape !== this.isLandscape) {
            this.isMobile = newIsMobile;
            this.isTablet = newIsTablet;
            this.isLandscape = newIsLandscape;
            this.updateLayout();
        }
    }

    updateLayout() {
        if (this.isMobile) {
            this.enableMobileLayout();
        } else {
            this.enableDesktopLayout();
        }
    }

    enableMobileLayout() {
        console.log('Enabling mobile layout...');
        
        // Convert tables to mobile card layout (skip DataTables-managed tables)
        $('.table-responsive').each((index, table) => {
            this.convertTableToCards($(table));
        });

        // Add mobile-specific classes
        $('body').addClass('mobile-layout');
        
        // Initialize mobile-specific features
        this.initMobileStepNavigation();
        this.initMobileCollapsibleSections();
    }

    enableDesktopLayout() {
        console.log('Enabling desktop layout...');
        
        // Hide mobile card containers and show tables
        $('.mobile-card-container').hide();
        $('.table-responsive').show();
        
        // Remove mobile-specific classes
        $('body').removeClass('mobile-layout');
    }

    initMobileTableCards() {
        // Mark tables for mobile optimization
        $('.table-responsive').addClass('mobile-table-target');
    }

    convertTableToCards(tableContainer) {
        if (tableContainer.next('.mobile-card-container').length) {
            // Already converted, just show/hide appropriately
            if (this.isMobile) {
                tableContainer.hide();
                tableContainer.next('.mobile-card-container').show();
            } else {
                tableContainer.show();
                tableContainer.next('.mobile-card-container').hide();
            }
            return;
        }

        const table = tableContainer.find('table');
        if (table.length === 0) return;

        // Skip if this table is managed by DataTables (we rely on DataTables responsive instead)
        try {
            if (typeof $.fn.dataTable !== 'undefined') {
                if ($.fn.dataTable.isDataTable(table[0])) return;
            }
        } catch (_) {}
        if (table.hasClass('dataTable') || table.closest('.dataTables_wrapper').length) return;

        const headers = [];
        
        // Extract headers
        table.find('thead th').each((index, th) => {
            headers.push($(th).text().trim());
        });

        const cardContainer = $('<div class="mobile-card-container"></div>');
        
        // Convert each row to a card
        table.find('tbody tr').each((rowIndex, row) => {
            const $row = $(row);
            const cells = $row.find('td');
            
            if (cells.length === 0) return;

            const card = $('<div class="mobile-table-card"></div>');
            
            // Create card header from first cell (usually item name/identifier)
            const headerCell = $(cells[0]);
            let headerText = headerCell.text().trim();
            
            // If first cell has multiple lines, use the first line as header
            const firstLine = headerText.split('\n')[0];
            if (firstLine.length > 0) {
                headerText = firstLine;
            }
            
            card.append(`<div class="mobile-card-header">${this.escapeHtml(headerText)}</div>`);
            
            // Create rows for all cells (including first one with full details)
            cells.each((cellIndex, cell) => {
                const $cell = $(cell);
                const label = headers[cellIndex] || `Field ${cellIndex + 1}`;
                let value = $cell.html();
                
                // Skip empty cells
                if (!value || value.trim() === '') return;
                
                // Special handling for action buttons
                if ($cell.find('.btn').length > 0) {
                    const actions = $('<div class="mobile-card-actions"></div>');
                    $cell.find('.btn').each((btnIndex, btn) => {
                        actions.append($(btn).clone());
                    });
                    card.append(actions);
                    return;
                }
                
                // Regular data row
                card.append(`
                    <div class="mobile-card-row">
                        <span class="mobile-card-label">${this.escapeHtml(label)}</span>
                        <span class="mobile-card-value">${value}</span>
                    </div>
                `);
            });

            cardContainer.append(card);
        });

        tableContainer.after(cardContainer);
        
        if (this.isMobile) {
            tableContainer.hide();
        } else {
            cardContainer.hide();
        }
    }

    initTouchOptimizations() {
        // Add touch feedback for buttons and clickable elements
        $(document).on('touchstart', '.btn, .clickable, .mobile-table-card', function(e) {
            $(this).addClass('touching');
        });

        $(document).on('touchend touchcancel', '.btn, .clickable, .mobile-table-card', function(e) {
            $(this).removeClass('touching');
        });

        // Prevent double-tap zoom on form elements and buttons
        $(document).on('touchend', '.btn, .form-control, .form-select', function(e) {
            // Small delay to prevent double-tap zoom
            setTimeout(() => {}, 300);
        });

        // Improve scrolling on mobile
        if (this.isMobile) {
            $('body').css({
                '-webkit-overflow-scrolling': 'touch',
                'overflow-scrolling': 'touch'
            });
        }
    }

    initMobileModals() {
        if (!this.isMobile) return;

        // Enhance modal behavior for mobile
        $('.modal').on('show.bs.modal', function() {
            const $modal = $(this);
            const $dialog = $modal.find('.modal-dialog');
            const $content = $modal.find('.modal-content');
            
            // Add mobile-specific classes
            $dialog.addClass('mobile-modal-responsive');
            $content.addClass('mobile-modal-content');
            
            // Prevent body scrolling when modal is open
            $('body').addClass('modal-open-mobile');
            
            // Set viewport meta to prevent zoom on input focus
            const viewport = $('meta[name=viewport]');
            if (viewport.length) {
                viewport.data('original-content', viewport.attr('content'));
                viewport.attr('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no');
            }
            
            // Focus management for accessibility
            setTimeout(() => {
                const firstFocusable = $modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').first();
                if (firstFocusable.length) {
                    firstFocusable.focus();
                }
            }, 150);
        });

        $('.modal').on('shown.bs.modal', function() {
            const $modal = $(this);
            
            // Ensure modal body scrolls to top
            const $modalBody = $modal.find('.modal-body');
            if ($modalBody.length) {
                $modalBody.scrollTop(0);
            }
        });

        $('.modal').on('hidden.bs.modal', function() {
            const $modal = $(this);
            
            // Remove mobile-specific classes
            $('body').removeClass('modal-open-mobile');
            $modal.find('.modal-dialog').removeClass('mobile-modal-responsive');
            $modal.find('.modal-content').removeClass('mobile-modal-content');
            
            // Restore original viewport settings
            const viewport = $('meta[name=viewport]');
            if (viewport.length && viewport.data('original-content')) {
                viewport.attr('content', viewport.data('original-content'));
            }
            
            // Reset any transform/opacity from swipe gestures
            const modalContent = $modal.find('.modal-content')[0];
            if (modalContent) {
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
            }
        });

        // Handle modal backdrop clicks on mobile
        $('.modal').on('click', function(e) {
            if (e.target === this) {
                const modal = bootstrap.Modal.getInstance(this);
                if (modal) {
                    modal.hide();
                }
            }
        });
        
        // Enhance form inputs in modals for mobile
        $('.modal').on('shown.bs.modal', function() {
            const $modal = $(this);
            
            // Add mobile-friendly classes to form elements
            $modal.find('.form-control, .form-select').addClass('mobile-form-input');
            
            // Ensure close buttons work properly on mobile
            $modal.find('.btn-close, [data-bs-dismiss="modal"]').on('click', function() {
                const modal = bootstrap.Modal.getInstance($modal[0]);
                if (modal) {
                    modal.hide();
                }
            });
            
            // Handle keyboard appearance on iOS
            $modal.find('input, textarea, select').on('focus', function() {
                setTimeout(() => {
                    $(this)[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 300);
            });
        });
        
        // Add pull-to-refresh indicator
        this.addModalPullIndicator();
    }
    
    addModalPullIndicator() {
        // Add a visual indicator for pull-to-close gesture
        const indicator = $(`
            <div class="modal-pull-indicator">
                <div class="modal-pull-handle"></div>
            </div>
        `);
        
        // Add styles if not already present
        if (!$('#mobile-modal-styles').length) {
            $('<style id="mobile-modal-styles">').html(`
                .modal-pull-indicator {
                    position: absolute;
                    top: 12px;
                    left: 50%;
                    transform: translateX(-50%);
                    z-index: 1000;
                    pointer-events: none;
                    padding: 8px 16px;
                    background: rgba(255,255,255,0.9);
                    border-radius: 12px;
                    backdrop-filter: blur(10px);
                }
                .modal-pull-handle {
                    width: 36px;
                    height: 4px;
                    background: #6c757d;
                    border-radius: 2px;
                    transition: all 0.2s ease;
                }
                .modal-header:active + * .modal-pull-handle,
                .modal-header:hover + * .modal-pull-handle {
                    background: var(--primary-color);
                    width: 48px;
                }
                
                /* Make header area more touch-friendly for swipe gesture */
                .modal-header {
                    cursor: grab;
                    user-select: none;
                    position: relative;
                }
                
                .modal-header:active {
                    cursor: grabbing;
                }
                
                /* Add subtle indication that header is swipeable */
                .modal-header::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, transparent, rgba(108,117,125,0.1), transparent);
                    pointer-events: none;
                }
            `).appendTo('head');
        }
        
        $('.modal').on('show.bs.modal', function() {
            if (window.innerWidth <= 767) {
                $(this).find('.modal-content').prepend(indicator.clone());
            }
        });
    }

    initMobileSearch() {
        if (!this.isMobile) return;

        // Convert search forms to collapsible sections on mobile
        $('.search-form, .filter-form').each((index, form) => {
            const $form = $(form);
            
            // Skip if already converted
            if ($form.hasClass('mobile-converted')) return;
            
            const $formContent = $form.children().detach();
            const collapseId = `mobileSearch${index}`;
            
            const trigger = $(`
                <button type="button" class="mobile-collapse-trigger" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false">
                    <span><i class="bi bi-search me-2"></i>Search & Filter</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
            `);
            
            const content = $(`
                <div class="collapse mobile-collapse-content" id="${collapseId}">
                    <div class="mobile-search-container"></div>
                </div>
            `);
            
            content.find('.mobile-search-container').append($formContent);
            
            $form.addClass('mobile-converted').empty().append(trigger).append(content);
        });
    }

    initMobileNavigation() {
        // Note: Mobile sidebar navigation is now handled by common.js
        // This method is kept for compatibility but disabled to prevent conflicts
        console.log('MobileResponsive: Sidebar navigation handled by common.js');
    }

    initMobileStepNavigation() {
        if (!this.isMobile) return;

        // Add mobile step navigation for multi-step forms
        $('.step-container, .modal-body').each(function() {
            const $container = $(this);
            
            // Look for step navigation buttons
            const $prevBtn = $container.find('[id*="prev"], [id*="back"], [class*="prev"], [class*="back"]').first();
            const $nextBtn = $container.find('[id*="next"], [class*="next"]').first();
            
            if ($prevBtn.length || $nextBtn.length) {
                const $navigation = $('<div class="step-navigation mobile-only"></div>');
                
                if ($prevBtn.length) {
                    const $mobilePrev = $prevBtn.clone().addClass('mobile-step-btn');
                    $navigation.append($mobilePrev);
                }
                
                if ($nextBtn.length) {
                    const $mobileNext = $nextBtn.clone().addClass('mobile-step-btn');
                    $navigation.append($mobileNext);
                }
                
                $container.append($navigation);
            }
        });
    }

    initMobileCollapsibleSections() {
        if (!this.isMobile) return;

        // Make large content sections collapsible on mobile
        $('.card-body, .table-responsive').each(function() {
            const $section = $(this);
            
            // Skip small sections
            if ($section.height() < 300) return;
            
            // Skip if already has collapse functionality
            if ($section.closest('.collapse').length) return;
            
            const $parent = $section.parent();
            const sectionTitle = $parent.find('.card-header h5, .card-header h6, h5, h6').first().text() || 'Section';
            
            if (sectionTitle && sectionTitle !== 'Section') {
                const collapseId = `mobileCollapse${Math.random().toString(36).substr(2, 9)}`;
                
                const $trigger = $(`
                    <button class="mobile-collapse-trigger mobile-only" data-bs-toggle="collapse" data-bs-target="#${collapseId}">
                        <span>${this.escapeHtml(sectionTitle)}</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                `);
                
                $section.addClass('collapse').attr('id', collapseId);
                $section.before($trigger);
            }
        });
    }

    initDataTableResponsive() {
        // Enhance DataTables for mobile if present
        if (typeof $.fn.DataTable !== 'undefined') {
            // Set responsive options for existing DataTables
            $('.dataTable').each(function() {
                const table = $(this).DataTable();
                if (table && table.responsive) {
                    table.responsive.recalc();
                }
            });

            // Default mobile settings for new DataTables
            $.extend(true, $.fn.dataTable.defaults, {
                responsive: {
                    details: {
                        type: this.isMobile ? 'column' : 'inline',
                        target: 'tr'
                    }
                },
                pageLength: this.isMobile ? 5 : 10,
                lengthMenu: this.isMobile ? [5, 10, 25] : [10, 25, 50, 100],
                dom: this.isMobile ? 'frtip' : 'Bfrtip'
            });
        }
    }

    // Utility function to escape HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Public method to refresh responsive layout
    refresh() {
        this.handleResize();
    }

    // Public method to convert specific table to cards
    convertTable(tableSelector) {
        const $table = $(tableSelector);
        if ($table.length) {
            this.convertTableToCards($table);
        }
    }

    // Public method to check if mobile
    isMobileDevice() {
        return this.isMobile;
    }

    // Public method to check if tablet
    isTabletDevice() {
        return this.isTablet;
    }
}

// Initialize when DOM is ready
$(document).ready(() => {
    // Create global instance
    window.MobileResponsive = new MobileResponsive();
    
    console.log('Mobile Responsive System initialized');
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MobileResponsive;
} 