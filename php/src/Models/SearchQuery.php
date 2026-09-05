<?php
namespace App\Models;

use App\Core\App;

class SearchQuery {
    public static function log(string $q, int $results = 0): void {
        $q = mb_strtolower(trim($q));
        if ($q === '' || mb_strlen($q) > 191) return;
        $st = App::$db->prepare("INSERT INTO search_queries (q, count, results_last, last_seen) VALUES (?, 1, ?, NOW())
            ON DUPLICATE KEY UPDATE count = count + 1, results_last = VALUES(results_last), last_seen = NOW()");
        $st->execute([$q, $results]);
    }

    public static function top(int $limit = 20): array {
        $st = App::$db->prepare("SELECT q, count, results_last, last_seen FROM search_queries ORDER BY count DESC, last_seen DESC LIMIT ?");
        $st->bindValue(1, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public static function stats(): array {
        $db = App::$db;
        return [
            'total_unique' => (int)$db->query("SELECT COUNT(*) FROM search_queries")->fetchColumn(),
            'total_searches' => (int)$db->query("SELECT COALESCE(SUM(count),0) FROM search_queries")->fetchColumn(),
            'zero_results' => (int)$db->query("SELECT COUNT(*) FROM search_queries WHERE results_last = 0")->fetchColumn(),
        ];
    }
}
