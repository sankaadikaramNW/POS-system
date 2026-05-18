<!-- ================= HEADER ================= -->
<h1 align="center">🛍️ DX Fashion POS System</h1>
<h3 align="center">A Comprehensive Web-Based Point of Sale & Inventory Management System</h3>

---

## 📝 About The Project

**DX Fashion POS System** is a robust, full-stack Point of Sale application specifically designed for clothing retail shops. Built to handle everyday business operations smoothly, it features real-time inventory tracking, seamless checkout processes, automated printable receipt generation, and detailed sales reporting. 

The system implements Role-Based Access Control (RBAC) to ensure secure operations, distinguishing between Admin privileges (full control) and Cashier privileges (sales and customer operations only).

---

## 🚀 Key Features

- 🔐 **Role-Based Authentication** – Secure login with password hashing for Admins and Cashiers.
- 📊 **Dynamic Dashboard** – Real-time tracking of today's sales, monthly revenue, and low-stock alerts.
- 🛒 **Smart POS Terminal** – AJAX-powered product search, real-time cart calculation (tax & discount), and barcode scanning support.
- 📦 **Inventory Management** – Complete CRUD operations for products, categories, and brands.
- 📋 **Stock Tracking** – Automated stock deductions during sales and manual "Stock In/Out" logging.
- 👥 **User Management** – Dedicated panels for managing Customers, Suppliers, and System Users.
- 🧾 **Printable Receipts** – Auto-generated, printer-friendly thermal receipts for every transaction.
- 📈 **Detailed Reports** – Daily and monthly sales analytics to track business performance.

---

## 🧰 Tech Stack

### 💻 Core Technologies
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

### 🌐 Frontend & Styling
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-563D7C?style=for-the-badge&logo=bootstrap&logoColor=white)
![jQuery](https://img.shields.io/badge/jQuery-0769AD?style=for-the-badge&logo=jquery&logoColor=white)

---

## 🗄️ Database Architecture

The system operates on a highly normalized relational database named `fashion_pos` comprising **13 structured tables**:
- `users`, `employees`, `attendance`
- `products`, `categories`, `brands`
- `customers`, `suppliers`
- `sales`, `sale_items`
- `purchases`, `purchase_items`
- `inventory_logs`

---

## ⚙️ Installation & Setup

Follow these steps to run the project locally on your machine:

1. **Install XAMPP/WAMP**
   - Download and install [XAMPP](https://www.apachefriends.org/index.html).
   - Start the **Apache** and **MySQL** modules from the XAMPP Control Panel.

2. **Clone the Repository**
   - Clone this project into your `htdocs` directory (e.g., `C:\xampp\htdocs\POS system`).

3. **Database Setup**
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Create a new database named **`fashion_pos`**.
   - Import the provided SQL schema file located at `sql/fashion_pos.sql`.

4. **Access the System**
   - Open your web browser and go to: `http://localhost/POS%20system/login.php`

### 🔑 Default Login Credentials

**Admin Account**
- **Username:** `admin`
- **Password:** `password123`

**Cashier Account**
- **Username:** `cashier`
- **Password:** `password123`

---

⭐️ *If you found this project helpful, please consider giving it a star!*
