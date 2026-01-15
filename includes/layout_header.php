<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
?><!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($title ?? APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/style.css">
  <?php if (!empty($extra_css) && is_array($extra_css)): foreach ($extra_css as $css): ?>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?><?= e($css) ?>">
  <?php endforeach; endif; ?>
  <link rel="manifest" href="<?= e(BASE_URL) ?>/manifest.webmanifest">
<meta name="theme-color" content="#d6a400">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

</head>
<body data-baseurl="<?= e(BASE_URL) ?>">  
<div class="app">
  <header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/">
      <span class="brand__star">★</span>
      <span class="brand__text"><?= e(APP_NAME) ?></span>
    </a>
<nav class="nav">
  <a class="nav__link" href="<?= e(BASE_URL) ?>/">Trang chủ</a>
  <button class="nav__link nav__btn" id="installBtn" type="button">Tải app</button>
  <button class="nav__link nav__btn" id="offlineBtn" type="button" style="display:none">
  Tải nhạc offline <span id="offlineBadge" class="offline-badge" style="display:none">●</span>
</button>

  <a class="nav__link" href="<?= e(BASE_URL) ?>/admin">Admin</a>
</nav>

  </header>
