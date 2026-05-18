<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle Add/Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
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
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: customers.php");
    exit();
}

$customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Customers</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="resetForm()">
        <i class="fas fa-plus"></i> Add Customer
    </button>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Loyalty Points</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['customer_name']) ?></td>
                    <td><?= htmlspecialchars($c['phone']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= $c['loyalty_points'] ?></td>
                    <td>
                        <button class="btn btn-sm btn-info text-white" onclick='editCustomer(<?= json_encode($c) ?>)'><i class="fas fa-edit"></i></button>
                        <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete customer?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="customer_id" id="customer_id">
                <div class="mb-3">
                    <label>Name</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Phone</label>
                    <input type="text" name="phone" id="phone" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Address</label>
                    <textarea name="address" id="address" class="form-control"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_customer" class="btn btn-primary">Save Changes</button>
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
    document.getElementById('phone').value = c.phone;
    document.getElementById('email').value = c.email;
    document.getElementById('address').value = c.address;
    document.getElementById('modalTitle').innerText = 'Edit Customer';
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
