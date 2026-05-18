<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Check Admin role
if ($_SESSION['role'] !== 'admin') {
    echo "<div class='alert alert-danger'>Access Denied. You do not have permission to view this page.</div>";
    require_once 'includes/footer.php';
    exit();
}

// Handle Add/Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    $user_id = $_POST['user_id'] ?? '';

    if (empty($user_id)) {
        // Add new
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$full_name, $username, $password, $role]);
    } else {
        // Update
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->execute([$full_name, $username, $password, $role, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE id = ?");
            $stmt->execute([$full_name, $username, $role, $user_id]);
        }
    }
    header("Location: users.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: users.php");
    exit();
}

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>User Management</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
        <i class="fas fa-plus"></i> Add New User
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : 'success' ?>"><?= ucfirst($user['role']) ?></span></td>
                        <td><?= $user['created_at'] ?></td>
                        <td>
                            <button class="btn btn-sm btn-info text-white" onclick='editUser(<?= json_encode($user) ?>)'><i class="fas fa-edit"></i></button>
                            <?php if($user['id'] != $_SESSION['user_id']): // Prevent deleting self ?>
                            <a href="?delete=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="user_id" id="user_id">
                <div class="mb-3">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password <small class="text-muted">(Leave blank to keep current if editing)</small></label>
                    <input type="password" name="password" id="password" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Role</label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="cashier">Cashier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_user" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('user_id').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = 'cashier';
    document.getElementById('modalTitle').innerText = 'Add User';
}

function editUser(user) {
    document.getElementById('user_id').value = user.id;
    document.getElementById('full_name').value = user.full_name;
    document.getElementById('username').value = user.username;
    document.getElementById('password').value = '';
    document.getElementById('role').value = user.role;
    document.getElementById('modalTitle').innerText = 'Edit User';
    var myModal = new bootstrap.Modal(document.getElementById('userModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
