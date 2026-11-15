<?php
/*
 * Email sending without extra libraries.
 * Uses SMTP (cPanel email account) when Admin → Settings has smtp_host/user/pass,
 * otherwise PHP mail() (works on cPanel, but more likely to land in spam).
 */

function mail_from(): array
{
    $email = setting('mail_from_email', setting('smtp_user', 'no-reply@' . preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost')));
    return [$email, setting('mail_from_name', setting('site_name', 'Practical Khata'))];
}

function mime_header(string $s): string
{
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/** Returns null on success, or an error message. */
function send_mail(string $to, string $subject, string $html, string $text = ''): ?string
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'ইমেইল ঠিকানা সঠিক নয়';
    }
    [$fromEmail, $fromName] = mail_from();
    $text = $text ?: trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>|<\/h\d>|<\/tr>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $boundary = 'pk' . bin2hex(random_bytes(8));
    $domain = substr(strrchr($fromEmail, '@'), 1) ?: 'localhost';

    $headers = [
        'Date: ' . date('r'),
        'From: ' . mime_header($fromName) . ' <' . $fromEmail . '>',
        'Reply-To: ' . (setting('contact_email') ?: $fromEmail),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text))
          . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html))
          . "--$boundary--\r\n";

    if (setting('smtp_host') !== '' && setting('smtp_user') !== '') {
        return smtp_send($fromEmail, $to, array_merge(['To: ' . $to, 'Subject: ' . mime_header($subject)], $headers), $body);
    }
    $ok = @mail($to, mime_header($subject), $body, implode("\r\n", $headers), '-f' . $fromEmail);
    return $ok ? null : 'mail() ব্যর্থ — সেটিংসে SMTP দিন';
}

function smtp_send(string $from, string $to, array $headers, string $body): ?string
{
    $host = setting('smtp_host');
    $port = (int)(setting('smtp_port') ?: 465);
    $secure = setting('smtp_secure', $port === 465 ? 'ssl' : 'tls');
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return "SMTP সংযোগ হয়নি ($host:$port) — $errstr";
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $c, array $okCodes) use ($fp, $read): ?string {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        return in_array((int)substr($r, 0, 3), $okCodes, true) ? null : trim($r);
    };
    try {
        if ((int)substr($read(), 0, 3) !== 220) {
            return 'SMTP সার্ভার সাড়া দেয়নি';
        }
        $ehlo = 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if ($e = $cmd($ehlo, [250])) {
            return 'EHLO: ' . $e;
        }
        if ($secure === 'tls') {
            if ($e = $cmd('STARTTLS', [220])) {
                return 'STARTTLS: ' . $e;
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return 'TLS চালু করা যায়নি';
            }
            $cmd($ehlo, [250]);
        }
        if ($e = $cmd('AUTH LOGIN', [334])) {
            return 'AUTH: ' . $e;
        }
        if ($cmd(base64_encode(setting('smtp_user')), [334]) || ($e = $cmd(base64_encode(setting('smtp_pass')), [235]))) {
            return 'SMTP লগইন ব্যর্থ — ইউজার/পাসওয়ার্ড দেখুন' . (isset($e) ? " ($e)" : '');
        }
        if ($e = $cmd("MAIL FROM:<$from>", [250])) {
            return 'MAIL FROM: ' . $e;
        }
        if ($e = $cmd("RCPT TO:<$to>", [250, 251])) {
            return 'RCPT TO: ' . $e;
        }
        if ($e = $cmd('DATA', [354])) {
            return 'DATA: ' . $e;
        }
        $msg = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $msg = preg_replace('/^\./m', '..', $msg); // dot-stuffing
        if ($e = $cmd($msg . "\r\n.", [250])) {
            return 'পাঠানো যায়নি: ' . $e;
        }
        $cmd('QUIT', [221]);
        return null;
    } finally {
        fclose($fp);
    }
}

/** Absolute link to the secret order/download page. */
function order_link(array $o): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $base = setting('site_url') ?: (($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return rtrim($base, '/') . url('order.php?t=' . $o['access_token']);
}

/** The customer's email for an order (account email or the one given at guest checkout). */
function order_email(array $o): string
{
    if (!empty($o['user_id'])) {
        $e = (string)val('SELECT email FROM users WHERE id = ?', [$o['user_id']]);
        if ($e !== '') {
            return $e;
        }
    }
    return (string)($o['guest_email'] ?? '');
}

function order_customer_name(array $o): string
{
    if (!empty($o['user_id'])) {
        return (string)val('SELECT name FROM users WHERE id = ?', [$o['user_id']]);
    }
    return (string)($o['guest_name'] ?? '');
}

/**
 * Email the download link for a paid order. Records the result on the order.
 * Returns null on success, or an error message (also when the customer gave no email).
 */
function send_download_email(int $orderId): ?string
{
    $o = row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o || $o['status'] !== 'paid') {
        return 'অর্ডারটি Approved নয়';
    }
    $to = order_email($o);
    if ($to === '') {
        return 'কাস্টমার ইমেইল দেননি';
    }
    $items = rows('SELECT title, price FROM order_items WHERE order_id = ?', [$orderId]);
    $site = setting('site_name', 'Practical Khata');
    $link = order_link($o);
    $name = order_customer_name($o);
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . e($it['title']) . '</td><td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right">' . money($it['price']) . '</td></tr>';
    }
    $html = '<!doctype html><html><body style="margin:0;background:#f4f7f2;font-family:Arial,sans-serif;color:#1c2b22">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px">'
        . '<div style="background:#15803d;color:#fff;padding:18px 24px;border-radius:12px 12px 0 0;border-bottom:4px solid #facc15"><b style="font-size:20px">' . e($site) . '</b></div>'
        . '<div style="background:#fff;padding:24px;border-radius:0 0 12px 12px">'
        . '<h2 style="margin:0 0 8px;color:#166534">আপনার ফাইল প্রস্তুত! ✅</h2>'
        . '<p>' . ($name ? e($name) . ', ' : '') . 'আপনার অর্ডার <b>#' . e($o['order_no']) . '</b> এর পেমেন্ট যাচাই হয়েছে। নিচের বাটনে চাপ দিয়ে ডাউনলোড করুন।</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:12px 0">' . $rows
        . '<tr><td style="padding:8px 0"><b>মোট</b></td><td style="padding:8px 0;text-align:right"><b>' . money($o['total']) . '</b></td></tr></table>'
        . '<p style="text-align:center;margin:24px 0"><a href="' . e($link) . '" style="background:#facc15;color:#052e16;text-decoration:none;font-weight:bold;padding:14px 28px;border-radius:10px;display:inline-block">⬇ ডাউনলোড করুন</a></p>'
        . '<p style="font-size:13px;color:#64748b">বাটন কাজ না করলে এই লিংকটি ব্রাউজারে খুলুন:<br><a href="' . e($link) . '" style="color:#15803d;word-break:break-all">' . e($link) . '</a></p>'
        . '<p style="font-size:13px;color:#64748b">এই লিংকটি শুধু আপনার জন্য — কারো সাথে শেয়ার করবেন না। নির্দিষ্ট সংখ্যক বার ডাউনলোড করা যাবে।</p>'
        . (setting('contact_phone') ? '<p style="font-size:13px;color:#64748b">যেকোনো সমস্যায়: ' . e(setting('contact_phone')) . '</p>' : '')
        . '</div><p style="text-align:center;font-size:12px;color:#94a3b8">© ' . date('Y') . ' ' . e($site) . '</p></div></body></html>';

    $err = send_mail($to, '✅ ডাউনলোড লিংক — অর্ডার #' . $o['order_no'] . ' | ' . $site, $html);
    q('UPDATE orders SET email_sent_at = ?, email_error = ? WHERE id = ?', [$err ? null : date('Y-m-d H:i:s'), $err ? mb_substr($err, 0, 250) : null, $orderId]);
    return $err;
}

/** Ready-made WhatsApp message with the download link (for customers without email). */
function order_whatsapp_link(array $o): string
{
    $phone = preg_replace('/\D+/', '', (string)($o['guest_phone'] ?: ($o['sender_number'] ?? '')));
    if (!empty($o['user_id'])) {
        $phone = preg_replace('/\D+/', '', (string)val('SELECT phone FROM users WHERE id = ?', [$o['user_id']])) ?: $phone;
    }
    if (str_starts_with($phone, '01')) {
        $phone = '88' . $phone;
    }
    $msg = 'আসসালামু আলাইকুম' . (order_customer_name($o) ? ' ' . order_customer_name($o) : '') . ', আপনার অর্ডার #' . $o['order_no']
         . ' এর পেমেন্ট যাচাই হয়েছে ✅' . "\n\nডাউনলোড লিংক:\n" . order_link($o) . "\n\nধন্যবাদ — " . setting('site_name', 'Practical Khata');
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg);
}
