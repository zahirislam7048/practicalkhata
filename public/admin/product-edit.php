<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = $id ? row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    redirect('admin/products.php');
}
$errors = [];

if (is_post()) {
    csrf_check();
    $data = [
        'title'          => input('title'),
        'short_desc'     => input('short_desc'),
        'description'    => input('description'),
        'price'          => input('price'),
        'sale_price'     => input('sale_price'),
        'download_limit' => input('download_limit', '5'),
        'prior_sales'    => (string)max(0, (int)input('prior_sales', '0')),
        'slug'           => input('slug'),
        'is_active'      => isset($_POST['is_active']) ? 1 : 0,
        'category_id'    => (int)input('category_id') ?: null,
    ];
    if ($data['title'] === '') $errors[] = 'পণ্যের নাম দিন।';
    if (!is_numeric($data['price']) || $data['price'] < 0) $errors[] = 'সঠিক দাম দিন।';
    if ($data['sale_price'] !== '' && (!is_numeric($data['sale_price']) || $data['sale_price'] < 0)) $errors[] = 'সঠিক অফার দাম দিন।';
    if (!ctype_digit($data['download_limit']) || (int)$data['download_limit'] < 1) $errors[] = 'ডাউনলোড সীমা কমপক্ষে ১ হতে হবে।';
    if (!has_upload('file') && empty($p['file_path']) && $data['is_active']) {
        $errors[] = 'ডিজিটাল ফাইল আপলোড না করে পণ্যটি চালু করা যাবে না। ফাইল দিন, অথবা "সাইটে দেখাও" টিক তুলে দিয়ে সেভ করুন।';
    }

    $image = $p['image'] ?? null;
    $file = ['path' => $p['file_path'] ?? null, 'name' => $p['file_name'] ?? null];
    if (!$errors) {
        try {
            if (has_upload('image')) {
                $newImage = save_image_upload($_FILES['image']);
                if ($image) @unlink(PUBLIC_DIR . '/uploads/' . basename($image));
                $image = $newImage;
            }
            if (has_upload('file')) {
                $newFile = save_product_file($_FILES['file']);
                if ($file['path']) @unlink(STORAGE_DIR . '/' . basename($file['path']));
                $file = $newFile;
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
    if (!$errors) {
        $params = [
            $data['title'], $data['short_desc'] ?: null, $data['description'] ?: null,
            $data['price'], $data['sale_price'] === '' ? null : $data['sale_price'],
            $image, $file['path'], $file['name'], (int)$data['download_limit'], $data['is_active'], $data['category_id'],
        ];
        if ($p) {
            q('UPDATE products SET title=?, short_desc=?, description=?, price=?, sale_price=?, image=?, file_path=?, file_name=?, download_limit=?, is_active=?, category_id=?, prior_sales=? WHERE id=?',
              [...$params, (int)$data['prior_sales'], $p['id']]);
            // Link follows the title (or the custom link typed by the admin); old link keeps redirecting
            $typed = $data['slug'] !== '' ? slugify($data['slug']) : '';
            if ($typed !== '' && $typed !== $p['slug']) {
                set_product_slug((int)$p['id'], unique_slug($data['slug'], (int)$p['id']));          // admin typed a new link
            } elseif ($data['title'] !== $p['title'] || $data['slug'] === '') {
                set_product_slug((int)$p['id'], unique_slug($data['title'], (int)$p['id']));         // link follows the title
            }
            flash('success', 'পণ্য আপডেট হয়েছে।');
        } else {
            q('INSERT INTO products (title, short_desc, description, price, sale_price, image, file_path, file_name, download_limit, is_active, category_id, prior_sales, slug) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
              [...$params, (int)$data['prior_sales'], unique_slug($data['slug'] !== '' ? $data['slug'] : $data['title'])]);
            flash('success', 'নতুন পণ্য যোগ হয়েছে।');
        }
        redirect('admin/products.php');
    }
    $p = array_merge($p ?? [], $data, ['image' => $image, 'file_name' => $file['name']]);
}

$title = $id ? 'পণ্য এডিট' : 'নতুন পণ্য';
require APP_DIR . '/views/admin_header.php';
$maxUpload = ini_get('upload_max_filesize');
?>
<p><a href="products.php">← সব পণ্য</a></p>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="card form" data-upload-progress>
  <?= csrf_field() ?>
  <label>পণ্যের নাম *<input name="title" value="<?= e($p['title'] ?? '') ?>" required></label>
  <label>লিংক (URL) <small class="muted">— খালি রাখলে নাম থেকে নিজে তৈরি হবে; নাম বদলালে লিংকও বদলাবে, পুরোনো লিংক নতুনটিতে নিয়ে যাবে</small>
    <div class="slug-row"><span class="muted"><?= e(($_SERVER['HTTP_HOST'] ?? '') . url('product/')) ?></span><input name="slug" value="<?= e(rawurldecode((string)($p['slug'] ?? ''))) ?>" placeholder="নাম থেকে তৈরি হবে"></div>
    <?php if (!empty($p['id']) && !empty($p['slug'])): ?><a class="small" href="<?= e(product_url($p)) ?>" target="_blank">পেজ দেখুন ↗</a><?php endif; ?>
  </label>
  <label>ক্যাটাগরি <small class="muted">(<a href="categories.php">ক্যাটাগরি যোগ/এডিট</a>)</small>
    <select name="category_id">
      <option value="">— কোনো ক্যাটাগরি নয় —</option>
      <?php foreach (categories() as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= (int)($p['category_id'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>><?= e(trim(($cat['icon'] ?? '') . ' ' . $cat['name'])) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>ছোট বিবরণ <small class="muted">(কার্ডে দেখাবে)</small><input name="short_desc" maxlength="300" value="<?= e($p['short_desc'] ?? '') ?>"></label>
  <label>বিস্তারিত বিবরণ<textarea name="description" rows="8"><?= e($p['description'] ?? '') ?></textarea></label>
  <div class="row3">
    <label>দাম (৳) *<input name="price" type="number" step="0.01" min="0" value="<?= e($p['price'] ?? '') ?>" required></label>
    <label>অফার দাম (৳)<input name="sale_price" type="number" step="0.01" min="0" value="<?= e($p['sale_price'] ?? '') ?>"></label>
    <label>ডাউনলোড সীমা<input name="download_limit" type="number" min="1" value="<?= e($p['download_limit'] ?? '5') ?>"></label>
  </div>
  <div class="row3">
    <label>আগের বিক্রি <small class="muted">(ওয়েবসাইটের বাইরে — Facebook/WhatsApp এ — আসলে যতগুলো বিক্রি হয়েছে)</small>
      <input name="prior_sales" type="number" min="0" value="<?= e((string)($p['prior_sales'] ?? '0')) ?>"></label>
  </div>
  <div class="row2">
    <label>কভার ছবি <small class="muted">(JPG/PNG/WEBP, ১৫MB পর্যন্ত — নিজে থেকে ছোট হয়ে যাবে)</small>
      <?php if (!empty($p['image'])): ?><img class="preview" src="<?= e(product_image($p)) ?>" alt=""><?php endif; ?>
      <input type="file" name="image" accept="image/*">
    </label>
    <label>ডিজিটাল ফাইল <?= $id ? '' : '*' ?> <small class="muted">(PDF/ZIP ইত্যাদি, সর্বোচ্চ <?= e($maxUpload) ?>)</small>
      <?php if (!empty($p['file_name'])): ?><div class="small">বর্তমান: <b><?= e($p['file_name']) ?></b> — নতুন দিলে বদলে যাবে</div><?php endif; ?>
      <input type="file" name="file">
    </label>
  </div>
  <label class="check"><input type="checkbox" name="is_active" <?= ($p['is_active'] ?? 1) ? 'checked' : '' ?>> সাইটে দেখাও</label>
  <div class="upload-progress" hidden>
    <div class="up-bar"><span></span></div>
    <div class="up-text small">আপলোড হচ্ছে… <b>0%</b> <span class="muted"></span></div>
  </div>
  <button class="btn">সেভ করুন</button>
</form>
<?php require APP_DIR . '/views/admin_footer.php';
