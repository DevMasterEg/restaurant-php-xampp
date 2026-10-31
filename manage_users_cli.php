<?php
/**
 * Interactive User Management CLI  (run via manage-users.bat)
 * Works on XAMPP: uses its bundled php.exe. No extra installs needed.
 */
if (PHP_SAPI !== 'cli') { echo "CLI only\n"; exit(1); }

require __DIR__ . '/config.php';

// ---------- tiny CLI helpers ----------
function prompt(string $label, string $default = ''): string {
    echo $label . ($default !== '' ? " [$default]" : '') . ': ';
    $raw = function_exists('readline') ? readline() : fgets(STDIN);
    if ($raw === false || $raw === null) {
        // EOF (Ctrl+D / Ctrl+Z / closed pipe) -> quit gracefully
        line('');
        exit(0);
    }
    $in = trim($raw);
    return $in !== '' ? $in : $default;
}
function pause(): void { prompt("\nPress Enter to continue..."); }
function cls(): void {
    if (PHP_OS_FAMILY === 'Windows') {
        passthru('cls 2>NUL');        // native Windows clear
    } else {
        echo "\033[2J\033[H";         // ANSI clear
    }
}
function line(string $s = ''): void { echo $s . PHP_EOL; }

function find_user(int $id): ?array {
    $st = db()->prepare('SELECT * FROM ' . DB_ACCOUNTS . '.users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function u_trunc(string $s, int $n): string {
    if (function_exists('mb_substr')) return mb_substr($s, 0, $n);
    return strlen($s) > $n ? substr($s, 0, $n) : $s;
}

function print_user(array $u, array $orderCount): void {
    line(sprintf(
        "  #%d  %-22s %-28s %-14s %-8s %-8s orders:%d (%s)",
        $u['id'],
        u_trunc($u['name'], 22),
        $u['email'],
        $u['phone'] ?? '-',
        $u['role'],
        $u['active'] ? 'active' : 'DISABLED',
        (int)($orderCount[$u['id']]['c'] ?? 0),
        money((float)($orderCount[$u['id']]['s'] ?? 0))
    ));
}

function pick_user(string $hint = 'User ID (or part of email/name)'): ?array {
    $q = prompt($hint);
    if ($q === '') return null;
    if (preg_match('/^\d+$/', $q)) {
        $u = find_user((int)$q);
        if ($u) return $u;
    }
    $like = "%$q%";
    $st = db()->prepare('SELECT * FROM ' . DB_ACCOUNTS . '.users WHERE email LIKE ? OR name LIKE ? ORDER BY id');
    $st->execute([$like, $like]);
    $rows = $st->fetchAll();
    if (!$rows) { line("  No user matches \"$q\"."); return null; }
    if (count($rows) === 1) return $rows[0];
    line('  Multiple matches:');
    $oc = [];
    foreach (db()->query('SELECT user_id, COUNT(*) c, COALESCE(SUM(total),0) s FROM ' . DB_ORDERS . '.orders GROUP BY user_id')->fetchAll() as $r) $oc[$r['user_id']] = $r;
    foreach ($rows as $i => $u) { echo '  [' . ($i + 1) . ']'; print_user($u, $oc); }
    $sel = (int)prompt('  Which one? (number)', '0');
    return ($sel >= 1 && $sel <= count($rows)) ? $rows[$sel - 1] : null;
}

function order_count_map(): array {
    $map = [];
    foreach (db()->query('SELECT user_id, COUNT(*) c, COALESCE(SUM(total),0) s FROM ' . DB_ORDERS . '.orders GROUP BY user_id')->fetchAll() as $r) {
        $map[$r['user_id']] = $r;
    }
    return $map;
}

// ---------- actions ----------
function action_stats(): void {
    line("\n=== Database statistics ===");
    $users = (int)db()->query('SELECT COUNT(*) FROM ' . DB_ACCOUNTS . '.users')->fetchColumn();
    $admins = (int)db()->query('SELECT COUNT(*) FROM ' . DB_ACCOUNTS . '.users WHERE role = "ADMIN"')->fetchColumn();
    $customers = (int)db()->query('SELECT COUNT(*) FROM ' . DB_ACCOUNTS . '.users WHERE role = "CUSTOMER"')->fetchColumn();
    $disabled = (int)db()->query('SELECT COUNT(*) FROM ' . DB_ACCOUNTS . '.users WHERE active = 0')->fetchColumn();
    $orders = (int)db()->query('SELECT COUNT(*) FROM ' . DB_ORDERS . '.orders')->fetchColumn();
    $revenue = (float)db()->query('SELECT COALESCE(SUM(total),0) FROM ' . DB_ORDERS . '.orders WHERE status != "CANCELLED"')->fetchColumn();
    $items = (int)db()->query('SELECT COUNT(*) FROM ' . DB_MENU . '.menu_items')->fetchColumn();
    line("  Users:      $users  (admins: $admins, customers: $customers, disabled: $disabled)");
    line("  Orders:     $orders");
    line("  Revenue:    " . money($revenue));
    line("  Menu items: $items");
}

function action_list(): void {
    line("\n=== All users ===");
    $oc = order_count_map();
    $rows = db()->query('SELECT * FROM ' . DB_ACCOUNTS . '.users ORDER BY role, name')->fetchAll();
    if (!$rows) { line('  (none)'); return; }
    foreach ($rows as $u) print_user($u, $oc);
    line('  total: ' . count($rows));
}

function action_search(): void {
    $q = prompt('Search name / email / phone');
    if ($q === '') return;
    $like = "%$q%";
    $st = db()->prepare('SELECT * FROM ' . DB_ACCOUNTS . '.users WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id');
    $st->execute([$like, $like, $like]);
    $rows = $st->fetchAll();
    line("\n=== Results for \"$q\" ===");
    if (!$rows) { line('  (none)'); return; }
    $oc = order_count_map();
    foreach ($rows as $u) print_user($u, $oc);
}

function action_add(): void {
    line("\n=== Add user ===");
    $name  = prompt('Full name');
    $email = prompt('Email');
    $phone = prompt('Phone (optional)');
    $role  = strtoupper(prompt('Role (ADMIN/CUSTOMER)', 'CUSTOMER'));
    $role  = $role === 'ADMIN' ? 'ADMIN' : 'CUSTOMER';
    do {
        $pass = prompt('Password (min 6 chars, empty = generate)');
        if ($pass === '') {
            $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $pass = substr(str_shuffle($chars), 0, 8);
            line("  generated password: $pass");
            break;
        }
    } while (strlen($pass) < 6 && (line('  too short, try again') || true));

    $okName = $name !== ''; $okMail = filter_var($email, FILTER_VALIDATE_EMAIL);
    if (!$okName || !$okMail) { line('  Invalid name or email. Aborted.'); return; }
    $st = db()->prepare('SELECT id FROM ' . DB_ACCOUNTS . '.users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetch()) { line('  Email already registered. Aborted.'); return; }

    db()->prepare('INSERT INTO ' . DB_ACCOUNTS . '.users (name, email, phone, password_hash, role, active) VALUES (?,?,?,?,?,1)')
        ->execute([$name, $email, $phone !== '' ? $phone : null, password_hash($pass, PASSWORD_DEFAULT), $role]);
    line("  ✔ Created $name ($email) as $role, password: $pass");
}

function action_role(): void {
    $u = pick_user();
    if (!$u) return;
    line("  Current role: {$u['role']}");
    $to = strtoupper(prompt('  Set role to (ADMIN/CUSTOMER)'));
    if ($to !== 'ADMIN' && $to !== 'CUSTOMER') { line('  Invalid role.'); return; }
    if ($to === $u['role']) { line('  Already ' . $to . '.'); return; }
    if ((int)$u['id'] === (int)(db()->query('SELECT MIN(id) FROM ' . DB_ACCOUNTS . '.users WHERE role="ADMIN"')->fetchColumn()) && $to === 'CUSTOMER') {
        line('  This is the first admin account — protected.');
        return;
    }
    db()->prepare('UPDATE ' . DB_ACCOUNTS . '.users SET role = ? WHERE id = ?')->execute([$to, $u['id']]);
    line("  ✔ {$u['name']} is now $to");
}

function action_toggle(): void {
    $u = pick_user();
    if (!$u) return;
    $new = $u['active'] ? 0 : 1;
    if ($new === 0) {
        $st = db()->prepare('SELECT role FROM ' . DB_ACCOUNTS . '.users WHERE id = ?');
        $st->execute([$u['id']]);
        if ($st->fetchColumn() === 'ADMIN' &&
            (int)db()->query('SELECT COUNT(*) FROM ' . DB_ACCOUNTS . '.users WHERE role="ADMIN" AND active=1')->fetchColumn() <= 1) {
            line('  Cannot disable the last active admin.'); return;
        }
    }
    db()->prepare('UPDATE ' . DB_ACCOUNTS . '.users SET active = ? WHERE id = ?')->execute([$new, $u['id']]);
    line('  ✔ ' . $u['name'] . ($new ? ' enabled' : ' disabled'));
}

function action_resetpw(): void {
    $u = pick_user();
    if (!$u) return;
    $pass = prompt('  New password (empty = generate)');
    if ($pass === '') {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $pass = substr(str_shuffle($chars), 0, 8);
    }
    if (strlen($pass) < 6) { line('  Password too short.'); return; }
    db()->prepare('UPDATE ' . DB_ACCOUNTS . '.users SET password_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
    line("  ✔ Password for {$u['name']} (#{$u['id']}) is now: $pass");
}

function action_edit(): void {
    $u = pick_user();
    if (!$u) return;
    line("  Leave a field empty to keep the current value.");
    $name  = prompt('  New name', $u['name']);
    $phone = prompt('  New phone', $u['phone'] ?? '');
    db()->prepare('UPDATE ' . DB_ACCOUNTS . '.users SET name = ?, phone = ? WHERE id = ?')
        ->execute([$name, $phone !== '' ? $phone : null, $u['id']]);
    line("  ✔ Saved.");
}

function action_delete(): void {
    $u = pick_user();
    if (!$u) return;
    line("  About to DELETE {$u['name']} (#{$u['id']}, {$u['email']}) and ALL their orders.");
    $confirm = prompt('  Type DELETE to confirm');
    if ($confirm !== 'DELETE') { line('  Cancelled.'); return; }
    db()->prepare('DELETE FROM ' . DB_ACCOUNTS . '.users WHERE id = ?')->execute([$u['id']]);
    line('  ✔ Deleted.');
}

// ---------- main loop ----------
$quit = isset($argv[1]) && $argv[1] === 'menu';
while (true) {
    cls();
    line('==============================================');
    line('   Tasty Bites - User Management (XAMPP/MySQL)');
    line('==============================================');
    line('  1) Statistics');
    line('  2) List all users');
    line('  3) Search users');
    line('  4) Add user');
    line('  5) Change role (admin <-> customer)');
    line('  6) Enable / disable account');
    line('  7) Reset password');
    line('  8) Edit name / phone');
    line('  9) Delete user');
    line('  0) Exit');
    $choice = prompt('Choose');
    try {
        switch ($choice) {
            case '1': action_stats();    pause(); break;
            case '2': action_list();     pause(); break;
            case '3': action_search();   pause(); break;
            case '4': action_add();      pause(); break;
            case '5': action_role();     pause(); break;
            case '6': action_toggle();   pause(); break;
            case '7': action_resetpw();  pause(); break;
            case '8': action_edit();     pause(); break;
            case '9': action_delete();   pause(); break;
            case '0': line('Bye!'); exit(0);
            default: line('  Unknown choice.'); pause();
        }
    } catch (Throwable $ex) {
        line('  ERROR: ' . $ex->getMessage());
        pause();
    }
}
