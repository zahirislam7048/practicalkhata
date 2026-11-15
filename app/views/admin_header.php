<?php
$admin = require_admin();
$current = basename($_SERVER['SCRIPT_NAME']);
$pendingCount = (int)val("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$menu = [
    'index.php'     => 'ড্যাশবোর্ড',
    'orders.php'    => 'অর্ডার',
    'products.php'  => 'পণ্য',
    'coupons.php'   => 'কুপন',
    'customers.php' => 'কাস্টমার',
    'settings.php'  => 'সেটিংস',
];
$active = ['order.php' => 'orders.php', 'product-edit.php' => 'products.php', 'coupon-edit.php' => 'coupons.php'][$current] ?? $current;
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e(($title ?? 'অ্যাডমিন') . ' | ' . setting('site_name', 'Practical Khata')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=4">
</head>
<body class="admin">
<aside class="admin-side">
  <a class="brand" href="<?= url('admin/') ?>"><span class="brand-mark">PK</span> অ্যাডমিন</a>
  <nav>
    <?php foreach ($menu as $file => $label): ?>
      <a href="<?= url('admin/' . $file) ?>" class="<?= $active === $file ? 'active' : '' ?>">
        <?= e($label) ?>
        <?php if ($file === 'orders.php' && $pendingCount): ?><span class="badge badge-warn"><?= $pendingCount ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
    <hr>
    <a href="<?= url() ?>" target="_blank">সাইট দেখুন ↗</a>
    <a href="<?= url('logout.php') ?>">লগআউট</a>
  </nav>
</aside>
<main class="admin-main">
<h1><?= e($title ?? '') ?></h1>
<?php foreach (flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
