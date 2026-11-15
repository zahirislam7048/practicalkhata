<?php
require __DIR__ . '/_init.php';

$prod = row('SELECT * FROM products WHERE slug = ? AND is_active = 1', [(string)($_GET['slug'] ?? '')]);
if (!$prod) {
    http_response_code(404);
    $title = 'পাওয়া যায়নি';
    require APP_DIR . '/views/header.php';
    echo '<p class="empty">এই পণ্যটি পাওয়া যায়নি। <a href="' . url() . '">হোমে ফিরে যান</a></p>';
    require APP_DIR . '/views/footer.php';
    exit;
}
$price = product_price($prod);
$inCart = in_array((int)$prod['id'], cart_ids(), true);
$owned = current_user() && in_array((int)$prod['id'], owned_product_ids((int)current_user()['id']), true);

track_event('ViewContent', 'view_item', track_items([$prod]), $price);

$title = $prod['title'];
$metaDesc = $prod['short_desc'] ?: mb_substr(strip_tags((string)$prod['description']), 0, 160);
require APP_DIR . '/views/header.php';
?>
<nav class="crumbs"><a href="<?= url() ?>">হোম</a> › <?= e($prod['title']) ?></nav>
<div class="product-detail">
  <div class="product-media card">
    <img src="<?= e(product_image($prod)) ?>" alt="<?= e($prod['title']) ?>">
  </div>
  <div>
    <h1><?= e($prod['title']) ?></h1>
    <?php if ($prod['short_desc']): ?><p class="lead"><?= e($prod['short_desc']) ?></p><?php endif; ?>
    <div class="price-row big">
      <span class="price"><?= $price > 0 ? money($price) : 'ফ্রি' ?></span>
      <?php if ($price < (float)$prod['price']): ?><s class="muted"><?= money($prod['price']) ?></s><?php endif; ?>
    </div>
    <ul class="features">
      <li>📄 ডিজিটাল পণ্য<?= $prod['file_name'] ? ' (' . e(strtoupper(pathinfo($prod['file_name'], PATHINFO_EXTENSION))) . ')' : '' ?></li>
      <li>⬇️ সর্বোচ্চ <?= (int)$prod['download_limit'] ?> বার ডাউনলোড</li>
      <li>💳 bKash / Nagad পেমেন্ট</li>
    </ul>
    <?php if ($owned): ?>
      <a class="btn" href="<?= url('account.php') ?>">আপনি এটি কিনেছেন — ডাউনলোড করুন</a>
    <?php elseif ($inCart): ?>
      <a class="btn" href="<?= url('cart.php') ?>">কার্টে আছে — চেকআউট করুন</a>
    <?php else: ?>
      <form method="post" action="<?= url('cart.php') ?>" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
        <input type="hidden" name="go" value="checkout">
        <button class="btn">এখনই কিনুন</button>
      </form>
      <form method="post" action="<?= url('cart.php') ?>" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
        <button class="btn btn-outline">কার্টে যোগ করুন</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php if ($prod['description']): ?>
<section class="card prose">
  <h2>বিস্তারিত</h2>
  <?= nl2br(e($prod['description'])) ?>
</section>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
