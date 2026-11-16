<?php
/*
 * Product reviews — only verified buyers (an Approved order containing the product), one review per
 * bought item. New reviews wait for admin approval (Admin → রিভিউ) before they appear on the site.
 */

function stars_html(float $avg, int $size = 16): string
{
    $out = '<span class="stars" style="--s:' . $size . 'px" aria-label="' . e(number_format($avg, 1)) . '/5">';
    for ($i = 1; $i <= 5; $i++) {
        $fill = max(0, min(1, $avg - ($i - 1)));
        $out .= '<i style="--f:' . round($fill * 100) . '%">★</i>';
    }
    return $out . '</span>';
}

/** ['avg' => 4.5, 'count' => 12, 'dist' => [5 => 8, 4 => 3, ...]] for approved reviews */
function review_summary(int $productId): array
{
    $rows = rows("SELECT rating, COUNT(*) n FROM reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating", [$productId]);
    $dist = array_fill_keys([5, 4, 3, 2, 1], 0);
    $sum = 0;
    $count = 0;
    foreach ($rows as $r) {
        $dist[(int)$r['rating']] = (int)$r['n'];
        $sum += (int)$r['rating'] * (int)$r['n'];
        $count += (int)$r['n'];
    }
    return ['avg' => $count ? round($sum / $count, 1) : 0.0, 'count' => $count, 'dist' => $dist];
}

/** Existing reviews for these order items: [order_item_id => review row] */
function reviews_for_items(array $itemIds): array
{
    if (!$itemIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($itemIds), '?'));
    $out = [];
    foreach (rows("SELECT * FROM reviews WHERE order_item_id IN ($in)", array_values($itemIds)) as $r) {
        $out[(int)$r['order_item_id']] = $r;
    }
    return $out;
}

/** A paid, not-yet-reviewed item of the current logged-in buyer for this product (for the product page form). */
function reviewable_item_for_user(int $productId): ?array
{
    $u = current_user();
    if (!$u) {
        return null;
    }
    return row("SELECT oi.id, o.access_token FROM order_items oi JOIN orders o ON o.id = oi.order_id
                LEFT JOIN reviews r ON r.order_item_id = oi.id
                WHERE oi.product_id = ? AND o.user_id = ? AND o.status = 'paid' AND r.id IS NULL
                ORDER BY oi.id LIMIT 1", [$productId, $u['id']]);
}

function review_status_label(string $s): string
{
    return ['pending' => 'অনুমোদনের অপেক্ষায়', 'approved' => 'প্রকাশিত', 'hidden' => 'লুকানো'][$s] ?? $s;
}
