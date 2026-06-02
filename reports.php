<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// ── Dropdown data ───────────────────────────────────────────
$users     = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name")->fetchAll();
$customers = $pdo->query("SELECT id, customer_name FROM customers ORDER BY customer_name")->fetchAll();

// ── Filter inputs ───────────────────────────────────────────
$f_invoice   = trim($_GET['invoice']   ?? '');
$f_date_from = trim($_GET['date_from'] ?? '');
$f_date_to   = trim($_GET['date_to']   ?? '');
$f_payment   = trim($_GET['payment']   ?? '');
$f_user      = (int)($_GET['user_id']  ?? 0);
$f_item_code = trim($_GET['item_code'] ?? '');
$f_customer   = (int)($_GET['customer_id'] ?? 0);
$f_card_ref   = trim($_GET['card_ref'] ?? '');

// ── Build query ────────────────────────────────────────────
$where  = ["1=1"];
$params = [];

if ($f_invoice)   { $where[] = "s.invoice_no LIKE ?";       $params[] = "%$f_invoice%"; }
if ($f_date_from) { $where[] = "DATE(s.sale_date) >= ?";     $params[] = $f_date_from; }
if ($f_date_to)   { $where[] = "DATE(s.sale_date) <= ?";     $params[] = $f_date_to; }
if ($f_payment)   { $where[] = "s.payment_method = ?";       $params[] = $f_payment; }
if ($f_user)      { $where[] = "s.created_by = ?";           $params[] = $f_user; }
if ($f_customer)  { $where[] = "s.customer_id = ?";          $params[] = $f_customer; }
if ($f_card_ref)  { $where[] = "s.card_reference = ?";        $params[] = $f_card_ref; }

// item code filter requires a subquery
$item_join = '';
if ($f_item_code) {
    $item_join  = "INNER JOIN sale_items si2 ON si2.sale_id = s.id INNER JOIN products p2 ON p2.id = si2.product_id";
    $where[]    = "(p2.barcode LIKE ? OR p2.product_name LIKE ?)";
    $params[]   = "%$f_item_code%";
    $params[]   = "%$f_item_code%";
}

$where_sql = implode(" AND ", $where);

$sql = "
    SELECT s.*, 
           COALESCE(c.customer_name,'Walk-in') AS customer_name,
           COALESCE(u.full_name,'—') AS cashier_name
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    LEFT JOIN users     u ON u.id = s.created_by
    $item_join
    WHERE $where_sql
    GROUP BY s.id
    ORDER BY s.sale_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// ── Summary totals ─────────────────────────────────────────
$active_sales   = array_filter($sales, function($s) { return $s['status'] !== 'CANCELLED'; });
$total_amount   = array_sum(array_column($active_sales,'total_amount'));
$total_invoices = count($active_sales);
$avg_order      = $total_invoices > 0 ? $total_amount / $total_invoices : 0;

// ── Payment breakdown ──────────────────────────────────────
$pay_breakdown = [];
foreach ($sales as $s) {
    if ($s['status'] === 'CANCELLED') continue;
    $pm = $s['payment_method'];
    $pay_breakdown[$pm] = ($pay_breakdown[$pm] ?? 0) + $s['total_amount'];
}
?>

<div class="premium-dark-page" style="margin:-24px;padding:24px;">

<style>
.rpt-card { background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; box-shadow:0 2px 8px rgba(15,23,42,.06); }
.rpt-summary-card { border-radius:14px; padding:20px 24px; }
.filter-label { font-size:.78rem; font-weight:600; color:#64748b; margin-bottom:4px; display:block; }
.rpt-table thead th { background:#f8fafc; color:#64748b; font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; border:none; border-bottom:2px solid #e2e8f0; padding:12px 14px; }
.rpt-table tbody tr { border-bottom:1px solid #f1f5f9; transition:background .15s; }
.rpt-table tbody tr:hover { background:#f8fafc; }
.rpt-table tbody td { color:#1e293b; vertical-align:middle; border:none; padding:11px 14px; font-size:.87rem; }
.badge-payment { padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
.badge-cash   { background:rgba(34,197,94,.15); color:#16a34a; }
.badge-card   { background:rgba(99,102,241,.15); color:#4f46e5; }
.badge-mobile { background:rgba(251,191,36,.15); color:#b45309; }
.no-print-btn { }
@media print {
    .no-print-btn, #filterPanel, .navbar, #sidebar, .nav { display:none!important; }
    .rpt-table tbody td, .rpt-table thead th { color:#000!important; background:#fff!important; }
}
</style>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold text-white m-0"><i class="fas fa-chart-bar me-2" style="color:var(--primary-orange)"></i>Sales Reports</h2>
        <p class="text-muted mb-0" style="font-size:.84rem;">Filter and analyse all sales transactions</p>
    </div>
    <div class="d-flex gap-2 no-print-btn">
        <button class="btn btn-dark-premium" onclick="document.getElementById('filterPanel').classList.toggle('d-none')">
            <i class="fas fa-filter me-1"></i> Filters <?php if(array_filter([$f_invoice,$f_date_from,$f_date_to,$f_payment,$f_user,$f_item_code,$f_customer])): ?><span class="badge bg-warning text-dark ms-1" style="font-size:.7rem;">ON</span><?php endif; ?>
        </button>
        <button class="btn btn-dark-premium" onclick="window.print()"><i class="fas fa-print me-1"></i> Print</button>
        <a href="reports.php" class="btn btn-dark-premium"><i class="fas fa-redo me-1"></i> Reset</a>
    </div>
</div>

<!-- ── Summary Cards ── -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rpt-summary-card" style="background:linear-gradient(135deg,rgba(249,115,22,.25),rgba(249,115,22,.08));border:1px solid rgba(249,115,22,.25);">
            <div class="d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:12px;background:rgba(249,115,22,.2);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-rupee-sign" style="color:#f97316;font-size:1.2rem;"></i>
                </div>
                <div>
                    <div style="font-size:.75rem;color:#94a3b8;font-weight:600;text-transform:uppercase;">Total Sales</div>
                    <div class="fw-bold text-white" style="font-size:1.35rem;">LKR <?= number_format($total_amount,2) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rpt-summary-card" style="background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(99,102,241,.08));border:1px solid rgba(99,102,241,.25);">
            <div class="d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:12px;background:rgba(99,102,241,.2);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-file-invoice" style="color:#818cf8;font-size:1.2rem;"></i>
                </div>
                <div>
                    <div style="font-size:.75rem;color:#94a3b8;font-weight:600;text-transform:uppercase;">Total Invoices</div>
                    <div class="fw-bold text-white" style="font-size:1.35rem;"><?= number_format($total_invoices) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rpt-summary-card" style="background:linear-gradient(135deg,rgba(34,197,94,.25),rgba(34,197,94,.08));border:1px solid rgba(34,197,94,.25);">
            <div class="d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:12px;background:rgba(34,197,94,.2);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-chart-line" style="color:#22c55e;font-size:1.2rem;"></i>
                </div>
                <div>
                    <div style="font-size:.75rem;color:#94a3b8;font-weight:600;text-transform:uppercase;">Avg. Order Value</div>
                    <div class="fw-bold text-white" style="font-size:1.35rem;">LKR <?= number_format($avg_order,2) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Filter Panel ── -->
<div id="filterPanel" class="rpt-card p-4 mb-4 <?= (array_filter([$f_invoice,$f_date_from,$f_date_to,$f_payment,$f_user,$f_item_code,$f_customer]))?'':'d-none' ?>">
    <h6 class="text-white fw-bold mb-3"><i class="fas fa-sliders-h me-2" style="color:var(--primary-orange)"></i>Filter Criteria</h6>
    
    <!-- Criteria Selection Checkbox Pills -->
    <div class="mb-4 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted fw-semibold me-2" style="font-size: .8rem; text-transform: uppercase;">Select Criterias:</span>
        <label class="criteria-pill-label">
            <input type="checkbox" id="chk_invoice" class="criteria-chk d-none" onchange="toggleCriteria('invoice')">
            <span class="criteria-pill"><i class="fas fa-file-signature me-1"></i> Invoice No.</span>
        </label>
        <label class="criteria-pill-label">
            <input type="checkbox" id="chk_date" class="criteria-chk d-none" onchange="toggleCriteria('date')">
            <span class="criteria-pill"><i class="fas fa-calendar-alt me-1"></i> Date Range</span>
        </label>
        <label class="criteria-pill-label">
            <input type="checkbox" id="chk_payment" class="criteria-chk d-none" onchange="toggleCriteria('payment')">
            <span class="criteria-pill"><i class="fas fa-credit-card me-1"></i> Payment Method</span>
        </label>
        <label class="criteria-pill-label">
            <input type="checkbox" id="chk_user" class="criteria-chk d-none" onchange="toggleCriteria('user')">
            <span class="criteria-pill"><i class="fas fa-user-tie me-1"></i> Cashier / User</span>
        </label>
        <label class="criteria-pill-label">
            <input type="checkbox" id="chk_item" class="criteria-chk d-none" onchange="toggleCriteria('item')">
            <span class="criteria-pill"><i class="fas fa-barcode me-1"></i> Item Code</span>
        </label>
        <label class="criteria-pill-label">
            <input type="checkbox" id="chk_customer" class="criteria-chk d-none" onchange="toggleCriteria('customer')">
            <span class="criteria-pill"><i class="fas fa-user-friends me-1"></i> Customer</span>
        </label>
        <label class="criteria-pill-label">
            <input type="checkbox" id="chk_card_ref" class="criteria-chk d-none" onchange="toggleCriteria('card_ref')">
            <span class="criteria-pill"><i class="fas fa-credit-card me-1"></i> Card Ref</span>
        </label>
    </div>

    <style>
    .criteria-pill-label { cursor: pointer; margin: 0; }
    .criteria-pill { display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; font-size: .8rem; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; transition: all 0.2s; }
    .criteria-chk:checked + .criteria-pill { background: rgba(15,98,254,.1); color: #0f62fe; border-color: #0f62fe; box-shadow: 0 0 10px rgba(15,98,254,.12); }
    .criteria-pill-label:hover .criteria-pill { background: #e2e8f0; color: #1e293b; border-color: #cbd5e1; }
    .criteria-field-group { transition: opacity 0.3s, transform 0.3s; }
    .criteria-field-group.disabled { display: none !important; }
    </style>

    <form method="GET" action="reports.php" id="filterForm">
        <div class="row g-3">
            <div class="col-md-4 col-lg-2 criteria-field-group" id="grp_invoice">
                <label class="filter-label">Invoice No.</label>
                <input type="text" name="invoice" id="input_invoice" class="form-control form-control-sm" placeholder="INV-..." value="<?= htmlspecialchars($f_invoice) ?>">
            </div>
            <div class="col-md-4 col-lg-2 criteria-field-group" id="grp_date_from">
                <label class="filter-label">Date From</label>
                <input type="date" name="date_from" id="input_date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($f_date_from) ?>">
            </div>
            <div class="col-md-4 col-lg-2 criteria-field-group" id="grp_date_to">
                <label class="filter-label">Date To</label>
                <input type="date" name="date_to" id="input_date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($f_date_to) ?>">
            </div>
            <div class="col-md-4 col-lg-2 criteria-field-group" id="grp_payment">
                <label class="filter-label">Payment Method</label>
                <select name="payment" id="input_payment" class="form-select form-select-sm">
                    <option value="">All Methods</option>
                    <?php foreach(['Cash','Card','Mobile'] as $pm): ?>
                    <option value="<?= $pm ?>" <?= $f_payment===$pm?'selected':'' ?>><?= $pm ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-2 criteria-field-group" id="grp_user">
                <label class="filter-label">Cashier / User</label>
                <select name="user_id" id="input_user" class="form-select form-select-sm">
                    <option value="0">All Users</option>
                    <?php foreach($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $f_user==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-2 criteria-field-group" id="grp_item">
                <label class="filter-label">Item Code / Name</label>
                <input type="text" name="item_code" id="input_item" class="form-control form-control-sm" placeholder="Barcode or name..." value="<?= htmlspecialchars($f_item_code) ?>">
            </div>
            <div class="col-md-4 col-lg-3 criteria-field-group" id="grp_customer">
                <label class="filter-label">Customer</label>
                <select name="customer_id" id="input_customer" class="form-select form-select-sm">
                    <option value="0">All Customers</option>
                    <?php foreach($customers as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $f_customer==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['customer_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-2 criteria-field-group" id="grp_card_ref">
                <label class="filter-label">Card Reference (Last 4)</label>
                <input type="text" name="card_ref" id="input_card_ref" class="form-control form-control-sm text-center fw-bold" maxlength="4" placeholder="e.g. 1234" inputmode="numeric" value="<?= htmlspecialchars($f_card_ref) ?>" style="letter-spacing:4px;">
            </div>
            <div class="col-md-4 col-lg-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-orange-premium btn-sm flex-grow-1"><i class="fas fa-search me-1"></i>Apply</button>
                <a href="reports.php" class="btn btn-dark-premium btn-sm"><i class="fas fa-times"></i></a>
            </div>
        </div>
    </form>
</div>

<!-- ── Payment Breakdown ── -->
<?php if (!empty($pay_breakdown)): ?>
<div class="row g-3 mb-4">
    <?php foreach($pay_breakdown as $method => $amount): ?>
    <div class="col-auto">
        <div class="rpt-card px-4 py-2 d-flex align-items-center gap-3">
            <span class="badge-payment badge-<?= strtolower($method) ?>"><?= $method ?></span>
            <span class="fw-bold" style="color:#1e293b;">LKR <?= number_format($amount,2) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Results Table ── -->
<div class="rpt-card p-0">
    <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid rgba(255,255,255,.07);">
        <h6 class="text-white fw-bold m-0"><i class="fas fa-list me-2 text-muted"></i>Sales Records 
            <span class="badge ms-2" style="background:rgba(249,115,22,.2);color:#f97316;font-size:.75rem;"><?= $total_invoices ?></span>
        </h6>
        <input type="text" class="form-control form-control-sm no-print-btn" id="tblSearch" placeholder="Quick search..." onkeyup="quickSearch(this.value)" style="width:200px;">
    </div>
    <div class="table-responsive">
        <table class="table rpt-table m-0" id="rptTable">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Date &amp; Time</th>
                    <th>Customer</th>
                    <th>Cashier</th>
                    <th>Status &amp; Payment</th>
                    <th>Card Ref</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Total</th>
                    <th class="text-center no-print-btn">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($sales)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="fas fa-search d-block fs-2 mb-2 opacity-25"></i>
                    No sales records found for the selected filters.
                </td></tr>
            <?php else: ?>
            <?php foreach($sales as $s): 
                $pm_badge = strtolower($s['payment_method']);
                $sub = $s['total_amount'] + $s['discount'] - $s['tax'];
            ?>
            <tr>
                <td class="fw-bold font-monospace" style="color:#0f62fe;font-size:.82rem;"><?= htmlspecialchars($s['invoice_no']) ?></td>
                <td>
                    <div style="font-size:.84rem;"><?= date('d M Y', strtotime($s['sale_date'])) ?></div>
                    <div style="font-size:.72rem;color:#64748b;"><?= date('h:i A', strtotime($s['sale_date'])) ?></div>
                </td>
                <td><?= htmlspecialchars($s['customer_name']) ?></td>
                <td style="font-size:.83rem;"><?= htmlspecialchars($s['cashier_name']) ?></td>
                <td>
                    <span class="badge-payment badge-<?= $pm_badge ?>"><?= htmlspecialchars($s['payment_method']) ?></span>
                    <?php if ($s['status'] === 'CANCELLED'): ?>
                        <span class="badge bg-danger ms-1" style="font-size:.72rem; font-weight:700;">CANCELLED</span>
                    <?php else: ?>
                        <span class="badge bg-success ms-1" style="font-size:.72rem; font-weight:700;">COMPLETED</span>
                    <?php endif; ?>
                </td>
                <td class="text-center font-monospace fw-bold" style="color:#4f46e5;letter-spacing:3px;">
                    <?= !empty($s['card_reference']) ? '&bull;&bull;&bull;&bull; ' . htmlspecialchars($s['card_reference']) : '<span class="text-muted" style="letter-spacing:normal;font-weight:400;font-size:.75rem;">â€”</span>' ?>
                </td>
                <td class="text-end">LKR <?= number_format($s['total_amount'] + $s['discount'], 2) ?></td>
                <td class="text-end text-danger">-LKR <?= number_format($s['discount'],2) ?></td>
                <td class="text-end fw-bold" style="color:#1e293b; <?= $s['status'] === 'CANCELLED' ? 'text-decoration: line-through; opacity: 0.6;' : '' ?>">LKR <?= number_format($s['total_amount'],2) ?></td>
                <td class="text-center no-print-btn">
                    <div class="d-inline-flex gap-2">
                        <button class="btn btn-sm btn-dark-premium text-info border-0" onclick="viewDetail('<?= htmlspecialchars($s['invoice_no'], ENT_QUOTES) ?>')" title="View Items">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($s['status'] !== 'CANCELLED'): ?>
                        <button class="btn btn-sm btn-dark-premium text-danger border-0" onclick="confirmCancelSale('<?= htmlspecialchars($s['invoice_no'], ENT_QUOTES) ?>')" title="Cancel Sale">
                            <i class="fas fa-ban"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- /premium-dark-page -->

<!-- ── Sale Detail Modal ── -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-receipt-modal">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-invoice-dollar me-2 text-success"></i>Invoice Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="detailBody" style="min-height:200px;">
                <div class="text-center py-5"><div class="spinner-border text-warning" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCriteria(name) {
    const isChecked = document.getElementById('chk_' + name).checked;
    
    // Group fields for date range need special double element handling
    if (name === 'date') {
        const grpFrom = document.getElementById('grp_date_from');
        const grpTo = document.getElementById('grp_date_to');
        const inputFrom = document.getElementById('input_date_from');
        const inputTo = document.getElementById('input_date_to');
        
        if (isChecked) {
            grpFrom.classList.remove('disabled');
            grpTo.classList.remove('disabled');
            inputFrom.removeAttribute('disabled');
            inputTo.removeAttribute('disabled');
        } else {
            grpFrom.classList.add('disabled');
            grpTo.classList.add('disabled');
            inputFrom.setAttribute('disabled', 'disabled');
            inputTo.setAttribute('disabled', 'disabled');
        }
    } else {
        const grp = document.getElementById('grp_' + name);
        const input = document.getElementById('input_' + name);
        
        if (isChecked) {
            grp.classList.remove('disabled');
            input.removeAttribute('disabled');
        } else {
            grp.classList.add('disabled');
            input.setAttribute('disabled', 'disabled');
        }
    }
}

// Initialise checked criteria filters from URL GET variables on load
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    const criteriaMapping = {
        'invoice':  urlParams.get('invoice'),
        'date':     urlParams.get('date_from') || urlParams.get('date_to'),
        'payment':  urlParams.get('payment'),
        'user':     urlParams.get('user_id') && urlParams.get('user_id') !== '0',
        'item':     urlParams.get('item_code'),
        'customer': urlParams.get('customer_id') && urlParams.get('customer_id') !== '0',
        'card_ref': urlParams.get('card_ref')
    };

    // If no filters are active, default to checking date and customer as standard quick filters
    let anyActive = false;
    for (let key in criteriaMapping) {
        if (criteriaMapping[key]) {
            anyActive = true;
            document.getElementById('chk_' + key).checked = true;
        }
    }

    if (!anyActive) {
        // Default select criteria
        document.getElementById('chk_date').checked = true;
        document.getElementById('chk_customer').checked = true;
    }

    // Call toggle on each key to update visibility states
    ['invoice', 'date', 'payment', 'user', 'item', 'customer', 'card_ref'].forEach(toggleCriteria);
});

function quickSearch(val) {
    val = val.toLowerCase();
    document.querySelectorAll('#rptTable tbody tr').forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}

function viewDetail(invoiceNo) {
    document.getElementById('detailBody').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-warning" role="status"></div></div>';
    var modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
    fetch('ajax_sale_detail.php?invoice=' + encodeURIComponent(invoiceNo))
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('detailBody').innerHTML = '<p class="text-danger p-4"><i class="fas fa-exclamation-circle me-2"></i>' + data.message + '</p>';
                return;
            }
            const s = data.sale;
            const items = data.items;
            let rows = items.map(i => `
                <tr>
                    <td class="font-monospace" style="color:#0f62fe;font-size:.82rem;">${i.product_name}</td>
                    <td class="text-center" style="color:#1e293b;">${i.quantity}</td>
                    <td class="text-end" style="color:#1e293b;">LKR ${parseFloat(i.selling_price).toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                    <td class="text-end fw-bold" style="color:#1e293b;">LKR ${parseFloat(i.subtotal).toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                </tr>`).join('');
            
            let stampHtml = '';
            let cancelBtnHtml = '';
            if (s.status === 'CANCELLED') {
                stampHtml = `
                    <div class="text-center mt-3" style="border: 3px double #dc3545; padding: 6px; border-radius: 8px; max-width: 200px; margin-left: auto;">
                        <span style="color: #dc3545; font-weight: 900; font-size: 1.1rem; letter-spacing: 2px;">✗ CANCELLED</span>
                    </div>
                `;
            } else {
                stampHtml = `
                    <div class="text-center mt-3" style="border: 3px double #10b981; padding: 6px; border-radius: 8px; max-width: 200px; margin-left: auto;">
                        <span style="color: #10b981; font-weight: 900; font-size: 1.1rem; letter-spacing: 2px;">✓ PAID</span>
                    </div>
                `;
                cancelBtnHtml = `
                    <button class="btn btn-danger w-100 mt-3 fw-bold no-print-btn" onclick="confirmCancelSale('${s.invoice_no}')">
                        <i class="fas fa-ban me-1"></i> Cancel Transaction (DB-Sync)
                    </button>
                `;
            }

            document.getElementById('detailBody').innerHTML = `
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                        <div>
                            <div class="fw-bold text-dark" style="font-size:1.05rem;">${s.invoice_no}</div>
                            <div class="text-muted" style="font-size:.82rem;">${s.sale_date}</div>
                        </div>
                        <div class="text-end">
                            <div style="font-size:.8rem;color:#64748b;">Cashier: <strong>${s.cashier_name}</strong></div>
                            <div style="font-size:.8rem;color:#64748b;">Customer: <strong>${s.customer_name}</strong></div>
                        </div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm" style="font-size:.85rem;">
                            <thead><tr style="background:rgba(0,0,0,.08);">
                                <th>Product</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Subtotal</th>
                            </tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1" style="font-size:.88rem;">
                        <div style="color:#64748b;">Subtotal: <strong style="color:#1e293b;">LKR ${parseFloat(s.subtotal).toLocaleString('en-US',{minimumFractionDigits:2})}</strong></div>
                        <div style="color:#dc2626;">Discount: <strong>-LKR ${parseFloat(s.discount).toLocaleString('en-US',{minimumFractionDigits:2})}</strong></div>
                        <div class="fw-bold" style="font-size:1.1rem;color:#1e293b;">Total: LKR ${parseFloat(s.total_amount).toLocaleString('en-US',{minimumFractionDigits:2})}</div>
                        <div style="color:#64748b;">Paid: LKR ${parseFloat(s.paid_amount).toLocaleString('en-US',{minimumFractionDigits:2})} &nbsp;|&nbsp; Balance: LKR ${parseFloat(s.balance).toLocaleString('en-US',{minimumFractionDigits:2})}</div>
                        <div class="d-flex gap-2 align-items-center mt-1">
                            <span class="badge" style="background:rgba(16,163,74,.12);color:#16a34a;">${s.payment_method}</span>
                            <span class="badge ${s.status === 'CANCELLED' ? 'bg-danger' : 'bg-success'}">${s.status}</span>
                        </div>
                        ${s.card_reference ? `<div style="margin-top:6px;font-size:.82rem;color:#4f46e5;font-weight:600;letter-spacing:2px;"><i class="fas fa-credit-card me-1" style="font-size:.72rem;"></i> Card Ref: &bull;&bull;&bull;&bull; ${s.card_reference}</div>` : ''}
                    </div>
                    ${stampHtml}
                    ${cancelBtnHtml}
                </div>`;
        })
        .catch(() => { document.getElementById('detailBody').innerHTML = '<p class="text-danger p-4">Error loading detail.</p>'; });
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
                const detailModalEl = document.getElementById('detailModal');
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

<?php require_once 'includes/footer.php'; ?>

