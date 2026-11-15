<?php
/* ---------- Cart (digital items, quantity is always 1) ---------- */

function cart_ids(): array
{
    return array_keys($_SESSION['cart'] ?? []);
}

function cart_add(int $id): void
{
    $_SESSION['cart'][$id] = 1;
}

function cart_remove(int $id): void
{
    unset($_SESSION['cart'][$id]);
}

function cart_clear(): void
{
    unset($_SESSION['cart'], $_SESSION['coupon']);
}

function cart_count(): int
{
    return count($_SESSION['cart'] ?? []);
}

/** Active products currently in the cart. */
function cart_items(): array
{
    $ids = cart_ids();
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $items = rows("SELECT * FROM products WHERE is_active = 1 AND id IN ($in)", $ids);
    // Drop anything that was deactivated or deleted
    $_SESSION['cart'] = array_fill_keys(array_map(fn($p) => (int)$p['id'], $items), 1);
    return $items;
}

function cart_subtotal(array $items): float
{
    return array_sum(array_map('product_price', $items));
}

/* ---------- Coupons ---------- */

/** Returns [coupon|null, error|null] */
function coupon_check(string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return [null, null];
    }
    $c = row('SELECT * FROM coupons WHERE code = ?', [$code]);
    if (!$c || !$c['is_active']) {
        return [null, 'কুপন কোডটি সঠিক নয়।'];
    }
    if ($c['expires_at'] && strtotime($c['expires_at']) < time()) {
        return [null, 'কুপনের মেয়াদ শেষ।'];
    }
    if ($c['max_uses'] !== null && (int)$c['used_count'] >= (int)$c['max_uses']) {
        return [null, 'এই কুপনের ব্যবহার সীমা শেষ।'];
    }
    if ($subtotal < (float)$c['min_amount']) {
        return [null, 'এই কুপন ব্যবহার করতে কমপক্ষে ' . money($c['min_amount']) . ' কেনাকাটা করতে হবে।'];
    }
    return [$c, null];
}

function coupon_discount(?array $c, float $subtotal): float
{
    if (!$c) {
        return 0.0;
    }
    $d = $c['type'] === 'percent'
        ? round($subtotal * (float)$c['value'] / 100, 2)
        : (float)$c['value'];
    return min($d, $subtotal);
}

/** Cart totals including the coupon stored in the session (drops it if no longer valid). */
function cart_totals(array $items): array
{
    $subtotal = cart_subtotal($items);
    $coupon = null;
    $error = null;
    if (!empty($_SESSION['coupon'])) {
        [$coupon, $error] = coupon_check($_SESSION['coupon'], $subtotal);
        if (!$coupon) {
            unset($_SESSION['coupon']);
        }
    }
    $discount = coupon_discount($coupon, $subtotal);
    return [
        'subtotal' => $subtotal,
        'coupon'   => $coupon,
        'coupon_error' => $error,
        'discount' => $discount,
        'total'    => max(0, round($subtotal - $discount, 2)),
    ];
}

/* ---------- Orders ---------- */

function new_order_no(): string
{
    do {
        $no = 'PK' . date('ymd') . random_int(1000, 9999);
    } while (val('SELECT id FROM orders WHERE order_no = ?', [$no]));
    return $no;
}

/** Products the user has already paid for (so we don't sell them twice). */
function owned_product_ids(int $userId): array
{
    return array_map('intval', array_column(rows(
        "SELECT DISTINCT oi.product_id FROM order_items oi JOIN orders o ON o.id = oi.order_id
         WHERE o.user_id = ? AND o.status = 'paid' AND oi.product_id IS NOT NULL",
        [$userId]
    ), 'product_id'));
}

function set_order_status(int $orderId, string $status, string $note = ''): void
{
    $o = row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o || $o['status'] === $status) {
        return;
    }
    db()->beginTransaction();
    q('UPDATE orders SET status = ?, admin_note = ? WHERE id = ?', [$status, $note ?: null, $orderId]);
    // A rejected order gives its coupon use back; re-approving takes it again.
    if ($o['coupon_id']) {
        if ($status === 'rejected') {
            q('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [$o['coupon_id']]);
        } elseif ($o['status'] === 'rejected') {
            q('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$o['coupon_id']]);
        }
    }
    db()->commit();
}
