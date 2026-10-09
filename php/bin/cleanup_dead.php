<?php
require __DIR__ . '/../src/autoload.php';

use App\Core\App;
use App\Support\XVideosDeadCleaner;

App::boot();
$result = XVideosDeadCleaner::run();
printf("[%s] dead-cleanup -> %s\n", gmdate('c'), $result['message']);
exit($result['ok'] ? 0 : 2);