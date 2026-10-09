<?php
require __DIR__ . '/../src/autoload.php';

use App\Core\App;
use App\Support\XVideosDeadCleaner;

App::boot();
$mode = $argv[1] ?? 'week';
$result = XVideosDeadCleaner::run($mode);
printf("[%s] dead-cleanup mode=%s -> %s\n", gmdate('c'), $mode, $result['message']);
exit($result['ok'] ? 0 : 2);