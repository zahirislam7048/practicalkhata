<?php
require __DIR__ . '/_init.php';

$user = require_login();
$item = row(
    "SELECT oi.*, o.user_id, o.status, p.file_path, p.file_name, p.download_limit
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.id = ?",
    [(int)($_GET['item'] ?? 0)]
);

$admin = is_admin();
if (!$item || ((int)$item['user_id'] !== (int)$user['id'] && !$admin) || ($item['status'] !== 'paid' && !$admin)) {
    http_response_code(403);
    exit('এই ফাইল ডাউনলোডের অনুমতি নেই।');
}
$path = STORAGE_DIR . '/' . basename((string)$item['file_path']);
if (!$item['file_path'] || !is_file($path)) {
    http_response_code(404);
    exit('ফাইলটি পাওয়া যায়নি। সাপোর্টে যোগাযোগ করুন।');
}
if (!$admin) {
    // Atomic so a double-click can't go past the limit
    $ok = q('UPDATE order_items SET download_count = download_count + 1 WHERE id = ? AND download_count < ?',
            [$item['id'], $item['download_limit']])->rowCount();
    if (!$ok) {
        http_response_code(403);
        exit('ডাউনলোড সীমা শেষ। সাপোর্টে যোগাযোগ করুন।');
    }
}

$name = $item['file_name'] ?: basename($path);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename=\"" . str_replace(['"', "\r", "\n"], '', $name) . "\"; filename*=UTF-8''" . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
