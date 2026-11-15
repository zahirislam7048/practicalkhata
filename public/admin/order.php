<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if (is_post()) {
    csrf_check();
    $action = input('action');
    if ($action === 'approve') {
        $mailMsg = set_order_status($id, 'paid', input('note'));
        flash('success', 'Approved — কাস্টমার এখন ডাউনলোড করতে পারবে।');
        if ($mailMsg) {
            flash(str_starts_with($mailMsg, 'ডাউনলোড লিংক ইমেইলে') ? 'success' : 'info', $mailMsg);
        }
    } elseif ($action === 'resend_email') {
        $err = send_download_email($id);
        flash($err ? 'error' : 'success', $err ? 'ইমেইল যায়নি: ' . $err : 'ডাউনলোড লিংক আবার ইমেইলে পাঠানো হয়েছে।');
    } elseif ($action === 'reject') {
        set_order_status($id, 'rejected', input('note'));
        flash('info', 'Declined — অর্ডার বাতিল করা হয়েছে।');
    } elseif ($action === 'due') {
        $due = input('due_amount');
        if (!is_numeric($due) || (float)$due <= 0) {
            flash('error', 'Payment Due দিতে কত টাকা বাকি সেটা লিখুন।');
        } else {
            set_order_status($id, 'due', input('note'), (float)$due);
            flash('info', 'Payment Due — কাস্টমার বাকি ' . money($due) . ' পাঠিয়ে নতুন TrxID দিতে পারবে।');
        }
    } elseif ($action === 'pending') {
        set_order_status($id, 'pending', input('note'));
        flash('info', 'অর্ডার আবার pending করা হয়েছে।');
    } elseif ($action === 'reset_downloads') {
        q('UPDATE order_items SET download_count = 0 WHERE order_id = ?', [$id]);
        flash('success', 'ডাউনলোড কাউন্ট রিসেট হয়েছে।');
    }
    redirect('admin/order.php?id=' . $id);
}

$o = row('SELECT o.*, COALESCE(u.name, o.guest_name) AS name, COALESCE(u.email, o.guest_email) AS email, COALESCE(u.phone, o.guest_phone) AS phone
          FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$id]);
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
      <?php if ($o['status'] === 'due'): ?><dt>বাকি</dt><dd><b class="danger"><?= money($o['due_amount']) ?></b></dd><?php endif; ?>
      <?php if ($o['payment_log']): ?><dt>পরের পেমেন্ট</dt><dd class="small"><?= nl2br(e(trim($o['payment_log']))) ?></dd><?php endif; ?>
      <dt>সময়</dt><dd><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></dd>
    </dl>
    <p class="muted small">আপনার <?= e(method_label($o['payment_method'])) ?> অ্যাপে TrxID ও টাকার পরিমাণ মিলিয়ে নিন, তারপর অনুমোদন দিন।</p>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>নোট (কাস্টমার দেখতে পাবে, ঐচ্ছিক)<input name="note" value="<?= e((string)$o['admin_note']) ?>"></label>
      <label>বাকি টাকা (শুধু Payment Due এর জন্য)
        <input name="due_amount" type="number" step="0.01" min="0" value="<?= e((string)($o['due_amount'] ?? '')) ?>" placeholder="যেমন 50"></label>
      <div class="status-actions">
        <button class="btn btn-approve" name="action" value="approve" <?= $o['status'] === 'paid' ? 'disabled' : '' ?>>✔ Approve</button>
        <button class="btn btn-due" name="action" value="due">⏳ Payment Due</button>
        <button class="btn btn-danger" name="action" value="reject" data-confirm="অর্ডারটি Decline করবেন?" <?= $o['status'] === 'rejected' ? 'disabled' : '' ?>>✕ Decline</button>
      </div>
      <?php if ($o['status'] !== 'pending'): ?><button class="link-btn small" name="action" value="pending">↺ আবার "যাচাই চলছে" করুন</button><?php endif; ?>
      <p class="muted small">
        <b>Approve</b>: টাকা ঠিক আছে, ডাউনলোড চালু হবে ·
        <b>Payment Due</b>: কম টাকা এসেছে — বাকি টাকা লিখুন, কাস্টমার আবার পাঠাবে ·
        <b>Decline</b>: পেমেন্ট পাওয়া যায়নি / ভুল TrxID
      </p>
    </form>
  </section>
  <section class="card">
    <h3>কাস্টমার</h3>
    <dl class="dl">
      <dt>নাম</dt><dd><?= e($o['name']) ?><?php if (!$o['user_id']): ?> <span class="badge">গেস্ট</span><?php endif; ?></dd>
      <dt>ইমেইল</dt><dd><?= e($o['email']) ?></dd>
      <dt>মোবাইল</dt><dd><?= e((string)$o['phone']) ?></dd>
    </dl>
    <h3>ডাউনলোড লিংক পাঠানো</h3>
    <?php $oFull = row('SELECT * FROM orders WHERE id = ?', [$id]); $custEmail = order_email($oFull); $link = order_link($oFull); ?>
    <div class="send-box">
      <?php if ($custEmail): ?>
        <div>✉️ <b><?= e($custEmail) ?></b></div>
        <?php if ($oFull['email_sent_at']): ?>
          <div class="success small">✓ ইমেইল পাঠানো হয়েছে — <?= e(date('d M Y, h:i A', strtotime($oFull['email_sent_at']))) ?></div>
        <?php elseif ($oFull['email_error']): ?>
          <div class="danger small">✕ ইমেইল যায়নি: <?= e($oFull['email_error']) ?></div>
        <?php elseif ($o['status'] !== 'paid'): ?>
          <div class="muted small">Approve করলে নিজে থেকেই ডাউনলোড লিংক এই ইমেইলে চলে যাবে।</div>
        <?php endif; ?>
        <?php if ($o['status'] === 'paid'): ?>
          <form method="post" class="inline"><?= csrf_field() ?><button class="btn btn-outline btn-sm" name="action" value="resend_email">✉️ <?= $oFull['email_sent_at'] ? 'আবার ইমেইল পাঠান' : 'ইমেইল পাঠান' ?></button></form>
        <?php endif; ?>
      <?php else: ?>
        <div class="muted small">কাস্টমার ইমেইল দেননি — নিচের লিংক কপি করে বা WhatsApp এ পাঠান।</div>
      <?php endif; ?>
      <div class="copy-row"><input readonly value="<?= e($link) ?>" onclick="this.select()"><button type="button" class="btn btn-sm" data-copy="<?= e($link) ?>"><small>কপি</small></button></div>
      <?php if ($o['status'] !== 'paid'): ?><p class="muted small">⚠️ Approve না করা পর্যন্ত এই লিংকে ডাউনলোড বাটন আসবে না।</p><?php endif; ?>
      <a class="btn btn-wa btn-sm" href="<?= e(order_whatsapp_link($oFull)) ?>" target="_blank" rel="noopener">WhatsApp এ লিংক পাঠান</a>
    </div>
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
