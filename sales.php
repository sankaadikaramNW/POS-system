<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$customers = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name")->fetchAll();
?>

<div class="row h-100">
    <!-- Product Selection Area -->
    <div class="col-md-7 border-end">
        <div class="mb-3">
            <input type="text" id="searchProduct" class="form-control form-control-lg" placeholder="Search by name or scan barcode...">
        </div>
        
        <div class="row g-3" id="productList" style="height: 70vh; overflow-y: auto;">
            <!-- Products loaded via AJAX -->
        </div>
    </div>

    <!-- Cart Area -->
    <div class="col-md-5 d-flex flex-column" style="height: 80vh;">
        <h4 class="mb-3 border-bottom pb-2">Current Sale</h4>
        
        <div class="mb-3">
            <select id="customerId" class="form-select">
                <option value="">Walk-in Customer</option>
                <?php foreach($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['customer_name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex-grow-1 overflow-auto" style="min-height: 200px;">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="cartItems">
                    <!-- Cart items injected here -->
                </tbody>
            </table>
        </div>

        <div class="mt-auto bg-light p-3 rounded">
            <div class="d-flex justify-content-between mb-2">
                <span>Subtotal:</span>
                <span id="subtotal">$0.00</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Discount ($):</span>
                <input type="number" id="discount" class="form-control form-control-sm text-end" style="width: 100px;" value="0" min="0" onchange="calculateTotal()">
            </div>
            <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                <span>Tax (%):</span>
                <input type="number" id="tax" class="form-control form-control-sm text-end" style="width: 100px;" value="0" min="0" onchange="calculateTotal()">
            </div>
            <div class="d-flex justify-content-between mb-3">
                <h4 class="fw-bold m-0">Total:</h4>
                <h4 class="fw-bold m-0" id="grandTotal">$0.00</h4>
            </div>
            
            <form id="checkoutForm" action="checkout.php" method="POST">
                <input type="hidden" name="cart_data" id="cartData">
                <input type="hidden" name="customer_id" id="formCustomerId">
                <input type="hidden" name="discount" id="formDiscount">
                <input type="hidden" name="tax" id="formTax">
                
                <div class="mb-3">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="Mobile">Mobile Payment</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label>Paid Amount</label>
                    <input type="number" step="0.01" name="paid_amount" id="paidAmount" class="form-control" required>
                </div>
                
                <button type="button" class="btn btn-success w-100 btn-lg" onclick="processCheckout()">
                    <i class="fas fa-check-circle"></i> Pay & Checkout
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let cart = [];

function loadProducts(query = '') {
    $.get('ajax_products.php?q=' + query, function(data) {
        let html = '';
        data.forEach(p => {
            let img = p.image != 'default.png' ? 'assets/images/'+p.image : 'https://via.placeholder.com/150?text=No+Image';
            html += `
            <div class="col-md-4 col-sm-6">
                <div class="card h-100 pos-product-card" onclick="addToCart(${p.id}, '${p.product_name}', ${p.selling_price}, ${p.stock_quantity})">
                    <img src="${img}" class="card-img-top" style="height:120px; object-fit:cover;">
                    <div class="card-body p-2 text-center">
                        <h6 class="card-title mb-1 text-truncate">${p.product_name}</h6>
                        <p class="card-text fw-bold text-primary mb-1">$${p.selling_price}</p>
                        <small class="text-muted">Stock: ${p.stock_quantity}</small>
                    </div>
                </div>
            </div>`;
        });
        $('#productList').html(html);
    });
}

function addToCart(id, name, price, maxStock) {
    let existing = cart.find(i => i.id === id);
    if(existing) {
        if(existing.qty < maxStock) {
            existing.qty++;
        } else {
            alert('Not enough stock!');
        }
    } else {
        if(maxStock > 0) {
            cart.push({id: id, name: name, price: price, qty: 1, maxStock: maxStock});
        } else {
            alert('Out of stock!');
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
            alert('Cannot exceed stock!');
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
    cart.forEach(item => {
        let total = item.price * item.qty;
        subtotal += total;
        html += `
        <tr>
            <td><small>${item.name}</small></td>
            <td>$${item.price}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="updateQty(${item.id}, -1)">-</button>
                    <span class="btn px-2 disabled border">${item.qty}</span>
                    <button class="btn btn-outline-secondary" onclick="updateQty(${item.id}, 1)">+</button>
                </div>
            </td>
            <td>$${total.toFixed(2)}</td>
            <td><button class="btn btn-sm btn-danger py-0 px-1" onclick="removeFromCart(${item.id})"><i class="fas fa-times"></i></button></td>
        </tr>`;
    });
    $('#cartItems').html(html);
    $('#subtotal').text('$' + subtotal.toFixed(2));
    calculateTotal(subtotal);
}

function calculateTotal(sub = null) {
    let subtotal = sub !== null ? sub : parseFloat($('#subtotal').text().replace('$', ''));
    if(isNaN(subtotal)) subtotal = 0;
    
    let discount = parseFloat($('#discount').val()) || 0;
    let taxPercent = parseFloat($('#tax').val()) || 0;
    
    let afterDiscount = subtotal - discount;
    if(afterDiscount < 0) afterDiscount = 0;
    
    let taxAmount = afterDiscount * (taxPercent / 100);
    let grandTotal = afterDiscount + taxAmount;
    
    $('#grandTotal').text('$' + grandTotal.toFixed(2));
    $('#paidAmount').val(grandTotal.toFixed(2)); // Auto fill
}

function processCheckout() {
    if(cart.length === 0) {
        alert("Cart is empty!");
        return;
    }
    
    $('#cartData').val(JSON.stringify(cart));
    $('#formCustomerId').val($('#customerId').val());
    $('#formDiscount').val($('#discount').val());
    $('#formTax').val($('#tax').val());
    
    $('#checkoutForm').submit();
}

$(document).ready(function() {
    loadProducts();
    
    // Live search
    $('#searchProduct').on('keyup', function() {
        loadProducts($(this).val());
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
