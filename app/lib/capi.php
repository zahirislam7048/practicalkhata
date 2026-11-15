<?php
/*
 * Meta Conversions API (server-side tracking).
 * Every browser Pixel event gets an event_id; the same event is sent from the server with the
 * same event_id, so Meta de-duplicates them. Sending happens after the page has been delivered
 * (fastcgi_finish_request / litespeed_finish_request) so visitors never wait for Facebook.
 *
 * Admin → Settings: capi_token (required), capi_test_code (optional, for Events Manager → Test events).
 */

const PK_PIXEL_DEFAULT = '1354603696469186';

function pixel_id(): string
{
    return preg_replace('/\D+/', '', setting('pixel_id', PK_PIXEL_DEFAULT));
}

function capi_enabled(): bool
{
    return setting('capi_token') !== '' && pixel_id() !== '';
}

function new_event_id(string $prefix = 'ev'): string
{
    return $prefix . '.' . bin2hex(random_bytes(8));
}

/** _fbc cookie, or build it from ?fbclid= on the landing page (and remember it). */
function capi_fbc(): string
{
    if (!empty($_COOKIE['_fbc'])) {
        return (string)$_COOKIE['_fbc'];
    }
    if (!empty($_GET['fbclid'])) {
        $_SESSION['fbc'] = 'fb.1.' . (int)(microtime(true) * 1000) . '.' . preg_replace('/[^\w\-]/', '', (string)$_GET['fbclid']);
    }
    return (string)($_SESSION['fbc'] ?? '');
}

function capi_hash(string $v): ?string
{
    $v = trim($v);
    return $v === '' ? null : hash('sha256', $v);
}

function capi_user_data(): array
{
    $c = tracking_customer();
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
    $ip = trim(explode(',', (string)$ip)[0]);
    $externalId = $c['id'] !== '' ? 'u' . $c['id'] : ($c['phone'] !== '' ? 'p' . $c['phone'] : session_id());
    return array_filter([
        'em'                => capi_hash($c['email']),
        'ph'                => capi_hash($c['phone']),
        'fn'                => capi_hash($c['first']),
        'ln'                => capi_hash($c['last']),
        'country'           => capi_hash($c['phone'] !== '' || $c['email'] !== '' ? 'bd' : ''),
        'external_id'       => capi_hash($externalId),
        'client_ip_address' => $ip ?: null,
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'fbp'               => $_COOKIE['_fbp'] ?? null,
        'fbc'               => capi_fbc() ?: null,
    ], fn($v) => $v !== null && $v !== '');
}

function current_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
}

/** Queue one server event; it is sent after the response is finished. */
function capi_queue(string $event, string $eventId, array $customData = []): void
{
    if (!capi_enabled()) {
        return;
    }
    $GLOBALS['capi_queue'][] = array_filter([
        'event_name'       => $event,
        'event_time'       => time(),
        'event_id'         => $eventId,
        'event_source_url' => $_SERVER['HTTP_REFERER'] ?? null,
        'action_source'    => 'website',
        'user_data'        => capi_user_data(),
        'custom_data'      => $customData ?: null,
    ], fn($v) => $v !== null);
    // Page URL is better than the referrer when we have it
    $GLOBALS['capi_queue'][array_key_last($GLOBALS['capi_queue'])]['event_source_url'] = current_url();

    if (empty($GLOBALS['capi_shutdown'])) {
        $GLOBALS['capi_shutdown'] = true;
        register_shutdown_function('capi_flush');
    }
}

function capi_flush(): void
{
    $events = $GLOBALS['capi_queue'] ?? [];
    $GLOBALS['capi_queue'] = [];
    if (!$events) {
        return;
    }
    // Let the visitor's page finish first
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
    }

    $payload = ['data' => $events];
    if (setting('capi_test_code') !== '') {
        $payload['test_event_code'] = setting('capi_test_code');
    }
    $endpoint = ($GLOBALS['config']['capi_url'] ?? 'https://graph.facebook.com/v21.0')
        . '/' . pixel_id() . '/events?access_token=' . urlencode(setting('capi_token'));

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // Keep the latest result so the admin can see whether it works
    $names = implode(', ', array_column($events, 'event_name'));
    if ($code === 200) {
        $status = 'OK · ' . $names;
    } else {
        $msg = $err ?: (json_decode((string)$body, true)['error']['message'] ?? substr((string)$body, 0, 200));
        $status = 'ERROR ' . $code . ' · ' . $names . ' · ' . $msg;
    }
    try {
        q('INSERT INTO settings (`key`, `value`) VALUES (\'capi_last_status\', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
          [date('d M Y h:i:s A') . ' — ' . $status]);
    } catch (Throwable $t) {
    }
}
