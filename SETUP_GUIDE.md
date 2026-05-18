# Fashion Clothing POS System Setup Guide

## Requirements
- XAMPP or WAMP server
- PHP 7.4 or higher
- MySQL 5.7 or higher

## Installation Steps

1. **Start XAMPP**
   Open XAMPP Control Panel and start the **Apache** and **MySQL** modules.

2. **Database Setup**
   - Open your browser and go to `http://localhost/phpmyadmin`
   - Click on "Import" in the top menu.
   - Choose the file `c:\xampp\htdocs\POS system\sql\fashion_pos.sql` (or wherever you placed the `sql/fashion_pos.sql` file) and click **Go**.
   - This will automatically create the `fashion_pos` database and populate it with tables and sample data.

3. **Database Configuration**
   If you have a password on your MySQL root user, you must edit `config/database.php`:
   ```php
   $username = 'root'; 
   $password = 'YOUR_PASSWORD'; // Add your MySQL password here
   ```

4. **Access the System**
   Open your browser and navigate to the project directory, for example:
   `http://localhost/POS%20system/index.php`

## Login Credentials

**Admin Account**
- **Username:** `admin`
- **Password:** `password123`

**Cashier Account**
- **Username:** `cashier`
- **Password:** `password123`

## Usage Notes
- **User Management** is strictly available for the `Admin` role.
- **Images** for products will be uploaded to the `assets/images` directory. Ensure it has correct write permissions.
- The POS system supports scanning barcodes by clicking the search box and scanning with a connected barcode reader.
