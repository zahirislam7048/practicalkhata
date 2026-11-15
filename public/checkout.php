<?php
require __DIR__ . '/_init.php';

$user = current_user();   // null = guest checkout
$items = cart_items();

// Don't charge again for something a logged-in customer already owns
$owned = $user ? owned_product_ids((int)$user['id']) : [];
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
    $guest = null;
    if (!$user) {
        $guest = [
            'name'  => input('guest_name'),
            'phone' => preg_replace('/\D+/', '', input('guest_phone')),
            'email' => mb_strtolower(input('guest_email')),
        ];
        if ($guest['name'] === '') {
            $errors[] = 'আপনার নাম লিখুন।';
        }
        if (!preg_match('/^01[3-9]\d{8}$/', $guest['phone'])) {
            $errors[] = 'সঠিক মোবাইল নাম্বার লিখুন (যেমন 017XXXXXXXX)।';
        }
        if ($guest['email'] !== '' && !filter_var($guest['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'সঠিক ইমেইল লিখুন, অথবা খালি রাখুন।';
        }
        $_SESSION['guest'] = $guest; // remembered for the next order and for tracking
    }

    if (!$isFree) {
        if (!isset($methods[$method])) {
            $errors[] = 'পেমেন্ট মাধ্যম বেছে নিন।';
        }
        if (!preg_match('/^01[3-9]\d{8}$/', $sender)) {
            $errors[] = 'যে নাম্বার থেকে টাকা পাঠিয়েছেন সেটি সঠিকভাবে লিখুন (যেমন 017XXXXXXXX)।';
        }
        if (!preg_match('/^[A-Z0-9]{6,30}$/', $trx)) {
            $errors[] = 'সঠিক Transaction ID লিখুন।';
        } elseif (trx_used($method, $trx)) {
            $errors[] = 'এই Transaction ID দিয়ে আগেই একটি অর্ডার করা হয়েছে।';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $orderNo = new_order_no();
            $token = bin2hex(random_bytes(20));
            q('INSERT INTO orders (order_no, user_id, guest_name, guest_phone, guest_email, access_token, subtotal, discount, total, coupon_id, coupon_code, payment_method, sender_number, trx_id, status)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                $orderNo, $user['id'] ?? null, $guest['name'] ?? null, $guest['phone'] ?? null, ($guest['email'] ?? '') ?: null, $token,
                $t['subtotal'], $t['discount'], $t['total'],
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
        if ($user) {
            redirect('account.php');
        }
        // Guests: remember their orders in this browser and show the secret order page
        $_SESSION['guest_orders'][] = $token;
        redirect('order.php?t=' . $token);
    }
}

if (!is_post()) {
    track_event('InitiateCheckout', 'begin_checkout', track_items($items), (float)$t['total']);
}

$title = 'চেকআউট';
require APP_DIR . '/views/header.php';
?>
<?php $step = 2; require APP_DIR . '/views/steps.php'; ?>
<h1>চেকআউট</h1>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<div class="checkout-grid">
  <form method="post" class="card form">
    <?= csrf_field() ?>
    <?php $g = $_SESSION['guest'] ?? []; if (!$user): ?>
      <div class="guest-box">
        <h3><?= icon('user', 20) ?> আপনার তথ্য</h3>
        <p class="muted small">অ্যাকাউন্ট ছাড়াই কিনতে পারবেন। অ্যাকাউন্ট আছে? <a href="<?= url('login.php?next=' . urlencode(url('checkout.php'))) ?>">লগইন করুন</a></p>
        <label>আপনার নাম *<input name="guest_name" value="<?= e(input('guest_name', (string)($g['name'] ?? ''))) ?>" required autocomplete="name"></label>
        <div class="row2">
          <label>মোবাইল নাম্বার *<input name="guest_phone" inputmode="numeric" placeholder="01XXXXXXXXX" value="<?= e(input('guest_phone', (string)($g['phone'] ?? ''))) ?>" required autocomplete="tel"></label>
          <label>ইমেইল <small class="muted">(ঐচ্ছিক)</small><input type="email" name="guest_email" value="<?= e(input('guest_email', (string)($g['email'] ?? ''))) ?>" autocomplete="email"></label>
        </div>
      </div>
    <?php endif; ?>
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
        <input name="sender" inputmode="numeric" placeholder="01XXXXXXXXX" value="<?= e(input('sender', (string)($user['phone'] ?? ($g['phone'] ?? '')))) ?>" required>
      </label>
      <label>Transaction ID (TrxID)
        <input name="trx" placeholder="যেমন: 9A7B6C5D4E" value="<?= e(input('trx')) ?>" required autocomplete="off">
      </label>
    <?php endif; ?>
    <?php if ($isFree || $methods): ?>
      <button class="btn btn-accent btn-lg btn-block"><?= icon('lock', 18) ?> <?= $isFree ? 'অর্ডার সম্পন্ন করুন' : 'অর্ডার জমা দিন' ?></button>
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
