<?php
require __DIR__ . '/_init.php';

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
if (current_user()) {
    redirect(safe_next($next));
}
$error = '';
if (is_post()) {
    csrf_check();
    if (login_throttled()) {
        $error = 'অনেকবার ভুল চেষ্টা হয়েছে। ১০ মিনিট পর আবার চেষ্টা করুন।';
    } else {
        $u = row('SELECT id, password_hash, role FROM users WHERE email = ?', [mb_strtolower(input('email'))]);
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            login_user((int)$u['id']);
            unset($_SESSION['login_fail']);
            redirect(safe_next($next, $u['role'] === 'admin' ? 'admin/' : 'account.php'));
        }
        login_failed();
        $error = 'ইমেইল বা পাসওয়ার্ড ভুল।';
    }
}
$title = 'লগইন';
require APP_DIR . '/views/header.php';
?>
<form method="post" class="card form narrow auth">
  <h1>লগইন</h1>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <label>ইমেইল <input type="email" name="email" value="<?= e(input('email')) ?>" required autofocus></label>
  <label>পাসওয়ার্ড <input type="password" name="password" required></label>
  <button class="btn btn-block">লগইন</button>
  <p class="small">অ্যাকাউন্ট নেই? <a href="<?= url('register.php' . ($next ? '?next=' . urlencode($next) : '')) ?>">রেজিস্টার করুন</a></p>
</form>
<?php require APP_DIR . '/views/footer.php';
