<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_data'])) {
    $cart = json_decode($_POST['cart_data'], true);
    if(empty($cart)) {
        die("Cart is empty");
    }

    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
    $discount = (float)($_POST['discount'] ?? 0);
    $tax_percent = (float)($_POST['tax'] ?? 0);
    $payment_method = $_POST['payment_method'];
    $paid_amount = (float)($_POST['paid_amount']);
    
    $created_by = $_SESSION['user_id'] ?? null;
    $invoice_no = 'INV-' . time() . rand(10, 99);

    try {
        $pdo->beginTransaction();

        // Calculate totals securely on backend
        $subtotal = 0;
        foreach($cart as $item) {
            $subtotal += ($item['price'] * $item['qty']);
        }
        
        $after_discount = $subtotal - $discount;
        $tax_amount = $after_discount * ($tax_percent / 100);
        $total_amount = $after_discount + $tax_amount;
        $balance = $paid_amount - $total_amount;

        // Insert Sale
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, total_amount, discount, tax, payment_method, paid_amount, balance, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_no, $customer_id, $total_amount, $discount, $tax_amount, $payment_method, $paid_amount, $balance, $created_by]);
        
        $sale_id = $pdo->lastInsertId();

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
        }

        // Add Loyalty Points to customer (1 point per $10 spent)
        if($customer_id) {
            $points = floor($total_amount / 10);
            if($points > 0) {
                $stmt = $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?");
                $stmt->execute([$points, $customer_id]);
            }
        }

        $pdo->commit();
        
        // Redirect to receipt
        header("Location: receipt.php?id=" . $sale_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error processing sale: " . $e->getMessage());
    }
}
?>
