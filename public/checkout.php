<?php
require __DIR__ . '/_init.php';

$user = require_login();
$items = cart_items();

// Don't charge again for something the customer already owns
$owned = owned_product_ids((int)$user['id']);
$already = array_filter($items, fn($p) => in_array((int)$p['id'], $owned, true));
foreach ($already as $p) {
    cart_remove((int)$p['id']);
    flash('info', '“' . $p['title'] . '” আপনি আগেই কিনেছেন, তাই কার্ট থেকে সরানো হয়েছে।');
}
if ($already) {
    redirect('checkout.php');
}
if (!$items) {
    redirect('cart.php');
}

$t = cart_totals($items);
$methods = array_filter([
    'bkash' => setting('bkash_number'),
    'nagad' => setting('nagad_number'),
]);
$isFree = $t['total'] <= 0;
$errors = [];

if (is_post()) {
    csrf_check();
    $method = $isFree ? 'free' : input('method');
    $sender = preg_replace('/\D+/', '', input('sender'));
    $trx = strtoupper(preg_replace('/\s+/', '', input('trx')));

    if (!$isFree) {
        if (!isset($methods[$method])) {
            $errors[] = 'পেমেন্ট মাধ্যম বেছে নিন।';
        }
        if (!preg_match('/^01[3-9]\d{8}$/', $sender)) {
            $errors[] = 'যে নাম্বার থেকে টাকা পাঠিয়েছেন সেটি সঠিকভাবে লিখুন (যেমন 017XXXXXXXX)।';
        }
        if (!preg_match('/^[A-Z0-9]{6,30}$/', $trx)) {
            $errors[] = 'সঠিক Transaction ID লিখুন।';
        } elseif (val('SELECT id FROM orders WHERE payment_method = ? AND trx_id = ?', [$method, $trx])) {
            $errors[] = 'এই Transaction ID দিয়ে আগেই একটি অর্ডার করা হয়েছে।';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $orderNo = new_order_no();
            q('INSERT INTO orders (order_no, user_id, subtotal, discount, total, coupon_id, coupon_code, payment_method, sender_number, trx_id, status)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
                $orderNo, $user['id'], $t['subtotal'], $t['discount'], $t['total'],
                $t['coupon']['id'] ?? null, $t['coupon']['code'] ?? null,
                $method, $isFree ? null : $sender, $isFree ? null : $trx,
                $isFree ? 'paid' : 'pending',
            ]);
            $orderId = (int)$pdo->lastInsertId();
            foreach ($items as $it) {
                q('INSERT INTO order_items (order_id, product_id, title, price) VALUES (?,?,?,?)',
                  [$orderId, $it['id'], $it['title'], product_price($it)]);
            }
            if ($t['coupon']) {
                q('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$t['coupon']['id']]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        cart_clear();
        track_event('Purchase', 'purchase', track_items($items), (float)$t['total'],
            ['event_id' => $orderNo, 'coupon' => $t['coupon']['code'] ?? null], true);
        flash('success', $isFree
            ? 'অর্ডার সম্পন্ন! এখনই ডাউনলোড করতে পারবেন।'
            : 'অর্ডার #' . $orderNo . ' জমা হয়েছে। পেমেন্ট যাচাই হলে ডাউনলোড চালু হবে।');
        redirect('account.php');
    }
}

if (!is_post()) {
    track_event('InitiateCheckout', 'begin_checkout', track_items($items), (float)$t['total']);
}

$title = 'চেকআউট';
require APP_DIR . '/views/header.php';
?>
<h1>চেকআউট</h1>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<div class="checkout-grid">
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <?php if ($isFree): ?>
      <h3>এই অর্ডারে কোনো পেমেন্ট লাগবে না</h3>
      <p class="muted">নিচের বাটনে চাপ দিলেই অর্ডার সম্পন্ন হবে।</p>
    <?php elseif (!$methods): ?>
      <div class="alert alert-error">পেমেন্ট নাম্বার এখনো সেট করা হয়নি। অনুগ্রহ করে পরে চেষ্টা করুন।</div>
    <?php else: ?>
      <h3>১. পেমেন্ট মাধ্যম বেছে নিন</h3>
      <div class="pay-methods">
        <?php $first = true; foreach ($methods as $m => $number): ?>
        <label class="pay-option pay-<?= $m ?>">
          <input type="radio" name="method" value="<?= $m ?>" <?= (input('method') ?: ($first ? $m : '')) === $m ? 'checked' : '' ?>>
          <span class="pay-name"><?= method_label($m) ?></span>
          <span class="pay-number" data-copy="<?= e($number) ?>"><?= e($number) ?> <small>(কপি)</small></span>
        </label>
        <?php $first = false; endforeach; ?>
      </div>
      <div class="pay-box">
        <p><b><?= money($t['total']) ?></b> টাকা উপরের নাম্বারে <b>Send Money</b> করুন।</p>
        <p class="muted small"><?= nl2br(e(setting('payment_note'))) ?></p>
      </div>
      <h3>২. পেমেন্টের তথ্য দিন</h3>
      <label>যে নাম্বার থেকে টাকা পাঠিয়েছেন
        <input name="sender" inputmode="numeric" placeholder="01XXXXXXXXX" value="<?= e(input('sender', (string)$user['phone'])) ?>" required>
      </label>
      <label>Transaction ID (TrxID)
        <input name="trx" placeholder="যেমন: 9A7B6C5D4E" value="<?= e(input('trx')) ?>" required autocomplete="off">
      </label>
    <?php endif; ?>
    <?php if ($isFree || $methods): ?>
      <button class="btn btn-block"><?= $isFree ? 'অর্ডার সম্পন্ন করুন' : 'অর্ডার জমা দিন' ?></button>
    <?php endif; ?>
  </form>
  <aside class="card summary">
    <h3>অর্ডার সারসংক্ষেপ</h3>
    <?php foreach ($items as $it): ?>
      <div class="sum-row"><span><?= e($it['title']) ?></span><span><?= money(product_price($it)) ?></span></div>
    <?php endforeach; ?>
    <hr>
    <div class="sum-row"><span>সাবটোটাল</span><span><?= money($t['subtotal']) ?></span></div>
    <?php if ($t['discount'] > 0): ?>
      <div class="sum-row success"><span>কুপন (<?= e($t['coupon']['code']) ?>)</span><span>−<?= money($t['discount']) ?></span></div>
    <?php endif; ?>
    <div class="sum-row total"><span>মোট</span><span><?= money($t['total']) ?></span></div>
    <a href="<?= url('cart.php') ?>" class="small">← কার্টে ফিরে যান</a>
  </aside>
</div>
<?php require APP_DIR . '/views/footer.php';
