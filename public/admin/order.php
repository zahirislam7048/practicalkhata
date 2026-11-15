<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if (is_post()) {
    csrf_check();
    $action = input('action');
    if ($action === 'approve') {
        set_order_status($id, 'paid', input('note'));
        flash('success', 'অর্ডার অনুমোদিত — কাস্টমার এখন ডাউনলোড করতে পারবে।');
    } elseif ($action === 'reject') {
        set_order_status($id, 'rejected', input('note'));
        flash('info', 'অর্ডার বাতিল করা হয়েছে।');
    } elseif ($action === 'pending') {
        set_order_status($id, 'pending', input('note'));
        flash('info', 'অর্ডার আবার pending করা হয়েছে।');
    } elseif ($action === 'reset_downloads') {
        q('UPDATE order_items SET download_count = 0 WHERE order_id = ?', [$id]);
        flash('success', 'ডাউনলোড কাউন্ট রিসেট হয়েছে।');
    }
    redirect('admin/order.php?id=' . $id);
}

$o = row('SELECT o.*, u.name, u.email, u.phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$id]);
if (!$o) {
    redirect('admin/orders.php');
}
$items = rows('SELECT oi.*, p.download_limit FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$id]);
$title = 'অর্ডার #' . $o['order_no'];
require APP_DIR . '/views/admin_header.php';
?>
<p><a href="orders.php">← সব অর্ডার</a></p>
<div class="two-col">
  <section class="card">
    <h3>পেমেন্ট যাচাই</h3>
    <dl class="dl">
      <dt>স্ট্যাটাস</dt><dd><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></dd>
      <dt>মাধ্যম</dt><dd><?= e(method_label($o['payment_method'])) ?></dd>
      <?php if ($o['payment_method'] !== 'free'): ?>
      <dt>Transaction ID</dt><dd><code class="big"><?= e($o['trx_id']) ?></code></dd>
      <dt>প্রেরকের নাম্বার</dt><dd><?= e($o['sender_number']) ?></dd>
      <?php endif; ?>
      <dt>পরিশোধযোগ্য</dt><dd><b><?= money($o['total']) ?></b></dd>
      <dt>সময়</dt><dd><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></dd>
    </dl>
    <p class="muted small">আপনার <?= e(method_label($o['payment_method'])) ?> অ্যাপে TrxID ও টাকার পরিমাণ মিলিয়ে নিন, তারপর অনুমোদন দিন।</p>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>নোট (কাস্টমার দেখতে পাবে, ঐচ্ছিক)<input name="note" value="<?= e((string)$o['admin_note']) ?>"></label>
      <div class="btn-row">
        <?php if ($o['status'] !== 'paid'): ?><button class="btn" name="action" value="approve">✔ অনুমোদন দিন</button><?php endif; ?>
        <?php if ($o['status'] !== 'rejected'): ?><button class="btn btn-danger" name="action" value="reject" data-confirm="অর্ডারটি বাতিল করবেন?">✕ বাতিল</button><?php endif; ?>
        <?php if ($o['status'] !== 'pending'): ?><button class="btn btn-outline" name="action" value="pending">Pending করুন</button><?php endif; ?>
      </div>
    </form>
  </section>
  <section class="card">
    <h3>কাস্টমার</h3>
    <dl class="dl">
      <dt>নাম</dt><dd><?= e($o['name']) ?></dd>
      <dt>ইমেইল</dt><dd><?= e($o['email']) ?></dd>
      <dt>মোবাইল</dt><dd><?= e((string)$o['phone']) ?></dd>
    </dl>
    <h3>পণ্য</h3>
    <table class="table">
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= e($it['title']) ?></td>
        <td class="num"><?= money($it['price']) ?></td>
        <td class="num small"><?= (int)$it['download_count'] ?>/<?= (int)($it['download_limit'] ?? 0) ?> ডাউনলোড</td>
      </tr>
      <?php endforeach; ?>
      <tr><td>সাবটোটাল</td><td class="num"><?= money($o['subtotal']) ?></td><td></td></tr>
      <?php if ((float)$o['discount'] > 0): ?><tr><td>কুপন <?= e((string)$o['coupon_code']) ?></td><td class="num">−<?= money($o['discount']) ?></td><td></td></tr><?php endif; ?>
      <tr><th>মোট</th><th class="num"><?= money($o['total']) ?></th><td></td></tr>
    </table>
    <form method="post"><?= csrf_field() ?><button class="btn btn-outline btn-sm" name="action" value="reset_downloads">ডাউনলোড কাউন্ট রিসেট</button></form>
  </section>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
