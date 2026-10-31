<?php
require_once __DIR__ . '/../config.php';
require_admin();
$me = current_user();

// ----- user management actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    // load target (if any)
    $target = null;
    if ($id) {
        $st = db()->prepare('SELECT * FROM ' . DB_ACCOUNTS . '.users WHERE id = ?');
        $st->execute([$id]);
        $target = $st->fetch();
    }
    $isSelf = $target && (int)$target['id'] === (int)$me['id'];

    switch ($action) {
        case 'add':
            $name  = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $role  = ($_POST['role'] ?? 'CUSTOMER') === 'ADMIN' ? 'ADMIN' : 'CUSTOMER';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 6) {
                flash('error', 'Valid name, email and a 6+ char password are required.');
            } else {
                $st = db()->prepare('SELECT id FROM ' . DB_ACCOUNTS . '.users WHERE email = ?');
                $st->execute([$email]);
                if ($st->fetch()) {
                    flash('error', 'Email already registered.');
                } else {
                    db()->prepare('INSERT INTO ' . DB_ACCOUNTS . '.users (name, email, phone, password_hash, role, active) VALUES (?,?,?,?,?,1)')
                        ->execute([$name, $email, $phone !== '' ? $phone : null, password_hash($pass, PASSWORD_DEFAULT), $role]);
                    flash('success', "$name added as $role.");
                }
            }
            break;

        case 'role':
            $role = ($_POST['role'] ?? 'CUSTOMER') === 'ADMIN' ? 'ADMIN' : 'CUSTOMER';
            if (!$target) {
                flash('error', 'User not found.');
            } elseif ($isSelf) {
                flash('error', 'You cannot change your own role.');
            } else {
                db()->prepare('UPDATE ' . DB_ACCOUNTS . '.users SET role = ? WHERE id = ?')->execute([$role, $id]);
                flash('success', $target['name'] . ' is now ' . $role . '.');
            }
            break;

        case 'toggle':
            if (!$target) {
                flash('error', 'User not found.');
            } elseif ($isSelf) {
                flash('error', 'You cannot disable your own account.');
            } else {
                db()->prepare('UPDATE ' . DB_ACCOUNTS . '.users SET active = 1 - active WHERE id = ?')->execute([$id]);
                flash('success', $target['name'] . ((int)$target['active'] ? ' disabled.' : ' enabled.'));
            }
            break;

        case 'resetpw':
            if (!$target) {
                flash('error', 'User not found.');
            } else {
                $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
                $temp  = substr(str_shuffle($chars), 0, 8);
                db()->prepare('UPDATE ' . DB_ACCOUNTS . '.users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
                flash('success', "Temporary password for {$target['name']}: $temp  (tell the user to change it)");
            }
            break;

        case 'delete':
            if (!$target) {
                flash('error', 'User not found.');
            } elseif ($isSelf) {
                flash('error', 'You cannot delete your own account.');
            } else {
                db()->prepare('DELETE FROM ' . DB_ACCOUNTS . '.users WHERE id = ?')->execute([$id]);
                flash('success', $target['name'] . ' and their orders deleted.');
            }
            break;
    }
    redirect('users.php');
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = "%$q%";
    $stmt = db()->prepare('SELECT * FROM ' . DB_ACCOUNTS . '.users WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY role, name');
    $stmt->execute([$like, $like, $like]);
    $users = $stmt->fetchAll();
} else {
    $users = db()->query('SELECT * FROM ' . DB_ACCOUNTS . '.users ORDER BY role, name')->fetchAll();
}

// order counts per user
$orderCounts = [];
foreach (db()->query('SELECT user_id, COUNT(*) c, COALESCE(SUM(total),0) s FROM ' . DB_ORDERS . '.orders GROUP BY user_id')->fetchAll() as $r) {
    $orderCounts[$r['user_id']] = $r;
}

$PAGE_TITLE = 'Admin · Users';
require __DIR__ . '/../includes/header.php';
?>
<h1>User manager 👥</h1>
<p class="muted">Add users, promote to admin, disable accounts, reset passwords or delete users. You can also do all of this offline with <code>manage-users.bat</code>.</p>

<div class="admin-grid">
  <div class="panel">
    <h2>Add user</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <label>Full name <input type="text" name="name" required placeholder="Sara Mohamed"></label>
      <label>Email <input type="email" name="email" required placeholder="sara@example.com"></label>
      <label>Phone <span class="muted">(optional)</span> <input type="tel" name="phone" placeholder="010 1234 5678"></label>
      <div class="grid-2">
        <label>Password <input type="text" name="password" required minlength="6" placeholder="min 6 chars"></label>
        <label>Role
          <select name="role" class="select">
            <option value="CUSTOMER">Customer</option>
            <option value="ADMIN">Admin</option>
          </select>
        </label>
      </div>
      <button class="btn btn-primary" type="submit">Add user</button>
    </form>
  </div>

  <div class="panel">
    <h2>All users (<?= count($users) ?>)</h2>
    <form method="get" class="toolbar">
      <input type="search" name="q" value="<?= e($q) ?>" class="input-search" placeholder="Search name, email, phone...">
      <button class="btn btn-primary">Search</button>
      <?php if ($q !== ''): ?><a class="btn btn-ghost" href="users.php">Reset</a><?php endif; ?>
    </form>

    <table class="table users-table">
      <thead>
        <tr><th>User</th><th>Role</th><th>Status</th><th>Orders</th><th class="right">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?>
        <tr class="<?= (int)$u['active'] ? '' : 'row-disabled' ?>">
          <td>
            <b><?= e($u['name']) ?></b> <?= $self ? '<span class="badge badge-blue">you</span>' : '' ?><br>
            <span class="muted small"><?= e($u['email']) ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?></span><br>
            <span class="muted small">joined <?= e(date('M j, Y', strtotime($u['created_at']))) ?></span>
          </td>
          <td><?= $u['role'] === 'ADMIN' ? '<span class="badge badge-purple">ADMIN</span>' : '<span class="badge badge-gray">Customer</span>' ?></td>
          <td><?= (int)$u['active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Disabled</span>' ?></td>
          <td class="nowrap"><?= (int)($orderCounts[$u['id']]['c'] ?? 0) ?><br><span class="muted small"><?= money((float)($orderCounts[$u['id']]['s'] ?? 0)) ?></span></td>
          <td class="right nowrap">
            <?php if (!$self): ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="role" value="<?= $u['role'] === 'ADMIN' ? 'CUSTOMER' : 'ADMIN' ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= $u['role'] === 'ADMIN' ? '→ Customer' : '→ Admin' ?></button>
              </form>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= (int)$u['active'] ? 'Disable' : 'Enable' ?></button>
              </form>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resetpw">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"
                        onclick="return confirm('Reset password for <?= e($u['name']) ?>?')">Reset pw</button>
              </form>
              <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"
                        onclick="return confirm('Delete <?= e($u['name']) ?> and all their orders? This cannot be undone.')">Delete</button>
              </form>
            <?php else: ?>
              <span class="muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
