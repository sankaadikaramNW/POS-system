<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}
$search = $_GET['q'] ?? '';
$category_id = $_GET['cat'] ?? '';

$sql = "SELECT * FROM products WHERE stock_quantity > 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (product_name LIKE ? OR barcode = ?)";
    $params[] = "%$search%";
    $params[] = $search;
}

if (!empty($category_id) && $category_id !== 'all') {
    $sql .= " AND category_id = ?";
    $params[] = $category_id;
}

$sql .= " LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($products);
?>
