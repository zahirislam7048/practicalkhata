<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

$fields = [
    'theme'         => ['সাইটের রঙ', 'theme'],
    'flash_sale_end' => ['ফ্ল্যাশ সেল শেষ হওয়ার সময় (দিলে হোম পেজে কাউন্টডাউন দেখাবে)', 'datetime'],
    'site_name'     => ['সাইটের নাম', 'text'],
    'tagline'       => ['ট্যাগলাইন', 'text'],
    'bkash_number'  => ['bKash নাম্বার (খালি রাখলে bKash বন্ধ)', 'text'],
    'nagad_number'  => ['Nagad নাম্বার (খালি রাখলে Nagad বন্ধ)', 'text'],
    'payment_note'  => ['পেমেন্ট নির্দেশনা (চেকআউটে দেখাবে)', 'textarea'],
    'announcement'  => ['উপরের ঘোষণা লাইন (খালি রাখলে ডিফল্ট লেখা)', 'text'],
    'contact_phone' => ['যোগাযোগ নাম্বার', 'text'],
    'whatsapp_number' => ['WhatsApp নাম্বার (দিলে সাইটে WhatsApp বাটন দেখাবে)', 'text'],
    'contact_email' => ['যোগাযোগ ইমেইল', 'text'],
    'facebook_url'  => ['Facebook পেজ লিংক', 'text'],
    'pixel_id'      => ['Meta Pixel ID', 'text'],
    'capi_token'    => ['Conversions API Access Token (সার্ভার সাইড ট্র্যাকিং)', 'password'],
    'capi_test_code' => ['Test Event Code (শুধু টেস্টের সময়, পরে খালি করুন)', 'text'],
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
      <?php if ($type === 'theme'): ?>
        <div class="theme-pick">
          <?php foreach (theme_presets() as $tk => $tp): ?>
            <label class="theme-opt"><input type="radio" name="theme" value="<?= $tk ?>" <?= setting('theme', 'green') === $tk ? 'checked' : '' ?>>
              <span class="sw" style="background:<?= $tp[1] ?>"></span><span class="sw" style="background:<?= $tp[5] ?>"></span> <?= e($tp[0]) ?></label>
          <?php endforeach; ?>
        </div>
      <?php elseif ($type === 'datetime'): ?>
        <input type="datetime-local" name="<?= $k ?>" value="<?= setting($k) ? e(date('Y-m-d\TH:i', strtotime(setting($k)))) : '' ?>">
      <?php elseif ($type === 'textarea'): ?>
        <textarea name="<?= $k ?>" rows="4"><?= e(setting($k)) ?></textarea>
      <?php else: ?>
        <input name="<?= $k ?>" type="<?= $type === 'password' ? 'password' : 'text' ?>" value="<?= e($k === 'pixel_id' ? pixel_id() : setting($k)) ?>" <?= $type === 'password' ? 'autocomplete="off"' : '' ?>>
      <?php endif; ?>
    </label>
  <?php endforeach; ?>
  <button class="btn">সেভ করুন</button>
</form>
<div class="card narrow">
  <h3>সার্ভার সাইড ট্র্যাকিং (Meta Conversions API)</h3>
  <?php if (capi_enabled()): ?>
    <p><span class="status status-paid">চালু</span> Pixel <?= e(pixel_id()) ?><?= setting('capi_test_code') ? ' · <b>টেস্ট মোড</b> (' . e(setting('capi_test_code')) . ')' : '' ?></p>
  <?php else: ?>
    <p><span class="status status-rejected">বন্ধ</span> Access Token দিলে চালু হবে।</p>
  <?php endif; ?>
  <p class="small"><b>সর্বশেষ পাঠানো:</b> <?= e(setting('capi_last_status', 'এখনো কিছু পাঠানো হয়নি — সাইটের কোনো পেজ খুলুন, তারপর এই পেজ রিফ্রেশ করুন।')) ?></p>
  <p class="muted small">Token পাবেন: Meta Events Manager → আপনার Pixel → Settings → Conversions API → <b>Generate access token</b>।</p>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
