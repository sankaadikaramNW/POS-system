<?php
// install_day_end.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict to admins if already logged in, otherwise let them run once to setup
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day End Schema Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', system-ui, sans-serif; color: #1e293b; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); max-width: 600px; width: 100%; overflow: hidden; background: #ffffff; }
        .card-header { background: linear-gradient(135deg, #0f62fe, #0043ce); color: white; padding: 30px; text-align: center; border: none; }
        .card-body { padding: 40px; }
        .step-log { max-height: 200px; overflow-y: auto; background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 0.82rem; padding: 15px; border-radius: 8px; border: 1px solid #334155; }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <i class="fas fa-database fs-1 mb-2"></i>
        <h4 class="fw-bold m-0">Day End Schema Installer</h4>
        <p class="text-white-50 m-0 mt-1 small">Setting up database tables and initial configuration</p>
    </div>
    <div class="card-body">
        <?php
        if (isset($_POST['install'])) {
            try {
                $sql_file = 'sql/day_end_schema.sql';
                if (!file_exists($sql_file)) {
                    throw new Exception("Schema SQL file not found at " . $sql_file);
                }

                $sql = file_get_contents($sql_file);
                
                // Remove MySQL comments and split statements
                $sql = preg_replace('/--.*\n/', '', $sql);
                $statements = array_filter(array_map('trim', explode(';', $sql)));

                echo '<h6 class="fw-bold mb-2 text-primary">Execution Logs:</h6>';
                echo '<div class="step-log mb-4">';
                
                foreach ($statements as $index => $stmt) {
                    if (empty($stmt)) continue;
                    $pdo->exec($stmt);
                    $first_line = strtok($stmt, "\n");
                    echo "[SUCCESS] Step " . ($index + 1) . ": " . htmlspecialchars(substr($first_line, 0, 50)) . "...<br>";
                }

                // Check and initialize default Day End Session for today
                $today = date('Y-m-d');
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM day_end_sessions WHERE business_date = ?");
                $check_stmt->execute([$today]);
                if ($check_stmt->fetchColumn() == 0) {
                    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                    
                    // Fallback to fetch first admin if none set (first run)
                    if (!$admin_id) {
                        $admin_id = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() ?: null;
                    }

                    $init_stmt = $pdo->prepare("INSERT INTO day_end_sessions (business_date, status, opened_by) VALUES (?, 'open', ?)");
                    $init_stmt->execute([$today, $admin_id]);
                    echo "[INIT] Initialized active Day End Session for business date: " . $today . "<br>";
                }

                echo '</div>';
                echo '<div class="alert alert-success d-flex align-items-center mb-4">';
                echo '  <i class="fas fa-check-circle fs-4 me-2"></i>';
                echo '  <div><strong>Installation Completed!</strong> Day End Process tables initialized successfully.</div>';
                echo '</div>';
                echo '<div class="d-flex justify-content-end">';
                echo '  <a href="day_end.php" class="btn btn-primary px-4 fw-bold">Go to Day End Dashboard <i class="fas fa-arrow-right ms-1"></i></a>';
                echo '</div>';

            } catch (Exception $e) {
                echo '</div>';
                echo '<div class="alert alert-danger mb-4">';
                echo '  <h6 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Installation Failed:</h6>';
                echo '  <p class="m-0 small">' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
                echo '<div class="d-flex justify-content-end">';
                echo '  <a href="install_day_end.php" class="btn btn-secondary px-4">Retry</a>';
                echo '</div>';
            }
        } else {
            ?>
            <p class="text-muted text-center mb-4">Click below to deploy Day End tables (`day_end_sessions`, `cashier_shifts`, `payment_reconciliation`, `z_reports`, `audit_logs`, `inventory_movements`) into your POS database.</p>
            <form method="POST">
                <button type="submit" name="install" class="btn btn-primary w-100 py-3 fw-bold fs-5">
                    <i class="fas fa-cogs me-2"></i> Run DB Migrations
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="dashboard.php" class="text-secondary small text-decoration-none"><i class="fas fa-chevron-left me-1"></i> Back to Dashboard</a>
            </div>
            <?php
        }
        ?>
    </div>
</div>

</body>
</html>
