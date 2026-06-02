<?php
/**
 * api/cancel_sale.php
 * POS System — Transactional Sale Cancellation & Stock Restoration Handler
 */

session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.']);
    exit();
}

$invoice_no = trim($_POST['invoice_no'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';

if (empty($invoice_no)) {
    echo json_encode(['success' => false, 'message' => 'Invoice number is required.']);
    exit();
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Cancellation reason is required.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Fetch and Lock the Sale invoice
    $sale_stmt = $pdo->prepare("SELECT * FROM sales WHERE invoice_no = ? FOR UPDATE");
    $sale_stmt->execute([$invoice_no]);
    $sale = $sale_stmt->fetch();

    if (!$sale) {
        throw new Exception("Sale with invoice number '{$invoice_no}' not found.");
    }

    if ($sale['status'] === 'CANCELLED') {
        throw new Exception("This transaction has already been cancelled.");
    }

    // 2. Fetch and Lock all Purchased Items under this sale
    $items_stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ? FOR UPDATE");
    $items_stmt->execute([$sale['id']]);
    $items = $items_stmt->fetchAll();

    // 3. Restore Quantities back to Product Inventory & Log in Stock Movements
    foreach ($items as $item) {
        $product_id = (int)$item['product_id'];
        $qty = (int)$item['quantity'];

        // 3a. Lock the product row to ensure database level concurrency safety
        $prod_stmt = $pdo->prepare("SELECT stock_quantity, product_name FROM products WHERE id = ? FOR UPDATE");
        $prod_stmt->execute([$product_id]);
        $product = $prod_stmt->fetch();

        if (!$product) {
            throw new Exception("Product ID {$product_id} not found during stock restoration.");
        }

        $prev_stock = (int)$product['stock_quantity'];
        $new_stock = $prev_stock + $qty;

        // 3b. Update stock quantity immediately in products table
        $upd_stock = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $upd_stock->execute([$qty, $product_id]);

        // 3c. Insert detailed record in stock_movements (for full inventory audit trail)
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
            "Restored {$qty} unit(s) via cancellation of {$invoice_no}",
            $user_id
        ]);

        // 3d. Insert record in legacy inventory_logs table (backward compatibility)
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
    }

    // 4. Revert loyalty points if customer was attached
    if (!empty($sale['customer_id'])) {
        $customer_id = (int)$sale['customer_id'];
        $points_to_deduct = floor((float)$sale['total_amount'] / 10);
        if ($points_to_deduct > 0) {
            $upd_cust = $pdo->prepare("UPDATE customers SET loyalty_points = GREATEST(0, loyalty_points - ?) WHERE id = ?");
            $upd_cust->execute([$points_to_deduct, $customer_id]);
        }
    }

    // 5. Update Sale invoice status and details
    $upd_sale = $pdo->prepare("
        UPDATE sales 
        SET status = 'CANCELLED', 
            cancelled_at = NOW(), 
            cancelled_by = ?, 
            cancellation_reason = ? 
        WHERE id = ?
    ");
    $upd_sale->execute([$user_id, $reason, $sale['id']]);

    // 6. Create Audit Log Cancellation record
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $audit_details = json_encode([
        'invoice_no' => $invoice_no,
        'user_id' => $user_id,
        'username' => $username,
        'cancellation_reason' => $reason,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s')
    ]);

    $audit_stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address, created_at)
        VALUES (?, 'SALE_CANCELLATION', ?, ?, NOW())
    ");
    $audit_stmt->execute([$user_id, $audit_details, $ip_address]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Invoice {$invoice_no} cancelled successfully. Inventory stock levels restored."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
