<?php
/**
 * install_inventory_refactor.php
 * DXL Fashion POS — Inventory Management Refactor Installer
 *
 * Admin-only page. Runs the inventory_refactor.sql migration safely.
 * Idempotent — safe to run multiple times.
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

$results   = [];
$has_error = false;

if (isset($_POST['run_migration'])) {
    $sql_file = __DIR__ . '/sql/inventory_refactor.sql';

    if (!file_exists($sql_file)) {
        $results[]  = ['type' => 'error', 'msg' => 'Migration file not found: sql/inventory_refactor.sql'];
        $has_error  = true;
    } else {
        $raw_sql = file_get_contents($sql_file);

        // Split on semicolons, skipping empty / comment-only blocks
        // We handle triggers specially (DELIMITER workaround for PDO)
        $statements = [];
        $current    = '';
        $in_trigger = false;

        foreach (explode("\n", $raw_sql) as $line) {
            $trimmed = trim($line);

            // Skip pure comment lines and empty lines for splitting logic
            if ($trimmed === '' || substr($trimmed, 0, 2) === '--') {
                $current .= $line . "\n";
                continue;
            }

            if (stripos($trimmed, 'CREATE TRIGGER') !== false) {
                $in_trigger = true;
            }

            $current .= $line . "\n";

            if ($in_trigger && stripos($trimmed, 'END;') !== false) {
                $statements[] = trim($current);
                $current      = '';
                $in_trigger   = false;
                continue;
            }

            if (!$in_trigger && substr($trimmed, -1) === ';') {
                $statements[] = trim($current);
                $current      = '';
            }
        }

        foreach ($statements as $stmt_sql) {
            $stmt_sql = trim($stmt_sql);
            // Skip blank or pure-comment statements
            if (empty($stmt_sql) || preg_match('/^(--.*)$/m', $stmt_sql) && strlen(preg_replace('/^--.*$/m', '', $stmt_sql)) < 5) {
                continue;
            }
            // Skip USE statement (PDO already connected to correct DB)
            if (stripos($stmt_sql, 'USE `fashion_pos`') === 0) {
                continue;
            }

            try {
                $pdo->exec($stmt_sql);
                // Extract a short description from first non-comment line
                $lines     = array_filter(explode("\n", $stmt_sql), fn($l) => trim($l) !== '' && substr(trim($l), 0, 2) !== '--');
                $first     = trim(reset($lines));
                $short     = strlen($first) > 90 ? substr($first, 0, 90) . '...' : $first;
                $results[] = ['type' => 'success', 'msg' => "✓ " . htmlspecialchars($short)];
            } catch (PDOException $e) {
                // Duplicate column / table already exists are acceptable (idempotent)
                $code  = $e->getCode();
                $emsg  = $e->getMessage();
                $lines = array_filter(explode("\n", $stmt_sql), fn($l) => trim($l) !== '' && substr(trim($l), 0, 2) !== '--');
                $first = trim(reset($lines));
                $short = strlen($first) > 90 ? substr($first, 0, 90) . '...' : $first;

                if (in_array($code, ['42S01', '42S21', '42000']) || strpos($emsg, 'already exists') !== false || strpos($emsg, 'Duplicate') !== false) {
                    $results[] = ['type' => 'warning', 'msg' => "⚠ Already exists (skipped): " . htmlspecialchars($short)];
                } else {
                    $results[] = ['type' => 'error',   'msg' => "✗ Error on: " . htmlspecialchars($short) . "<br><small class='text-muted'>" . htmlspecialchars($emsg) . "</small>"];
                    $has_error = true;
                }
            }
        }
    }
}

// Verify current state
$tables_exist = [];
$check_tables = ['stock_movements', 'inventory_logs', 'sales', 'products', 'pending_sales', 'pending_sales_logs'];
foreach ($check_tables as $tbl) {
    $r = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'")->fetchColumn();
    $tables_exist[$tbl] = (int)$r > 0;
}

// Check columns in inventory_logs
$inv_has_invoice = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_logs' AND COLUMN_NAME = 'invoice_id'")->fetchColumn();
$inv_has_ref     = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_logs' AND COLUMN_NAME = 'reference_no'")->fetchColumn();

// Check triggers
$triggers_exist = [];
foreach (['trg_prevent_negative_stock_update', 'trg_prevent_negative_stock_insert'] as $trg) {
    $triggers_exist[$trg] = (bool)$pdo->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = '$trg'")->fetchColumn();
}

require_once 'includes/header.php';
?>

<div class="premium-dark-page" style="margin:-24px; padding:24px;">

    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="fw-bold text-white m-0">
                <i class="fas fa-database me-2" style="color:var(--primary-orange)"></i>
                Inventory Management — DB Migration
            </h2>
            <p class="text-muted mb-0 mt-1" style="font-size:.85rem;">
                One-click installer for the inventory refactor schema changes.
                Safe to run multiple times (idempotent).
            </p>
        </div>
        <div class="col-auto">
            <a href="dashboard.php" class="btn btn-dark-premium px-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Current Status Card -->
    <div class="card mb-4" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:14px;">
        <div class="card-body p-4">
            <h5 class="fw-bold text-white mb-3"><i class="fas fa-clipboard-check me-2" style="color:#22c55e"></i>Current Database Status</h5>
            <div class="row g-3">
                <?php foreach ($tables_exist as $tname => $exists): ?>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(0,0,0,.2);">
                        <?php if ($exists): ?>
                            <i class="fas fa-check-circle text-success"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-danger"></i>
                        <?php endif; ?>
                        <span class="text-white font-monospace" style="font-size:.82rem;"><?= htmlspecialchars($tname) ?></span>
                        <span class="ms-auto badge <?= $exists ? 'bg-success' : 'bg-danger' ?>" style="font-size:.7rem;">
                            <?= $exists ? 'EXISTS' : 'MISSING' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- inventory_logs columns -->
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(0,0,0,.2);">
                        <i class="fas fa-<?= $inv_has_invoice ? 'check-circle text-success' : 'times-circle text-danger' ?>"></i>
                        <span class="text-white font-monospace" style="font-size:.82rem;">inventory_logs.invoice_id</span>
                        <span class="ms-auto badge <?= $inv_has_invoice ? 'bg-success' : 'bg-danger' ?>" style="font-size:.7rem;">
                            <?= $inv_has_invoice ? 'EXISTS' : 'MISSING' ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(0,0,0,.2);">
                        <i class="fas fa-<?= $inv_has_ref ? 'check-circle text-success' : 'times-circle text-danger' ?>"></i>
                        <span class="text-white font-monospace" style="font-size:.82rem;">inventory_logs.reference_no</span>
                        <span class="ms-auto badge <?= $inv_has_ref ? 'bg-success' : 'bg-danger' ?>" style="font-size:.7rem;">
                            <?= $inv_has_ref ? 'EXISTS' : 'MISSING' ?>
                        </span>
                    </div>
                </div>

                <!-- Triggers -->
                <?php foreach ($triggers_exist as $trgname => $texists): ?>
                <div class="col-md-6">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:rgba(0,0,0,.2);">
                        <i class="fas fa-<?= $texists ? 'check-circle text-success' : 'times-circle text-danger' ?>"></i>
                        <span class="text-white font-monospace" style="font-size:.82rem;">TRIGGER: <?= htmlspecialchars($trgname) ?></span>
                        <span class="ms-auto badge <?= $texists ? 'bg-success' : 'bg-danger' ?>" style="font-size:.7rem;">
                            <?= $texists ? 'ACTIVE' : 'MISSING' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Migration Results -->
    <?php if (!empty($results)): ?>
    <div class="card mb-4" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:14px;">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 <?= $has_error ? 'text-danger' : 'text-success' ?>">
                <i class="fas fa-<?= $has_error ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
                Migration Results
            </h5>
            <div style="max-height:350px; overflow-y:auto; font-family: monospace; font-size:.82rem; background:rgba(0,0,0,.3); border-radius:8px; padding:16px;">
                <?php foreach ($results as $r): ?>
                    <div class="mb-1 <?= $r['type'] === 'error' ? 'text-danger' : ($r['type'] === 'warning' ? 'text-warning' : 'text-success') ?>">
                        <?= $r['msg'] ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!$has_error): ?>
            <div class="alert mt-3 mb-0" style="background:rgba(34,197,94,.1); border:1px solid rgba(34,197,94,.3); color:#22c55e; border-radius:8px;">
                <i class="fas fa-shield-alt me-2"></i>
                <strong>Migration completed successfully.</strong>
                The inventory management system is now fully database-driven with row-level locking and a complete stock movements audit trail.
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Card -->
    <div class="card" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:14px;">
        <div class="card-body p-4">
            <h5 class="fw-bold text-white mb-3"><i class="fas fa-play-circle me-2" style="color:var(--primary-orange)"></i>Run Migration</h5>

            <div class="alert mb-4" style="background:rgba(249,115,22,.1); border:1px solid rgba(249,115,22,.3); color:#fb923c; border-radius:8px;">
                <i class="fas fa-info-circle me-2"></i>
                <strong>What this migration does:</strong>
                <ul class="mt-2 mb-0" style="font-size:.87rem;">
                    <li>Creates the <code>stock_movements</code> audit table (full before/after stock tracking)</li>
                    <li>Adds <code>invoice_id</code> and <code>reference_no</code> columns to <code>inventory_logs</code></li>
                    <li>Installs two DB triggers to prevent negative stock values at the database level</li>
                </ul>
            </div>

            <form method="POST">
                <button type="submit" name="run_migration" class="btn btn-orange-premium px-4 py-2 fw-bold"
                        onclick="return confirm('Run inventory database migration now?\n\nThis is safe to run multiple times.')">
                    <i class="fas fa-database me-2"></i>
                    Run Inventory Migration
                </button>
            </form>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
