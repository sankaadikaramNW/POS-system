<?php
session_start();
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    die("Invalid Invoice ID");
}

$sale_id = $_GET['id'];

// Get Sale info
$stmt = $pdo->prepare("
    SELECT s.*, c.customer_name, u.full_name as cashier_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN users u ON s.created_by = u.id 
    WHERE s.id = ?
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Invoice not found.");
}

// Get Sale Items
$stmt = $pdo->prepare("
    SELECT si.*, p.product_name 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();

$subtotal = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt <?= $sale['invoice_no'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; background: #eee; font-size: 14px; }
        .receipt-card { background: #fff; width: 350px; margin: 20px auto; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 10px;}
        th, td { padding: 5px 0; border-bottom: 1px dashed #ccc; }
        th { text-align: left; }
        .font-bold { font-weight: bold; }
        .footer { margin-top: 20px; font-size: 12px; }
        
        @media print {
            body { background: #fff; margin: 0; padding: 0; }
            .receipt-card { width: 100%; margin: 0; padding: 0; box-shadow: none; border: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt-card">
    <div class="text-center">
        <h2>DX FASHION</h2>
        <p>123 Style Street, City<br>Phone: 123-456-7890</p>
        <p>--------------------------------</p>
        <p class="font-bold">RECEIPT</p>
    </div>
    
    <p>
        Invoice: <?= $sale['invoice_no'] ?><br>
        Date: <?= date('d M Y H:i', strtotime($sale['sale_date'])) ?><br>
        Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'System') ?><br>
        Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer') ?>
    </p>
    
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $item): ?>
            <?php $subtotal += $item['subtotal']; ?>
            <tr>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>LKR <?= number_format($item['selling_price'], 2) ?></td>
                <td class="text-right">LKR <?= number_format($item['subtotal'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <table style="border: none;">
        <tr>
            <td style="border:none">Subtotal:</td>
            <td style="border:none" class="text-right">LKR <?= number_format($subtotal, 2) ?></td>
        </tr>
        <tr>
            <td style="border:none">Discount:</td>
            <td style="border:none" class="text-right">-LKR <?= number_format($sale['discount'], 2) ?></td>
        </tr>
        <tr>
            <td style="border:none">Tax:</td>
            <td style="border:none" class="text-right">+LKR <?= number_format($sale['tax'], 2) ?></td>
        </tr>
        <tr>
            <td style="border:none" class="font-bold h3">Grand Total:</td>
            <td style="border:none" class="text-right font-bold h3">LKR <?= number_format($sale['total_amount'], 2) ?></td>
        </tr>
        <tr>
            <td style="border:none">Paid (<?= $sale['payment_method'] ?>):</td>
            <td style="border:none" class="text-right">LKR <?= number_format($sale['paid_amount'], 2) ?></td>
        </tr>
        <tr>
            <td style="border:none">Change:</td>
            <td style="border:none" class="text-right">LKR <?= number_format(abs($sale['balance']), 2) ?></td>
        </tr>
    </table>
    
    <div class="text-center footer">
        <p>Thank you for shopping with us!</p>
        <p>Please come again.</p>
    </div>
    
    <div class="text-center no-print" style="margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor:pointer;">Print Receipt</button>
        <a href="sales.php" style="display:block; margin-top: 10px; color: blue;">Back to POS</a>
    </div>
</div>

</body>
</html>
