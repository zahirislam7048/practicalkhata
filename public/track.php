<?php
require __DIR__ . '/_init.php';

/*
 * One box: order number, mobile number, payment (bKash/Nagad) number or email.
 * One match → straight to that order's page; several → a list to choose from.
 */
$error = '';
$found = [];
$query = '';
if (is_post()) {
    csrf_check();
    $query = trim(input('q'));
    if (login_throttled()) {
        $error = 'অনেকবার ভুল চেষ্টা হয়েছে। ১০ মিনিট পর আবার চেষ্টা করুন।';
    } elseif ($query === '') {
        $error = 'অর্ডার নম্বর, মোবাইল/পেমেন্ট নাম্বার বা ইমেইল লিখুন।';
    } else {
        $where = [];
        $params = [];
        // Order number (with or without #)
        $no = strtoupper(ltrim(preg_replace('/\s+/', '', $query), '#'));
        if (preg_match('/^PK\d{6,}$/', $no)) {
            $where[] = 'o.order_no = ?';
            $params[] = $no;
        }
        // Mobile / payment number: accept +880, 880, spaces and dashes
        $digits = preg_replace('/\D+/', '', strtr($query, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']));
        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^01[3-9]\d{8}$/', $digits)) {
            $where[] = '(o.guest_phone = ? OR o.sender_number = ? OR u.phone = ? OR o.payment_log LIKE ?)';
            array_push($params, $digits, $digits, $digits, '% · ' . $digits . ' · %');
        }
        // Email
        if (filter_var($query, FILTER_VALIDATE_EMAIL)) {
            $email = mb_strtolower($query);
            $where[] = '(o.guest_email = ? OR u.email = ?)';
            array_push($params, $email, $email);
        }

        if (!$where) {
            $error = 'সঠিক অর্ডার নম্বর (যেমন PK2611151234), মোবাইল নাম্বার (01XXXXXXXXX) বা ইমেইল লিখুন।';
        } else {
            $found = rows('SELECT o.order_no, o.status, o.total, o.access_token, o.created_at FROM orders o
                           LEFT JOIN users u ON u.id = o.user_id
                           WHERE o.access_token IS NOT NULL AND (' . implode(' OR ', $where) . ')
                           ORDER BY o.created_at DESC LIMIT 20', $params);
            if (count($found) === 1) {
                redirect('order.php?t=' . $found[0]['access_token']);
            }
            if (!$found) {
                login_failed();
                $error = 'এই তথ্য দিয়ে কোনো অর্ডার পাওয়া যায়নি। অর্ডারের সময় যে নাম্বার/ইমেইল দিয়েছিলেন সেটা লিখুন।';
            } else {
                foreach ($found as $f) {
                    $_SESSION['guest_orders'][] = $f['access_token'];
                }
                $_SESSION['guest_orders'] = array_values(array_unique($_SESSION['guest_orders']));
            }
        }
    }
}
// Orders placed or found from this browser
$mine = [];
foreach (array_slice(array_reverse($_SESSION['guest_orders'] ?? []), 0, 20) as $tk) {
    if ($r = row('SELECT order_no, status, total, access_token, created_at FROM orders WHERE access_token = ?', [$tk])) {
        $mine[] = $r;
    }
}
$title = 'অর্ডার ট্র্যাক';
require APP_DIR . '/views/header.php';
?>
<form method="post" class="card form narrow auth" novalidate>
  <h1>অর্ডার ট্র্যাক করুন</h1>
  <p class="muted small" style="text-align:center">নিচের যেকোনো <b>একটি</b> লিখুন — অর্ডারের অবস্থা দেখবেন ও ডাউনলোড করতে পারবেন।</p>
  <div class="track-hints"><span>অর্ডার নম্বর</span><span>মোবাইল নাম্বার</span><span>পেমেন্ট নাম্বার</span><span>ইমেইল</span></div>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>অর্ডার নম্বর / মোবাইল / পেমেন্ট নাম্বার / ইমেইল
    <input name="q" value="<?= e($query) ?>" placeholder="যেমন PK2611151234 বা 017XXXXXXXX বা name@gmail.com" autofocus autocomplete="off">
  </label>
  <button class="btn btn-accent btn-lg btn-block"><?= icon('search', 18) ?> খুঁজুন</button>
  <p class="small" style="text-align:center">অ্যাকাউন্ট দিয়ে কিনেছেন? <a href="<?= url('account.php') ?>">আমার অ্যাকাউন্ট</a></p>
</form>
<?php if ($found || $mine): ?>
<div class="card narrow" style="margin:0 auto">
  <h3><?= $found ? bn(count($found)) . 'টি অর্ডার পাওয়া গেছে' : 'এই ব্রাউজার থেকে করা অর্ডার' ?></h3>
  <table class="table">
    <?php foreach ($found ?: $mine as $m): ?>
    <tr><td><a href="<?= url('order.php?t=' . $m['access_token']) ?>">#<?= e($m['order_no']) ?></a>
        <div class="muted small"><?= e(date('d M Y', strtotime($m['created_at']))) ?></div></td>
      <td class="num"><?= money($m['total']) ?></td>
      <td class="num"><span class="status status-<?= e($m['status']) ?>"><?= e(status_label($m['status'])) ?></span></td>
      <td class="num"><a class="btn btn-sm" href="<?= url('order.php?t=' . $m['access_token']) ?>">দেখুন</a></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
<?php require APP_DIR . '/views/footer.php';
