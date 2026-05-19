<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    if(empty($cart)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
        exit();
    }

    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
    $discount_percent = (float)($_POST['discount'] ?? 0);
    $tax_percent = (float)($_POST['tax'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    
    $created_by = $_SESSION['user_id'] ?? null;
    $invoice_no = 'INV-' . time() . rand(10, 99);

    try {
        $pdo->beginTransaction();

        // Calculate totals securely on backend
        $subtotal = 0;
        foreach($cart as $item) {
            $subtotal += ($item['price'] * $item['qty']);
        }
        
        $discount_amount = $subtotal * ($discount_percent / 100);
        $after_discount = $subtotal - $discount_amount;
        if ($after_discount < 0) $after_discount = 0;
        
        $tax_amount = $after_discount * ($tax_percent / 100);
        $total_amount = $after_discount + $tax_amount;
        $balance = $paid_amount - $total_amount;

        // Insert Sale
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, total_amount, discount, tax, payment_method, paid_amount, balance, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_no, $customer_id, $total_amount, $discount_amount, $tax_amount, $payment_method, $paid_amount, $balance, $created_by]);
        
        $sale_id = $pdo->lastInsertId();

        $purchased_items = [];

        // Insert Sale Items & Deduct Stock
        foreach($cart as $item) {
            $item_subtotal = $item['price'] * $item['qty'];
            
            // Insert Item
            $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sale_id, $item['id'], $item['qty'], $item['price'], $item_subtotal]);
            
            // Deduct Stock
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $stmt->execute([$item['qty'], $item['id']]);

            // Log Inventory
            $stmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, action_type, quantity, created_by) VALUES (?, 'SALE', ?, ?)");
            $stmt->execute([$item['id'], $item['qty'], $created_by]);

            $purchased_items[] = [
                'product_name' => $item['name'],
                'quantity' => $item['qty'],
                'selling_price' => $item['price'],
                'subtotal' => $item_subtotal
            ];
        }

        // Add Loyalty Points to customer (1 point per 10 LKR spent)
        if($customer_id) {
            $points = floor($total_amount / 10);
            if($points > 0) {
                $stmt = $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?");
                $stmt->execute([$points, $customer_id]);
            }
        }

        $pdo->commit();

        // Get Cashier Name
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$created_by]);
        $cashier_name = $stmt->fetch()['full_name'] ?? 'System Cashier';

        // Get Customer Name
        $customer_name = 'Walk-in Customer';
        if ($customer_id) {
            $stmt = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $res = $stmt->fetch();
            if ($res) {
                $customer_name = $res['customer_name'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'invoice_no' => $invoice_no,
            'sale_date' => date('d M Y H:i'),
            'cashier_name' => $cashier_name,
            'customer_name' => $customer_name,
            'subtotal' => $subtotal,
            'discount' => $discount_amount,
            'tax' => $tax_amount,
            'total_amount' => $total_amount,
            'paid_amount' => $paid_amount,
            'balance' => abs($balance),
            'payment_method' => $payment_method,
            'items' => $purchased_items
        ]);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}
?>
