<?php
// router.php for `php -S`. Delegates everything to index.php unless the file exists.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) { return false; }
require __DIR__ . '/index.php';
