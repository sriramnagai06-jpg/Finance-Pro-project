# Installation Guide

Follow these steps to deploy FinancePro on your local XAMPP environment.

## Prerequisites
- XAMPP installed (PHP 8.0+ and MySQL).
- Any modern web browser.

## Step 1: Extract Files
1. Copy the `FinancePro` project folder.
2. Paste it into your XAMPP `htdocs` directory (e.g., `C:\xampp\htdocs\FinancePro`).

## Step 2: Database Setup
1. Open XAMPP Control Panel.
2. Start the **Apache** and **MySQL** modules.
3. Open your browser and go to `http://localhost/phpmyadmin`.
4. Go to the **Import** tab (no need to create the database first - the script does that).
5. Choose the file `database/financepro.sql` from your project folder and click **Go**.
6. That's it - `financepro.sql` is a single, self-contained script. It creates every table the app needs (including chart of accounts, journal entries, notifications, GST fields, etc.) and seeds the demo data. There is nothing else to run.

## Step 3: Configuration (If needed)
1. Open `config.php` in a text editor.
2. Ensure the database credentials match your XAMPP setup:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'financepro');
   define('DB_PORT', 3306); // standard XAMPP MySQL port - only change this if you know your MySQL runs on a different port
   ```

## Step 4: Run the Application
1. Open your browser.
2. Navigate to: `http://localhost/FinancePro/login.php`

## Default Credentials
**Admin User:**
- Email: `admin@financepro.com`
- Password: `Admin@123`

**Standard User:**
- Email: `demo@financepro.com`
- Password: `Demo@123`
