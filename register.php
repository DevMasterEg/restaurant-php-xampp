<?php
require_once __DIR__ . '/config.php';

if (current_user()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = db()->prepare('SELECT id FROM ' . DB_ACCOUNTS . '.users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO ' . DB_ACCOUNTS . '.users (name, email, phone, password_hash, role, active)
                 VALUES (?, ?, ?, ?, "CUSTOMER", 1)'
            );
            $stmt->execute([$name, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT)]);
            $newId = (int)db()->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newId;
            flash('success', 'Account created - welcome, ' . $name . '!');
            redirect('index.php');
        }
    }
}

$PAGE_TITLE = 'Sign up';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <h1>Create account</h1>
  <p class="muted">Sign up as a customer and order with pickup time selection.</p>

  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Full name
      <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required autofocus placeholder="Ahmed Hassan">
    </label>
    <label>Email
      <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required placeholder="you@example.com">
    </label>
    <label>Phone <span class="muted">(optional)</span>
      <input type="tel" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" placeholder="010 1234 5678">
    </label>
    <div class="grid-2">
      <label>Password
        <input type="password" name="password" required placeholder="min 6 characters">
      </label>
      <label>Confirm
        <input type="password" name="confirm" required placeholder="repeat password">
      </label>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
  </form>

  <p class="auth-alt">Already registered? <a href="login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
