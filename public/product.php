<?php
require __DIR__ . '/_init.php';

$prod = row('SELECT * FROM products WHERE slug = ? AND is_active = 1', [(string)($_GET['slug'] ?? '')]);
if (!$prod) {
    http_response_code(404);
    $title = 'পাওয়া যায়নি';
    require APP_DIR . '/views/header.php';
    echo '<div class="empty">' . icon('search', 40) . '<p>এই পণ্যটি পাওয়া যায়নি।</p><a class="btn" href="' . url() . '">হোমে ফিরে যান</a></div>';
    require APP_DIR . '/views/footer.php';
    exit;
}
$price = product_price($prod);
$off = discount_percent($prod);
$inCart = in_array((int)$prod['id'], cart_ids(), true);
$owned = current_user() && in_array((int)$prod['id'], owned_product_ids((int)current_user()['id']), true);
$sold = (int)val("SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.status = 'paid'", [$prod['id']]);
$ext = $prod['file_name'] ? strtoupper(pathinfo($prod['file_name'], PATHINFO_EXTENSION)) : 'ডিজিটাল';
$related = shop_products('p.id <> ?', [$prod['id']], 'RAND()', 4);

track_event('ViewContent', 'view_item', track_items([$prod]), $price);

$title = $prod['title'];
$metaDesc = $prod['short_desc'] ?: mb_substr(strip_tags((string)$prod['description']), 0, 160);
$ogImage = (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . product_image($prod);
$wa = whatsapp_link('আমি "' . $prod['title'] . '" সম্পর্কে জানতে চাই।');
require APP_DIR . '/views/header.php';
?>
<nav class="crumbs"><a href="<?= url() ?>">হোম</a> <?= icon('chevron', 14) ?> <a href="<?= url('index.php?sort=new#products') ?>">সব পণ্য</a> <?= icon('chevron', 14) ?> <span><?= e($prod['title']) ?></span></nav>

<div class="pd">
  <div class="pd-media">
    <img src="<?= e(product_image($prod)) ?>" alt="<?= e($prod['title']) ?>">
    <?php if ($off): ?><span class="pcard-off big">-<?= bn($off) ?>%</span><?php endif; ?>
  </div>

  <div class="pd-info">
    <span class="pd-type"><?= icon('file', 14) ?> <?= e($ext) ?> ফাইল • ডিজিটাল পণ্য</span>
    <h1><?= e($prod['title']) ?></h1>
    <?php if ($sold > 0): ?><div class="pd-sold"><?= icon('check', 16) ?> <?= bn($sold) ?> জন কিনেছেন</div><?php endif; ?>
    <?php if ($prod['short_desc']): ?><p class="lead"><?= e($prod['short_desc']) ?></p><?php endif; ?>

    <div class="pd-pricebox">
      <div class="pd-price">
        <span class="now"><?= $price > 0 ? money($price) : 'ফ্রি' ?></span>
        <?php if ($off): ?><s><?= money($prod['price']) ?></s><span class="save">আপনি বাঁচাচ্ছেন <?= money((float)$prod['price'] - $price) ?></span><?php endif; ?>
      </div>

      <?php if ($owned): ?>
        <a class="btn btn-lg btn-block" href="<?= url('account.php') ?>"><?= icon('download', 20) ?> আপনি এটি কিনেছেন — ডাউনলোড করুন</a>
      <?php else: ?>
        <div class="pd-buttons">
          <form method="post" action="<?= url('cart.php') ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= (int)$prod['id'] ?>"><input type="hidden" name="go" value="checkout">
            <button class="btn btn-accent btn-lg btn-block">এখনই কিনুন</button>
          </form>
          <?php if ($inCart): ?>
            <a class="btn btn-outline btn-lg btn-block" href="<?= url('cart.php') ?>"><?= icon('cart', 20) ?> কার্টে আছে</a>
          <?php else: ?>
            <form method="post" action="<?= url('cart.php') ?>">
              <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
              <button class="btn btn-outline btn-lg btn-block"><?= icon('cart', 20) ?> কার্টে যোগ করুন</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="pay-badges"><span>পেমেন্ট:</span><span class="pb-bkash">bKash</span><span class="pb-nagad">Nagad</span></div>
    </div>

    <ul class="pd-perks">
      <li><?= icon('zap', 18) ?> পেমেন্ট যাচাইয়ের পর সাথে সাথে ডাউনলোড</li>
      <li><?= icon('download', 18) ?> সর্বোচ্চ <?= bn((int)$prod['download_limit']) ?> বার ডাউনলোড করা যাবে</li>
      <li><?= icon('user', 18) ?> অ্যাকাউন্ট ছাড়াই কেনা যায়</li>
      <?php if ($wa): ?><li><?= icon('support', 18) ?> প্রশ্ন আছে? <a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp করুন</a></li><?php endif; ?>
    </ul>
  </div>
</div>

<div class="pd-sections">
  <?php if ($prod['description']): ?>
  <section class="card">
    <h2>পণ্যের বিবরণ</h2>
    <div class="prose"><?= nl2br(e($prod['description'])) ?></div>
  </section>
  <?php endif; ?>
  <section class="card">
    <h2>কীভাবে কিনবেন?</h2>
    <ol class="howto">
      <li><b>১</b><span><strong>এখনই কিনুন</strong> চাপুন — অ্যাকাউন্ট ছাড়াই শুধু নাম ও মোবাইল নাম্বার দিয়ে কেনা যায়।</span></li>
      <li><b>২</b><span>চেকআউটে দেখানো bKash / Nagad নাম্বারে <strong><?= $price > 0 ? money($price) : '' ?></strong> Send Money করুন।</span></li>
      <li><b>৩</b><span>যে নাম্বার থেকে পাঠিয়েছেন সেটি ও <strong>Transaction ID</strong> দিয়ে অর্ডার জমা দিন।</span></li>
      <li><b>৪</b><span>পেমেন্ট যাচাই হলে অর্ডারের পেজ বা <strong>আমার ডাউনলোড</strong> থেকে ফাইল নামিয়ে নিন।</span></li>
    </ol>
  </section>
  <section class="card faq">
    <h2>সাধারণ জিজ্ঞাসা</h2>
    <details><summary>অ্যাকাউন্ট খোলা কি বাধ্যতামূলক?</summary><p>না। শুধু নাম ও মোবাইল নাম্বার দিয়েই কিনতে পারবেন। অর্ডারের পর একটি ডাউনলোড লিংক পাবেন — সেটি সেভ করে রাখুন।</p></details>
    <details><summary>ফাইল কখন পাব?</summary><p>পেমেন্ট যাচাই হওয়ার সাথে সাথেই অর্ডারের পেজে (অথবা অ্যাকাউন্টের "আমার ডাউনলোড" অংশে) ডাউনলোড বাটন চালু হবে।</p></details>
    <details><summary>কোন ফরম্যাটে ফাইল পাব?</summary><p>এই পণ্যটি <?= e($ext) ?> ফাইল হিসেবে দেওয়া হয়। মোবাইল বা কম্পিউটার — দুটোতেই খুলতে পারবেন।</p></details>
    <details><summary>ভুল Transaction ID দিলে কী হবে?</summary><p>যাচাই করতে না পারলে আমরা যোগাযোগ করব। সঠিক তথ্য দিয়ে আবার অর্ডার করতে পারবেন।</p></details>
  </section>
</div>

<?php if ($related): ?>
<section class="shelf">
  <div class="shelf-head"><h2>আরও দেখুন</h2><a href="<?= url('index.php?sort=new#products') ?>">সব পণ্য <?= icon('chevron', 16) ?></a></div>
  <div class="pgrid"><?php $mainProd = $prod; foreach ($related as $prod) { require APP_DIR . '/views/product_card.php'; } $prod = $mainProd; ?></div>
</section>
<?php endif; ?>

<?php if (!$owned): ?>
<div class="buy-bar">
  <div><small>মূল্য</small><b><?= $price > 0 ? money($price) : 'ফ্রি' ?></b></div>
  <form method="post" action="<?= url('cart.php') ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= (int)$prod['id'] ?>"><input type="hidden" name="go" value="checkout">
    <button class="btn btn-accent">এখনই কিনুন</button>
  </form>
</div>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
