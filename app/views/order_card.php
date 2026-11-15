<?php /** @var array $o @var array $items @var ?string $token */ ?>
<div class="card order-card">
  <div class="order-head">
    <div>
      <b>অর্ডার #<?= e($o['order_no']) ?></b>
      <span class="muted small"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?></span>
    </div>
    <span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span>
  </div>
  <table class="table">
    <?php foreach ($items as $it):
        $left = max(0, (int)$it['download_limit'] - (int)$it['download_count']); ?>
    <tr>
      <td><?= e($it['title']) ?></td>
      <td class="num"><?= money($it['price']) ?></td>
      <td class="num">
        <?php if ($o['status'] === 'paid' && $it['file_path'] && $left > 0): ?>
          <a class="btn btn-sm btn-accent" href="<?= url('download.php?item=' . (int)$it['id'] . ($token ? '&t=' . $token : '')) ?>">ডাউনলোড</a>
          <div class="muted small">আর <?= bn($left) ?> বার</div>
        <?php elseif ($o['status'] === 'paid' && $it['file_path']): ?>
          <span class="muted small">ডাউনলোড সীমা শেষ — সাপোর্টে যোগাযোগ করুন</span>
        <?php elseif ($o['status'] === 'paid'): ?>
          <span class="muted small">ফাইল শীঘ্রই যুক্ত হবে</span>
        <?php elseif ($o['status'] === 'pending'): ?>
          <span class="muted small">পেমেন্ট যাচাইয়ের অপেক্ষায়</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <div class="order-foot muted small">
    মোট <?= money($o['total']) ?>
    <?php if ($o['payment_method'] !== 'free'): ?> · <?= e(method_label($o['payment_method'])) ?> · TrxID <?= e($o['trx_id']) ?><?php endif; ?>
    <?php if ($o['admin_note']): ?><div class="note">নোট: <?= e($o['admin_note']) ?></div><?php endif; ?>
  </div>
</div>
