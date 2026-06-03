<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle Add/Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    require_once 'includes/lock_check.php';
    $session_stmt = $pdo->query("SELECT business_date FROM day_end_sessions ORDER BY business_date DESC LIMIT 1");
    $active_business_date = $session_stmt->fetchColumn() ?: date('Y-m-d');
    check_day_end_lock($active_business_date, $pdo);

    $customer_name = $_POST['customer_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $customer_id = $_POST['customer_id'] ?? '';

    if (empty($customer_id)) {
        $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$customer_name, $phone, $email, $address]);
    } else {
        $stmt = $pdo->prepare("UPDATE customers SET customer_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
        $stmt->execute([$customer_name, $phone, $email, $address, $customer_id]);
    }
    header("Location: customers.php");
    exit();
}

if (isset($_GET['delete'])) {
    require_once 'includes/lock_check.php';
    $session_stmt = $pdo->query("SELECT business_date FROM day_end_sessions ORDER BY business_date DESC LIMIT 1");
    $active_business_date = $session_stmt->fetchColumn() ?: date('Y-m-d');
    check_day_end_lock($active_business_date, $pdo);

    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: customers.php");
    exit();
}

$customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Customers</h2>
        <p class="text-muted mb-0 small">
            Total: <strong><?= count($customers) ?></strong> customer<?= count($customers) !== 1 ? 's' : '' ?>
        </p>
    </div>
    <button class="btn btn-orange-premium" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetForm()">
        <i class="fas fa-plus me-1"></i> Add Customer
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th class="text-center">Loyalty Pts</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="fas fa-users d-block fs-1 mb-3 opacity-25"></i>
                            <h5 class="fw-bold mb-1">No Customers Available</h5>
                            <p class="text-muted mb-3 small">No customer records found. Add your first customer to get started.</p>
                            <button class="btn btn-orange-premium btn-sm" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetForm()">
                                <i class="fas fa-plus me-1"></i> Add First Customer
                            </button>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($customers as $i => $c): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($c['customer_name']) ?></td>
                        <td><?= htmlspecialchars($c['phone'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($c['email'] ?: '---') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($c['address'] ?: '---') ?></td>
                        <td class="text-center">
                            <span class="badge bg-warning text-dark"><?= number_format($c['loyalty_points']) ?></span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-dark-premium text-info border-0 me-1" onclick='editCustomer(<?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-dark-premium text-danger border-0" onclick="return confirm('Delete customer?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content glass-receipt-modal">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="customer_id" id="customer_id">
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Name <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Phone</label>
                    <input type="text" name="phone" id="phone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="fw-semibold mb-1">Address</label>
                    <textarea name="address" id="address" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-dark-premium" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_customer" class="btn btn-orange-premium fw-bold">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('customer_id').value = '';
    document.getElementById('customer_name').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('email').value = '';
    document.getElementById('address').value = '';
    document.getElementById('modalTitle').innerText = 'Add Customer';
}

function editCustomer(c) {
    document.getElementById('customer_id').value = c.id;
    document.getElementById('customer_name').value = c.customer_name;
    document.getElementById('phone').value = c.phone || '';
    document.getElementById('email').value = c.email || '';
    document.getElementById('address').value = c.address || '';
    document.getElementById('modalTitle').innerText = 'Edit Customer';
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
