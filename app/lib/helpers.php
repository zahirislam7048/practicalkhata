<?php
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site-root-relative URL, e.g. url('cart.php') → /cart.php */
function url(string $path = ''): string
{
    $base = rtrim((string)($GLOBALS['config']['base_url'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('ফর্মের মেয়াদ শেষ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function money($amount): string
{
    $n = (float)$amount;
    return '৳' . (floor($n) == $n ? number_format($n) : number_format($n, 2));
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (rows('SELECT `key`, `value` FROM settings') as $r) {
                $cache[$r['key']] = (string)$r['value'];
            }
        } catch (Throwable $t) {
            // settings table not ready yet
        }
    }
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

function slugify(string $text): string
{
    $slug = mb_strtolower(trim($text));
    $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? mb_substr($slug, 0, 200) : 'product-' . time();
}

function unique_slug(string $title, ?int $ignoreId = null): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (val('SELECT id FROM products WHERE slug = ? AND id <> ?', [$slug, $ignoreId ?? 0])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function product_price(array $p): float
{
    return ($p['sale_price'] !== null && (float)$p['sale_price'] < (float)$p['price'])
        ? (float)$p['sale_price']
        : (float)$p['price'];
}

/** $size 'thumb' = small (~400px) version for product cards, 'full' = page image */
function product_image(array $p, string $size = 'full'): string
{
    if (!$p['image']) {
        return url('assets/img/placeholder.svg');
    }
    // Built-in images live in assets/, admin uploads in uploads/
    $rel = str_starts_with($p['image'], 'assets/') ? $p['image'] : 'uploads/' . $p['image'];
    if ($size === 'thumb') {
        $thumbRel = preg_replace('/\.[a-z]+$/i', '', $rel) . '-t.' . (function_exists('imagewebp') ? 'webp' : 'jpg');
        $thumbRel = preg_replace('#^(assets/img|uploads)/#', 'uploads/thumbs/', $thumbRel);
        if (is_file(PUBLIC_DIR . '/' . $thumbRel) || make_image(PUBLIC_DIR . '/' . $rel, PUBLIC_DIR . '/' . $thumbRel, 420)) {
            return url($thumbRel);
        }
    }
    return url($rel);
}

/**
 * Resize an image to fit $max px (keeps aspect) and save it. Output type follows the target extension.
 * Returns false when GD can't read it (the original is then used as-is).
 */
function make_image(string $src, string $dst, int $max, int $quality = 80): bool
{
    if (!function_exists('imagecreatetruecolor') || !is_file($src)) {
        return false;
    }
    $info = @getimagesize($src);
    if (!$info) {
        return false;
    }
    [$w, $h, $type] = $info;
    $img = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => @imagecreatefrompng($src),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($src),
        default        => false,
    };
    if (!$img) {
        return false;
    }
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // flatten transparency on white
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if (!is_dir(dirname($dst))) {
        @mkdir(dirname($dst), 0755, true);
    }
    $ok = str_ends_with($dst, '.webp') && function_exists('imagewebp')
        ? imagewebp($out, $dst, $quality)
        : imagejpeg($out, $dst, $quality + 2);
    imagedestroy($img);
    imagedestroy($out);
    return (bool)$ok;
}

function status_label(string $status): string
{
    return [
        'pending'  => 'যাচাই চলছে',
        'paid'     => 'Approved',
        'rejected' => 'Declined',
        'due'      => 'Payment Due',
    ][$status] ?? $status;
}

function method_label(string $m): string
{
    return ['bkash' => 'bKash', 'nagad' => 'Nagad', 'free' => 'ফ্রি'][$m] ?? $m;
}

function upload_error_text(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ফাইলটি অনেক বড়। cPanel → MultiPHP INI Editor থেকে upload_max_filesize বাড়ান।',
        UPLOAD_ERR_PARTIAL => 'ফাইল পুরোটা আপলোড হয়নি, আবার চেষ্টা করুন।',
        default => 'ফাইল আপলোড করা যায়নি (code ' . $code . ')।',
    };
}

/** Saves a product image into public/uploads. Returns the stored file name. */
function save_image_upload(array $f): string
{
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text($f['error']));
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext) {
        throw new RuntimeException('ছবি অবশ্যই JPG, PNG, WEBP বা GIF হতে হবে।');
    }
    if ($f['size'] > 15 * 1024 * 1024) {
        throw new RuntimeException('ছবি ১৫MB এর বেশি হতে পারবে না।');
    }
    $dir = PUBLIC_DIR . '/uploads';
    $base = date('Ym') . '-' . bin2hex(random_bytes(8));
    // Shrink big photos (phone pictures are often 3–5 MB) to max 1200px — much faster pages
    $outExt = function_exists('imagewebp') ? 'webp' : 'jpg';
    if ($ext !== 'gif' && make_image($f['tmp_name'], $dir . '/' . $base . '.' . $outExt, 1200, 82)) {
        @unlink($f['tmp_name']);
        return $base . '.' . $outExt;
    }
    $name = $base . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('ছবি সেভ করা যায়নি। uploads ফোল্ডারের permission দেখুন।');
    }
    return $name;
}

/** Saves a digital product file into private storage (outside public_html). */
function save_product_file(array $f): array
{
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text($f['error']));
    }
    $original = basename((string)$f['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'zip', 'rar', '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'mp3', 'mp4', 'epub', 'txt'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('এই ধরনের ফাইল অনুমোদিত নয়: .' . $ext);
    }
    if (!is_dir(STORAGE_DIR)) {
        mkdir(STORAGE_DIR, 0750, true);
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], STORAGE_DIR . '/' . $stored)) {
        throw new RuntimeException('ফাইল সেভ করা যায়নি। storage ফোল্ডারের permission দেখুন।');
    }
    return ['path' => $stored, 'name' => $original];
}

function has_upload(string $field): bool
{
    return isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE;
}

function paginate(int $total, int $perPage = 20): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage];
}

function page_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $qs = $_GET;
    $html = '<nav class="pager">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        $qs['page'] = $i;
        $cls = $i === $p['page'] ? ' class="active"' : '';
        $html .= '<a' . $cls . ' href="?' . e(http_build_query($qs)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Inline SVG icons (stroke style) */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'cart'     => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'home'     => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'zap'      => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'wallet'   => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M16 12h2M2 10h20"/>',
        'support'  => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
        'tag'      => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
        'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'fb'       => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'menu'     => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'chevron'  => '<path d="m9 18 6-6-6-6"/>',
        'trash'    => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ];
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function discount_percent(array $p): int
{
    $price = (float)$p['price'];
    $now = product_price($p);
    return ($price > 0 && $now < $price) ? (int)round(($price - $now) / $price * 100) : 0;
}

/** Bangla digits for display */
function bn(string|int $s): string
{
    return strtr((string)$s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

function whatsapp_link(string $text = ''): string
{
    $n = preg_replace('/\D+/', '', setting('whatsapp_number'));
    if ($n === '') {
        return '';
    }
    if (str_starts_with($n, '01')) {
        $n = '88' . $n;
    }
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}
