<?php use App\Core\View; ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — <?= View::e($title ?? 'DAVISPORN') ?></title>
<link rel="stylesheet" href="/assets/app.css">
</head><body class="admin-body">
<header class="topbar">
  <div class="wrap">
    <a href="/" class="brand">DAVIS<span>PORN</span> <em style="color:#8f96a3;font-style:normal;font-weight:500">/ admin</em></a>
    <nav class="mainnav"><a href="/admin">Dashboard</a><a href="/">View site</a></nav>
  </div>
</header>
<main class="wrap main"><?= $content ?></main>
</body></html>
