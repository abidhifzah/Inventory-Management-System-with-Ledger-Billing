# Retail POS

A minimal, working Point of Sale system for a Pakistani retail shop selling
toners, printers, laptops and scanners. Plain PHP + MySQL, no framework —
built to run on ordinary cPanel shared hosting (PHP 8.2+/MySQL).

## Features

- **Two product tracking types**: *Bulk* (one shared barcode, quantity count
  — e.g. Toner 47) and *Serialized* (every unit has its own barcode — e.g.
  Printer HP 2025). Stock levels, checkout and stock-out all behave
  differently per type.
- **Stock In**: receive bulk quantities from a vendor, or scan a unique
  barcode for every serialized unit received.
- **Billing / Checkout**: USB barcode scanner support (scanner just types +
  Enter, like a keyboard), manual product search fallback, cart with
  editable quantities (bulk only), partial payment with auto-calculated
  balance due.
- **Customers**: name + phone only (no CNIC/ID/address), purchase history
  with exact serial numbers sold, running balance, and payment recording.
- **Vendors**: contact info, purchase history, payment status
  (paid/partial/due), outstanding balance, payment recording.
- **Stock Out**: automatically logged from sales; manual stock-out for
  damage/return/internal use, for both bulk quantities and specific serial
  numbers.
- **Reports**: sales totals (today/week/month/custom range), best-selling
  products, current stock levels, low-stock alerts, outstanding vendor and
  customer balances.
- Currency: PKR everywhere. Simple, touch-friendly UI that works at
  1024x768 or smaller.

## Project structure

```
config/config.php     Database credentials & app settings (edit this)
database/schema.sql    Full schema + demo seed data
includes/              Shared PHP (db connection, auth, helpers, header/footer)
assets/                CSS and JavaScript
api/                    JSON endpoints used by the billing screen (scan, search, checkout)
*.php (root)           One file per screen (products, billing, customers, vendors, reports, ...)
```

There's no build step — every `.php` file in the project root is a page you
navigate to directly (`billing.php`, `products.php`, etc).

## Running it locally

You need PHP 8.2+ (with the `pdo_mysql` extension) and a MySQL or MariaDB
server. If you don't have PHP or a database server installed, either follow
the manual OS-specific steps in **1)** and **2)** below, or use XAMPP/MAMP,
which bundles Apache/PHP/MySQL/phpMyAdmin for Windows/Mac.

### 1. Get a MySQL/MariaDB server running

- **macOS**: `brew install mariadb && brew services start mariadb`
- **Windows**: install via XAMPP, or MySQL Installer from mysql.com
- **Linux (Debian/Ubuntu)**: `sudo apt install mariadb-server && sudo service mariadb start`

Create the database:

```bash
mysql -u root -e "CREATE DATABASE pos_system CHARACTER SET utf8mb4;"
mysql -u root pos_system < database/schema.sql
```

This creates all tables and seeds demo data: a login, two vendors, a few
bulk products (Toner 47A, Toner 12A, HP Scanjet 2020) and two serialized
products (HP LaserJet Pro M126, Dell Inspiron 15 Laptop) with sample units
already in stock, so the app is instantly demo-able.

### 2. Configure the app

Open `config/config.php` and check the DB settings match what you just
created:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pos_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

(Defaults match a typical local install with no MySQL root password. Adjust
if yours differs.)

### 3. Start the app

PHP has a built-in dev server, no Apache/Nginx needed for local testing:

```bash
php -S localhost:8000
```

Open **http://localhost:8000/login.php** in your browser.

### 4. Log in

- Username: `admin`
- Password: `admin123`

(Change this password, or add more staff logins, directly in the `users`
table once you're comfortable — there's no self-service "add user" screen
in this minimal build.)

### 5. Try the golden path

1. **Products** — see the demo catalogue, or add your own. Choose "Bulk" or
   "Serialized" tracking when adding a product.
2. **Stock In** — receive more of a bulk product (enter qty + vendor +
   price), or receive serialized units (scan/type a barcode per unit).
3. **Billing** — click into the barcode field and type a demo barcode
   (e.g. `8964000000011` for Toner 47A) then press Enter — this simulates a
   USB scanner. Try `SN-HP2025-0001` for a serialized printer unit. Use
   "Manual Search" to add products without a barcode. Enter a customer name
   + phone, adjust the amount paid, and complete the sale.
4. **Invoice** — see the printable receipt with exact serial numbers sold.
5. **Customers / Vendors** — look up the customer or vendor you just
   transacted with, see their ledger, and record a payment.
6. **Stock Out** — log a manual damage/return/internal-use adjustment.
7. **Reports** — see sales totals, best sellers, stock levels and
   outstanding balances update in real time.

A real USB/handheld barcode scanner works out of the box on the Billing and
Stock In screens — scanners act as a keyboard and type the code followed by
Enter, which is exactly what those input fields listen for.

## Deploying to cPanel / shared hosting

1. Create a MySQL database and user in cPanel → **MySQL Databases**, and
   note the generated database name/username (cPanel usually prefixes them
   with your account name, e.g. `myuser_pos_system`).
2. Import `database/schema.sql` via cPanel → **phpMyAdmin** (select your
   database, use the "Import" tab).
3. Upload the project files. If the app should be the whole site, upload
   everything into `public_html/`. If it should live at a sub-path (e.g.
   `yourdomain.com/pos`), upload into `public_html/pos/`.
4. Edit `config/config.php` on the server with your production DB
   credentials, and set `APP_DEBUG` to `false`.
5. Confirm PHP version is 8.2+ in cPanel → **MultiPHP Manager**.
6. Visit `https://yourdomain.com/login.php` (or `/pos/login.php`) and log
   in with the default admin account — then change that password.

No other configuration, `.htaccess`, or build step is required.

## Notes on the data model

- **Bulk products** carry one `barcode` on the `products` row and a
  `stock_qty` counter that's incremented on Stock In and decremented on
  sale/stock-out.
- **Serialized products** carry no product-level barcode. Every physical
  unit is a row in `serial_numbers` with its own `serial_barcode` and a
  `status` (`in_stock`, `sold`, `damaged`, `returned`, `internal_use`,
  `other`). "Stock" for these products is just a count of `in_stock` rows.
- Every sale automatically writes a `stock_out` row per line item
  (`reason = 'sale'`), so the Stock Out log is a complete history of
  everything that has left inventory, sold or otherwise.
- Vendor purchases and customer sales both track `amount_paid` /
  `amount_due` independently, so partial payments and running balances work
  the same way on both sides of the business.
