<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DXL Fashion</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
            overflow: hidden;
        }
        .login-left {
            background-color: #0c0d12;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            min-height: 400px;
        }
        .login-right {
            padding: 40px;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .btn-primary {
            background-color: #0f62fe !important;
            border-color: #0f62fe !important;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #0043ce !important;
            border-color: #0043ce !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(15, 98, 254, 0.3);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card login-card">
                <div class="row g-0">
                    <div class="col-md-5 login-left">
                        <img src="assets/images/PSX_20260519_122008.jpg" alt="DXL Fashion Logo" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;">
                    </div>
                    <div class="col-md-7 login-right">
                        <h3 class="mb-1 fw-bold text-dark">DXL Fashion</h3>
                        <p class="text-muted small mb-4">Enter credentials to access the POS system</p>
                        
                        <?php if(isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <form action="auth.php" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold" style="font-size: 0.85rem; color: #475569;">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required style="border-radius: 8px;">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold" style="font-size: 0.85rem; color: #475569;">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required style="border-radius: 8px;">
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" name="login" class="btn btn-primary btn-lg fw-bold" style="border-radius: 8px; font-size: 1rem;">Sign In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
