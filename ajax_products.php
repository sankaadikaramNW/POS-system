<?php
require_once 'config/database.php';

$search = $_GET['q'] ?? '';

$sql = "SELECT * FROM products WHERE stock_quantity > 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (product_name LIKE ? OR barcode = ?)";
    $params = ["%$search%", $search];
}

$sql .= " LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($products);
?>
