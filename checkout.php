<?php
require_once __DIR__ . '/config.php';
require_login();

$cart = cart();
if (!$cart) redirect('cart.php');

// load cart lines (server-side pricing - never trust the browser)
$ids = array_map('intval', array_keys($cart));
$in  = implode(',', array_fill(0, count($ids), '?'));
$stmt = db()->prepare('SELECT * FROM ' . DB_MENU . '.menu_items WHERE id IN (' . $in . ') AND available = 1');
$stmt->execute($ids);
$rows = $stmt->fetchAll();
if (!$rows) { cart_clear(); flash('error', 'Your cart items are no longer available.'); redirect('index.php'); }

$total = 0.0; $lines = [];
foreach ($rows as $row) {
    $qty = (int)$cart[$row['id']];
    $line = $qty * (float)$row['price'];
    $total += $line;
    $lines[] = ['item' => $row, 'qty' => $qty, 'line' => $line];
}

// ----- selected date (default: today, but today can jump to tomorrow if kitchen nearly closed) -----
$dates = pickup_dates();
$selDate = $_GET['date'] ?? '';
if (!isset($dates[$selDate])) { reset($dates); $selDate = (string)key($dates); }

// ----- default suggested time + limits for the selected date -----
$earliest = earliest_pickup_minutes($selDate);              // null = day's window passed
$defaultTime = '';
if ($earliest !== null) {
    // today: suggest the earliest possible (ASAP); other days: suggest lunch 12:30
    $defaultTime = hhmm($selDate === date('Y-m-d') ? $earliest : max(750, $earliest)); // 750 = 12:30
}
$minTime = $earliest !== null ? hhmm($earliest) : KITCHEN_OPEN;
$isToday = ($selDate === date('Y-m-d'));

// ----- place order -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $date  = trim($_POST['pickup_date'] ?? '');
    $time  = trim($_POST['pickup_time'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    [$ok, $err] = pickup_validate($date, $time);
    if (!$ok) {
        flash('error', $err);
        redirect('checkout.php?date=' . urlencode($date ?: $selDate));
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO ' . DB_ORDERS . '.orders (user_id, status, pickup_date, pickup_time, notes, total)
                             VALUES (?, "PENDING", ?, ?, ?, ?)');
        $st->execute([current_user()['id'], $date, $time . ':00', $notes !== '' ? $notes : null, $total]);
        $orderId = (int)$pdo->lastInsertId();

        $stItem = $pdo->prepare('INSERT INTO ' . DB_ORDERS . '.order_items (order_id, menu_item_id, name, price, quantity)
                                 VALUES (?, ?, ?, ?, ?)');
        foreach ($lines as $l) {
            $stItem->execute([$orderId, $l['item']['id'], $l['item']['name'], $l['item']['price'], $l['qty']]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        error_log('Order failed: ' . $ex->getMessage());
        flash('error', 'Could not place the order. Please try again.');
        redirect('checkout.php?date=' . urlencode($selDate));
    }

    cart_clear();
    flash('success', "Order #$orderId placed! Pickup " . date('D, M j', strtotime($date)) . ' at ' . date('g:i A', strtotime($time)) . '.');
    redirect('orders.php');
}

$PAGE_TITLE = 'Checkout';
require __DIR__ . '/includes/header.php';
?>
<h1>Checkout 🕐</h1>
<p class="muted">Review your order and choose exactly when you want to pick it up — down to the minute.</p>

<div class="checkout-grid">
  <div>
    <div class="panel">
      <h2>1. Pickup date</h2>
      <div class="date-chips">
        <?php foreach ($dates as $d => $label): ?>
          <a class="date-chip <?= $d === $selDate ? 'date-chip-active' : '' ?>"
             href="checkout.php?date=<?= e($d) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <h2>2. Pickup time — hour &amp; minute</h2>
      <p class="muted small">Kitchen is open 11:00 – 22:30. Pick any minute you like, or use a quick time below.</p>
      <form method="post" id="checkout-form" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="pickup_date" value="<?= e($selDate) ?>">

        <div class="quick-times">
          <button type="button" class="qt-chip" data-time="<?= e($earliest !== null ? hhmm($earliest) : '') ?>">⚡ Soonest<?= $isToday && $earliest !== null ? ' (' . e(date('g:i A', strtotime(hhmm($earliest)))) . ')' : '' ?></button>
          <button type="button" class="qt-chip" data-time="12:30">12:30 PM</button>
          <button type="button" class="qt-chip" data-time="18:00">6:00 PM</button>
          <button type="button" class="qt-chip" data-time="20:00">8:00 PM</button>
          <button type="button" class="qt-chip" data-time="21:30">9:30 PM</button>
        </div>

        <label class="time-field">
          <span>Pickup time (any minute):</span>
          <input type="time" id="pickup-time" name="pickup_time" value="<?= e($defaultTime) ?>"
                 min="<?= e($minTime) ?>" max="<?= e(KITCHEN_CLOSE) ?>" required>
        </label>
        <div id="time-hint" class="time-hint muted small"></div>

        <h2>3. Notes <span class="muted small">(optional)</span></h2>
        <textarea name="notes" rows="2" placeholder="Allergies, extra sauce, no onions..."></textarea>

        <button class="btn btn-primary btn-block" type="submit">Place order — <?= money($total) ?></button>
        <a class="btn btn-ghost btn-block" href="cart.php">← Back to cart</a>
      </form>
    </div>
  </div>

  <div>
    <div class="panel panel-sticky">
      <h2>Order summary</h2>
      <table class="table table-plain">
        <?php foreach ($lines as $l): ?>
          <tr>
            <td><?= (int)$l['qty'] ?> × <?= e($l['item']['emoji']) ?> <?= e($l['item']['name']) ?></td>
            <td class="right nowrap"><?= money($l['line']) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td><b>Total</b></td><td class="right"><b><?= money($total) ?></b></td>
        </tr>
      </table>
      <div class="slot-preview muted small">
        📍 Pickup on <b id="prev-date"><?= e($dates[$selDate]) ?></b> at <b id="prev-time"><?= e($defaultTime !== '' ? date('g:i A', strtotime($defaultTime)) : '—') ?></b>
      </div>
    </div>
  </div>
</div>

<script>
/* checkout minute-level pickup config for app.js */
window.TB_PICKUP = {
  open:      '<?= e(KITCHEN_OPEN) ?>',
  close:     '<?= e(KITCHEN_CLOSE) ?>',
  min:       '<?= e($minTime) ?>',
  isToday:   <?= $isToday ? 'true' : 'false' ?>
};
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
