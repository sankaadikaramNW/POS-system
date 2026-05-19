<?php
/**
 * ============================================================
 *  DX Fashion POS — Sample Data Seeder
 *  Run this once via browser: http://localhost/POS%20system/seed_data.php
 *  It will INSERT 50+ records into every table.
 *  After running, DELETE this file for security.
 * ============================================================
 */

set_time_limit(300);
require_once 'config/database.php';

echo "<html><head><title>DX Fashion — Data Seeder</title>
<style>
body{font-family:'Segoe UI',sans-serif;background:#1a1a2e;color:#e0e0e0;padding:40px;line-height:1.7}
h1{color:#667eea}h2{color:#764ba2;border-bottom:1px solid #333;padding-bottom:5px;margin-top:25px}
.ok{color:#10b981}.err{color:#ef4444}.count{color:#667eea;font-weight:bold}
pre{background:#0d0d1a;padding:15px;border-radius:8px;overflow-x:auto}
.done{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:12px;margin-top:30px;text-align:center;font-size:1.3rem}
</style></head><body>";
echo "<h1>🛍️ DX Fashion POS — Sample Data Seeder</h1>";

try {
    // Disable FK checks for clean insert
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // ========================================================
    // Helper functions
    // ========================================================
    function randomDate($start, $end) {
        $ts = mt_rand(strtotime($start), strtotime($end));
        return date('Y-m-d H:i:s', $ts);
    }
    function randomDateOnly($start, $end) {
        $ts = mt_rand(strtotime($start), strtotime($end));
        return date('Y-m-d', $ts);
    }
    function pick($arr) { return $arr[array_rand($arr)]; }

    // ========================================================
    // 1. USERS  (6 records — keep existing, add new)
    // ========================================================
    echo "<h2>1. Users</h2>";
    $hashed = password_hash('password123', PASSWORD_DEFAULT);
    $users = [
        ['Sanka Adikaram', 'sanka', $hashed, 'admin'],
        ['Nimal Perera', 'nimal', $hashed, 'cashier'],
        ['Kumari Silva', 'kumari', $hashed, 'cashier'],
        ['Ruwan Fernando', 'ruwan', $hashed, 'cashier'],
        ['Dilini Jayawardena', 'dilini', $hashed, 'admin'],
        ['Kasun Bandara', 'kasun', $hashed, 'cashier'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (full_name, username, password, role) VALUES (?,?,?,?)");
    $c = 0;
    foreach ($users as $u) { $stmt->execute($u); $c += $stmt->rowCount(); }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> new users (password: password123)<br>";

    // Collect all user IDs
    $userIds = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);

    // ========================================================
    // 2. CATEGORIES (12 total)
    // ========================================================
    echo "<h2>2. Categories</h2>";
    $categories = ['T-Shirts','Jeans','Dresses','Jackets','Shorts','Skirts','Hoodies','Sweaters','Blouses','Trousers','Activewear','Accessories'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (category_name) VALUES (?)");
    $c = 0;
    foreach ($categories as $cat) { $stmt->execute([$cat]); $c += $stmt->rowCount(); }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> new categories<br>";

    $catIds = $pdo->query("SELECT id FROM categories")->fetchAll(PDO::FETCH_COLUMN);

    // ========================================================
    // 3. BRANDS (15 total)
    // ========================================================
    echo "<h2>3. Brands</h2>";
    $brands = ['Levis','Zara','H&M','Nike','Adidas','Puma','Gucci','Calvin Klein','Tommy Hilfiger','Ralph Lauren','Under Armour','Gap','Uniqlo','Mango','Bershka'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO brands (brand_name) VALUES (?)");
    $c = 0;
    foreach ($brands as $b) { $stmt->execute([$b]); $c += $stmt->rowCount(); }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> new brands<br>";

    $brandIds = $pdo->query("SELECT id FROM brands")->fetchAll(PDO::FETCH_COLUMN);

    // ========================================================
    // 4. PRODUCTS (60 records)
    // ========================================================
    echo "<h2>4. Products</h2>";

    $productNames = [
        'Classic Crew Neck Tee','Slim Fit Denim Jeans','Floral Maxi Dress','Leather Biker Jacket',
        'Cotton Polo Shirt','High Waist Skinny Jeans','Cocktail Mini Dress','Bomber Jacket',
        'V-Neck Basic Tee','Bootcut Jeans','A-Line Summer Dress','Denim Trucker Jacket',
        'Graphic Print Tee','Ripped Boyfriend Jeans','Wrap Around Dress','Puffer Winter Jacket',
        'Striped Henley Shirt','Cargo Pants','Pleated Midi Skirt','Windbreaker Jacket',
        'Oversized Hoodie','Chino Trousers','Pencil Skirt','Track Jacket',
        'Tank Top','Jogger Pants','Bodycon Dress','Blazer Jacket',
        'Cropped T-Shirt','Wide Leg Palazzo','Sundress Floral','Rain Coat',
        'Muscle Fit Tee','Slim Tapered Chinos','Tunic Blouse','Cardigan Sweater',
        'Long Sleeve Henley','Corduroy Pants','Off-Shoulder Top','Fleece Pullover',
        'Hawaiian Print Shirt','Cargo Shorts','Midi Wrap Skirt','Varsity Jacket',
        'Performance Tank','Athletic Shorts','Sports Bra Top','Running Jacket',
        'Linen Button Down','Bermuda Shorts','Kaftan Dress','Trench Coat',
        'Thermal Undershirt','Sweatpants','Halter Neck Top','Quilted Vest',
        'Dri-Fit Training Tee','Stretch Skinny Jeans','Embroidered Kurti','Nehru Collar Jacket',
        'Cotton Boxer Shorts','Silk Camisole','Ribbed Crop Top','Wool Blend Coat'
    ];

    $sizes = ['S','M','L','XL','XXL'];
    $colors = ['Black','White','Navy Blue','Red','Grey','Olive','Beige','Maroon','Teal','Pink','Charcoal','Cream','Sky Blue','Forest Green','Burgundy'];
    $genders = ["Men's","Women's","Unisex"];

    // Check which columns exist
    $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
    $hasGender = in_array('gender', $cols);
    $hasFeatured = in_array('is_featured', $cols);
    $hasDiscount = in_array('discount_percent', $cols);

    $c = 0;
    foreach ($productNames as $i => $name) {
        $barcode = 'DXF-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
        $catId = pick($catIds);
        $brandId = pick($brandIds);
        $size = pick($sizes);
        $color = pick($colors);
        $purchasePrice = rand(500, 5000);
        $sellingPrice = $purchasePrice + rand(200, 2500);
        $stock = rand(5, 200);
        $reorder = rand(3, 15);
        $gender = pick($genders);
        $featured = rand(0, 1);
        $discountPct = pick([0,0,0,0,5,10,15,20,25]);

        $sql = "INSERT IGNORE INTO products (category_id, brand_id, product_name, barcode, size, color, purchase_price, selling_price, stock_quantity, reorder_level, image";
        $params = [$catId, $brandId, $name, $barcode, $size, $color, $purchasePrice, $sellingPrice, $stock, $reorder, 'default.png'];

        if ($hasGender) { $sql .= ", gender"; $params[] = $gender; }
        if ($hasFeatured) { $sql .= ", is_featured"; $params[] = $featured; }
        if ($hasDiscount) { $sql .= ", discount_percent"; $params[] = $discountPct; }

        $placeholders = implode(',', array_fill(0, count($params), '?'));
        $sql .= ") VALUES ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $c += $stmt->rowCount();
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> products<br>";

    $productIds = $pdo->query("SELECT id FROM products")->fetchAll(PDO::FETCH_COLUMN);
    $productMap = $pdo->query("SELECT id, selling_price, purchase_price, product_name FROM products")->fetchAll(PDO::FETCH_ASSOC);

    // ========================================================
    // 5. CUSTOMERS (55 records)
    // ========================================================
    echo "<h2>5. Customers</h2>";

    $firstNames = ['Amara','Bimal','Chaminda','Dinusha','Eshan','Fathima','Gayan','Hashini','Isuru','Janani',
                   'Kamal','Lahiru','Malini','Nadeesha','Osanda','Pavithra','Qadir','Rashmi','Sampath','Thilini',
                   'Upul','Vindya','Wasana','Xena','Yasiru','Zainab','Anura','Buddhika','Chathura','Damayanthi',
                   'Eranga','Fatima','Gamini','Hansika','Indika','Jayani','Kelum','Lakmini','Mahesh','Nirasha',
                   'Pradeep','Renuka','Suresh','Tharanga','Uthpala','Vimukthi','Waruni','Asanka','Bhagya','Chamari',
                   'Darshana','Erandi','Gayani','Harsha','Indunil'];
    $lastNames = ['Perera','Silva','Fernando','Jayawardena','Bandara','Wijesinghe','Dissanayake','Ratnayake',
                  'Samarasinghe','Gunasekara','Wickremasinghe','Senanayake','Karunaratne','Abeysekara','Herath'];
    $areas = ['Colombo','Kandy','Galle','Matara','Negombo','Kurunegala','Ratnapura','Badulla','Jaffna','Anuradhapura',
              'Polonnaruwa','Trincomalee','Batticaloa','Hambantota','Nuwara Eliya'];

    $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, email, address, loyalty_points) VALUES (?,?,?,?,?)");
    $c = 0;
    foreach ($firstNames as $i => $fn) {
        $ln = pick($lastNames);
        $name = "$fn $ln";
        $phone = '07' . rand(0,9) . rand(1000000,9999999);
        $email = strtolower($fn) . '.' . strtolower($ln) . rand(1,99) . '@gmail.com';
        $address = rand(1,500) . ', ' . pick(['Main St','Temple Rd','Galle Rd','High Level Rd','Station Rd','Lake Dr','Hill St','Park Ave']) . ', ' . pick($areas);
        $loyalty = rand(0, 500);
        $stmt->execute([$name, $phone, $email, $address, $loyalty]);
        $c++;
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> customers<br>";

    $customerIds = $pdo->query("SELECT id FROM customers")->fetchAll(PDO::FETCH_COLUMN);

    // ========================================================
    // 6. SUPPLIERS (20 records)
    // ========================================================
    echo "<h2>6. Suppliers</h2>";

    $supplierNames = [
        'Lanka Garments Ltd','Colombo Textile Traders','Kandy Fashion Hub','Southern Fabrics Co',
        'Prime Clothing Imports','Global Wear Solutions','Silk Route Exports','Metro Fashion Supply',
        'Diamond Garments PLC','CeyFashion Distributors','Royal Textiles Lanka','Pacific Apparel Co',
        'NovaTrend Suppliers','Urban Style Wholesale','FabriCare Lanka','Elegance Fashion House',
        'TrendSetter Imports','StarWear International','BlueLine Garments','FashionForward Lanka'
    ];

    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, phone, email, address) VALUES (?,?,?,?)");
    $c = 0;
    foreach ($supplierNames as $i => $sn) {
        $phone = '011' . rand(2000000, 9999999);
        $slug = strtolower(str_replace([' ', '&'], ['', ''], $sn));
        $email = 'info@' . substr($slug, 0, 15) . '.lk';
        $address = rand(1,200) . ', Industrial Zone, ' . pick($areas);
        $stmt->execute([$sn, $phone, $email, $address]);
        $c++;
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> suppliers<br>";

    $supplierIds = $pdo->query("SELECT id FROM suppliers")->fetchAll(PDO::FETCH_COLUMN);

    // ========================================================
    // 7. EMPLOYEES (25 records)
    // ========================================================
    echo "<h2>7. Employees</h2>";

    $empRoles = ['Sales Associate','Senior Cashier','Floor Manager','Stock Keeper','Visual Merchandiser',
                 'Store Supervisor','Assistant Manager','Customer Service','Tailor','Security Guard'];
    $empNames = [
        'Ashan Wijeratne','Buddhika Perera','Charith Fernando','Dinesh Kumar','Eranthi Mendis',
        'Farhan Ahmed','Gimhani Silva','Harsha Jayasinghe','Ishan Cooray','Jeewanthi Kumari',
        'Kithsiri Bandara','Lakshika Dias','Malith Ranasinghe','Nadeeka Pathirana','Oshadi Gamage',
        'Prasanna Herath','Rajitha Senanayake','Sachini Wijesuriya','Tharindu Gunasekara','Udani Wickremasinghe',
        'Viraj Karunaratne','Wasantha Liyanage','Yashodha Ratnayake','Asiri Abeysekara','Bhashitha Amarasinghe'
    ];

    $stmt = $pdo->prepare("INSERT INTO employees (employee_name, role, phone, salary, joined_date) VALUES (?,?,?,?,?)");
    $c = 0;
    foreach ($empNames as $en) {
        $role = pick($empRoles);
        $phone = '07' . rand(0,9) . rand(1000000,9999999);
        $salary = rand(25000, 85000);
        $joinDate = randomDateOnly('2020-01-01', '2025-12-31');
        $stmt->execute([$en, $role, $phone, $salary, $joinDate]);
        $c++;
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> employees<br>";

    $employeeIds = $pdo->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);

    // ========================================================
    // 8. PURCHASES + PURCHASE_ITEMS (55 purchases, ~140 items)
    // ========================================================
    echo "<h2>8. Purchases & Purchase Items</h2>";

    $purchStmt = $pdo->prepare("INSERT INTO purchases (supplier_id, total_amount, purchase_date, created_by) VALUES (?,?,?,?)");
    $piStmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?,?,?,?,?)");

    $purchCount = 0;
    $piCount = 0;

    for ($i = 0; $i < 55; $i++) {
        $suppId = pick($supplierIds);
        $userId = pick($userIds);
        $purchDate = randomDateOnly('2025-06-01', '2026-05-18');

        // Each purchase has 1-5 items
        $numItems = rand(1, 5);
        $totalAmount = 0;
        $items = [];

        for ($j = 0; $j < $numItems; $j++) {
            $prod = pick($productMap);
            $qty = rand(10, 100);
            $price = (float)$prod['purchase_price'];
            $subtotal = $qty * $price;
            $totalAmount += $subtotal;
            $items[] = [$prod['id'], $qty, $price, $subtotal];
        }

        $purchStmt->execute([$suppId, $totalAmount, $purchDate, $userId]);
        $purchaseId = $pdo->lastInsertId();
        $purchCount++;

        foreach ($items as $item) {
            $piStmt->execute([$purchaseId, $item[0], $item[1], $item[2], $item[3]]);
            $piCount++;
        }
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$purchCount</span> purchases with <span class='count'>$piCount</span> purchase items<br>";

    // ========================================================
    // 9. SALES + SALE_ITEMS (80 sales, ~200 items)
    // ========================================================
    echo "<h2>9. Sales & Sale Items</h2>";

    $saleStmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, total_amount, discount, tax, payment_method, paid_amount, balance, sale_date, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $siStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal) VALUES (?,?,?,?,?)");

    $payMethods = ['Cash','Cash','Cash','Card','Card','Mobile'];
    $saleCount = 0;
    $siCount = 0;

    for ($i = 0; $i < 80; $i++) {
        $invoiceNo = 'INV-' . date('Ymd', strtotime(randomDateOnly('2025-06-01','2026-05-19'))) . str_pad($i+1, 3, '0', STR_PAD_LEFT);
        // 70% chance of having a customer, 30% walk-in
        $custId = (rand(1,10) > 3) ? pick($customerIds) : null;
        $userId = pick($userIds);
        $saleDate = randomDate('2025-06-01 08:00:00', '2026-05-19 21:00:00');
        $payMethod = pick($payMethods);

        // 1-5 items per sale
        $numItems = rand(1, 5);
        $subtotal = 0;
        $items = [];

        for ($j = 0; $j < $numItems; $j++) {
            $prod = pick($productMap);
            $qty = rand(1, 4);
            $price = (float)$prod['selling_price'];
            $itemTotal = $qty * $price;
            $subtotal += $itemTotal;
            $items[] = [$prod['id'], $qty, $price, $itemTotal];
        }

        // Random discount & tax
        $discountPct = pick([0,0,0,0,5,10,15]);
        $discountAmt = round($subtotal * $discountPct / 100, 2);
        $afterDiscount = $subtotal - $discountAmt;
        $taxPct = pick([0,0,0,2,5]);
        $taxAmt = round($afterDiscount * $taxPct / 100, 2);
        $totalAmount = $afterDiscount + $taxAmt;
        $paidAmount = ceil($totalAmount / 100) * 100; // Round up to nearest 100
        $balance = $paidAmount - $totalAmount;

        $saleStmt->execute([$invoiceNo, $custId, $totalAmount, $discountAmt, $taxAmt, $payMethod, $paidAmount, $balance, $saleDate, $userId]);
        $saleId = $pdo->lastInsertId();
        $saleCount++;

        foreach ($items as $item) {
            $siStmt->execute([$saleId, $item[0], $item[1], $item[2], $item[3]]);
            $siCount++;
        }
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$saleCount</span> sales with <span class='count'>$siCount</span> sale items<br>";

    // ========================================================
    // 10. INVENTORY LOGS (100+ records)
    // ========================================================
    echo "<h2>10. Inventory Logs</h2>";

    $logStmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, action_type, quantity, action_date, created_by) VALUES (?,?,?,?,?)");
    $actionTypes = ['STOCK_IN','STOCK_IN','STOCK_IN','STOCK_OUT','SALE','SALE','SALE','DAMAGES','ADJUSTMENT'];
    $c = 0;

    for ($i = 0; $i < 120; $i++) {
        $prodId = pick($productIds);
        $action = pick($actionTypes);
        $qty = ($action === 'STOCK_IN') ? rand(10, 100) : rand(1, 10);
        $date = randomDate('2025-06-01 06:00:00', '2026-05-19 20:00:00');
        $userId = pick($userIds);
        $logStmt->execute([$prodId, $action, $qty, $date, $userId]);
        $c++;
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> inventory logs<br>";

    // ========================================================
    // 11. ATTENDANCE (300+ records — covers many days)
    // ========================================================
    echo "<h2>11. Attendance</h2>";

    $attStmt = $pdo->prepare("INSERT IGNORE INTO attendance (employee_id, attendance_date, status) VALUES (?,?,?)");
    $statuses = ['Present','Present','Present','Present','Present','Absent','Leave'];
    $c = 0;

    // Generate attendance for last 30 days for all employees
    for ($day = 0; $day < 30; $day++) {
        $date = date('Y-m-d', strtotime("-$day days"));
        // Skip Sundays
        if (date('w', strtotime($date)) == 0) continue;

        foreach ($employeeIds as $empId) {
            $status = pick($statuses);
            $attStmt->execute([$empId, $date, $status]);
            $c++;
        }
    }
    echo "<span class='ok'>✓</span> Inserted <span class='count'>$c</span> attendance records<br>";

    // Re-enable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // ========================================================
    // SUMMARY
    // ========================================================
    echo "<h2>📊 Final Record Counts</h2>";
    $tables = ['users','categories','brands','products','customers','suppliers','employees','purchases','purchase_items','sales','sale_items','inventory_logs','attendance'];
    echo "<table style='border-collapse:collapse;width:400px'>";
    echo "<tr style='border-bottom:2px solid #667eea'><th style='text-align:left;padding:8px'>Table</th><th style='text-align:right;padding:8px'>Records</th></tr>";
    $grandTotal = 0;
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $grandTotal += $count;
        $rowColor = ($count >= 50) ? '#10b981' : '#f59e0b';
        echo "<tr style='border-bottom:1px solid #333'><td style='padding:8px'>$t</td><td style='text-align:right;padding:8px;color:$rowColor;font-weight:bold'>$count</td></tr>";
    }
    echo "<tr style='border-top:2px solid #667eea'><td style='padding:8px;font-weight:bold'>TOTAL</td><td style='text-align:right;padding:8px;color:#667eea;font-weight:bold'>$grandTotal</td></tr>";
    echo "</table>";

    echo "<div class='done'>✅ Database seeding completed successfully!<br><small>⚠️ Delete <code>seed_data.php</code> after use for security.</small></div>";

} catch (Exception $e) {
    echo "<div class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>
