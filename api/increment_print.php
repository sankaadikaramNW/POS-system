<?php
// api/increment_print.php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sale_id = $_POST['sale_id'] ?? '';

if (empty($sale_id)) {
    echo json_encode(['success' => false, 'message' => 'Sale ID required']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE sales SET print_count = print_count + 1 WHERE id = ?");
    $stmt->execute([$sale_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
