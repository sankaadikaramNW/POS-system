<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'cashier';
?>
<nav id="sidebar">
    <div class="sidebar-header d-flex align-items-center gap-3" style="background: #1a252f; border-bottom: 1px solid rgba(0,0,0,0.15); height: 90px; overflow: hidden; padding: 0 20px;">
        <div style="width: 68px; height: 68px; border-radius: 50%; overflow: hidden; border: 2px solid rgba(255, 215, 0, 0.6); flex-shrink: 0; display: flex; align-items: center; justify-content: center; background-color: #0c0d12; box-shadow: 0 0 12px rgba(255, 215, 0, 0.2);">
            <img src="assets/images/PSX_20260519_122008.jpg" alt="DXL Logo" style="width: 100%; height: 100%; object-fit: cover; transform: scale(1.22); transform-origin: center center;">
        </div>
        <div class="d-flex flex-column" style="line-height: 1.25;">
            <span class="fw-bold text-white" style="font-size: 1.25rem; letter-spacing: 0.5px;">DXL Fashion</span>
            <span class="text-muted" style="font-size: 0.76rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #cbd5e1 !important; opacity: 0.9;">Men's & Boys</span>
        </div>
    </div>

    <ul class="list-unstyled components">
        <li class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </li>
        <li class="<?= $current_page == 'sales.php' ? 'active' : '' ?>">
            <a href="sales.php"><i class="fas fa-shopping-cart"></i> POS / Sales</a>
        </li>
        <li class="<?= $current_page == 'products.php' ? 'active' : '' ?>">
            <a href="products.php"><i class="fas fa-box"></i> Products</a>
        </li>
        <?php if($role === 'admin'): ?>
        <li class="<?= $current_page == 'inventory.php' ? 'active' : '' ?>">
            <a href="inventory.php"><i class="fas fa-warehouse"></i> Inventory</a>
        </li>
        <?php endif; ?>
        <li class="<?= $current_page == 'customers.php' ? 'active' : '' ?>">
            <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
        </li>
        <li class="<?= $current_page == 'reports.php' ? 'active' : '' ?>">
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        </li>
        
        <?php if($role === 'admin'): ?>
        <li class="border-top mt-3 pt-3">
            <span class="ps-3 text-muted small fw-bold">ADMINISTRATION</span>
        </li>
        <li class="<?= $current_page == 'day_end.php' ? 'active' : '' ?>">
            <a href="day_end.php" style="color: #0f62fe; font-weight: 700;"><i class="fas fa-calendar-check text-primary"></i> Day End Process</a>
        </li>
        <li class="<?= $current_page == 'item_registration.php' ? 'active' : '' ?>">
            <a href="item_registration.php"><i class="fas fa-layer-group"></i> Item Registration</a>
        </li>
        <li class="<?= $current_page == 'suppliers.php' ? 'active' : '' ?>">
            <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
        </li>
        <li class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
            <a href="users.php"><i class="fas fa-user-cog"></i> User Management</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
