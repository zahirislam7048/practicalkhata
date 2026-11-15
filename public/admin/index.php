<?php
require dirname(__DIR__) . '/_init.php';
$title = 'ড্যাশবোর্ড';
require APP_DIR . '/views/admin_header.php';

$stats = [
    'যাচাই বাকি'       => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
    'Payment Due'      => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'due'"),
    'আজকের বিক্রি'     => money(val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'paid' AND DATE(created_at) = CURDATE()")),
    'এই মাসের বিক্রি'  => money(val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'paid' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")),
    'মোট বিক্রি'       => money(val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'paid'")),
    'সফল অর্ডার'      => (int)val("SELECT COUNT(*) FROM orders WHERE status = 'paid'"),
    'কাস্টমার'         => (int)val("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
    'সক্রিয় পণ্য'      => (int)val('SELECT COUNT(*) FROM products WHERE is_active = 1'),
];
$pending = rows("SELECT o.*, COALESCE(u.name, o.guest_name) AS name FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.status = 'pending' ORDER BY o.created_at ASC LIMIT 10");
$top = rows("SELECT oi.title, COUNT(*) sold, SUM(oi.price) revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id
             WHERE o.status = 'paid' GROUP BY oi.product_id, oi.title ORDER BY sold DESC LIMIT 5");
$setupMissing = !setting('bkash_number') && !setting('nagad_number');
?>
<?php if ($setupMissing): ?>
  <div class="alert alert-info">পেমেন্ট নেওয়ার জন্য <a href="settings.php">সেটিংস</a> এ গিয়ে bKash / Nagad নাম্বার দিন।</div>
<?php endif; ?>
<div class="stats">
  <?php foreach ($stats as $label => $v): ?>
    <div class="card stat"><div class="muted small"><?= e($label) ?></div><div class="stat-v"><?= e((string)$v) ?></div></div>
  <?php endforeach; ?>
</div>
<div class="two-col">
  <section class="card">
    <h3>যাচাইয়ের অপেক্ষায় অর্ডার</h3>
    <?php if (!$pending): ?><p class="muted">কোনো pending অর্ডার নেই 🎉</p><?php else: ?>
    <table class="table">
      <tr><th>অর্ডার</th><th>কাস্টমার</th><th>মাধ্যম / TrxID</th><th class="num">টাকা</th></tr>
      <?php foreach ($pending as $o): ?>
      <tr>
        <td><a href="order.php?id=<?= (int)$o['id'] ?>">#<?= e($o['order_no']) ?></a></td>
        <td><?= e($o['name']) ?></td>
        <td><?= e(method_label($o['payment_method'])) ?> · <code><?= e($o['trx_id']) ?></code></td>
        <td class="num"><?= money($o['total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
  <section class="card">
    <h3>সর্বাধিক বিক্রিত</h3>
    <?php if (!$top): ?><p class="muted">এখনো বিক্রি হয়নি।</p><?php else: ?>
    <table class="table">
      <?php foreach ($top as $t): ?>
      <tr><td><?= e($t['title']) ?></td><td class="num"><?= (int)$t['sold'] ?> টি</td><td class="num"><?= money($t['revenue']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
