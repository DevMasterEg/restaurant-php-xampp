<?php
/** Shared page header + navigation bar. Set $PAGE_TITLE before including. */
if (!isset($PAGE_TITLE)) $PAGE_TITLE = 'Restaurant';
$__u = current_user();
$__b = base_url();
$__self = basename($_SERVER['SCRIPT_NAME'] ?? '');
$__inAdmin = $__b === '..';
$__count = cart_count();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($PAGE_TITLE) ?> &middot; Tasty Bites</title>
<link rel="stylesheet" href="<?= $__b ?>/assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="<?= $__b ?>/<?= ($__u['role'] ?? '') === 'ADMIN' ? 'admin/index.php' : 'index.php' ?>">
      <span class="brand-logo">🍽️</span> <span>Tasty&nbsp;Bites</span>
    </a>
    <input type="checkbox" id="navtoggle" class="navtoggle" aria-label="menu">
    <label for="navtoggle" class="navburger" aria-hidden="true">☰</label>
    <nav class="nav">
      <?php if ($__u && $__u['role'] === 'ADMIN'): ?>
        <a href="<?= $__b ?>/admin/index.php" class="<?= ($__inAdmin && $__self === 'index.php') ? 'active' : '' ?>">Orders</a>
        <a href="<?= $__b ?>/admin/menu.php"   class="<?= ($__inAdmin && $__self === 'menu.php')   ? 'active' : '' ?>">Menu</a>
        <a href="<?= $__b ?>/admin/users.php"  class="<?= ($__inAdmin && $__self === 'users.php')  ? 'active' : '' ?>">Users</a>
        <a href="<?= $__b ?>/index.php">Storefront</a>
      <?php else: ?>
        <a href="<?= $__b ?>/index.php"  class="<?= (!$__inAdmin && $__self === 'index.php')  ? 'active' : '' ?>">Menu</a>
        <a href="<?= $__b ?>/orders.php" class="<?= (!$__inAdmin && $__self === 'orders.php') ? 'active' : '' ?>">My Orders</a>
      <?php endif; ?>
      <?php if ($__u): ?>
        <a href="<?= $__b ?>/cart.php" class="cart-link <?= (!$__inAdmin && $__self === 'cart.php') ? 'active' : '' ?>">
          Cart<?php if ($__count): ?> <span class="cart-badge"><?= $__count ?></span><?php endif; ?>
        </a>
        <div class="nav-user">
          <span class="nav-user-name">👤 <?= e($__u['name']) ?></span>
          <a class="btn btn-ghost btn-sm" href="<?= $__b ?>/logout.php">Logout</a>
        </div>
      <?php else: ?>
        <a href="<?= $__b ?>/login.php"    class="<?= (!$__inAdmin && $__self === 'login.php')    ? 'active' : '' ?>">Login</a>
        <a href="<?= $__b ?>/register.php" class="btn btn-primary btn-sm nav-cta <?= (!$__inAdmin && $__self === 'register.php') ? 'active' : '' ?>">Sign up</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap page">
<?php foreach (flash_take() as $__f): ?>
  <div class="flash flash-<?= e($__f['type']) ?>"><?= e($__f['msg']) ?></div>
<?php endforeach; ?>
