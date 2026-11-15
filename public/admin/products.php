<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)input('id');
    if (input('action') === 'toggle') {
        q('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$id]);
    } elseif (input('action') === 'delete') {
        $p = row('SELECT * FROM products WHERE id = ?', [$id]);
        if ($p) {
            $sold = (int)val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]);
            if ($sold) {
                // Keep the file so past buyers can still download it
                q('UPDATE products SET is_active = 0 WHERE id = ?', [$id]);
                flash('info', 'এই পণ্য আগে বিক্রি হয়েছে, তাই মুছে না ফেলে লুকিয়ে রাখা হয়েছে (ক্রেতারা ডাউনলোড করতে পারবে)।');
            } else {
                q('DELETE FROM products WHERE id = ?', [$id]);
                if ($p['image']) @unlink(PUBLIC_DIR . '/uploads/' . basename($p['image']));
                if ($p['file_path']) @unlink(STORAGE_DIR . '/' . basename($p['file_path']));
                flash('success', 'পণ্য মুছে ফেলা হয়েছে।');
            }
        }
    }
    redirect('admin/products.php');
}

$title = 'পণ্য';
require APP_DIR . '/views/admin_header.php';
$products = rows("SELECT p.*, (SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id
                  WHERE oi.product_id = p.id AND o.status = 'paid') sold FROM products p ORDER BY p.created_at DESC");
?>
<p><a class="btn" href="product-edit.php">+ নতুন পণ্য</a></p>
<div class="card">
<table class="table">
  <tr><th></th><th>নাম</th><th class="num">দাম</th><th>ফাইল</th><th class="num">বিক্রি</th><th>অবস্থা</th><th></th></tr>
  <?php foreach ($products as $p): ?>
  <tr>
    <td class="w-thumb"><img src="<?= e(product_image($p)) ?>" alt=""></td>
    <td><a href="product-edit.php?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></td>
    <td class="num"><?= money(product_price($p)) ?><?php if (product_price($p) < (float)$p['price']): ?><div class="muted small"><s><?= money($p['price']) ?></s></div><?php endif; ?></td>
    <td class="small"><?= $p['file_name'] ? e($p['file_name']) : '<span class="danger">ফাইল নেই</span>' ?></td>
    <td class="num"><?= (int)$p['sold'] ?></td>
    <td>
      <form method="post" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="status <?= $p['is_active'] ? 'status-paid' : 'status-rejected' ?>" name="action" value="toggle" title="চাপ দিয়ে বদলান">
          <?= $p['is_active'] ? 'চালু' : 'লুকানো' ?></button>
      </form>
    </td>
    <td class="num">
      <a href="product-edit.php?id=<?= (int)$p['id'] ?>">এডিট</a>
      <form method="post" class="inline"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="link-btn danger" name="action" value="delete" data-confirm="পণ্যটি মুছে ফেলবেন?">মুছুন</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$products): ?><tr><td colspan="7" class="muted">এখনো কোনো পণ্য নেই।</td></tr><?php endif; ?>
</table>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
