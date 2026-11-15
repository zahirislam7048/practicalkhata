<?php
require dirname(__DIR__) . '/_init.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)input('id');
    if (input('action') === 'make_admin') {
        q("UPDATE users SET role = 'admin' WHERE id = ?", [$id]);
        flash('success', 'অ্যাডমিন বানানো হয়েছে।');
    } elseif (input('action') === 'remove_admin' && $id !== (int)current_user()['id']) {
        q("UPDATE users SET role = 'customer' WHERE id = ?", [$id]);
        flash('success', 'অ্যাডমিন অধিকার সরানো হয়েছে।');
    }
    redirect('admin/customers.php');
}

$title = 'কাস্টমার';
require APP_DIR . '/views/admin_header.php';
$s = trim((string)($_GET['s'] ?? ''));
$where = '1=1';
$params = [];
if ($s !== '') {
    $where = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $params = ["%$s%", "%$s%", "%$s%"];
}
$p = paginate((int)val("SELECT COUNT(*) FROM users u WHERE $where", $params), 30);
$users = rows("SELECT u.*,
    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'paid') paid_orders,
    (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id = u.id AND o.status = 'paid') spent
    FROM users u WHERE $where ORDER BY u.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);
?>
<form class="toolbar" method="get"><input type="search" name="s" value="<?= e($s) ?>" placeholder="নাম, ইমেইল বা মোবাইল…"></form>
<div class="card">
<table class="table">
  <tr><th>নাম</th><th>ইমেইল</th><th>মোবাইল</th><th class="num">সফল অর্ডার</th><th class="num">মোট কেনা</th><th>যোগদান</th><th></th></tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= e($u['name']) ?><?php if ($u['role'] === 'admin'): ?> <span class="badge">admin</span><?php endif; ?></td>
    <td><a href="orders.php?s=<?= urlencode($u['email']) ?>"><?= e($u['email']) ?></a></td>
    <td><?= e((string)$u['phone']) ?></td>
    <td class="num"><?= (int)$u['paid_orders'] ?></td>
    <td class="num"><?= money($u['spent']) ?></td>
    <td class="small"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
    <td class="num">
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
      <?php if ($u['role'] === 'customer'): ?>
        <button class="link-btn" name="action" value="make_admin" data-confirm="<?= e($u['name']) ?> কে অ্যাডমিন বানাবেন?">অ্যাডমিন করুন</button>
      <?php elseif ((int)$u['id'] !== (int)current_user()['id']): ?>
        <button class="link-btn danger" name="action" value="remove_admin">অ্যাডমিন সরান</button>
      <?php endif; ?>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?= page_links($p) ?>
<?php require APP_DIR . '/views/admin_footer.php';
