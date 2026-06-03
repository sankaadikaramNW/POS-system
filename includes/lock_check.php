<?php
// includes/lock_check.php
// Centralized Day End lock checking helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the given business date is closed.
 * If closed, it blocks execution, audits the attempt, and returns a JSON error or error screen.
 * 
 * @param string $date The business date or transaction datetime.
 * @param PDO $pdo The database connection.
 * @return bool True if the date is open or bypass is active.
 */
function check_day_end_lock($date, $pdo) {
    // 1. Super Admin role always bypasses the lock
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
        // Set bypass variable in DB session to satisfy triggers
        $pdo->exec("SET @bypass_day_end_lock = 1");
        return true;
    }
    
    // Default ensure bypass is disabled for non-super-admins
    $pdo->exec("SET @bypass_day_end_lock = 0");

    // 2. Format target date
    $formatted_date = date('Y-m-d', strtotime($date));

    // 3. Query the status of this business date
    try {
        $stmt = $pdo->prepare("SELECT status FROM day_end_sessions WHERE business_date = ?");
        $stmt->execute([$formatted_date]);
        $status = $stmt->fetchColumn();
    } catch (Exception $e) {
        // If query fails (e.g. table doesn't exist yet during setup), proceed
        return true;
    }

    // 4. Reject if closed
    if ($status === 'closed') {
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $action = "ATTEMPTED_MODIFICATION_ON_CLOSED_DAY";
        $details = "User '$username' attempted modification/access on locked business date: $formatted_date";

        // Log to audit log
        try {
            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
            $log_stmt->execute([$user_id, $action, $details, $ip]);
        } catch (Exception $ex) {
            // Ignore audit log write errors
        }

        // Determine if request is AJAX
        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
                   || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
                   || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        $msg = "This business day has been closed through Day End Process. Modifications are not permitted.";

        if ($is_ajax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit();
        } else {
            // Check if header is already sent, if not, render clean page
            if (!headers_sent()) {
                require_once __DIR__ . '/header.php';
            }
            echo "<div class='container my-5'>
                    <div class='card shadow-lg border-0 mx-auto' style='max-width: 600px; border-radius: 16px;'>
                        <div class='card-body p-5 text-center'>
                            <i class='fas fa-lock fs-1 text-danger mb-4'></i>
                            <h3 class='fw-bold text-dark mb-3'>Record Locked</h3>
                            <p class='text-muted'>$msg</p>
                            <div class='d-flex justify-content-center gap-3 mt-4'>
                                <a href='javascript:history.back()' class='btn btn-secondary px-4 fw-bold'>Go Back</a>
                                <a href='dashboard.php' class='btn btn-primary px-4 fw-bold'>Dashboard</a>
                            </div>
                        </div>
                    </div>
                  </div>";
            require_once __DIR__ . '/footer.php';
            exit();
        }
    }

    return true;
}
?>
