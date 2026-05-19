<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$customers = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
?>

<!-- Load jQuery Early to Ensure Inline Script works perfectly -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Premium POS Screen Wrapper — Full Viewport -->
<div class="premium-dark-page pos-viewport-wrapper">
    <div class="pos-layout-container">
        
        <!-- Product Selection Area (Left Panel) -->
        <div class="pos-left-panel">
            <div class="d-flex justify-content-between align-items-center mb-2" style="flex-shrink:0;">
                <h5 class="fw-bold m-0"><i class="fas fa-th-large text-primary me-2"></i> Product Selection</h5>
            </div>

            <!-- Search and Scan Input -->
            <div class="mb-2 position-relative" style="flex-shrink:0;">
                <input type="text" id="searchProduct" class="form-control ps-5" placeholder="Search by product name or scan barcode..." style="height:38px;">
                <i class="fas fa-search position-absolute text-muted" style="left: 14px; top: 11px; font-size: 1rem;"></i>
            </div>
            
            <!-- Category Scrollable Filter Pills — SINGLE LINE -->
            <div class="d-flex overflow-auto mb-2 gap-2 align-items-center" style="white-space: nowrap; flex-shrink:0; padding-bottom: 8px;">
                <a href="javascript:void(0)" class="pos-category-pill active" data-id="all" onclick="selectCategory('all', this)">All Products</a>
                <?php foreach($categories as $cat): ?>
                    <a href="javascript:void(0)" class="pos-category-pill" data-id="<?= $cat['id'] ?>" onclick="selectCategory(<?= $cat['id'] ?>, this)">
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Products Loaded dynamically via AJAX — scrolls internally -->
            <div class="pos-product-grid-scroll">
                <div class="row g-2 m-0" id="productList">
                    <!-- Product grid cards injected here -->
                </div>
            </div>
        </div>

        <!-- Cart and Billing Summary Area (Right Panel) -->
        <div class="pos-right-panel">
            <div class="card pos-cart-card border-0" style="border: 1px solid var(--border-color-dark) !important;">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-2" style="flex-shrink:0;">
                    <h6 class="m-0 fw-bold text-dark"><i class="fas fa-shopping-cart text-primary me-2"></i> Current Sale</h6>
                    <span class="badge bg-dark-premium text-white px-2 py-1 border" style="border-color: var(--border-color-dark) !important; font-size:0.75rem;" id="cartCount">0 Items</span>
                </div>
                
                <div class="card-body">
                    
                    <!-- Customer Selector -->
                    <div class="mb-2" style="flex-shrink:0;">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.72rem;">Select Customer</label>
                        <select id="customerId" class="form-select form-select-sm">
                            <option value="">Walk-in Customer</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Cart Item Table (Scrollable) -->
                    <div class="pos-cart-items-scroll" style="border-bottom: 1px solid var(--border-color-dark);">
                        <table class="table table-hover align-middle table-sm m-0">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color-dark);">
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cartItems">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">
                                        <i class="fas fa-shopping-basket d-block fs-4 mb-1 opacity-50"></i>
                                        <small>Cart is empty. Select items to start sale.</small>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Billing Calculations Footer Card — always visible -->
                    <div class="pos-billing-footer mt-auto" style="background: #ffffff !important; border: 1px solid var(--border-color-dark) !important;">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted" style="font-size:0.85rem;">Subtotal:</span>
                            <span class="fw-bold text-dark" style="font-size:0.85rem;" id="subtotal">LKR 0.00</span>
                        </div>
                        <div class="row g-2 mb-1">
                            <div class="col-6">
                                <label class="text-muted" style="font-size: 0.7rem;">Discount (%)</label>
                                <input type="number" id="discount" class="form-control form-control-sm text-end" value="0" min="0" max="100" onchange="calculateTotal()" style="height:28px; font-size:0.8rem;">
                            </div>
                            <div class="col-6">
                                <label class="text-muted" style="font-size: 0.7rem;">Tax (%)</label>
                                <input type="number" id="tax" class="form-control form-control-sm text-end" value="0" min="0" onchange="calculateTotal()" style="height:28px; font-size:0.8rem;">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2 border-top pt-2" style="border-color: var(--border-color-dark) !important;">
                            <h5 class="fw-bold m-0 text-dark">Grand Total:</h5>
                            <h5 class="fw-bold m-0" id="grandTotal" style="color: var(--primary-orange) !important;">LKR 0.00</h5>
                        </div>
                        
                        <!-- Form submitted via AJAX -->
                        <form id="checkoutForm" onsubmit="submitCheckout(event)">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="text-muted mb-1" style="font-size: 0.7rem;">Payment Method</label>
                                    <select name="payment_method" id="paymentMethod" class="form-select form-select-sm" required style="height:28px; font-size:0.8rem;">
                                        <option value="Cash">Cash</option>
                                        <option value="Card">Card</option>
                                        <option value="Mobile">Mobile</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="text-muted mb-1" style="font-size: 0.7rem;">Paid Amount (LKR)</label>
                                    <input type="number" step="0.01" name="paid_amount" id="paidAmount" class="form-control form-control-sm text-end" required style="height:28px; font-size:0.8rem;">
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-dark-premium w-50 py-1 fw-bold text-uppercase" onclick="clearCart()" style="font-size:0.8rem;">
                                    <i class="fas fa-trash-alt me-1"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-orange-premium flex-grow-1 py-1 fw-bold text-uppercase" style="font-size:0.8rem;">
                                    <i class="fas fa-check-circle me-1"></i> Pay & Checkout
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- Premium Receipt Print Preview Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-receipt-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-invoice-dollar text-success me-2"></i> Checkout Success</h5>
            </div>
            <div class="modal-body p-3" style="max-height: 70vh; overflow-y: auto;">
                
                <!-- Thermal Invoice Card Structure -->
                <div class="thermal-receipt-preview" id="receiptPreview">
                    <div class="text-center">
                        <h2 style="margin: 0; font-weight: 800; font-size: 1.5rem; letter-spacing: 1px;">DXL FASHION</h2>
                        <p style="margin: 5px 0;">123 Style Street, City<br>Phone: 123-456-7890</p>
                        <div class="dashed-line"></div>
                        <h3 style="margin: 5px 0; font-weight: 700; font-size: 1.1rem; letter-spacing: 2px;">TAX INVOICE</h3>
                    </div>
                    
                    <div style="font-size: 12px; line-height: 1.4;">
                        <b>Invoice:</b> <span id="rInvoice">INV-XXXXXX</span><br>
                        <b>Date:</b> <span id="rDate">18 May 2026</span><br>
                        <b>Cashier:</b> <span id="rCashier">Cashier One</span><br>
                        <b>Customer:</b> <span id="rCustomer">Walk-in Customer</span>
                    </div>
                    <div class="dashed-line"></div>
                    
                    <table style="width: 100%;">
                        <thead>
                            <tr style="border-bottom: 1px dashed #000;">
                                <th style="text-align: left; font-weight: 600;">Item</th>
                                <th style="text-align: center; font-weight: 600; width: 40px;">Qty</th>
                                <th style="text-align: right; font-weight: 600; width: 70px;">Price</th>
                                <th style="text-align: right; font-weight: 600; width: 85px;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="rItems">
                            <!-- Items dynamically injected here -->
                        </tbody>
                    </table>
                    
                    <div class="dashed-line"></div>
                    
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="border:none; font-weight: 500;">Subtotal:</td>
                            <td style="border:none; text-align: right;" id="rSubtotal">LKR 0.00</td>
                        </tr>
                        <tr>
                            <td style="border:none; font-weight: 500;">Discount:</td>
                            <td style="border:none; text-align: right;" id="rDiscount">-LKR 0.00</td>
                        </tr>
                        <tr>
                            <td style="border:none; font-weight: 500;">Tax:</td>
                            <td style="border:none; text-align: right;" id="rTax">+LKR 0.00</td>
                        </tr>
                        <tr style="font-size: 14px; font-weight: 800;">
                            <td style="border:none;">Grand Total:</td>
                            <td style="border:none; text-align: right;" id="rTotal">LKR 0.00</td>
                        </tr>
                        <tr>
                            <td style="border:none; font-weight: 500;">Paid (<span id="rMethod">Cash</span>):</td>
                            <td style="border:none; text-align: right;" id="rPaid">LKR 0.00</td>
                        </tr>
                        <tr style="font-weight: 600;">
                            <td style="border:none;">Change Due:</td>
                            <td style="border:none; text-align: right;" id="rBalance">LKR 0.00</td>
                        </tr>
                    </table>
                    
                    <div class="dashed-line"></div>
                    
                    <div class="text-center mt-3 mb-2">
                        <div class="paid-stamp-glow">PAID</div>
                    </div>
                    
                    <div class="text-center" style="font-size: 11px; margin-top: 15px;">
                        <p style="margin: 2px 0;">Thank you for shopping with us!</p>
                        <p style="margin: 2px 0;">No refunds/exchanges without receipt.</p>
                        <p style="margin: 2px 0; font-weight: 700;">DXL Fashion - Be Trendy!</p>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top-0 d-flex gap-2">
                <button type="button" class="btn btn-orange-premium flex-grow-1" onclick="printReceipt()"><i class="fas fa-print me-2"></i> Print Invoice</button>
                <button type="button" class="btn btn-dark-premium px-3" onclick="startNewSale()"><i class="fas fa-plus me-2"></i> New Sale</button>
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal" onclick="startNewSale()"><i class="fas fa-times me-2"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Print utility container (invisible on screen, only prints) -->
<div id="printContainer" class="print-only"></div>

<script>
let cart = [];
let activeCategory = 'all';

function loadProducts(query = '') {
    $.get('ajax_products.php?q=' + encodeURIComponent(query) + '&cat=' + activeCategory, function(data) {
        let html = '';
        if (data.length === 0) {
            html = `<div class="col-12 text-center text-muted py-5"><i class="fas fa-search d-block fs-1 mb-2 opacity-50"></i>No products found matching filters.</div>`;
        } else {
            data.forEach(p => {
                let img = (p.image && p.image !== 'default.png') ? 'assets/images/'+p.image : 'assets/images/default.png';
                // Render circular thumbnail in card with stock count indicator badge
                let stock_badge_class = p.stock_quantity <= p.reorder_level ? 'bg-danger text-white' : 'bg-success text-white';
                let price_formatted = parseFloat(p.selling_price).toFixed(2);
                
                html += `
                <div class="col-md-4 col-sm-6">
                    <div class="pos-item-grid-card" onclick="addToCart(${p.id}, '${escapeHtml(p.product_name)}', ${p.selling_price}, ${p.stock_quantity})">
                        <div class="img-container">
                            <span class="stock-badge ${stock_badge_class}">Stock: ${p.stock_quantity}</span>
                            <img src="${img}" onerror="this.onerror=null; this.src='assets/images/default.png';">
                        </div>
                        <div class="card-body">
                            <h6 class="product-title">${escapeHtml(p.product_name)}</h6>
                            <p class="product-price">Rs. ${price_formatted}</p>
                            ${p.discount_percent > 0 ? `<span class="badge-discount-percent mt-1">-${p.discount_percent}%</span>` : ''}
                        </div>
                    </div>
                </div>`;
            });
        }
        $('#productList').html(html);
    });
}

function selectCategory(catId, element) {
    $('.pos-category-pill').removeClass('active');
    $(element).addClass('active');
    activeCategory = catId;
    loadProducts($('#searchProduct').val());
}

function addToCart(id, name, price, maxStock) {
    let existing = cart.find(i => i.id === id);
    if(existing) {
        if(existing.qty < maxStock) {
            existing.qty++;
        } else {
            alert('Not enough stock available!');
        }
    } else {
        if(maxStock > 0) {
            cart.push({id: id, name: name, price: price, qty: 1, maxStock: maxStock});
        } else {
            alert('This product is out of stock!');
        }
    }
    renderCart();
}

function updateQty(id, change) {
    let item = cart.find(i => i.id === id);
    if(item) {
        let newQty = item.qty + change;
        if(newQty > 0 && newQty <= item.maxStock) {
            item.qty = newQty;
        } else if(newQty <= 0) {
            cart = cart.filter(i => i.id !== id);
        } else {
            alert('Cannot exceed available stock limit!');
        }
        renderCart();
    }
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function renderCart() {
    let html = '';
    let subtotal = 0;
    let itemCount = 0;

    if (cart.length === 0) {
        html = `
        <tr>
            <td colspan="5" class="text-center text-muted py-4">
                <i class="fas fa-shopping-basket d-block fs-3 mb-2 opacity-50"></i>
                Cart is empty. Select items to start sale.
            </td>
        </tr>`;
        $('#cartItems').html(html);
        $('#subtotal').text('LKR 0.00');
        $('#cartCount').text('0 Items');
        calculateTotal(0);
        return;
    }

    cart.forEach(item => {
        let total = item.price * item.qty;
        subtotal += total;
        itemCount += item.qty;
        html += `
        <tr style="border-bottom: 1px solid var(--border-color-dark);">
            <td style="padding: 10px 5px;"><small class="fw-bold text-dark">${item.name}</small></td>
            <td class="text-muted">Rs. ${item.price.toFixed(2)}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-sm btn-dark-premium py-0 px-2" onclick="updateQty(${item.id}, -1)">-</button>
                    <span class="btn btn-sm bg-light text-dark px-2 disabled fw-bold py-0 border" style="border-color: var(--border-color-dark) !important;">${item.qty}</span>
                    <button type="button" class="btn btn-sm btn-dark-premium py-0 px-2" onclick="updateQty(${item.id}, 1)">+</button>
                </div>
            </td>
            <td class="text-end fw-semibold text-primary">Rs. ${total.toFixed(2)}</td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-dark-premium text-danger py-0 px-1 border-0" onclick="removeFromCart(${item.id})"><i class="fas fa-times-circle"></i></button></td>
        </tr>`;
    });
    
    $('#cartItems').html(html);
    $('#subtotal').text('LKR ' + subtotal.toFixed(2));
    $('#cartCount').text(itemCount + (itemCount === 1 ? ' Item' : ' Items'));
    calculateTotal(subtotal);
}

function calculateTotal(sub = null) {
    let subtotal = sub !== null ? sub : parseFloat($('#subtotal').text().replace('LKR ', ''));
    if(isNaN(subtotal)) subtotal = 0;
    
    let discountPercent = parseFloat($('#discount').val()) || 0;
    let taxPercent = parseFloat($('#tax').val()) || 0;
    
    let discountAmount = subtotal * (discountPercent / 100);
    let afterDiscount = subtotal - discountAmount;
    if(afterDiscount < 0) afterDiscount = 0;
    
    let taxAmount = afterDiscount * (taxPercent / 100);
    let grandTotal = afterDiscount + taxAmount;
    
    $('#grandTotal').text('LKR ' + grandTotal.toFixed(2));
    $('#paidAmount').val(grandTotal.toFixed(2)); // Pre-fill paid amount
}

function submitCheckout(e) {
    e.preventDefault();
    if(cart.length === 0) {
        alert("Cart is empty!");
        return;
    }

    let customerId = $('#customerId').val();
    let discount = $('#discount').val();
    let tax = $('#tax').val();
    let paymentMethod = $('#paymentMethod').val();
    let paidAmount = $('#paidAmount').val();

    // Check paid amount validation
    let grandTotal = parseFloat($('#grandTotal').text().replace('LKR ', ''));
    if (parseFloat(paidAmount) < grandTotal) {
        alert("Paid amount is less than the Grand Total!");
        return;
    }

    // Submit checkout via AJAX
    $.ajax({
        type: 'POST',
        url: 'checkout_ajax.php',
        data: {
            cart_data: JSON.stringify(cart),
            customer_id: customerId,
            discount: discount,
            tax: tax,
            payment_method: paymentMethod,
            paid_amount: paidAmount
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Populate Invoice Receipt details
                $('#rInvoice').text(response.invoice_no);
                $('#rDate').text(response.sale_date);
                $('#rCashier').text(response.cashier_name);
                $('#rCustomer').text(response.customer_name);
                $('#rSubtotal').text('LKR ' + parseFloat(response.subtotal).toFixed(2));
                $('#rDiscount').text('-LKR ' + parseFloat(response.discount).toFixed(2));
                $('#rTax').text('+LKR ' + parseFloat(response.tax).toFixed(2));
                $('#rTotal').text('LKR ' + parseFloat(response.total_amount).toFixed(2));
                $('#rPaid').text('LKR ' + parseFloat(response.paid_amount).toFixed(2));
                $('#rBalance').text('LKR ' + parseFloat(response.balance).toFixed(2));
                $('#rMethod').text(response.payment_method);

                // Inject Item rows
                let itemsHtml = '';
                response.items.forEach(item => {
                    itemsHtml += `
                    <tr>
                        <td style="text-align: left; padding: 4px 0;">${item.product_name}</td>
                        <td style="text-align: center; padding: 4px 0;">${item.quantity}</td>
                        <td style="text-align: right; padding: 4px 0;">${parseFloat(item.selling_price).toFixed(2)}</td>
                        <td style="text-align: right; padding: 4px 0;">${parseFloat(item.subtotal).toFixed(2)}</td>
                    </tr>`;
                });
                $('#rItems').html(itemsHtml);

                // Show Success Receipt Modal
                let receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
                receiptModal.show();
            } else {
                alert("Checkout Failed: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert("Error in network communication: " + error);
        }
    });
}

function printReceipt() {
    let receiptContent = document.getElementById('receiptPreview').outerHTML;
    // Set into isolated print container to handle thermal sizing print stylesheet
    $('#printContainer').html(receiptContent);
    document.body.classList.add('receipt-printing');
    window.print();
    setTimeout(() => { document.body.classList.remove('receipt-printing'); }, 1000);
}

function clearCart() {
    if (confirm("Are you sure you want to cancel this sale and clear the cart?")) {
        cart = [];
        renderCart();
        $('#customerId').val('');
        $('#discount').val('0');
        $('#tax').val('0');
        $('#paymentMethod').val('Cash');
        $('#paidAmount').val('');
    }
}

function startNewSale() {
    // Reset Cart
    cart = [];
    renderCart();

    // Reset customer selection
    $('#customerId').val('');
    $('#discount').val('0');
    $('#tax').val('0');
    $('#paymentMethod').val('Cash');
    $('#paidAmount').val('');
    
    // Hide Receipt modal
    bootstrap.Modal.getInstance(document.getElementById('receiptModal')).hide();

    // Reload products to update stocks
    loadProducts();
}

function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

$(document).ready(function() {
    loadProducts();
    
    // Live product search
    $('#searchProduct').on('keyup', function() {
        loadProducts($(this).val());
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
