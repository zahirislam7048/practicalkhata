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

/**
 * Change an order's status. When it becomes Approved, the download link is emailed to the customer.
 * Returns a short message about that email (or null when no email was attempted).
 */
function set_order_status(int $orderId, string $status, string $note = '', ?float $dueAmount = null): ?string
{
    $o = row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o || !in_array($status, ['pending', 'paid', 'rejected', 'due'], true)) {
        return null;
    }
    if ($o['status'] === $status) {
        q('UPDATE orders SET admin_note = ?, due_amount = ? WHERE id = ?', [$note ?: null, $status === 'due' ? $dueAmount : null, $orderId]);
        return null;
    }
    db()->beginTransaction();
    q('UPDATE orders SET status = ?, admin_note = ?, due_amount = ? WHERE id = ?',
      [$status, $note ?: null, $status === 'due' ? $dueAmount : null, $orderId]);
    // A rejected order gives its coupon use back; re-approving takes it again.
    if ($o['coupon_id']) {
        if ($status === 'rejected') {
            q('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [$o['coupon_id']]);
        } elseif ($o['status'] === 'rejected') {
            q('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$o['coupon_id']]);
        }
    }
    db()->commit();

    if ($status === 'paid') {
        if (order_email($o) === '') {
            return 'কাস্টমার ইমেইল দেননি — অর্ডারের পেজ থেকে WhatsApp এ লিংক পাঠান।';
        }
        $err = send_download_email($orderId);
        return $err ? 'ইমেইল যায়নি: ' . $err : 'ডাউনলোড লিংক ইমেইলে পাঠানো হয়েছে (' . order_email($o) . ')।';
    }
    return null;
}

/* ---------- Tracking (Meta Pixel + GTM dataLayer) ---------- */

/**
 * Queue an ecommerce event. $later = true keeps it in the session so it still fires after a redirect.
 * $items: list of ['id','title','price'].
 */
function track_event(string $fbEvent, string $gaEvent, array $items, float $value, array $extra = [], bool $later = false): void
{
    $extra['event_id'] ??= new_event_id(strtolower($fbEvent));
    $ev = [$fbEvent, $gaEvent, ['items' => $items, 'value' => $value] + $extra];
    // Same event from the server (Conversions API), de-duplicated by event_id
    if ($gaEvent === 'purchase') {
        ga4_queue_purchase($extra['event_id'], $items, $value, $extra['coupon'] ?? null);
    }
    capi_queue($fbEvent, $extra['event_id'], array_filter([
        'currency'     => 'BDT',
        'value'        => $value,
        'content_ids'  => array_map(fn($i) => (string)$i['id'], $items),
        'content_type' => 'product',
        'contents'     => array_map(fn($i) => ['id' => (string)$i['id'], 'quantity' => 1, 'item_price' => (float)$i['price']], $items),
        'num_items'    => count($items),
        'order_id'     => $fbEvent === 'Purchase' ? $extra['event_id'] : null,
    ], fn($v) => $v !== null));
    if ($later) {
        $_SESSION['track'][] = $ev;
    } else {
        $GLOBALS['track_now'][] = $ev;
    }
}

function track_items(array $products): array
{
    return array_map(fn($p) => ['id' => (int)$p['id'], 'title' => $p['title'], 'price' => product_price($p)], $products);
}

/** Customer info for GTM/Pixel. Visitors who aren't logged in get the default name "Guest". */
function tracking_customer(): array
{
    $u = current_user();
    if (!$u && !empty($_SESSION['guest']['name'])) {
        // Guest who already filled the checkout form
        $g = $_SESSION['guest'];
        $u = ['id' => '', 'name' => $g['name'], 'email' => $g['email'] ?? '', 'phone' => $g['phone'] ?? '', 'guest' => true];
    }
    $phone = preg_replace('/\D+/', '', (string)($u['phone'] ?? ''));
    if (str_starts_with($phone, '01')) {
        $phone = '88' . $phone; // 01XXXXXXXXX → 8801XXXXXXXXX (country code for Meta matching)
    }
    $parts = preg_split('/\s+/u', trim((string)($u['name'] ?? '')), 2);
    return [
        'logged_in' => $u && empty($u['guest']),
        'id'        => $u ? (string)$u['id'] : '',
        'name'      => $u ? $u['name'] : 'Guest',
        'first'     => $u ? mb_strtolower($parts[0]) : '',
        'last'      => $u ? mb_strtolower($parts[1] ?? '') : '',
        'email'     => $u ? mb_strtolower($u['email']) : '',
        'phone'     => $phone,
    ];
}

/** Active products with how many paid copies were sold. */
function shop_products(string $where = '1=1', array $params = [], string $order = 'p.created_at DESC', int $limit = 12, int $offset = 0): array
{
    return rows("SELECT p.*, p.prior_sales + (SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id
                 WHERE oi.product_id = p.id AND o.status = 'paid') AS sold,
                 (SELECT AVG(rating) FROM reviews rv WHERE rv.product_id = p.id AND rv.status = 'approved') AS rating_avg,
                 (SELECT COUNT(*) FROM reviews rv WHERE rv.product_id = p.id AND rv.status = 'approved') AS rating_count
                 FROM products p WHERE p.is_active = 1 AND ($where) ORDER BY $order LIMIT $limit OFFSET $offset", $params);
}

/** Is this Transaction ID already used by any order (main TrxID or an extra due payment)? */
function trx_used(string $method, string $trx): bool
{
    return (bool)val('SELECT id FROM orders WHERE (payment_method = ? AND trx_id = ?) OR payment_log LIKE ?',
        [$method, $trx, '%TrxID ' . $trx . '%']);
}

/**
 * Customer pays the remaining amount of a "Payment Due" order. Returns an error message or null.
 */
function submit_due_payment(array $o, string $method, string $sender, string $trx): ?string
{
    $sender = preg_replace('/\D+/', '', $sender);
    $trx = strtoupper(preg_replace('/\s+/', '', $trx));
    if ($o['status'] !== 'due') {
        return 'এই অর্ডারে কোনো বাকি পেমেন্ট নেই।';
    }
    if (!in_array($method, ['bkash', 'nagad'], true) || setting($method . '_number') === '') {
        return 'পেমেন্ট মাধ্যম বেছে নিন।';
    }
    if (!preg_match('/^01[3-9]\d{8}$/', $sender)) {
        return 'যে নাম্বার থেকে টাকা পাঠিয়েছেন সেটি সঠিকভাবে লিখুন।';
    }
    if (!preg_match('/^[A-Z0-9]{6,30}$/', $trx)) {
        return 'সঠিক Transaction ID লিখুন।';
    }
    if (trx_used($method, $trx)) {
        return 'এই Transaction ID আগেই ব্যবহার করা হয়েছে।';
    }
    $line = date('d M Y h:i A') . ' — বাকি পেমেন্ট: ' . method_label($method) . ' · ' . $sender . ' · TrxID ' . $trx
          . ($o['due_amount'] ? ' · ' . money($o['due_amount']) : '');
    q("UPDATE orders SET status = 'pending', payment_log = CONCAT(COALESCE(payment_log, ''), ?, '\n') WHERE id = ?", [$line, $o['id']]);
    return null;
}

/* ---------- Categories & theme ---------- */

function categories(): array
{
    static $cats = null;
    if ($cats === null) {
        try {
            $cats = rows("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS n
                          FROM categories c ORDER BY c.sort_order, c.name");
        } catch (Throwable $t) {
            $cats = [];
        }
    }
    return $cats;
}

/** Colour presets the admin can pick in Settings → theme */
function theme_presets(): array
{
    return [
        'green'  => ['সবুজ + হলুদ',      '#15803d', '#166534', '#dcfce7', '#f0fdf4', '#facc15', '#eab308', '#fef9c3', '#052e16'],
        'navy'   => ['নেভি ব্লু + কমলা', '#1e3a8a', '#172554', '#dbeafe', '#eff6ff', '#f97316', '#ea580c', '#ffedd5', '#0b1533'],
        'teal'   => ['টিল + কমলা',      '#0f766e', '#115e59', '#ccfbf1', '#f0fdfa', '#f59e0b', '#d97706', '#fef3c7', '#042f2e'],
        'purple' => ['বেগুনি + গোলাপি',  '#6d28d9', '#5b21b6', '#ede9fe', '#f5f3ff', '#ec4899', '#db2777', '#fce7f3', '#1e1038'],
        'red'    => ['লাল + হলুদ',       '#dc2626', '#b91c1c', '#fee2e2', '#fef2f2', '#facc15', '#eab308', '#fef9c3', '#2a0a0a'],
    ];
}

function theme_css(): string
{
    $p = theme_presets();
    $t = $p[setting('theme', 'green')] ?? $p['green'];
    [, $brand, $dark, $soft, $b50, $acc, $accDark, $accSoft, $deep] = $t;
    // Text colour on the accent: dark for light accents (yellow), white otherwise
    $lightAccent = in_array($acc, ['#facc15'], true);
    $onAccent = $lightAccent ? $deep : '#ffffff';
    // Yellow text is hard to read on white, so prices use the dark brand colour with a yellow accent
    $price = $lightAccent ? $dark : $accDark;
    return ":root{--brand:$brand;--brand-dark:$dark;--brand-soft:$soft;--brand-50:$b50;--accent:$acc;--accent-dark:$accDark;--accent-soft:$accSoft;--deep:$deep;--on-accent:$onAccent;--price:$price}";
}

/** Flash sale end time (Unix) or 0 when no countdown is set / it is over. */
function flash_sale_end(): int
{
    $t = strtotime(setting('flash_sale_end'));
    return ($t && $t > time()) ? $t : 0;
}

/** "You may also like" on cart/checkout: active products not in the cart and not already bought. */
function upsell_products(int $limit = 4): array
{
    $skip = cart_ids();
    if ($u = current_user()) {
        $skip = array_merge($skip, owned_product_ids((int)$u['id']));
    }
    $skip = array_values(array_unique(array_map('intval', $skip))) ?: [0];
    $in = implode(',', array_fill(0, count($skip), '?'));
    return shop_products("p.id NOT IN ($in)", $skip, '(p.sale_price IS NOT NULL AND p.sale_price < p.price) DESC, sold DESC, p.created_at DESC', $limit);
}

/** Total happy customers to show on the home page: sales before the website + paid website orders. */
function total_customers(): int
{
    $online = (int)val("SELECT COUNT(DISTINCT COALESCE(CONCAT('u', user_id), guest_phone, id)) FROM orders WHERE status = 'paid'");
    return (int)setting('offline_customers', '0') + $online;
}

/** Change a product's link; the old one is remembered and redirects to the new one. */
function set_product_slug(int $productId, string $newSlug): void
{
    $old = (string)val('SELECT slug FROM products WHERE id = ?', [$productId]);
    if ($old === $newSlug || $newSlug === '') {
        return;
    }
    if ($old !== '') {
        q('INSERT INTO product_slugs (old_slug, product_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE product_id = VALUES(product_id)', [$old, $productId]);
    }
    q('DELETE FROM product_slugs WHERE old_slug = ?', [$newSlug]); // the new link must point to this product only
    q('UPDATE products SET slug = ? WHERE id = ?', [$newSlug, $productId]);
}
