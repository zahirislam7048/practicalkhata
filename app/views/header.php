<?php
/** @var string $title */
$siteName = setting('site_name', 'Practical Khata');
$u = current_user();
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? $title . ' | ' . $siteName : $siteName . ' — ' . setting('tagline')) ?></title>
<meta name="description" content="<?= e($metaDesc ?? setting('tagline')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=1">
<?php require __DIR__ . '/tracking_head.php'; ?>
<link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body>
<?php require __DIR__ . '/tracking_body.php'; ?>
<header class="site-header">
  <div class="container nav">
    <a class="brand" href="<?= url() ?>">
      <span class="brand-mark">PK</span>
      <span><?= e($siteName) ?></span>
    </a>
    <form class="search" action="<?= url('index.php') ?>" method="get">
      <input type="search" name="q" placeholder="খাতা বা নোট খুঁজুন…" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
    <nav class="nav-links">
      <a href="<?= url('cart.php') ?>" class="cart-link">কার্ট <span class="badge"><?= cart_count() ?></span></a>
      <?php if ($u): ?>
        <?php if ($u['role'] === 'admin'): ?><a href="<?= url('admin/') ?>">অ্যাডমিন</a><?php endif; ?>
        <a href="<?= url('account.php') ?>">আমার অ্যাকাউন্ট</a>
      <?php else: ?>
        <a href="<?= url('login.php') ?>">লগইন</a>
        <a class="btn btn-sm" href="<?= url('register.php') ?>">রেজিস্টার</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container main">
<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
