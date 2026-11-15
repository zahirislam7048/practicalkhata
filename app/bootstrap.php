<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('STORAGE_DIR', APP_DIR . '/storage/files');
define('CONFIG_FILE', APP_DIR . '/config.php');

$GLOBALS['config'] = is_file(CONFIG_FILE) ? require CONFIG_FILE : null;

$debug = !empty($GLOBALS['config']['debug']);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Dhaka');
mb_internal_encoding('UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('pk_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/shop.php';
require APP_DIR . '/lib/migrations.php';

// Every page except the installer needs a config.
if ($GLOBALS['config'] === null && !defined('PK_INSTALLER')) {
    header('Location: ' . (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : '') . 'install.php');
    exit;
}

if ($GLOBALS['config'] !== null && !defined('PK_INSTALLER')) {
    run_migrations();
}
