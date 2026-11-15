<?php
/** @var array $prod */
$cPrice = product_price($prod);
$cOff = discount_percent($prod);
$cLink = url('product.php?slug=' . urlencode($prod['slug']));
?>
<article class="pcard">
  <a href="<?= $cLink ?>" class="pcard-img">
    <img src="<?= e(product_image($prod)) ?>" alt="<?= e($prod['title']) ?>" loading="lazy">
    <?php if ($cOff): ?><span class="pcard-off">-<?= bn($cOff) ?>%</span><?php endif; ?>
    <?php if ($cPrice <= 0): ?><span class="pcard-off free">ফ্রি</span><?php endif; ?>
    <span class="pcard-type"><?= icon('file', 14) ?> <?= e($prod['file_name'] ? strtoupper(pathinfo($prod['file_name'], PATHINFO_EXTENSION)) : 'ডিজিটাল') ?></span>
  </a>
  <div class="pcard-body">
    <h3 class="pcard-title"><a href="<?= $cLink ?>"><?= e($prod['title']) ?></a></h3>
    <?php if (!empty($prod['sold']) && (int)$prod['sold'] > 0): ?>
      <div class="pcard-sold"><?= bn((int)$prod['sold']) ?> জন কিনেছেন</div>
    <?php endif; ?>
    <div class="pcard-price">
      <span class="now"><?= $cPrice > 0 ? money($cPrice) : 'ফ্রি' ?></span>
      <?php if ($cOff): ?><s><?= money($prod['price']) ?></s><?php endif; ?>
    </div>
    <div class="pcard-actions">
      <form method="post" action="<?= url('cart.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
        <input type="hidden" name="go" value="checkout">
        <button class="btn btn-buy">এখনই কিনুন</button>
      </form>
      <form method="post" action="<?= url('cart.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$prod['id'] ?>">
        <button class="btn-icon" title="কার্টে যোগ করুন" aria-label="কার্টে যোগ করুন"><?= icon('cart', 18) ?></button>
      </form>
    </div>
  </div>
</article>
