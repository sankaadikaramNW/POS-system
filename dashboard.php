<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Get today's sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as today_sales FROM sales WHERE DATE(sale_date) = CURDATE() AND status != 'CANCELLED'");
$stmt->execute();
$today_sales = $stmt->fetch()['today_sales'];

// Get monthly sales
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as monthly_sales FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE()) AND status != 'CANCELLED'");
$stmt->execute();
$monthly_sales = $stmt->fetch()['monthly_sales'];

// Total Products
$stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products");
$total_products = $stmt->fetch()['total_products'];

// Total Stock Units (sum of all stock_quantity in products table)
$stmt = $pdo->query("SELECT COALESCE(SUM(stock_quantity), 0) as total_stock_units FROM products");
$total_stock_units = $stmt->fetch()['total_stock_units'];

// Out of Stock Count
$stmt = $pdo->query("SELECT COUNT(*) as out_of_stock FROM products WHERE stock_quantity = 0");
$out_of_stock_count = $stmt->fetch()['out_of_stock'];

// Low Stock Count
$stmt = $pdo->query("SELECT COUNT(*) as low_stock_count FROM products WHERE stock_quantity > 0 AND stock_quantity <= reorder_level");
$low_stock_count = $stmt->fetch()['low_stock_count'];

// Total Customers
$stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customers");
$total_customers = $stmt->fetch()['total_customers'];

// Active Pending Sales
$stmt = $pdo->query("SELECT COUNT(*) as pending_count FROM pending_sales WHERE status = 'PENDING'");
$pending_count = $stmt->fetch()['pending_count'];

// Recent Sales (excluding cancelled sales)
$stmt = $pdo->query("
    SELECT s.id, s.invoice_no, s.total_amount, s.sale_date, c.customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE s.status != 'CANCELLED'
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

<div class="row g-3 mb-4">
    <!-- Today's Sales -->
    <div class="col-xl col-md-6 col-sm-6">
        <a href="reports.php" class="text-decoration-none">
            <div class="card bg-primary text-white h-100 dashboard-tile">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Today's Sales</h6>
                            <h3 class="mb-0 fw-bold">LKR <?= number_format($today_sales, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <span class="fw-bold fs-3">LKR</span>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <!-- Monthly Sales -->
    <div class="col-xl col-md-6 col-sm-6">
        <a href="reports.php" class="text-decoration-none">
            <div class="card bg-success text-white h-100 dashboard-tile">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Monthly Sales</h6>
                            <h3 class="mb-0 fw-bold">LKR <?= number_format($monthly_sales, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <!-- Total Products -->
    <div class="col-xl col-md-6 col-sm-6">
        <a href="products.php" class="text-decoration-none">
            <div class="card bg-info text-white h-100 dashboard-tile">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Total Products</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($total_products) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-box-open"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <!-- Total Customers -->
    <div class="col-xl col-md-6 col-sm-6">
        <a href="customers.php" class="text-decoration-none">
            <div class="card bg-dark text-white h-100 dashboard-tile" style="background:#4b5563 !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Total Customers</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($total_customers) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <!-- Pending Holds -->
    <div class="col-xl col-md-6 col-sm-6">
        <a href="pending_bills.php" class="text-decoration-none">
            <div class="card h-100 dashboard-tile <?= $pending_count > 0 ? 'pulse-held-active' : '' ?>" style="background: linear-gradient(135deg, #fef3c7, #fde68a) !important; border: 1px solid #f59e0b !important; color: #92400e !important; box-shadow: <?= $pending_count > 0 ? '0 0 15px rgba(245, 158, 11, 0.4)' : 'none' ?>;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Pending Holds</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($pending_count) ?></h3>
                        </div>
                        <div class="fs-1 opacity-70">
                            <i class="fas fa-hourglass-half text-warning animate-pulse"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Second Row: Inventory KPIs (DB-driven) -->
<div class="row g-3 mb-4">
    <!-- Total Stock Units -->
    <div class="col-xl col-md-4 col-sm-6">
        <a href="inventory.php" class="text-decoration-none">
            <div class="card text-white h-100 dashboard-tile" style="background: linear-gradient(135deg, #0ea5e9, #0369a1) !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Total Stock Units</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($total_stock_units) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-cubes"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <!-- Low Stock Items -->
    <div class="col-xl col-md-4 col-sm-6">
        <a href="inventory.php" class="text-decoration-none">
            <div class="card text-white h-100 dashboard-tile" style="background: linear-gradient(135deg, #f59e0b, #b45309) !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Low Stock Items</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($low_stock_count) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <!-- Out of Stock -->
    <div class="col-xl col-md-4 col-sm-6">
        <a href="inventory.php" class="text-decoration-none">
            <div class="card text-white h-100 dashboard-tile <?= $out_of_stock_count > 0 ? 'pulse-held-active' : '' ?>" style="background: linear-gradient(135deg, #ef4444, #991b1b) !important; <?= $out_of_stock_count > 0 ? 'box-shadow: 0 0 15px rgba(239,68,68,0.4);' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-2" style="font-weight:700; font-size:0.75rem; letter-spacing:0.5px;">Out of Stock</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($out_of_stock_count) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3">
    <!-- Recent Sales -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Sales</h5>
                <a href="reports.php" class="btn btn-sm btn-primary">View All</a>
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
                            <tr class="sale-row" style="cursor: pointer;" onclick="viewSaleDetail('<?= htmlspecialchars($rs['invoice_no']) ?>')">
                                <td><i class="fas fa-file-invoice text-primary me-1"></i> <?= htmlspecialchars($rs['invoice_no']) ?></td>
                                <td><?= htmlspecialchars($rs['customer_name'] ?? 'Walk-in') ?></td>
                                <td>LKR <?= number_format($rs['total_amount'], 2) ?></td>
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

<!-- Transaction Detail Modal -->
<div class="modal fade" id="saleDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Transaction Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" style="max-height: 70vh; overflow-y: auto;">
                <div id="saleDetailContent" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Loading...</p>
                </div>
            </div>
            <div class="modal-footer border-top-0 d-flex gap-2">
                <button type="button" class="btn btn-primary flex-grow-1" onclick="printSaleDetail()"><i class="fas fa-print me-2"></i> Print</button>
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal"><i class="fas fa-times me-2"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Admin PIN Modal -->
<div class="modal fade" id="adminPinModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header bg-danger text-white border-0">
                <h6 class="modal-title fw-bold"><i class="fas fa-lock me-2"></i> Admin PIN Required</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <p class="text-muted small mb-3">This bill has already been printed. Please enter the Admin PIN to authorize reprinting.</p>
                <input type="password" id="adminPinInput" class="form-control text-center fw-bold fs-4 mb-3" maxlength="4" placeholder="••••" style="letter-spacing: 10px; border-radius: 8px;">
                <button type="button" class="btn btn-danger w-100 py-2 fw-bold" onclick="verifyAdminPinAndPrint()" style="border-radius: 8px;">Authorize & Print</button>
            </div>
        </div>
    </div>
</div>

<!-- Print container for transaction detail -->
<div id="printDetailContainer" class="print-only"></div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
let currentSaleId = null;
let currentPrintCount = 0;
let currentInvoiceNo = '';
const userRole = '<?= $_SESSION['role'] ?? 'cashier' ?>';

function viewSaleDetail(invoiceNo) {
    currentInvoiceNo = invoiceNo;
    // Show modal with loading state
    let modal = new bootstrap.Modal(document.getElementById('saleDetailModal'));
    $('#saleDetailContent').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Loading transaction...</p>
        </div>
    `);
    modal.show();

    // Fetch sale details via AJAX
    $.get('ajax_sale_detail.php?invoice=' + encodeURIComponent(invoiceNo), function(data) {
        if (data.success) {
            let s = data.sale;
            currentSaleId = s.id;
            currentPrintCount = parseInt(s.print_count) || 0;

            let itemsHtml = '';
            data.items.forEach(function(item) {
                itemsHtml += `
                    <tr>
                        <td style="font-size: 13px;">${item.product_name}</td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-end">LKR ${parseFloat(item.selling_price).toFixed(2)}</td>
                        <td class="text-end">LKR ${parseFloat(item.subtotal).toFixed(2)}</td>
                    </tr>
                `;
            });

            // If print count is 1 or more, it means it's been printed already
            let watermarkHtml = '';
            if (currentPrintCount >= 1) {
                watermarkHtml = `
                    <div class="watermark-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 2.8rem; font-weight: 900; color: rgba(220, 53, 69, 0.12); border: 4px double rgba(220, 53, 69, 0.12); padding: 5px 15px; border-radius: 8px; z-index: 0; pointer-events: none; white-space: nowrap; letter-spacing: 3px;">2ND COPY</div>
                `;
            }

            let stampHtml = '';
            let cancelBtnHtml = '';
            if (s.status === 'CANCELLED') {
                stampHtml = `
                    <div class="text-center mt-3" style="border: 4px double #dc3545; padding: 8px; border-radius: 8px; position: relative; z-index: 5;">
                        <span style="color: #dc3545; font-weight: 900; font-size: 1.4rem; letter-spacing: 3px;">✗ CANCELLED</span>
                    </div>
                `;
            } else {
                stampHtml = `
                    <div class="text-center mt-3" style="border: 4px double #10b981; padding: 8px; border-radius: 8px; position: relative; z-index: 5;">
                        <span style="color: #10b981; font-weight: 900; font-size: 1.4rem; letter-spacing: 3px;">✓ PAID</span>
                    </div>
                `;
                cancelBtnHtml = `
                    <button type="button" class="btn btn-danger w-100 mt-3 fw-bold no-print-btn" onclick="confirmCancelSale('${s.invoice_no}')" style="position: relative; z-index: 10;">
                        <i class="fas fa-ban me-1"></i> Cancel Transaction (DB-Sync)
                    </button>
                `;
            }

            let receiptHtml = `
                <div class="thermal-receipt-preview" id="saleDetailReceipt" style="max-width: 100%; box-shadow: none; border: none; position: relative; overflow: hidden; padding: 10px;">
                    ${watermarkHtml}
                    <div style="position: relative; z-index: 1;">
                        <div class="text-center">
                            <div class="d-inline-block mb-1" style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; border: 1.5px solid #000; background-color: #000; vertical-align: middle;">
                                <img src="assets/images/PSX_20260519_122008.jpg" alt="Logo" style="width: 100%; height: 100%; object-fit: cover; filter: grayscale(1) contrast(1.3); transform: scale(1.22); transform-origin: center center;">
                            </div>
                            <h2 style="margin: 5px 0 0 0; font-weight: 800; font-size: 1.35rem; letter-spacing: 1px;">DXL FASHION</h2>
                            <p style="margin: 3px 0 5px 0;">123 Style Street, City<br>Phone: 123-456-7890</p>
                            <div class="dashed-line"></div>
                            <h3 style="margin: 5px 0; font-weight: 700; font-size: 1.1rem; letter-spacing: 2px;">TRANSACTION DETAIL</h3>
                            <div class="dashed-line"></div>
                        </div>
                        <div style="font-size: 13px; margin: 10px 0;">
                            <p style="margin: 3px 0;"><strong>Invoice:</strong> ${s.invoice_no}</p>
                            <p style="margin: 3px 0;"><strong>Date:</strong> ${s.sale_date}</p>
                            <p style="margin: 3px 0;"><strong>Cashier:</strong> ${s.cashier_name}</p>
                            <p style="margin: 3px 0;"><strong>Customer:</strong> ${s.customer_name}</p>
                        </div>
                        <div class="dashed-line"></div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px dashed #000;">
                                    <th style="text-align: left; padding: 5px 0; font-size: 13px;">Item</th>
                                    <th style="text-align: center; padding: 5px 0; font-size: 13px;">Qty</th>
                                    <th style="text-align: right; padding: 5px 0; font-size: 13px;">Price</th>
                                    <th style="text-align: right; padding: 5px 0; font-size: 13px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>${itemsHtml}</tbody>
                        </table>
                        <div class="dashed-line"></div>
                        <table style="width: 100%; font-size: 13px;">
                            <tr><td>Subtotal:</td><td style="text-align: right;">LKR ${parseFloat(s.subtotal).toFixed(2)}</td></tr>
                            <tr><td>Discount:</td><td style="text-align: right; color: #dc3545;">-LKR ${parseFloat(s.discount).toFixed(2)}</td></tr>
                            <tr><td>Tax:</td><td style="text-align: right;">+LKR ${parseFloat(s.tax).toFixed(2)}</td></tr>
                        </table>
                        <div class="dashed-line"></div>
                        <div class="d-flex justify-content-between" style="font-size: 1.1rem; font-weight: 800;">
                            <span>GRAND TOTAL</span>
                            <span style="color: #0f62fe;">LKR ${parseFloat(s.total_amount).toFixed(2)}</span>
                        </div>
                        <div class="dashed-line"></div>
                        <table style="width: 100%; font-size: 13px;">
                            <tr><td>Paid (${s.payment_method}):</td><td style="text-align: right;">LKR ${parseFloat(s.paid_amount).toFixed(2)}</td></tr>
                            <tr><td>Change:</td><td style="text-align: right;">LKR ${parseFloat(s.balance).toFixed(2)}</td></tr>
                        </table>
                        <div class="dashed-line"></div>
                        ${stampHtml}
                        ${cancelBtnHtml}
                    </div>
                </div>
            `;
            $('#saleDetailContent').html(receiptHtml);
        } else {
            $('#saleDetailContent').html(`
                <div class="text-center py-5 text-danger">
                    <i class="fas fa-exclamation-circle fs-1 mb-2"></i>
                    <p>${data.message || 'Failed to load transaction details.'}</p>
                </div>
            `);
        }
    }, 'json').fail(function() {
        $('#saleDetailContent').html(`
            <div class="text-center py-5 text-danger">
                <i class="fas fa-exclamation-circle fs-1 mb-2"></i>
                <p>Network error. Please try again.</p>
            </div>
        `);
    });
}

function printSaleDetail() {
    let content = document.getElementById('saleDetailReceipt');
    if (!content) return;

    if (currentPrintCount >= 1 && userRole !== 'admin') {
        // Require PIN
        $('#adminPinInput').val('');
        let pinModal = new bootstrap.Modal(document.getElementById('adminPinModal'));
        pinModal.show();
    } else {
        // Admin or first print
        executePrint();
    }
}

function verifyAdminPinAndPrint() {
    let pin = $('#adminPinInput').val();
    if (!pin) {
        alert("Please enter PIN");
        return;
    }
    $.post('api/verify_admin.php', { pin: pin }, function(response) {
        if (response.success) {
            // Hide PIN Modal
            let pinModalEl = document.getElementById('adminPinModal');
            let modalInstance = bootstrap.Modal.getInstance(pinModalEl);
            if (modalInstance) modalInstance.hide();
            executePrint();
        } else {
            alert(response.message || "Invalid Admin PIN");
        }
    }, 'json');
}

function executePrint() {
    $.post('api/increment_print.php', { sale_id: currentSaleId }, function(res) {
        if (res.success) {
            let content = document.getElementById('saleDetailReceipt');
            $('#printDetailContainer').html(content.outerHTML);
            document.body.classList.add('receipt-printing');
            window.print();
            setTimeout(() => { 
                document.body.classList.remove('receipt-printing'); 
                // Refresh modal to reflect incremented count and watermark
                viewSaleDetail(currentInvoiceNo);
            }, 1000);
        }
    }, 'json');
}

function confirmCancelSale(invoiceNo) {
    if (!confirm("Are you sure you want to cancel transaction " + invoiceNo + "?\nThis action will update invoice status to CANCELLED, automatically restore all sold quantities back to inventory stock, and log this cancellation in the database.")) {
        return;
    }
    
    let reason = prompt("Please enter the reason for cancellation:");
    if (reason === null) return;
    reason = reason.trim();
    if (!reason) {
        alert("Cancellation reason is required!");
        return;
    }
    
    // Call backend API
    $.ajax({
        type: 'POST',
        url: 'api/cancel_sale.php',
        data: {
            invoice_no: invoiceNo,
            reason: reason
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(response.message);
                const detailModalEl = document.getElementById('saleDetailModal');
                const detailModal = bootstrap.Modal.getInstance(detailModalEl);
                if (detailModal) detailModal.hide();
                window.location.reload();
            } else {
                alert("Failed to cancel transaction: " + response.message);
            }
        },
        error: function(xhr, status, error) {
            alert("Error communicating with cancellation endpoint: " + error);
        }
    });
}
</script>

<style>
.dashboard-tile {
    transition: all 0.25s ease;
    cursor: pointer;
}
.dashboard-tile:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}
.sale-row:hover {
    background-color: #eef2ff !important;
}
.sale-row td {
    transition: all 0.15s ease;
}
</style>

<?php require_once 'includes/footer.php'; ?>
