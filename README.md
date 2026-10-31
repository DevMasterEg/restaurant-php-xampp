# Tasty Bites — Restaurant Ordering System (XAMPP Edition)

A complete restaurant pickup-ordering web app built with **plain PHP + HTML + CSS + MySQL**, designed to run 100% inside **XAMPP** — no Composer, no Node.js, nothing to download or install besides XAMPP itself.

## Features

- **Customer storefront** — menu with categories, emoji dish cards, quantity steppers, cart
- **Pickup time down to the MINUTE** — customer picks a date (next 7 days) and types/picks any minute between 11:00 and 22:30 (e.g. 13:47). Quick-time buttons included; the time is validated live in the browser *and* again on the server (15 min lead time)
- **3 organized MySQL databases**:
  - `restaurant_accounts` → user accounts (admin + customers, hashed passwords)
  - `restaurant_menu` → menu items (13 seeded dishes)
  - `restaurant_orders` → orders + order items
- **Admin panel** — order dashboard with status flow (pending → confirmed → preparing → ready → completed), menu CRUD, and full user management
- **User management 3 ways** — Admin → Users tab in the browser, the `manage-users.bat` CLI, or phpMyAdmin directly
- **Fixed, responsive layout UI** — sticky dark header, mobile burger menu, warm amber/stone theme, works on phone and desktop

## Install (XAMPP on Windows)

1. **Copy this whole folder** into your XAMPP htdocs, so the path is:
   `C:\xampp\htdocs\restaurant` (any folder name works — even `0`)
2. **Double-click `setup.bat`** — it finds your XAMPP folder, starts MySQL if needed, imports `database.sql` (creates all **3 databases** with demo data) and verifies the tables.
   *(Alternatively: start Apache + MySQL in the XAMPP Control Panel, open http://localhost/phpmyadmin, go to **Import**, choose `database.sql` → Go. You should then see `restaurant_accounts`, `restaurant_menu`, `restaurant_orders` in the left sidebar.)*
3. Start **Apache** (XAMPP Control Panel or double-click `start-restaurant.bat`).
4. Open **http://localhost/YOUR-FOLDER-NAME** (e.g. http://localhost/0/ if the folder is named `0`)

> Old single database `restaurant_db` from a previous version can be safely deleted in phpMyAdmin.

### Default logins

| Role     | Email                    | Password    |
|----------|--------------------------|-------------|
| Admin    | admin@restaurant.com     | admin123    |
| Customer | customer@restaurant.com  | customer123 |

> Change the admin password right away (Admin → Users → Reset pw, or `manage-users.bat` → option 7).

## Managing users

**Option A — the web UI:** log in as admin → **Users** tab: add, promote/demote, disable/enable, reset password, delete (with self-protection so you can't lock yourself out).

**Option B — `manage-users.bat`:** double-click it. A menu-driven console tool backed by the same MySQL database:

```
1) Statistics          6) Enable / disable account
2) List all users      7) Reset password
3) Search users        8) Edit name / phone
4) Add user            9) Delete user
5) Change role         0) Exit
```

## File structure

```
restaurant/
├── database.sql          ← creates the 3 databases + seed data (import via setup.bat or phpMyAdmin)
├── config.php            ← DB settings (edit here if your MySQL uses a password) + helpers
├── index.php             ← storefront menu + add to cart
├── cart.php  checkout.php  orders.php
├── login.php  register.php  logout.php
├── check.php             ← self-diagnosis page (green/red checklist)
├── admin/
│   ├── index.php         ← order dashboard (statuses, search, stats)
│   ├── menu.php          ← menu CRUD
│   └── users.php         ← user management
├── includes/  assets/    ← shared layout + CSS/JS
├── manage_users_cli.php  ← CLI engine behind manage-users.bat
├── setup.bat  start-restaurant.bat  manage-users.bat
└── README-WINDOWS.txt    ← quick-start in plain language
```

## Changing database settings

XAMPP defaults (MySQL root, empty password, port 3306) are pre-configured in `config.php`. If you changed the MySQL root password or port, edit these lines at the top:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_ACCOUNTS', 'restaurant_accounts');  // user accounts
define('DB_MENU',     'restaurant_menu');      // menu items
define('DB_ORDERS',   'restaurant_orders');    // orders + order items
define('DB_USER', 'root');
define('DB_PASS', '');
```

## Customizing pickup rules

Also in `config.php`:

```php
define('KITCHEN_OPEN',  '11:00');  // first minute customers can pick up
define('KITCHEN_CLOSE', '22:30');  // last minute customers can pick up
define('LEAD_MINUTES',  15);       // minimum order lead time
define('DAYS_AHEAD',    7);        // how far customers can book
```
"# restaurant-php-xampp" 
"# restaurant-php-xampp" 
