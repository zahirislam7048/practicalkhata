<?php
require dirname(__DIR__) . '/_init.php';
require_admin();
if (is_post()) {
    csrf_check();
    $st = input('status');
    $due = input('due_amount');
    if ($st === 'due' && (!is_numeric($due) || (float)$due <= 0)) {
        flash('error', 'Payment Due দিতে বাকি টাকার পরিমাণ লিখুন।');
    } else {
        $cur = row('SELECT admin_note FROM orders WHERE id = ?', [(int)input('id')]);
        set_order_status((int)input('id'), $st, (string)($cur['admin_note'] ?? ''), $st === 'due' ? (float)$due : null);
        flash('success', 'স্ট্যাটাস বদলানো হয়েছে: ' . status_label($st));
    }
    redirect('admin/orders.php?' . http_build_query(array_intersect_key($_GET, ['status' => 1, 's' => 1, 'page' => 1])));
}
$title = 'অর্ডার';
require APP_DIR . '/views/admin_header.php';

$status = (string)($_GET['status'] ?? '');
$s = trim((string)($_GET['s'] ?? ''));
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'paid', 'rejected', 'due'], true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($s !== '') {
    $where[] = '(o.order_no LIKE ? OR o.trx_id LIKE ? OR o.sender_number LIKE ? OR u.email LIKE ? OR u.name LIKE ? OR o.guest_name LIKE ? OR o.guest_phone LIKE ? OR o.guest_email LIKE ?)';
    array_push($params, ...array_fill(0, 8, "%$s%"));
}
$w = implode(' AND ', $where);
$p = paginate((int)val("SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $w", $params), 25);
$orders = rows("SELECT o.*, COALESCE(u.name, o.guest_name) AS name, COALESCE(u.email, o.guest_email, o.guest_phone) AS email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $w
                ORDER BY o.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);
?>
<form class="toolbar" method="get">
  <div class="tabs">
    <?php foreach (['' => 'সব', 'pending' => 'যাচাই বাকি', 'due' => 'Payment Due', 'paid' => 'Approved', 'rejected' => 'Declined'] as $k => $label): ?>
      <a class="<?= $status === $k ? 'active' : '' ?>" href="?status=<?= $k ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input type="search" name="s" value="<?= e($s) ?>" placeholder="অর্ডার নং, TrxID, নাম্বার, ইমেইল…">
</form>
<div class="card">
<table class="table">
  <tr><th>অর্ডার</th><th>তারিখ</th><th>কাস্টমার</th><th>পেমেন্ট</th><th class="num">টাকা</th><th>স্ট্যাটাস</th></tr>
  <?php foreach ($orders as $o): ?>
  <tr>
    <td><a href="order.php?id=<?= (int)$o['id'] ?>">#<?= e($o['order_no']) ?></a></td>
    <td class="small"><?= e(date('d M y, h:i A', strtotime($o['created_at']))) ?></td>
    <td><?= e($o['name']) ?><?php if (!$o['user_id']): ?> <span class="badge">গেস্ট</span><?php endif; ?><div class="muted small"><?= e((string)$o['email']) ?></div></td>
    <td class="small"><?= e(method_label($o['payment_method'])) ?><?php if ($o['trx_id']): ?><div><code><?= e($o['trx_id']) ?></code> · <?= e($o['sender_number']) ?></div><?php endif; ?></td>
    <td class="num"><?= money($o['total']) ?></td>
    <td>
      <form method="post" class="quick-status">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
        <select name="status" class="status status-<?= e($o['status']) ?>" onchange="pkQuickStatus(this)" aria-label="স্ট্যাটাস">
          <?php foreach (['pending', 'paid', 'due', 'rejected'] as $st): ?>
            <option value="<?= $st ?>" <?= $o['status'] === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="due_amount" value="">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="6" class="muted">কোনো অর্ডার নেই।</td></tr><?php endif; ?>
</table>
</div>
<?= page_links($p) ?>
<?php require APP_DIR . '/views/admin_footer.php';
