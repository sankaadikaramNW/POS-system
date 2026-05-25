<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';

$role = $_SESSION['role'] ?? 'cashier';
if ($role !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$success = ''; $error = '';

// ── BRAND actions ──────────────────────────────────────────
if (isset($_POST['save_brand'])) {
    $name = trim($_POST['brand_name']);
    $id   = $_POST['brand_id'] ?? '';
    if ($name) {
        // Duplicate Check
        if ($id) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM brands WHERE LOWER(brand_name) = LOWER(?) AND id != ?");
            $chk->execute([$name, $id]);
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM brands WHERE LOWER(brand_name) = LOWER(?)");
            $chk->execute([$name]);
        }
        if ($chk->fetchColumn() > 0) {
            header("Location: item_registration.php?tab=brands&error=duplicate"); exit();
        }

        if ($id) {
            $pdo->prepare("UPDATE brands SET brand_name=? WHERE id=?")->execute([$name,$id]);
        } else {
            $pdo->prepare("INSERT INTO brands (brand_name) VALUES (?)")->execute([$name]);
        }
    }
    header("Location: item_registration.php?tab=brands&saved=1"); exit();
}
if (isset($_GET['del_brand'])) {
    $pdo->prepare("DELETE FROM brands WHERE id=?")->execute([$_GET['del_brand']]);
    header("Location: item_registration.php?tab=brands"); exit();
}

// ── CATEGORY actions ───────────────────────────────────────
if (isset($_POST['save_category'])) {
    $name = trim($_POST['category_name']);
    $id   = $_POST['category_id'] ?? '';
    if ($name) {
        // Duplicate Check
        if ($id) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = LOWER(?) AND id != ?");
            $chk->execute([$name, $id]);
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(category_name) = LOWER(?)");
            $chk->execute([$name]);
        }
        if ($chk->fetchColumn() > 0) {
            header("Location: item_registration.php?tab=categories&error=duplicate"); exit();
        }

        if ($id) {
            $pdo->prepare("UPDATE categories SET category_name=? WHERE id=?")->execute([$name,$id]);
        } else {
            $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)")->execute([$name]);
        }
    }
    header("Location: item_registration.php?tab=categories&saved=1"); exit();
}
if (isset($_GET['del_category'])) {
    $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$_GET['del_category']]);
    header("Location: item_registration.php?tab=categories"); exit();
}

// ── ITEM/PRODUCT actions ───────────────────────────────────
if (isset($_POST['save_item'])) {
    $product_name    = trim($_POST['product_name']);
    $category_id     = $_POST['category_id'] ?: null;
    $brand_id        = $_POST['brand_id'] ?: null;
    $barcode         = trim($_POST['barcode']) ?: null;
    $size            = $_POST['size'];
    $color           = $_POST['color'];
    $purchase_price  = $_POST['purchase_price'];
    $selling_price   = $_POST['selling_price'];
    $discount_percent= (int)($_POST['discount_percent'] ?? 0);
    $stock_quantity  = $_POST['stock_quantity'];
    $reorder_level   = $_POST['reorder_level'];
    $is_featured     = isset($_POST['is_featured']) ? 1 : 0;
    $gender          = $_POST['gender'] ?? 'Unisex';
    $product_id      = $_POST['product_id'] ?? '';

    // 1. Duplicate Name + Size combo check
    if ($product_id) {
        $chk_name = $pdo->prepare("SELECT COUNT(*) FROM products WHERE LOWER(product_name) = LOWER(?) AND size = ? AND id != ?");
        $chk_name->execute([$product_name, $size, $product_id]);
    } else {
        $chk_name = $pdo->prepare("SELECT COUNT(*) FROM products WHERE LOWER(product_name) = LOWER(?) AND size = ?");
        $chk_name->execute([$product_name, $size]);
    }
    if ($chk_name->fetchColumn() > 0) {
        header("Location: item_registration.php?tab=items&error=duplicate"); exit();
    }

    // 2. Duplicate Barcode check
    if ($barcode) {
        if ($product_id) {
            $chk_bar = $pdo->prepare("SELECT COUNT(*) FROM products WHERE barcode = ? AND id != ?");
            $chk_bar->execute([$barcode, $product_id]);
        } else {
            $chk_bar = $pdo->prepare("SELECT COUNT(*) FROM products WHERE barcode = ?");
            $chk_bar->execute([$barcode]);
        }
        if ($chk_bar->fetchColumn() > 0) {
            header("Location: item_registration.php?tab=items&error=duplicate_barcode"); exit();
        }
    }

    $image = 'default.png';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif'])) {
            $new_name = time() . '_' . $_FILES['image']['name'];
            if (!is_dir('assets/images')) mkdir('assets/images', 0777, true);
            move_uploaded_file($_FILES['image']['tmp_name'], 'assets/images/' . $new_name);
            $image = $new_name;
        }
    }

    if (empty($product_id)) {
        $pdo->prepare("INSERT INTO products (category_id,brand_id,product_name,barcode,size,color,purchase_price,selling_price,discount_percent,stock_quantity,is_featured,reorder_level,image,gender) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$category_id,$brand_id,$product_name,$barcode,$size,$color,$purchase_price,$selling_price,$discount_percent,$stock_quantity,$is_featured,$reorder_level,$image,$gender]);
    } else {
        if ($image !== 'default.png') {
            $pdo->prepare("UPDATE products SET category_id=?,brand_id=?,product_name=?,barcode=?,size=?,color=?,purchase_price=?,selling_price=?,discount_percent=?,stock_quantity=?,is_featured=?,reorder_level=?,image=?,gender=? WHERE id=?")
                ->execute([$category_id,$brand_id,$product_name,$barcode,$size,$color,$purchase_price,$selling_price,$discount_percent,$stock_quantity,$is_featured,$reorder_level,$image,$gender,$product_id]);
        } else {
            $pdo->prepare("UPDATE products SET category_id=?,brand_id=?,product_name=?,barcode=?,size=?,color=?,purchase_price=?,selling_price=?,discount_percent=?,stock_quantity=?,is_featured=?,reorder_level=?,gender=? WHERE id=?")
                ->execute([$category_id,$brand_id,$product_name,$barcode,$size,$color,$purchase_price,$selling_price,$discount_percent,$stock_quantity,$is_featured,$reorder_level,$gender,$product_id]);
        }
    }
    header("Location: item_registration.php?tab=items&saved=1"); exit();
}
if (isset($_GET['del_item'])) {
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$_GET['del_item']]);
    header("Location: item_registration.php?tab=items"); exit();
}

// ── Fetch data ─────────────────────────────────────────────
$brands     = $pdo->query("SELECT * FROM brands ORDER BY brand_name")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
$products   = $pdo->query("SELECT p.*, c.category_name, b.brand_name FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN brands b ON p.brand_id=b.id ORDER BY p.id DESC")->fetchAll();

$active_tab = $_GET['tab'] ?? 'brands';
$saved      = isset($_GET['saved']);

// Pre-fill edit data from GET (for inline edit)
$edit_brand    = null; $edit_category = null; $edit_item = null;
if (isset($_GET['edit_brand']))    $edit_brand    = $pdo->query("SELECT * FROM brands WHERE id=".(int)$_GET['edit_brand'])->fetch();
if (isset($_GET['edit_category'])) $edit_category = $pdo->query("SELECT * FROM categories WHERE id=".(int)$_GET['edit_category'])->fetch();
if (isset($_GET['edit_item']))     $edit_item     = $pdo->query("SELECT * FROM products WHERE id=".(int)$_GET['edit_item'])->fetch();
if ($edit_item) $active_tab = 'items';
if ($edit_brand) $active_tab = 'brands';
if ($edit_category) $active_tab = 'categories';

require_once 'includes/header.php';
?>

<div class="premium-dark-page" style="margin:-24px;padding:24px;">

<!-- Page Header -->
<div class="row align-items-center mb-4">
    <div class="col">
        <h2 class="fw-bold text-white m-0"><i class="fas fa-layer-group me-2" style="color:var(--primary-orange)"></i>Item Registration</h2>
        <p class="text-muted mb-0" style="font-size:.85rem;">Manage Brands, Categories and Products</p>
    </div>
</div>

<?php if ($saved): ?>
<div class="alert alert-success alert-dismissible fade show border-0" role="alert" style="background:rgba(34,197,94,.15);color:#22c55e;border-left:4px solid #22c55e !important;">
    <i class="fas fa-check-circle me-2"></i> Record saved successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php 
$error_code = $_GET['error'] ?? '';
if ($error_code): 
    $err_text = 'An error occurred.';
    if ($error_code === 'duplicate') {
        $err_text = '⚠️ Already existing item or name! Please enter a unique name.';
    } elseif ($error_code === 'duplicate_barcode') {
        $err_text = '⚠️ Barcode already exists! Please enter a unique barcode.';
    }
?>
<div class="alert alert-danger alert-dismissible fade show border-0" role="alert" style="background:rgba(239,68,68,.15);color:#ef4444;border-left:4px solid #ef4444 !important;">
    <?= htmlspecialchars($err_text) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Tab Nav -->
<ul class="nav nav-pills mb-4 gap-2" id="regTabs">
    <li class="nav-item">
        <a class="nav-link fw-semibold <?= $active_tab=='brands'?'active':'' ?>" href="?tab=brands">
            <i class="fas fa-tag me-1"></i> Brands
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-semibold <?= $active_tab=='categories'?'active':'' ?>" href="?tab=categories">
            <i class="fas fa-th-list me-1"></i> Categories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-semibold <?= $active_tab=='items'?'active':'' ?>" href="?tab=items">
            <i class="fas fa-tshirt me-1"></i> Items / Products
        </a>
    </li>
</ul>

<style>
.nav-pills .nav-link { background:rgba(255,255,255,.06); color:#94a3b8; border:1px solid rgba(255,255,255,.1); }
.nav-pills .nav-link.active { background:var(--primary-orange,#f97316); color:#fff; border-color:transparent; }
.reg-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:14px; padding:28px; }
.reg-table thead th { background:rgba(0,0,0,.3); color:#94a3b8; font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; border:none; }
.reg-table tbody tr { border-bottom:1px solid rgba(255,255,255,.06); }
.reg-table tbody td { color:#cbd5e1; vertical-align:middle; border:none; padding:12px 14px; }
.reg-table tbody tr:hover { background:rgba(255,255,255,.04); }
.form-label-sm { font-size:.8rem; color:#94a3b8; font-weight:600; margin-bottom:4px; display:block; }
</style>

<!-- ════════════════════  BRANDS TAB  ════════════════════ -->
<?php if ($active_tab === 'brands'): ?>
<div class="row g-4">
    <!-- Form -->
    <div class="col-lg-4">
        <div class="reg-card">
            <h5 class="text-white fw-bold mb-4"><i class="fas fa-tag me-2" style="color:var(--primary-orange)"></i><?= $edit_brand ? 'Edit Brand' : 'Add New Brand' ?></h5>
            <form method="POST">
                <input type="hidden" name="brand_id" value="<?= $edit_brand['id'] ?? '' ?>">
                <div class="mb-3">
                    <label class="form-label-sm">Brand Name <span class="text-danger">*</span></label>
                    <input type="text" name="brand_name" class="form-control" value="<?= htmlspecialchars($edit_brand['brand_name'] ?? '') ?>" required placeholder="e.g. Levis, Nike...">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="save_brand" class="btn btn-orange-premium flex-grow-1">
                        <i class="fas fa-save me-1"></i> <?= $edit_brand ? 'Update Brand' : 'Save Brand' ?>
                    </button>
                    <?php if ($edit_brand): ?>
                    <a href="?tab=brands" class="btn btn-dark-premium"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <!-- Table -->
    <div class="col-lg-8">
        <div class="reg-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="text-white fw-bold m-0">All Brands <span class="badge ms-2" style="background:rgba(249,115,22,.2);color:#f97316;font-size:.75rem;"><?= count($brands) ?></span></h5>
                <input type="text" class="form-control form-control-sm w-auto" id="srchBrand" placeholder="Search..." style="width:180px!important;" onkeyup="filterTbl('tblBrand',this.value)">
            </div>
            <div class="table-responsive">
                <table class="table reg-table m-0" id="tblBrand">
                    <thead><tr><th>#</th><th>Brand Name</th><th>Added</th><th class="text-center">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($brands as $i=>$b): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td class="fw-semibold text-white"><?= htmlspecialchars($b['brand_name']) ?></td>
                        <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                        <td class="text-center">
                            <a href="?tab=brands&edit_brand=<?= $b['id'] ?>" class="btn btn-sm btn-dark-premium text-info border-0 me-1"><i class="fas fa-edit"></i></a>
                            <a href="?del_brand=<?= $b['id'] ?>" class="btn btn-sm btn-dark-premium text-danger border-0" onclick="return confirm('Delete brand?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($brands)): ?><tr><td colspan="4" class="text-center text-muted py-4">No brands yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════  CATEGORIES TAB  ════════════════════ -->
<?php if ($active_tab === 'categories'): ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="reg-card">
            <h5 class="text-white fw-bold mb-4"><i class="fas fa-th-list me-2" style="color:var(--primary-orange)"></i><?= $edit_category ? 'Edit Category' : 'Add New Category' ?></h5>
            <form method="POST">
                <input type="hidden" name="category_id" value="<?= $edit_category['id'] ?? '' ?>">
                <div class="mb-3">
                    <label class="form-label-sm">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="category_name" class="form-control" value="<?= htmlspecialchars($edit_category['category_name'] ?? '') ?>" required placeholder="e.g. T-Shirts, Jeans...">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="save_category" class="btn btn-orange-premium flex-grow-1">
                        <i class="fas fa-save me-1"></i> <?= $edit_category ? 'Update Category' : 'Save Category' ?>
                    </button>
                    <?php if ($edit_category): ?>
                    <a href="?tab=categories" class="btn btn-dark-premium"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="reg-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="text-white fw-bold m-0">All Categories <span class="badge ms-2" style="background:rgba(249,115,22,.2);color:#f97316;font-size:.75rem;"><?= count($categories) ?></span></h5>
                <input type="text" class="form-control form-control-sm w-auto" placeholder="Search..." onkeyup="filterTbl('tblCat',this.value)" style="width:180px!important;">
            </div>
            <div class="table-responsive">
                <table class="table reg-table m-0" id="tblCat">
                    <thead><tr><th>#</th><th>Category Name</th><th>Added</th><th class="text-center">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach($categories as $i=>$c): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td class="fw-semibold text-white"><?= htmlspecialchars($c['category_name']) ?></td>
                        <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                        <td class="text-center">
                            <a href="?tab=categories&edit_category=<?= $c['id'] ?>" class="btn btn-sm btn-dark-premium text-info border-0 me-1"><i class="fas fa-edit"></i></a>
                            <a href="?del_category=<?= $c['id'] ?>" class="btn btn-sm btn-dark-premium text-danger border-0" onclick="return confirm('Delete category?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($categories)): ?><tr><td colspan="4" class="text-center text-muted py-4">No categories yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ════════════════════  ITEMS TAB  ════════════════════ -->
<?php if ($active_tab === 'items'): ?>
<div class="row g-4">
    <!-- Item Form -->
    <div class="col-lg-5">
        <div class="reg-card">
            <h5 class="text-white fw-bold mb-4"><i class="fas fa-tshirt me-2" style="color:var(--primary-orange)"></i><?= $edit_item ? 'Edit Item' : 'Register New Item' ?></h5>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?= $edit_item['id'] ?? '' ?>">
                <div class="row g-2 mb-2">
                    <div class="col-7">
                        <label class="form-label-sm">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="product_name" class="form-control" required value="<?= htmlspecialchars($edit_item['product_name'] ?? '') ?>">
                    </div>
                    <div class="col-5">
                        <label class="form-label-sm">Barcode / Code</label>
                        <input type="text" name="barcode" class="form-control" value="<?= htmlspecialchars($edit_item['barcode'] ?? '') ?>">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label-sm">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($edit_item['category_id']??'')==$c['id'])?'selected':'' ?>><?= htmlspecialchars($c['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label-sm">Brand</label>
                        <select name="brand_id" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach($brands as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= (($edit_item['brand_id']??'')==$b['id'])?'selected':'' ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label-sm">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Unisex" <?= (($edit_item['gender']??'Unisex')==='Unisex')?'selected':'' ?>>Unisex</option>
                            <option value="Men's" <?= (($edit_item['gender']??'')==="Men's")?'selected':'' ?>>Men's</option>
                            <option value="Women's" <?= (($edit_item['gender']??'')==="Women's")?'selected':'' ?>>Women's</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label-sm">Size</label>
                        <select name="size" class="form-select">
                            <option value="">None</option>
                            <?php foreach(['S','M','L','XL','XXL'] as $sz): ?>
                            <option value="<?= $sz ?>" <?= (($edit_item['size']??'')===$sz)?'selected':'' ?>><?= $sz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label-sm">Color</label>
                        <input type="text" name="color" class="form-control" value="<?= htmlspecialchars($edit_item['color'] ?? '') ?>">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label-sm">Purchase Price (LKR)</label>
                        <input type="number" step="0.01" name="purchase_price" class="form-control" value="<?= $edit_item['purchase_price'] ?? 0 ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label-sm">Selling Price (LKR)</label>
                        <input type="number" step="0.01" name="selling_price" class="form-control" value="<?= $edit_item['selling_price'] ?? 0 ?>" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label-sm">Stock Qty</label>
                        <input type="number" name="stock_quantity" class="form-control" value="<?= $edit_item['stock_quantity'] ?? 0 ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label-sm">Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" value="<?= $edit_item['reorder_level'] ?? 5 ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label-sm">Discount (%)</label>
                        <input type="number" name="discount_percent" class="form-control" value="<?= $edit_item['discount_percent'] ?? 0 ?>" min="0" max="100">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label-sm">Product Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="mb-3 d-flex align-items-center gap-2">
                    <input type="checkbox" name="is_featured" id="is_featured" class="premium-custom-checkbox" <?= ($edit_item['is_featured']??0)?'checked':'' ?>>
                    <label for="is_featured" class="form-label-sm m-0" style="cursor:pointer;">Mark as Featured</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" name="save_item" class="btn btn-orange-premium flex-grow-1">
                        <i class="fas fa-save me-1"></i> <?= $edit_item ? 'Update Item' : 'Register Item' ?>
                    </button>
                    <?php if ($edit_item): ?>
                    <a href="?tab=items" class="btn btn-dark-premium"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Items Table -->
    <div class="col-lg-7">
        <div class="reg-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="text-white fw-bold m-0">All Items <span class="badge ms-2" style="background:rgba(249,115,22,.2);color:#f97316;font-size:.75rem;"><?= count($products) ?></span></h5>
                <input type="text" class="form-control form-control-sm" placeholder="Search items..." onkeyup="filterTbl('tblItems',this.value)" style="width:190px!important;">
            </div>
            <div class="table-responsive" style="max-height:520px;overflow-y:auto;">
                <table class="table reg-table m-0" id="tblItems">
                    <thead style="position:sticky;top:0;z-index:2;">
                        <tr><th>Code</th><th>Name</th><th>Category</th><th>Brand</th><th>Price</th><th>Stock</th><th class="text-center">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach($products as $p): ?>
                    <tr>
                        <td class="font-monospace" style="font-size:.78rem;color:#94a3b8;"><?= htmlspecialchars($p['barcode'] ?: 'PRD-'.str_pad($p['id'],3,'0',STR_PAD_LEFT)) ?></td>
                        <td class="fw-semibold text-white"><?= htmlspecialchars($p['product_name']) ?></td>
                        <td><span class="badge-category-pill"><?= htmlspecialchars($p['category_name'] ?? '—') ?></span></td>
                        <td style="font-size:.82rem;"><?= htmlspecialchars($p['brand_name'] ?? '—') ?></td>
                        <td class="text-warning fw-semibold">LKR <?= number_format($p['selling_price'],2) ?></td>
                        <td>
                            <?php if ($p['stock_quantity'] <= $p['reorder_level']): ?>
                            <span class="fw-bold text-danger"><?= $p['stock_quantity'] ?></span>
                            <?php else: ?>
                            <span class="fw-bold" style="color:#cbd5e1;"><?= $p['stock_quantity'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="?tab=items&edit_item=<?= $p['id'] ?>" class="btn btn-sm btn-dark-premium text-info border-0 me-1"><i class="fas fa-edit"></i></a>
                            <a href="?del_item=<?= $p['id'] ?>" class="btn btn-sm btn-dark-premium text-danger border-0" onclick="return confirm('Delete item?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($products)): ?><tr><td colspan="7" class="text-center text-muted py-4">No items registered yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div><!-- end premium-dark-page -->

<script>
function filterTbl(tableId, val) {
    val = val.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
