<?php
/** @var string $returnTo  'cart' or 'checkout' */
$ups = upsell_products(4);
if (!$ups) return;
?>
<section class="upsell card">
  <h3>🛍️ এগুলোও নিতে পারেন</h3>
  <div class="upsell-list">
    <?php foreach ($ups as $up): $upPrice = product_price($up); $upOff = discount_percent($up); ?>
    <div class="upsell-item">
      <a href="<?= url('product.php?slug=' . urlencode($up['slug'])) ?>" class="ui-img"><img src="<?= e(product_image($up, 'thumb')) ?>" alt="" loading="lazy" width="80" height="80"></a>
      <div class="ui-info">
        <a href="<?= url('product.php?slug=' . urlencode($up['slug'])) ?>" class="ui-title"><?= e($up['title']) ?></a>
        <div class="ui-price"><b><?= $upPrice > 0 ? money($upPrice) : 'ফ্রি' ?></b><?php if ($upOff): ?> <s><?= money($up['price']) ?></s> <span class="ui-off">-<?= bn($upOff) ?>%</span><?php endif; ?></div>
      </div>
      <form method="post" action="<?= url('cart.php') ?>" class="ui-add">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$up['id'] ?>">
        <input type="hidden" name="go" value="<?= $returnTo === 'checkout' ? 'checkout' : 'cart' ?>">
        <button class="btn btn-sm btn-accent" name="upsell" value="1">+ যোগ করুন</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</section>
