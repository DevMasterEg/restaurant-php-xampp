<?php
/**
 * Restaurant Ordering System - XAMPP Edition
 * Core configuration: databases, session, helpers.
 * Pure PHP + MySQL. No frameworks, no Composer, nothing to download.
 */

// ---------- DATABASE SETTINGS (XAMPP defaults - change here if needed) ----------
// The project is organized into 3 separate databases:
//   restaurant_accounts -> user accounts (admin + customers)
//   restaurant_menu     -> menu items (the dishes)
//   restaurant_orders   -> orders + order items
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_ACCOUNTS', 'restaurant_accounts');
define('DB_MENU',     'restaurant_menu');
define('DB_ORDERS',   'restaurant_orders');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP default: root has no password

// ---------- PICKUP SETTINGS ----------
define('KITCHEN_OPEN',  '11:00'); // first minute you can pick up
define('KITCHEN_CLOSE', '22:30'); // last minute you can pick up
define('LEAD_MINUTES',  15);      // must order at least 15 min ahead
define('DAYS_AHEAD',    7);       // customer can pick a date up to 7 days ahead

// ---------- ERROR REPORTING ----------
// display_errors is ON while you set the project up, so real problems show on screen.
// After everything works you may change '1' to '0' for a tidier look.
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// ---------- DATABASE CONNECTION (PDO) ----------
// ONE connection to the MySQL server. Every query addresses its table with the
// FULL database name (e.g. restaurant_accounts.users), which also lets us JOIN
// across the 3 databases on this single connection.
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            db_error_page($e->getMessage());
        }
    }
    return $pdo;
}

// ---------- SESSION ----------
if (session_status() === PHP_SESSION_NONE) {
    // fallback if the configured session save path doesn't exist / isn't writable
    $sp = ini_get('session.save_path');
    if (!$sp || !is_dir($sp) || !is_writable($sp)) {
        $fallback = sys_get_temp_dir() . '/tastybites_sessions';
        if (!is_dir($fallback)) @mkdir($fallback, 0700, true);
        if (is_dir($fallback) && is_writable($fallback)) session_save_path($fallback);
    }
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    } else {
        session_set_cookie_params(0, '/');   // old PHP: no array support
    }
    session_start();
}

// ---------- FRIENDLY DATABASE ERROR PAGE ----------
// NOTE: the HTML must be larger than 512 bytes, otherwise Chrome replaces it
// with its own "HTTP ERROR 500" page and hides the real explanation.
function db_error_page(string $message): void {
    http_response_code(503);
    $msg    = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $host   = htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8');
    $port   = htmlspecialchars(DB_PORT, ENT_QUOTES, 'UTF-8');
    $user   = htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8');
    $dblist = htmlspecialchars(DB_ACCOUNTS . ', ' . DB_MENU . ', ' . DB_ORDERS, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<title>Database setup needed - Tasty Bites</title></head>'
       . '<body style="font-family:Segoe UI,Arial,sans-serif;background:#fafaf9;color:#292524;margin:0;padding:40px 20px">'
       . '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:14px;padding:28px 30px;box-shadow:0 4px 14px rgba(0,0,0,.08)">'
       . '<h1 style="margin:0 0 8px;color:#b45309;font-size:26px">&#127860; Database not ready yet</h1>'
       . '<p style="font-size:15px;line-height:1.6">The web pages are fine, but MySQL could not be reached on <b>' . $host . ':' . $port . '</b> with user <b>' . $user . '</b>. The project needs 3 databases: <b>' . $dblist . '</b>.</p>'
       . '<div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:14px 16px;margin:14px 0;font-size:14px;line-height:1.7">'
       . '<b>How to fix it (2 minutes):</b><br>1. Open the <b>XAMPP Control Panel</b> and make sure MySQL says <b>Running</b>.<br>'
       . '2. Double-click <b>setup.bat</b> inside this project folder - it creates all 3 databases automatically.<br>'
       . '3. Reload this page.</div>'
       . '<p style="font-size:13px;color:#57534e;line-height:1.6">Still stuck? Open <b>check.php</b> in your browser '
       . '(for example http://localhost/YOUR-FOLDER-NAME/check.php) - it will tell you exactly what is wrong. '
       . 'If you changed the MySQL root password or port, edit the values at the top of <b>config.php</b>.</p>'
       . '<p style="font-size:12px;color:#a8a29e;margin-top:18px">Technical detail: ' . $msg . '</p>'
       . '</div></body></html>';
    exit;
}

// ---------- HTML ESCAPING ----------
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---------- AUTH ----------
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = false;
    if ($user === false) {
        $stmt = db()->prepare('SELECT * FROM ' . DB_ACCOUNTS . '.users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if ($user && !(int)$user['active']) {
            // account disabled while logged in -> kick out
            session_destroy();
            $user = null;
        }
    }
    return $user;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: ' . base_url() . '/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }
}

function require_admin(): void {
    require_login();
    if ((current_user()['role'] ?? '') !== 'ADMIN') {
        http_response_code(403);
        flash('error', 'Admins only.');
        header('Location: ' . base_url() . '/index.php');
        exit;
    }
}

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}
function csrf_check(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(419);
        die('Invalid CSRF token. Go back and try again.');
    }
}

// ---------- FLASH MESSAGES ----------
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
function flash_take(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- CART (session based) ----------
function cart(): array {                     // [menu_item_id => qty]
    return $_SESSION['cart'] ?? [];
}
function cart_count(): int {
    return array_sum(cart());
}
function cart_clear(): void {
    unset($_SESSION['cart']);
}

// ---------- MONEY ----------
function money(float $n): string {
    return '$' . number_format($n, 2);
}

// ---------- PICKUP TIME (any minute, e.g. 13:47) ----------
function hhmm(int $minutes): string {
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function to_minutes(string $hhmm): int {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return $h * 60 + $m;
}

function kitchen_open_minutes(): int  { return to_minutes(KITCHEN_OPEN); }
function kitchen_close_minutes(): int { return to_minutes(KITCHEN_CLOSE); }

/**
 * Earliest selectable pickup minute for a date (in minutes of the day).
 * Returns null when the whole day's kitchen window has already passed.
 */
function earliest_pickup_minutes(string $date): ?int {
    if ($date < date('Y-m-d')) return null;
    $open = kitchen_open_minutes();
    if ($date === date('Y-m-d')) {
        // now + lead time, rounded UP to the next full 5 minutes
        $min = intdiv(((int)date('G') * 60 + (int)date('i') + LEAD_MINUTES + 4), 5) * 5;
        if ($min > $open) $open = $min;
    }
    return $open <= kitchen_close_minutes() ? $open : null;
}

function pickup_dates(): array {
    // next 7 days: ['YYYY-MM-DD' => 'Mon, Oct 27']
    $out = [];
    for ($i = 0; $i < DAYS_AHEAD; $i++) {
        $ts = strtotime("+$i day");
        $out[date('Y-m-d', $ts)] = date('D, M j', $ts);
    }
    return $out;
}

/**
 * Validate a requested pickup date + time (any minute) against kitchen rules.
 * Returns [true, ''] on success or [false, 'reason'].
 */
function pickup_validate(string $date, string $time): array {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) return [false, 'Invalid date.'];
    if ($date < date('Y-m-d'))                return [false, 'That date already passed - pick another day.'];

    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $m) || (int)$m[1] > 23 || (int)$m[2] > 59) {
        return [false, 'Please enter a valid time like 13:45.'];
    }
    $mins = (int)$m[1] * 60 + (int)$m[2];
    if ($mins < kitchen_open_minutes() || $mins > kitchen_close_minutes()) {
        return [false, 'Kitchen is open ' . KITCHEN_OPEN . ' to ' . KITCHEN_CLOSE . ' - pick a time inside these hours.'];
    }

    $now      = new DateTime('now');
    $dayStart = new DateTime($date . ' 00:00:00');
    $maxDay   = new DateTime('+' . (DAYS_AHEAD - 1) . ' day 23:59');
    $slot     = new DateTime($date . ' ' . hhmm($mins));

    if ($slot > $maxDay) return [false, 'Pick a date within the next ' . DAYS_AHEAD . ' days.'];
    if ($slot->getTimestamp() < $now->getTimestamp() + LEAD_MINUTES * 60) {
        return [false, 'Pickup must be at least ' . LEAD_MINUTES . ' minutes from now.'];
    }
    return [true, ''];
}

function pickup_label(string $date, string $time): string {
    return date('D, M j', strtotime($date)) . ' &middot; ' . date('g:i A', strtotime($time));
}

// ---------- ORDER STATUS ----------
function status_badge(string $status): string {
    $colors = [
        'PENDING'   => 'badge-amber',
        'CONFIRMED' => 'badge-blue',
        'PREPARING' => 'badge-purple',
        'READY'     => 'badge-green',
        'COMPLETED' => 'badge-gray',
        'CANCELLED' => 'badge-red',
    ];
    return '<span class="badge ' . ($colors[$status] ?? 'badge-gray') . '">' . e(ucfirst(strtolower($status))) . '</span>';
}

function order_statuses(): array {
    return ['PENDING', 'CONFIRMED', 'PREPARING', 'READY', 'COMPLETED', 'CANCELLED'];
}

// ---------- REDIRECT ----------
function redirect(string $url): void {
    if (PHP_SAPI !== 'cli') header('Location: ' . $url);
    exit;
}

// ---------- BASE URL (works whether app is in htdocs root or a subfolder) ----------
function base_url(): string {
    return (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) ? '..' : '.';
}
