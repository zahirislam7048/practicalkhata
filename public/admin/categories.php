<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

$editId = (int)($_GET['edit'] ?? 0);
if (is_post()) {
    csrf_check();
    $id = (int)input('id');
    if (input('action') === 'delete') {
        q('UPDATE products SET category_id = NULL WHERE category_id = ?', [$id]);
        q('DELETE FROM categories WHERE id = ?', [$id]);
        flash('success', 'ক্যাটাগরি মুছে ফেলা হয়েছে (পণ্যগুলো থেকে যাবে)।');
        redirect('admin/categories.php');
    }
    $name = input('name');
    if ($name === '') {
        flash('error', 'ক্যাটাগরির নাম দিন।');
        redirect('admin/categories.php' . ($id ? '?edit=' . $id : ''));
    }
    $slug = slugify($name);
    $i = 2;
    while (val('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $id])) {
        $slug = slugify($name) . '-' . $i++;
    }
    $params = [$name, $slug, mb_substr(input('icon'), 0, 8) ?: null, (int)input('sort_order')];
    if ($id) {
        q('UPDATE categories SET name = ?, slug = ?, icon = ?, sort_order = ? WHERE id = ?', [...$params, $id]);
    } else {
        q('INSERT INTO categories (name, slug, icon, sort_order) VALUES (?,?,?,?)', $params);
    }
    flash('success', 'ক্যাটাগরি সেভ হয়েছে।');
    redirect('admin/categories.php');
}

$title = 'ক্যাটাগরি';
require APP_DIR . '/views/admin_header.php';
$cats = categories();
$c = $editId ? row('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
?>
<div class="two-col">
  <section class="card">
    <table class="table">
      <tr><th>আইকন</th><th>নাম</th><th class="num">পণ্য</th><th class="num">ক্রম</th><th></th></tr>
      <?php foreach ($cats as $cat): ?>
      <tr>
        <td style="font-size:1.5rem"><?= e((string)$cat['icon']) ?></td>
        <td><a href="<?= url('index.php?cat=' . urlencode($cat['slug'])) ?>" target="_blank"><?= e($cat['name']) ?></a></td>
        <td class="num"><?= (int)$cat['n'] ?></td>
        <td class="num"><?= (int)$cat['sort_order'] ?></td>
        <td class="num"><a href="?edit=<?= (int)$cat['id'] ?>">এডিট</a>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
          <button class="link-btn danger" name="action" value="delete" data-confirm="ক্যাটাগরিটি মুছবেন?">মুছুন</button></form></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$cats): ?><tr><td colspan="5" class="muted">এখনো কোনো ক্যাটাগরি নেই।</td></tr><?php endif; ?>
    </table>
  </section>
  <form method="post" class="card form">
    <h3><?= $c ? 'ক্যাটাগরি এডিট' : 'নতুন ক্যাটাগরি' ?></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($c['id'] ?? 0) ?>">
    <label>নাম<input name="name" value="<?= e($c['name'] ?? '') ?>" required placeholder="যেমন: HSC প্র্যাকটিক্যাল"></label>
    <div class="row2">
      <label>আইকন (ইমোজি)<input name="icon" value="<?= e($c['icon'] ?? '') ?>" placeholder="📒" maxlength="8"></label>
      <label>ক্রম (ছোট সংখ্যা আগে)<input name="sort_order" type="number" value="<?= e((string)($c['sort_order'] ?? count($cats))) ?>"></label>
    </div>
    <div class="btn-row"><button class="btn">সেভ করুন</button><?php if ($c): ?><a class="btn btn-outline" href="categories.php">বাতিল</a><?php endif; ?></div>
  </form>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
