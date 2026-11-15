<?php
/** @var string $title */
$siteName = setting('site_name', 'Practical Khata');
$u = current_user();
$page = basename($_SERVER['SCRIPT_NAME']);
$cartN = cart_count();
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? $title . ' | ' . $siteName : $siteName . ' — ' . setting('tagline')) ?></title>
<meta name="description" content="<?= e($metaDesc ?? setting('tagline')) ?>">
<meta name="theme-color" content="#15803d">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta property="og:title" content="<?= e($title ?? $siteName) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=5">
<?php require __DIR__ . '/tracking_head.php'; ?>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body class="shop">
<?php require __DIR__ . '/tracking_body.php'; ?>
<div class="topbar">
  <div class="container topbar-in">
    <span><?= icon('zap', 14) ?> <?= e(setting('announcement', 'পেমেন্ট যাচাইয়ের পর সাথে সাথে ডাউনলোড — bKash / Nagad এ পেমেন্ট করুন')) ?></span>
    <?php if (setting('contact_phone')): ?>
      <a href="tel:<?= e(setting('contact_phone')) ?>" class="topbar-phone"><?= icon('phone', 14) ?> <?= e(setting('contact_phone')) ?></a>
    <?php endif; ?>
  </div>
</div>
<header class="site-header">
  <div class="container header-main">
    <a class="brand" href="<?= url() ?>">
      <span class="brand-mark">PK</span>
      <span class="brand-text"><?= e($siteName) ?><small><?= e(setting('tagline', 'ডিজিটাল খাতা ও নোট')) ?></small></span>
    </a>
    <form class="search" action="<?= url('index.php') ?>" method="get" role="search">
      <input type="search" name="q" placeholder="খাতা, নোট বা গাইড খুঁজুন…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="খুঁজুন">
      <button aria-label="খুঁজুন"><?= icon('search', 18) ?></button>
    </form>
    <nav class="header-icons">
      <a href="<?= url($u ? 'account.php' : 'login.php') ?>" class="hicon">
        <?= icon('user', 22) ?>
        <span><small><?= $u ? 'স্বাগতম' : 'লগইন /' ?></small><?= $u ? e(mb_strimwidth($u['name'], 0, 14, '…')) : 'রেজিস্টার' ?></span>
      </a>
      <a href="<?= url('cart.php') ?>" class="hicon hcart">
        <span class="cart-ico"><?= icon('cart', 22) ?><b><?= bn($cartN) ?></b></span>
        <span><small>আমার</small>কার্ট</span>
      </a>
    </nav>
  </div>
  <nav class="subnav">
    <div class="container subnav-in">
      <a href="<?= url() ?>" class="<?= $page === 'index.php' && empty($_GET) ? 'active' : '' ?>">হোম</a>
      <a href="<?= url('index.php?sort=new#products') ?>" class="<?= isset($_GET['sort']) && empty($_GET['offer']) ? 'active' : '' ?>">সব পণ্য</a>
      <a href="<?= url('index.php?offer=1#products') ?>" class="<?= !empty($_GET['offer']) ? 'active' : '' ?>"><?= icon('tag', 15) ?> অফার</a>
      <a href="<?= url($u ? 'account.php' : 'track.php') ?>" class="<?= in_array($page, ['account.php', 'order.php'], true) ? 'active' : '' ?>"><?= icon('download', 15) ?> আমার ডাউনলোড</a>
      <a href="<?= url('track.php') ?>" class="<?= $page === 'track.php' ? 'active' : '' ?>"><?= icon('search', 15) ?> অর্ডার ট্র্যাক</a>
      <?php if ($u && $u['role'] === 'admin'): ?><a href="<?= url('admin/') ?>" class="admin-link">অ্যাডমিন প্যানেল</a><?php endif; ?>
    </div>
  </nav>
</header>
<main class="container main">
<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
