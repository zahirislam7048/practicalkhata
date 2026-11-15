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
<div class="card order-card">
  <div class="order-head">
    <div>
      <b>অর্ডার #<?= e($o['order_no']) ?></b>
      <span class="muted small"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></span>
    </div>
    <span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
  </div>
  <table class="table">
    <?php foreach ($itemsByOrder[$o['id']] ?? [] as $it):
        $left = max(0, (int)$it['download_limit'] - (int)$it['download_count']); ?>
    <tr>
      <td><?= e($it['title']) ?></td>
      <td class="num"><?= money($it['price']) ?></td>
      <td class="num">
        <?php if ($o['status'] === 'paid' && $it['file_path'] && $left > 0): ?>
          <a class="btn btn-sm" href="<?= url('download.php?item=' . (int)$it['id']) ?>">ডাউনলোড</a>
          <div class="muted small">আর <?= $left ?> বার</div>
        <?php elseif ($o['status'] === 'paid' && $it['file_path']): ?>
          <span class="muted small">ডাউনলোড সীমা শেষ — সাপোর্টে যোগাযোগ করুন</span>
        <?php elseif ($o['status'] === 'paid'): ?>
          <span class="muted small">ফাইল শীঘ্রই যুক্ত হবে</span>
        <?php elseif ($o['status'] === 'pending'): ?>
          <span class="muted small">পেমেন্ট যাচাইয়ের অপেক্ষায়</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="order-foot muted small">
    মোট <?= money($o['total']) ?>
    <?php if ($o['payment_method'] !== 'free'): ?> · <?= e(method_label($o['payment_method'])) ?> · TrxID <?= e($o['trx_id']) ?><?php endif; ?>
    <?php if ($o['admin_note']): ?><div class="note">নোট: <?= e($o['admin_note']) ?></div><?php endif; ?>
  </div>
</div>
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
