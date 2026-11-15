<?php
require dirname(__DIR__) . '/_init.php';
$title = 'অর্ডার';
require APP_DIR . '/views/admin_header.php';

$status = (string)($_GET['status'] ?? '');
$s = trim((string)($_GET['s'] ?? ''));
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'paid', 'rejected'], true)) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($s !== '') {
    $where[] = '(o.order_no LIKE ? OR o.trx_id LIKE ? OR o.sender_number LIKE ? OR u.email LIKE ? OR u.name LIKE ?)';
    array_push($params, ...array_fill(0, 5, "%$s%"));
}
$w = implode(' AND ', $where);
$p = paginate((int)val("SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id WHERE $w", $params), 25);
$orders = rows("SELECT o.*, u.name, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE $w
                ORDER BY o.created_at DESC LIMIT {$p['limit']} OFFSET {$p['offset']}", $params);
?>
<form class="toolbar" method="get">
  <div class="tabs">
    <?php foreach (['' => 'সব', 'pending' => 'যাচাই বাকি', 'paid' => 'সফল', 'rejected' => 'বাতিল'] as $k => $label): ?>
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
    <td><?= e($o['name']) ?><div class="muted small"><?= e($o['email']) ?></div></td>
    <td class="small"><?= e(method_label($o['payment_method'])) ?><?php if ($o['trx_id']): ?><div><code><?= e($o['trx_id']) ?></code> · <?= e($o['sender_number']) ?></div><?php endif; ?></td>
    <td class="num"><?= money($o['total']) ?></td>
    <td><span class="status status-<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="6" class="muted">কোনো অর্ডার নেই।</td></tr><?php endif; ?>
</table>
</div>
<?= page_links($p) ?>
<?php require APP_DIR . '/views/admin_footer.php';
