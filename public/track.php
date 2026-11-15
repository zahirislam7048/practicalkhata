<?php
require __DIR__ . '/_init.php';

$error = '';
if (is_post()) {
    csrf_check();
    if (login_throttled()) {
        $error = 'অনেকবার ভুল চেষ্টা হয়েছে। ১০ মিনিট পর আবার চেষ্টা করুন।';
    } else {
        $no = strtoupper(ltrim(input('order_no'), '#'));
        $phone = preg_replace('/\D+/', '', input('phone'));
        $o = row('SELECT o.access_token FROM orders o LEFT JOIN users u ON u.id = o.user_id
                  WHERE o.order_no = ? AND (o.guest_phone = ? OR o.sender_number = ? OR u.phone = ?)', [$no, $phone, $phone, $phone]);
        if ($o && $o['access_token']) {
            redirect('order.php?t=' . $o['access_token']);
        }
        login_failed();
        $error = 'এই অর্ডার নম্বর ও মোবাইল নাম্বার মেলেনি।';
    }
}
// Orders placed as a guest from this browser
$mine = [];
foreach (array_slice(array_reverse($_SESSION['guest_orders'] ?? []), 0, 10) as $tk) {
    if ($r = row('SELECT order_no, status, total, access_token, created_at FROM orders WHERE access_token = ?', [$tk])) {
        $mine[] = $r;
    }
}
$title = 'অর্ডার ট্র্যাক';
require APP_DIR . '/views/header.php';
?>
<form method="post" class="card form narrow auth">
  <h1>অর্ডার ট্র্যাক করুন</h1>
  <p class="muted small" style="text-align:center">অর্ডার নম্বর ও যে মোবাইল নাম্বার দিয়েছিলেন তা লিখুন — অর্ডারের অবস্থা দেখবেন ও ডাউনলোড করতে পারবেন।</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>অর্ডার নম্বর<input name="order_no" placeholder="যেমন PK2611151234" value="<?= e(input('order_no')) ?>" required></label>
  <label>মোবাইল নাম্বার<input name="phone" inputmode="numeric" placeholder="01XXXXXXXXX" value="<?= e(input('phone')) ?>" required></label>
  <button class="btn btn-accent btn-lg btn-block"><?= icon('search', 18) ?> খুঁজুন</button>
  <p class="small" style="text-align:center">অ্যাকাউন্ট দিয়ে কিনেছেন? <a href="<?= url('account.php') ?>">আমার অ্যাকাউন্ট</a></p>
</form>
<?php if ($mine): ?>
<div class="card narrow" style="margin:0 auto">
  <h3>এই ব্রাউজার থেকে করা অর্ডার</h3>
  <table class="table">
    <?php foreach ($mine as $m): ?>
    <tr><td><a href="<?= url('order.php?t=' . $m['access_token']) ?>">#<?= e($m['order_no']) ?></a></td>
      <td class="num"><?= money($m['total']) ?></td>
      <td class="num"><span class="status status-<?= e($m['status']) ?>"><?= e(status_label($m['status'])) ?></span></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
