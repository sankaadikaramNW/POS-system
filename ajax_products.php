<?php
/**
 * ajax_products.php
 * DXL Fashion POS — Live Product Loader for POS Billing Screen
 *
 * Returns products from SQL with real-time stock_quantity values.
 * No caching, no mock data — always queries the database directly.
 */

session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$search      = trim($_GET['q']   ?? '');
$category_id = trim($_GET['cat'] ?? '');

// ── Build dynamic query ────────────────────────────────────────────────────
// Show ALL products including out-of-stock so cashiers can see what's depleted.
// The JS addToCart() function already blocks adding zero-stock items.
$sql    = "SELECT p.*, c.category_name, b.brand_name
           FROM products p
           LEFT JOIN categories c ON p.category_id = c.id
           LEFT JOIN brands b     ON p.brand_id    = b.id
           WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql     .= " AND (p.product_name LIKE ? OR p.barcode = ?)";
    $params[] = "%{$search}%";
    $params[] = $search;
}

if ($category_id !== '' && $category_id !== 'all') {
    $sql     .= " AND p.category_id = ?";
    $params[] = (int)$category_id;
}

// Order: in-stock first, then by name. Limit raised to 200 for large catalogues.
$sql .= " ORDER BY p.stock_quantity DESC, p.product_name ASC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

echo json_encode($products);
?>
