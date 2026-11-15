<?php
require __DIR__ . '/_init.php';

if (is_post()) {
    csrf_check();
    $action = input('action');
    $id = (int)input('id');
    if ($action === 'add' && $id) {
        $added = row('SELECT * FROM products WHERE id = ? AND is_active = 1', [$id]);
        if ($added) {
            if (!in_array($id, cart_ids(), true)) {
                track_event('AddToCart', 'add_to_cart', track_items([$added]), product_price($added), [], true);
            }
            cart_add($id);
            if (input('go') !== 'checkout') {
                flash('success', 'কার্টে যোগ হয়েছে।');
            }
        }
    } elseif ($action === 'remove') {
        cart_remove($id);
    } elseif ($action === 'coupon') {
        $code = strtoupper(input('coupon'));
        [$c, $err] = coupon_check($code, cart_subtotal(cart_items()));
        if ($c) {
            $_SESSION['coupon'] = $c['code'];
            flash('success', 'কুপন যুক্ত হয়েছে।');
        } else {
            unset($_SESSION['coupon']);
            flash('error', $err ?: 'কুপন কোড লিখুন।');
        }
    } elseif ($action === 'uncoupon') {
        unset($_SESSION['coupon']);
    }
    redirect(input('go') === 'checkout' ? 'checkout.php' : 'cart.php');
}

$items = cart_items();
$t = cart_totals($items);
$title = 'কার্ট';
require APP_DIR . '/views/header.php';
?>
<?php $step = 1; require APP_DIR . '/views/steps.php'; ?>
<h1>আপনার কার্ট <small class="muted">(<?= bn(count($items)) ?>টি পণ্য)</small></h1>
<?php if (!$items): ?>
  <div class="empty"><?= icon('cart', 44) ?><p>আপনার কার্ট খালি।</p><a class="btn" href="<?= url() ?>">কেনাকাটা শুরু করুন</a></div>
<?php else: ?>
<div class="checkout-grid">
  <div class="card">
    <table class="table">
      <?php foreach ($items as $it): ?>
      <tr>
        <td class="w-thumb"><img src="<?= e(product_image($it)) ?>" alt=""></td>
        <td><a href="<?= url('product.php?slug=' . urlencode($it['slug'])) ?>"><?= e($it['title']) ?></a></td>
        <td class="num"><?= money(product_price($it)) ?></td>
        <td class="num">
          <form method="post"><?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="link-btn danger" title="সরান" aria-label="সরান"><?= icon('trash', 18) ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <aside class="card summary">
    <h3>সারসংক্ষেপ</h3>
    <div class="sum-row"><span>সাবটোটাল</span><span><?= money($t['subtotal']) ?></span></div>
    <?php if ($t['coupon']): ?>
      <div class="sum-row success"><span>কুপন (<?= e($t['coupon']['code']) ?>)
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="uncoupon"><button class="link-btn">সরান</button></form>
      </span><span>−<?= money($t['discount']) ?></span></div>
    <?php else: ?>
      <form method="post" class="coupon-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="coupon">
        <input name="coupon" placeholder="কুপন কোড" autocomplete="off">
        <button class="btn btn-outline btn-sm">প্রয়োগ</button>
      </form>
    <?php endif; ?>
    <div class="sum-row total"><span>মোট</span><span><?= money($t['total']) ?></span></div>
    <a class="btn btn-accent btn-lg btn-block" href="<?= url('checkout.php') ?>"><?= icon('lock', 18) ?> চেকআউট করুন</a>
    <a class="small center-link" href="<?= url() ?>">← আরও কেনাকাটা করুন</a>
  </aside>
</div>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
