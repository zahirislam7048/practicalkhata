<?php
/** @var string $title */
$siteName = setting('site_name', 'Practical Khata');
$u = current_user();
$page = basename($_SERVER['SCRIPT_NAME']);
$cartN = cart_count();
$cats = categories();
$curCat = (string)($_GET['cat'] ?? '');
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? $title . ' | ' . $siteName : $siteName . ' — ' . setting('tagline')) ?></title>
<meta name="description" content="<?= e($metaDesc ?? setting('tagline')) ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta property="og:title" content="<?= e($title ?? $siteName) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap"></noscript>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=15">
<style><?= theme_css() ?></style>
<?php require __DIR__ . '/tracking_head.php'; ?>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body class="shop">
<?php require __DIR__ . '/tracking_body.php'; ?>
<div class="topbar">
  <div class="container topbar-in">
    <span class="topbar-msg"><?= icon('zap', 14) ?> <?= e(setting('announcement', 'পেমেন্ট যাচাইয়ের পর সাথে সাথে ডাউনলোড — bKash / Nagad এ পেমেন্ট করুন')) ?></span>
    <nav class="topbar-links">
      <a href="<?= url('track.php') ?>">অর্ডার ট্র্যাক</a>
      <?php if (setting('contact_phone')): ?><a href="tel:<?= e(setting('contact_phone')) ?>"><?= icon('phone', 13) ?> <?= e(setting('contact_phone')) ?></a><?php endif; ?>
      <?php if ($u && $u['role'] === 'admin'): ?><a href="<?= url('admin/') ?>">অ্যাডমিন</a><?php endif; ?>
    </nav>
  </div>
</div>
<header class="site-header">
  <div class="container header-main">
    <a class="brand" href="<?= url() ?>">
      <span class="brand-mark">PK</span>
      <span class="brand-text"><?= e($siteName) ?></span>
    </a>
    <form class="search" action="<?= url('index.php') ?>" method="get" role="search">
      <select name="cat" aria-label="ক্যাটাগরি">
        <option value="">সব ক্যাটাগরি</option>
        <?php foreach ($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $curCat === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="search" name="q" placeholder="খাতা, নোট বা গাইড খুঁজুন…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="খুঁজুন">
      <button aria-label="খুঁজুন"><?= icon('search', 20) ?></button>
    </form>
    <nav class="header-icons">
      <a href="<?= url($u ? 'account.php' : 'login.php') ?>" class="hicon">
        <?= icon('user', 24) ?>
        <span><small><?= $u ? 'স্বাগতম' : 'অ্যাকাউন্ট' ?></small><?= $u ? e(mb_strimwidth($u['name'], 0, 12, '…')) : 'লগইন' ?></span>
      </a>
      <a href="<?= url('cart.php') ?>" class="hicon hcart">
        <span class="cart-ico"><?= icon('cart', 24) ?><b><?= bn($cartN) ?></b></span>
        <span><small>আমার</small>কার্ট</span>
      </a>
    </nav>
  </div>
  <nav class="catnav">
    <div class="container catnav-in">
      <div class="allcats">
        <button type="button" class="allcats-btn" aria-haspopup="true"><?= icon('menu', 18) ?> সব ক্যাটাগরি</button>
        <div class="allcats-menu">
          <?php foreach ($cats as $c): ?>
            <a href="<?= url('index.php?cat=' . urlencode($c['slug'])) ?>"><span><?= e((string)$c['icon']) ?></span> <?= e($c['name']) ?> <small><?= bn((int)$c['n']) ?></small></a>
          <?php endforeach; ?>
          <a href="<?= url('index.php?sort=new#products') ?>"><span>🛍️</span> সব পণ্য</a>
        </div>
      </div>
      <a href="<?= url('index.php?offer=1#products') ?>" class="hot <?= !empty($_GET['offer']) ? 'active' : '' ?>">🔥 অফার</a>
      <a href="<?= url('index.php?sort=new#products') ?>" class="<?= ($_GET['sort'] ?? '') === 'new' ? 'active' : '' ?>">নতুন</a>
      <a href="<?= url('index.php?sort=popular#products') ?>" class="<?= ($_GET['sort'] ?? '') === 'popular' ? 'active' : '' ?>">জনপ্রিয়</a>
      <?php foreach (array_slice($cats, 0, 4) as $c): ?>
        <a href="<?= url('index.php?cat=' . urlencode($c['slug'])) ?>" class="<?= $curCat === $c['slug'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="<?= url($u ? 'account.php' : 'track.php') ?>" class="catnav-right"><?= icon('download', 15) ?> আমার ডাউনলোড</a>
    </div>
  </nav>
</header>
<main class="container main">
<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
