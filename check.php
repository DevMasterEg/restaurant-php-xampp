<?php
/**
 * Self-diagnosis page. Open http://localhost/YOUR-FOLDER-NAME/check.php
 * It shows exactly what is OK and what is missing, in plain language.
 */
require_once __DIR__ . '/config.php';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tasty Bites - System Check</title>
</head>
<body style="font-family:Segoe UI,Arial,sans-serif;background:#fafaf9;color:#292524;margin:0;padding:30px 16px">
<div style="max-width:700px;margin:0 auto">
  <h1 style="font-size:24px">🍽️ Tasty Bites — System Check</h1>
  <p style="color:#57534e">Everything below should be green. Fix the first red row, then reload this page.</p>

  <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,.08);font-size:14px">
    <?php
    function row(bool $ok, string $label, string $detail): void {
        $bg  = $ok ? '#f0fdf4' : '#fef2f2';
        $dot = $ok ? '✅' : '❌';
        echo '<tr style="background:' . $bg . ';border-bottom:1px solid #eee">'
           . '<td style="padding:10px 14px;width:34px;font-size:16px">' . $dot . '</td>'
           . '<td style="padding:10px 6px;font-weight:600">' . htmlspecialchars($label) . '</td>'
           . '<td style="padding:10px 14px;color:#57534e">' . htmlspecialchars($detail) . '</td></tr>';
    }

    $verOk  = PHP_VERSION_ID >= 70300;
    $ext    = ['pdo' => extension_loaded('pdo'), 'pdo_mysql' => extension_loaded('pdo_mysql')];
    $sp     = ini_get('session.save_path') ?: sys_get_temp_dir();
    $spOk   = is_dir($sp) && is_writable($sp);

    row(true, 'PHP version', PHP_VERSION . ' at ' . PHP_BINARY);
    row($ext['pdo'], 'PDO extension', $ext['pdo'] ? 'loaded' : 'MISSING - enable extension=pdo in php.ini');
    row($ext['pdo_mysql'], 'PDO MySQL driver', $ext['pdo_mysql'] ? 'loaded' : 'MISSING - enable extension=pdo_mysql in php.ini');
    row($spOk, 'Sessions folder writable', $sp);

    $dbOk = false; $dbErr = '';
    $users = 0; $items = 0; $orders = 0;
    $dbs = [
        DB_ACCOUNTS => ['users'],
        DB_MENU     => ['menu_items'],
        DB_ORDERS   => ['orders', 'order_items'],
    ];
    try {
        $pdo = db();
        $dbOk = true;
        row(true, 'MySQL connection', 'connected to ' . DB_HOST . ':' . DB_PORT . ' as ' . DB_USER);

        // which of the 3 databases exist?
        $existing = $pdo->query('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA')->fetchAll(PDO::FETCH_COLUMN);
        $missing = [];
        foreach (array_keys($dbs) as $dbname) {
            if (!in_array($dbname, $existing, true)) $missing[] = $dbname;
        }
        row(!$missing, '3 project databases exist',
            $missing ? 'MISSING: ' . implode(', ', $missing) . ' - run setup.bat to create them'
                     : implode(', ', array_keys($dbs)));

        if (!$missing) {
            foreach ($dbs as $dbname => $tables) {
                $found = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . $dbname . "'")->fetchColumn();
                row((int)$found === count($tables), "Database \"$dbname\" tables", $found . '/' . count($tables) . ' table(s): ' . implode(', ', $tables));
            }
            $users  = (int)$pdo->query('SELECT COUNT(*) FROM ' . DB_ACCOUNTS . '.users')->fetchColumn();
            $items  = (int)$pdo->query('SELECT COUNT(*) FROM ' . DB_MENU . '.menu_items')->fetchColumn();
            $orders = (int)$pdo->query('SELECT COUNT(*) FROM ' . DB_ORDERS . '.orders')->fetchColumn();
        }
    } catch (Throwable $ex) {
        row(false, 'MySQL connection', 'FAILED: ' . $ex->getMessage());
    }

    if ($dbOk && !$missing) {
        row($users >= 1, 'Users in restaurant_accounts', $users . ' user(s)' . ($users ? '' : ' - run setup.bat to import demo logins'));
        row($items >= 1, 'Menu items in restaurant_menu', $items . ' dish(es)');
        row(true, 'Orders in restaurant_orders', $orders . ' order(s)');
    }

    $folder = basename(__DIR__);
    row(true, 'Project folder', __DIR__ . '  (URL: http://localhost/' . $folder . '/)');
    ?>
  </table>

  <?php if ($dbOk && $users >= 1 && $items >= 1): ?>
    <div style="background:#dcfce7;border-radius:12px;padding:16px 18px;margin-top:18px;font-size:15px">
      ✅ <b>Everything looks good!</b> Open
      <a href="index.php" style="color:#b45309;font-weight:700">http://localhost/<?php echo htmlspecialchars($folder); ?>/</a><br>
      Admin: admin@restaurant.com / admin123 &nbsp;·&nbsp; Customer: customer@restaurant.com / customer123
    </div>
  <?php else: ?>
    <div style="background:#fee2e2;border-radius:12px;padding:16px 18px;margin-top:18px;font-size:15px;line-height:1.7">
      ❌ <b>Not ready yet.</b> Most common fixes:<br>
      1. XAMPP Control Panel: start <b>MySQL</b> and <b>Apache</b>.<br>
      2. Double-click <b>setup.bat</b> in the project folder (creates all 3 databases).<br>
      3. If you set a MySQL root password, put it in <b>config.php</b> (DB_PASS).
    </div>
  <?php endif; ?>
</div>
</body>
</html>
