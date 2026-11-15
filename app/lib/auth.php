<?php
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = !empty($_SESSION['uid'])
            ? row('SELECT id, name, email, phone, role FROM users WHERE id = ?', [$_SESSION['uid']])
            : null;
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('info', 'চালিয়ে যেতে লগইন করুন।');
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    return $u;
}

function require_admin(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
    }
    if ($u['role'] !== 'admin') {
        flash('error', 'এই পেজ শুধু অ্যাডমিনের জন্য।');
        redirect('account.php');
    }
    return $u;
}

/** Only allow redirects back into this site. */
function safe_next(string $next, string $fallback = 'account.php'): string
{
    return (str_starts_with($next, '/') && !str_starts_with($next, '//')) ? $next : url($fallback);
}

/** Simple login throttle: 5 failed tries per 10 minutes per session. */
function login_throttled(): bool
{
    $t = array_filter($_SESSION['login_fail'] ?? [], fn($ts) => $ts > time() - 600);
    $_SESSION['login_fail'] = $t;
    return count($t) >= 5;
}

function login_failed(): void
{
    $_SESSION['login_fail'][] = time();
}
