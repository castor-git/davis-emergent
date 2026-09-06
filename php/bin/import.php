<?php
// CLI import runner: php bin/import.php <source-slug> [limit]
require __DIR__ . '/../src/autoload.php';

use App\Core\App;
use App\Support\SourceManager;

$slug = $argv[1] ?? '';
$limit = (int)($argv[2] ?? 0);
if ($slug === '') { fwrite(STDERR, "usage: import.php <slug> [limit]\n"); exit(1); }

App::boot();
$limit = $limit > 0 ? $limit : SourceManager::limitFor($slug, 300);
$t = microtime(true);
$r = SourceManager::import($slug, $limit);
printf("[%s] %s limit=%d -> %s (%.1fs)\n", gmdate('c'), $slug, $limit, $r['message'], microtime(true) - $t);
exit($r['ok'] ? 0 : 2);
