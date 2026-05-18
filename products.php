<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle Add/Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $product_name = $_POST['product_name'];
    $category_id = $_POST['category_id'] ?: null;
    $brand_id = $_POST['brand_id'] ?: null;
    $barcode = $_POST['barcode'] ?: null;
    $size = $_POST['size'];
    $color = $_POST['color'];
    $purchase_price = $_POST['purchase_price'];
    $selling_price = $_POST['selling_price'];
    $stock_quantity = $_POST['stock_quantity'];
    $reorder_level = $_POST['reorder_level'];
    $product_id = $_POST['product_id'] ?? '';

    // Simple image upload
    $image = 'default.png';
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if(in_array(strtolower($ext), $allowed)) {
            $new_name = time() . '_' . $filename;
            if(!is_dir('assets/images')) { mkdir('assets/images', 0777, true); }
            move_uploaded_file($_FILES['image']['tmp_name'], 'assets/images/' . $new_name);
            $image = $new_name;
        }
    }

    if (empty($product_id)) {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, brand_id, product_name, barcode, size, color, purchase_price, selling_price, stock_quantity, reorder_level, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category_id, $brand_id, $product_name, $barcode, $size, $color, $purchase_price, $selling_price, $stock_quantity, $reorder_level, $image]);
    } else {
        if ($image !== 'default.png') {
            $stmt = $pdo->prepare("UPDATE products SET category_id=?, brand_id=?, product_name=?, barcode=?, size=?, color=?, purchase_price=?, selling_price=?, stock_quantity=?, reorder_level=?, image=? WHERE id=?");
            $stmt->execute([$category_id, $brand_id, $product_name, $barcode, $size, $color, $purchase_price, $selling_price, $stock_quantity, $reorder_level, $image, $product_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET category_id=?, brand_id=?, product_name=?, barcode=?, size=?, color=?, purchase_price=?, selling_price=?, stock_quantity=?, reorder_level=? WHERE id=?");
            $stmt->execute([$category_id, $brand_id, $product_name, $barcode, $size, $color, $purchase_price, $selling_price, $stock_quantity, $reorder_level, $product_id]);
        }
    }
    header("Location: products.php");
    exit();
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: products.php");
    exit();
}

// Fetch lists
$products = $pdo->query("SELECT p.*, c.category_name, b.brand_name FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id ORDER BY p.id DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY brand_name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Products</h2>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
            <i class="fas fa-plus"></i> Add Product
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td>
                            <?php if(file_exists('assets/images/'.$p['image']) && $p['image'] != 'default.png'): ?>
                                <img src="assets/images/<?= $p['image'] ?>" width="40" height="40" class="rounded">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fas fa-box"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($p['product_name']) ?></strong>
                            <br><small class="text-muted">Barcode: <?= $p['barcode'] ?: 'N/A' ?> | Size: <?= $p['size'] ?: 'N/A' ?></small>
                        </td>
                        <td><?= htmlspecialchars($p['category_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($p['brand_name'] ?? 'N/A') ?></td>
                        <td>LKR <?= number_format($p['selling_price'], 2) ?></td>
                        <td>
                            <?php if($p['stock_quantity'] <= $p['reorder_level']): ?>
                                <span class="badge bg-danger"><?= $p['stock_quantity'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $p['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info text-white" onclick='editProduct(<?= json_encode($p) ?>)'><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete product?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="product_id" id="product_id">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="product_name" id="product_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Barcode</label>
                        <input type="text" name="barcode" id="barcode" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Brand</label>
                        <select name="brand_id" id="brand_id" class="form-select">
                            <option value="">Select Brand</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['brand_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>Size</label>
                        <select name="size" id="size" class="form-select">
                            <option value="">None</option>
                            <option value="S">Small (S)</option>
                            <option value="M">Medium (M)</option>
                            <option value="L">Large (L)</option>
                            <option value="XL">Extra Large (XL)</option>
                            <option value="XXL">XXL</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Color</label>
                        <input type="text" name="color" id="color" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Purchase Price</label>
                        <input type="number" step="0.01" name="purchase_price" id="purchase_price" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Selling Price</label>
                        <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control" value="0" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Initial Stock</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Reorder Level</label>
                        <input type="number" name="reorder_level" id="reorder_level" class="form-control" value="5" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Image</label>
                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="save_product" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('product_id').value = '';
    document.getElementById('product_name').value = '';
    document.getElementById('barcode').value = '';
    document.getElementById('category_id').value = '';
    document.getElementById('brand_id').value = '';
    document.getElementById('size').value = '';
    document.getElementById('color').value = '';
    document.getElementById('purchase_price').value = '0';
    document.getElementById('selling_price').value = '0';
    document.getElementById('stock_quantity').value = '0';
    document.getElementById('reorder_level').value = '5';
    document.getElementById('modalTitle').innerText = 'Add Product';
}

function editProduct(p) {
    document.getElementById('product_id').value = p.id;
    document.getElementById('product_name').value = p.product_name;
    document.getElementById('barcode').value = p.barcode;
    document.getElementById('category_id').value = p.category_id;
    document.getElementById('brand_id').value = p.brand_id;
    document.getElementById('size').value = p.size;
    document.getElementById('color').value = p.color;
    document.getElementById('purchase_price').value = p.purchase_price;
    document.getElementById('selling_price').value = p.selling_price;
    document.getElementById('stock_quantity').value = p.stock_quantity;
    document.getElementById('reorder_level').value = p.reorder_level;
    document.getElementById('modalTitle').innerText = 'Edit Product';
    var myModal = new bootstrap.Modal(document.getElementById('productModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
