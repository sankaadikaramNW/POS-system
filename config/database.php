<?php
// config/database.php

// Read environment variables with fallback to local development defaults
$host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'fashion_pos';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$port = getenv('DB_PORT') ?: '3306';
$socket = getenv('DB_SOCKET') ?: null;

try {
    // If DB_SOCKET is provided, connect via Unix Socket (recommended for Google Cloud SQL)
    if ($socket) {
        $dsn = "mysql:unix_socket=$socket;dbname=$db_name;charset=utf8mb4";
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";
    }
    
    $pdo = new PDO($dsn, $username, $password);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>

