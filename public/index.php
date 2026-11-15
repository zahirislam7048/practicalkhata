<?php
require __DIR__ . '/_init.php';

$search = trim((string)($_GET['q'] ?? ''));
$where = 'is_active = 1';
$params = [];
if ($search !== '') {
    $where .= ' AND (title LIKE ? OR short_desc LIKE ?)';
    $params = ["%$search%", "%$search%"];
}
$total = (int)val("SELECT COUNT(*) FROM products WHERE $where", $params);
$p = paginate($total, 12);
$products = rows("SELECT * FROM products WHERE $where ORDER BY created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);

require APP_DIR . '/views/header.php';
?>
<?php if ($search === '' && $p['page'] === 1): ?>
<section class="hero">
  <div>
    <h1><?= e(setting('tagline', 'পড়াশোনার ডিজিটাল খাতা ও নোট')) ?></h1>
    <p>কিনুন bKash বা Nagad দিয়ে — পেমেন্ট যাচাইয়ের পর সাথে সাথে আপনার অ্যাকাউন্ট থেকে ডাউনলোড করুন।</p>
    <a class="btn" href="#products">সব পণ্য দেখুন</a>
  </div>
  <ol class="steps">
    <li><b>১</b> পছন্দের খাতা কার্টে যোগ করুন</li>
    <li><b>২</b> bKash / Nagad এ Send Money করুন</li>
    <li><b>৩</b> Transaction ID দিয়ে অর্ডার করুন</li>
    <li><b>৪</b> যাচাইয়ের পর ডাউনলোড করুন</li>
  </ol>
</section>
<?php endif; ?>

<h2 id="products"><?= $search !== '' ? '“' . e($search) . '” এর ফলাফল (' . $total . ')' : 'সব পণ্য' ?></h2>
<?php if (!$products): ?>
  <p class="empty">কোনো পণ্য পাওয়া যায়নি।</p>
<?php else: ?>
<div class="grid">
  <?php foreach ($products as $prod): $price = product_price($prod); ?>
  <article class="card product-card">
    <a href="<?= url('product.php?slug=' . urlencode($prod['slug'])) ?>" class="thumb">
      <img src="<?= e(product_image($prod)) ?>" alt="<?= e($prod['title']) ?>" loading="lazy">
      <?php if ($price < (float)$prod['price']): ?><span class="ribbon">ছাড়</span><?php endif; ?>
    </a>
    <div class="card-body">
      <h3><a href="<?= url('product.php?slug=' . urlencode($prod['slug'])) ?>"><?= e($prod['title']) ?></a></h3>
      <?php if ($prod['short_desc']): ?><p class="muted small"><?= e($prod['short_desc']) ?></p><?php endif; ?>
      <div class="price-row">
        <span class="price"><?= $price > 0 ? money($price) : 'ফ্রি' ?></span>
        <?php if ($price < (float)$prod['price']): ?><s class="muted"><?= money($prod['price']) ?></s><?php endif; ?>
      </div>
      <form method="post" action="<?= url('cart.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
        <button class="btn btn-block">কার্টে যোগ করুন</button>
      </form>
    </div>
  </article>
  <?php endforeach; ?>
</div>
<?= page_links($p) ?>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
