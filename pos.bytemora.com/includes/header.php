<?php
/**
 * Shared page header. Expects $pageTitle to be set by the including page.
 * Also expects require_login() to already have been called.
 */
$pageTitle = $pageTitle ?? APP_NAME;
$flash = flash_get();
$currentScript = basename($_SERVER['SCRIPT_NAME']);

$navItems = [
    'index.php'     => 'Dashboard',
    'billing.php'   => 'Billing',
    'products.php'  => 'Products',
    'stock_in.php'  => 'Stock In',
    'stock_out.php' => 'Stock Out',
    'customers.php' => 'Customers',
    'vendors.php'   => 'Vendors',
    'reports.php'   => 'Reports',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle) ?> - <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <header class="topbar">
    <div class="brand"><?= h(APP_NAME) ?></div>
    <nav class="main-nav">
      <?php foreach ($navItems as $file => $label): ?>
        <a href="<?= h($file) ?>" class="<?= $currentScript === $file ? 'active' : '' ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="user-box">
      <span><?= h(current_user_name()) ?></span>
      <a href="logout.php" class="btn btn-small btn-outline">Logout</a>
    </div>
  </header>
  <main class="content">
    <h1 class="page-title"><?= h($pageTitle) ?></h1>
    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>
