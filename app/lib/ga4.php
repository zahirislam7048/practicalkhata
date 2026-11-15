<?php
/*
 * Google Analytics 4 — server-side purchase (Measurement Protocol).
 * The browser (GTM) sends `purchase` with transaction_id = order number; the server sends the same
 * purchase with the same transaction_id, so GA4 counts it once even when an ad blocker stops the browser.
 *
 * Admin → Settings: ga4_measurement_id (G-XXXXXXX), ga4_api_secret
 * (GA4 → Admin → Data streams → your web stream → Measurement Protocol API secrets → Create).
 */

function ga4_enabled(): bool
{
    return preg_match('/^G-[A-Z0-9]+$/i', setting('ga4_measurement_id')) && setting('ga4_api_secret') !== '';
}

/** client_id from the _ga cookie (GA1.1.<id>.<time>), or a stable random one for this session. */
function ga4_client_id(): string
{
    if (!empty($_COOKIE['_ga']) && preg_match('/^GA\d\.\d\.(\d+\.\d+)$/', (string)$_COOKIE['_ga'], $m)) {
        return $m[1];
    }
    return $_SESSION['ga_cid'] ??= random_int(100000000, 999999999) . '.' . time();
}

/** session_id from the _ga_<stream> cookie (GS1.1.<sid>... or GS2.1.s<sid>$...) so the sale joins the visit. */
function ga4_session_id(): ?string
{
    $name = '_ga_' . preg_replace('/^G-/i', '', setting('ga4_measurement_id'));
    $v = (string)($_COOKIE[$name] ?? '');
    if (preg_match('/^GS1\.\d\.(\d+)\./', $v, $m) || preg_match('/^GS2\.\d\.s(\d+)/', $v, $m)) {
        return $m[1];
    }
    return null;
}

function ga4_queue_purchase(string $transactionId, array $items, float $value, ?string $coupon = null): void
{
    if (!ga4_enabled()) {
        return;
    }
    $params = array_filter([
        'transaction_id'       => $transactionId,
        'currency'             => 'BDT',
        'value'                => $value,
        'coupon'               => $coupon,
        'items'                => array_map(fn($i) => ['item_id' => (string)$i['id'], 'item_name' => $i['title'], 'price' => (float)$i['price'], 'quantity' => 1], $items),
        'session_id'           => ga4_session_id(),
        'engagement_time_msec' => 1,
    ], fn($v) => $v !== null && $v !== '');
    $u = current_user();
    $GLOBALS['ga4_queue'][] = array_filter([
        'client_id' => ga4_client_id(),
        'user_id'   => $u ? 'u' . $u['id'] : null,
        'events'    => [['name' => 'purchase', 'params' => $params]],
    ], fn($v) => $v !== null);

    if (empty($GLOBALS['ga4_shutdown'])) {
        $GLOBALS['ga4_shutdown'] = true;
        register_shutdown_function('ga4_flush');
    }
}

function ga4_flush(): void
{
    $batches = $GLOBALS['ga4_queue'] ?? [];
    $GLOBALS['ga4_queue'] = [];
    if (!$batches) {
        return;
    }
    finish_response_early();

    // "Debug" mode uses Google's validation endpoint: nothing is recorded, but errors are shown in Settings
    $debug = setting('ga4_debug') === '1';
    $base = $GLOBALS['config']['ga4_url'] ?? 'https://www.google-analytics.com';
    $endpoint = $base . ($debug ? '/debug/mp/collect' : '/mp/collect')
        . '?measurement_id=' . urlencode(setting('ga4_measurement_id')) . '&api_secret=' . urlencode(setting('ga4_api_secret'));

    foreach ($batches as $payload) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 4,
        ]);
        $body = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $tx = $payload['events'][0]['params']['transaction_id'] ?? '';
        if ($err || $code >= 300) {
            $status = 'ERROR ' . $code . ' · purchase ' . $tx . ' · ' . ($err ?: substr($body, 0, 200));
        } elseif ($debug) {
            $msgs = array_column(json_decode($body, true)['validationMessages'] ?? [], 'description');
            $status = ($msgs ? 'DEBUG সমস্যা · ' . implode(' | ', $msgs) : 'DEBUG OK (কোনো ভুল নেই — টেস্ট শেষে Debug বন্ধ করুন)') . ' · purchase ' . $tx;
        } else {
            $status = 'OK · purchase ' . $tx;
        }
        save_status('ga4_last_status', $status);
    }
}
