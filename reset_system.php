<?php
/**
 * reset_system.php
 * DXL Fashion POS — Full System Reset (Admin Only)
 *
 * Clears ABSOLUTELY ALL operational, catalog, HR, and configuration tables.
 * Returns the database to a brand-new installation state.
 *
 * SECURITY: Admin role required. Double-confirm required.
 */

session_start();
require_once 'config/database.php';

// ── Auth guard ────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$results   = [];
$has_error = false;
$done      = false;

// ── All system tables to truncate ────────────────────────────
$always_tables = [
    'attendance',
    'audit_logs',
    'brands',
    'cashier_shifts',
    'categories',
    'customers',
    'day_end_sessions',
    'employees',
    'inventory_logs',
    'inventory_movements',
    'payment_reconciliation',
    'pending_sales',
    'pending_sales_logs',
    'products',
    'purchases',
    'purchase_items',
    'sales',
    'sale_items',
    'stock_movements',
    'suppliers',
    'users',
    'z_reports',
];

// ── Optional tables (truncate only if they exist) ─────────────
$optional_tables = [
    'day_end_records',
    'day_end_summary',
    'day_end_logs',
    'cashier_sessions',
];

// ── Current counts before reset (for display) ────────────────
$pre_counts = [];
foreach ($always_tables as $tbl) {
    try {
        $pre_counts[$tbl] = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
    } catch (Exception $e) {
        $pre_counts[$tbl] = 'N/A';
    }
}

// ── Double-confirm token ──────────────────────────────────────
$token    = $_SESSION['reset_token'] ?? null;
$confirm1 = isset($_POST['confirm1']);
$confirm2 = isset($_POST['confirm2']);
$tok_ok   = isset($_POST['reset_token']) && $_POST['reset_token'] === $token;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirm1 && $confirm2 && $tok_ok) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Truncate all tables
        foreach ($always_tables as $tbl) {
            try {
                $pdo->exec("TRUNCATE TABLE `$tbl`");
                $results[] = ['type' => 'success', 'msg' => "✓ Truncated: <code>{$tbl}</code>"];
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "doesn't exist") !== false) {
                    $results[] = ['type' => 'warning', 'msg' => "⚠ Skipped (not found): <code>{$tbl}</code>"];
                } else {
                    $results[] = ['type' => 'error', 'msg' => "✗ Error truncating <code>{$tbl}</code>: " . htmlspecialchars($e->getMessage())];
                    $has_error = true;
                }
            }
        }

        // Truncate optional tables
        foreach ($optional_tables as $tbl) {
            $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'")->fetchColumn();
            if ($exists) {
                $pdo->exec("TRUNCATE TABLE `$tbl`");
                $results[] = ['type' => 'success', 'msg' => "✓ Truncated optional: <code>{$tbl}</code>"];
            }
        }

        // Re-seed default admin account (to allow logging back in)
        $stmt = $pdo->prepare("INSERT INTO users (id, full_name, username, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            1,
            'Administrator',
            'admin',
            '$2y$10$3lBHAJYToK.GhPldqTKFLee3DWdedzpkH95SAJ1Z3nZS9HMnTWMB.', // password123
            'admin'
        ]);
        $results[] = ['type' => 'success', 'msg' => "✓ Seeded default admin account (<code>admin</code> / <code>password123</code>)"];

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        if (!$has_error) {
            $done = true;
            $results[] = ['type' => 'done', 'msg' => '🎉 Full database reset completed successfully.'];
        }

        unset($_SESSION['reset_token']);

    } catch (Exception $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $results[] = ['type' => 'error', 'msg' => "✗ Fatal error: " . htmlspecialchars($e->getMessage())];
        $has_error = true;
    }
}

// Generate a fresh one-time token
if (!$done) {
    $_SESSION['reset_token'] = bin2hex(random_bytes(16));
}

require_once 'includes/header.php';
?>

<div class="premium-dark-page" style="margin:-24px; padding:24px;">

<style>
.reset-warning-box { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.35); border-radius: 14px; padding: 28px; }
.reset-count-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,.06); font-size: .85rem; }
.reset-count-row:last-child { border-bottom: none; }
.reset-log { background: rgba(0,0,0,.35); border-radius: 10px; padding: 18px; font-family: monospace; font-size: .82rem; max-height: 380px; overflow-y: auto; }
</style>

<!-- Header -->
<div class="row align-items-center mb-4">
    <div class="col">
        <h2 class="fw-bold text-white m-0">
            <i class="fas fa-trash-restore me-2" style="color:#ef4444"></i>
            Full System Reset
        </h2>
        <p class="text-muted mb-0 mt-1" style="font-size:.85rem;">
            Permanently clears ALL system tables. Returns system to clean installation state.
        </p>
    </div>
    <div class="col-auto">
        <a href="dashboard.php" class="btn btn-dark-premium px-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if ($done): ?>
<!-- SUCCESS STATE -->
<div class="card border-0 mb-4" style="background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.3)!important; border-radius:14px;">
    <div class="card-body p-4 text-center">
        <i class="fas fa-check-circle text-success mb-3" style="font-size:3rem;"></i>
        <h4 class="fw-bold text-white mb-2">Database Clean & Empty</h4>
        <p class="text-muted mb-4">Every table in the database has been zeroed out. Default credentials restored.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="dashboard.php" class="btn btn-orange-premium px-4">
                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
            </a>
            <a href="item_registration.php" class="btn btn-dark-premium px-4">
                <i class="fas fa-plus me-2"></i>Add Products
            </a>
        </div>
    </div>
</div>

<div class="reset-log">
    <?php foreach ($results as $r): ?>
    <div class="mb-1 <?= $r['type'] === 'error' ? 'text-danger' : ($r['type'] === 'warning' ? 'text-warning' : ($r['type'] === 'done' ? 'text-success fw-bold' : 'text-success')) ?>">
        <?= $r['msg'] ?>
    </div>
    <?php endforeach; ?>
</div>

<?php else: ?>

<?php if (!empty($results) && $has_error): ?>
<div class="alert border-0 mb-4" style="background:rgba(239,68,68,.1); border-left:4px solid #ef4444!important; color:#ef4444;">
    <i class="fas fa-exclamation-triangle me-2"></i>
    Reset encountered errors. See log below.
</div>
<div class="reset-log mb-4">
    <?php foreach ($results as $r): ?>
    <div class="mb-1 <?= $r['type'] === 'error' ? 'text-danger' : 'text-warning' ?>"><?= $r['msg'] ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left: Current Counts -->
    <div class="col-lg-5">
        <div class="card border-0 h-100" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08)!important; border-radius:14px;">
            <div class="card-body p-4">
                <h5 class="fw-bold text-white mb-3">
                    <i class="fas fa-database me-2" style="color:#94a3b8"></i>Current Data in Database
                </h5>
                <p class="text-muted mb-3" style="font-size:.83rem;">
                    The following tables will be <strong class="text-danger">truncated to 0 records</strong>:
                </p>
                <?php
                $display_labels = [
                    'products'               => 'Products Catalog',
                    'categories'             => 'Categories',
                    'brands'                 => 'Brands',
                    'sales'                  => 'Sales Invoices',
                    'sale_items'             => '  └ Sale Items',
                    'purchases'              => 'Purchase Records',
                    'purchase_items'         => '  └ Purchase Items',
                    'customers'              => 'Customers',
                    'suppliers'              => 'Suppliers',
                    'employees'              => 'Employees',
                    'attendance'             => 'Attendance Records',
                    'pending_sales'          => 'Pending Holds',
                    'pending_sales_logs'     => 'Pending Holds Logs',
                    'cashier_shifts'         => 'Cashier Shifts',
                    'day_end_sessions'       => 'Day End Sessions',
                    'payment_reconciliation' => 'Payment Reconciliations',
                    'z_reports'              => 'Z-Reports',
                    'inventory_logs'         => 'Inventory Logs',
                    'inventory_movements'    => 'Inventory Movements',
                    'stock_movements'        => 'Stock Movements',
                    'audit_logs'             => 'Audit Logs',
                    'users'                  => 'Users (Seeded Admin kept)',
                ];
                $grand_total = 0;
                foreach ($display_labels as $tbl => $label):
                    $cnt = $pre_counts[$tbl] ?? 'N/A';
                    $grand_total += is_numeric($cnt) ? $cnt : 0;
                ?>
                <div class="reset-count-row">
                    <span class="text-muted" style="font-size:.82rem;"><?= $label ?></span>
                    <span class="fw-bold <?= is_numeric($cnt) && $cnt > 0 ? 'text-danger' : 'text-muted' ?>">
                        <?= is_numeric($cnt) ? number_format((int)$cnt) : $cnt ?>
                        <?= $cnt != 1 ? 'records' : 'record' ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <div class="reset-count-row mt-2" style="border-top:2px solid rgba(239,68,68,.3)!important;">
                    <span class="fw-bold text-white">Total Database Records</span>
                    <span class="fw-bold text-danger" style="font-size:1.1rem;"><?= number_format($grand_total) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Confirm Form -->
    <div class="col-lg-7">
        <div class="reset-warning-box h-100">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div style="width:52px;height:52px;background:rgba(239,68,68,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-exclamation-triangle text-danger" style="font-size:1.4rem;"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-danger m-0">⚠ DANGER — Complete Database Erase</h5>
                    <p class="text-muted m-0" style="font-size:.83rem;">This action CANNOT be undone. Absolutely all tables will be zeroed.</p>
                </div>
            </div>

            <div class="mb-4" style="background:rgba(0,0,0,.3); border-radius:10px; padding:16px; font-size:.85rem; color:#94a3b8; line-height:1.8;">
                <strong class="text-white">What will be truncated:</strong><br>
                ✗ All sales, purchases, and catalogs<br>
                ✗ All categories, brands, and products<br>
                ✗ All customers, suppliers, and loyalty data<br>
                ✗ All employees, attendance, and audit logs<br>
                ✗ All cashier shifts, sessions, and day end logs<br>
                <br>
                <strong class="text-warning">Restore Default Admin:</strong><br>
                ✓ Username: <strong class="text-white">admin</strong><br>
                ✓ Password: <strong class="text-white">password123</strong><br>
                ✓ Role: <strong class="text-white">admin</strong>
            </div>

            <form method="POST" id="resetForm">
                <input type="hidden" name="reset_token" value="<?= htmlspecialchars($_SESSION['reset_token'] ?? '') ?>">

                <div class="mb-3 d-flex align-items-start gap-3 p-3 rounded" style="background:rgba(239,68,68,.08);">
                    <input type="checkbox" name="confirm1" id="confirm1" class="form-check-input mt-1" style="min-width:18px;height:18px;" required>
                    <label for="confirm1" class="text-white" style="cursor:pointer;font-size:.87rem;">
                        I understand this will <strong class="text-danger">truncate every single table to 0 records</strong>.
                    </label>
                </div>

                <div class="mb-4 d-flex align-items-start gap-3 p-3 rounded" style="background:rgba(239,68,68,.08);">
                    <input type="checkbox" name="confirm2" id="confirm2" class="form-check-input mt-1" style="min-width:18px;height:18px;" required>
                    <label for="confirm2" class="text-white" style="cursor:pointer;font-size:.87rem;">
                        I confirm I want to restore the system to a completely empty state.
                    </label>
                </div>

                <div class="mb-4">
                    <label class="text-white fw-semibold mb-2" style="font-size:.87rem;">
                        Type <code class="text-danger">RESET</code> to confirm:
                    </label>
                    <input type="text" id="confirmText" class="form-control" placeholder="Type RESET here..."
                           oninput="document.getElementById('resetBtn').disabled = this.value !== 'RESET'">
                </div>

                <button type="submit" id="resetBtn" class="btn btn-danger fw-bold w-100 py-3" disabled
                        onclick="return confirm('FINAL WARNING: This will permanently delete all data. Are you absolutely sure?')">
                    <i class="fas fa-trash-alt me-2"></i>
                    Reset System to Clean State
                </button>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>
</div><!-- /premium-dark-page -->

<?php require_once 'includes/footer.php'; ?>
