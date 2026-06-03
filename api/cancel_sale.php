<?php
/**
 * api/cancel_sale.php
 * POS System — Transactional Sale Cancellation & Stock Restoration Handler
 *
 * Flow:
 *  1. Auth check
 *  2. Validate input
 *  3. BEGIN TRANSACTION
 *  4. Lock sale row (FOR UPDATE) — fetch & validate status
 *  5. Lock + iterate sale_items — restore stock for each product
 *  6. Log stock_movements + inventory_logs (with graceful table-missing handling)
 *  7. Revert loyalty points if customer attached
 *  8. Mark sale as CANCELLED with reason, cancelled_by, cancelled_at
 *  9. Write audit_logs entry
 * 10. COMMIT
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// ── Authentication ─────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.']);
    exit();
}

// ── Input ──────────────────────────────────────────────────────────────────
$invoice_no = trim($_POST['invoice_no'] ?? '');
$reason     = trim($_POST['reason']     ?? '');
$user_id    = (int)$_SESSION['user_id'];
$username   = $_SESSION['username'] ?? 'unknown';

if (empty($invoice_no)) {
    echo json_encode(['success' => false, 'message' => 'Invoice number is required.']);
    exit();
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Cancellation reason is required.']);
    exit();
}

// ── Helpers ────────────────────────────────────────────────────────────────

/**
 * Check whether a given table exists in the current database.
 * Used to gracefully skip logging to optional audit tables.
 */
function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

// Pre-check optional tables once, outside the transaction
$has_stock_movements = tableExists($pdo, 'stock_movements');
$has_inventory_logs  = tableExists($pdo, 'inventory_logs');
$has_audit_logs      = tableExists($pdo, 'audit_logs');

// ── Main Transaction ───────────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // ── 1. Fetch & Lock the Sale Invoice ──────────────────────────────────
    $sale_stmt = $pdo->prepare("SELECT * FROM sales WHERE invoice_no = ? FOR UPDATE");
    $sale_stmt->execute([$invoice_no]);
    $sale = $sale_stmt->fetch();

    if (!$sale) {
        throw new Exception("Sale with invoice number '{$invoice_no}' not found.");
    }

    // Check if the sale's business day is closed/locked
    require_once __DIR__ . '/../includes/lock_check.php';
    check_day_end_lock($sale['sale_date'], $pdo);

    // ── 2. Idempotency — already cancelled is a success (no-op) ──────────
    if ($sale['status'] === 'CANCELLED') {
        $pdo->rollBack();
        echo json_encode([
            'success' => true,
            'message' => "Invoice {$invoice_no} was already cancelled."
        ]);
        exit();
    }

    // ── 3. Fetch & Lock all Sale Items ────────────────────────────────────
    $items_stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ? FOR UPDATE");
    $items_stmt->execute([$sale['id']]);
    $items = $items_stmt->fetchAll();

    // ── 4. Restore Stock for each Item ────────────────────────────────────
    foreach ($items as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $qty        = (int)($item['quantity']   ?? 0);

        if ($product_id <= 0 || $qty <= 0) {
            // Skip invalid rows — log and continue rather than aborting the whole cancellation
            error_log("[POS][cancel_sale] Skipping invalid item row: product_id={$product_id}, qty={$qty}, sale_id={$sale['id']}");
            continue;
        }

        // Lock the product row
        $prod_stmt = $pdo->prepare("SELECT stock_quantity, product_name FROM products WHERE id = ? FOR UPDATE");
        $prod_stmt->execute([$product_id]);
        $product = $prod_stmt->fetch();

        if (!$product) {
            // Product was deleted after sale — log and skip (do not abort cancellation)
            error_log("[POS][cancel_sale] Product ID {$product_id} not found during stock restore. Invoice: {$invoice_no}");
            continue;
        }

        $prev_stock = (int)$product['stock_quantity'];
        $new_stock  = $prev_stock + $qty;

        // Restore stock atomically
        $upd_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $upd_stock->execute([$qty, $product_id]);

        // ── 4a. stock_movements audit trail ───────────────────────────────
        if ($has_stock_movements) {
            try {
                $sm_stmt = $pdo->prepare("
                    INSERT INTO stock_movements 
                        (product_id, invoice_id, reference_no, movement_type, quantity, 
                         previous_stock, new_stock, notes, user_id, created_at)
                    VALUES (?, ?, ?, 'CANCELLATION', ?, ?, ?, ?, ?, NOW())
                ");
                $sm_stmt->execute([
                    $product_id,
                    $sale['id'],
                    $invoice_no,
                    $qty,
                    $prev_stock,
                    $new_stock,
                    "Restored {$qty} unit(s) via cancellation of {$invoice_no} by {$username}",
                    $user_id
                ]);
            } catch (Exception $e) {
                // Non-fatal — log and continue
                error_log("[POS][cancel_sale][stock_movements] INSERT failed for product {$product_id}: " . $e->getMessage());
            }
        }

        // ── 4b. inventory_logs (legacy backward compatibility) ────────────
        if ($has_inventory_logs) {
            try {
                $log_stmt = $pdo->prepare("
                    INSERT INTO inventory_logs 
                        (product_id, action_type, quantity, invoice_id, reference_no, created_by)
                    VALUES (?, 'CANCELLATION', ?, ?, ?, ?)
                ");
                $log_stmt->execute([
                    $product_id,
                    $qty,
                    $sale['id'],
                    $invoice_no,
                    $user_id
                ]);
            } catch (Exception $e) {
                error_log("[POS][cancel_sale][inventory_logs] INSERT failed for product {$product_id}: " . $e->getMessage());
            }
        }
    }

    // ── 5. Revert Loyalty Points ──────────────────────────────────────────
    if (!empty($sale['customer_id'])) {
        $customer_id      = (int)$sale['customer_id'];
        $points_to_deduct = (int)floor((float)$sale['total_amount'] / 10);
        if ($points_to_deduct > 0) {
            $upd_cust = $pdo->prepare("UPDATE customers SET loyalty_points = GREATEST(0, loyalty_points - ?) WHERE id = ?");
            $upd_cust->execute([$points_to_deduct, $customer_id]);
        }
    }

    // ── 6. Mark Sale as CANCELLED ─────────────────────────────────────────
    $upd_sale = $pdo->prepare("
        UPDATE sales 
        SET status              = 'CANCELLED', 
            cancelled_at        = NOW(), 
            cancelled_by        = ?, 
            cancellation_reason = ? 
        WHERE id = ?
    ");
    $upd_sale->execute([$user_id, $reason, $sale['id']]);

    // ── 7. Audit Log ──────────────────────────────────────────────────────
    if ($has_audit_logs) {
        try {
            $ip_address   = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $audit_details = json_encode([
                'invoice_no'          => $invoice_no,
                'user_id'             => $user_id,
                'username'            => $username,
                'cancellation_reason' => $reason,
                'date'                => date('Y-m-d'),
                'time'                => date('H:i:s')
            ]);

            $audit_stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
                VALUES (?, 'SALE_CANCELLATION', ?, ?, NOW())
            ");
            $audit_stmt->execute([$user_id, $audit_details, $ip_address]);
        } catch (Exception $e) {
            error_log("[POS][cancel_sale][audit_logs] INSERT failed: " . $e->getMessage());
        }
    }

    // ── COMMIT ────────────────────────────────────────────────────────────
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Invoice {$invoice_no} cancelled successfully. Inventory stock levels restored."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[POS][cancel_sale] TRANSACTION FAILED — Invoice: {$invoice_no}, User: {$username}, Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
