<?php
require __DIR__ . '/_init.php';

$search = trim((string)($_GET['q'] ?? ''));
$offer = !empty($_GET['offer']);
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
$total = (int)val("SELECT COUNT(*) FROM products p WHERE p.is_active = 1 AND ($where)", $params);
$pg = paginate($total, 12);
$products = shop_products($where, $params, $sorts[$sort][1], $pg['limit'], $pg['offset']);

$isHome = $search === '' && !$offer && !isset($_GET['sort']) && $pg['page'] === 1;
if ($isHome) {
    $deals = shop_products('p.sale_price IS NOT NULL AND p.sale_price < p.price', [], 'p.updated_at DESC', 4);
    $popular = shop_products("EXISTS (SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status = 'paid')", [], 'sold DESC', 4);
    $featured = $deals[0] ?? ($products[0] ?? null);
}

$heading = $search !== '' ? '“' . $search . '” এর ফলাফল' : ($offer ? 'অফার চলছে' : 'সব পণ্য');
require APP_DIR . '/views/header.php';
?>
<?php if ($isHome): ?>
<section class="hero">
  <div class="hero-text">
    <span class="hero-kicker"><?= icon('zap', 16) ?> ডিজিটাল খাতা • নোট • গাইড</span>
    <h1>পড়াশোনা ও দক্ষতার <span class="accent">প্র্যাকটিক্যাল খাতা</span> এখন এক ক্লিকে</h1>
    <p>bKash বা Nagad এ পেমেন্ট করুন — অ্যাকাউন্ট ছাড়াই কিনুন, যাচাইয়ের পর সাথে সাথে ডাউনলোড।</p>
    <div class="hero-cta">
      <a class="btn btn-accent btn-lg" href="#products">কেনাকাটা শুরু করুন</a>
      <?php if ($deals): ?><a class="btn btn-ghost btn-lg" href="<?= url('index.php?offer=1#products') ?>">অফার দেখুন</a><?php endif; ?>
    </div>
  </div>
  <?php if ($featured): $fp = product_price($featured); $foff = discount_percent($featured); ?>
  <a class="hero-product" href="<?= url('product.php?slug=' . urlencode($featured['slug'])) ?>">
    <img src="<?= e(product_image($featured)) ?>" alt="<?= e($featured['title']) ?>">
    <div class="hero-product-info">
      <?php if ($foff): ?><span class="pcard-off static">-<?= bn($foff) ?>%</span><?php endif; ?>
      <b><?= e($featured['title']) ?></b>
      <span class="hp-price"><?= $fp > 0 ? money($fp) : 'ফ্রি' ?> <?php if ($foff): ?><s><?= money($featured['price']) ?></s><?php endif; ?></span>
    </div>
  </a>
  <?php endif; ?>
</section>

<section class="trust-bar">
  <div><?= icon('zap', 24) ?><span><b>সাথে সাথে ডাউনলোড</b>যাচাইয়ের পরই</span></div>
  <div><?= icon('wallet', 24) ?><span><b>bKash / Nagad</b>সহজ পেমেন্ট</span></div>
  <div><?= icon('shield', 24) ?><span><b>১০০% নিরাপদ</b>ফাইল শুধু আপনার</span></div>
  <div><?= icon('support', 24) ?><span><b>সাপোর্ট</b>যেকোনো প্রয়োজনে</span></div>
</section>

<?php if ($deals): ?>
<section class="shelf">
  <div class="shelf-head"><h2><?= icon('tag', 22) ?> অফার চলছে</h2><a href="<?= url('index.php?offer=1#products') ?>">সব অফার <?= icon('chevron', 16) ?></a></div>
  <div class="pgrid"><?php foreach ($deals as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
</section>
<?php endif; ?>

<?php if ($popular): ?>
<section class="shelf">
  <div class="shelf-head"><h2>🔥 সবচেয়ে জনপ্রিয়</h2><a href="<?= url('index.php?sort=popular#products') ?>">আরও দেখুন <?= icon('chevron', 16) ?></a></div>
  <div class="pgrid"><?php foreach ($popular as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
</section>
<?php endif; ?>
<?php endif; ?>

<section class="shelf" id="products">
  <div class="shelf-head">
    <h2><?= e($heading) ?> <small class="muted">(<?= bn($total) ?>টি)</small></h2>
    <form method="get" class="sort-form">
      <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
      <?php if ($offer): ?><input type="hidden" name="offer" value="1"><?php endif; ?>
      <label>সাজান
        <select name="sort" onchange="this.form.submit()">
          <?php foreach ($sorts as $k => [$label]): ?>
            <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </div>
  <?php if (!$products): ?>
    <div class="empty">
      <?= icon('search', 40) ?>
      <p>কোনো পণ্য পাওয়া যায়নি।</p>
      <a class="btn" href="<?= url() ?>">সব পণ্য দেখুন</a>
    </div>
  <?php else: ?>
    <div class="pgrid"><?php foreach ($products as $prod) require APP_DIR . '/views/product_card.php'; ?></div>
    <?= page_links($pg) ?>
  <?php endif; ?>
</section>
<?php require APP_DIR . '/views/footer.php';
