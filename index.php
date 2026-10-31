<?php
require_once __DIR__ . '/config.php';

$user = current_user();

// ----- add to cart -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    $id = (int)($_POST['item_id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    $stmt = db()->prepare('SELECT * FROM ' . DB_MENU . '.menu_items WHERE id = ? AND available = 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    if ($item) {
        $cart = cart();
        $cart[$id] = ($cart[$id] ?? 0) + $qty;
        $_SESSION['cart'] = $cart;
        flash('success', $item['name'] . ' added to cart.');
    } else {
        flash('error', 'That item is not available.');
    }
    redirect('cart.php');
}

// ----- category filter -----
$cat = trim($_GET['cat'] ?? '');
$categories = db()->query('SELECT DISTINCT category FROM ' . DB_MENU . '.menu_items ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

if ($cat !== '' && in_array($cat, $categories, true)) {
    $stmt = db()->prepare('SELECT * FROM ' . DB_MENU . '.menu_items WHERE available = 1 AND category = ? ORDER BY name');
    $stmt->execute([$cat]);
} else {
    $cat = '';
    $stmt = db()->query('SELECT * FROM ' . DB_MENU . '.menu_items WHERE available = 1 ORDER BY category, name');
}
$items = $stmt->fetchAll();

$PAGE_TITLE = 'Menu';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <h1>Order online &amp; pick it up 🍽️</h1>
  <p>Fresh food, ready when you are. Choose your dishes, pick a <b>pickup time</b> that suits you, and skip the wait.</p>
  <?php if (!$user): ?>
    <p><a class="btn btn-primary" href="register.php">Create an account</a> <a class="btn btn-ghost" href="login.php">Log in</a></p>
  <?php else: ?>
    <p><a class="btn btn-primary" href="cart.php">Go to cart<?= cart_count() ? ' (' . cart_count() . ')' : '' ?></a></p>
  <?php endif; ?>
</section>

<nav class="chips">
  <a class="chip <?= $cat === '' ? 'chip-active' : '' ?>" href="index.php">All</a>
  <?php foreach ($categories as $c): ?>
    <a class="chip <?= $cat === $c ? 'chip-active' : '' ?>" href="index.php?cat=<?= urlencode($c) ?>"><?= e($c) ?></a>
  <?php endforeach; ?>
</nav>

<?php if (!$items): ?>
  <div class="empty">No dishes in this category yet.</div>
<?php endif; ?>

<div class="menu-grid">
  <?php foreach ($items as $it): ?>
    <form method="post" class="card menu-card">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
      <div class="menu-emoji"><?= e($it['emoji']) ?></div>
      <div class="menu-cat"><?= e($it['category']) ?></div>
      <h3 class="menu-name"><?= e($it['name']) ?></h3>
      <p class="menu-desc"><?= e($it['description']) ?></p>
      <div class="menu-foot">
        <span class="price"><?= money((float)$it['price']) ?></span>
        <div class="qty-inline">
          <button type="button" class="btn btn-ghost btn-sm js-qty" data-dir="-1" aria-label="less">−</button>
          <input type="number" name="qty" value="1" min="1" max="20" class="input-qty">
          <button type="button" class="btn btn-ghost btn-sm js-qty" data-dir="1" aria-label="more">+</button>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Add</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
