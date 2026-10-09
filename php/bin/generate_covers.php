<?php
require __DIR__ . '/../src/autoload.php';

use App\Core\App;
use App\Support\CoverGenerator;

App::boot();
$ids = array_values(array_filter(array_map('intval', array_slice($argv, 1))));
foreach ($ids as $id) {
    try {
        $path = CoverGenerator::generate($id);
        printf("[%s] landing #%d -> %s\n", gmdate('c'), $id, $path);
    } catch (Throwable $exception) {
        fprintf(STDERR, "[%s] landing #%d failed: %s\n", gmdate('c'), $id, $exception->getMessage());
    }
}