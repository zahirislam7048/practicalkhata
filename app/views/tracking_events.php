<?php
// Ecommerce events queued during this request or before a redirect (see track_event()).
$events = array_merge($_SESSION['track'] ?? [], $GLOBALS['track_now'] ?? []);
unset($_SESSION['track']);
if (!$events) return;
?>
<script>
<?php foreach ($events as [$fbEvent, $gaEvent, $data]): ?>
<?php $items = array_map(fn($i) => ['item_id' => (string)$i['id'], 'item_name' => $i['title'], 'price' => (float)$i['price']], $data['items']); ?>
if (window.fbq) fbq('track', <?= json_encode($fbEvent) ?>, <?= json_encode([
    'content_ids'  => array_map(fn($i) => (string)$i['id'], $data['items']),
    'content_type' => 'product',
    'value'        => (float)$data['value'],
    'currency'     => 'BDT',
    'num_items'    => count($data['items']),
], JSON_UNESCAPED_UNICODE) ?><?= !empty($data['event_id']) ? ', ' . json_encode(['eventID' => $data['event_id']]) : '' ?>);
dataLayer.push({ecommerce: null});
dataLayer.push(<?= json_encode(array_filter([
    'event'     => $gaEvent,
    'event_id'  => $data['event_id'] ?? null,
    'ecommerce' => array_filter([
        'transaction_id' => $gaEvent === 'purchase' ? ($data['event_id'] ?? null) : null,
        'currency'       => 'BDT',
        'value'          => (float)$data['value'],
        'coupon'         => $data['coupon'] ?? null,
        'items'          => $items,
    ], fn($v) => $v !== null),
    'customer'  => [
        'id'    => $pkCustomer['id'],
        'name'  => $pkCustomer['name'],
        'email' => $pkCustomer['email'],
        'phone' => $pkCustomer['phone'],
    ],
]), JSON_UNESCAPED_UNICODE) ?>);
<?php endforeach; ?>
</script>
