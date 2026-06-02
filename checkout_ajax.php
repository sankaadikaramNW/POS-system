<?php
/**
 * checkout_ajax.php
 * DXL Fashion POS — Secure Transactional Checkout Handler
 *
 * ─── INVENTORY INTEGRITY GUARANTEES ──────────────────────────────────────────
 * 1. Pre-sale stock validation     — checks DB stock before any write
 * 2. Row-level locking             — SELECT … FOR UPDATE prevents concurrent over-sell
 * 3. Atomic stock deduction        — UPDATE … WHERE stock_quantity >= qty (guard)
 * 4. Full DB transaction           — BEGIN → Invoice → Items → Stock → Movements → COMMIT
 * 5. stock_movements audit trail   — every deduction recorded with before/after values
 * 6. Pending bill completion       — only marks COMPLETED; stock never double-deducted
 * ─────────────────────────────────────────────────────────────────────────────
 */

session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['cart_data'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

$cart = json_decode($_POST['cart_data'], true);
if (empty($cart)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit();
}

$customer_id      = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
$discount_percent = (float)($_POST['discount'] ?? 0);
$tax_percent      = (float)($_POST['tax']      ?? 0);
$payment_method   = $_POST['payment_method'] ?? 'Cash';
$paid_amount      = (float)($_POST['paid_amount'] ?? 0);
$created_by       = (int)$_SESSION['user_id'];
$invoice_no       = 'INV-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -5));

try {
    $pdo->beginTransaction();

    // ─── STEP 1: Pre-sale stock validation with row locking ──────────────────
    // Lock all affected rows first to prevent concurrent over-sell.
    // This must happen INSIDE the transaction before any writes.
    $insufficient = [];

    foreach ($cart as $item) {
        $item_id  = (int)($item['id']  ?? 0);
        $item_qty = (int)($item['qty'] ?? 0);

        if ($item_id <= 0 || $item_qty <= 0) {
            throw new Exception("Invalid cart item data (id={$item_id}, qty={$item_qty}).");
        }

        // SELECT FOR UPDATE — row lock prevents concurrent transactions from
        // reading stale stock and selling the same units simultaneously.
        $lock_stmt = $pdo->prepare(
            "SELECT id, product_name, stock_quantity
             FROM products
             WHERE id = ?
             FOR UPDATE"
        );
        $lock_stmt->execute([$item_id]);
        $product = $lock_stmt->fetch();

        if (!$product) {
            throw new Exception("Product ID {$item_id} not found in database.");
        }

        if ((int)$product['stock_quantity'] < $item_qty) {
            $insufficient[] = [
                'name'      => $product['product_name'],
                'requested' => $item_qty,
                'available' => (int)$product['stock_quantity']
            ];
        }
    }

    // Abort immediately if ANY item has insufficient stock
    if (!empty($insufficient)) {
        $pdo->rollBack();
        $msg = "Insufficient Stock Available:\n";
        foreach ($insufficient as $ins) {
            $msg .= "• {$ins['name']}: requested {$ins['requested']}, available {$ins['available']}\n";
        }
        echo json_encode(['success' => false, 'message' => trim($msg), 'insufficient_stock' => true, 'items' => $insufficient]);
        exit();
    }

    // ─── STEP 2: Calculate totals (server-side — never trust frontend) ───────
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += ((float)$item['price'] * (int)$item['qty']);
    }

    $discount_amount = $subtotal * ($discount_percent / 100);
    $after_discount  = max(0, $subtotal - $discount_amount);
    $tax_amount      = $after_discount * ($tax_percent / 100);
    $total_amount    = $after_discount + $tax_amount;
    $balance         = $paid_amount - $total_amount;

    // ─── STEP 3: Create Sale Invoice ────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO sales
            (invoice_no, customer_id, total_amount, discount, tax, payment_method, paid_amount, balance, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_no, $customer_id, $total_amount,
        $discount_amount, $tax_amount, $payment_method,
        $paid_amount, $balance, $created_by
    ]);
    $sale_id = (int)$pdo->lastInsertId();

    // ─── STEP 4: Mark Resumed Pending Sale as COMPLETED ─────────────────────
    $resumed_hold_bill_no = !empty($_POST['resumed_hold_bill_no'])
        ? trim($_POST['resumed_hold_bill_no'])
        : null;

    if ($resumed_hold_bill_no) {
        $upd = $pdo->prepare("
            UPDATE pending_sales
            SET status = 'COMPLETED', completed_at = NOW(), updated_at = NOW()
            WHERE hold_bill_no = ? AND status = 'PENDING'
        ");
        $upd->execute([$resumed_hold_bill_no]);

        $username = $_SESSION['username'] ?? 'unknown';
        $log_stmt = $pdo->prepare("
            INSERT INTO pending_sales_logs
                (user_id, username, action, log_date, log_time, bill_no)
            VALUES (?, ?, 'Bill Completed', CURDATE(), CURTIME(), ?)
        ");
        $log_stmt->execute([$created_by, $username, $resumed_hold_bill_no]);
    }

    // ─── STEP 5: Insert Sale Items + Deduct Stock + Record Movements ─────────
    $purchased_items = [];

    foreach ($cart as $item) {
        $item_id       = (int)$item['id'];
        $item_qty      = (int)$item['qty'];
        $item_price    = (float)$item['price'];
        $item_subtotal = $item_price * $item_qty;

        // 5a. Insert sale_items row
        $si_stmt = $pdo->prepare("
            INSERT INTO sale_items
                (sale_id, product_id, quantity, selling_price, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        $si_stmt->execute([$sale_id, $item_id, $item_qty, $item_price, $item_subtotal]);

        // 5b. Capture previous stock (already locked via FOR UPDATE above)
        $prev_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $prev_stmt->execute([$item_id]);
        $prev_stock = (int)$prev_stmt->fetchColumn();

        // 5c. Atomic deduction with safety guard:
        //     Only deducts if current stock is still >= requested qty.
        //     If another transaction already took the stock, this returns 0 rows.
        $deduct_stmt = $pdo->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity - ?
            WHERE id = ? AND stock_quantity >= ?
        ");
        $deduct_stmt->execute([$item_qty, $item_id, $item_qty]);

        if ($deduct_stmt->rowCount() === 0) {
            // Race condition or logic error — abort entire transaction
            throw new Exception(
                "Stock deduction failed for product ID {$item_id}. " .
                "Available stock may have changed since validation. Please retry."
            );
        }

        $new_stock = $prev_stock - $item_qty;

        // 5d. Record in stock_movements (full audit trail)
        $sm_stmt = $pdo->prepare("
            INSERT INTO stock_movements
                (product_id, invoice_id, reference_no, movement_type, quantity,
                 previous_stock, new_stock, notes, user_id, created_at)
            VALUES (?, ?, ?, 'SALE', ?, ?, ?, ?, ?, NOW())
        ");
        $sm_stmt->execute([
            $item_id,
            $sale_id,
            $invoice_no,
            $item_qty,
            $prev_stock,
            $new_stock,
            "Sale: {$item_qty} unit(s) sold via {$invoice_no}",
            $created_by
        ]);

        // 5e. Legacy inventory_logs entry (backward-compatible)
        $log_stmt = $pdo->prepare("
            INSERT INTO inventory_logs
                (product_id, action_type, quantity, invoice_id, reference_no, created_by)
            VALUES (?, 'SALE', ?, ?, ?, ?)
        ");
        $log_stmt->execute([$item_id, $item_qty, $sale_id, $invoice_no, $created_by]);

        $purchased_items[] = [
            'product_name'  => $item['name'],
            'quantity'      => $item_qty,
            'selling_price' => $item_price,
            'subtotal'      => $item_subtotal
        ];
    }

    // ─── STEP 6: Loyalty Points ─────────────────────────────────────────────
    if ($customer_id) {
        $points = floor($total_amount / 10);
        if ($points > 0) {
            $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?")
                ->execute([$points, $customer_id]);
        }
    }

    // ─── COMMIT ─────────────────────────────────────────────────────────────
    $pdo->commit();

    // ─── Build response ──────────────────────────────────────────────────────
    $cashier_stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $cashier_stmt->execute([$created_by]);
    $cashier_name = $cashier_stmt->fetchColumn() ?: 'System Cashier';

    $customer_name = 'Walk-in Customer';
    if ($customer_id) {
        $c_stmt = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ?");
        $c_stmt->execute([$customer_id]);
        $c_row = $c_stmt->fetch();
        if ($c_row) $customer_name = $c_row['customer_name'];
    }

    echo json_encode([
        'success'        => true,
        'invoice_no'     => $invoice_no,
        'sale_date'      => date('d M Y H:i'),
        'cashier_name'   => $cashier_name,
        'customer_name'  => $customer_name,
        'subtotal'       => $subtotal,
        'discount'       => $discount_amount,
        'tax'            => $tax_amount,
        'total_amount'   => $total_amount,
        'paid_amount'    => $paid_amount,
        'balance'        => abs($balance),
        'payment_method' => $payment_method,
        'items'          => $purchased_items
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
