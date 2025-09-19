/**
 * Point-of-sale front-end controller.
 * ----------------------------------
 * The POS page is intentionally self-contained because it must keep working
 * even when the rest of the admin UI fails to load. This script orchestrates
 * barcode scanning, cart state management, and checkout submission while
 * degrading gracefully if translations or other optional bundles are missing.
 *
 * Responsibilities:
 * - Manage the item lookup pipeline (barcode scans, manual search, custom items) and enforce stock availability rules.
 * - Maintain reactive cart state (quantities, discounts, totals) with change propagation to payment inputs and receipt preview.
 * - Execute critical workflows such as sales submission, returns, expense logging, and shift report generation via AJAX.
 *
 * Dependencies:
 * - jQuery for DOM orchestration, SweetAlert/Bootstrap modals for dialogs, and the thermal receipt print helper defined later in the file.
 * - Backend endpoints: `../ajax/get_item_by_barcode.php`, `../ajax/search_items.php`, `../ajax/process_sale.php`, `../ajax/process_return.php`, `../ajax/process_expense.php`, and `../ajax/generate_shift_report.php`.
 */

// Simple translation function - fallback implementation
/**
 * Look up a translated string for a given key.
 *
 * The real translation bundle is loaded late in the page lifecycle, so the
 * POS shell ships with this minimal helper to keep the UI responsive while
 * assets are bootstrapping.
 *
 * @param {string} key - Translation key.
 * @param {Object<string, string>} [params] - Optional placeholder replacements.
 * @returns {string}
 */
function t(key) {
    const translations = {
        'out_of_stock': 'Out of Stock!',
        'cannot_add_more_items': 'Cannot add more items. Available stock: {stock}, Already in cart: {cart}',
        'item_added': 'Item Added!',
        'added_to_cart': 'added to cart',
        'item_not_found': 'Item Not Found!',
        'no_item_found_barcode': 'No item found with this barcode',
        'scan_error': 'Scan Error!',
        'error_scanning_barcode': 'Error scanning barcode. Please try again.',
        'stock_warning': 'Stock Warning!',
        'items_exceed_stock': 'One or more items exceed available stock. Please adjust quantities before checkout.'
    };
    
    let translation = translations[key] || key;
    
    // Handle simple placeholder replacement
    if (arguments.length > 1) {
        const params = arguments[1];
        if (typeof params === 'object') {
            for (let param in params) {
                translation = translation.replace('{' + param + '}', params[param]);
            }
        }
    }
    
    return translation;
}

$(document).ready(function () {
    // Close sidebar on POS page load for more space
	if ($("#sidebar").length) {
		$("#sidebar").addClass("collapsed");
		$("#wrapper").addClass("sidebar-collapsed");
        
        // Update stored state in localStorage
		localStorage.setItem("sidebarCollapsed", "true");
        
        // Update toggle button icon and position
        updateToggleIcon(true);
        updateTogglePosition(true);
    }
    
    let cart = [];
	const csrfToken = $("#csrf_token").val();
    
    // Smart focus management for barcode scanning
    /**
     * Return focus to the barcode input.
     *
     * The slight timeout gives Bootstrap modals and toasts enough time to
     * release focus before the scanner starts pushing key presses again.
     */
    function focusBarcodeInput() {
        setTimeout(() => {
                        $("#barcodeInput").focus();
            updateBarcodeIndicator(true);
        }, 100);
    }

    /**
     * Visually indicate whether the scanner input is ready.
     *
     * @param {boolean} isActive
     */
    function updateBarcodeIndicator(isActive) {
                const input = $("#barcodeInput");

        if (isActive) {
                        input.addClass("barcode-ready");
        } else {
			input.removeClass("barcode-ready");
        }
    }
    
    // Initial focus on page load
    focusBarcodeInput();

    // Add item by barcode
    /**
     * Resolve a scanned barcode and add the item to the cart.
     *
     * This function encapsulates the AJAX call so we can reuse it when the
     * operator enters a barcode manually or when a handheld scanner emits an
     * `Enter` key event.
     */
    function addItemByBarcode() {
                const barcode = $("#barcodeInput").val().trim();
        if (!barcode) return;
        $.ajax({
			url: "../ajax/get_item_by_barcode.php",
			type: "POST",
            data: { barcode, csrf_token: csrfToken },
			dataType: "json",
			success: function (response) {
                if (response.success) {
                    // Check stock availability before adding
                    // Standardize item properties
                    const item = {
                        item_id: response.data.id,
                        barcode_id: response.data.barcode_id || response.data.id, // Fallback for items without barcode
                        name: response.data.name,
                        item_code: response.data.item_code,
                        selling_price: response.data.selling_price,
                        current_stock: response.data.current_stock,
						is_non_inventory: false,
                    };
                    
                    // Debug log
					console.log("Barcode scan item:", item);

					const existingCartItem = cart.find(
						(i) => i.item_id == item.item_id && !i.is_non_inventory
					);
					const currentCartQty = existingCartItem
						? existingCartItem.quantity
						: 0;
                    const availableStock = parseInt(item.current_stock || 0);
                    
                    if (currentCartQty >= availableStock) {
                        showToast(
							t("out_of_stock"),
							t("cannot_add_more_items", {stock: availableStock, cart: currentCartQty}),
							"warning",
                            5000
                        );
                        focusBarcodeInput();
                        return;
                    }
                    
                    addToCart(item);
					$("#barcodeInput").val("");
                    focusBarcodeInput();
					$("#posMessage").html("");
                    
                    // Show success toast
                    showToast(
						t("item_added"),
						`${response.data.name} ${t("added_to_cart")}`,
						"success",
                        2000,
                        50
                    );
                } else {
                    showToast(
						t("item_not_found"),
						response.message || t("no_item_found_barcode"),
						"error",
                        4000
                    );
                    focusBarcodeInput(); // Re-focus even on error
                }
            },
			error: function () {
                showToast(
					t("scan_error"),
					t("error_scanning_barcode"),
					"error",
                    2000
                );
			},
        });
    }

    // Add item to cart or increment quantity
    /**
     * Merge the given item into the in-memory cart representation.
     *
     * @param {Object} item - Object returned from the lookup endpoint.
     * @param {number} [customQuantity=1] - Quantity to apply when adding.
     */
         function addToCart(item, customQuantity = 1) {
         // Handle custom items (they have negative IDs)
         const isCustomItem = item.item_id < 0;
         
         if (isCustomItem) {
             // For custom items, always add as new entry (no quantity increment)
             cart.push({
                 item_id: item.item_id,
                 barcode_id: item.barcode_id,
                 name: item.name,
                 item_code: item.item_code,
				unit_price: parseFloat(
					item.unit_price || item.selling_price || item.base_price
				),
                 quantity: customQuantity,
				total_price:
					parseFloat(item.unit_price || item.selling_price || item.base_price) *
					customQuantity,
                 discount_amount: 0,
                 stock: 999999, // Custom items have unlimited "stock"
				is_non_inventory: true,
             });
         } else {
             // For regular items, check if already in cart and increment
             // Only check item_id for regular inventory items, as barcode_id might be inconsistent
			const idx = cart.findIndex(
				(i) => i.item_id == item.item_id && !i.is_non_inventory
			);
        if (idx >= 0) {
                 cart[idx].quantity += customQuantity;
                 cart[idx].total_price = cart[idx].unit_price * cart[idx].quantity;
        } else {
            cart.push({
                     item_id: item.item_id,
                     barcode_id: item.barcode_id,
                name: item.name,
                item_code: item.item_code,
                     unit_price: parseFloat(item.selling_price),
                     quantity: customQuantity,
                     total_price: parseFloat(item.selling_price) * customQuantity,
                discount_amount: 0,
                     stock: parseInt(item.current_stock || 0),
					is_non_inventory: false,
            });
             }
        }
        renderCart();
    }

    // Render cart table
    /**
     * Re-render the cart table and summary totals.
     *
     * This keeps the DOM updates encapsulated in a single place so that any
     * future re-theming or responsive tweaks only need to touch this block.
     */
    function renderCart() {
                let html = "";
        let stockWarning = false;
        cart.forEach((item, idx) => {
            const isCustomItem = item.is_non_inventory || false;
			const overStock = !isCustomItem && item.quantity > item.stock;
            if (overStock) stockWarning = true;
            
            const maxQty = isCustomItem ? 999999 : item.stock;
			const stockBadge = isCustomItem
				? '<span class="badge bg-info">Custom</span>'
				: "";
            const isPriceOverridden = item.is_price_overridden || false;
			const priceOverrideBadge = isPriceOverridden
				? '<span class="badge bg-warning text-dark ms-1">Override</span>'
				: "";
			const rowClass = isCustomItem
				? "table-info"
				: isPriceOverridden
				? "table-warning"
				: "";

			html += `<tr ${rowClass ? `class="${rowClass}"` : ""}>
                <td>${item.name} ${stockBadge}</td>
                <td>${item.item_code}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <span class="me-2">CFA ${item.unit_price.toFixed(
													2
												)}${priceOverrideBadge}</span>
                        <button class="btn btn-warning btn-sm price-edit-btn me-1" data-idx="${idx}" title="Edit Price (Manager Authorization Required)">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button class="btn btn-info btn-sm discount-btn" data-idx="${idx}" title="Add Discount">
                            <i class="bi bi-percent"></i>
                        </button>
                    </div>
                </td>
                <td><input type="number" class="form-control form-control-sm qty-input" data-idx="${idx}" value="${
				item.quantity
			}" min="1" max="${maxQty}"></td>
                <td>
                    <div class="d-flex flex-column">
                        <span>CFA ${(item.unit_price * item.quantity).toFixed(
													2
												)}</span>
                        ${
													item.discount_amount > 0
														? `<small class="text-success">-${
																item.discount_percentage
														  }% (CFA -${item.discount_amount.toFixed(
																2
														  )})</small>`
														: ""
												}
                    </div>
                </td>
                <td><button class="btn btn-danger btn-sm remove-item-btn" data-idx="${idx}"><i class="bi bi-trash"></i></button></td>
            </tr>`;
        });
		$("#cartTable thead tr").html(`
            <th>Nom de l Article</th>
            <th>Code</th>
            <th>Unit Price (CFA)</th>
            <th>Qty</th>
            <th>Total (CFA)</th>
            <th>Remove</th>
        `);
		$("#cartTable tbody").html(html);
        recalculateTotals();
        if (stockWarning) {
            showToast(
				t("stock_warning"),
				t("items_exceed_stock"),
				"warning",
                2000
            );
			$("#checkoutBtn").prop("disabled", true);
        } else {
			$("#checkoutBtn").prop("disabled", false);
        }
                 // Re-render inventory table with updated available stock
         updateInventoryTableStock();
    }

    // Remove item
	$(document).on("click", ".remove-item-btn", function () {
		const idx = $(this).data("idx");
        cart.splice(idx, 1);
        renderCart();
    });

    // Update quantity
	$(document).on("input", ".qty-input", function () {
		const idx = $(this).data("idx");
        const item = cart[idx];
        let qty = parseInt($(this).val());
        
        if (isNaN(qty) || qty < 1) {
            qty = 1;
        }
        
        // Check stock limit for non-custom items
        if (!item.is_non_inventory && qty > item.stock) {
            showToast(
				"Stock Limit Exceeded!",
                `Maximum available quantity: ${item.stock}. Quantity adjusted automatically.`,
				"warning",
                3000
            );
            qty = item.stock;
            $(this).val(qty); // Update the input field
        }
        
        cart[idx].quantity = qty;
        renderCart();
    });

    // Handle price edit button click
	$(document).on("click", ".price-edit-btn", function () {
		const idx = $(this).data("idx");
        const item = cart[idx];
        if (item) {
            showPriceOverrideModal(idx, item);
        }
    });

    // Add item by barcode (button or enter)
	$("#addBarcodeBtn").on("click", addItemByBarcode);
	$("#barcodeInput").on("keypress", function (e) {
        if (e.which === 13) {
            addItemByBarcode();
            e.preventDefault();
        }
    });
    
    // Focus and blur handlers for barcode input
	$("#barcodeInput")
		.on("focus", function () {
        updateBarcodeIndicator(true);
		})
		.on("blur", function () {
        // Only remove indicator if no other POS-related input is focused
        setTimeout(() => {
            const focusedElement = document.activeElement;
				const isPOSInput =
					$(focusedElement).closest(".card-body").length > 0 &&
					!$(focusedElement).is("#barcodeInput");
            
            if (!isPOSInput) {
                updateBarcodeIndicator(false);
            }
        }, 100);
    });
    
    // Smart re-focus: Return to barcode input when other inputs lose focus
    // (unless user is actively typing in another input)
    let lastInputTime = 0;
	$(document).on("input", "input:not(#barcodeInput)", function () {
        lastInputTime = Date.now();
    });
    
	$(document).on("blur", "input:not(#barcodeInput)", function () {
        setTimeout(() => {
            const timeSinceLastInput = Date.now() - lastInputTime;
            const focusedElement = document.activeElement;
            
            // If no input is focused and user hasn't typed recently, return to barcode
			if (
				!$(focusedElement).is("input, select, textarea") &&
				timeSinceLastInput > 1000
			) {
                focusBarcodeInput();
            }
        }, 200);
    });
    
    // ESC key to quickly return focus to barcode input
	$(document).on("keydown", function (e) {
		if (e.which === 27 && !$(e.target).is("#barcodeInput")) {
			// ESC key
            e.preventDefault();
            focusBarcodeInput();
        }
    });

    // Simple search implementation without autocomplete
    let searchTimeout;
    let searchResults = [];
    
	$("#itemSearchInput").on("input", function () {
        const term = $(this).val().trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide any existing results
        hideSearchResults();
        
        if (term.length < 2) {
            return;
        }
        
        // Debounce search requests
        searchTimeout = setTimeout(() => {
            performSearch(term);
        }, 300);
    });
    
    function performSearch(term) {
		console.log("Search request:", term);
        $.ajax({
			url: "../ajax/search_items.php",
			type: "POST",
			dataType: "json",
            data: {
                term: term,
				csrf_token: csrfToken,
            },
			success: function (data) {
				console.log("Search response:", data);
                if (data.success && data.items.length > 0) {
                    showSearchResults(data.items);
                } else {
					console.log("No items found or search failed:", data.message);
                    hideSearchResults();
                }
            },
			error: function (xhr, status, error) {
				console.error("Search AJAX error:", error, xhr.responseText);
                hideSearchResults();
			},
        });
    }
    
    function showSearchResults(items) {
        searchResults = items;
        let html = '<div class="search-results-dropdown" id="searchResults">';
        items.forEach((item, index) => {
            html += `<div class="search-result-item" data-index="${index}">
                        <strong>${item.name}</strong> (${item.item_code})
                        <small class="text-muted d-block">Stock: ${
													item.current_stock
												} | Price: CFA ${parseFloat(
				item.selling_price || 0
			).toFixed(2)}</small>
                     </div>`;
        });
		html += "</div>";
        
        // Remove any existing dropdown
		$("#searchResults").remove();
        
        // Add new dropdown
		$("#itemSearchInput").parent().css("position", "relative").append(html);
    }
    
    function hideSearchResults() {
		$("#searchResults").remove();
        searchResults = [];
    }
    
    // Handle clicking on search results
	$(document).on("click", ".search-result-item", function () {
		const index = $(this).data("index");
        const item = searchResults[index];
        if (item) {
            addToCart(item);
			$("#itemSearchInput").val("");
            hideSearchResults();
        }
    });
    
    // Hide results when clicking outside
	$(document).on("click", function (e) {
		if (!$(e.target).closest("#itemSearchInput, #searchResults").length) {
            hideSearchResults();
        }
    });
    
    // Hide results when pressing escape
	$("#itemSearchInput").on("keydown", function (e) {
		if (e.which === 27) {
			// Escape key
            hideSearchResults();
        }
    });

    // Recalculate totals
    function recalculateTotals() {
        let subtotal = 0;
        let itemDiscounts = 0;
        
        // Calculate subtotal and sum up all item discounts
		cart.forEach((item) => {
            const itemTotal = item.unit_price * item.quantity;
            subtotal += itemTotal;
            itemDiscounts += parseFloat(item.discount_amount || 0);
        });

        // Update the discount field with total item discounts
		$("#discount").val(`CFA ${itemDiscounts.toFixed(2)}`);
        
        // Calculate final total (tax removed for now)
        let total = subtotal - itemDiscounts;
		$("#subtotal").val(`CFA ${subtotal.toFixed(2)}`);
		$("#total").val(`CFA ${total.toFixed(2)}`);
    }

    // Handle payment method change - Enhanced version with debugging
    function handlePaymentMethodChange() {
        const paymentMethod = $("#paymentMethod").val();
        const paymentFields = $("#paymentAmountFields");
        const cashField = $("#cashAmountField");
        const mobileField = $("#mobileAmountField");
        
        console.log('Payment method changed to:', paymentMethod);
        
        // Show/hide payment amount fields based on method
        if (paymentMethod === "cash" || paymentMethod === "mobile" || paymentMethod === "cash_mobile") {
            paymentFields.css('display', 'block');
            console.log('Showing payment fields');
            
            // Show/hide specific fields
            if (paymentMethod === "cash") {
                cashField.css('display', 'none');
                mobileField.css('display', 'none');
                console.log('Hiding cash and mobile fields for cash payment');
            } else if (paymentMethod === "mobile") {
                cashField.css('display', 'none');
                mobileField.css('display', 'none');
                console.log('Hiding cash and mobile fields for mobile payment');
            } else if (paymentMethod === "cash_mobile") {
                cashField.css('display', 'block');
                mobileField.css('display', 'block');
                console.log('Showing cash and mobile fields for cash_mobile payment');
            }
        } else {
            paymentFields.css('display', 'none');
            console.log('Hiding payment fields for other payment methods');
        }
        
        // Clear amounts when switching methods
        $("#amountPaid").val("");
        $("#cashAmount").val("");
        $("#mobileAmount").val("");
        $("#changeDue").val("");
    }
    
    // Attach event handler with multiple methods for reliability
    $("#paymentMethod").on("change", handlePaymentMethodChange);
    
    // Backup event handler using direct DOM event
    document.addEventListener('DOMContentLoaded', function() {
        const paymentSelect = document.getElementById('paymentMethod');
        if (paymentSelect) {
            paymentSelect.addEventListener('change', handlePaymentMethodChange);
        }
    });
    
    // Additional backup using jQuery ready
    $(document).ready(function() {
        $("#paymentMethod").off('change').on("change", handlePaymentMethodChange);
        
        // Make function globally available for manual testing
        window.testPaymentMethod = function(method) {
            console.log('Manually testing payment method:', method);
            $("#paymentMethod").val(method);
            handlePaymentMethodChange();
        };
        
        // Auto-trigger on page load to test
        setTimeout(function() {
            console.log('Auto-testing payment method change...');
            handlePaymentMethodChange();
        }, 1000);
    });

    // Calculate change when amount paid changes
    $("#amountPaid").on("input", function() {
        calculateChange();
    });

    // Calculate change when cash/mobile amounts change (for cash_mobile method)
    $("#cashAmount, #mobileAmount").on("input", function() {
        const cashAmount = parseFloat($("#cashAmount").val()) || 0;
        const mobileAmount = parseFloat($("#mobileAmount").val()) || 0;
        const totalPaid = cashAmount + mobileAmount;
        $("#amountPaid").val(totalPaid.toFixed(2));
        calculateChange();
    });

    // Function to calculate change
    function calculateChange() {
        const total = parseFloat($("#total").val().replace("CFA ", "")) || 0;
        const amountPaid = parseFloat($("#amountPaid").val()) || 0;
        const change = amountPaid - total;
        
        if (change >= 0) {
            $("#changeDue").val(`CFA ${change.toFixed(2)}`);
            $("#changeDue").css("color", "green");
        } else {
            $("#changeDue").val(`CFA ${Math.abs(change).toFixed(2)} (Short)`);
            $("#changeDue").css("color", "red");
        }
    }

    // Prevent Enter key from triggering checkout in any input in the POS area
	$(
		"#cartTable, #subtotal, #tax, #discount, #total, #customerName, #customerPhone, #paymentMethod, #amountPaid, #cashAmount, #mobileAmount"
	).on("keypress keydown", "input, select", function (e) {
        if (e.which === 13) {
            e.preventDefault();
            return false;
        }
    });

    // Checkout
    let checkoutInProgress = false;
	$("#checkoutBtn").on("click", function () {
        if (checkoutInProgress) return;
        if (cart.length === 0) {
			showMessage("Cart is empty.", "danger");
            return;
        }
        checkoutInProgress = true;
		$("#checkoutBtn").prop("disabled", true);
		const customer_name = $("#customerName").val();
		const customer_phone = $("#customerPhone").val();
		const payment_method = $("#paymentMethod").val();
        // Parse values removing CFA prefix if present
        // Parse values removing CFA prefix if present
		const subtotal = parseFloat($("#subtotal").val().replace("CFA ", "")) || 0;
		const discount = parseFloat($("#discount").val().replace("CFA ", "")) || 0; // This is now the total of item discounts
		const total = parseFloat($("#total").val().replace("CFA ", "")) || 0;
        const tax = 0; // Tax removed for now
        
        // Get payment amounts
        const amountPaid = parseFloat($("#amountPaid").val()) || 0;
        const cashAmount = parseFloat($("#cashAmount").val()) || 0;
        const mobileAmount = parseFloat($("#mobileAmount").val()) || 0;
        
        // Debug: Log the data being sent
		console.log("Sale Data:", {
            cart: cart,
            cart_length: cart.length,
            subtotal: subtotal,
            tax: tax,
            discount: discount,
			total: total,
        });
        
        // Prepare cart data for API (convert to format expected by backend)
		const cartForAPI = cart.map((item) => ({
            item_id: item.item_id,
            barcode_id: item.barcode_id,
            item_name: item.name,
            item_code: item.item_code,
            quantity: item.quantity,
            unit_price: item.unit_price,
            total_price: item.unit_price * item.quantity,
            discount_amount: item.discount_amount || 0,
			is_non_inventory: item.is_non_inventory || false,
        }));
        
        $.ajax({
			url: "../ajax/process_sale.php",
			type: "POST",
            data: {
                csrf_token: csrfToken,
                cart: JSON.stringify(cartForAPI),
                customer_name,
                customer_phone,
                payment_method,
                subtotal,
                tax,
                discount,
				total,
                amount_paid: amountPaid,
                cash_amount: cashAmount,
                mobile_amount: mobileAmount,
            },
			dataType: "json",
			success: function (response) {
                checkoutInProgress = false;
				$("#checkoutBtn").prop("disabled", false);
                if (response.success) {
                                    showReceipt(response.invoice_id, null, null, true); // Pass true for autoPrint
                cart = [];
                renderCart();
					$("#customerName, #customerPhone").val("");
					$("#paymentMethod").val("cash");
					
					// Reset payment details fields
					$("#amountPaid").val("");
					$("#cashAmount").val("");
					$("#mobileAmount").val("");
					$("#changeDue").val("");
					$("#paymentAmountFields").hide();
					
					showMessage("Vente complétée avec succès.", "success");
                loadInventory(); // Refresh inventory table after sale
                
                // Return focus to barcode input after successful transaction
                setTimeout(() => {
                    focusBarcodeInput();
                }, 500);
                } else {
					showMessage(response.message, "danger");
                }
            },
			error: function () {
                checkoutInProgress = false;
				$("#checkoutBtn").prop("disabled", false);
				showMessage("Error processing sale.", "danger");
			},
        });
    });

    // Show receipt modal
    function showReceipt(invoice_id) {
        // Fetch invoice details and render a professional receipt
        $.ajax({
			url: "../ajax/get_invoice.php",
			type: "POST",
            data: { invoice_id: invoice_id, csrf_token: csrfToken },
			dataType: "json",
			success: function (response) {
                if (response.success && response.data) {
                    const inv = response.data;
					let itemsHtml = "";
                    const autoPrint = arguments[3] === true; // Optional parameter to trigger auto-print
					inv.items.forEach(function (item, idx) {
                        const unitPrice = parseFloat(item.unit_price);
                        const quantity = parseInt(item.quantity);
                        const discount = parseFloat(item.discount_amount || 0);
						const discountPercentage = parseFloat(
							item.discount_percentage || 0
						);
                        const totalBeforeDiscount = unitPrice * quantity;
                        const netTotal = totalBeforeDiscount - discount;

                        itemsHtml += `<tr>
                            <td style="text-align:center">${idx + 1}</td>
                            <td>
                                <div style="font-weight:bold">${item.name}</div>
                                <div style="color:#666;font-size:11px">${
																	item.item_code
																}</div>
                            </td>
                            <td style="text-align:right">${quantity}</td>
                            <td style="text-align:right">CFA ${unitPrice.toFixed(
															2
														)}</td>
                            <td style="text-align:right;font-weight:bold">CFA ${netTotal.toFixed(
															2
														)}</td>
                        </tr>`;
                    });
                    const receiptHtml = `
                        <div style="max-width:400px;margin:auto;font-family:monospace;">
                            <div style="text-align:center;">
                                <h4 style="margin-bottom:0.5em;">${
																	inv.store_name || "Magasin"
																}</h4>
                                <div style="font-size:13px;">ID Magasin: ${
																	inv.store_id || ""
																}</div>
                                <div style="font-size:13px;">Vendeur: ${
																	inv.sales_person_name || ""
																} (${inv.sales_person_id || ""})</div>
                                <div style="font-size:13px;">Date: ${
																	inv.created_at
																		? inv.created_at.replace(" ", " &nbsp; ")
																		: ""
																}</div>
                            </div>
                            <hr style="margin:0.5em 0;">
                            <div style="font-size:13px;">Facture N°: <b>${
															inv.invoice_number
														}</b> &nbsp; (ID: ${inv.invoice_id})</div>
                            <table style="width:100%;font-size:13px;margin-top:0.5em;margin-bottom:0.5em;" border="1" cellspacing="0" cellpadding="2">
                                <thead>
                                    <tr style="background:#f8f9fa;">
                                        <th style="text-align:center">#</th>
                                        <th>Article</th>
                                        <th style="text-align:right">Qté</th>
                                        <th style="text-align:right">Prix Unit.</th>
                                        <th style="text-align:right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>${itemsHtml}</tbody>
                            </table>
                            <div style="text-align:right;font-size:13px;">
                                <div style="margin-bottom:8px;padding-bottom:8px;border-bottom:1px dashed #ccc;">
                                    <div>Sous-total: <b>CFA ${parseFloat(
																			inv.subtotal
																		).toFixed(2)}</b></div>
                                    <div style="color:#28a745">Remises Articles: <b>-CFA ${inv.items
																			.reduce(
																				(sum, item) =>
																					sum +
																					(parseFloat(item.discount_amount) ||
																						0),
																				0
																			)
																			.toFixed(2)}</b></div>
                                </div>
                                <!-- Tax line hidden for now -->
                                <div style="margin-bottom:8px;">
                                    <div>Remise Additionnelle: <b>-CFA ${parseFloat(
																			inv.discount_amount
																		).toFixed(2)}</b></div>
                                </div>
                                <div style="font-size:15px;font-weight:bold;margin-top:8px;padding-top:8px;border-top:2px solid #000;">
                                    Total Net: <b>CFA ${parseFloat(
																			inv.total_amount
																		).toFixed(2)}</b>
                                </div>
                            </div>
                            <hr style="margin:0.5em 0;">
                            <div style="font-size:12px;text-align:center;margin-top:1em;">
                                <div style="margin-bottom:8px;">
                                    <strong>Mode de Paiement:</strong> ${inv.payment_method || 'N/A'}<br>
                                    ${inv.cash_amount && inv.cash_amount > 0 ? `<strong>Espèces:</strong> CFA ${parseFloat(inv.cash_amount).toFixed(2)}<br>` : ''}
                                    ${inv.mobile_amount && inv.mobile_amount > 0 ? `<strong>Mobile:</strong> CFA ${parseFloat(inv.mobile_amount).toFixed(2)}<br>` : ''}
                                    ${inv.amount_paid && inv.amount_paid > 0 ? `<strong>Montant Payé:</strong> CFA ${parseFloat(inv.amount_paid).toFixed(2)}<br>` : ''}
                                    ${inv.change_due && inv.change_due > 0 ? `<strong>Monnaie:</strong> CFA ${parseFloat(inv.change_due).toFixed(2)}<br>` : ''}
                                </div>
                                Merci de votre achat.<br>
                                Les articles vendus ne sont ni échangeables ni remboursables<br>
                                <b>Conditions Générales Applicables</b>
                            </div>
                        </div>
                    `;
					$("#receiptContent").html(receiptHtml);
                    
                    if (autoPrint) {
                        // Automatically print without showing modal
						const iframe = document.createElement("iframe");
						iframe.style.display = "none";
                        document.body.appendChild(iframe);
                        
                        iframe.contentDocument.write(`
                            <html>
                                <head>
                                    <style>
                                        body { margin: 0; padding: 10px; }
                                        @media print {
                                            @page { margin: 0; size: 80mm auto; }
                                            body { width: 72mm; }
                                        }
                                    </style>
                                </head>
                                <body>${receiptHtml}</body>
                            </html>
                        `);
                        
                        iframe.contentDocument.close();
                        
                        setTimeout(() => {
                            iframe.contentWindow.print();
                            setTimeout(() => {
                                document.body.removeChild(iframe);
                            }, 1000);
                        }, 500);
                    } else {
                        // Show modal if not auto-printing
						const modal = new bootstrap.Modal(
							document.getElementById("receiptModal")
						);
                        modal.show();
                    }
                } else {
					$("#receiptContent").html(
						'<div class="text-danger text-center">Failed to load receipt details.</div>'
					);
					const modal = new bootstrap.Modal(
						document.getElementById("receiptModal")
					);
                    modal.show();
                }
            },
			error: function () {
				$("#receiptContent").html(
					'<div class="text-danger text-center">Error loading receipt details.</div>'
				);
				const modal = new bootstrap.Modal(
					document.getElementById("receiptModal")
				);
                modal.show();
			},
        });
    }

    // Print receipt directly without dialog
	$("#printReceiptBtn").on("click", function () {
		const content = document.getElementById("receiptContent").innerHTML;
		const iframe = document.createElement("iframe");
		iframe.style.display = "none";
        document.body.appendChild(iframe);
        
        iframe.contentDocument.write(`
            <html>
                <head>
                    <style>
                        body { margin: 0; padding: 10px; }
                        @media print {
                            @page { margin: 0; size: 80mm auto; }
                            body { width: 72mm; }
                        }
                    </style>
                </head>
                <body>${content}</body>
            </html>
        `);
        
        iframe.contentDocument.close();
        
        // Wait for content to load
        setTimeout(() => {
            iframe.contentWindow.print();
            // Remove the iframe after printing
            setTimeout(() => {
                document.body.removeChild(iframe);
            }, 1000);
        }, 500);
    });

    // Show message (legacy function, kept for compatibility)
    function showMessage(msg, type) {
		$("#posMessage").html(
			'<div class="alert alert-' + type + '">' + msg + "</div>"
		);
    }

    // Show toast notification
	function showToast(title, text, icon = "info", timer = 2000, delay = 0) {
        // Close any existing toasts first
        Swal.close();
        
        // Add small delay to prevent conflicts
        setTimeout(() => {
            return Swal.fire({
                title: title,
                text: text,
                icon: icon,
                timer: timer,
                timerProgressBar: true,
                toast: true,
				position: "top-end",
                                 showConfirmButton: false,
                didOpen: () => {
                    // Prevent auto-close on hover
                    const toast = Swal.getContainer();
                    if (toast) {
						toast.addEventListener("mouseenter", Swal.stopTimer);
						toast.addEventListener("mouseleave", Swal.resumeTimer);
                    }
				},
            });
        }, delay);
    }

    // Store original inventory items globally
    window.originalInventoryItems = [];

    function loadInventory() {
        $.ajax({
			url: "../ajax/get_store_inventory.php",
			type: "POST",
            data: { csrf_token: csrfToken },
			dataType: "json",
			success: function (response) {
                if (response.success) {
                    window.originalInventoryItems = response.items;
                    renderInventoryTable(response.items);
                } else {
					$("#inventoryTable tbody").html(
						'<tr><td colspan="6">No items found</td></tr>'
					);
				}
			},
			error: function () {
				$("#inventoryTable tbody").html(
					'<tr><td colspan="6">Error loading inventory</td></tr>'
				);
			},
        });
    }

    function getReservedQty(item_id) {
        let reserved = 0;
		cart.forEach(function (item) {
            if (item.item_id == item_id && !item.is_non_inventory) {
                reserved += item.quantity;
            }
        });
        return reserved;
    }

    // Update inventory table stock numbers without full re-render
    function updateInventoryTableStock() {
        if (!window.originalInventoryItems) return;

        const items = window.originalInventoryItems;
        items.forEach((item, idx) => {
            const reserved = getReservedQty(item.id);
            const availableStock = Math.max(0, item.current_stock - reserved);
            
            // Update stock display
            const row = $(`#inventoryTable tbody tr:eq(${idx})`);
			row.find("td:eq(3)").text(availableStock); // Stock column
            
            // Update quantity input max and value if needed
			const qtyInput = row.find(".inv-qty-input");
			qtyInput.attr("max", availableStock);
            
            // If current value exceeds new max, adjust it
            if (parseInt(qtyInput.val()) > availableStock) {
                qtyInput.val(Math.max(1, availableStock));
            }
            
            // Disable/enable add button based on stock
			const addBtn = row.find(".add-inv-btn");
            if (availableStock < 1) {
				addBtn.prop("disabled", true).addClass("disabled");
				qtyInput.prop("disabled", true);
            } else {
				addBtn.prop("disabled", false).removeClass("disabled");
				qtyInput.prop("disabled", false);
            }
        });
    }

    function renderInventoryTable(items) {
		let html = "";
		items.forEach(function (item, idx) {
            // Subtract reserved (cart) quantity from current_stock
            let reserved = getReservedQty(item.id);
            let availableStock = Math.max(0, item.current_stock - reserved);
            html += `<tr>
                <td>${item.name}</td>
                <td>${item.item_code}</td>
                <td>${parseFloat(item.selling_price).toFixed(2)}</td>
                <td>${availableStock}</td>
                <td><input type="number" class="form-control form-control-sm inv-qty-input" id="invQty${idx}" value="1" min="1" max="${availableStock}"></td>
                <td><button class="btn btn-success btn-sm add-inv-btn" data-idx="${idx}"><i class="bi bi-plus"></i></button></td>
            </tr>`;
        });
		$("#inventoryTable tbody").html(html);
        // Store items for quick access
		$("#inventoryTable").data("items", items);
    }

    // Add inventory item to cart
	$(document).on("click", ".add-inv-btn", function () {
		const idx = $(this).data("idx");
		const items = $("#inventoryTable").data("items") || [];
        const item = items[idx];
		const qty = parseInt($("#invQty" + idx).val()) || 1;
        
        // Check current cart quantity for this item
		const existingCartItem = cart.find(
			(i) => i.item_id == item.id && !i.is_non_inventory
		);
        const currentCartQty = existingCartItem ? existingCartItem.quantity : 0;
        const availableStock = parseInt(item.current_stock || 0);
        
        if (item && qty > 0) {
            if (currentCartQty + qty > availableStock) {
                showToast(
					"Insufficient Stock!",
                    `Cannot add ${qty} items. Available stock: ${availableStock}, Already in cart: ${currentCartQty}`,
					"warning",
                    2000
                );
                return;
            }
            
            // Standardize item properties
            const standardizedItem = {
                item_id: item.id,
                barcode_id: item.barcode_id || item.id, // Fallback for items without barcode
                name: item.name,
                item_code: item.item_code,
                selling_price: item.selling_price,
                current_stock: item.current_stock,
				is_non_inventory: false,
            };
            
            // Debug log
			console.log("Inventory add item:", standardizedItem);
            
            addToCart(standardizedItem, qty);
            
            // Reset quantity input to 1
			$("#invQty" + idx).val(1);
        }
    });

    // Load inventory on page load
    loadInventory();

    // --- Return Workflow ---
	$("#openReturnModal").on("click", function () {
		$("#returnInvoiceNumber").val("");
		$("#returnInvoiceDetails").hide();
		$("#submitReturnBtn").hide();
		$("#returnItemsTable tbody").empty();
		$("#returnReason").val("");
		$("#returnNotes").val("");
		const modal = new bootstrap.Modal(document.getElementById("returnModal"));
        modal.show();
    });

    // Sales person return button - requires manager password verification
	$("#openReturnModalSales").on("click", function () {
        // Reset password modal
		$("#managerPassword").val("");
		$("#managerPassword").removeClass("is-invalid");
		$("#passwordError").text("");

		const passwordModal = new bootstrap.Modal(
			document.getElementById("managerPasswordModal")
		);
        passwordModal.show();
    });

    // Manager password verification
	$("#verifyManagerPassword").on("click", function () {
		const password = $("#managerPassword").val().trim();
        
        if (!password) {
			$("#managerPassword").addClass("is-invalid");
			$("#passwordError").text("Please enter the manager password");
            return;
        }

        // Show loading state
        const $btn = $(this);
        const originalText = $btn.html();
		$btn
			.prop("disabled", true)
			.html('<i class="bi bi-hourglass-split me-1"></i> Verifying...');

        $.ajax({
			url: "../ajax/verify_manager_password.php",
			type: "POST",
            data: {
                password: password,
				csrf_token: csrfToken,
            },
			dataType: "json",
			success: function (response) {
                if (response.success) {
                    // Password verified - close password modal and open return modal
					$("#managerPasswordModal").modal("hide");
                    
                    // Reset return modal
					$("#returnInvoiceNumber").val("");
					$("#returnInvoiceDetails").hide();
					$("#submitReturnBtn").hide();
					$("#returnItemsTable tbody").empty();
					$("#returnReason").val("");
					$("#returnNotes").val("");
                    
                    // Show success message briefly
                    Swal.fire({
						title: "Authorization Granted",
						text: "Manager password verified. You can now process returns.",
						icon: "success",
                        timer: 1500,
						showConfirmButton: false,
                    }).then(() => {
                        // Open return modal
						const returnModal = new bootstrap.Modal(
							document.getElementById("returnModal")
						);
                        returnModal.show();
                    });
                } else {
                    // Show error
					$("#managerPassword").addClass("is-invalid");
					$("#passwordError").text(
						response.message || "Invalid manager password"
					);
				}
			},
			error: function () {
				$("#managerPassword").addClass("is-invalid");
				$("#passwordError").text("Error verifying password. Please try again.");
			},
			complete: function () {
                // Restore button state
				$btn.prop("disabled", false).html(originalText);
			},
        });
    });

    // Allow Enter key to verify password
	$("#managerPassword").on("keypress", function (e) {
        if (e.which === 13) {
			$("#verifyManagerPassword").click();
        }
    });

    // Clear validation state when typing
	$("#managerPassword").on("input", function () {
		$(this).removeClass("is-invalid");
		$("#passwordError").text("");
	});

	$("#searchInvoiceBtn").on("click", function () {
		const invoiceNumber = $("#returnInvoiceNumber").val().trim();
        if (!invoiceNumber) return;
        Swal.fire({
			title: "Loading...",
            allowOutsideClick: false,
			didOpen: () => {
				Swal.showLoading();
			},
        });
        $.ajax({
			url: "../ajax/get_invoice.php",
			type: "POST",
            data: { invoice_number: invoiceNumber, csrf_token: csrfToken },
			dataType: "json",
			success: function (response) {
                Swal.close();
				if (
					response.success &&
					response.data &&
					response.data.items.length > 0
				) {
					$("#returnInvoiceDetails").show();
					$("#submitReturnBtn").show();
					let html = "";
					response.data.items.forEach(function (item, idx) {
                        html += `<tr>
                            <td>${item.name}</td>
                            <td>${item.item_code}</td>
                            <td>${item.quantity}</td>
                            <td><input type="number" class="form-control form-control-sm return-qty-input" data-idx="${idx}" min="0" max="${
							item.quantity
						}" value="0"></td>
                            <td>${parseFloat(item.unit_price).toFixed(2)}</td>
                            <td><input type="text" class="form-control form-control-sm condition-notes-input" data-idx="${idx}"></td>
                        </tr>`;
                    });
					$("#returnItemsTable tbody").html(html);
					$("#returnItemsTable").data("invoice", response.data);
                } else {
					$("#returnInvoiceDetails").hide();
					$("#submitReturnBtn").hide();
					$("#returnItemsTable tbody").html(
						'<tr><td colspan="6">No items found for this invoice.</td></tr>'
					);
                    Swal.fire({
						title: "Not Found",
						text: response.message || "No items found for this invoice.",
						icon: "warning",
                        timer: 2000,
						showConfirmButton: false,
                    });
                }
            },
			error: function () {
                Swal.close();
				$("#returnInvoiceDetails").hide();
				$("#submitReturnBtn").hide();
				$("#returnItemsTable tbody").html(
					'<tr><td colspan="6">Error fetching invoice.</td></tr>'
				);
                Swal.fire({
					title: "Error",
					text: "Error fetching invoice.",
					icon: "error",
                    timer: 2000,
					showConfirmButton: false,
                });
			},
        });
    });

	$("#submitReturnBtn").on("click", function () {
		const invoiceData = $("#returnItemsTable").data("invoice");
        if (!invoiceData) return;
        let items = [];
		$("#returnItemsTable tbody tr").each(function (idx) {
            const item = invoiceData.items[idx];
			const qty = parseInt($(this).find(".return-qty-input").val()) || 0;
			const notes = $(this).find(".condition-notes-input").val();
            if (qty > 0) {
                items.push({
                    invoice_item_id: item.invoice_item_id,
                    item_id: item.item_id,
                    barcode_id: item.barcode_id,
                    quantity_returned: qty,
                    unit_price: item.unit_price,
					condition_notes: notes,
                });
            }
        });
        if (items.length === 0) {
            Swal.fire({
				title: "Error",
				text: "Please enter at least one return quantity.",
				icon: "error",
                timer: 2000,
				showConfirmButton: false,
            });
            return;
        }
        const data = {
            csrf_token: csrfToken,
            original_invoice_id: invoiceData.invoice_id,
			return_reason: $("#returnReason").val(),
			notes: $("#returnNotes").val(),
            items: JSON.stringify(items),
			return_type:
				items.length === invoiceData.items.length &&
				items.every(
					(it, idx) => it.quantity_returned === invoiceData.items[idx].quantity
				)
					? "full"
					: "partial",
        };
        Swal.fire({
			title: "Processing...",
            allowOutsideClick: false,
			didOpen: () => {
				Swal.showLoading();
			},
        });
        $.ajax({
			url: "../ajax/process_return.php",
			type: "POST",
            data: data,
			dataType: "json",
			success: function (response) {
                Swal.close();
                if (response.success) {
					$("#returnModal").modal("hide");
                    loadInventory(); // Refresh inventory after return
                    Swal.fire({
						title: "Success!",
						text:
							"Return processed successfully! Return Number: " +
							response.return_number,
						icon: "success",
                        timer: 2500,
						showConfirmButton: false,
                    });
                } else {
                    Swal.fire({
						title: "Error",
                        text: response.message,
						icon: "error",
                        timer: 2000,
						showConfirmButton: false,
                    });
                }
            },
			error: function () {
                Swal.close();
                Swal.fire({
					title: "Error",
					text: "Server error while processing return.",
					icon: "error",
                    timer: 2000,
					showConfirmButton: false,
                });
			},
        });
    });

    // --- Expenses Modal Logic ---
    function loadExpenses() {
        $.ajax({
			url: "../ajax/process_expense.php",
			type: "GET",
			data: { action: "list" },
			dataType: "json",
			success: function (response) {
                if (response.success) {
					let html = "";
					response.data.forEach(function (exp) {
                        html += `<tr>
                            <td>${exp.expense_date}</td>
                            <td>${exp.category}</td>
                            <td>${exp.description}</td>
                            <td>${parseFloat(exp.amount).toFixed(2)}</td>
                            <td><span class="badge bg-${
															exp.status === "approved"
																? "success"
																: exp.status === "rejected"
																? "danger"
																: "secondary"
														}">${exp.status}</span></td>
                            <td>${
															exp.receipt_image
																? `<a href="../${exp.receipt_image}" target="_blank">View</a>`
																: ""
														}</td>
                            <td>${exp.notes || ""}</td>
                            <td>`;
						if (exp.status === "pending") {
                            html += `<button class="btn btn-sm btn-primary edit-expense-btn" data-id="${exp.id}"><i class="bi bi-pencil"></i></button> `;
                            html += `<button class="btn btn-sm btn-danger delete-expense-btn" data-id="${exp.id}"><i class="bi bi-trash"></i></button>`;
                        }
                        html += `</td></tr>`;
                    });
					$("#expensesTable tbody").html(html);
                } else {
					$("#expensesTable tbody").html(
						'<tr><td colspan="8">No expenses found.</td></tr>'
					);
				}
			},
			error: function () {
				$("#expensesTable tbody").html(
					'<tr><td colspan="8">Error loading expenses.</td></tr>'
				);
			},
		});
	}

	$("#openExpensesModal, #openExpensesModalSales").on("click", function () {
        loadExpenses();
		const modal = new bootstrap.Modal(document.getElementById("expensesModal"));
        modal.show();
    });

	$("#addExpenseBtn").on("click", function () {
		$("#expenseModalTitle").text("Add Expense");
		$("#expenseForm")[0].reset();
		$("#expenseId").val("");
		$("#expenseReceiptPreview").html("");
		const modal = new bootstrap.Modal(
			document.getElementById("addEditExpenseModal")
		);
        modal.show();
    });

	$(document).on("click", ".edit-expense-btn", function () {
		const id = $(this).data("id");
        $.ajax({
			url: "../ajax/process_expense.php",
			type: "GET",
			data: { action: "list" },
			dataType: "json",
			success: function (response) {
                if (response.success) {
					const exp = response.data.find((e) => e.id == id);
                    if (exp) {
						$("#expenseModalTitle").text("Edit Expense");
						$("#expenseId").val(exp.id);
						$("#expenseCategory").val(exp.category);
						$("#expenseDescription").val(exp.description);
						$("#expenseAmount").val(exp.amount);
						$("#expenseDate").val(exp.expense_date);
						$("#expenseNotes").val(exp.notes);
                        if (exp.receipt_image) {
							$("#expenseReceiptPreview").html(
								`<a href="../${exp.receipt_image}" target="_blank">View Current Receipt</a>`
							);
                        } else {
							$("#expenseReceiptPreview").html("");
                        }
						const modal = new bootstrap.Modal(
							document.getElementById("addEditExpenseModal")
						);
                        modal.show();
                    }
                }
			},
        });
    });

	$(document).on("click", ".delete-expense-btn", function () {
		if (!confirm("Delete this expense?")) return;
		const id = $(this).data("id");
        $.ajax({
			url: "../ajax/process_expense.php",
			type: "POST",
			data: { action: "delete", expense_id: id },
			dataType: "json",
			success: function (response) {
                if (response.success) {
                    loadExpenses();
                } else {
                    alert(response.message);
                }
			},
        });
    });

	$("#expenseReceipt").on("change", function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
			reader.onload = function (e) {
				$("#expenseReceiptPreview").html(
					`<img src="${e.target.result}" class="img-thumbnail" style="max-width:150px;">`
				);
			};
            reader.readAsDataURL(this.files[0]);
        }
    });

	$("#expenseForm").on("submit", function (e) {
        e.preventDefault();
        const formData = new FormData(this);
		formData.append("action", $("#expenseId").val() ? "edit" : "add");
        $.ajax({
			url: "../ajax/process_expense.php",
			type: "POST",
            data: formData,
            processData: false,
            contentType: false,
			dataType: "json",
			success: function (response) {
                if (response.success) {
					$("#addEditExpenseModal").modal("hide");
                    loadExpenses();
                } else {
                    alert(response.message);
                }
			},
        });
    });

	$("#returnModal").on("hidden.bs.modal", function () {
        if (Swal.isVisible()) {
            Swal.close();
        }
        // Remove any lingering Bootstrap modal backdrops
		$(".modal-backdrop").remove();
        // Remove modal-open class from body
		$("body").removeClass("modal-open");
    });

    // --- Custom Item Functionality ---
    let customItemCounter = -1; // Use negative numbers for custom items

    // Open custom item modal
	$("#addCustomItemBtn").on("click", function () {
		$("#customItemForm")[0].reset();
		$("#customItemQuantity").val(1);
		const modal = new bootstrap.Modal(
			document.getElementById("customItemModal")
		);
        modal.show();
        
        // Focus on price field (matching Flutter autofocus)
        setTimeout(() => {
			$("#customItemPrice").focus();
        }, 500);
    });

    // Add custom item to cart
	$("#addCustomItemToCart").on("click", function () {
		const form = $("#customItemForm")[0];
        
        // Validate form
        if (!form.checkValidity()) {
			form.classList.add("was-validated");
            return;
        }
        
		const price = parseFloat($("#customItemPrice").val());
		const quantity = parseInt($("#customItemQuantity").val());
        
        if (price <= 0 || quantity <= 0) {
            Swal.fire({
				title: "Invalid Input!",
				text: "Please enter valid price and quantity.",
				icon: "error",
                timer: 3000,
                timerProgressBar: true,
                toast: true,
				position: "top-end",
				showConfirmButton: false,
            });
            return;
        }
        
        // Auto-generate name and code based on price (matching Flutter logic)
        const name = `product_${price.toFixed(0)}`;
        const code = `P${price.toFixed(0)}`;
        
        // Check if non-inventory item with same name and price already exists
		const existingIndex = cart.findIndex(
			(item) =>
				item.is_non_inventory && item.name === name && item.unit_price === price
        );
        
        if (existingIndex >= 0) {
            // Update existing non-inventory item quantity (matching Flutter logic)
            cart[existingIndex].quantity += quantity;
            renderCart();
        } else {
            // Create new custom item object
            const customItem = {
                id: customItemCounter,
                item_id: customItemCounter,
                barcode_id: customItemCounter,
                name: name,
                item_code: code,
                unit_price: price,
                selling_price: price,
                current_stock: 999999,
				is_non_inventory: true,
            };
            
            // Add to cart
            addToCart(customItem, quantity);
            
            // Decrement counter for next custom item
            customItemCounter--;
        }
        
        // Close modal
		$("#customItemModal").modal("hide");
        
        // Show success toast
        Swal.fire({
			title: "Custom Item Added!",
            text: `${name} added to cart successfully`,
			icon: "success",
            timer: 2000,
            timerProgressBar: true,
            toast: true,
			position: "top-end",
			showConfirmButton: false,
        });
        
        // Return focus to barcode input
        setTimeout(() => {
            focusBarcodeInput();
        }, 500);
    });

    // Allow Enter key to add custom item
	$("#customItemModal input").on("keypress", function (e) {
        if (e.which === 13) {
            e.preventDefault();
			$("#addCustomItemToCart").click();
        }
    });

    // Clear form validation when modal is hidden
	$("#customItemModal").on("hidden.bs.modal", function () {
		$("#customItemForm")[0].classList.remove("was-validated");
    });

    // --- Price Override Functionality ---
    let currentPriceOverrideIndex = -1;

    // Show price override modal
    function showPriceOverrideModal(itemIndex, item) {
        currentPriceOverrideIndex = itemIndex;
        
        // Populate item information
		$("#priceOverrideItemName").text(item.name);
		$("#priceOverrideItemCode").text(item.item_code);
		$("#priceOverrideOriginalPrice").text(
			`CFA ${(item.original_price || item.unit_price).toFixed(2)}`
		);
		$("#priceOverrideCurrentPrice").text(`CFA ${item.unit_price.toFixed(2)}`);
        
        // Set current price in input
		$("#newPriceInput").val(item.unit_price.toFixed(2));
        
        // Clear password and form validation
		$("#managerPasswordInput").val("");
		$("#priceOverrideForm")[0].classList.remove("was-validated");
        
        // Show modal
		const modal = new bootstrap.Modal(
			document.getElementById("priceOverrideModal")
		);
        modal.show();
        
        // Focus on price input
        setTimeout(() => {
			$("#newPriceInput").focus().select();
        }, 500);
    }

    // Reset price to original
	$("#resetPriceBtn").on("click", function () {
		if (
			currentPriceOverrideIndex >= 0 &&
			currentPriceOverrideIndex < cart.length
		) {
            const item = cart[currentPriceOverrideIndex];
            const originalPrice = item.original_price || item.unit_price;
			$("#newPriceInput").val(originalPrice.toFixed(2));
        }
    });

    // Override price with manager authorization
	$("#overridePriceBtn").on("click", function () {
		const form = $("#priceOverrideForm")[0];
        
        // Validate form
        if (!form.checkValidity()) {
			form.classList.add("was-validated");
            return;
        }
        
		const newPrice = parseFloat($("#newPriceInput").val());
		const managerPassword = $("#managerPasswordInput").val().trim();
        
        if (newPrice < 0) {
            Swal.fire({
				title: "Invalid Price!",
				text: "Please enter a valid price.",
				icon: "error",
                timer: 3000,
                timerProgressBar: true,
                toast: true,
				position: "top-end",
				showConfirmButton: false,
            });
            return;
        }
        
        if (!managerPassword) {
            Swal.fire({
				title: "Authorization Required!",
				text: "Manager password is required for price override.",
				icon: "warning",
                timer: 3000,
                timerProgressBar: true,
                toast: true,
				position: "top-end",
				showConfirmButton: false,
            });
            return;
        }
        
        // Disable button during verification
		const $btn = $("#overridePriceBtn");
        const originalText = $btn.html();
		$btn
			.prop("disabled", true)
			.html('<i class="bi bi-hourglass-split me-1"></i> Verifying...');
        
        // Verify manager password
        $.ajax({
			url: "../ajax/verify_manager_password.php",
			type: "POST",
            data: {
                password: managerPassword,
				csrf_token: csrfToken,
            },
			dataType: "json",
			success: function (response) {
                if (response.success) {
                    // Password verified - apply price override
					if (
						currentPriceOverrideIndex >= 0 &&
						currentPriceOverrideIndex < cart.length
					) {
                        const item = cart[currentPriceOverrideIndex];
                        const oldPrice = item.unit_price;
                        
                        // Store original price if not already stored
                        if (!item.original_price) {
                            item.original_price = item.unit_price;
                        }
                        
                        // Update price
                        item.unit_price = newPrice;
						item.is_price_overridden = newPrice !== item.original_price;
                        
                        // Re-render cart
                        renderCart();
                        
                        // Close modal
						$("#priceOverrideModal").modal("hide");
                        
                        // Show success toast
                        const difference = newPrice - oldPrice;
                        const isIncrease = difference > 0;
                        Swal.fire({
							title: "Price Updated!",
							text: `Price ${
								isIncrease ? "increased" : "decreased"
							} by CFA ${Math.abs(difference).toFixed(
								2
							)} with manager authorization`,
							icon: "success",
                            timer: 3000,
                            timerProgressBar: true,
                            toast: true,
							position: "top-end",
							showConfirmButton: false,
                        });
                        
                        // Return focus to barcode input
                        setTimeout(() => {
                            focusBarcodeInput();
                        }, 500);
                    }
                } else {
                    Swal.fire({
						title: "Access Denied!",
						text: "Invalid manager password. Please try again.",
						icon: "error",
                        timer: 3000,
                        timerProgressBar: true,
                        toast: true,
						position: "top-end",
						showConfirmButton: false,
                    });
                }
            },
			error: function () {
                Swal.fire({
					title: "Verification Error!",
					text: "Error verifying password. Please check your connection and try again.",
					icon: "error",
                    timer: 3000,
                    timerProgressBar: true,
                    toast: true,
					position: "top-end",
					showConfirmButton: false,
                });
            },
			complete: function () {
                // Restore button state
				$btn.prop("disabled", false).html(originalText);
			},
        });
    });

    // Allow Enter key in price override modal
	$("#priceOverrideModal input").on("keypress", function (e) {
        if (e.which === 13) {
            e.preventDefault();
			if ($(this).is("#newPriceInput")) {
				$("#managerPasswordInput").focus();
			} else if ($(this).is("#managerPasswordInput")) {
				$("#overridePriceBtn").click();
            }
        }
    });

         // Clear form validation when price override modal is hidden
	$("#priceOverrideModal").on("hidden.bs.modal", function () {
		$("#priceOverrideForm")[0].classList.remove("was-validated");
         currentPriceOverrideIndex = -1;
     });

    // --- Discount Modal Logic ---
    let currentDiscountIndex = -1;

    // Show discount modal
    function showDiscountModal(itemIndex, item) {
        currentDiscountIndex = itemIndex;
        
        // Populate item information
		$("#discountItemName").text(item.name);
		$("#discountItemCode").text(item.item_code);
		$("#discountOriginalPrice").text(`CFA ${item.unit_price.toFixed(2)}`);
		$("#discountCurrentTotal").text(
			`CFA ${(item.unit_price * item.quantity).toFixed(2)}`
		);
        
        // Reset custom discount input
		$("#customDiscountInput").val("");
        
        // Clear any previous selection
		$(".discount-option").removeClass("active");
        
        // Show current discount if any
        if (item.discount_percentage > 0) {
			if (
				[10, 20, 30, 40, 50, 60, 70, 80, 90, 100].includes(item.discount_percentage)
			) {
				$(
					`.discount-option[data-discount="${item.discount_percentage}"]`
				).addClass("active");
            } else {
				$("#customDiscountInput").val(item.discount_percentage);
            }
        }
        
        // Show modal
		const modal = new bootstrap.Modal(document.getElementById("discountModal"));
        modal.show();
    }

    // Handle discount button click
	$(document).on("click", ".discount-btn", function () {
		const idx = $(this).data("idx");
        const item = cart[idx];
        if (item) {
            showDiscountModal(idx, item);
        }
    });

    // Handle preset discount selection
	$(document).on("click", ".discount-option", function () {
		$(".discount-option").removeClass("active");
		$(this).addClass("active");
		$("#customDiscountInput").val(""); // Clear custom input
        
        // Show preview
		const discountPercentage = $(this).data("discount");
        updateDiscountPreview(discountPercentage);
    });

    // Handle custom discount input
	$("#customDiscountInput").on("input", function () {
        let value = parseFloat($(this).val());
        
        // Clear preset selection
		$(".discount-option").removeClass("active");
        
        // Validate and limit input
        if (value < 0) value = 0;
        if (value > 100) value = 100;
        $(this).val(value);
        
        // Show preview
        updateDiscountPreview(value);
    });

    // Update discount preview
    function updateDiscountPreview(discountPercentage) {
        if (currentDiscountIndex >= 0 && currentDiscountIndex < cart.length) {
            const item = cart[currentDiscountIndex];
            const originalTotal = item.unit_price * item.quantity;
            const discountAmount = (originalTotal * discountPercentage) / 100;
            const finalTotal = originalTotal - discountAmount;
            
			$("#discountPreview").html(`
                <div class="text-muted">Original Total: CFA ${originalTotal.toFixed(
									2
								)}</div>
                <div class="text-success">Discount (${discountPercentage}%): CFA -${discountAmount.toFixed(
				2
			)}</div>
                <div class="fw-bold">Final Total: CFA ${finalTotal.toFixed(
									2
								)}</div>
            `);
        }
    }

    // Apply discount
	$("#applyDiscountBtn").on("click", function () {
        if (currentDiscountIndex >= 0 && currentDiscountIndex < cart.length) {
            const item = cart[currentDiscountIndex];
            
            // Get selected discount percentage
            let discountPercentage = 0;
			const activePreset = $(".discount-option.active");
            if (activePreset.length) {
				discountPercentage = activePreset.data("discount");
            } else {
				discountPercentage = parseFloat($("#customDiscountInput").val()) || 0;
            }
            
            // Validate discount
            if (discountPercentage < 0 || discountPercentage > 100) {
                showToast(
					"Invalid Discount!",
					"Please select a discount between 0% and 100%.",
					"error",
                    3000
                );
                return;
            }
            
            // Calculate and apply discount
            const originalTotal = item.unit_price * item.quantity;
            const discountAmount = (originalTotal * discountPercentage) / 100;
            
            // Update cart item
            item.discount_percentage = discountPercentage;
            item.discount_amount = discountAmount;
            
            // Close modal
			$("#discountModal").modal("hide");
            
            // Re-render cart
            renderCart();
            
            // Show success message
            showToast(
				"Discount Applied!",
                `${discountPercentage}% discount applied to ${item.name}`,
				"success",
                2000
            );
        }
    });

    // Remove discount
	$("#removeDiscountBtn").on("click", function () {
        if (currentDiscountIndex >= 0 && currentDiscountIndex < cart.length) {
            const item = cart[currentDiscountIndex];
            
            // Remove discount
            item.discount_percentage = 0;
            item.discount_amount = 0;
            
            // Close modal
			$("#discountModal").modal("hide");
            
            // Re-render cart
            renderCart();
            
            // Show message
            showToast(
				"Discount Removed!",
                `Discount removed from ${item.name}`,
				"success",
                2000
            );
        }
    });

    // Clear discount modal when hidden
	$("#discountModal").on("hidden.bs.modal", function () {
        currentDiscountIndex = -1;
		$("#discountPreview").html("");
	});

	// ============ SHIFT REPORT FUNCTIONALITY ============

	// Open shift report modal
	$("#openShiftReportModal").on("click", function () {
		$("#shiftReportModal").modal("show");

		// Update session information display
		updateSessionInfo();

		// Hide previous content
		$("#shiftReportContent").hide();
		$("#shiftReportError").hide();
		$("#printShiftReport").hide();
		$("#endShiftBtn").hide();
	});

	// Update session information in the modal
	function updateSessionInfo() {
		const currentTime = new Date();
		$("#sessionCurrentTime").text(currentTime.toLocaleString());

		// Session info will be populated when report is generated
		$("#sessionLoginTime").text("Will be shown in report");
		$("#sessionDuration").text("Will be calculated in report");
	}

	// Generate shift report
	$("#generateShiftReport").on("click", function () {
		// Show loading
		$("#shiftReportContent").hide();
		$("#shiftReportError").hide();
		$("#shiftReportLoading").show();
		$("#printShiftReport").hide();

		// Generate session-based report
		$.ajax({
			url: "../ajax/generate_shift_report.php",
			type: "GET",
			data: {
				// No date parameters needed - using session-based tracking
			},
			dataType: "json",
			success: function (response) {
				$("#shiftReportLoading").hide();

				if (response.success) {
					populateShiftReport(response.data);
					$("#shiftReportContent").show();
					$("#printShiftReport").show();
					$("#endShiftBtn").show();
				} else {
					$("#shiftReportErrorMessage").text(
						response.message || "Failed to generate report"
					);
					$("#shiftReportError").show();
				}
			},
			error: function () {
				$("#shiftReportLoading").hide();
				$("#shiftReportErrorMessage").text(
					"Network error occurred while generating report"
				);
				$("#shiftReportError").show();
			},
		});
	});

	// Populate shift report with data
	function populateShiftReport(data) {
		// Store data for printing
		currentReportData = data;

		const shiftInfo = data.shift_info;
		const netSales = data.net_sales || 0;
		const paymentMethods = data.payment_methods || [];
		const expenses = data.expenses || [];
		const totalExpenses = data.total_expenses || 0;

		// Update session information in modal
		$("#sessionLoginTime").text(formatDateTime(shiftInfo.login_time));
		$("#sessionCurrentTime").text(formatDateTime(shiftInfo.current_time));
		$("#sessionDuration").text(shiftInfo.session_duration_formatted);

		// Report header
		$("#reportStoreName").text(shiftInfo.store_name || "Store");
		$("#reportLoginTime").text(formatDateTime(shiftInfo.login_time));
		$("#reportGeneratedAt").text(formatDateTime(shiftInfo.generated_at));
		$("#reportSessionDuration").text(shiftInfo.session_duration_formatted);
		$("#reportGeneratedBy").text(shiftInfo.generated_by);

		// Net sales summary
		$("#netSales").text("CFA " + netSales.toFixed(2));
		$("#totalExpenses").text("CFA " + totalExpenses.toFixed(2));

		// Sales by Payment Method table
		let salesHtml = "";
		if (paymentMethods && paymentMethods.length > 0) {
			paymentMethods.forEach(function (method) {
				let methodDisplay = capitalizeFirst(method.method);
				let amountDisplay = `CFA ${method.amount.toFixed(2)}`;
				
				// Add cash/mobile breakdown for cash_mobile payments
				if (method.method === 'cash_mobile' && method.cash_amount !== undefined && method.mobile_amount !== undefined) {
					methodDisplay = 'Cash + Mobile';
					amountDisplay = `
						<div>Total: CFA ${method.amount.toFixed(2)}</div>
						<div class="small text-muted">
							Cash: CFA ${method.cash_amount.toFixed(2)} | Mobile: CFA ${method.mobile_amount.toFixed(2)}
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
			salesHtml =
				'<tr><td colspan="3" class="text-center text-muted">No sales data</td></tr>';
		}
		$("#salesByPaymentTable tbody").html(salesHtml);
		$("#totalSalesAmount").text("CFA " + netSales.toFixed(2));

		// Expenses table
		let expensesHtml = "";
		if (expenses && expenses.length > 0) {
			expenses.forEach(function (expense) {
				expensesHtml += `
                    <tr>
                        <td>${expense.category}</td>
                        <td>${expense.description}</td>
                        <td>CFA ${expense.amount.toFixed(2)}</td>
                        <td>${formatDateTime(expense.created_at)}</td>
                    </tr>
                `;
			});
		} else {
			expensesHtml =
				'<tr><td colspan="4" class="text-center text-muted">No expenses recorded</td></tr>';
		}
		$("#expensesTable tbody").html(expensesHtml);
		$("#totalExpensesAmount").text("CFA " + totalExpenses.toFixed(2));

		// Final Summary calculations
		const netTotal = netSales - totalExpenses;
		$("#totalSalesSummary").text("CFA " + netSales.toFixed(2));
		$("#totalExpensesSummary").text("CFA " + totalExpenses.toFixed(2));
		$("#netTotalSummary").text("CFA " + netTotal.toFixed(2));
	}

	// Print shift report
	$("#printShiftReport").on("click", function () {
		// Hide modal temporarily for printing
		$("#shiftReportModal").modal("hide");

		setTimeout(function () {
			// Generate 80mm printer-friendly content
			const reportData = getCurrentReportData();
			const printContent = generate80mmPrintLayout(reportData);
			const printWindow = window.open("", "_blank");

			printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Shift Report</title>
                    <meta charset="UTF-8">
                    <style>
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                        
                        body {
                            width: 80mm;
                            margin: 0;
                            padding: 2mm;
                            font-family: 'Courier New', monospace;
                            font-size: 10px;
                            line-height: 1.2;
                            color: #000;
                            background: white;
                        }
                        
                        .center { text-align: center; }
                        .bold { font-weight: bold; }
                        .small { font-size: 8px; }
                        .line { 
                            display: flex; 
                            justify-content: space-between; 
                            margin: 1mm 0; 
                        }
                        .line.total { 
                            font-weight: bold; 
                            border-top: 1px solid #000; 
                            padding-top: 1mm; 
                            margin-top: 2mm; 
                        }
                        .divider {
                            border-top: 1px solid #000;
                            margin: 2mm 0;
                        }
                        .section { margin: 3mm 0; }
                        h1 { font-size: 12px; margin: 2mm 0; }
                        h2 { font-size: 10px; margin: 2mm 0 1mm 0; }
                        p { margin: 1mm 0; font-size: 9px; }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
                </html>
            `);

			printWindow.document.close();
			printWindow.focus();

			// Wait for content to load then print
			setTimeout(function () {
				printWindow.print();
				printWindow.close();

				// Show modal again
				$("#shiftReportModal").modal("show");
			}, 500);
		}, 300);
	});

	// End shift functionality - now uses the same modal as enforced logout
	$("#endShiftBtn").on("click", function () {
		if (
			confirm(
				"Are you sure you want to end your shift and logout?\n\nA shift summary report will be generated automatically."
			)
		) {
			// Close the shift report modal first
			$("#shiftReportModal").modal("hide");
			
			// Show the logout summary modal with loading state
			const modal = new bootstrap.Modal(document.getElementById("logoutSummaryModal"));
			modal.show();
			
			// Reset modal state
			document.getElementById("logoutSummaryLoading").style.display = "block";
			document.getElementById("logoutSummaryError").style.display = "none";
			document.getElementById("logoutSummaryContent").style.display = "none";
			document.getElementById("printLogoutSummary").style.display = "none";
			document.getElementById("completeLogoutBtn").style.display = "none";
			document.getElementById("forceLogoutBtn").style.display = "none";
			
			// Use the same enforced logout logic for consistency
			$.ajax({
				url: "../ajax/enforced_logout.php",
				type: "POST",
				dataType: "json",
				success: function (response) {
					// Hide loading
					document.getElementById("logoutSummaryLoading").style.display = "none";
					
					if (response.success && response.can_logout) {
						if (response.shift_summary) {
							// Populate the modal with shift summary
							populateLogoutSummary(response.shift_summary);
							
							// Show summary content and buttons
							document.getElementById("logoutSummaryContent").style.display = "block";
							document.getElementById("printLogoutSummary").style.display = "inline-block";
							document.getElementById("completeLogoutBtn").style.display = "inline-block";
						} else {
							// No summary but can logout
							document.getElementById("completeLogoutBtn").style.display = "inline-block";
						}
					} else {
						// Show error state
						document.getElementById("logoutSummaryErrorMessage").textContent = response.message || "Failed to close shift. Please try again.";
						document.getElementById("logoutSummaryError").style.display = "block";
						document.getElementById("forceLogoutBtn").style.display = "inline-block";
					}
				},
				error: function () {
					// Hide loading and show error
					document.getElementById("logoutSummaryLoading").style.display = "none";
					document.getElementById("logoutSummaryErrorMessage").textContent = "Network error occurred while closing shift. Please try again.";
					document.getElementById("logoutSummaryError").style.display = "block";
					document.getElementById("forceLogoutBtn").style.display = "inline-block";
				}
			});
		}
	});

	// Store current report data for printing
	let currentReportData = null;

	// Helper functions for shift report
	function getCurrentReportData() {
		return currentReportData;
	}

	// Generate 80mm thermal printer layout
	function generate80mmPrintLayout(data) {
		if (!data) return '<p class="center">No report data available</p>';

		const shiftInfo = data.shift_info;
		const netSales = data.net_sales || 0;
		const paymentMethods = data.payment_methods || [];
		const expenses = data.expenses || [];
		const totalExpenses = data.total_expenses || 0;

		let html = `
            <div class="center">
                <h1 class="bold">RAPPORT DE GARDE</h1>
                <p class="bold">${shiftInfo.store_name || "Magasin"}</p>
                <p class="small">${shiftInfo.store_code || ""}</p>
            </div>
            <div class="divider"></div>
            
            <div class="section">
                <p class="small">Début: ${formatDateTimeCompact(
									shiftInfo.login_time
								)}</p>
                <p class="small">Généré: ${formatDateTimeCompact(
									shiftInfo.generated_at
								)}</p>
                <p class="small">Durée: ${formatDurationFrench(
									shiftInfo.session_duration_hours,
									shiftInfo.session_duration_minutes
								)}</p>
                <p class="small">Par: ${shiftInfo.generated_by}</p>
            </div>
            <div class="divider"></div>
            
            <div class="section">
                <h2 class="bold">VENTES PAR MODE DE PAIEMENT</h2>
        `;
		
		// Add payment methods breakdown
		if (paymentMethods && paymentMethods.length > 0) {
			paymentMethods.forEach((method) => {
				let methodName = translatePaymentMethod(method.method);
				let amountDisplay = formatCurrencyFrench(method.amount);
				
				// Add cash/mobile breakdown for cash_mobile payments
				if (method.method === 'cash_mobile' && method.cash_amount !== undefined && method.mobile_amount !== undefined) {
					methodName = 'Espèces + Mobile';
					amountDisplay = formatCurrencyFrench(method.amount);
					
					html += `
                        <div class="line">
                            <span>${methodName}:</span>
                            <span>${amountDisplay}</span>
                        </div>
                        <div class="small" style="margin-left: 3mm; margin-bottom: 1mm;">
                            Espèces: ${formatCurrencyFrench(method.cash_amount)} | Mobile: ${formatCurrencyFrench(method.mobile_amount)}
                        </div>
                    `;
				} else {
					html += `
                        <div class="line">
                            <span>${methodName}:</span>
                            <span>${amountDisplay}</span>
                        </div>
                    `;
				}
			});
		} else {
			html += `
                <div class="line">
                    <span>Aucune vente</span>
                    <span>CFA 0.00</span>
                </div>
            `;
		}
		
		html += `
                <div class="line total">
                    <span>TOTAL VENTES:</span>
                    <span class="bold">${formatCurrencyFrench(netSales)}</span>
                </div>
            </div>
            <div class="divider"></div>
        `;


		// Expenses
		if (expenses && expenses.length > 0) {
			html += `
                <div class="section">
                    <h2 class="bold">DÉPENSES</h2>
            `;
			expenses.forEach((expense) => {
				const description = expense.description.length > 25
					? expense.description.substring(0, 25) + "..."
					: expense.description;
				html += `
                    <div class="line">
                        <span>${expense.category}:</span>
                        <span>${formatCurrencyFrench(expense.amount)}</span>
                    </div>
                    <div class="small" style="margin-left: 3mm; margin-bottom: 1mm;">
                        ${description}
                    </div>
                `;
			});
			html += `
                    <div class="line total">
                        <span>TOTAL DÉPENSES:</span>
                        <span class="bold">${formatCurrencyFrench(totalExpenses)}</span>
                    </div>
                </div>
                <div class="divider"></div>
            `;
		} else {
			html += `
                <div class="section">
                    <h2 class="bold">DÉPENSES</h2>
                    <div class="line">
                        <span>Aucune dépense</span>
                        <span>CFA 0.00</span>
                    </div>
                </div>
                <div class="divider"></div>
            `;
		}
		
		// Final Total Calculation
		const finalTotal = netSales - totalExpenses;
		html += `
            <div class="section">
                <h2 class="bold">RÉCAPITULATIF</h2>
                <div class="line">
                    <span>Total Ventes:</span>
                    <span>${formatCurrencyFrench(netSales)}</span>
                </div>
                <div class="line">
                    <span>Total Dépenses:</span>
                    <span>-${formatCurrencyFrench(totalExpenses)}</span>
                </div>
                <div class="line total">
                    <span>TOTAL NET:</span>
                    <span class="bold">${formatCurrencyFrench(finalTotal)}</span>
                </div>
            </div>
        `;

		// Footer
		html += `
            <div class="center">
                <p class="small">Merci!</p>
                <p class="small">${formatDateTimeCompact(
									new Date().toISOString()
								)}</p>
            </div>
        `;

		return html;
	}

	function formatDateTime(dateTimeStr) {
		const date = new Date(dateTimeStr);
		return date.toLocaleString("en-US", {
			year: "2-digit",
			month: "2-digit",
			day: "2-digit",
			hour: "2-digit",
			minute: "2-digit",
			hour12: false,
		});
	}

	function formatDateTimeCompact(dateTimeStr) {
		const date = new Date(dateTimeStr);
		const day = String(date.getDate()).padStart(2, "0");
		const month = String(date.getMonth() + 1).padStart(2, "0");
		const year = String(date.getFullYear()).slice(-2);
		const hours = String(date.getHours()).padStart(2, "0");
		const minutes = String(date.getMinutes()).padStart(2, "0");

		return `${day}/${month}/${year} ${hours}:${minutes}`;
	}

	function capitalizeFirst(str) {
		return str.charAt(0).toUpperCase() + str.slice(1);
	}

	// French formatting functions for 80mm receipt
	function formatCurrencyFrench(amount) {
		return `${amount.toFixed(2).replace(".", ",")} CFA`;
	}

	function formatDurationFrench(hours, minutes) {
		if (hours === 0) {
			return `${minutes} min`;
		} else if (minutes === 0) {
			return `${hours}h`;
		} else {
			return `${hours}h ${minutes}min`;
		}
	}

	function translatePaymentMethod(method) {
		const translations = {
			cash: "Espèces",
			card: "Carte",
			mobile: "Mobile",
			cash_mobile: "Espèces + Mobile",
			credit: "Crédit",
		};
		return translations[method] || method;
	}
});
