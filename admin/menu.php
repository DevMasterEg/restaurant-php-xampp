<?php
require_once __DIR__ . '/../config.php';
require_admin();

// ----- create / update / toggle / delete menu item -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        $category    = trim($_POST['category'] ?? 'Mains');
        $emoji       = trim($_POST['emoji'] ?? '🍽️');
        $available   = isset($_POST['available']) ? 1 : 0;

        if ($name === '' || $price <= 0) {
            flash('error', 'Name and a positive price are required.');
        } elseif ($id) {
            db()->prepare('UPDATE ' . DB_MENU . '.menu_items SET name=?, description=?, price=?, category=?, emoji=?, available=? WHERE id=?')
                ->execute([$name, $description, $price, $category, $emoji, $available, $id]);
            flash('success', "$name updated.");
        } else {
            db()->prepare('INSERT INTO ' . DB_MENU . '.menu_items (name, description, price, category, emoji, available) VALUES (?,?,?,?,?,?)')
                ->execute([$name, $description, $price, $category, $emoji, $available]);
            flash('success', "$name added to the menu.");
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE ' . DB_MENU . '.menu_items SET available = 1 - available WHERE id = ?')->execute([$id]);
        flash('success', 'Availability toggled.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = db()->prepare('SELECT name FROM ' . DB_MENU . '.menu_items WHERE id = ?');
        $st->execute([$id]);
        $name = $st->fetchColumn();
        db()->prepare('DELETE FROM ' . DB_MENU . '.menu_items WHERE id = ?')->execute([$id]);
        flash('success', ($name ?: 'Item') . ' deleted.');
    }
    redirect('menu.php');
}

// ----- editing existing item? -----
$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare('SELECT * FROM ' . DB_MENU . '.menu_items WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$items = db()->query('SELECT * FROM ' . DB_MENU . '.menu_items ORDER BY category, name')->fetchAll();
$categories = db()->query('SELECT DISTINCT category FROM ' . DB_MENU . '.menu_items ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$PAGE_TITLE = 'Admin · Menu';
require __DIR__ . '/../includes/header.php';
?>
<h1>Menu manager 🍳</h1>

<div class="admin-grid">
  <div class="panel">
    <h2><?= $edit ? 'Edit: ' . e($edit['name']) : 'Add new item' ?></h2>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label>Name
        <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Chicken Burger">
      </label>
      <label>Description
        <textarea name="description" rows="2" placeholder="Short tasty description..."><?= e($edit['description'] ?? '') ?></textarea>
      </label>
      <div class="grid-3">
        <label>Price ($)
          <input type="number" step="0.01" min="0.01" name="price" required value="<?= e($edit['price'] ?? '') ?>" placeholder="8.50">
        </label>
        <label>Category
          <input type="text" name="category" list="catlist" value="<?= e($edit['category'] ?? 'Mains') ?>">
          <datalist id="catlist">
            <?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?>
          </datalist>
        </label>
        <label>Emoji
          <input type="text" name="emoji" maxlength="8" value="<?= e($edit['emoji'] ?? '🍽️') ?>">
        </label>
      </div>
      <label class="check-row">
        <input type="checkbox" name="available" <?= (!$edit || (int)$edit['available']) ? 'checked' : '' ?>>
        Available for ordering
      </label>
      <div class="btn-row">
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add item' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="menu.php">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="panel">
    <h2>Menu items (<?= count($items) ?>)</h2>
    <table class="table">
      <thead><tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><b><?= e($it['emoji']) ?> <?= e($it['name']) ?></b></td>
          <td><?= e($it['category']) ?></td>
          <td class="nowrap"><?= money((float)$it['price']) ?></td>
          <td><?= (int)$it['available']
                ? '<span class="badge badge-green">Available</span>'
                : '<span class="badge badge-red">Hidden</span>' ?></td>
          <td class="right nowrap">
            <a class="btn btn-ghost btn-sm" href="menu.php?edit=<?= (int)$it['id'] ?>">Edit</a>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= (int)$it['available'] ? 'Hide' : 'Show' ?></button>
            </form>
            <form method="post" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit"
                      onclick="return confirm('Delete <?= e($it['name']) ?> permanently?')">Del</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
