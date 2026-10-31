<?php
require_once __DIR__ . '/config.php';

if (current_user()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM ' . DB_ACCOUNTS . '.users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Wrong email or password.';
        } elseif (!(int)$user['active']) {
            $error = 'This account has been disabled. Contact the administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            flash('success', 'Welcome back, ' . $user['name'] . '!');
            $next = $_GET['next'] ?? '';
            if ($user['role'] === 'ADMIN') {
                redirect('admin/index.php');
            }
            redirect(preg_match('/^[a-z_]+\.php$/', $next) ? $next : 'index.php');
        }
    }
}

$PAGE_TITLE = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Log in</h1>
  <p class="muted">Welcome back! Log in to order and pick your pickup time.</p>

  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Email
      <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus placeholder="you@example.com">
    </label>
    <label>Password
      <input type="password" name="password" required placeholder="••••••••">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
  </form>

  <p class="auth-alt">No account yet? <a href="register.php">Create one</a></p>


</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
