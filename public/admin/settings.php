<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

$fields = [
    'site_name'     => ['সাইটের নাম', 'text'],
    'tagline'       => ['ট্যাগলাইন', 'text'],
    'bkash_number'  => ['bKash নাম্বার (খালি রাখলে bKash বন্ধ)', 'text'],
    'nagad_number'  => ['Nagad নাম্বার (খালি রাখলে Nagad বন্ধ)', 'text'],
    'payment_note'  => ['পেমেন্ট নির্দেশনা (চেকআউটে দেখাবে)', 'textarea'],
    'contact_phone' => ['যোগাযোগ নাম্বার', 'text'],
    'contact_email' => ['যোগাযোগ ইমেইল', 'text'],
    'facebook_url'  => ['Facebook পেজ লিংক', 'text'],
];
if (is_post()) {
    csrf_check();
    foreach (array_keys($fields) as $k) {
        q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, input($k)]);
    }
    flash('success', 'সেটিংস সেভ হয়েছে।');
    redirect('admin/settings.php');
}
$title = 'সেটিংস';
require APP_DIR . '/views/admin_header.php';
?>
<form method="post" class="card form narrow">
  <?= csrf_field() ?>
  <?php foreach ($fields as $k => [$label, $type]): ?>
    <label><?= e($label) ?>
      <?php if ($type === 'textarea'): ?>
        <textarea name="<?= $k ?>" rows="4"><?= e(setting($k)) ?></textarea>
      <?php else: ?>
        <input name="<?= $k ?>" value="<?= e(setting($k)) ?>">
      <?php endif; ?>
    </label>
  <?php endforeach; ?>
  <button class="btn">সেভ করুন</button>
</form>
<?php require APP_DIR . '/views/admin_footer.php';
