<?php
require __DIR__ . '/_init.php';

if (!is_post()) {
    redirect('index.php');
}
csrf_check();

$back = (string)input('return');
$back = (str_starts_with($back, '/') && !str_starts_with($back, '//')) ? $back : url('index.php');
$item = row("SELECT oi.*, o.user_id, o.status, o.access_token, o.guest_name FROM order_items oi
             JOIN orders o ON o.id = oi.order_id WHERE oi.id = ?", [(int)input('item')]);

// Only the buyer: logged-in owner of the order, or whoever has the order's secret link
$u = current_user();
$token = (string)input('t');
$owner = $item && (
    ($u && $item['user_id'] !== null && (int)$item['user_id'] === (int)$u['id'])
    || ($token !== '' && $item['access_token'] && hash_equals($item['access_token'], $token))
);
$rating = (int)input('rating');
$name = mb_substr(trim(strip_tags(input('name'))), 0, 60);
$comment = mb_substr(trim(strip_tags(input('comment'))), 0, 1000);

if (!$owner || !$item['product_id']) {
    flash('error', 'এই পণ্যের রিভিউ দেওয়ার অনুমতি নেই।');
} elseif ($item['status'] !== 'paid') {
    flash('error', 'পেমেন্ট যাচাই হওয়ার পর রিভিউ দিতে পারবেন।');
} elseif ($rating < 1 || $rating > 5) {
    flash('error', '১ থেকে ৫ এর মধ্যে স্টার দিন।');
} elseif ($name === '' || mb_strlen($comment) < 5) {
    flash('error', 'নাম ও অন্তত কয়েকটি শব্দে মতামত লিখুন।');
} elseif (val('SELECT id FROM reviews WHERE order_item_id = ?', [$item['id']])) {
    flash('info', 'এই পণ্যের রিভিউ আপনি আগেই দিয়েছেন। ধন্যবাদ!');
} else {
    q("INSERT INTO reviews (product_id, order_item_id, user_id, name, rating, comment, status) VALUES (?,?,?,?,?,?, ?)",
      [$item['product_id'], $item['id'], $item['user_id'], $name, $rating, $comment,
       setting('reviews_auto_approve') === '1' ? 'approved' : 'pending']);
    flash('success', setting('reviews_auto_approve') === '1'
        ? 'ধন্যবাদ! আপনার রিভিউ প্রকাশিত হয়েছে।'
        : 'ধন্যবাদ! আপনার রিভিউ জমা হয়েছে — যাচাইয়ের পর সাইটে দেখাবে।');
}
redirect($back);
