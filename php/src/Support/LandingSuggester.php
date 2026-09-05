<?php
namespace App\Support;

use App\Core\App;
use App\Models\Landing;

/**
 * Turns the freshest zero-result search queries into DRAFT landings (active=0).
 * Admin sees them in the dashboard and just clicks "Enable + Save" to publish.
 */
class LandingSuggester {
    public static function run(int $max = 3): array {
        $db = App::$db;
        // Pick zero-result queries from the last 7 days that don't already have a landing
        $rows = $db->query("
            SELECT sq.q, sq.count FROM search_queries sq
            WHERE sq.results_last = 0 AND sq.last_seen >= (NOW() - INTERVAL 7 DAY)
              AND NOT EXISTS (SELECT 1 FROM landings l WHERE LOWER(l.keyword) = sq.q)
            ORDER BY sq.count DESC, sq.last_seen DESC
            LIMIT " . (int)$max)->fetchAll();

        $created = [];
        foreach ($rows as $r) {
            $kw = $r['q'];
            $like = '%' . $kw . '%';
            $cs = $db->prepare("SELECT slug FROM categories WHERE name LIKE ? OR slug LIKE ? LIMIT 5");
            $cs->execute([$like, $like]);
            $cats = array_column($cs->fetchAll(), 'slug');
            $ts = $db->prepare("SELECT slug FROM tags WHERE name LIKE ? OR slug LIKE ? LIMIT 5");
            $ts->execute([$like, $like]);
            $tags = array_column($ts->fetchAll(), 'slug');

            $id = Landing::save([
                'title' => ucwords($kw) . ' videos — curated',
                'slug' => SourceManager::slugify($kw) . '-suggested-' . date('Ymd'),
                'keyword' => $kw,
                'intro' => "Auto-suggested landing for the trending search “{$kw}”. Review the categories/tags then flip Active to publish.",
                'categories' => $cats,
                'tags' => $tags,
                'active' => 0, // draft
                'template' => 'grid',
            ]);
            // Mark as suggested
            $db->prepare("UPDATE landings SET suggested=1 WHERE id=?")->execute([$id]);
            $created[] = ['id'=>$id, 'keyword'=>$kw, 'count'=>$r['count']];
        }
        return $created;
    }
}
