<?php
session_start();
require_once 'includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$sale_id = $_GET['sale_id'] ?? null;
if (!$sale_id) {
    die("Sale ID missing");
}

// Fetch Sale details
$stmt = $pdo->prepare("SELECT s.*, u.full_name as seller_name FROM sales s JOIN users u ON s.seller_id = u.id WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Sale not found");
}

// Fetch items
$stmt = $pdo->prepare("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
$stmt->execute([$sale_id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?php echo $sale_id; ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 80mm; margin: 0 auto; padding: 10px; font-size: 12px; }
        .center { text-align: center; }
        .line { border-bottom: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Receipt</button>
        <button onclick="window.location.href='seller_dashboard.php'">Back to POS</button>
    </div>

    <div class="center">
        <h2 style="margin: 0;">FEETUP CLOTHING</h2>
        <p style="margin: 5px 0;">123 Fashion Street, City<br>Tel: +123 456 789</p>
    </div>

    <div class="line"></div>
    <p>
        Receipt: #<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?><br>
        Date: <?php echo date('Y-m-d H:i', strtotime($sale['created_at'])); ?><br>
        Seller: <?php echo htmlspecialchars($sale['seller_name']); ?>
    </p>
    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td class="right"><?php echo $item['quantity']; ?></td>
                <td class="right"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="right"><?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="line"></div>
    <div class="right">
        <p>Subtotal: $<?php echo number_format($sale['total_amount'], 2); ?></p>
        <p>Discount: -$<?php echo number_format($sale['discount_amount'], 2); ?></p>
        <p class="bold" style="font-size: 14px;">Total: $<?php echo number_format($sale['final_amount'], 2); ?></p>
    </div>
    <div class="line"></div>

    <div class="center" style="margin-top: 20px;">
        <p>Thank you for shopping with us!</p>
        <p>No returns without receipt.</p>
    </div>

    <script>
        // Auto print on load
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
