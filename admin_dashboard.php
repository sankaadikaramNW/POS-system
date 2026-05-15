<?php
session_start();
require_once 'includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle Add/Update Product
if (isset($_POST['save_product'])) {
    $name = $_POST['name'];
    $sku = $_POST['sku'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $id = $_POST['id'] ?? null;

    if ($id) {
        $stmt = $pdo->prepare("UPDATE products SET name=?, sku=?, category=?, price=?, stock_quantity=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
        $stmt->execute([$name, $sku, $category, $price, $stock, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO products (name, sku, category, price, stock_quantity) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $sku, $category, $price, $stock]);
    }
    header("Location: admin_dashboard.php?success=1");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: admin_dashboard.php?deleted=1");
    exit();
}

// Fetch products
$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FeetUp POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <span>FeetUp POS</span>
            </div>
            <nav>
                <a href="admin_dashboard.php" class="nav-link active">Inventory Management</a>
                <a href="reports.php" class="nav-link">Sales Reports</a>
                <a href="auth.php?logout=1" class="nav-link" style="margin-top: auto; color: #f87171;">Logout</a>
            </nav>
        </div>

        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1>Inventory Management</h1>
                <div class="user-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong> (Admin)</span>
                </div>
            </header>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Add/Edit Product</h2>
                </div>
                <form action="admin_dashboard.php" method="POST" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; align-items: end;">
                    <input type="hidden" name="id" id="prod_id">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Product Name</label>
                        <input type="text" name="name" id="prod_name" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>SKU</label>
                        <input type="text" name="sku" id="prod_sku" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Category</label>
                        <input type="text" name="category" id="prod_cat" class="form-control">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Price ($)</label>
                        <input type="number" step="0.01" name="price" id="prod_price" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Stock Quantity</label>
                        <input type="number" name="stock" id="prod_stock" class="form-control" required>
                    </div>
                    <button type="submit" name="save_product" class="btn btn-primary">Save Product</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Product List</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['sku']); ?></td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo htmlspecialchars($p['category']); ?></td>
                            <td>$<?php echo number_format($p['price'], 2); ?></td>
                            <td>
                                <span style="color: <?php echo $p['stock_quantity'] < 10 ? 'var(--error-color)' : 'inherit'; ?>; font-weight: bold;">
                                    <?php echo $p['stock_quantity']; ?>
                                </span>
                            </td>
                            <td>
                                <button onclick="editProduct(<?php echo htmlspecialchars(json_encode($p)); ?>)" class="btn" style="padding: 0.25rem 0.75rem; font-size: 0.875rem; background: #e2e8f0; width: auto; margin-right: 0.5rem;">Edit</button>
                                <a href="admin_dashboard.php?delete=<?php echo $p['id']; ?>" class="btn" style="padding: 0.25rem 0.75rem; font-size: 0.875rem; background: #fee2e2; color: #ef4444; width: auto;" onclick="return confirm('Are you sure?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function editProduct(p) {
            document.getElementById('prod_id').value = p.id;
            document.getElementById('prod_name').value = p.name;
            document.getElementById('prod_sku').value = p.sku;
            document.getElementById('prod_cat').value = p.category;
            document.getElementById('prod_price').value = p.price;
            document.getElementById('prod_stock').value = p.stock_quantity;
        }
    </script>
</body>
</html>
