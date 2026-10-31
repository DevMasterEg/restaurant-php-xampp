<?php
require_once __DIR__ . '/../config.php';
require_admin();

// ----- update order status -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    csrf_check();
    $id     = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, order_statuses(), true)) {
        db()->prepare('UPDATE ' . DB_ORDERS . '.orders SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash('success', "Order #$id marked as " . strtolower($status) . '.');
    } else {
        flash('error', 'Invalid status.');
    }
    redirect('index.php' . (($_POST['q'] ?? '') !== '' ? '?q=' . urlencode($_POST['q']) : ''));
}

// ----- filter: status / search by id or customer -----
$filter = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');

$sql = 'SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
        FROM ' . DB_ORDERS . '.orders o JOIN ' . DB_ACCOUNTS . '.users u ON u.id = o.user_id WHERE 1=1';
$args = [];
if ($filter !== '' && in_array($filter, order_statuses(), true)) {
    $sql .= ' AND o.status = ?';
    $args[] = $filter;
}
if ($q !== '') {
    $sql .= ' AND (o.id = ? OR u.name LIKE ? OR u.email LIKE ?)';
    $args[] = preg_match('/^\d+$/', $q) ? (int)$q : 0;
    $args[] = "%$q%";
    $args[] = "%$q%";
}
$sql .= ' ORDER BY o.id DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$orders = $stmt->fetchAll();

// items for these orders
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare('SELECT * FROM ' . DB_ORDERS . '.order_items WHERE order_id IN (' . $in . ')');
    $st->execute($ids);
    foreach ($st->fetchAll() as $row) $itemsByOrder[$row['order_id']][] = $row;
}

// quick stats
$stats = db()->query(
    'SELECT status, COUNT(*) c, COALESCE(SUM(total),0) s FROM ' . DB_ORDERS . '.orders GROUP BY status'
)->fetchAll();
$statMap = [];
foreach ($stats as $s) $statMap[$s['status']] = $s;

$PAGE_TITLE = 'Admin · Orders';
require __DIR__ . '/../includes/header.php';
?>
<h1>Order dashboard 🧾</h1>

<div class="stat-grid">
  <?php
  $today = date('Y-m-d');
  $stToday = db()->prepare('SELECT COUNT(*) c, COALESCE(SUM(total),0) s FROM ' . DB_ORDERS . '.orders WHERE DATE(created_at) = ? AND status != "CANCELLED"');
  $stToday->execute([$today]);
  $t = $stToday->fetch();
  $stPending = db()->query('SELECT COUNT(*) c FROM ' . DB_ORDERS . '.orders WHERE status = "PENDING"')->fetchColumn();
  $stCustomers = db()->query('SELECT COUNT(*) c FROM ' . DB_ACCOUNTS . '.users WHERE role = "CUSTOMER"')->fetchColumn();
  ?>
  <div class="stat"><div class="stat-num"><?= (int)$t['c'] ?></div><div class="stat-label">Orders today</div><div class="stat-sub"><?= money((float)$t['s']) ?> revenue</div></div>
  <div class="stat stat-warn"><div class="stat-num"><?= (int)$stPending ?></div><div class="stat-label">Pending</div><div class="stat-sub">need confirmation</div></div>
  <div class="stat"><div class="stat-num"><?= (int)$stCustomers ?></div><div class="stat-label">Customers</div><div class="stat-sub">registered</div></div>
  <div class="stat"><div class="stat-num"><?= (int)($statMap['READY']['c'] ?? 0) ?></div><div class="stat-label">Ready</div><div class="stat-sub"><?= money((float)($statMap['READY']['s'] ?? 0)) ?> waiting</div></div>
</div>

<form method="get" class="toolbar">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search order #, customer name or email..." class="input-search">
  <select name="status" class="select">
    <option value="">All statuses</option>
    <?php foreach (order_statuses() as $s): ?>
      <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>><?= ucfirst(strtolower($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary">Filter</button>
  <?php if ($filter || $q): ?><a class="btn btn-ghost" href="index.php">Reset</a><?php endif; ?>
</form>

<?php if (!$orders): ?>
  <div class="empty">No orders match.</div>
<?php endif; ?>

<div class="order-list">
<?php foreach ($orders as $o): ?>
  <div class="panel order-card">
    <div class="order-head">
      <div>
        <b class="order-id">#<?= (int)$o['id'] ?></b>
        <?= status_badge($o['status']) ?>
        <span class="muted">by <b><?= e($o['customer_name']) ?></b> (<?= e($o['customer_email']) ?><?= $o['customer_phone'] ? ' · ' . e($o['customer_phone']) : '' ?>)</span>
      </div>
      <div class="order-pickup">🕐 <b><?= pickup_label($o['pickup_date'], $o['pickup_time']) ?></b></div>
    </div>
    <div class="order-body">
      <table class="table table-plain">
        <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it): ?>
          <tr>
            <td><?= (int)$it['quantity'] ?> × <?= e($it['name']) ?></td>
            <td class="right nowrap"><?= money((float)$it['price'] * (int)$it['quantity']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row"><td><b>Total</b></td><td class="right"><b><?= money((float)$o['total']) ?></b></td></tr>
      </table>
      <?php if ($o['notes']): ?><p class="muted small">📝 <?= e($o['notes']) ?></p><?php endif; ?>
    </div>
    <form method="post" class="order-status-form inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="status">
      <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
      <input type="hidden" name="q" value="<?= e($q) ?>">
      <label class="small">Status:</label>
      <select name="status" class="select select-sm">
        <?php foreach (order_statuses() as $s): ?>
          <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst(strtolower($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm" type="submit">Update</button>
    </form>
  </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
