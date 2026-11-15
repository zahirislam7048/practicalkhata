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

function product_image(array $p): string
{
    if (!$p['image']) {
        return url('assets/img/placeholder.svg');
    }
    // Built-in images live in assets/, admin uploads in uploads/
    return str_starts_with($p['image'], 'assets/') ? url($p['image']) : url('uploads/' . $p['image']);
}

function status_label(string $status): string
{
    return [
        'pending'  => 'যাচাই চলছে',
        'paid'     => 'পেমেন্ট সফল',
        'rejected' => 'বাতিল',
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
    if ($f['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('ছবি ৫MB এর বেশি হতে পারবে না।');
    }
    $dir = PUBLIC_DIR . '/uploads';
    $name = date('Ym') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
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
