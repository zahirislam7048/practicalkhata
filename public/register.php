<?php
require __DIR__ . '/_init.php';

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
if (current_user()) {
    redirect(safe_next($next));
}
$errors = [];
if (is_post()) {
    csrf_check();
    $name = input('name');
    $email = mb_strtolower(input('email'));
    $phone = preg_replace('/\D+/', '', input('phone'));
    $pass = (string)($_POST['password'] ?? '');

    if ($name === '') $errors[] = 'নাম লিখুন।';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'সঠিক ইমেইল লিখুন।';
    if ($phone !== '' && !preg_match('/^01[3-9]\d{8}$/', $phone)) $errors[] = 'সঠিক মোবাইল নাম্বার লিখুন।';
    if (mb_strlen($pass) < 6) $errors[] = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।';
    if (!$errors && val('SELECT id FROM users WHERE email = ?', [$email])) {
        $errors[] = 'এই ইমেইল দিয়ে আগেই অ্যাকাউন্ট আছে। লগইন করুন।';
    }
    if (!$errors) {
        q('INSERT INTO users (name, email, phone, password_hash) VALUES (?,?,?,?)',
          [$name, $email, $phone ?: null, password_hash($pass, PASSWORD_DEFAULT)]);
        login_user((int)db()->lastInsertId());
        flash('success', 'অ্যাকাউন্ট তৈরি হয়েছে!');
        redirect(safe_next($next, cart_count() ? 'checkout.php' : 'account.php'));
    }
}
$title = 'রেজিস্টার';
require APP_DIR . '/views/header.php';
?>
<form method="post" class="card form narrow auth">
  <h1>নতুন অ্যাকাউন্ট</h1>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <label>নাম <input name="name" value="<?= e(input('name')) ?>" required autofocus></label>
  <label>ইমেইল <input type="email" name="email" value="<?= e(input('email')) ?>" required></label>
  <label>মোবাইল <small class="muted">(ঐচ্ছিক)</small> <input name="phone" inputmode="numeric" placeholder="01XXXXXXXXX" value="<?= e(input('phone')) ?>"></label>
  <label>পাসওয়ার্ড <input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
  <button class="btn btn-block">রেজিস্টার</button>
  <p class="small">আগেই অ্যাকাউন্ট আছে? <a href="<?= url('login.php' . ($next ? '?next=' . urlencode($next) : '')) ?>">লগইন</a></p>
</form>
<?php require APP_DIR . '/views/footer.php';
