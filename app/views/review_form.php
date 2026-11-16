<?php
/** @var int $rvItemId  @var ?string $rvToken  @var string $rvReturn  @var string $rvName */
?>
<form method="post" action="<?= url('review.php') ?>" class="review-form">
  <?= csrf_field() ?>
  <input type="hidden" name="item" value="<?= (int)$rvItemId ?>">
  <?php if (!empty($rvToken)): ?><input type="hidden" name="t" value="<?= e($rvToken) ?>"><?php endif; ?>
  <input type="hidden" name="return" value="<?= e($rvReturn) ?>">
  <div class="rating-input" role="radiogroup" aria-label="রেটিং">
    <?php for ($i = 5; $i >= 1; $i--): ?>
      <input type="radio" id="r<?= (int)$rvItemId ?>-<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
      <label for="r<?= (int)$rvItemId ?>-<?= $i ?>" title="<?= $i ?> স্টার">★</label>
    <?php endfor; ?>
  </div>
  <input name="name" maxlength="60" placeholder="আপনার নাম (সাইটে দেখাবে)" value="<?= e($rvName) ?>" required>
  <textarea name="comment" rows="3" maxlength="1000" placeholder="পণ্যটি কেমন লেগেছে? কোন অংশ সবচেয়ে কাজে লেগেছে?" required></textarea>
  <button class="btn btn-sm btn-accent">রিভিউ জমা দিন</button>
</form>
