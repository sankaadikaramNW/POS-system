<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

// Handle Resuming Pending Sale
$resumed_bill = null;
if (isset($_GET['resume'])) {
    $resume_no = $_GET['resume'];
    try {
        $pdo->beginTransaction();
        // Prevent completed or cancelled bills from being resumed
        $stmt = $pdo->prepare("SELECT * FROM pending_sales WHERE hold_bill_no = ? AND status = 'PENDING'");
        $stmt->execute([$resume_no]);
        $resumed_bill = $stmt->fetch();
        
        if ($resumed_bill) {
            // Update resumed_at and updated_at
            $upd = $pdo->prepare("UPDATE pending_sales SET resumed_at = NOW(), updated_at = NOW() WHERE hold_bill_no = ?");
            $upd->execute([$resume_no]);

            // Add Audit Trail Log: Bill Resumed
            $user_id = $_SESSION['user_id'] ?? null;
            $username = $_SESSION['username'] ?? 'unknown';
            
            $log = $pdo->prepare("INSERT INTO pending_sales_logs (user_id, username, action, log_date, log_time, bill_no) VALUES (?, ?, 'Bill Resumed', CURDATE(), CURTIME(), ?)");
            $log->execute([$user_id, $username, $resume_no]);
            
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

require_once 'includes/header.php';

$customers = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
?>

<!-- Load jQuery Early to Ensure Inline Script works perfectly -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<!-- Premium POS Screen Wrapper â€” Full Viewport -->
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
            
            <!-- Category Scrollable Filter Pills â€” SINGLE LINE -->
            <div class="d-flex overflow-auto mb-2 gap-2 align-items-center" style="white-space: nowrap; flex-shrink:0; padding-bottom: 8px;">
                <a href="javascript:void(0)" class="pos-category-pill active" data-id="all" onclick="selectCategory('all', this)">All Products</a>
                <?php foreach($categories as $cat): ?>
                    <a href="javascript:void(0)" class="pos-category-pill" data-id="<?= $cat['id'] ?>" onclick="selectCategory(<?= $cat['id'] ?>, this)">
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Products Loaded dynamically via AJAX â€” scrolls internally -->
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
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-dark-premium py-1 px-2 border" id="btnHeldBills" onclick="openHeldBillsModal()" style="font-size:0.75rem; border-color: var(--border-color-dark) !important; display: inline-flex; align-items: center;">
                            <i class="fas fa-hourglass-half me-1"></i> Held Bills (<span id="heldBillsCount">0</span>)
                        </button>
                        <span class="badge bg-dark-premium text-white px-2 py-1 border" style="border-color: var(--border-color-dark) !important; font-size:0.75rem;" id="cartCount">0 Items</span>
                    </div>
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

                    <!-- Sale Notes / Reference -->
                    <div class="mb-2" style="flex-shrink:0;">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.72rem;">Sale Notes / Reference</label>
                        <input type="text" id="saleNotes" class="form-control form-control-sm" placeholder="Add note or reference..." style="height:32px; font-size:0.8rem;">
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

                    <!-- Billing Calculations Footer Card â€” always visible -->
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
                            <input type="hidden" name="resumed_hold_bill_no" id="resumedHoldBillNo" value="">
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
                                <button type="button" class="btn btn-dark-premium py-1 fw-bold text-uppercase" id="btnCancelTransaction" onclick="cancelPOSTransaction()" style="font-size:0.8rem; width: 25%;">
                                    <i class="fas fa-trash-alt me-1"></i> Cancel
                                </button>
                                <button type="button" class="btn btn-hold-premium py-1 fw-bold text-uppercase" onclick="holdCurrentBill()" style="font-size:0.8rem; width: 35%;">
                                    <i class="fas fa-pause-circle me-1"></i> Hold
                                </button>
                                <button type="submit" class="btn btn-orange-premium py-1 fw-bold text-uppercase" style="font-size:0.8rem; width: 40%;">
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
                        <div class="d-inline-block mb-1" style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; border: 1.5px solid #000; background-color: #000; vertical-align: middle;">
                            <img src="assets/images/PSX_20260519_122008.jpg" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; filter: grayscale(1) contrast(1.3); transform: scale(1.22); transform-origin: center center;">
                        </div>
                        <h2 style="margin: 5px 0 0 0; font-weight: 800; font-size: 1.35rem; letter-spacing: 1px;">DXL FASHION</h2>
                        <p style="margin: 3px 0 5px 0;">123 Style Street, City<br>Phone: 123-456-7890</p>
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

<!-- Premium Held Bills Modal -->
<div class="modal fade" id="heldBillsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-receipt-modal" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.15) !important; background: #ffffff !important;">
            <div class="modal-header border-0 bg-primary text-white py-3 d-flex align-items-center justify-content-between">
                <h5 class="modal-title fw-bold m-0 text-white"><i class="fas fa-hourglass-half me-2"></i> Held / Pending Bills</h5>
                <button type="button" class="btn-close btn-close-white m-0" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0) invert(1); opacity: 0.8;"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 65vh; overflow-y: auto;">
                <div id="heldBillsListContainer">
                    <!-- Dynamic Held Bills List Table Injected Here -->
                </div>
            </div>
            <div class="modal-footer border-top-0 d-flex gap-2">
                <button type="button" class="btn btn-dark-premium px-3 w-100" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     POS Cancel Transaction Confirmation Modal
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="modal fade" id="posCancelConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="posCancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 12px 40px rgba(239,68,68,0.18);">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="text-center w-100">
                    <div style="width:56px; height:56px; border-radius:50%; background:rgba(239,68,68,0.1); display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                        <i class="fas fa-exclamation-triangle text-danger" style="font-size:1.5rem;"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark" id="posCancelModalLabel">Cancel Transaction?</h5>
                </div>
            </div>
            <div class="modal-body text-center px-4 pt-2 pb-1">
                <p class="text-muted small mb-0" id="cancelModalSubtext">All items in the current cart will be removed.</p>
            </div>
            <div class="modal-footer border-0 d-flex gap-2 px-4 pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary flex-grow-1 py-2 fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;">
                    <i class="fas fa-arrow-left me-1"></i> Keep Sale
                </button>
                <button type="button" class="btn btn-danger flex-grow-1 py-2 fw-bold" id="btnConfirmCancelPOS" onclick="confirmCancelPOSTransaction()" style="border-radius:10px;">
                    <i class="fas fa-trash-alt me-1"></i> Yes, Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
     Held Bill Discard Confirmation Modal
     â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
<div class="modal fade" id="heldBillDiscardModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 12px 40px rgba(239,68,68,0.18);">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="text-center w-100">
                    <div style="width:56px; height:56px; border-radius:50%; background:rgba(239,68,68,0.1); display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                        <i class="fas fa-ban text-danger" style="font-size:1.5rem;"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark">Discard Held Bill?</h5>
                </div>
            </div>
            <div class="modal-body text-center px-4 pt-2 pb-1">
                <p class="text-muted small mb-0">Hold bill <strong id="heldBillDiscardNo">â€”</strong> will be permanently cancelled and logged in the audit trail.</p>
            </div>
            <div class="modal-footer border-0 d-flex gap-2 px-4 pb-4 pt-3">
                <button type="button" class="btn btn-outline-secondary flex-grow-1 py-2 fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;">
                    <i class="fas fa-times me-1"></i> Keep Bill
                </button>
                <button type="button" class="btn btn-danger flex-grow-1 py-2 fw-bold" id="btnConfirmDiscardHeld" style="border-radius:10px;">
                    <i class="fas fa-trash-alt me-1"></i> Yes, Discard
                </button>
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
    let existing = cart.find(i => Number(i.id) === Number(id));
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
    let item = cart.find(i => Number(i.id) === Number(id));
    if(item) {
        let newQty = item.qty + change;
        if(newQty > 0 && newQty <= item.maxStock) {
            item.qty = newQty;
        } else if(newQty <= 0) {
            cart = cart.filter(i => Number(i.id) !== Number(id));
        } else {
            alert('Cannot exceed available stock limit!');
        }
        renderCart();
    }
}

function removeFromCart(id) {
    cart = cart.filter(i => Number(i.id) !== Number(id));
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
        // Normalize types -- after a DB recall all values come back as strings
        let itemId    = Number(item.id);
        let itemPrice = parseFloat(item.price) || 0;
        let itemQty   = parseInt(item.qty)     || 1;
        let total = itemPrice * itemQty;
        subtotal += total;
        itemCount += itemQty;
        html += `
        <tr style="border-bottom: 1px solid var(--border-color-dark);">
            <td style="padding: 10px 5px;"><small class="fw-bold text-dark">${item.name}</small></td>
            <td class="text-muted">Rs. ${itemPrice.toFixed(2)}</td>
            <td class="text-center">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-sm btn-dark-premium py-0 px-2" onclick="updateQty(${itemId}, -1)">-</button>
                    <span class="btn btn-sm bg-light text-dark px-2 disabled fw-bold py-0 border" style="border-color: var(--border-color-dark) !important;">${itemQty}</span>
                    <button type="button" class="btn btn-sm btn-dark-premium py-0 px-2" onclick="updateQty(${itemId}, 1)">+</button>
                </div>
            </td>
            <td class="text-end fw-semibold text-primary">Rs. ${total.toFixed(2)}</td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-dark-premium text-danger py-0 px-1 border-0" onclick="removeFromCart(${itemId})"><i class="fas fa-times-circle"></i></button></td>
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
            paid_amount: paidAmount,
            resumed_hold_bill_no: $('#resumedHoldBillNo').val()
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

// â”€â”€ Cancel / Clear Cart â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let _cancelInProgress = false; // idempotency guard

/**
 * Entry-point: shows the Bootstrap confirmation modal.
 * Only opens the modal if the cart actually has items.
 */
function cancelPOSTransaction() {
    if (_cancelInProgress) return; // already processing

    const resumedBillNo = $('#resumedHoldBillNo').val().trim();
    const hasItems = cart.length > 0;

    // Update modal sub-message depending on context
    if (resumedBillNo) {
        $('#cancelModalSubtext').text(
            `Hold bill ${resumedBillNo} will be returned to the pending queue so it can be recalled later.`
        );
    } else if (hasItems) {
        $('#cancelModalSubtext').text('All items in the current cart will be removed.');
    } else {
        // Nothing in cart â€” just reset fields silently
        resetPOSFields();
        return;
    }

    // Show the confirm modal
    let modalEl  = document.getElementById('posCancelConfirmModal');
    let modalInst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modalInst.show();
}

/**
 * Called when the user clicks "Yes, Cancel" in the confirmation modal.
 * Handles both plain new-bill cancellations and resumed-hold-bill reverts.
 */
function confirmCancelPOSTransaction() {
    if (_cancelInProgress) return;
    _cancelInProgress = true;

    // Disable button to prevent double-fire
    $('#btnConfirmCancelPOS').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Cancelling...');

    const resumedBillNo = $('#resumedHoldBillNo').val().trim();

    // Close the confirmation modal first
    let modalEl   = document.getElementById('posCancelConfirmModal');
    let modalInst = bootstrap.Modal.getInstance(modalEl);
    if (modalInst) modalInst.hide();

    if (resumedBillNo) {
        // Resumed hold bill â€” revert it back to PENDING in DB
        $.ajax({
            type: 'POST',
            url: 'api/pending_sales_api.php',
            data: { action: 'revert_resume', hold_bill_no: resumedBillNo },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    // Non-fatal: log it but still clear the cart
                    console.warn('[POS Cancel] revert_resume failed:', response.message);
                }
                resetPOSFields();
                updateHeldCount();
                loadProducts(); // refresh stock badges
                showPOSToast(
                    response.success
                        ? `Hold bill ${resumedBillNo} returned to pending queue.`
                        : 'Cart cleared (hold bill revert encountered an issue â€” check logs).',
                    response.success ? 'success' : 'warning'
                );
            },
            error: function(xhr, status, err) {
                console.error('[POS Cancel] revert_resume network error:', err);
                resetPOSFields(); // still clear cart even if API fails
                loadProducts();
                showPOSToast('Cart cleared (network error during hold-bill revert).', 'warning');
            },
            complete: function() {
                _cancelInProgress = false;
                $('#btnConfirmCancelPOS').prop('disabled', false).html('<i class="fas fa-trash-alt me-1"></i> Yes, Cancel');
            }
        });
    } else {
        // Plain new bill â€” just reset locally
        resetPOSFields();
        loadProducts(); // refresh stock badges
        showPOSToast('Transaction cancelled. Cart cleared.', 'success');
        _cancelInProgress = false;
        $('#btnConfirmCancelPOS').prop('disabled', false).html('<i class="fas fa-trash-alt me-1"></i> Yes, Cancel');
    }
}

/**
 * Resets all POS fields and cart state without any confirmation.
 * Safe to call directly after a successful checkout.
 */
function resetPOSFields() {
    cart = [];
    renderCart();
    $('#customerId').val('');
    $('#discount').val('0');
    $('#tax').val('0');
    $('#paymentMethod').val('Cash');
    $('#paidAmount').val('');
    $('#saleNotes').val('');
    $('#resumedHoldBillNo').val('');
}

// Legacy alias kept so any old inline calls still work
function clearCart() { cancelPOSTransaction(); }

function startNewSale() {
    resetPOSFields();
    updateHeldCount();

    // Hide Receipt modal
    const receiptModalEl = document.getElementById('receiptModal');
    const receiptModal   = bootstrap.Modal.getInstance(receiptModalEl);
    if (receiptModal) receiptModal.hide();

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

const resumedBillData = <?php echo $resumed_bill ? json_encode($resumed_bill) : 'null'; ?>;

/* ==========================================================================
   HELD/WAITING BILLS JAVASCRIPT ENGINE (DATABASE INTEGRATED)
   ========================================================================== */

// Update the dynamic counter and pulse animation in the header
function updateHeldCount() {
    $.ajax({
        type: 'GET',
        url: 'api/pending_sales_api.php',
        data: { action: 'count' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const count = response.count;
                $('#heldBillsCount').text(count);
                
                const btn = $('#btnHeldBills');
                if (count > 0) {
                    btn.addClass('pulse-held-active');
                    btn.removeClass('btn-dark-premium').addClass('btn-warning');
                    btn.css({
                        'background-color': '#f59e0b',
                        'border-color': '#f59e0b',
                        'color': 'white'
                    });
                } else {
                    btn.removeClass('pulse-held-active btn-warning').addClass('btn-dark-premium');
                    btn.css({
                        'background-color': '',
                        'border-color': '',
                        'color': ''
                    });
                }
            }
        }
    });
}

// Hold Current Bill
function holdCurrentBill() {
    if (cart.length === 0) {
        alert("Cart is empty! There is no bill to hold.");
        return;
    }

    // Get notes / reference
    let notes = $('#saleNotes').val().trim();
    if (!notes) {
        // Generate default reference if empty
        const customerSelect = document.getElementById('customerId');
        let customerName = "Walk-in Customer";
        if (customerSelect && customerSelect.value) {
            customerName = customerSelect.options[customerSelect.selectedIndex].text.split('(')[0].trim();
        }
        const now = new Date();
        const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        notes = `${customerName} (${timeString})`;
    }

    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const discountPercent = parseFloat($('#discount').val()) || 0;
    const taxPercent = parseFloat($('#tax').val()) || 0;
    
    const discountAmount = subtotal * (discountPercent / 100);
    const afterDiscount = subtotal - discountAmount;
    const taxAmount = afterDiscount * (taxPercent / 100);
    const grandTotal = afterDiscount + taxAmount;

    const customerSelect = document.getElementById('customerId');
    let customerName = "Walk-in Customer";
    if (customerSelect && customerSelect.value) {
        customerName = customerSelect.options[customerSelect.selectedIndex].text.split('(')[0].trim();
    }

    // Save using API
    $.ajax({
        type: 'POST',
        url: 'api/pending_sales_api.php?action=hold',
        data: {
            cart_data: JSON.stringify(cart),
            customer_id: $('#customerId').val(),
            customer_name: customerName,
            subtotal: subtotal,
            discount_amount: discountAmount,
            tax_amount: taxAmount,
            grand_total: grandTotal,
            notes: notes
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Reset the POS cart and fields instantly
                cart = [];
                renderCart();
                $('#customerId').val('');
                $('#discount').val('0');
                $('#tax').val('0');
                $('#paymentMethod').val('Cash');
                $('#paidAmount').val('');
                $('#saleNotes').val('');
                $('#resumedHoldBillNo').val('');
                
                // Update visual count
                updateHeldCount();
                
                alert("Bill placed on hold successfully!\nHold Bill No: " + response.hold_bill_no);
            } else {
                alert("Failed to hold bill: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert("Error placing bill on hold: " + error);
        }
    });
}

// Open Held Bills Modal
function openHeldBillsModal() {
    // Show Modal Early so loader is seen
    let heldBillsModalObj = document.getElementById('heldBillsModal');
    let heldBillsModal = bootstrap.Modal.getInstance(heldBillsModalObj);
    if (!heldBillsModal) {
        heldBillsModal = new bootstrap.Modal(heldBillsModalObj);
    }
    heldBillsModal.show();

    loadHeldBillsList();
}

// Reload lists without re-initializing modal triggers
function loadHeldBillsList() {
    const container = $('#heldBillsListContainer');
    container.html(`
        <div class="text-center py-5 text-muted">
            <i class="fas fa-spinner fa-spin fs-1 mb-3 text-warning"></i>
            <p class="small mb-0">Loading held bills from database...</p>
        </div>
    `);

    $.ajax({
        type: 'GET',
        url: 'api/pending_sales_api.php',
        data: { action: 'list', limit: 50 }, // Fetch up to 50 active pending sales
        dataType: 'json',
        success: function(response) {
            if (response.success && response.bills && response.bills.length > 0) {
                let html = `
                    <div class="table-responsive">
                        <table class="table table-hover align-middle held-bills-table m-0">
                            <thead>
                                <tr>
                                    <th>Hold Bill No</th>
                                    <th>Customer / Notes</th>
                                    <th>Time Held</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-center" style="width: 170px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                response.bills.forEach(bill => {
                    let cartData = [];
                    try {
                        cartData = JSON.parse(bill.cart_data_json);
                    } catch(e){}
                    const itemCount = cartData.reduce((sum, i) => sum + i.qty, 0);
                    
                    // Style badge elapsed time
                    let minutes = parseInt(bill.minutes_elapsed) || 0;
                    let waitClass = "bg-success";
                    if (minutes > 30) {
                        waitClass = "bg-danger";
                    } else if (minutes > 15) {
                        waitClass = "bg-warning text-dark";
                    }

                    // Format creation time nicely
                    let dateStr = new Date(bill.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    
                    html += `
                        <tr>
                            <td><strong class="text-dark">${bill.hold_bill_no}</strong></td>
                            <td>
                                <span class="d-block fw-semibold text-secondary small">${escapeHtml(bill.customer_name)}</span>
                                <span class="text-muted text-truncate d-inline-block small" style="max-width: 150px;" title="${escapeHtml(bill.notes || '')}">${escapeHtml(bill.notes || 'No notes')}</span>
                            </td>
                            <td>
                                <span class="text-muted small">${dateStr}</span>
                                <span class="badge ${waitClass} ms-1 small" style="font-size:0.68rem;">${minutes}m ago</span>
                            </td>
                            <td class="text-end fw-bold text-primary">Rs. ${parseFloat(bill.grand_total).toFixed(2)}</td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-primary py-1 px-2 d-flex align-items-center" onclick="recallHeldBill('${bill.hold_bill_no}')" title="Recall & Checkout">
                                        <i class="fas fa-folder-open me-1"></i> Recall
                                    </button>
                                    <button class="btn btn-sm btn-danger py-1 px-2 d-flex align-items-center" onclick="deleteHeldBill('${bill.hold_bill_no}')" title="Discard Bill">
                                        <i class="fas fa-trash-alt me-1"></i> Discard
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                container.html(html);
            } else {
                container.html(`
                    <div class="text-center py-5 text-muted held-bills-empty-state">
                        <i class="fas fa-hourglass-half d-block fs-1 mb-3 opacity-50 text-warning"></i>
                        <h5 class="fw-bold text-dark mb-1">No Bills on Hold</h5>
                        <p class="small mb-0">Put active bills on hold when a customer needs to step away.</p>
                    </div>
                `);
            }
        },
        error: function() {
            container.html(`
                <div class="alert alert-danger m-3 small">
                    <i class="fas fa-exclamation-triangle me-1"></i> Failed to retrieve held bills.
                </div>
            `);
        }
    });
}

// Recall Held Bill
function recallHeldBill(holdBillNo) {
    if (cart.length > 0) {
        const choice = confirm("You currently have items in the active cart. Do you want to put the CURRENT cart on hold first, then recall the selected bill?\n(Click Cancel to overwrite active cart instead.)");
        
        if (choice) {
            // Auto hold current first
            let notes = $('#saleNotes').val().trim();
            if (!notes) {
                const customerSelect = document.getElementById('customerId');
                let customerName = "Walk-in Customer";
                if (customerSelect && customerSelect.value) {
                    customerName = customerSelect.options[customerSelect.selectedIndex].text.split('(')[0].trim();
                }
                const now = new Date();
                const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                notes = `${customerName} - Auto Hold (${timeString})`;
            }

            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const discountPercent = parseFloat($('#discount').val()) || 0;
            const taxPercent = parseFloat($('#tax').val()) || 0;
            
            const discountAmount = subtotal * (discountPercent / 100);
            const afterDiscount = subtotal - discountAmount;
            const taxAmount = afterDiscount * (taxPercent / 100);
            const grandTotal = afterDiscount + taxAmount;

            const customerSelect = document.getElementById('customerId');
            let customerName = "Walk-in Customer";
            if (customerSelect && customerSelect.value) {
                customerName = customerSelect.options[customerSelect.selectedIndex].text.split('(')[0].trim();
            }

            // Sync ajax call to ensure sequential holds
            $.ajax({
                type: 'POST',
                url: 'api/pending_sales_api.php?action=hold',
                async: false,
                data: {
                    cart_data: JSON.stringify(cart),
                    customer_id: $('#customerId').val(),
                    customer_name: customerName,
                    subtotal: subtotal,
                    discount_amount: discountAmount,
                    tax_amount: taxAmount,
                    grand_total: grandTotal,
                    notes: notes
                }
            });
        }
    }

    // Call API to resume and get details
    $.ajax({
        type: 'GET',
        url: 'api/pending_sales_api.php',
        data: { action: 'resume', hold_bill_no: holdBillNo },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const bill = response.bill;
                
                let rawCart = [];
                try {
                    rawCart = JSON.parse(bill.cart_data_json);
                } catch(e) { rawCart = []; }

                // â”€â”€ Re-validate stock from DB before loading recalled cart â”€â”€
                // This prevents stale maxStock from hold time causing over-sell
                $.ajax({
                    type: 'POST',
                    url: 'api/stock_check.php',
                    data: { items: JSON.stringify(rawCart.map(i => ({ id: i.id, qty: i.qty }))) },
                    dataType: 'json',
                    success: function(stockResp) {
                        let stockMap = {};
                        if (stockResp.success && stockResp.items) {
                            stockResp.items.forEach(s => { stockMap[s.id] = s; });
                        }

                        let warnings = [];
                        cart = rawCart.map(i => {
                            let itemId    = Number(i.id);
                            let itemQty   = parseInt(i.qty)   || 1;
                            let itemPrice = parseFloat(i.price) || 0;
                            let liveStock = stockMap[itemId] ? stockMap[itemId].current_stock : parseInt(i.maxStock) || 999;
                            let finalQty  = itemQty;

                            if (stockMap[itemId] && !stockMap[itemId].sufficient) {
                                // Cap qty to available
                                finalQty = liveStock;
                                if (finalQty === 0) {
                                    warnings.push(`âš  "${escapeHtml(i.name)}" is now OUT OF STOCK (removed from cart).`);
                                    return null; // Remove from cart
                                } else {
                                    warnings.push(`âš  "${escapeHtml(i.name)}": reduced from ${itemQty} to ${finalQty} (current stock: ${liveStock}).`);
                                }
                            }

                            if (!stockMap[itemId] || !stockMap[itemId].product_exists) {
                                warnings.push(`âš  "${escapeHtml(i.name)}" no longer exists in the database â€” removed from cart.`);
                                return null;
                            }

                            return {
                                id:       itemId,
                                name:     i.name,
                                price:    itemPrice,
                                qty:      finalQty,
                                maxStock: liveStock
                            };
                        }).filter(i => i !== null && i.qty > 0);

                        $('#resumedHoldBillNo').val(bill.hold_bill_no);
                        $('#customerId').val(bill.customer_id || '');
                        $('#saleNotes').val(bill.notes || '');

                        // Restore discount/tax percentages
                        let sub = parseFloat(bill.subtotal) || 0;
                        let discAmt = parseFloat(bill.discount_amount) || 0;
                        let taxAmt  = parseFloat(bill.tax_amount) || 0;
                        let discP = sub > 0 ? Math.round((discAmt / sub) * 100) : 0;
                        let afterD = sub - discAmt;
                        let taxP = afterD > 0 ? Math.round((taxAmt / afterD) * 100) : 0;
                        $('#discount').val(discP);
                        $('#tax').val(taxP);

                        renderCart();
                        updateHeldCount();

                        // Close held bills modal
                        const modalEl = document.getElementById('heldBillsModal');
                        const modalInst = bootstrap.Modal.getInstance(modalEl);
                        if (modalInst) modalInst.hide();

                        if (warnings.length > 0) {
                            alert("Hold bill " + holdBillNo + " recalled.\n\nStock changes detected since hold:\n" + warnings.join("\n"));
                        } else {
                            alert("Hold bill " + holdBillNo + " recalled successfully!");
                        }
                    },
                    error: function() {
                        // Fallback: load cart with stored maxStock (graceful degradation)
                        cart = rawCart.map(i => ({
                            id:       Number(i.id),
                            name:     i.name,
                            price:    parseFloat(i.price)  || 0,
                            qty:      parseInt(i.qty)      || 1,
                            maxStock: parseInt(i.maxStock) || 999
                        }));
                        $('#resumedHoldBillNo').val(bill.hold_bill_no);
                        $('#customerId').val(bill.customer_id || '');
                        $('#saleNotes').val(bill.notes || '');
                        renderCart();
                        updateHeldCount();
                        const modalEl = document.getElementById('heldBillsModal');
                        const modalInst = bootstrap.Modal.getInstance(modalEl);
                        if (modalInst) modalInst.hide();
                        alert("Hold bill " + holdBillNo + " recalled (stock check unavailable).");
                    }
                });
            } else {
                alert("Failed to recall bill: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert("Error resuming held bill: " + error);
        }
    });
}

// Discard Held Bill â€” from the modal list inside POS
let _deletingBill = false; // idempotency guard

function deleteHeldBill(holdBillNo) {
    if (_deletingBill) return;

    // Use Bootstrap modal confirmation
    $('#heldBillDiscardNo').text(holdBillNo);
    let confirmEl   = document.getElementById('heldBillDiscardModal');
    let confirmInst = bootstrap.Modal.getInstance(confirmEl) || new bootstrap.Modal(confirmEl);

    // Wire confirm button (unbind first to prevent stacking)
    $('#btnConfirmDiscardHeld').off('click').on('click', function() {
        if (_deletingBill) return;
        _deletingBill = true;

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Discarding...');
        confirmInst.hide();

        $.ajax({
            type: 'POST',
            url: 'api/pending_sales_api.php',
            data: { action: 'cancel', hold_bill_no: holdBillNo },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateHeldCount();
                    loadHeldBillsList();
                    showPOSToast('Hold bill ' + holdBillNo + ' discarded.', 'success');
                } else {
                    showPOSToast('Failed to discard: ' + response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                showPOSToast('Network error while discarding bill: ' + error, 'danger');
            },
            complete: function() {
                _deletingBill = false;
                $btn.prop('disabled', false).html('<i class="fas fa-trash-alt me-1"></i> Yes, Discard');
            }
        });
    });

    confirmInst.show();
}

$(document).ready(function() {
    loadProducts();
    updateHeldCount();
    
    // Live product search
    $('#searchProduct').on('keyup', function() {
        loadProducts($(this).val());
    });

    // Check for inline injected resumed bill data
    if (resumedBillData) {
        try {
            let rawCart = JSON.parse(resumedBillData.cart_data_json);

            // â”€â”€ Validate recalled cart stock from DB before restoring â”€â”€
            $.ajax({
                type: 'POST',
                url: 'api/stock_check.php',
                data: { items: JSON.stringify(rawCart.map(i => ({ id: i.id, qty: i.qty }))) },
                dataType: 'json',
                success: function(stockResp) {
                    let stockMap = {};
                    if (stockResp.success && stockResp.items) {
                        stockResp.items.forEach(s => { stockMap[s.id] = s; });
                    }

                    let warnings = [];
                    cart = rawCart.map(i => {
                        let itemId    = Number(i.id);
                        let itemQty   = parseInt(i.qty)    || 1;
                        let itemPrice = parseFloat(i.price) || 0;
                        let liveStock = stockMap[itemId] ? stockMap[itemId].current_stock : parseInt(i.maxStock) || 999;
                        let finalQty  = itemQty;

                        if (stockMap[itemId] && !stockMap[itemId].sufficient) {
                            finalQty = liveStock;
                            if (finalQty === 0) {
                                warnings.push(`âš  "${escapeHtml(i.name)}" is now OUT OF STOCK â€” removed.`);
                                return null;
                            } else {
                                warnings.push(`âš  "${escapeHtml(i.name)}": qty reduced ${itemQty}â†’${finalQty} (stock: ${liveStock}).`);
                            }
                        }

                        if (!stockMap[itemId] || !stockMap[itemId].product_exists) {
                            warnings.push(`âš  "${escapeHtml(i.name)}" no longer exists â€” removed.`);
                            return null;
                        }

                        return {
                            id:       itemId,
                            name:     i.name,
                            price:    itemPrice,
                            qty:      finalQty,
                            maxStock: liveStock
                        };
                    }).filter(i => i !== null && i.qty > 0);

                    $('#resumedHoldBillNo').val(resumedBillData.hold_bill_no);
                    $('#customerId').val(resumedBillData.customer_id || '');

                    let sub    = parseFloat(resumedBillData.subtotal)       || 0;
                    let discAmt= parseFloat(resumedBillData.discount_amount) || 0;
                    let taxAmt = parseFloat(resumedBillData.tax_amount)      || 0;
                    let discP  = sub > 0 ? Math.round((discAmt / sub) * 100) : 0;
                    let afterD = sub - discAmt;
                    let taxP   = afterD > 0 ? Math.round((taxAmt / afterD) * 100) : 0;
                    $('#discount').val(discP);
                    $('#tax').val(taxP);
                    $('#saleNotes').val(resumedBillData.notes || '');

                    renderCart();

                    if (warnings.length > 0) {
                        alert('Resumed Hold Bill ' + resumedBillData.hold_bill_no + '.\n\nStock changes since hold:\n' + warnings.join('\n'));
                    } else {
                        alert('Resumed Hold Bill ' + resumedBillData.hold_bill_no + ' successfully!');
                    }
                },
                error: function() {
                    // Fallback if stock_check.php unavailable
                    cart = rawCart.map(i => ({
                        id:       Number(i.id),
                        name:     i.name,
                        price:    parseFloat(i.price)  || 0,
                        qty:      parseInt(i.qty)      || 1,
                        maxStock: parseInt(i.maxStock) || 999
                    }));
                    $('#resumedHoldBillNo').val(resumedBillData.hold_bill_no);
                    $('#customerId').val(resumedBillData.customer_id || '');
                    let sub    = parseFloat(resumedBillData.subtotal)       || 0;
                    let discAmt= parseFloat(resumedBillData.discount_amount) || 0;
                    let taxAmt = parseFloat(resumedBillData.tax_amount)      || 0;
                    let discP  = sub > 0 ? Math.round((discAmt / sub) * 100) : 0;
                    let afterD = sub - discAmt;
                    let taxP   = afterD > 0 ? Math.round((taxAmt / afterD) * 100) : 0;
                    $('#discount').val(discP);
                    $('#tax').val(taxP);
                    $('#saleNotes').val(resumedBillData.notes || '');
                    renderCart();
                    alert('Resumed Hold Bill ' + resumedBillData.hold_bill_no + ' (stock check unavailable).');
                }
            });
        } catch (e) {
            console.error("Error resuming bill:", e);
        }
    }
});

// â”€â”€ Toast Notification Helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function showPOSToast(message, type = 'success') {
    const toastId = 'posToast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' :
                    type === 'warning' ? 'bg-warning text-dark' :
                    type === 'danger'  ? 'bg-danger' : 'bg-primary';
    const icon    = type === 'success' ? 'fa-check-circle' :
                    type === 'warning' ? 'fa-exclamation-triangle' :
                    type === 'danger'  ? 'fa-times-circle' : 'fa-info-circle';

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white border-0 ${bgClass}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body fw-semibold" style="font-size:0.88rem;">
                    <i class="fas ${icon} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;

    // Ensure container exists
    if (!$('#posToastContainer').length) {
        $('body').append('<div id="posToastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:9999;"></div>');
    }

    $('#posToastContainer').append(toastHtml);
    const toastEl   = document.getElementById(toastId);
    const toastInst = new bootstrap.Toast(toastEl);
    toastInst.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}
</script>

<?php require_once 'includes/footer.php'; ?>


