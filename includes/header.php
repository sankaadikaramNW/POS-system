<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// App configuration (version, name, environment)
require_once __DIR__ . '/../config/app.php';

// Prevent browser from caching protected pages — forces fresh request on back button
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DXL Fashion</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm" style="height: 90px; min-height: 90px; padding: 0 15px; display: flex; align-items: center; border: none; border-radius: 0; margin-bottom: 0 !important; box-shadow: 0 2px 10px rgba(0,0,0,0.05); flex-shrink: 0; background: #fff !important;">
                <div class="container-fluid" style="height: 100%; display: flex; align-items: center;">
                    <button type="button" id="sidebarCollapse" class="btn btn-primary">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="ms-auto d-flex align-items-center gap-3">
                        <!-- Version badge in topbar -->
                        <span class="d-none d-lg-inline-flex align-items-center gap-1 px-2 py-1 rounded"
                              style="background:rgba(15,98,254,.08);border:1px solid rgba(15,98,254,.15);font-size:.7rem;font-weight:700;color:#0f62fe;letter-spacing:.03em;">
                            <i class="fas fa-code-branch" style="font-size:.65rem;"></i>
                            <?= APP_VERSION ?>
                        </span>
                        <span class="fw-bold"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?> (<?= ucfirst(htmlspecialchars($_SESSION['role'] ?? '')) ?>)</span>
                        <a href="logout.php" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content Container -->
            <div class="container-fluid" style="padding: 25px 35px !important;">
