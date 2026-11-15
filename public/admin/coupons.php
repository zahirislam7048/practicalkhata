<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

$editId = (int)($_GET['edit'] ?? 0);
$errors = [];
if (is_post()) {
    csrf_check();
    $action = input('action');
    $id = (int)input('id');
    if ($action === 'delete') {
        q('DELETE FROM coupons WHERE id = ?', [$id]);
        flash('success', 'কুপন মুছে ফেলা হয়েছে।');
        redirect('admin/coupons.php');
    }
    if ($action === 'toggle') {
        q('UPDATE coupons SET is_active = 1 - is_active WHERE id = ?', [$id]);
        redirect('admin/coupons.php');
    }
    // save
    $code = strtoupper(preg_replace('/\s+/', '', input('code')));
    $type = input('type') === 'fixed' ? 'fixed' : 'percent';
    $value = input('value');
    $min = input('min_amount', '0') ?: '0';
    $max = input('max_uses');
    $exp = input('expires_at');
    if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) $errors[] = 'কোড ৩-৫০ অক্ষরের হতে হবে (A-Z, 0-9, - , _)।';
    if (!is_numeric($value) || $value <= 0) $errors[] = 'ছাড়ের পরিমাণ সঠিক নয়।';
    if ($type === 'percent' && is_numeric($value) && $value > 100) $errors[] = 'শতাংশ ১০০ এর বেশি হতে পারে না।';
    if (!is_numeric($min) || $min < 0) $errors[] = 'সর্বনিম্ন কেনাকাটা সঠিক নয়।';
    if ($max !== '' && !ctype_digit($max)) $errors[] = 'ব্যবহার সীমা একটি সংখ্যা হতে হবে।';
    if (!$errors && val('SELECT id FROM coupons WHERE code = ? AND id <> ?', [$code, $id])) $errors[] = 'এই কোড আগেই আছে।';
    if (!$errors) {
        $params = [$code, $type, $value, $min, $max === '' ? null : (int)$max, $exp === '' ? null : date('Y-m-d 23:59:59', strtotime($exp)), isset($_POST['is_active']) ? 1 : 0];
        if ($id) {
            q('UPDATE coupons SET code=?, type=?, value=?, min_amount=?, max_uses=?, expires_at=?, is_active=? WHERE id=?', [...$params, $id]);
        } else {
            q('INSERT INTO coupons (code, type, value, min_amount, max_uses, expires_at, is_active) VALUES (?,?,?,?,?,?,?)', $params);
        }
        flash('success', 'কুপন সেভ হয়েছে।');
        redirect('admin/coupons.php');
    }
    $editId = $id;
}

$title = 'কুপন';
require APP_DIR . '/views/admin_header.php';
$c = $editId ? row('SELECT * FROM coupons WHERE id = ?', [$editId]) : null;
if ($errors) {
    $c = array_merge($c ?? [], $_POST);
}
$coupons = rows('SELECT * FROM coupons ORDER BY created_at DESC');
?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
<div class="two-col">
  <section class="card">
    <table class="table">
      <tr><th>কোড</th><th>ছাড়</th><th>ব্যবহার</th><th>মেয়াদ</th><th>অবস্থা</th><th></th></tr>
      <?php foreach ($coupons as $cp): $expired = $cp['expires_at'] && strtotime($cp['expires_at']) < time(); ?>
      <tr>
        <td><code><?= e($cp['code']) ?></code><?php if ((float)$cp['min_amount'] > 0): ?><div class="muted small">ন্যূনতম <?= money($cp['min_amount']) ?></div><?php endif; ?></td>
        <td><?= $cp['type'] === 'percent' ? e((float)$cp['value']) . '%' : money($cp['value']) ?></td>
        <td><?= (int)$cp['used_count'] ?><?= $cp['max_uses'] !== null ? '/' . (int)$cp['max_uses'] : '' ?></td>
        <td class="small <?= $expired ? 'danger' : '' ?>"><?= $cp['expires_at'] ? e(date('d M Y', strtotime($cp['expires_at']))) : '—' ?></td>
        <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$cp['id'] ?>">
          <button class="status <?= $cp['is_active'] ? 'status-paid' : 'status-rejected' ?>" name="action" value="toggle"><?= $cp['is_active'] ? 'চালু' : 'বন্ধ' ?></button></form></td>
        <td class="num"><a href="?edit=<?= (int)$cp['id'] ?>">এডিট</a>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$cp['id'] ?>">
          <button class="link-btn danger" name="action" value="delete" data-confirm="কুপনটি মুছবেন?">মুছুন</button></form></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$coupons): ?><tr><td colspan="6" class="muted">এখনো কোনো কুপন নেই।</td></tr><?php endif; ?>
    </table>
  </section>
  <form method="post" class="card form">
    <h3><?= $editId ? 'কুপন এডিট' : 'নতুন কুপন' ?></h3>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)$editId ?>">
    <label>কোড<input name="code" value="<?= e($c['code'] ?? '') ?>" placeholder="যেমন EID20" required></label>
    <div class="row2">
      <label>ধরন<select name="type">
        <option value="percent" <?= ($c['type'] ?? '') === 'percent' ? 'selected' : '' ?>>শতাংশ (%)</option>
        <option value="fixed" <?= ($c['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>নির্দিষ্ট টাকা (৳)</option>
      </select></label>
      <label>পরিমাণ<input name="value" type="number" step="0.01" min="0" value="<?= e($c['value'] ?? '') ?>" required></label>
    </div>
    <div class="row2">
      <label>ন্যূনতম কেনাকাটা (৳)<input name="min_amount" type="number" step="0.01" min="0" value="<?= e($c['min_amount'] ?? '0') ?>"></label>
      <label>মোট ব্যবহার সীমা<input name="max_uses" type="number" min="1" value="<?= e($c['max_uses'] ?? '') ?>" placeholder="সীমাহীন"></label>
    </div>
    <label>মেয়াদ শেষ<input name="expires_at" type="date" value="<?= e(!empty($c['expires_at']) ? date('Y-m-d', strtotime($c['expires_at'])) : '') ?>"></label>
    <label class="check"><input type="checkbox" name="is_active" <?= ($c['is_active'] ?? 1) ? 'checked' : '' ?>> চালু</label>
    <div class="btn-row"><button class="btn">সেভ করুন</button><?php if ($editId): ?><a class="btn btn-outline" href="coupons.php">বাতিল</a><?php endif; ?></div>
  </form>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
