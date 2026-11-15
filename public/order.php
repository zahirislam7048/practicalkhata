<?php
require __DIR__ . '/_init.php';

// Secret order page for guest (and any) orders: order.php?t=<access token>
$token = (string)($_GET['t'] ?? '');
$o = $token !== '' && preg_match('/^[a-f0-9]{40}$/', $token)
    ? row('SELECT * FROM orders WHERE access_token = ?', [$token])
    : null;
if (!$o) {
    http_response_code(404);
    $title = 'অর্ডার পাওয়া যায়নি';
    require APP_DIR . '/views/header.php';
    echo '<div class="empty">' . icon('search', 40) . '<p>অর্ডারটি পাওয়া যায়নি।</p><a class="btn" href="' . url('track.php') . '">অর্ডার খুঁজুন</a></div>';
    require APP_DIR . '/views/footer.php';
    exit;
}
if (is_post() && input('action') === 'pay_due') {
    csrf_check();
    $dueOrder = row('SELECT * FROM orders WHERE id = ?', [(int)input('order_id')]);
    $allowed = $dueOrder && (hash_equals((string)$dueOrder['access_token'], $token) && (int)$dueOrder['id'] === (int)$o['id']);
    $err = $allowed ? submit_due_payment($dueOrder, input('method'), input('sender'), input('trx')) : 'অর্ডারটি পাওয়া যায়নি।';
    flash($err ? 'error' : 'success', $err ?: 'বাকি পেমেন্ট জমা হয়েছে। যাচাই হলে ডাউনলোড চালু হবে।');
    redirect('order.php?t=' . $token);
}
$items = rows('SELECT oi.*, p.download_limit, p.file_path, p.slug FROM order_items oi
               LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$o['id']]);
$link = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . url('order.php?t=' . $token);
header('X-Robots-Tag: noindex');
$title = 'অর্ডার #' . $o['order_no'];
require APP_DIR . '/views/header.php';
?>
<?php $step = $o['status'] === 'paid' ? 3 : 2; require APP_DIR . '/views/steps.php'; ?>
<div class="order-hero card">
  <?php if ($o['status'] === 'paid'): ?>
    <h1><?= icon('check', 28) ?> আপনার ফাইল প্রস্তুত!</h1>
    <p>নিচের বাটন থেকে ডাউনলোড করুন।</p>
  <?php elseif ($o['status'] === 'pending'): ?>
    <h1>ধন্যবাদ<?= $o['guest_name'] ? ', ' . e($o['guest_name']) : '' ?>! অর্ডার জমা হয়েছে</h1>
    <p>আমরা আপনার পেমেন্ট যাচাই করছি। যাচাই হলে এই পেজেই ডাউনলোড বাটন চালু হবে — একটু পরে পেজটি রিফ্রেশ করুন।</p>
  <?php elseif ($o['status'] === 'due'): ?>
    <h1>⏳ পেমেন্ট বাকি আছে</h1>
    <p>বাকি <?= money($o['due_amount'] ?? 0) ?> পাঠিয়ে নিচে নতুন Transaction ID দিন।</p>
  <?php else: ?>
    <h1>অর্ডারটি বাতিল হয়েছে</h1>
    <p>পেমেন্ট যাচাই করা যায়নি। প্রশ্ন থাকলে আমাদের সাথে যোগাযোগ করুন।</p>
  <?php endif; ?>
  <div class="save-link">
    <b><?= icon('lock', 16) ?> এই লিংকটি সেভ করে রাখুন</b> — এটি দিয়েই পরে আবার ডাউনলোড করতে পারবেন:
    <div class="copy-row"><input readonly value="<?= e($link) ?>" onclick="this.select()"><button type="button" class="btn btn-sm" data-copy="<?= e($link) ?>"><small>কপি</small></button></div>
    <p class="muted small">লিংক হারালে <a href="<?= url('track.php') ?>">অর্ডার ট্র্যাক</a> পেজে অর্ডার নম্বর (#<?= e($o['order_no']) ?>), মোবাইল নাম্বার বা ইমেইল দিয়ে খুঁজে পাবেন।</p>
  </div>
</div>
<?php require APP_DIR . '/views/order_card.php'; ?>
<?php require APP_DIR . '/views/footer.php';
