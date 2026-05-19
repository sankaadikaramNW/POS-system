<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$role = $_SESSION['role'] ?? 'cashier';

// Handle Add/Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    if ($role !== 'admin') die("Unauthorized access");
    $product_name = $_POST['product_name'];
    $category_id = $_POST['category_id'] ?: null;
    $brand_id = $_POST['brand_id'] ?: null;
    $barcode = $_POST['barcode'] ?: null;
    $size = $_POST['size'];
    $color = $_POST['color'];
    $purchase_price = $_POST['purchase_price'];
    $selling_price = $_POST['selling_price'];
    $discount_percent = (int)($_POST['discount_percent'] ?? 0);
    $stock_quantity = $_POST['stock_quantity'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $reorder_level = $_POST['reorder_level'];
    $gender = $_POST['gender'] ?? 'Unisex';
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
        $stmt = $pdo->prepare("INSERT INTO products (category_id, brand_id, product_name, barcode, size, color, purchase_price, selling_price, discount_percent, stock_quantity, is_featured, reorder_level, image, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$category_id, $brand_id, $product_name, $barcode, $size, $color, $purchase_price, $selling_price, $discount_percent, $stock_quantity, $is_featured, $reorder_level, $image, $gender]);
    } else {
        if ($image !== 'default.png') {
            $stmt = $pdo->prepare("UPDATE products SET category_id=?, brand_id=?, product_name=?, barcode=?, size=?, color=?, purchase_price=?, selling_price=?, discount_percent=?, stock_quantity=?, is_featured=?, reorder_level=?, image=?, gender=? WHERE id=?");
            $stmt->execute([$category_id, $brand_id, $product_name, $barcode, $size, $color, $purchase_price, $selling_price, $discount_percent, $stock_quantity, $is_featured, $reorder_level, $image, $gender, $product_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET category_id=?, brand_id=?, product_name=?, barcode=?, size=?, color=?, purchase_price=?, selling_price=?, discount_percent=?, stock_quantity=?, is_featured=?, reorder_level=?, gender=? WHERE id=?");
            $stmt->execute([$category_id, $brand_id, $product_name, $barcode, $size, $color, $purchase_price, $selling_price, $discount_percent, $stock_quantity, $is_featured, $reorder_level, $gender, $product_id]);
        }
    }
    header("Location: products.php");
    exit();
}

if (isset($_GET['delete'])) {
    if ($role !== 'admin') die("Unauthorized access");
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: products.php");
    exit();
}

// Fetch lists (Including dynamic calculation of units purchased / sold)
$products = $pdo->query("
    SELECT p.*, c.category_name, b.brand_name,
           COALESCE((SELECT SUM(si.quantity) FROM sale_items si WHERE si.product_id = p.id), 0) as purchased_qty 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN brands b ON p.brand_id = b.id 
    ORDER BY p.id DESC
")->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands ORDER BY brand_name")->fetchAll();
?>

<!-- Outer Premium Mode Wrapper -->
<div class="premium-dark-page" style="margin: -24px; padding: 24px;">
    
    <!-- Top Sleek Header Filters Section -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-3">
            <h2 class="fw-bold m-0 text-white">Product Inventory</h2>
        </div>
        
        <div class="col-md-9 d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
            <!-- Live Search Bar -->
            <div class="position-relative" style="min-width: 250px;">
                <input type="text" id="searchInventory" class="form-control ps-5" placeholder="Search by Name or Code..." onkeyup="filterTable()">
                <i class="fas fa-search position-absolute text-muted" style="left: 15px; top: 12px;"></i>
            </div>
            
            <!-- Gender Filter Dropdown -->
            <select id="filterGender" class="form-select" style="width: 140px;" onchange="filterTable()">
                <option value="">All Genders</option>
                <option value="Men's">Men's</option>
                <option value="Women's">Women's</option>
                <option value="Unisex">Unisex</option>
            </select>
            
            <!-- Category Filter Dropdown -->
            <select id="filterCategory" class="form-select" style="width: 160px;" onchange="filterTable()">
                <option value="">All Categories</option>
                <?php foreach($categories as $c): ?>
                    <option value="<?= htmlspecialchars($c['category_name']) ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <!-- Discount Toggle Button -->
            <button id="btnDiscountFilter" class="btn btn-dark-premium px-3" onclick="toggleDiscountFilter()" title="Filter Active Discounts">
                <i class="fas fa-percentage"></i>
            </button>
            
            <?php if ($role === 'admin'): ?>
            <!-- Orange Add Product Button -->
            <button class="btn btn-orange-premium d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetForm()">
                <i class="fas fa-plus"></i> Add Product
            </button>
            <?php endif; ?>
            
            <!-- White Download Button -->
            <button class="btn btn-light px-3 py-2 border-0" onclick="window.print()" title="Print Inventory Report">
                <i class="fas fa-download text-dark"></i>
            </button>
        </div>
    </div>

    <!-- Product Inventory Table Card -->
    <div class="card border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle m-0" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Product</th>
                            <th>Gender</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Purchased</th>
                            <th class="text-center">Featured</th>
                            <th>Discount</th>
                            <?php if ($role === 'admin'): ?>
                            <th class="text-center">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $p): ?>
                        <tr class="out-of-stock-container" 
                            data-gender="<?= htmlspecialchars($p['gender'] ?? 'Unisex') ?>"
                            data-category="<?= htmlspecialchars($p['category_name'] ?? '') ?>"
                            data-discount="<?= $p['discount_percent'] ?>">
                            
                            <!-- Diagonal Out of Stock Ribbon (if stock is 0) -->
                            <?php if ($p['stock_quantity'] <= 0): ?>
                                <td style="padding:0; width:0; border:0; position:relative;">
                                    <div class="out-of-stock-ribbon">Out of Stock</div>
                                </td>
                            <?php endif; ?>
                            
                            <!-- Product Code (Barcode) -->
                            <td class="product-code-cell fw-semibold font-monospace" style="color: #cbd5e1;">
                                <?= htmlspecialchars($p['barcode'] ?: 'PRD-'.str_pad($p['id'], 3, '0', STR_PAD_LEFT)) ?>
                            </td>
                            
                            <!-- Product Name & Circular Thumbnail -->
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php 
                                    $img_src = (file_exists('assets/images/'.$p['image']) && $p['image'] != 'default.png') ? 'assets/images/'.$p['image'] : null;
                                    if ($img_src): 
                                    ?>
                                        <img src="<?= $img_src ?>" class="product-avatar-circle">
                                    <?php else: ?>
                                        <div class="product-avatar-fallback">
                                            <i class="fas fa-tshirt"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex align-items-center">
                                        <span class="product-name-cell fw-bold text-white"><?= htmlspecialchars($p['product_name']) ?></span>
                                        <?php if ($p['discount_percent'] > 0): ?>
                                            <span class="badge-discount-percent">-<?= $p['discount_percent'] ?>%</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            
                            <!-- Gender -->
                            <td>
                                <?php 
                                $gender_class = 'badge-gender-unisex';
                                if ($p['gender'] === "Men's") $gender_class = 'badge-gender-mens';
                                if ($p['gender'] === "Women's") $gender_class = 'badge-gender-womens';
                                ?>
                                <span class="<?= $gender_class ?>"><?= htmlspecialchars($p['gender'] ?? 'Unisex') ?></span>
                            </td>
                            
                            <!-- Category -->
                            <td>
                                <span class="badge-category-pill"><?= htmlspecialchars($p['category_name'] ?? 'General') ?></span>
                            </td>
                            
                            <!-- Price -->
                            <td class="text-white fw-medium">
                                Rs. <?= number_format($p['selling_price'], 2) ?>
                            </td>
                            
                            <!-- Stock -->
                            <td>
                                <?php if($p['stock_quantity'] <= $p['reorder_level']): ?>
                                    <span class="fw-bold text-danger"><?= $p['stock_quantity'] ?></span>
                                <?php else: ?>
                                    <span class="fw-bold" style="color: #cbd5e1;"><?= $p['stock_quantity'] ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Dynamic Purchased (Sold) count -->
                            <td class="fw-bold text-success">
                                <?= number_format($p['purchased_qty']) ?>
                            </td>
                            
                            <!-- Featured -->
                            <td class="text-center">
                                <input type="checkbox" class="premium-custom-checkbox" disabled <?= $p['is_featured'] ? 'checked' : '' ?>>
                            </td>
                            
                            <!-- Discount Button/Badge -->
                            <td>
                                <?php if ($p['discount_percent'] > 0): ?>
                                    <button class="btn btn-sm btn-danger py-1 px-3 fw-bold rounded-pill text-white border-0" style="font-size:0.75rem;">
                                        <?= $p['discount_percent'] ?>%
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.8rem; margin-left: 10px;">None</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Edit/Delete Action Buttons -->
                            <?php if ($role === 'admin'): ?>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <button class="btn btn-sm btn-dark-premium text-info border-0" onclick='editProduct(<?= json_encode($p) ?>)' title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-dark-premium text-danger border-0" onclick="return confirm('Are you sure you want to delete this product?')" title="Delete Product">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="<?= $role === 'admin' ? '10' : '9' ?>" class="text-center text-muted py-4">No products found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Redesigned to Premium Dark-violet Theme -->
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content glass-receipt-modal" enctype="multipart/form-data" style="border: 1px solid var(--border-color-dark) !important;">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold text-dark" id="modalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body premium-dark-page py-2" style="background: transparent; min-height: auto; padding: 20px;">
                <input type="hidden" name="product_id" id="product_id">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="product_name" id="product_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Product Code / Barcode</label>
                        <input type="text" name="barcode" id="barcode" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">Select Category</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Brand</label>
                        <select name="brand_id" id="brand_id" class="form-select">
                            <option value="">Select Brand</option>
                            <?php foreach($brands as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['brand_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Gender</label>
                        <select name="gender" id="gender" class="form-select">
                            <option value="Unisex">Unisex</option>
                            <option value="Men's">Men's</option>
                            <option value="Women's">Women's</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Size</label>
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
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Color</label>
                        <input type="text" name="color" id="color" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Purchase Price (LKR)</label>
                        <input type="number" step="0.01" name="purchase_price" id="purchase_price" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Selling Price (LKR)</label>
                        <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control" value="0" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Initial Stock</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" class="form-control" value="0" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Reorder Level</label>
                        <input type="number" name="reorder_level" id="reorder_level" class="form-control" value="5" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Discount Percent (%)</label>
                        <input type="number" name="discount_percent" id="discount_percent" class="form-control" value="0" min="0" max="100">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="text-muted fw-semibold mb-1" style="font-size:0.85rem;">Product Image</label>
                        <input type="file" name="image" id="image" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-12 d-flex align-items-center gap-2">
                        <input type="checkbox" name="is_featured" id="is_featured" class="premium-custom-checkbox">
                        <label for="is_featured" class="text-muted fw-semibold m-0" style="font-size:0.9rem; cursor:pointer;">Mark this item as Featured (Show prominently in inventory / front POS)</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-dark-premium" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_product" class="btn btn-orange-premium">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
// Filter search list table dynamically
function filterTable() {
    let search = $('#searchInventory').val().toLowerCase();
    let gender = $('#filterGender').val();
    let category = $('#filterCategory').val();
    let showDiscountOnly = $('#btnDiscountFilter').hasClass('active');

    $('#inventoryTable tbody tr').each(function() {
        let row = $(this);
        let name = row.find('.product-name-cell').text().toLowerCase();
        let code = row.find('.product-code-cell').text().toLowerCase();
        let rowGender = row.data('gender');
        let rowCategory = row.data('category');
        let rowDiscount = parseInt(row.data('discount')) || 0;

        let matchesSearch = !search || name.includes(search) || code.includes(search);
        let matchesGender = !gender || rowGender === gender;
        let matchesCategory = !category || rowCategory === category;
        let matchesDiscount = !showDiscountOnly || rowDiscount > 0;

        if (matchesSearch && matchesGender && matchesCategory && matchesDiscount) {
            row.show();
        } else {
            row.hide();
        }
    });
}

function toggleDiscountFilter() {
    let btn = $('#btnDiscountFilter');
    btn.toggleClass('active btn-dark-premium btn-danger');
    filterTable();
}

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
    
    // Bind reset fields
    document.getElementById('gender').value = 'Unisex';
    document.getElementById('discount_percent').value = '0';
    document.getElementById('is_featured').checked = false;
    
    document.getElementById('modalTitle').innerText = 'Add Product';
}

function editProduct(p) {
    document.getElementById('product_id').value = p.id;
    document.getElementById('product_name').value = p.product_name;
    document.getElementById('barcode').value = p.barcode || '';
    document.getElementById('category_id').value = p.category_id || '';
    document.getElementById('brand_id').value = p.brand_id || '';
    document.getElementById('size').value = p.size || '';
    document.getElementById('color').value = p.color || '';
    document.getElementById('purchase_price').value = p.purchase_price;
    document.getElementById('selling_price').value = p.selling_price;
    document.getElementById('stock_quantity').value = p.stock_quantity;
    document.getElementById('reorder_level').value = p.reorder_level;
    
    // Bind edit values
    document.getElementById('gender').value = p.gender || 'Unisex';
    document.getElementById('discount_percent').value = p.discount_percent || '0';
    document.getElementById('is_featured').checked = parseInt(p.is_featured) === 1;

    document.getElementById('modalTitle').innerText = 'Edit Product';
    var myModal = new bootstrap.Modal(document.getElementById('productModal'));
    myModal.show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
