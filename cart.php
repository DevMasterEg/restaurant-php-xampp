<?php
require_once __DIR__ . '/config.php';
require_login();

// ----- actions: update qty / remove / clear -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $cart = cart();

    if ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $id => $q) {
            $id = (int)$id; $q = max(0, min(20, (int)$q));
            if ($q === 0) unset($cart[$id]); else $cart[$id] = $q;
        }
        $_SESSION['cart'] = $cart;
        flash('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        unset($cart[(int)($_POST['item_id'] ?? 0)]);
        $_SESSION['cart'] = $cart;
        flash('success', 'Item removed.');
    } elseif ($action === 'clear') {
        cart_clear();
        flash('success', 'Cart cleared.');
    }
    redirect('cart.php');
}

// ----- load cart items from DB -----
$cart = cart();
$lines = []; $total = 0.0;
if ($cart) {
    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare('SELECT * FROM ' . DB_MENU . '.menu_items WHERE id IN (' . $in . ')');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $qty  = (int)$cart[$row['id']];
        $line = $qty * (float)$row['price'];
        $total += $line;
        $lines[] = ['item' => $row, 'qty' => $qty, 'line' => $line];
    }
}

$PAGE_TITLE = 'Cart';
require __DIR__ . '/includes/header.php';
?>
<h1>Your cart 🛒</h1>

<?php if (!$lines): ?>
  <div class="empty">
    <p>Your cart is empty.</p>
    <a class="btn btn-primary" href="index.php">Browse the menu</a>
  </div>
<?php else: ?>
  <form method="post" class="cart-table-wrap">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <table class="table cart-table">
      <thead>
        <tr><th>Dish</th><th>Price</th><th>Qty</th><th class="right">Subtotal</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td class="cart-dish"><span class="menu-emoji-sm"><?= e($l['item']['emoji']) ?></span> <b><?= e($l['item']['name']) ?></b></td>
          <td><?= money((float)$l['item']['price']) ?></td>
          <td><input class="input-qty input-qty-box" type="number" name="qty[<?= (int)$l['item']['id'] ?>]" value="<?= (int)$l['qty'] ?>" min="0" max="20"></td>
          <td class="right"><b><?= money($l['line']) ?></b></td>
          <td class="right">
            <button class="btn btn-ghost btn-sm" type="submit" formaction="cart.php" name="action" value="remove"
                    onclick="this.form.item_id.value=<?= (int)$l['item']['id'] ?>">✕</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <input type="hidden" name="item_id" value="0">
    <div class="cart-summary">
      <div class="cart-total">Total <b><?= money($total) ?></b></div>
      <div class="cart-actions">
        <button class="btn btn-ghost" type="submit" name="action" value="update">Update quantities</button>
        <button class="btn btn-ghost" type="submit" name="action" value="clear"
                onclick="return confirm('Clear the whole cart?')">Clear cart</button>
        <a class="btn btn-primary" href="checkout.php">Checkout →</a>
      </div>
    </div>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
