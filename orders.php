<?php
require_once __DIR__ . '/config.php';
require_login();
$user = current_user();

// ----- customer cancels own pending order -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    csrf_check();
    $id = (int)($_POST['order_id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM ' . DB_ORDERS . '.orders WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $user['id']]);
    $order = $stmt->fetch();
    if ($order && $order['status'] === 'PENDING') {
        db()->prepare('UPDATE ' . DB_ORDERS . '.orders SET status = "CANCELLED" WHERE id = ?')->execute([$id]);
        flash('success', "Order #$id cancelled.");
    } else {
        flash('error', 'Only pending orders can be cancelled.');
    }
    redirect('orders.php');
}

// ----- list my orders + items -----
$stmt = db()->prepare('SELECT * FROM ' . DB_ORDERS . '.orders WHERE user_id = ? ORDER BY id DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare('SELECT * FROM ' . DB_ORDERS . '.order_items WHERE order_id IN (' . $in . ')');
    $st->execute($ids);
    foreach ($st->fetchAll() as $row) {
        $itemsByOrder[$row['order_id']][] = $row;
    }
}

$PAGE_TITLE = 'My Orders';
require __DIR__ . '/includes/header.php';
?>
<h1>My orders 📋</h1>
<p class="muted">Track every order and its pickup time.</p>

<?php if (!$orders): ?>
  <div class="empty">
    <p>You haven't ordered anything yet.</p>
    <a class="btn btn-primary" href="index.php">Browse the menu</a>
  </div>
<?php endif; ?>

<div class="order-list">
<?php foreach ($orders as $o): ?>
  <div class="panel order-card">
    <div class="order-head">
      <div>
        <b class="order-id">Order #<?= (int)$o['id'] ?></b>
        <?= status_badge($o['status']) ?>
      </div>
      <div class="order-pickup">🕐 Pickup: <b><?= pickup_label($o['pickup_date'], $o['pickup_time']) ?></b></div>
    </div>
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
    <div class="order-foot">
      <span class="muted small">Placed <?= e(date('M j, Y g:i A', strtotime($o['created_at']))) ?></span>
      <?php if ($o['status'] === 'PENDING'): ?>
        <form method="post" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cancel">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit"
                  onclick="return confirm('Cancel order #<?= (int)$o['id'] ?>?')">Cancel order</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
