<?php
require __DIR__ . '/_init.php';

$user = require_login();

if (is_post() && input('action') === 'profile') {
    csrf_check();
    $name = input('name');
    $phone = preg_replace('/\D+/', '', input('phone'));
    $newPass = (string)($_POST['new_password'] ?? '');
    if ($name === '') {
        flash('error', 'নাম লিখুন।');
    } elseif ($newPass !== '' && mb_strlen($newPass) < 6) {
        flash('error', 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।');
    } else {
        q('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone ?: null, $user['id']]);
        if ($newPass !== '') {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
        }
        flash('success', 'প্রোফাইল আপডেট হয়েছে।');
    }
    redirect('account.php');
}

$orders = rows('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', [$user['id']]);
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (rows("SELECT oi.*, p.download_limit, p.file_path, p.slug FROM order_items oi
                   LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id IN ($in)", $ids) as $it) {
        $itemsByOrder[$it['order_id']][] = $it;
    }
}

$title = 'আমার অ্যাকাউন্ট';
require APP_DIR . '/views/header.php';
?>
<div class="account-head">
  <h1>স্বাগতম, <?= e($user['name']) ?></h1>
  <a href="<?= url('logout.php') ?>" class="btn btn-outline btn-sm">লগআউট</a>
</div>

<h2>আমার অর্ডার ও ডাউনলোড</h2>
<?php if (!$orders): ?>
  <p class="empty">এখনো কোনো অর্ডার নেই। <a href="<?= url() ?>">পণ্য দেখুন</a></p>
<?php endif; ?>
<?php foreach ($orders as $o): ?>
<?php $items = $itemsByOrder[$o['id']] ?? []; $token = null; require APP_DIR . '/views/order_card.php'; ?>
<?php endforeach; ?>

<h2>প্রোফাইল</h2>
<form method="post" class="card form narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="profile">
  <label>নাম <input name="name" value="<?= e($user['name']) ?>" required></label>
  <label>ইমেইল <input value="<?= e($user['email']) ?>" disabled></label>
  <label>মোবাইল <input name="phone" value="<?= e((string)$user['phone']) ?>" inputmode="numeric"></label>
  <label>নতুন পাসওয়ার্ড <small class="muted">(পরিবর্তন না করলে খালি রাখুন)</small>
    <input type="password" name="new_password" autocomplete="new-password"></label>
  <button class="btn">সেভ করুন</button>
</form>
<?php require APP_DIR . '/views/footer.php';
