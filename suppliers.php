<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Access Denied.</div>";
    require_once 'includes/footer.php';
    exit();
}

// Handle Add/Edit Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_supplier'])) {
    $supplier_name = $_POST['supplier_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $supplier_id = $_POST['supplier_id'] ?? '';

    if (empty($supplier_id)) {
        $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$supplier_name, $phone, $email, $address]);
    } else {
        $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
        $stmt->execute([$supplier_name, $phone, $email, $address, $supplier_id]);
    }
    header("Location: suppliers.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: suppliers.php");
    exit();
}

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Suppliers</h2>
        <p class="text-muted mb-0 small">
            Total: <strong><?= count($suppliers) ?></strong> supplier<?= count($suppliers) !== 1 ? 's' : '' ?>
        </p>
    </div>
    <button class="btn btn-orange-premium" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="resetForm()">
        <i class="fas fa-plus me-1"></i> Add Supplier
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
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="fas fa-truck d-block fs-1 mb-3 opacity-25"></i>
                            <h5 class="fw-bold mb-1">No Suppliers Available</h5>
                            <p class="text-muted mb-3 small">No supplier records found. Add your first supplier to manage purchases.</p>
                            <button class="btn btn-orange-premium btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="resetForm()">
                                <i class="fas fa-plus me-1"></i> Add First Supplier
                            </button>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($suppliers as $i => $s): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($s['supplier_name']) ?></td>
                        <td><?= htmlspecialchars($s['phone'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($s['email'] ?: '---') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($s['address'] ?: '---') ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-dark-premium text-info border-0 me-1" onclick='editSupplier(<?= json_encode($s) ?>)'><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-dark-premium text-danger border-0" onclick="return confirm('Delete supplier?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="supplier_id" id="supplier_id">
                <div class="mb-3">
                    <label>Name</label>
                    <input type="text" name="supplier_name" id="supplier_name" class="form-control" required>
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
                <button type="submit" name="save_supplier" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('supplier_id').value = '';
    document.getElementById('supplier_name').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('email').value = '';
    document.getElementById('address').value = '';
    document.getElementById('modalTitle').innerText = 'Add Supplier';
}

function editSupplier(s) {
    document.getElementById('supplier_id').value = s.id;
    document.getElementById('supplier_name').value = s.supplier_name;
    document.getElementById('phone').value = s.phone;
    document.getElementById('email').value = s.email;
    document.getElementById('address').value = s.address;
    document.getElementById('modalTitle').innerText = 'Edit Supplier';
    var myModal = new bootstrap.Modal(document.getElementById('supplierModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
