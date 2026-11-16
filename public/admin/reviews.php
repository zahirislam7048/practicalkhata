<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)input('id');
    $action = input('action');
    if (in_array($action, ['approved', 'hidden', 'pending'], true)) {
        q('UPDATE reviews SET status = ? WHERE id = ?', [$action, $id]);
        flash('success', 'রিভিউ: ' . review_status_label($action));
    } elseif ($action === 'reply') {
        q('UPDATE reviews SET admin_reply = ? WHERE id = ?', [input('reply') ?: null, $id]);
        flash('success', 'উত্তর সেভ হয়েছে।');
    } elseif ($action === 'add') {
        $pid = (int)input('product_id');
        $rating = (int)input('rating');
        $src = in_array(input('source'), review_sources(), true) ? input('source') : '';
        if (!isset($_POST['confirm_real'])) {
            flash('error', 'শুধু আসল কাস্টমারের দেওয়া মতামত যোগ করা যাবে — নিশ্চিত করার বক্সে টিক দিন।');
        } elseif (!val('SELECT id FROM products WHERE id = ?', [$pid]) || $rating < 1 || $rating > 5 || input('name') === '' || mb_strlen(input('comment')) < 5 || $src === '') {
            flash('error', 'পণ্য, নাম, স্টার, উৎস ও মতামত — সব পূরণ করুন।');
        } else {
            q("INSERT INTO reviews (product_id, name, rating, comment, source, status) VALUES (?,?,?,?,?,'approved')",
              [$pid, mb_substr(input('name'), 0, 60), $rating, mb_substr(input('comment'), 0, 1000), $src]);
            flash('success', 'মতামত যোগ হয়েছে — সাইটে "' . $src . ' থেকে সংগৃহীত" লেখাসহ দেখাবে।');
        }
    } elseif ($action === 'delete') {
        q('DELETE FROM reviews WHERE id = ?', [$id]);
        flash('success', 'রিভিউ মুছে ফেলা হয়েছে।');
    }
    redirect('admin/reviews.php?' . http_build_query(array_intersect_key($_GET, ['status' => 1])));
}

$title = 'রিভিউ';
require APP_DIR . '/views/admin_header.php';
$status = (string)($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'hidden', 'all'], true)) {
    $status = 'pending';
}
$where = $status === 'all' ? '1=1' : 'r.status = ?';
$reviews = rows("SELECT r.*, p.title, p.slug, o.order_no, o.access_token FROM reviews r
                 JOIN products p ON p.id = r.product_id
                 LEFT JOIN order_items oi ON oi.id = r.order_item_id LEFT JOIN orders o ON o.id = oi.order_id
                 WHERE $where ORDER BY r.created_at DESC LIMIT 200", $status === 'all' ? [] : [$status]);
$counts = array_column(rows('SELECT status, COUNT(*) n FROM reviews GROUP BY status'), 'n', 'status');
?>
<div class="toolbar">
  <div class="tabs">
    <?php foreach (['pending' => 'অপেক্ষমাণ', 'approved' => 'প্রকাশিত', 'hidden' => 'লুকানো', 'all' => 'সব'] as $k => $label): ?>
      <a class="<?= $status === $k ? 'active' : '' ?>" href="?status=<?= $k ?>"><?= $label ?><?= isset($counts[$k]) ? ' (' . (int)$counts[$k] . ')' : '' ?></a>
    <?php endforeach; ?>
  </div>
  <span class="muted small">শুধু ভেরিফায়েড ক্রেতারা রিভিউ দিতে পারেন। <?= setting('reviews_auto_approve') === '1' ? 'নতুন রিভিউ নিজে থেকে প্রকাশ হচ্ছে।' : 'নতুন রিভিউ আপনার অনুমোদনের পর প্রকাশ হবে।' ?></span>
</div>
<details class="card add-review">
  <summary><b>➕ আসল কাস্টমারের মতামত যোগ করুন</b> <span class="muted small">(Facebook/WhatsApp/Messenger-এ পাওয়া প্রশংসা)</span></summary>
  <form method="post" class="form" style="margin-top:12px">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="row2">
      <label>পণ্য<select name="product_id" required>
        <?php foreach (rows('SELECT id, title FROM products ORDER BY title') as $pp): ?><option value="<?= (int)$pp['id'] ?>"><?= e($pp['title']) ?></option><?php endforeach; ?>
      </select></label>
      <label>কোথা থেকে পেয়েছেন<select name="source" required>
        <?php foreach (review_sources() as $src): ?><option><?= e($src) ?></option><?php endforeach; ?>
      </select></label>
    </div>
    <div class="row2">
      <label>কাস্টমারের নাম<input name="name" maxlength="60" required></label>
      <label>স্টার (কাস্টমার যা বলেছেন সে অনুযায়ী)<select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option><?php endfor; ?></select></label>
    </div>
    <label>মতামত (কাস্টমারের নিজের কথা, যতটা সম্ভব হুবহু)<textarea name="comment" rows="3" maxlength="1000" required></textarea></label>
    <label class="check"><input type="checkbox" name="confirm_real" value="1" required> আমি নিশ্চিত করছি এটি একজন আসল কাস্টমারের দেওয়া মতামত (প্রমাণ — স্ক্রিনশট — আমার কাছে আছে)</label>
    <button class="btn">যোগ করুন</button>
  </form>
</details>
<?php if (!$reviews): ?><div class="card muted">এখানে কোনো রিভিউ নেই।</div><?php endif; ?>
<?php foreach ($reviews as $r): ?>
<div class="card admin-review">
  <div class="ar-head">
    <div>
      <?= stars_html((float)$r['rating'], 16) ?> <b><?= e($r['name']) ?></b>
      <span class="muted small">· <?= e(date('d M Y, h:i A', strtotime($r['created_at']))) ?></span>
      <div class="small"><a href="<?= product_url($r) ?>#reviews" target="_blank"><?= e($r['title']) ?></a>
        <?php if ($r['order_no']): ?> · অর্ডার <a href="orders.php?s=<?= urlencode($r['order_no']) ?>">#<?= e($r['order_no']) ?></a><?php else: ?> · <?= e(review_badge($r)) ?><?php endif; ?></div>
    </div>
    <span class="status <?= $r['status'] === 'approved' ? 'status-paid' : ($r['status'] === 'pending' ? 'status-pending' : 'status-rejected') ?>"><?= e(review_status_label($r['status'])) ?></span>
  </div>
  <p><?= nl2br(e($r['comment'])) ?></p>
  <form method="post" class="ar-actions">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <?php if ($r['status'] !== 'approved'): ?><button class="btn btn-sm btn-approve" name="action" value="approved">✔ প্রকাশ করুন</button><?php endif; ?>
    <?php if ($r['status'] !== 'hidden'): ?><button class="btn btn-sm btn-outline" name="action" value="hidden">লুকান</button><?php endif; ?>
    <button class="link-btn danger" name="action" value="delete" data-confirm="রিভিউটি মুছে ফেলবেন?">মুছুন</button>
  </form>
  <form method="post" class="ar-reply">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="reply">
    <input name="reply" value="<?= e((string)$r['admin_reply']) ?>" placeholder="কাস্টমারকে উত্তর দিন (সাইটে রিভিউয়ের নিচে দেখাবে)">
    <button class="btn btn-sm">উত্তর সেভ</button>
  </form>
</div>
<?php endforeach; ?>
<?php require APP_DIR . '/views/admin_footer.php';
