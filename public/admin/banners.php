<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)input('id');
    $action = input('action');
    if ($action === 'delete') {
        $b = row('SELECT image FROM banners WHERE id = ?', [$id]);
        if ($b) {
            @unlink(PUBLIC_DIR . '/uploads/' . basename($b['image']));
            q('DELETE FROM banners WHERE id = ?', [$id]);
        }
        flash('success', 'ব্যানার মুছে ফেলা হয়েছে।');
    } elseif ($action === 'toggle') {
        q('UPDATE banners SET is_active = 1 - is_active WHERE id = ?', [$id]);
    } elseif ($action === 'add') {
        try {
            if (!has_upload('image')) {
                throw new RuntimeException('ব্যানারের ছবি দিন।');
            }
            $img = save_image_upload($_FILES['image']);
            q('INSERT INTO banners (image, title, link, sort_order) VALUES (?,?,?,?)',
              [$img, input('title') ?: null, input('link') ?: null, (int)input('sort_order')]);
            flash('success', 'ব্যানার যোগ হয়েছে।');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
    }
    redirect('admin/banners.php');
}

$title = 'ব্যানার স্লাইডার';
require APP_DIR . '/views/admin_header.php';
$banners = rows('SELECT * FROM banners ORDER BY sort_order, id');
?>
<p class="muted">হোম পেজের উপরের স্লাইডারে এই ছবিগুলো ঘুরবে। সাইজ: <b>১২০০ × ৪৫০ পিক্সেল</b> (বা একই অনুপাত)। কোনো ব্যানার না থাকলে অফারের পণ্য দিয়ে স্বয়ংক্রিয় ব্যানার দেখাবে।</p>
<div class="two-col">
  <section class="card">
    <?php foreach ($banners as $b): ?>
      <div class="banner-row">
        <img src="<?= url('uploads/' . $b['image']) ?>" alt="">
        <div>
          <b><?= e($b['title'] ?: 'শিরোনাম নেই') ?></b>
          <div class="muted small"><?= e($b['link'] ?: 'লিংক নেই') ?> · ক্রম <?= (int)$b['sort_order'] ?></div>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="status <?= $b['is_active'] ? 'status-paid' : 'status-rejected' ?>" name="action" value="toggle"><?= $b['is_active'] ? 'চালু' : 'বন্ধ' ?></button>
            <button class="link-btn danger" name="action" value="delete" data-confirm="ব্যানারটি মুছবেন?">মুছুন</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$banners): ?><p class="muted">এখনো কোনো ব্যানার নেই।</p><?php endif; ?>
  </section>
  <form method="post" enctype="multipart/form-data" class="card form" data-upload-progress>
    <h3>নতুন ব্যানার</h3>
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <label>ছবি (JPG/PNG/WEBP)<input type="file" name="image" accept="image/*" required></label>
    <label>শিরোনাম (ঐচ্ছিক)<input name="title"></label>
    <label>লিংক (ঐচ্ছিক) <small class="muted">যেমন /product/পণ্যের-নাম বা /index.php?offer=1</small><input name="link"></label>
    <label>ক্রম<input name="sort_order" type="number" value="<?= count($banners) ?>"></label>
    <div class="upload-progress" hidden><div class="up-bar"><span></span></div><div class="up-text small">আপলোড হচ্ছে… <b>0%</b> <span class="muted"></span></div></div>
    <button class="btn">যোগ করুন</button>
  </form>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
