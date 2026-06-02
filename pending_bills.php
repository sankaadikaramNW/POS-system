<?php
// pending_bills.php
require_once 'config/database.php';
require_once 'includes/header.php';
?>
<!-- Load jQuery Early to Ensure Inline Script works perfectly -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<div class="premium-dark-page flex-1 container-fluid p-0">
    <!-- Header Card -->
    <div class="card mb-4" style="border: 1px solid var(--border-color-dark) !important; background: #ffffff !important;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="fw-bold text-dark m-0"><i class="fas fa-hourglass-half text-warning me-2 animate-pulse"></i> Pending Bills Queue</h4>
                    <p class="text-muted m-0 small mt-1">Manage, resume, or cancel active waiting customer bills in real-time.</p>
                </div>
                <div>
                    <a href="sales.php" class="btn btn-orange-premium py-2 px-3 fw-bold text-uppercase" style="font-size: 0.8rem;">
                        <i class="fas fa-plus-circle me-1"></i> Go to POS Billing
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Panel -->
    <div class="card mb-4" style="border: 1px solid var(--border-color-dark) !important; background: #ffffff !important;">
        <div class="card-body p-3">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="text-muted fw-semibold small mb-1" style="font-size:0.75rem;">Search Hold Bill / Customer / Notes</label>
                    <div class="position-relative">
                        <input type="text" id="searchKeyword" class="form-control form-control-sm ps-5" placeholder="Enter Hold Bill No, name, notes..." style="height:38px; font-size:0.85rem;">
                        <i class="fas fa-search position-absolute text-muted" style="left: 15px; top: 12px; font-size:0.9rem;"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="text-muted fw-semibold small mb-1" style="font-size:0.75rem;">Date Range Filter</label>
                    <select id="dateFilter" class="form-select form-select-sm" style="height:38px; font-size:0.85rem;">
                        <option value="">All Pending Dates</option>
                        <option value="today">Today Only</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="last7">Last 7 Days</option>
                        <option value="custom">Custom Date Range...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="text-muted fw-semibold small mb-1" style="font-size:0.75rem;">Sort Order</label>
                    <select id="sortOrder" class="form-select form-select-sm" style="height:38px; font-size:0.85rem;">
                        <option value="created_at_desc">Hold Time (Newest First)</option>
                        <option value="created_at_asc">Hold Time (Oldest First)</option>
                        <option value="amount_desc">Grand Total (High to Low)</option>
                        <option value="amount_asc">Grand Total (Low to High)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" onclick="loadPendingQueue()" class="btn btn-dark-premium w-100 fw-bold text-uppercase d-flex align-items-center justify-content-center" style="height:38px; font-size:0.8rem;">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>

                <!-- Custom Date Selectors (Collapsible) -->
                <div class="col-12 mt-3 d-none" id="customDateRange">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="text-muted small mb-1">Start Date</label>
                            <input type="date" id="startDate" class="form-control form-control-sm" style="height:34px;">
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small mb-1">End Date</label>
                            <input type="date" id="endDate" class="form-control form-control-sm" style="height:34px;">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Active List Queue -->
    <div class="card" style="border: 1px solid var(--border-color-dark) !important; background: #ffffff !important;">
        <div class="card-body p-0">
            <div id="queueTableContainer">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fs-1 mb-3 text-warning"></i>
                    <p class="text-muted m-0 small">Querying pending sales records...</p>
                </div>
            </div>

            <!-- Pagination Footer -->
            <div class="card-footer bg-transparent border-top py-3 px-4 d-flex flex-wrap justify-content-between align-items-center border-color-dark gap-2">
                <div class="small text-muted" id="paginationStats">
                    Showing 0 to 0 of 0 pending entries
                </div>
                <nav>
                    <ul class="pagination pagination-sm m-0 border-radius" id="paginationList">
                        <!-- Dynamic pagination buttons -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Cart Viewing Modal -->
<div class="modal fade glass-receipt-modal" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-file-invoice text-warning me-2"></i> Hold Bill Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detailsBody">
                <!-- Loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary py-1 px-3" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger py-1 px-3" id="btnDetailsCancel">Cancel Bill</button>
                <a href="#" class="btn btn-success py-1 px-3 text-white" id="btnDetailsResume">Resume Checkout</a>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
const recordsPerPage = 10;

$(document).ready(function() {
    loadPendingQueue();

    // Toggle custom date range view
    $('#dateFilter').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#customDateRange').removeClass('d-none');
        } else {
            $('#customDateRange').addClass('d-none');
            $('#startDate').val('');
            $('#endDate').val('');
        }
        loadPendingQueue(1);
    });

    $('#sortOrder, #searchKeyword').on('change keyup', function() {
        if ($(this).attr('id') === 'searchKeyword' && $(this).val().length > 0 && $(this).val().length < 2) return;
        loadPendingQueue(1);
    });
});

function loadPendingQueue(page = 1) {
    currentPage = page;
    const container = $('#queueTableContainer');
    
    // Build filters
    let search = $('#searchKeyword').val().trim();
    let dateFilter = $('#dateFilter').val();
    let sort = $('#sortOrder').val();
    let startDate = '';
    let endDate = '';

    if (dateFilter === 'today') {
        const today = new Date().toISOString().split('T')[0];
        startDate = today;
        endDate = today;
    } else if (dateFilter === 'yesterday') {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        const yestStr = yesterday.toISOString().split('T')[0];
        startDate = yestStr;
        endDate = yestStr;
    } else if (dateFilter === 'last7') {
        const last7 = new Date();
        last7.setDate(last7.getDate() - 7);
        startDate = last7.toISOString().split('T')[0];
        endDate = new Date().toISOString().split('T')[0];
    } else if (dateFilter === 'custom') {
        startDate = $('#startDate').val();
        endDate = $('#endDate').val();
    }

    $.ajax({
        type: 'GET',
        url: 'api/pending_sales_api.php',
        data: {
            action: 'list',
            search: search,
            start_date: startDate,
            end_date: endDate,
            sort: sort,
            page: currentPage,
            limit: recordsPerPage
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.bills && response.bills.length > 0) {
                let html = `
                    <div class="table-responsive">
                        <table class="table table-hover align-middle premium-dark-page m-0" style="background:transparent !important;">
                            <thead>
                                <tr>
                                    <th>Hold Bill No</th>
                                    <th>Customer / Client</th>
                                    <th>Wait Time / Age</th>
                                    <th class="text-end">Hold Totals</th>
                                    <th>Active Cashier</th>
                                    <th class="text-center" style="width: 250px;">Management Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                response.bills.forEach(bill => {
                    let minutes = parseInt(bill.minutes_elapsed) || 0;
                    let waitClass = "bg-success text-white";
                    let waitText = minutes + " mins ago";
                    
                    if (minutes >= 30) {
                        waitClass = "bg-danger text-white pulse";
                        waitText = minutes + " mins (Urgent)";
                    } else if (minutes >= 15) {
                        waitClass = "bg-warning text-dark";
                        waitText = minutes + " mins (Warning)";
                    }

                    // Format dates
                    let createdObj = new Date(bill.created_at);
                    let formatTime = createdObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    let formatDate = createdObj.toLocaleDateString([], { month: 'short', day: 'numeric' });

                    html += `
                        <tr>
                            <td><strong class="text-dark fs-6">${bill.hold_bill_no}</strong></td>
                            <td>
                                <span class="d-block fw-bold text-secondary small">${escapeHtml(bill.customer_name)}</span>
                                <span class="text-muted d-block text-truncate small" style="max-width: 200px;" title="${escapeHtml(bill.notes || '')}">
                                    <i class="fas fa-sticky-note me-1 opacity-70"></i> ${escapeHtml(bill.notes || 'No reference/notes')}
                                </span>
                            </td>
                            <td>
                                <div class="d-inline-flex flex-column">
                                    <span class="fw-semibold text-dark small">${formatDate} at ${formatTime}</span>
                                    <span class="badge ${waitClass} mt-1 py-1" style="font-size:0.7rem; border-radius: 4px; font-weight:700;">${waitText}</span>
                                </div>
                            </td>
                            <td class="text-end fw-extrabold text-primary fs-6">LKR ${parseFloat(bill.grand_total).toFixed(2)}</td>
                            <td><span class="small fw-semibold text-muted"><i class="fas fa-user-tie me-1"></i> ${escapeHtml(bill.cashier_name || 'Cashier')}</span></td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-2">
                                    <button class="btn btn-sm btn-dark-premium py-1 px-2 d-flex align-items-center" onclick="viewDetails('${bill.hold_bill_no}')" title="Inspect Cart Items">
                                        <i class="fas fa-eye me-1 text-primary"></i> View Details
                                    </button>
                                    <a href="sales.php?resume=${bill.hold_bill_no}" class="btn btn-sm btn-success py-1 px-2 d-flex align-items-center text-white" title="Resume POS Checkout" style="background:#10b981; border-color:#10b981;">
                                        <i class="fas fa-folder-open me-1"></i> Resume
                                    </a>
                                    <button class="btn btn-sm btn-danger py-1 px-2 d-flex align-items-center" onclick="cancelPendingSale('${bill.hold_bill_no}')" title="Cancel & Audit Log">
                                        <i class="fas fa-trash-alt me-1"></i> Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                container.html(html);

                // Render Pagination Stats
                let p = response.pagination;
                let from = (p.current_page - 1) * p.limit + 1;
                let to = Math.min(from + p.limit - 1, p.total_records);
                $('#paginationStats').text(`Showing ${from} to ${to} of ${p.total_records} pending entries`);

                // Render Pagination List
                let pagHtml = '';
                // Prev button
                pagHtml += `
                    <li class="page-item ${p.current_page === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="javascript:void(0)" onclick="loadPendingQueue(${p.current_page - 1})"><i class="fas fa-chevron-left"></i></a>
                    </li>
                `;

                for (let i = 1; i <= p.total_pages; i++) {
                    pagHtml += `
                        <li class="page-item ${p.current_page === i ? 'active' : ''}">
                            <a class="page-link" href="javascript:void(0)" onclick="loadPendingQueue(${i})">${i}</a>
                        </li>
                    `;
                }

                // Next button
                pagHtml += `
                    <li class="page-item ${p.current_page === p.total_pages ? 'disabled' : ''}">
                        <a class="page-link" href="javascript:void(0)" onclick="loadPendingQueue(${p.current_page + 1})"><i class="fas fa-chevron-right"></i></a>
                    </li>
                `;
                $('#paginationList').html(pagHtml);

            } else {
                container.html(`
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-hourglass-empty d-block fs-1 mb-3 opacity-30 text-warning animate-bounce"></i>
                        <h5 class="fw-bold text-dark mb-1">No Active Bills on Hold</h5>
                        <p class="small mb-0">The pending bill queue is empty. Active cashiers can put bills on hold from POS.</p>
                    </div>
                `);
                $('#paginationStats').text(`Showing 0 to 0 of 0 pending entries`);
                $('#paginationList').html('');
            }
        },
        error: function() {
            container.html(`
                <div class="alert alert-danger m-3 small">
                    <i class="fas fa-exclamation-triangle me-1"></i> Critical Error: Failed to query billing queue from service layer.
                </div>
            `);
        }
    });
}

function viewDetails(holdBillNo) {
    $.ajax({
        type: 'GET',
        url: 'api/pending_sales_api.php',
        data: { action: 'details', hold_bill_no: holdBillNo },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const bill = response.bill;
                let cartItems = [];
                try {
                    cartItems = JSON.parse(bill.cart_data_json);
                } catch(e){}

                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-color-dark">
                        <div>
                            <h6 class="fw-bold text-dark m-0">${bill.hold_bill_no}</h6>
                            <small class="text-muted">Cashier: ${escapeHtml(bill.cashier_name || 'Cashier')}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary p-1 small" style="font-size:0.7rem;">PENDING STATUS</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block font-semibold">Customer:</small>
                        <span class="text-dark fw-bold small">${escapeHtml(bill.customer_name)}</span>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted d-block font-semibold">Hold Notes / Special Reference:</small>
                        <span class="text-dark small bg-light p-2 rounded d-block border" style="font-style:italic;">"${escapeHtml(bill.notes || 'No notes added')}"</span>
                    </div>

                    <h6 class="fw-bold text-dark mt-4 mb-2 small text-uppercase">Cart Items List:</h6>
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm align-middle m-0" style="font-size:0.82rem;">
                            <thead>
                                <tr class="text-secondary" style="border-bottom: 2px solid var(--border-color-dark);">
                                    <th>Item Detail</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Total Price</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                cartItems.forEach(item => {
                    html += `
                        <tr style="border-bottom: 1px solid var(--border-color-dark);">
                            <td style="padding: 6px 0;"><strong>${item.name}</strong><br><small class="text-muted">Rs. ${parseFloat(item.price).toFixed(2)}</small></td>
                            <td class="text-center fw-semibold">${item.qty}</td>
                            <td class="text-end fw-bold text-dark">Rs. ${(item.price * item.qty).toFixed(2)}</td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 pt-3 border-top border-color-dark">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-semibold text-dark">Rs. ${parseFloat(bill.subtotal).toFixed(2)}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Discount Allowed:</span>
                            <span class="fw-semibold text-danger">-Rs. ${parseFloat(bill.discount_amount).toFixed(2)}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Tax Added:</span>
                            <span class="fw-semibold text-dark">+Rs. ${parseFloat(bill.tax_amount).toFixed(2)}</span>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-2 border-top border-color-dark">
                            <h6 class="fw-bold text-dark m-0">Grand Total:</h6>
                            <h6 class="fw-extrabold text-primary m-0">LKR ${parseFloat(bill.grand_total).toFixed(2)}</h6>
                        </div>
                    </div>
                `;

                $('#detailsBody').html(html);

                // Configure modal actions
                $('#btnDetailsCancel').off('click').on('click', function() {
                    // Safely hide the modal - getInstance can return null if modal context differs
                    try {
                        let modalInst = bootstrap.Modal.getInstance(document.getElementById('detailsModal'));
                        if (modalInst) modalInst.hide();
                    } catch(e) {}
                    cancelPendingSale(bill.hold_bill_no);
                });

                $('#btnDetailsResume').attr('href', `sales.php?resume=${bill.hold_bill_no}`);

                // Show modal
                new bootstrap.Modal(document.getElementById('detailsModal')).show();
            } else {
                alert("Failed to load details: " + response.message);
            }
        },
        error: function() {
            alert("Error retrieving details from server API.");
        }
    });
}

function cancelPendingSale(holdBillNo) {
    if (!confirm(`Are you sure you want to permanently cancel and discard Hold Bill ${holdBillNo}?\nThis action will record a status change to CANCELLED in the database and write to the audit trail.`)) return;

    $.ajax({
        type: 'GET',
        url: 'api/pending_sales_api.php',
        data: { action: 'cancel', hold_bill_no: holdBillNo },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert(`Hold Bill ${holdBillNo} cancelled successfully.`);
                loadPendingQueue(currentPage);
            } else {
                alert("Failed to cancel: " + response.message);
            }
        },
        error: function() {
            alert("Error communicating cancellation payload to API.");
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php
require_once 'includes/footer.php';
?>
