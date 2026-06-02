<?php
/**
 * inventory.php
 * DXL Fashion POS — Inventory Adjustment & Audit Log Page
 *
 * All stock adjustments are written to both inventory_logs (legacy) and
 * stock_movements (new full audit trail with previous/new stock values).
 * Negative stock is blocked at both application and database trigger level.
 */

require_once 'config/database.php';
require_once 'includes/header.php';

// ── Handle Stock Adjustment ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $product_id  = (int)$_POST['product_id'];
    $action_type = $_POST['action_type']; // STOCK_IN, STOCK_OUT, DAMAGES
    $quantity    = (int)$_POST['quantity'];
    $notes       = trim($_POST['notes'] ?? '');
    $user_id     = $_SESSION['user_id'] ?? null;

    if ($product_id > 0 && $quantity > 0) {
        try {
            $pdo->beginTransaction();

            // Get current stock with row lock
            $lock_stmt = $pdo->prepare("SELECT stock_quantity, product_name FROM products WHERE id = ? FOR UPDATE");
            $lock_stmt->execute([$product_id]);
            $product = $lock_stmt->fetch();

            if (!$product) {
                throw new Exception("Product not found.");
            }

            $prev_stock = (int)$product['stock_quantity'];

            if ($action_type === 'STOCK_IN') {
                $new_stock = $prev_stock + $quantity;
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")
                    ->execute([$quantity, $product_id]);
                $sm_type = 'STOCK_IN';
            } else {
                // STOCK_OUT or DAMAGES — prevent going negative
                if ($quantity > $prev_stock) {
                    throw new Exception("Cannot remove {$quantity} units from '{$product['product_name']}'. Only {$prev_stock} available.");
                }
                $new_stock = $prev_stock - $quantity;
                $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?")
                    ->execute([$quantity, $product_id, $quantity]);
                $sm_type = ($action_type === 'DAMAGES') ? 'DAMAGES' : 'STOCK_OUT';
            }

            $ref_no = 'ADJ-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // Full audit trail — stock_movements
            $sm_check = $pdo->query("SHOW TABLES LIKE 'stock_movements'")->fetchColumn();
            if ($sm_check) {
                $pdo->prepare("
                    INSERT INTO stock_movements
                        (product_id, invoice_id, reference_no, movement_type, quantity,
                         previous_stock, new_stock, notes, user_id, created_at)
                    VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $product_id,
                    $ref_no,
                    $sm_type,
                    $quantity,
                    $prev_stock,
                    $new_stock,
                    $notes ?: "Manual adjustment: {$sm_type} of {$quantity} units",
                    $user_id
                ]);
            }

            // Legacy inventory_logs
            $log_cols = $pdo->query("SHOW COLUMNS FROM inventory_logs LIKE 'reference_no'")->fetchColumn();
            if ($log_cols) {
                $pdo->prepare("
                    INSERT INTO inventory_logs
                        (product_id, action_type, quantity, invoice_id, reference_no, created_by)
                    VALUES (?, ?, ?, NULL, ?, ?)
                ")->execute([$product_id, $action_type, $quantity, $ref_no, $user_id]);
            } else {
                $pdo->prepare("
                    INSERT INTO inventory_logs
                        (product_id, action_type, quantity, created_by)
                    VALUES (?, ?, ?, ?)
                ")->execute([$product_id, $action_type, $quantity, $user_id]);
            }

            $pdo->commit();

            header("Location: inventory.php?success=1&ref={$ref_no}");
            exit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            header("Location: inventory.php?error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: inventory.php?error=Invalid+product+or+quantity");
        exit();
    }
}

// ── Fetch Stock Movement Logs ────────────────────────────────────────────────
// Prefer stock_movements (richer) if it exists, fall back to inventory_logs
$sm_exists = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_movements'")->fetchColumn();

if ($sm_exists) {
    $logs = $pdo->query("
        SELECT
            sm.created_at   AS action_date,
            p.product_name,
            sm.movement_type AS action_type,
            sm.quantity,
            sm.previous_stock,
            sm.new_stock,
            sm.reference_no,
            sm.notes,
            u.full_name      AS user_name
        FROM stock_movements sm
        JOIN products p   ON sm.product_id = p.id
        LEFT JOIN users u ON sm.user_id    = u.id
        ORDER BY sm.id DESC
        LIMIT 200
    ")->fetchAll();
    $use_rich_log = true;
} else {
    $logs = $pdo->query("
        SELECT l.*, p.product_name, u.full_name as user_name
        FROM inventory_logs l
        JOIN products p ON l.product_id = p.id
        LEFT JOIN users u ON l.created_by = u.id
        ORDER BY l.id DESC LIMIT 100
    ")->fetchAll();
    $use_rich_log = false;
}

$products = $pdo->query("SELECT id, product_name, stock_quantity FROM products ORDER BY product_name")->fetchAll();

// ── Summary stats ────────────────────────────────────────────────────────────
$total_products  = count($products);
$out_of_stock    = count(array_filter($products, fn($p) => (int)$p['stock_quantity'] === 0));
$low_stock_count = count($pdo->query("SELECT id FROM products WHERE stock_quantity > 0 AND stock_quantity <= reorder_level")->fetchAll());
$total_units     = array_sum(array_column($products, 'stock_quantity'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Inventory Management</h2>
        <p class="text-muted mb-0 small">All stock values are read directly from the SQL database in real-time.</p>
    </div>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#adjustModal">
        <i class="fas fa-sliders-h me-1"></i> Adjust Stock
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show border-0 mb-3" style="background:rgba(34,197,94,.1); border-left:4px solid #22c55e !important; color:#15803d;">
    <i class="fas fa-check-circle me-2"></i>
    <strong>Stock adjusted successfully.</strong>
    <?php if (!empty($_GET['ref'])): ?>
        Reference: <code><?= htmlspecialchars($_GET['ref']) ?></code>
    <?php endif; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 mb-3">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?= htmlspecialchars(urldecode($_GET['error'])) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- KPI Summary Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card text-center border-0" style="background: linear-gradient(135deg,#3b82f6,#1d4ed8); border-radius:12px;">
            <div class="card-body py-3">
                <div class="fw-bold text-white" style="font-size:1.8rem;"><?= number_format($total_products) ?></div>
                <div class="text-white opacity-75 small">Total Products</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card text-center border-0" style="background: linear-gradient(135deg,#10b981,#047857); border-radius:12px;">
            <div class="card-body py-3">
                <div class="fw-bold text-white" style="font-size:1.8rem;"><?= number_format($total_units) ?></div>
                <div class="text-white opacity-75 small">Total Stock Units</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card text-center border-0" style="background: linear-gradient(135deg,#f59e0b,#b45309); border-radius:12px;">
            <div class="card-body py-3">
                <div class="fw-bold text-white" style="font-size:1.8rem;"><?= number_format($low_stock_count) ?></div>
                <div class="text-white opacity-75 small">Low Stock Items</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card text-center border-0" style="background: linear-gradient(135deg,#ef4444,#991b1b); border-radius:12px;">
            <div class="card-body py-3">
                <div class="fw-bold text-white" style="font-size:1.8rem;"><?= number_format($out_of_stock) ?></div>
                <div class="text-white opacity-75 small">Out of Stock</div>
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <?= $use_rich_log ? 'Stock Movements (Full Audit Trail)' : 'Inventory Activity Log' ?>
        </h5>
        <?php if (!$sm_exists): ?>
        <a href="install_inventory_refactor.php" class="btn btn-sm btn-warning">
            <i class="fas fa-database me-1"></i> Run Migration for Full Audit Trail
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>Product</th>
                        <th>Movement</th>
                        <th class="text-center">Qty</th>
                        <?php if ($use_rich_log): ?>
                        <th class="text-center">Before</th>
                        <th class="text-center">After</th>
                        <th>Reference</th>
                        <?php endif; ?>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <small class="text-muted"><?= date('d M Y', strtotime($log['action_date'])) ?></small><br>
                            <small class="fw-semibold"><?= date('H:i:s', strtotime($log['action_date'])) ?></small>
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($log['product_name']) ?></td>
                        <td>
                            <?php
                            $badge_class = 'bg-secondary';
                            $icon = 'fa-exchange-alt';
                            $at = $log['action_type'];
                            if (in_array($at, ['SALE'])) { $badge_class = 'bg-primary'; $icon = 'fa-shopping-cart'; }
                            elseif (in_array($at, ['STOCK_IN', 'PURCHASE'])) { $badge_class = 'bg-success'; $icon = 'fa-arrow-down'; }
                            elseif (in_array($at, ['STOCK_OUT','DAMAGES','RETURN'])) { $badge_class = 'bg-danger'; $icon = 'fa-arrow-up'; }
                            elseif ($at === 'ADJUSTMENT') { $badge_class = 'bg-warning text-dark'; $icon = 'fa-sliders-h'; }
                            elseif ($at === 'CANCELLATION') { $badge_class = 'bg-info'; $icon = 'fa-undo'; }
                            ?>
                            <span class="badge <?= $badge_class ?>">
                                <i class="fas <?= $icon ?> me-1"></i><?= htmlspecialchars($at) ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold"><?= number_format($log['quantity']) ?></td>
                        <?php if ($use_rich_log): ?>
                        <td class="text-center text-muted"><?= number_format($log['previous_stock']) ?></td>
                        <td class="text-center">
                            <span class="fw-bold <?= (int)$log['new_stock'] === 0 ? 'text-danger' : ((int)$log['new_stock'] < 5 ? 'text-warning' : 'text-success') ?>">
                                <?= number_format($log['new_stock']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($log['reference_no'])): ?>
                                <code style="font-size:.78rem;"><?= htmlspecialchars($log['reference_no']) ?></code>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="text-muted small"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="<?= $use_rich_log ? '8' : '5' ?>" class="text-center text-muted py-4">No inventory activity recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" onsubmit="this.querySelector('button[type=submit]').disabled=true; return true;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sliders-h me-2 text-warning"></i>Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Select Product</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['product_name']) ?>
                            (Current Stock: <?= $p['stock_quantity'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Adjustment Type</label>
                    <select name="action_type" class="form-select" required>
                        <option value="STOCK_IN">Stock In — Add Inventory</option>
                        <option value="STOCK_OUT">Stock Out — Remove Inventory</option>
                        <option value="DAMAGES">Damages — Write Off</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Quantity</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Notes / Reason <small class="text-muted">(optional)</small></label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Reason for adjustment..."></textarea>
                </div>
                <div class="alert alert-warning py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    This adjustment will be recorded in the full audit trail with before and after stock values.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="adjust_stock" class="btn btn-warning fw-bold">
                    <i class="fas fa-check me-1"></i> Apply Adjustment
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
