<?php
/** @var array $prod */
$cPrice = product_price($prod);
$cOff = discount_percent($prod);
$cLink = product_url($prod);
?>
<article class="pcard">
  <a href="<?= $cLink ?>" class="pcard-img">
    <img src="<?= e(product_image($prod, 'thumb')) ?>" alt="<?= e($prod['title']) ?>" loading="lazy" decoding="async" width="300" height="300">
    <?php if ($cOff): ?><span class="pcard-off">-<?= bn($cOff) ?>%</span><?php endif; ?>
    <?php if ($cPrice <= 0): ?><span class="pcard-off free">ফ্রি</span><?php endif; ?>
  </a>
  <div class="pcard-body">
    <h3 class="pcard-title"><a href="<?= $cLink ?>"><?= e($prod['title']) ?></a></h3>
    <?php if (!empty($prod['rating_count'])): ?>
      <div class="pcard-rating"><?= stars_html((float)$prod['rating_avg'], 13) ?> <span>(<?= bn((int)$prod['rating_count']) ?>)</span></div>
    <?php endif; ?>
    <div class="pcard-price">
      <span class="now"><?= $cPrice > 0 ? money($cPrice) : 'ফ্রি' ?></span>
      <?php if ($cOff): ?><s><?= money($prod['price']) ?></s><?php endif; ?>
    </div>
    <div class="pcard-meta">
      <span><?= icon('file', 12) ?> <?= e($prod['file_name'] ? strtoupper(pathinfo($prod['file_name'], PATHINFO_EXTENSION)) : 'ডিজিটাল') ?></span>
      <?php if (!empty($prod['sold']) && (int)$prod['sold'] > 0): ?><span><?= bn((int)$prod['sold']) ?> বিক্রি</span><?php endif; ?>
    </div>
    <form method="post" action="<?= url('cart.php') ?>" class="pcard-add">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
      <button class="btn-add"><?= icon('cart', 15) ?> কার্টে যোগ করুন</button>
    </form>
  </div>
</article>
