<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Get today's sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM sales WHERE DATE(sale_date) = CURDATE()");
$stmt->execute();
$today_sales = $stmt->fetch()['today_sales'];

// Get monthly sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as monthly_sales FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())");
$stmt->execute();
$monthly_sales = $stmt->fetch()['monthly_sales'];

// Total Products
$stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products");
$total_products = $stmt->fetch()['total_products'];

// Total Customers
$stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customers");
$total_customers = $stmt->fetch()['total_customers'];

// Recent Sales
$stmt = $pdo->query("
    SELECT s.invoice_no, s.total_amount, s.sale_date, c.customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.id DESC LIMIT 5
");
$recent_sales = $stmt->fetchAll();

// Low Stock Items
$stmt = $pdo->query("
    SELECT product_name, stock_quantity, reorder_level 
    FROM products 
    WHERE stock_quantity <= reorder_level 
    ORDER BY stock_quantity ASC LIMIT 5
");
$low_stock = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Dashboard Overview</h2>
        <p class="text-muted">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></p>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Today's Sales -->
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2">Today's Sales</h6>
                        <h3 class="mb-0">$<?= number_format($today_sales, 2) ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Monthly Sales -->
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2">Monthly Sales</h6>
                        <h3 class="mb-0">$<?= number_format($monthly_sales, 2) ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Total Products -->
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2">Total Products</h6>
                        <h3 class="mb-0"><?= number_format($total_products) ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Total Customers -->
    <div class="col-md-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-uppercase mb-2">Total Customers</h6>
                        <h3 class="mb-0"><?= number_format($total_customers) ?></h3>
                    </div>
                    <div class="fs-1 opacity-50">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Sales -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Sales</h5>
                <a href="sales.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_sales as $rs): ?>
                            <tr>
                                <td><?= htmlspecialchars($rs['invoice_no']) ?></td>
                                <td><?= htmlspecialchars($rs['customer_name'] ?? 'Walk-in') ?></td>
                                <td>$<?= number_format($rs['total_amount'], 2) ?></td>
                                <td><?= date('M d, Y H:i', strtotime($rs['sale_date'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($recent_sales)): ?>
                            <tr><td colspan="4" class="text-center">No recent sales found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Low Stock -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0 text-danger"><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach($low_stock as $ls): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <?= htmlspecialchars($ls['product_name']) ?>
                        <span class="badge bg-danger rounded-pill"><?= $ls['stock_quantity'] ?> left</span>
                    </li>
                    <?php endforeach; ?>
                    <?php if(empty($low_stock)): ?>
                    <li class="list-group-item px-0 text-muted">All products are sufficiently stocked.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
