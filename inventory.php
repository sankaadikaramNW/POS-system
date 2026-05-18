<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $product_id = $_POST['product_id'];
    $action_type = $_POST['action_type']; // STOCK_IN, STOCK_OUT, DAMAGES
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0) {
        $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $current_stock = $stmt->fetchColumn();

        if ($action_type === 'STOCK_IN') {
            $new_stock = $current_stock + $quantity;
        } else {
            // STOCK_OUT or DAMAGES
            $new_stock = $current_stock - $quantity;
            if ($new_stock < 0) $new_stock = 0; // Prevent negative stock
        }

        // Update Stock
        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);

        // Log
        $stmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, action_type, quantity, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $action_type, $quantity, $_SESSION['user_id']]);
    }
    header("Location: inventory.php");
    exit();
}

$logs = $pdo->query("
    SELECT l.*, p.product_name, u.full_name as user_name 
    FROM inventory_logs l 
    JOIN products p ON l.product_id = p.id 
    LEFT JOIN users u ON l.created_by = u.id 
    ORDER BY l.id DESC LIMIT 100
")->fetchAll();

$products = $pdo->query("SELECT id, product_name, stock_quantity FROM products ORDER BY product_name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Inventory Management</h2>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#adjustModal">
        <i class="fas fa-sliders-h"></i> Adjust Stock
    </button>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Recent Inventory Activity</h5>
    </div>
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Action</th>
                    <th>Quantity</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($logs as $log): ?>
                <tr>
                    <td><?= $log['action_date'] ?></td>
                    <td><?= htmlspecialchars($log['product_name']) ?></td>
                    <td>
                        <?php if($log['action_type'] == 'STOCK_IN'): ?>
                            <span class="badge bg-success"><i class="fas fa-arrow-down"></i> IN</span>
                        <?php elseif($log['action_type'] == 'SALE'): ?>
                            <span class="badge bg-primary"><i class="fas fa-shopping-cart"></i> SALE</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fas fa-arrow-up"></i> <?= $log['action_type'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $log['quantity'] ?></td>
                    <td><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($logs)): ?>
                <tr><td colspan="5" class="text-center">No inventory logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Select Product</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">-- Choose Product --</option>
                        <?php foreach($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['product_name']) ?> (Stock: <?= $p['stock_quantity'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Action Type</label>
                    <select name="action_type" class="form-select" required>
                        <option value="STOCK_IN">Stock In (Add)</option>
                        <option value="STOCK_OUT">Stock Out (Remove)</option>
                        <option value="DAMAGES">Damages (Remove)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Quantity</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="adjust_stock" class="btn btn-warning">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
