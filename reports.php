<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Simple Reports
// Daily Sales
$stmt = $pdo->query("
    SELECT DATE(sale_date) as date, SUM(total_amount) as total_sales, COUNT(id) as total_invoices 
    FROM sales 
    GROUP BY DATE(sale_date) 
    ORDER BY date DESC LIMIT 7
");
$daily_sales = $stmt->fetchAll();

// Top Products
$stmt = $pdo->query("
    SELECT p.product_name, SUM(si.quantity) as qty_sold 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    GROUP BY p.id 
    ORDER BY qty_sold DESC LIMIT 5
");
$top_products = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2>Reports</h2>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Last 7 Days Sales</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoices</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($daily_sales as $ds): ?>
                        <tr>
                            <td><?= $ds['date'] ?></td>
                            <td><?= $ds['total_invoices'] ?></td>
                            <td>LKR <?= number_format($ds['total_sales'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($daily_sales)): ?>
                            <tr><td colspan="3" class="text-center">No sales data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Top Selling Products</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($top_products as $tp): ?>
                        <tr>
                            <td><?= htmlspecialchars($tp['product_name']) ?></td>
                            <td><?= $tp['qty_sold'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($top_products)): ?>
                            <tr><td colspan="2" class="text-center">No sales data.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card text-center py-5 bg-light border-0">
            <div class="card-body">
                <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                <h4>Export Advanced Reports</h4>
                <p class="text-muted">Generate comprehensive PDF reports for inventory, monthly profits, and detailed tax summaries.</p>
                <button class="btn btn-danger" onclick="alert('PDF Generation functionality would be implemented using a library like TCPDF or Dompdf.')">Export to PDF</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
