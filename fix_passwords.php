<?php
require_once 'config/database.php';
$p = password_hash('password123', PASSWORD_DEFAULT);
$pdo->exec("UPDATE users SET password = '$p'");
echo 'Success! Passwords in fashion_pos have been reset to password123';
