<?php
// PSR-4 autoloader for App\
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base = __DIR__ . '/../src/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $rel = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) require $file;
});
