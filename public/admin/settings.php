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
    'mail_from_name' => ['ইমেইলে প্রেরকের নাম (যেমন Practical Khata)', 'text'],
    'mail_from_email' => ['প্রেরকের ইমেইল (যেমন support@practicalkhata.com)', 'text'],
    'smtp_host'     => ['SMTP সার্ভার (cPanel: mail.practicalkhata.com)', 'text'],
    'smtp_port'     => ['SMTP পোর্ট (465 অথবা 587)', 'text'],
    'smtp_user'     => ['SMTP ইউজার (পুরো ইমেইল ঠিকানা)', 'text'],
    'smtp_pass'     => ['SMTP পাসওয়ার্ড (ওই ইমেইল একাউন্টের পাসওয়ার্ড)', 'password'],
    'pixel_id'      => ['Meta Pixel ID', 'text'],
    'capi_token'    => ['Conversions API Access Token (সার্ভার সাইড ট্র্যাকিং)', 'password'],
    'capi_test_code' => ['Test Event Code (শুধু টেস্টের সময়, পরে খালি করুন)', 'text'],
    'ga4_measurement_id' => ['GA4 Measurement ID (যেমন G-XXXXXXXXXX)', 'text'],
    'ga4_api_secret' => ['GA4 Measurement Protocol API Secret (সার্ভার সাইড Purchase)', 'password'],
    'ga4_debug'     => ['GA4 Debug মোড — টেস্টের সময় চালু করুন, পরে বন্ধ করুন', 'checkbox'],
];
if (is_post() && input('action') === 'test_email') {
    csrf_check();
    $err = send_mail(input('test_to'), 'টেস্ট ইমেইল — ' . setting('site_name', 'Practical Khata'),
        '<p>এটি একটি টেস্ট ইমেইল। এটি পেয়ে থাকলে অর্ডার Approve করলে কাস্টমারের কাছেও ডাউনলোড লিংক পৌঁছাবে। ✅</p>');
    flash($err ? 'error' : 'success', $err ? 'টেস্ট ইমেইল যায়নি: ' . $err : 'টেস্ট ইমেইল পাঠানো হয়েছে — ইনবক্স (আর Spam ফোল্ডার) দেখুন।');
    redirect('admin/settings.php#email');
}
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
      <?php elseif ($type === 'checkbox'): ?>
        <span class="check-inline"><input type="hidden" name="<?= $k ?>" value="0"><input type="checkbox" name="<?= $k ?>" value="1" <?= setting($k) === '1' ? 'checked' : '' ?>> চালু</span>
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
<form method="post" class="card narrow form" id="email">
  <h3>✉️ ইমেইল পাঠানো (ডাউনলোড লিংক)</h3>
  <p class="small">অর্ডার Approve করলে কাস্টমারের ইমেইলে ডাউনলোড লিংক যায়। এখন:
    <b><?= setting('smtp_host') && setting('smtp_user') ? 'SMTP (' . e(setting('smtp_user')) . ')' : 'সাধারণ PHP mail — Spam এ যেতে পারে, SMTP দেওয়া ভালো' ?></b></p>
  <p class="muted small">SMTP তথ্য পাবেন: cPanel → <b>Email Accounts</b> → একটি ইমেইল বানান (যেমন support@practicalkhata.com) → <b>Connect Devices</b> → "Secure SSL/TLS Settings" (সাধারণত সার্ভার mail.practicalkhata.com, পোর্ট 465)।</p>
  <?= csrf_field() ?><input type="hidden" name="action" value="test_email">
  <div class="copy-row"><input type="email" name="test_to" placeholder="টেস্ট ইমেইল যাবে যে ঠিকানায়" value="<?= e(setting('contact_email')) ?>" required><button class="btn btn-sm">টেস্ট পাঠান</button></div>
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
<div class="card narrow">
  <h3>সার্ভার সাইড ট্র্যাকিং (Google Analytics 4 — Purchase)</h3>
  <?php if (ga4_enabled()): ?>
    <p><span class="status status-paid">চালু</span> <?= e(setting('ga4_measurement_id')) ?><?= setting('ga4_debug') === '1' ? ' · <b>Debug মোড</b> (GA4 এ রেকর্ড হবে না)' : '' ?></p>
  <?php else: ?>
    <p><span class="status status-rejected">বন্ধ</span> Measurement ID ও API Secret দিলে চালু হবে।</p>
  <?php endif; ?>
  <p class="small"><b>সর্বশেষ পাঠানো:</b> <?= e(setting('ga4_last_status', 'এখনো কোনো অর্ডার পাঠানো হয়নি — একটি টেস্ট অর্ডার দিন, তারপর এই পেজ রিফ্রেশ করুন।')) ?></p>
  <p class="muted small">API Secret পাবেন: Google Analytics → Admin → Data streams → আপনার ওয়েবসাইট → <b>Measurement Protocol API secrets</b> → Create।</p>
</div>
<?php require APP_DIR . '/views/admin_footer.php';
