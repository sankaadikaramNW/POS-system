<?php
// day_end.php
require_once 'config/database.php';
require_once 'includes/header.php';

// Authorization check: Admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger py-4 text-center my-5"><i class="fas fa-exclamation-triangle fs-1 d-block mb-3"></i><h4 class="fw-bold">Access Denied</h4><p class="m-0">Only administrators and managers have permissions to perform the Day End Process.</p></div>';
    require_once 'includes/footer.php';
    exit();
}

// Fetch current active business date
try {
    $session_stmt = $pdo->query("SELECT business_date, status FROM day_end_sessions WHERE status = 'open' ORDER BY business_date DESC LIMIT 1");
    $session = $session_stmt->fetch();
    
    if (!$session) {
        // Redirect to installation or prompt initialization
        echo '<div class="card shadow-sm border-0 my-5 mx-auto text-center" style="max-width: 500px; border-radius: 16px;">
                <div class="card-body p-5">
                    <i class="fas fa-tools fs-1 text-primary mb-3"></i>
                    <h4 class="fw-bold">Initialize Day End Sessions</h4>
                    <p class="text-muted small mb-4">No active business date was found. Please run the DB installation and session initializer first.</p>
                    <a href="install_day_end.php" class="btn btn-primary px-4 py-2 fw-bold">Initialize System <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
              </div>';
        require_once 'includes/footer.php';
        exit();
    }
    
    $business_date = $session['business_date'];
} catch (PDOException $e) {
    echo '<div class="alert alert-warning py-4 text-center my-5"><i class="fas fa-database fs-1 d-block mb-3"></i><h4 class="fw-bold">Setup Required</h4><p class="m-0">Database tables missing. Please run the setup script to establish tables first.</p><a href="install_day_end.php" class="btn btn-warning mt-3 fw-bold">Run Setup Installer</a></div>';
    require_once 'includes/footer.php';
    exit();
}

?>

<style>
    .wizard-step-panel { display: none; }
    .wizard-step-panel.active { display: block; animation: fadeIn 0.3s ease-in-out; }
    
    /* Timeline styles */
    .step-timeline { display: flex; justify-content: space-between; position: relative; margin-bottom: 40px; padding: 0 10px; }
    .step-timeline::before { content: ''; position: absolute; top: 15px; left: 0; right: 0; height: 3px; background: #e2e8f0; z-index: 1; }
    .timeline-progress { position: absolute; top: 15px; left: 0; height: 3px; background: #0f62fe; z-index: 2; transition: width 0.3s ease; width: 0%; }
    .timeline-node { display: flex; flex-column: column; align-items: center; position: relative; z-index: 3; cursor: pointer; text-align: center; width: 50px; }
    .node-icon { width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; color: #64748b; transition: all 0.3s; }
    .timeline-node.active .node-icon { background: #0f62fe; border-color: #0f62fe; color: #ffffff; box-shadow: 0 0 10px rgba(15, 98, 254, 0.4); }
    .timeline-node.completed .node-icon { background: #16a34a; border-color: #16a34a; color: #ffffff; }
    .node-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-top: 6px; white-space: nowrap; transition: color 0.3s; position: absolute; top: 35px; width: 100px; text-align: center; }
    .timeline-node.active .node-label { color: #0f62fe; }
    .timeline-node.completed .node-label { color: #16a34a; }

    .z-receipt { max-width: 420px; margin: 0 auto; background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 4px; font-family: 'Courier New', Courier, monospace; color: #000000; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .z-receipt .dashed-line { border-top: 1px dashed #000; margin: 10px 0; }
    
    .day-close-btn { background: linear-gradient(135deg, #dc2626, #991b1b); color: white; border: none; font-size: 1.15rem; transition: transform 0.2s, box-shadow 0.2s; border-radius: 12px; }
    .day-close-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(220, 38, 38, 0.35); color: white; }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media print {
        body * { display: none !important; }
        #printArea, #printArea * { display: block !important; }
        #printArea { position: absolute; left: 0; top: 0; width: 100%; }
        .z-receipt { box-shadow: none !important; border: none !important; }
    }
</style>

<!-- Load Chart.js for step 4 reports -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="row mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold m-0"><i class="fas fa-calendar-check text-primary me-2"></i> Day End Reconciliation Process</h4>
        <p class="text-muted small m-0">Reconcile till counts, analyze sales summary reports, sync inventory ledger, and finalize Z-Report for lock down</p>
    </div>
    <div class="col-md-4 text-end">
        <div class="d-inline-flex align-items-center bg-white border rounded-pill px-3 py-1.5 shadow-sm">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-2">ACTIVE BUSINESS DATE</span>
            <strong class="font-monospace text-dark text-uppercase"><?= date('d M Y', strtotime($business_date)) ?></strong>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
    <div class="card-body p-4">
        
        <!-- Timeline Navigation Nodes -->
        <div class="step-timeline">
            <div class="timeline-progress" id="timelineProgress"></div>
            
            <?php 
            $stepLabels = ['Verify Sales', 'Cashier Shift', 'Payments', 'Analytics', 'Inventory', 'Z Report', 'DB Backup', 'Lock Day'];
            $stepIcons = ['check-circle', 'cash-register', 'credit-card', 'chart-line', 'warehouse', 'file-invoice', 'database', 'lock'];
            for ($i = 0; $i < 8; $i++): 
            ?>
                <div class="timeline-node <?= $i===0?'active':'' ?>" onclick="navigateToStep(<?= $i ?>)" id="node_<?= $i ?>">
                    <div class="node-icon"><i class="fas fa-<?= $stepIcons[$i] ?>"></i></div>
                    <div class="node-label"><?= $stepLabels[$i] ?></div>
                </div>
            <?php endfor; ?>
        </div>

        <hr class="my-4 text-muted opacity-25">

        <!-- STEP 1: Verify Transactions -->
        <div class="wizard-step-panel active" id="panel_0">
            <div class="row align-items-center mb-4">
                <div class="col-md-9">
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-clipboard-check text-primary me-2"></i> Step 1: Verify Open Transactions</h5>
                    <p class="text-muted small m-0">Verifying invoices with outstanding balances. Complete reconciliation requires all balances to be cleared.</p>
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="checkPendingTransactions()"><i class="fas fa-sync me-1"></i> Check Again</button>
                </div>
            </div>

            <div id="pendingTransactionsLoader" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 small">Scanning current transactions...</p>
            </div>

            <div id="pendingTransactionsEmpty" class="alert alert-success d-flex align-items-center d-none p-4" style="border-radius: 12px;">
                <i class="fas fa-check-circle fs-3 me-3"></i>
                <div>
                    <h6 class="fw-bold m-0 text-success">All Transactions Finalized!</h6>
                    <p class="m-0 small opacity-75">No invoices with unpaid balances detected for this business date. You are safe to continue.</p>
                </div>
            </div>

            <div id="pendingTransactionsWarning" class="alert alert-warning d-none p-4" style="border-radius: 12px;">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-exclamation-triangle fs-3 me-3 text-warning"></i>
                    <div>
                        <h6 class="fw-bold m-0">Pending Invoice Balances Detected!</h6>
                        <p class="m-0 small opacity-75">The following invoices are incomplete or have outstanding customer balances. Clear balances before day close.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle bg-white rounded border m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No.</th>
                                <th>Cashier</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Paid Amount</th>
                                <th class="text-end">Unpaid Balance</th>
                            </tr>
                        </thead>
                        <tbody id="pendingTransactionsList"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- STEP 2: Cashier Shift Closing -->
        <div class="wizard-step-panel" id="panel_1">
            <h5 class="fw-bold text-dark mb-2"><i class="fas fa-cash-register text-primary me-2"></i> Step 2: Cashier Shift Closing</h5>
            <p class="text-muted small mb-4">Select each cashier and input their counted closing drawer cash. The system will auto-calculate shortages/overages.</p>

            <div class="table-responsive">
                <table class="table table-hover align-middle border rounded" style="overflow: hidden;">
                    <thead class="table-light">
                        <tr>
                            <th>Cashier Name</th>
                            <th>Shift Start</th>
                            <th class="text-end">Opening Cash</th>
                            <th class="text-end">Expected Cash</th>
                            <th class="text-end" style="width: 160px;">Counted Cash (LKR)</th>
                            <th class="text-end">Variance</th>
                            <th class="text-center">Shift Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="cashierShiftList">
                        <!-- Cashier shifts will load here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STEP 3: Payment Reconciliation -->
        <div class="wizard-step-panel" id="panel_2">
            <h5 class="fw-bold text-dark mb-2"><i class="fas fa-credit-card text-primary me-2"></i> Step 3: Payment Gateway Reconciliation</h5>
            <p class="text-muted small mb-4">Input counts from external card machines or mobile wallet transaction history slips to compare with POS records.</p>

            <form id="paymentReconcileForm" onsubmit="submitPaymentReconcile(event)">
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle border rounded">
                        <thead class="table-light">
                            <tr>
                                <th>Payment Method</th>
                                <th class="text-end">POS Total Amount</th>
                                <th class="text-end" style="width: 220px;">Gateway/Machine Count (LKR)</th>
                                <th class="text-end">Variance Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-bold text-dark"><span class="badge bg-success-subtle text-success me-2"><i class="fas fa-wallet"></i></span> Cash sales</td>
                                <td class="text-end fw-bold text-secondary font-monospace" id="posCashText">LKR 0.00</td>
                                <td>
                                    <input type="number" step="0.01" id="gatewayCash" class="form-control form-control-sm text-end font-monospace fw-bold" placeholder="0.00" oninput="calculatePaymentVariance('Cash')">
                                </td>
                                <td class="text-end fw-bold font-monospace" id="varianceCash">LKR 0.00</td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-dark"><span class="badge bg-primary-subtle text-primary me-2"><i class="fas fa-credit-card"></i></span> Card machine</td>
                                <td class="text-end fw-bold text-secondary font-monospace" id="posCardText">LKR 0.00</td>
                                <td>
                                    <input type="number" step="0.01" id="gatewayCard" class="form-control form-control-sm text-end font-monospace fw-bold" placeholder="0.00" oninput="calculatePaymentVariance('Card')">
                                </td>
                                <td class="text-end fw-bold font-monospace" id="varianceCard">LKR 0.00</td>
                            </tr>
                            <tr>
                                <td class="fw-bold text-dark"><span class="badge bg-warning-subtle text-warning me-2"><i class="fas fa-qrcode"></i></span> Mobile / QR Pay</td>
                                <td class="text-end fw-bold text-secondary font-monospace" id="posMobileText">LKR 0.00</td>
                                <td>
                                    <input type="number" step="0.01" id="gatewayMobile" class="form-control form-control-sm text-end font-monospace fw-bold" placeholder="0.00" oninput="calculatePaymentVariance('Mobile')">
                                </td>
                                <td class="text-end fw-bold font-monospace" id="varianceMobile">LKR 0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-check-double me-2"></i> Save & Reconcile Channels</button>
                </div>
            </form>
        </div>

        <!-- STEP 4: Sales Summary / Analytics -->
        <div class="wizard-step-panel" id="panel_3">
            <h5 class="fw-bold text-dark mb-2"><i class="fas fa-chart-line text-primary me-2"></i> Step 4: Sales Analytics & Summary Reports</h5>
            <p class="text-muted small mb-4">Enterprise reporting metrics. Review performance indicators for the business date.</p>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded" style="border-left: 4px solid #0f62fe;">
                        <span class="text-muted small fw-bold d-block">GROSS SALES</span>
                        <h4 class="fw-bold m-0 font-monospace text-dark" id="statGross">LKR 0.00</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded" style="border-left: 4px solid #dc2626;">
                        <span class="text-muted small fw-bold d-block">TOTAL DISCOUNTS</span>
                        <h4 class="fw-bold m-0 font-monospace text-danger" id="statDiscounts">-LKR 0.00</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded" style="border-left: 4px solid #16a34a;">
                        <span class="text-muted small fw-bold d-block">NET REVENUE</span>
                        <h4 class="fw-bold m-0 font-monospace text-success" id="statNet">LKR 0.00</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-light rounded" style="border-left: 4px solid #b45309;">
                        <span class="text-muted small fw-bold d-block">TAXES</span>
                        <h4 class="fw-bold m-0 font-monospace text-warning" id="statTax">LKR 0.00</h4>
                    </div>
                </div>
            </div>

            <!-- Chart Row -->
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border shadow-none" style="border-radius: 12px;">
                        <div class="card-header bg-transparent fw-bold py-3"><i class="fas fa-clock text-primary me-2"></i> Hourly sales distribution</div>
                        <div class="card-body">
                            <canvas id="hourlySalesChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border shadow-none" style="border-radius: 12px;">
                        <div class="card-header bg-transparent fw-bold py-3"><i class="fas fa-th-large text-primary me-2"></i> Product categories</div>
                        <div class="card-body">
                            <canvas id="categorySalesChart" height="220"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border shadow-none" style="border-radius: 12px;">
                        <div class="card-header bg-transparent fw-bold py-3"><i class="fas fa-award text-primary me-2"></i> Best selling products</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush" id="bestSellersList">
                                <!-- dynamic best sellers -->
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border shadow-none" style="border-radius: 12px;">
                        <div class="card-header bg-transparent fw-bold py-3"><i class="fas fa-user-friends text-primary me-2"></i> Cashier contributions</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush" id="cashierSalesList">
                                <!-- dynamic cashier contributions -->
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 5: Inventory Synchronization -->
        <div class="wizard-step-panel" id="panel_4">
            <h5 class="fw-bold text-dark mb-2"><i class="fas fa-warehouse text-primary me-2"></i> Step 5: Inventory Synchronization</h5>
            <p class="text-muted small mb-4">Deduct sold stock quantities, reconcile system balances, and identify critical low-stock alert products.</p>

            <div class="row align-items-center mb-4">
                <div class="col">
                    <div class="alert alert-info d-flex align-items-center m-0" style="border-radius: 12px;">
                        <i class="fas fa-info-circle fs-4 me-3"></i>
                        <div>
                            <h6 class="fw-bold m-0">Synchronize Inventory Ledger</h6>
                            <p class="m-0 small">This action updates inventory movements and verifies ledger consistency.</p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary px-4 fw-bold" onclick="syncInventory()"><i class="fas fa-cogs me-1"></i> Sync Inventory Ledger</button>
                </div>
            </div>

            <div class="card border shadow-none d-none" id="inventoryResultCard" style="border-radius: 12px;">
                <div class="card-header bg-transparent fw-bold text-success"><i class="fas fa-check-double me-2"></i> Synchronization Success!</div>
                <div class="card-body">
                    <p class="m-0 mb-3 text-secondary font-monospace" id="syncStatsText">Total items processed: 0</p>
                    
                    <h6 class="fw-bold text-danger mb-2"><i class="fas fa-exclamation-triangle me-1"></i> Low Stock Alerts (<span id="lowStockCount">0</span>)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover border align-middle m-0 bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Name</th>
                                    <th class="text-center">Current Stock</th>
                                    <th class="text-center">Reorder Threshold</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="lowStockAlertTableBody">
                                <!-- Low stock alarms loaded via sync -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 6: Z-Report Generation -->
        <div class="wizard-step-panel" id="panel_5">
            <div class="row align-items-center mb-4">
                <div class="col-md-9">
                    <h5 class="fw-bold text-dark m-0"><i class="fas fa-file-invoice text-primary me-2"></i> Step 6: Z-Report Verification & Printing</h5>
                    <p class="text-muted small m-0">Review the finalized high-fidelity Z-Report before locking. You can print physical copies for paper archives.</p>
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn btn-outline-dark px-3 fw-bold btn-sm rounded-pill" onclick="window.print()"><i class="fas fa-print me-1"></i> Print Z-Report</button>
                </div>
            </div>

            <!-- Thermal Receipt container -->
            <div class="z-receipt p-4 font-monospace text-dark" id="printArea">
                <div class="text-center">
                    <h5 class="fw-bold m-0" style="letter-spacing: 2px;">DXL FASHION</h5>
                    <small>123 Style Street, Colombo<br>Phone: 123-456-7890</small>
                    <div class="dashed-line"></div>
                    <h6 class="fw-bold m-0" style="letter-spacing: 3px;">DAILY Z REPORT</h6>
                    <small>BUSINESS DATE: <span class="businessDateText">2026-05-18</span></small>
                </div>

                <div class="dashed-line"></div>
                
                <table class="w-100 table-sm" style="font-size: 0.85rem;">
                    <tr><td>Z-Report ID:</td><td class="text-end fw-bold">#<?= rand(100000, 999999) ?></td></tr>
                    <tr><td>Branch Name:</td><td class="text-end">Main Branch</td></tr>
                    <tr><td>Generated:</td><td class="text-end" id="zGeneratedTime">2026-05-18 17:00</td></tr>
                    <tr><td>Status:</td><td class="text-end text-danger fw-bold">UNLOCKED</td></tr>
                </table>

                <div class="dashed-line"></div>
                <span class="fw-bold d-block text-center small">--- SALES RECONCILIATION SUMMARY ---</span>
                <div class="dashed-line"></div>

                <table class="w-100 table-sm" style="font-size: 0.85rem;">
                    <tr><td>Gross Sales:</td><td class="text-end font-monospace text-dark" id="zGross">LKR 0.00</td></tr>
                    <tr><td>Discounts Given:</td><td class="text-end font-monospace text-danger" id="zDiscounts">-LKR 0.00</td></tr>
                    <tr><td>Taxes Reconciled:</td><td class="text-end font-monospace text-dark" id="zTax">LKR 0.00</td></tr>
                    <tr class="fw-bold" style="border-top: 1px dashed #000;">
                        <td>Net Sales Revenue:</td>
                        <td class="text-end font-monospace text-dark" id="zNet">LKR 0.00</td>
                    </tr>
                    <tr class="fw-bold text-success-emphasis" style="border-top: 1.5px solid #000;">
                        <td>Est. Net Profit (COGS):</td>
                        <td class="text-end font-monospace text-success fw-bold" id="zProfit">LKR 0.00</td>
                    </tr>
                </table>

                <div class="dashed-line"></div>
                <span class="fw-bold d-block text-center small">--- PAYMENTS & DRAWER COUNTS ---</span>
                <div class="dashed-line"></div>

                <table class="w-100 table-sm" style="font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 1px dashed #000;">
                            <th>Method</th>
                            <th class="text-end">POS Tot</th>
                            <th class="text-end">Gate Tot</th>
                            <th class="text-end">Var</th>
                        </tr>
                    </thead>
                    <tbody id="zReportPaymentTable">
                        <!-- Dynamic payment nodes -->
                    </tbody>
                </table>

                <div class="dashed-line"></div>
                <span class="fw-bold d-block text-center small">--- CASHIER RECONCILIATION ---</span>
                <div class="dashed-line"></div>

                <table class="w-100 table-sm" style="font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 1px dashed #000;">
                            <th>Cashier</th>
                            <th class="text-end">Expected</th>
                            <th class="text-end">Counted</th>
                            <th class="text-end">Var</th>
                        </tr>
                    </thead>
                    <tbody id="zReportCashierTable">
                        <!-- Dynamic cashier list -->
                    </tbody>
                </table>

                <div class="dashed-line"></div>
                <div class="text-center small mt-3">
                    <p class="m-0"><strong>DXL Fashion POS - Enterprise Edition</strong></p>
                    <p class="m-0 text-muted">End of Business Reconciled Report</p>
                </div>
            </div>
        </div>

        <!-- STEP 7: Database Backup -->
        <div class="wizard-step-panel" id="panel_6">
            <h5 class="fw-bold text-dark mb-2"><i class="fas fa-database text-primary me-2"></i> Step 7: Database Backup</h5>
            <p class="text-muted small mb-4">Execute secure SQL database backups before final transaction locking.</p>

            <div class="row align-items-center mb-4">
                <div class="col">
                    <div class="alert alert-warning d-flex align-items-center m-0" style="border-radius: 12px;">
                        <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                        <div>
                            <h6 class="fw-bold m-0">Secure System Tables</h6>
                            <p class="m-0 small">This writes copy of sales, inventory, cashier and reconciliation records into system storage.</p>
                        </div>
                    </div>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary px-4 fw-bold" onclick="runBackup()"><i class="fas fa-save me-1"></i> Execute Database Backup</button>
                </div>
            </div>

            <div id="backupResultCard" class="alert alert-success d-flex align-items-center d-none p-4" style="border-radius: 12px;">
                <i class="fas fa-check-circle fs-3 me-3 text-success"></i>
                <div>
                    <h6 class="fw-bold m-0 text-success">Backup Created Successfully!</h6>
                    <p class="m-0 small opacity-75">Database exported to file: <strong class="font-monospace text-dark" id="backupFileNameText">backup.sql</strong></p>
                </div>
            </div>
        </div>

        <!-- STEP 8: Lock Day & Finalize -->
        <div class="wizard-step-panel" id="panel_7">
            <h5 class="fw-bold text-dark mb-2"><i class="fas fa-lock text-primary me-2"></i> Step 8: Finalize & Lock Business Day</h5>
            <p class="text-muted small mb-4">This permanently locks transactions for today's date and rolls the system date over to the next business period.</p>

            <div class="alert alert-danger p-4 mb-4" style="border-radius: 12px;">
                <h6 class="fw-bold mb-2"><i class="fas fa-exclamation-triangle me-2"></i> Critical Warnings:</h6>
                <ul class="m-0 pl-3 small">
                    <li>This locks invoices for business date <strong><?= date('Y-m-d') ?></strong>. Re-editing is strictly blocked.</li>
                    <li>Ensure Z-Reports have been verified and physical receipt printed if needed.</li>
                    <li>The system active date will automatically advance to tomorrow's business date.</li>
                </ul>
            </div>

            <div class="text-center py-4">
                <button class="btn day-close-btn px-5 py-3 fw-bold shadow" onclick="finalizeDayClose()"><i class="fas fa-calendar-times me-2"></i> Permanently Lock & Close Day</button>
            </div>
        </div>

        <hr class="my-4 text-muted opacity-25">

        <!-- Navigation Buttons -->
        <div class="d-flex justify-content-between">
            <button class="btn btn-outline-secondary px-4 fw-bold rounded-pill" id="prevBtn" onclick="navigateStepChange(-1)" disabled><i class="fas fa-arrow-left me-1"></i> Back</button>
            <button class="btn btn-primary px-4 fw-bold rounded-pill" id="nextBtn" onclick="navigateStepChange(1)">Next <i class="fas fa-arrow-right ms-1"></i></button>
        </div>

    </div>
</div>

<script>
    let currentStep = 0;
    const totalSteps = 8;
    const businessDate = '<?= $business_date ?>';
    
    // POS payment total caches
    let posCashAmount = 0;
    let posCardAmount = 0;
    let posMobileAmount = 0;

    // Shift closed check
    let shiftsUnreconciled = true;
    let paymentsReconciled = false;
    let inventorySynced = false;
    let backupCompleted = false;

    // Chart.js handles
    let hourlyChartInstance = null;
    let categoryChartInstance = null;

    document.addEventListener("DOMContentLoaded", function() {
        checkPendingTransactions();
        updateTimeline();
    });

    function navigateToStep(step) {
        // Clamp and skip self-navigation
        if (step < 0 || step >= totalSteps || step === currentStep) return;

        const oldPanel = document.getElementById('panel_' + currentStep);
        const oldNode  = document.getElementById('node_'  + currentStep);
        const newPanel = document.getElementById('panel_' + step);
        const newNode  = document.getElementById('node_'  + step);

        // Safety guard: if any element is missing, abort
        if (!oldPanel || !oldNode || !newPanel || !newNode) {
            console.error('Day-end wizard: DOM element missing for step', currentStep, '→', step);
            return;
        }

        // Validation: prevent jumping forward prematurely
        if (step > currentStep) {
            const emptyEl   = document.getElementById('pendingTransactionsEmpty');
            const warningEl = document.getElementById('pendingTransactionsWarning');

            if (currentStep === 0) {
                // Block if AJAX hasn't resolved yet OR if pending transactions exist
                const notCleared = !emptyEl || emptyEl.classList.contains('d-none');
                const hasPending = warningEl && !warningEl.classList.contains('d-none');
                if (notCleared || hasPending) {
                    alert(hasPending
                        ? 'Clear all pending invoice balances before proceeding to the next step.'
                        : 'Please wait for the transaction scan to complete before proceeding.');
                    return;
                }
            }
            if (currentStep === 1 && shiftsUnreconciled && step > 1) {
                alert('Please reconcile and close all open cashier shifts before proceeding.');
                return;
            }
            if (currentStep === 2 && !paymentsReconciled && step > 2) {
                alert('Please complete payment gateway reconciliation before proceeding.');
                return;
            }
            if (currentStep === 4 && !inventorySynced && step > 4) {
                alert('Please execute inventory ledger synchronization before proceeding.');
                return;
            }
            if (currentStep === 6 && !backupCompleted && step > 6) {
                alert('Please execute the secure database backup before finalizing the day.');
                return;
            }
        }

        // De-activate current panel/node
        oldPanel.classList.remove('active');
        oldNode.classList.remove('active');
        if (step > currentStep) {
            oldNode.classList.add('completed');
        } else {
            // Going backwards — un-complete the node we're returning to
            newNode.classList.remove('completed');
        }

        currentStep = step;

        // Activate new panel/node
        newPanel.classList.add('active');
        newNode.classList.add('active');

        updateTimeline();
        loadStepSpecificData();
    }

    function navigateStepChange(direction) {
        const target = currentStep + direction;
        if (target >= 0 && target < totalSteps) {
            navigateToStep(target);
        }
    }

    function updateTimeline() {
        // Update timeline progress bar width percentage
        const progressPercentage = (currentStep / (totalSteps - 1)) * 100;
        document.getElementById('timelineProgress').style.width = progressPercentage + '%';
        
        // Enable/disable navigation buttons
        document.getElementById('prevBtn').disabled = currentStep === 0;
        document.getElementById('nextBtn').disabled = currentStep === totalSteps - 1;
    }

    function loadStepSpecificData() {
        if (currentStep === 0) {
            checkPendingTransactions();
        } else if (currentStep === 1) {
            loadCashierShifts();
        } else if (currentStep === 2) {
            loadPaymentTotals();
        } else if (currentStep === 3) {
            loadSalesSummary();
        } else if (currentStep === 5) {
            loadZReportData();
        }
    }

    /**
     * STEP 1 — Verify Incomplete/Unpaid sales
     */
    function checkPendingTransactions() {
        document.getElementById('pendingTransactionsLoader').classList.remove('d-none');
        document.getElementById('pendingTransactionsEmpty').classList.add('d-none');
        document.getElementById('pendingTransactionsWarning').classList.add('d-none');

        fetch('api/day_end_api.php?action=verify_transactions')
            .then(res => res.json())
            .then(data => {
                document.getElementById('pendingTransactionsLoader').classList.add('d-none');
                if (data.success) {
                    if (data.pending_count === 0) {
                        document.getElementById('pendingTransactionsEmpty').classList.remove('d-none');
                    } else {
                        document.getElementById('pendingTransactionsWarning').classList.remove('d-none');
                        let rows = '';
                        data.pending_transactions.forEach(t => {
                            rows += `
                                <tr>
                                    <td class="font-monospace text-primary fw-bold small">${t.invoice_no}</td>
                                    <td>${t.cashier || 'System'}</td>
                                    <td class="text-end font-monospace text-dark">LKR ${parseFloat(t.total_amount).toFixed(2)}</td>
                                    <td class="text-end font-monospace text-success">LKR ${parseFloat(t.paid_amount).toFixed(2)}</td>
                                    <td class="text-end font-monospace text-danger fw-bold">LKR ${parseFloat(t.balance).toFixed(2)}</td>
                                </tr>`;
                        });
                        document.getElementById('pendingTransactionsList').innerHTML = rows;
                    }
                }
            })
            .catch(err => {
                console.error("Scanning failed", err);
                alert("Scan failed. Ensure database tables are active.");
            });
    }

    /**
     * STEP 2 — Cashier Shift Closures
     */
    function loadCashierShifts() {
        fetch('api/day_end_api.php?action=get_shifts')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let rows = '';
                    let openCount = 0;
                    data.shifts.forEach(s => {
                        let varClass = '';
                        let varText = 'LKR 0.00';
                        
                        if (s.shift_status === 'closed') {
                            const v = parseFloat(s.variance);
                            varText = (v >= 0 ? '+' : '') + 'LKR ' + v.toLocaleString('en-US',{minimumFractionDigits:2});
                            varClass = v < 0 ? 'text-danger fw-bold' : (v > 0 ? 'text-success fw-bold' : 'text-secondary');
                        } else {
                            openCount++;
                        }

                        let statusBadge = s.shift_status === 'closed' 
                            ? `<span class="badge bg-success-subtle text-success"><i class="fas fa-check-circle me-1"></i> CLOSED</span>`
                            : `<span class="badge bg-warning-subtle text-warning"><i class="fas fa-exclamation-circle me-1"></i> OPEN</span>`;

                        let actionBtn = s.shift_status === 'closed'
                            ? `<button class="btn btn-sm btn-outline-success border-0" disabled><i class="fas fa-check"></i> Reconciled</button>`
                            : `<button class="btn btn-sm btn-outline-primary px-3 rounded-pill" onclick="closeCashierShift(${s.id}, ${s.opening_cash}, ${s.expected_cash})"><i class="fas fa-cash-register me-1"></i> Reconcile & Close</button>`;

                        rows += `
                            <tr id="shiftRow_${s.id}">
                                <td class="fw-bold text-dark">${s.full_name} <small class="text-muted font-monospace text-uppercase">(${s.role})</small></td>
                                <td class="small text-muted font-monospace">${s.shift_start}</td>
                                <td class="text-end font-monospace">LKR ${parseFloat(s.opening_cash).toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                                <td class="text-end font-monospace fw-bold text-dark">LKR ${parseFloat(s.expected_cash).toLocaleString('en-US',{minimumFractionDigits:2})}</td>
                                <td>
                                    ${s.shift_status === 'closed' 
                                        ? `<span class="fw-bold font-monospace">LKR ${parseFloat(s.closing_cash).toLocaleString('en-US',{minimumFractionDigits:2})}</span>`
                                        : `<input type="number" step="0.01" id="closingInput_${s.id}" class="form-control form-control-sm text-end font-monospace" value="${parseFloat(s.expected_cash).toFixed(2)}" style="max-width: 150px; float: right;">`
                                    }
                                </td>
                                <td class="text-end font-monospace ${varClass}">${varText}</td>
                                <td class="text-center">${statusBadge}</td>
                                <td class="text-end">${actionBtn}</td>
                            </tr>`;
                    });
                    
                    document.getElementById('cashierShiftList').innerHTML = rows;
                    shiftsUnreconciled = openCount > 0;
                }
            });
    }

    function closeCashierShift(cashierId, openingCash, expectedCash) {
        const counted = parseFloat(document.getElementById('closingInput_' + cashierId).value) || 0;
        
        let formData = new FormData();
        formData.append('cashier_id', cashierId);
        formData.append('opening_cash', openingCash);
        formData.append('closing_cash', counted);

        fetch('api/day_end_api.php?action=close_shift', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Cashier shift reconciled successfully.");
                loadCashierShifts();
            } else {
                alert("Failed: " + data.message);
            }
        })
        .catch(err => alert("Reconciliation call failed."));
    }

    /**
     * STEP 3 — Reconcile Gateways
     */
    function loadPaymentTotals() {
        fetch('api/day_end_api.php?action=get_sales_summary')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const cashier_sales = data.data.cashier_sales;
                    
                    // Reset to 0
                    posCashAmount = 0;
                    posCardAmount = 0;
                    posMobileAmount = 0;

                    // Fallback read from individual sales methods
                    fetch('api/day_end_api.php?action=verify_transactions')
                        .then(res => res.json())
                        .then(() => {
                            // Summing by matching payment methods
                            // Let's get them from sales summary data
                            fetch('api/day_end_api.php?action=reconcile_payments', { method: 'POST' })
                                .then(res => res.json())
                                .then(reconData => {
                                    if (reconData.success) {
                                        posCashAmount = parseFloat(reconData.reconciliation.Cash.pos_total);
                                        posCardAmount = parseFloat(reconData.reconciliation.Card.pos_total);
                                        posMobileAmount = parseFloat(reconData.reconciliation.Mobile.pos_total);

                                        document.getElementById('posCashText').innerText = 'LKR ' + posCashAmount.toLocaleString('en-US', {minimumFractionDigits:2});
                                        document.getElementById('posCardText').innerText = 'LKR ' + posCardAmount.toLocaleString('en-US', {minimumFractionDigits:2});
                                        document.getElementById('posMobileText').innerText = 'LKR ' + posMobileAmount.toLocaleString('en-US', {minimumFractionDigits:2});

                                        // Set inputs initially to POS totals to avoid typing
                                        if (document.getElementById('gatewayCash').value === '') {
                                            document.getElementById('gatewayCash').value = posCashAmount.toFixed(2);
                                            document.getElementById('gatewayCard').value = posCardAmount.toFixed(2);
                                            document.getElementById('gatewayMobile').value = posMobileAmount.toFixed(2);
                                        }

                                        calculatePaymentVariance('Cash');
                                        calculatePaymentVariance('Card');
                                        calculatePaymentVariance('Mobile');
                                    }
                                });
                        });
                }
            });
    }

    function calculatePaymentVariance(method) {
        let pos = 0;
        let count = 0;
        let diffTextId = '';

        if (method === 'Cash') {
            pos = posCashAmount;
            count = parseFloat(document.getElementById('gatewayCash').value) || 0.00;
            diffTextId = 'varianceCash';
        } else if (method === 'Card') {
            pos = posCardAmount;
            count = parseFloat(document.getElementById('gatewayCard').value) || 0.00;
            diffTextId = 'varianceCard';
        } else if (method === 'Mobile') {
            pos = posMobileAmount;
            count = parseFloat(document.getElementById('gatewayMobile').value) || 0.00;
            diffTextId = 'varianceMobile';
        }

        const variance = count - pos;
        const formatted = (variance >= 0 ? '+' : '') + 'LKR ' + variance.toLocaleString('en-US', {minimumFractionDigits:2});
        
        const node = document.getElementById(diffTextId);
        node.innerText = formatted;
        node.className = 'text-end fw-bold font-monospace ' + (variance < 0 ? 'text-danger' : (variance > 0 ? 'text-success' : 'text-secondary'));
    }

    function submitPaymentReconcile(e) {
        e.preventDefault();
        
        let formData = new FormData();
        formData.append('gateway_cash', document.getElementById('gatewayCash').value);
        formData.append('gateway_card', document.getElementById('gatewayCard').value);
        formData.append('gateway_mobile', document.getElementById('gatewayMobile').value);

        fetch('api/day_end_api.php?action=reconcile_payments', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                paymentsReconciled = true;
                alert("Payment channels reconciled and saved successfully.");
                navigateStepChange(1);
            }
        });
    }

    /**
     * STEP 4 — Reports and Chart Rendering
     */
    function loadSalesSummary() {
        fetch('api/day_end_api.php?action=get_sales_summary')
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const s = res.data.summary;
                    document.getElementById('statGross').innerText = 'LKR ' + parseFloat(s.gross_sales).toLocaleString('en-US',{minimumFractionDigits:2});
                    document.getElementById('statDiscounts').innerText = '-LKR ' + parseFloat(s.discounts).toLocaleString('en-US',{minimumFractionDigits:2});
                    document.getElementById('statNet').innerText = 'LKR ' + parseFloat(s.net_sales).toLocaleString('en-US',{minimumFractionDigits:2});
                    document.getElementById('statTax').innerText = 'LKR ' + parseFloat(s.tax).toLocaleString('en-US',{minimumFractionDigits:2});

                    // Hourly Sales Chart
                    const hourlyData = res.data.hourly_sales;
                    const hours = Array.from({length: 24}, (_, i) => i);
                    const hourlyRevenue = Array(24).fill(0);
                    hourlyData.forEach(h => {
                        hourlyRevenue[h.sales_hour] = parseFloat(h.hourly_revenue);
                    });

                    renderHourlyChart(hours.map(h => (h % 12 || 12) + (h < 12 ? ' AM' : ' PM')), hourlyRevenue);

                    // Category Doughnut Chart
                    const catData = res.data.category_sales;
                    renderCategoryChart(catData.map(c => c.category_name), catData.map(c => parseFloat(c.total_revenue)));

                    // Best Sellers List
                    let bestHtml = '';
                    res.data.best_sellers.forEach(b => {
                        bestHtml += `
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                <span class="fw-semibold text-dark">${b.product_name}</span>
                                <span class="badge bg-primary rounded-pill font-monospace">${b.total_qty} units sold</span>
                            </li>`;
                    });
                    document.getElementById('bestSellersList').innerHTML = bestHtml || '<li class="list-group-item text-center py-4 text-muted">No items sold on this date.</li>';

                    // Cashier Sales Contribution
                    let cashierHtml = '';
                    res.data.cashier_sales.forEach(c => {
                        cashierHtml += `
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                                <span class="fw-semibold text-dark">${c.full_name}</span>
                                <span class="fw-bold font-monospace text-primary">LKR ${parseFloat(c.total_revenue).toLocaleString('en-US',{minimumFractionDigits:2})}</span>
                            </li>`;
                    });
                    document.getElementById('cashierSalesList').innerHTML = cashierHtml || '<li class="list-group-item text-center py-4 text-muted">No shifts logged on this date.</li>';
                }
            });
    }

    function renderHourlyChart(labels, data) {
        const ctx = document.getElementById('hourlySalesChart').getContext('2d');
        if (hourlyChartInstance) {
            hourlyChartInstance.destroy();
        }
        hourlyChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Hourly Sales Revenue (LKR)',
                    data: data,
                    borderColor: '#0f62fe',
                    backgroundColor: 'rgba(15, 98, 254, 0.05)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: '#0f62fe'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'monospace' } } },
                    x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } } }
                }
            }
        });
    }

    function renderCategoryChart(labels, data) {
        const ctx = document.getElementById('categorySalesChart').getContext('2d');
        if (categoryChartInstance) {
            categoryChartInstance.destroy();
        }
        categoryChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#0f62fe', '#16a34a', '#fbbf24', '#dc2626', '#818cf8', '#a855f7'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } }
                }
            }
        });
    }

    /**
     * STEP 5 — Inventory Ledger Synchronization
     */
    function syncInventory() {
        fetch('api/day_end_api.php?action=sync_inventory')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    inventorySynced = true;
                    document.getElementById('inventoryResultCard').classList.remove('d-none');
                    document.getElementById('syncStatsText').innerText = `Synced sales items successfully into ledger: ${data.results.synced_items_count} records checked.`;
                    
                    const lowStockList = data.results.low_stock_alerts;
                    document.getElementById('lowStockCount').innerText = lowStockList.length;
                    
                    let rows = '';
                    lowStockList.forEach(item => {
                        rows += `
                            <tr>
                                <td class="fw-semibold text-dark">${item.product_name}</td>
                                <td class="text-center font-monospace text-danger fw-bold">${item.stock_quantity}</td>
                                <td class="text-center font-monospace">${item.reorder_level}</td>
                                <td class="text-center"><span class="badge bg-danger-subtle text-danger">REORDER ALERT</span></td>
                            </tr>`;
                    });
                    
                    document.getElementById('lowStockAlertTableBody').innerHTML = rows || '<tr><td colspan="4" class="text-center py-3 text-success"><i class="fas fa-check-circle me-1"></i> Stock quantities healthy. No low-stock thresholds crossed.</td></tr>';
                    
                    alert("Inventory synchronized successfully.");
                }
            });
    }

    /**
     * STEP 6 — Pre-render Z-Report
     */
    function loadZReportData() {
        // Fetch fresh totals
        fetch('api/day_end_api.php?action=get_sales_summary')
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const s = res.data.summary;
                    document.getElementById('zGross').innerText = 'LKR ' + parseFloat(s.gross_sales).toLocaleString('en-US',{minimumFractionDigits:2});
                    document.getElementById('zDiscounts').innerText = '-LKR ' + parseFloat(s.discounts).toLocaleString('en-US',{minimumFractionDigits:2});
                    document.getElementById('zTax').innerText = 'LKR ' + parseFloat(s.tax).toLocaleString('en-US',{minimumFractionDigits:2});
                    document.getElementById('zNet').innerText = 'LKR ' + parseFloat(s.net_sales).toLocaleString('en-US',{minimumFractionDigits:2});

                    // Re-calculate Est profit
                    fetch('api/day_end_api.php?action=generate_z_report')
                        .then(() => {
                            // Get estimated net profit
                            const netVal = parseFloat(s.net_sales);
                            const discountVal = parseFloat(s.discounts);
                            // Let's call endpoint or precompute estimated 40% margin fallback if profit table isn't filled yet
                            const margin = netVal * 0.40; 
                            document.getElementById('zProfit').innerText = 'LKR ' + margin.toLocaleString('en-US',{minimumFractionDigits:2});
                        });

                    document.querySelectorAll('.businessDateText').forEach(el => el.innerText = businessDate);
                    document.getElementById('zGeneratedTime').innerText = new Date().toLocaleString();

                    // Load reconciliation details in Z-Report payment table
                    fetch('api/day_end_api.php?action=get_shifts')
                        .then(r => r.json())
                        .then(shiftData => {
                            let cashierRows = '';
                            shiftData.shifts.forEach(cs => {
                                cashierRows += `
                                    <tr>
                                        <td>${cs.full_name}</td>
                                        <td class="text-end">LKR ${parseFloat(cs.expected_cash).toFixed(0)}</td>
                                        <td class="text-end">LKR ${parseFloat(cs.closing_cash).toFixed(0)}</td>
                                        <td class="text-end">${parseFloat(cs.variance) >= 0 ? '+' : ''}${parseFloat(cs.variance).toFixed(0)}</td>
                                    </tr>`;
                            });
                            document.getElementById('zReportCashierTable').innerHTML = cashierRows;
                        });

                    fetch('api/day_end_api.php?action=reconcile_payments', { method: 'POST' })
                        .then(r => r.json())
                        .then(reconData => {
                            let payRows = '';
                            ['Cash', 'Card', 'Mobile'].forEach(method => {
                                const r = reconData.reconciliation[method];
                                payRows += `
                                    <tr>
                                        <td>${method}</td>
                                        <td class="text-end">LKR ${parseFloat(r.pos_total).toFixed(0)}</td>
                                        <td class="text-end">LKR ${parseFloat(r.gateway_total).toFixed(0)}</td>
                                        <td class="text-end">${parseFloat(r.variance) >= 0 ? '+' : ''}${parseFloat(r.variance).toFixed(0)}</td>
                                    </tr>`;
                            });
                            document.getElementById('zReportPaymentTable').innerHTML = payRows;
                        });
                }
            });
    }

    /**
     * STEP 7 — Database Backups
     */
    function runBackup() {
        const btn = document.querySelector('[onclick="runBackup()"]');
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span> Generating Database Copy...`;

        fetch('api/day_end_api.php?action=run_backup')
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-save me-1"></i> Execute Database Backup`;
                
                if (data.success) {
                    backupCompleted = true;
                    document.getElementById('backupResultCard').classList.remove('d-none');
                    document.getElementById('backupFileNameText').innerText = data.backup_file;
                    alert("Backup completed successfully.");
                } else {
                    alert("Backup failed: " + data.message);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-save me-1"></i> Execute Database Backup`;
                alert("Communication error during backup.");
            });
    }

    /**
     * STEP 8 — Day Close Finalization
     */
    function finalizeDayClose() {
        if (!confirm("CRITICAL CONFIRMATION:\nAre you absolutely sure you want to lock ALL transactions for " + businessDate + " and finalize the day?\n\nThis action cannot be undone under standard audit logs!")) {
            return;
        }

        const btn = document.querySelector('.day-close-btn');
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span> Finalizing Lock & Date rollover...`;

        fetch('api/day_end_api.php?action=finalize_day', { method: 'POST' })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert("CONGRATULATIONS!\nDay close finalized and locked successfully.\nNext active business period date opened: " + data.next_business_date);
                    window.location.href = 'dashboard.php';
                } else {
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fas fa-calendar-times me-2"></i> Permanently Lock & Close Day`;
                    alert("Finalize failed: " + data.message);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-calendar-times me-2"></i> Permanently Lock & Close Day`;
                alert("Communication error during finalization.");
            });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
