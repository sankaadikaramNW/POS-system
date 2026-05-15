<?php
session_start();
require_once 'includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: index.php");
    exit();
}

// Fetch products for the search/dropdown
$products = $pdo->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY name ASC")->fetchAll();

// Handle Sale Submission
if (isset($_POST['complete_sale'])) {
    $seller_id = $_SESSION['user_id'];
    $cart_data = json_decode($_POST['cart_json'], true);
    $total_amount = $_POST['total_amount'];
    $discount = $_POST['discount_amount'];
    $final_amount = $_POST['final_amount'];

    if (!empty($cart_data)) {
        try {
            $pdo->beginTransaction();

            // Insert Sale
            $stmt = $pdo->prepare("INSERT INTO sales (seller_id, total_amount, discount_amount, final_amount) VALUES (?, ?, ?, ?) RETURNING id");
            $stmt->execute([$seller_id, $total_amount, $discount, $final_amount]);
            $sale_id = $stmt->fetchColumn();

            // Insert Items and Update Stock
            foreach ($cart_data as $item) {
                $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$sale_id, $item['id'], $item['qty'], $item['price']]);

                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt->execute([$item['qty'], $item['id']]);
            }

            $pdo->commit();
            header("Location: print_bill.php?sale_id=" . $sale_id);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Sale failed: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller POS - FeetUp POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .product-card {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }
        .product-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .grid-products {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            max-height: 500px;
            overflow-y: auto;
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <span>FeetUp POS</span>
            </div>
            <nav>
                <a href="seller_dashboard.php" class="nav-link active">New Sale</a>
                <a href="auth.php?logout=1" class="nav-link" style="margin-top: auto; color: #f87171;">Logout</a>
            </nav>
        </div>

        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1>Point of Sale</h1>
                <div class="user-info">
                    <span>Seller: <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></span>
                </div>
            </header>

            <div class="pos-grid">
                <!-- Product Selection -->
                <div class="card">
                    <div class="product-search">
                        <input type="text" id="search" class="form-control" placeholder="Search products by name or SKU...">
                    </div>
                    <div class="grid-products" id="product-list">
                        <?php foreach ($products as $p): ?>
                        <div class="product-card" onclick="addToCart(<?php echo htmlspecialchars(json_encode($p)); ?>)">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;"><?php echo htmlspecialchars($p['sku']); ?></div>
                            <div style="color: var(--primary-color); font-weight: 700; margin-top: 0.5rem;">$<?php echo number_format($p['price'], 2); ?></div>
                            <div style="font-size: 0.75rem;">Stock: <?php echo $p['stock_quantity']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cart & Checkout -->
                <div class="card">
                    <h2 class="card-title" style="margin-bottom: 1rem;">Current Order</h2>
                    <div class="cart-items" id="cart-container">
                        <div style="text-align: center; color: var(--text-muted); padding: 2rem;">Cart is empty</div>
                    </div>

                    <div class="bill-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Discount ($)</span>
                            <input type="number" id="discount-input" value="0" step="0.01" style="width: 80px; text-align: right;" onchange="updateTotals()">
                        </div>
                        <div class="summary-row total-row">
                            <span>Total</span>
                            <span id="final-total">$0.00</span>
                        </div>
                    </div>

                    <form action="seller_dashboard.php" method="POST" id="checkout-form">
                        <input type="hidden" name="cart_json" id="cart-json">
                        <input type="hidden" name="total_amount" id="total-amount-hidden">
                        <input type="hidden" name="discount_amount" id="discount-amount-hidden">
                        <input type="hidden" name="final_amount" id="final-amount-hidden">
                        <button type="submit" name="complete_sale" class="btn btn-primary" style="margin-top: 1.5rem;" onclick="return validateCheckout()">Complete Sale & Print Bill</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        let cart = [];

        function addToCart(product) {
            const existing = cart.find(item => item.id === product.id);
            if (existing) {
                if (existing.qty < product.stock_quantity) {
                    existing.qty++;
                } else {
                    alert('Maximum stock reached');
                }
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    qty: 1,
                    stock: product.stock_quantity
                });
            }
            renderCart();
        }

        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            renderCart();
        }

        function updateQty(id, delta) {
            const item = cart.find(i => i.id === id);
            if (item) {
                const newQty = item.qty + delta;
                if (newQty > 0 && newQty <= item.stock) {
                    item.qty = newQty;
                } else if (newQty === 0) {
                    removeFromCart(id);
                }
                renderCart();
            }
        }

        function renderCart() {
            const container = document.getElementById('cart-container');
            if (cart.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 2rem;">Cart is empty</div>';
                updateTotals();
                return;
            }

            let html = '<table style="font-size: 0.875rem;">';
            cart.forEach(item => {
                html += `
                    <tr>
                        <td style="padding: 0.5rem 0;">
                            <strong>${item.name}</strong><br>
                            <small>$${item.price.toFixed(2)}</small>
                        </td>
                        <td style="text-align: center;">
                            <button onclick="updateQty(${item.id}, -1)" style="padding: 0 5px;">-</button>
                            ${item.qty}
                            <button onclick="updateQty(${item.id}, 1)" style="padding: 0 5px;">+</button>
                        </td>
                        <td style="text-align: right;">$${(item.price * item.qty).toFixed(2)}</td>
                        <td><button onclick="removeFromCart(${item.id})" style="color: red; border:none; background:none; cursor:pointer;">×</button></td>
                    </tr>
                `;
            });
            html += '</table>';
            container.innerHTML = html;
            updateTotals();
        }

        function updateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const discount = parseFloat(document.getElementById('discount-input').value) || 0;
            const final = Math.max(0, subtotal - discount);

            document.getElementById('subtotal').innerText = '$' + subtotal.toFixed(2);
            document.getElementById('final-total').innerText = '$' + final.toFixed(2);

            // Update hidden fields
            document.getElementById('cart-json').value = JSON.stringify(cart);
            document.getElementById('total-amount-hidden').value = subtotal.toFixed(2);
            document.getElementById('discount-amount-hidden').value = discount.toFixed(2);
            document.getElementById('final-amount-hidden').value = final.toFixed(2);
        }

        function validateCheckout() {
            if (cart.length === 0) {
                alert('Cart is empty');
                return false;
            }
            return true;
        }

        // Simple Search
        document.getElementById('search').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                card.style.display = text.includes(term) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
