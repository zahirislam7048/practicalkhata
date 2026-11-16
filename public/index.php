<?php
require __DIR__ . '/_init.php';

$search = trim((string)($_GET['q'] ?? ''));
$offer = !empty($_GET['offer']);
$cat = null;
if (!empty($_GET['cat'])) {
    foreach (categories() as $c) {
        if ($c['slug'] === $_GET['cat']) {
            $cat = $c;
        }
    }
}
$sorts = [
    'new'        => ['নতুন আগে', 'p.created_at DESC'],
    'popular'    => ['জনপ্রিয়', 'sold DESC, p.created_at DESC'],
    'price_asc'  => ['দাম: কম থেকে বেশি', 'COALESCE(LEAST(p.sale_price, p.price), p.price) ASC'],
    'price_desc' => ['দাম: বেশি থেকে কম', 'COALESCE(LEAST(p.sale_price, p.price), p.price) DESC'],
];
$sort = isset($sorts[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'new';

$where = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (p.title LIKE ? OR p.short_desc LIKE ? OR p.description LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%");
}
if ($offer) {
    $where .= ' AND p.sale_price IS NOT NULL AND p.sale_price < p.price';
}
if ($cat) {
    $where .= ' AND p.category_id = ?';
    $params[] = $cat['id'];
}
$total = (int)val("SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND ($where)", $params);
$pg = paginate($total, 24);
$products = shop_products($where, $params, $sorts[$sort][1], $pg['limit'], $pg['offset']);

$isHome = $search === '' && !$offer && !$cat && !isset($_GET['sort']) && $pg['page'] === 1;
if ($isHome) {
    $banners = rows('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order, id');
    $deals = shop_products('p.sale_price IS NOT NULL AND p.sale_price < p.price', [], 'p.updated_at DESC', 12);
    $newest = shop_products('1=1', [], 'p.created_at DESC', 12);
    $popular = shop_products("EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status = 'paid')", [], 'sold DESC', 6);
    // Auto slides from offer/new products when the admin hasn't uploaded banners yet
    $autoSlides = $banners ? [] : array_slice($deals ?: $newest, 0, 3);
    $flashEnd = flash_sale_end();
}

$heading = $search !== '' ? '“' . $search . '” এর ফলাফল' : ($cat ? $cat['name'] : ($offer ? 'অফার চলছে' : ($sort === 'popular' ? 'জনপ্রিয় পণ্য' : 'সব পণ্য')));
$title = $isHome ? null : $heading;
require APP_DIR . '/views/header.php';
?>
<?php if ($isHome): ?>
<section class="home-top">
  <aside class="side-cats">
    <h3><?= icon('menu', 16) ?> ক্যাটাগরি</h3>
    <?php foreach (categories() as $c): ?>
      <a href="<?= url('index.php?cat=' . urlencode($c['slug'])) ?>"><span><?= e((string)$c['icon']) ?></span><?= e($c['name']) ?><?= icon('chevron', 14) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('index.php?offer=1#products') ?>"><span>🔥</span>সব অফার<?= icon('chevron', 14) ?></a>
  </aside>

  <div class="slider" data-slider>
    <div class="slides">
      <?php foreach ($banners as $b): ?>
        <a class="slide" href="<?= e($b['link'] ?: '#products') ?>"><img src="<?= url('uploads/' . $b['image']) ?>" alt="<?= e((string)$b['title']) ?>" width="1200" height="450" <?= $b === $banners[0] ? 'fetchpriority="high"' : 'loading="lazy"' ?>></a>
      <?php endforeach; ?>
      <?php foreach ($autoSlides as $i => $s): $sp = product_price($s); $so = discount_percent($s); ?>
        <a class="slide auto-slide v<?= $i % 3 ?>" href="<?= url('product.php?slug=' . urlencode($s['slug'])) ?>">
          <div class="as-text">
            <span class="as-kicker"><?= $so ? '🔥 ' . bn($so) . '% ছাড়' : '✨ নতুন এসেছে' ?></span>
            <h2><?= e($s['title']) ?></h2>
            <p><?= e(mb_strimwidth((string)$s['short_desc'], 0, 90, '…')) ?></p>
            <span class="as-price"><?= $sp > 0 ? money($sp) : 'ফ্রি' ?> <?php if ($so): ?><s><?= money($s['price']) ?></s><?php endif; ?></span>
            <span class="btn btn-accent">এখনই কিনুন</span>
          </div>
          <img src="<?= e(product_image($s, 'thumb')) ?>" alt="" width="420" height="315" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
        </a>
      <?php endforeach; ?>
      <?php if (!$banners && !$autoSlides): ?>
        <div class="slide auto-slide v0"><div class="as-text"><h2><?= e(setting('tagline', 'পড়াশোনার ডিজিটাল খাতা ও নোট')) ?></h2><p>শীঘ্রই নতুন পণ্য আসছে।</p></div></div>
      <?php endif; ?>
    </div>
    <?php if (count($banners) + count($autoSlides) > 1): ?>
      <button class="sl-btn prev" type="button" aria-label="আগের">‹</button>
      <button class="sl-btn next" type="button" aria-label="পরের">›</button>
      <div class="sl-dots"></div>
    <?php endif; ?>
  </div>

  <aside class="side-promos">
    <a href="<?= url('index.php?offer=1#products') ?>" class="promo p1"><b>🔥 আজকের অফার</b><span>সেরা দামে ডিজিটাল খাতা</span></a>
    <a href="<?= url('track.php') ?>" class="promo p2"><b>📦 অর্ডার ট্র্যাক</b><span>অর্ডার নম্বর দিয়ে ডাউনলোড</span></a>
    <div class="promo p3"><b>💳 bKash / Nagad</b><span>সহজ পেমেন্ট, দ্রুত যাচাই</span></div>
  </aside>
</section>

<?php if (categories()): ?>
<section class="cat-icons">
  <?php foreach (categories() as $c): ?>
    <a href="<?= url('index.php?cat=' . urlencode($c['slug'])) ?>"><span><?= e((string)($c['icon'] ?: '📁')) ?></span><b><?= e($c['name']) ?></b></a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($deals): ?>
<section class="block flash">
  <div class="block-head">
    <h2>⚡ ফ্ল্যাশ সেল</h2>
    <?php if ($flashEnd): ?>
      <div class="countdown" data-end="<?= $flashEnd * 1000 ?>"><small>শেষ হবে</small><span data-d-wrap hidden><b data-d>0</b> দিন</span><b data-h>00</b>:<b data-m>00</b>:<b data-s>00</b></div>
    <?php else: ?>
      <span class="flash-tag">সীমিত সময়ের অফার</span>
    <?php endif; ?>
    <a href="<?= url('index.php?offer=1#products') ?>" class="see-all">সব দেখুন <?= icon('chevron', 14) ?></a>
  </div>
  <div class="pgrid"><?php foreach (array_slice($deals, 0, 6) as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
</section>
<?php endif; ?>

<?php if ($popular): ?>
<section class="block">
  <div class="block-head"><h2>🏆 সবচেয়ে বেশি বিক্রি</h2><a href="<?= url('index.php?sort=popular#products') ?>" class="see-all">সব দেখুন <?= icon('chevron', 14) ?></a></div>
  <div class="pgrid"><?php foreach ($popular as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
</section>
<?php endif; ?>

<?php foreach (categories() as $c):
    if (!(int)$c['n']) continue;
    $catProducts = shop_products('p.category_id = ?', [$c['id']], 'p.created_at DESC', 6); ?>
<section class="block">
  <div class="block-head"><h2><?= e((string)$c['icon']) ?> <?= e($c['name']) ?></h2><a href="<?= url('index.php?cat=' . urlencode($c['slug'])) ?>" class="see-all">সব দেখুন <?= icon('chevron', 14) ?></a></div>
  <div class="pgrid"><?php foreach ($catProducts as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
</section>
<?php endforeach; ?>

<section class="block" id="products">
  <div class="block-head"><h2>🛍️ সব পণ্য</h2><a href="<?= url('index.php?sort=new#products') ?>" class="see-all">সব দেখুন <?= icon('chevron', 14) ?></a></div>
  <?php if (!$newest): ?>
    <div class="empty"><?= icon('search', 40) ?><p>শীঘ্রই নতুন পণ্য আসছে।</p></div>
  <?php else: ?>
    <div class="pgrid"><?php foreach ($newest as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
  <?php endif; ?>
</section>

<?php $homeReviews = latest_reviews(10); if ($homeReviews): ?>
<section class="block testimonials">
  <div class="block-head"><h2>💬 কাস্টমাররা যা বলছেন</h2>
    <div class="tm-nav"><button type="button" class="tm-prev" aria-label="আগের">‹</button><button type="button" class="tm-next" aria-label="পরের">›</button></div>
  </div>
  <div class="tm-track" data-testimonials>
    <?php foreach ($homeReviews as $r): ?>
    <figure class="tm-card">
      <?= stars_html((float)$r['rating'], 16) ?>
      <blockquote>“<?= e(mb_strimwidth($r['comment'], 0, 220, '…')) ?>”</blockquote>
      <figcaption>
        <span class="rv-avatar"><?= e(mb_substr($r['name'], 0, 1)) ?></span>
        <span><b><?= e($r['name']) ?></b> <small class="rv-verified<?= $r['order_item_id'] ? '' : ' rv-collected' ?>"><?= e(review_badge($r)) ?></small>
          <a href="<?= url('product.php?slug=' . urlencode($r['slug'])) ?>#reviews"><?= e($r['title']) ?></a></span>
      </figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="trust-bar">
  <div><?= icon('zap', 24) ?><span><b>সাথে সাথে ডাউনলোড</b>যাচাইয়ের পরই</span></div>
  <div><?= icon('wallet', 24) ?><span><b>bKash / Nagad</b>সহজ পেমেন্ট</span></div>
  <div><?= icon('user', 24) ?><span><b>অ্যাকাউন্ট ছাড়াই কিনুন</b>শুধু নাম ও মোবাইল</span></div>
  <div><?= icon('support', 24) ?><span><b>সাপোর্ট</b>যেকোনো প্রয়োজনে</span></div>
</section>

<?php else: ?>
<nav class="crumbs"><a href="<?= url() ?>">হোম</a> <?= icon('chevron', 14) ?> <span><?= e($heading) ?></span></nav>
<div class="listing">
  <aside class="filter-side">
    <h3>ক্যাটাগরি</h3>
    <a href="<?= url('index.php?' . http_build_query(array_filter(['q' => $search, 'offer' => $offer ? 1 : null, 'sort' => $_GET['sort'] ?? null]))) ?>" class="<?= !$cat ? 'active' : '' ?>">সব ক্যাটাগরি</a>
    <?php foreach (categories() as $c): ?>
      <a href="<?= url('index.php?' . http_build_query(array_filter(['cat' => $c['slug'], 'q' => $search, 'offer' => $offer ? 1 : null, 'sort' => $_GET['sort'] ?? null]))) ?>" class="<?= $cat && $cat['id'] === $c['id'] ? 'active' : '' ?>"><?= e((string)$c['icon']) ?> <?= e($c['name']) ?> <small><?= bn((int)$c['n']) ?></small></a>
    <?php endforeach; ?>
    <h3>অফার</h3>
    <a href="<?= url('index.php?' . http_build_query(array_filter(['cat' => $cat['slug'] ?? null, 'q' => $search, 'offer' => $offer ? null : 1]))) ?>" class="<?= $offer ? 'active' : '' ?>"><?= $offer ? '☑' : '☐' ?> শুধু ছাড়ের পণ্য</a>
  </aside>
  <section class="block" id="products">
    <div class="block-head">
      <h2><?= e($heading) ?> <small class="muted">(<?= bn($total) ?>টি)</small></h2>
      <form method="get" class="sort-form">
        <?php foreach (['q' => $search, 'offer' => $offer ? '1' : '', 'cat' => $cat['slug'] ?? ''] as $hk => $hv): if ($hv !== ''): ?><input type="hidden" name="<?= $hk ?>" value="<?= e($hv) ?>"><?php endif; endforeach; ?>
        <label>সাজান
          <select name="sort" onchange="this.form.submit()">
            <?php foreach ($sorts as $k => [$label]): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
          </select>
        </label>
      </form>
    </div>
    <?php if (!$products): ?>
      <div class="empty"><?= icon('search', 40) ?><p>কোনো পণ্য পাওয়া যায়নি।</p><a class="btn" href="<?= url() ?>">হোমে ফিরে যান</a></div>
    <?php else: ?>
      <div class="pgrid pgrid-5"><?php foreach ($products as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
      <?= page_links($pg) ?>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
