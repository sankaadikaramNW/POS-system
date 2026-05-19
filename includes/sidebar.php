<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'cashier';
?>
<nav id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-tshirt"></i> DXL Fashion</h3>
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
        <li class="<?= $current_page == 'suppliers.php' ? 'active' : '' ?>">
            <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
        </li>
        <li class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
            <a href="users.php"><i class="fas fa-user-cog"></i> User Management</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
