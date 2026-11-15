<?php
require __DIR__ . '/_init.php';

if (is_post()) {
    csrf_check();
    $action = input('action');
    $id = (int)input('id');
    if ($action === 'add' && $id) {
        if (val('SELECT id FROM products WHERE id = ? AND is_active = 1', [$id])) {
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
<h1>আপনার কার্ট</h1>
<?php if (!$items): ?>
  <p class="empty">কার্ট খালি। <a href="<?= url() ?>">পণ্য দেখুন</a></p>
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
            <button class="link-btn danger" title="সরান">✕</button>
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
    <a class="btn btn-block" href="<?= url('checkout.php') ?>">চেকআউট করুন</a>
  </aside>
</div>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
