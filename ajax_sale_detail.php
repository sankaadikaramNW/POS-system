<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$invoice_no = $_GET['invoice'] ?? '';

if (empty($invoice_no)) {
    echo json_encode(['success' => false, 'message' => 'Invoice number required']);
    exit();
}

// Get Sale info
$stmt = $pdo->prepare("
    SELECT s.*, c.customer_name, u.full_name as cashier_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    LEFT JOIN users u ON s.created_by = u.id 
    WHERE s.invoice_no = ?
");
$stmt->execute([$invoice_no]);
$sale = $stmt->fetch();

if (!$sale) {
    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
    exit();
}

// Get Sale Items
$stmt = $pdo->prepare("
    SELECT si.*, p.product_name 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$stmt->execute([$sale['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['subtotal'];
}

echo json_encode([
    'success' => true,
    'sale' => [
        'id' => $sale['id'],
        'print_count' => (int)$sale['print_count'],
        'invoice_no' => $sale['invoice_no'],
        'sale_date' => date('d M Y H:i', strtotime($sale['sale_date'])),
        'cashier_name' => $sale['cashier_name'] ?? 'System',
        'customer_name' => $sale['customer_name'] ?? 'Walk-in Customer',
        'subtotal' => $subtotal,
        'discount' => (float)$sale['discount'],
        'tax' => (float)$sale['tax'],
        'total_amount' => (float)$sale['total_amount'],
        'paid_amount' => (float)$sale['paid_amount'],
        'balance' => abs((float)$sale['balance']),
        'payment_method' => $sale['payment_method']
    ],
    'items' => $items
]);
