<?php
namespace App\Support;

use App\Core\App;
use App\Adapters\SourceAdapter;
use App\Adapters\DemoAdapter;
use App\Adapters\UporniaCsvAdapter;
use App\Adapters\XVideosCsvAdapter;
use App\Adapters\XnxxRapidApiAdapter;
use App\Support\UporniaDeletedCleaner;

class SourceManager {
    public static function adapter(string $slug): SourceAdapter {
        return match ($slug) {
            'demo' => new DemoAdapter(),
            'upornia_csv' => new UporniaCsvAdapter(),
            'xvideos_csv' => new XVideosCsvAdapter(),
            'xnxx_rapidapi' => new XnxxRapidApiAdapter(),
            default => throw new \InvalidArgumentException("Unknown source: {$slug}"),
        };
    }

    /** Effective row limit for a source: admin-configured import_limit, else the caller's fallback. */
    public static function limitFor(string $slug, int $fallback): int {
        $cfg = (int)App::config("sources.{$slug}.import_limit", 0);
        $maximum = $slug === 'upornia_csv' ? 10000 : 5000;
        return $cfg > 0 ? min($cfg, $maximum) : $fallback;
    }

    /** Spawn bin/import.php in the background so long feeds never block the web server or the cron ack. */
    public static function importAsync(string $slug, int $limit): void {
        $root = dirname(__DIR__, 2);
        App::$db->prepare("UPDATE sources SET last_status=? WHERE slug=?")->execute(['RUNNING… started ' . gmdate('H:i:s') . ' UTC', $slug]);
        $cmd = sprintf('nohup %s %s %s %d >> %s 2>&1 &',
            escapeshellarg(PHP_BINARY ?: 'php'), escapeshellarg($root . '/bin/import.php'),
            escapeshellarg($slug), $limit, escapeshellarg($root . '/storage/logs/import.log'));
        exec($cmd);
    }

    public static function import(string $slug, int $limit = 100): array {
        $db = App::$db;
        $locked = false;
        if ($slug === 'upornia_csv') {
            $locked = (int)$db->query("SELECT GET_LOCK('upornia_import', 0)")->fetchColumn() === 1;
            if (!$locked) {
                $message = 'SKIPPED — another Upornia import is already running';
                return ['ok' => true, 'inserted' => 0, 'updated' => 0, 'message' => $message];
            }
        }
        try {
            $adapter = self::adapter($slug);
            $items = $adapter->fetch($limit);
            $inserted = 0; $updated = 0;
            foreach ($items as $v) {
                $title = mb_substr(trim($v['title']) ?: 'Untitled', 0, 255);
                $slugStr = mb_substr(self::slugify($title), 0, 150) . '-' . substr(md5($v['source'].$v['source_video_id']), 0, 6);
                $st = $db->prepare("INSERT INTO videos (source, source_video_id, slug, title, description, thumbnail, preview, embed_url, page_url, duration, views, rating, quality, is_featured, published_at)
                    VALUES (:source,:sid,:slug,:title,:desc,:thumb,:preview,:embed,:page,:dur,:views,:rating,:quality,:feat,:pub)
                    ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), views=VALUES(views), rating=VALUES(rating), thumbnail=VALUES(thumbnail), preview=VALUES(preview), embed_url=VALUES(embed_url), page_url=VALUES(page_url), duration=VALUES(duration), published_at=VALUES(published_at), is_available=1, unavailable_at=NULL");
                $st->execute([
                    ':source'=>$v['source'], ':sid'=>mb_substr((string)$v['source_video_id'], 0, 191), ':slug'=>$slugStr,
                    ':title'=>$title, ':desc'=>mb_substr($v['description'] ?? '', 0, 5000),
                    ':thumb'=>mb_substr($v['thumbnail'] ?? '', 0, 500), ':preview'=>mb_substr($v['preview'] ?? '', 0, 500),
                    ':embed'=>mb_substr($v['embed_url'] ?? '', 0, 500), ':page'=>mb_substr($v['page_url'] ?? '', 0, 500),
                    ':dur'=>(int)($v['duration'] ?? 0), ':views'=>(int)($v['views'] ?? 0),
                    ':rating'=>(float)($v['rating'] ?? 0), ':quality'=>$v['quality'] ?? 'HD',
                    ':feat'=>(int)($v['is_featured'] ?? 0),
                    ':pub'=>$v['published_at'] ?? date('Y-m-d H:i:s'),
                ]);
                $vid = (int)($db->lastInsertId() ?: 0);
                if (!$vid) {
                    $vid = (int)$db->query("SELECT id FROM videos WHERE source=".$db->quote($v['source'])." AND source_video_id=".$db->quote($v['source_video_id']))->fetchColumn();
                    $updated++;
                } else { $inserted++; }
                self::syncTaxonomy($vid, $v['categories'] ?? [], $v['tags'] ?? []);
                self::syncCredits($vid, $v['actors'] ?? [], $v['studios'] ?? []);
            }
            $hidden = 0;
            if ($slug === 'upornia_csv') {
                $hidden = UporniaDeletedCleaner::run();
            }
            self::recount();
            $status = "OK — inserted {$inserted}, updated {$updated}";
            if ($slug === 'upornia_csv') {
                $status .= ", hidden {$hidden} deleted";
            }
            $upd = $db->prepare("UPDATE sources SET last_import_at=NOW(), last_status=? WHERE slug=?");
            $upd->execute([$status, $slug]);
            \App\Core\Cache::forget();
            return ['ok'=>true,'inserted'=>$inserted,'updated'=>$updated,'message'=>$status];
        } catch (\Throwable $e) {
            $upd = $db->prepare("UPDATE sources SET last_status=? WHERE slug=?");
            $upd->execute(['ERROR: ' . $e->getMessage(), $slug]);
            return ['ok'=>false,'message'=>$e->getMessage()];
        } finally {
            if ($locked) {
                $db->query("SELECT RELEASE_LOCK('upornia_import')");
            }
        }
    }

    protected static function syncTaxonomy(int $videoId, array $cats, array $tags): void {
        $db = App::$db;
        foreach (array_unique($cats) as $name) {
            $name = mb_substr(trim($name), 0, 120); if ($name === '') continue;
            $slug = self::slugify($name);
            $db->prepare("INSERT IGNORE INTO categories (slug, name) VALUES (?, ?)")->execute([$slug, $name]);
            $cid = (int)$db->query("SELECT id FROM categories WHERE slug=".$db->quote($slug))->fetchColumn();
            $db->prepare("INSERT IGNORE INTO video_categories (video_id, category_id) VALUES (?, ?)")->execute([$videoId, $cid]);
        }
        foreach (array_unique($tags) as $name) {
            $name = mb_substr(trim($name), 0, 120); if ($name === '') continue;
            $slug = self::slugify($name);
            $db->prepare("INSERT IGNORE INTO tags (slug, name) VALUES (?, ?)")->execute([$slug, $name]);
            $tid = (int)$db->query("SELECT id FROM tags WHERE slug=".$db->quote($slug))->fetchColumn();
            $db->prepare("INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)")->execute([$videoId, $tid]);
        }
    }

    public static function recount(): void {
        $db = App::$db;
        $db->exec("UPDATE categories c SET video_count = (SELECT COUNT(*) FROM video_categories vc JOIN videos v ON v.id=vc.video_id WHERE vc.category_id = c.id AND v.is_available=1)");
        $db->exec("UPDATE tags t SET video_count = (SELECT COUNT(*) FROM video_tags vt JOIN videos v ON v.id=vt.video_id WHERE vt.tag_id = t.id AND v.is_available=1)");
        $db->exec("UPDATE actors a SET video_count = (SELECT COUNT(*) FROM video_actors va JOIN videos v ON v.id=va.video_id WHERE va.actor_id=a.id AND v.is_available=1)");
        $db->exec("UPDATE studios s SET video_count = (SELECT COUNT(*) FROM video_studios vs JOIN videos v ON v.id=vs.video_id WHERE vs.studio_id=s.id AND v.is_available=1)");
        // Feeds carry no "featured" flag — promote the 12 most-viewed recent videos with an embed
        $db->exec("UPDATE videos SET is_featured=0 WHERE is_featured=1");
        $db->exec("UPDATE videos SET is_featured=1 WHERE embed_url<>'' AND is_available=1 ORDER BY views DESC, published_at DESC LIMIT 12");
    }

    /** Remove seeded demo videos (and now-orphaned taxonomies) once real feeds are live. */
    public static function purgeDemo(): int {
        $db = App::$db;
        $db->exec("DELETE vc FROM video_categories vc JOIN videos v ON v.id=vc.video_id WHERE v.source='demo'");
        $db->exec("DELETE vt FROM video_tags vt JOIN videos v ON v.id=vt.video_id WHERE v.source='demo'");
        $n = $db->exec("DELETE FROM videos WHERE source='demo'");
        self::recount();
        $db->exec("DELETE FROM categories WHERE video_count=0");
        $db->exec("DELETE FROM tags WHERE video_count=0");
        $db->exec("UPDATE sources SET enabled=0, last_status='Purged demo data' WHERE slug='demo'");
        \App\Core\Cache::forget();
        return (int)$n;
    }

    protected static function syncCredits(int $videoId, array $actors, array $studios): void {
        $db = App::$db;
        $db->prepare('DELETE FROM video_actors WHERE video_id=?')->execute([$videoId]);
        $db->prepare('DELETE FROM video_studios WHERE video_id=?')->execute([$videoId]);
        self::syncCreditType($videoId, $actors, 'actors', 'video_actors', 'actor_id');
        self::syncCreditType($videoId, $studios, 'studios', 'video_studios', 'studio_id');
    }

    protected static function syncCreditType(
        int $videoId,
        array $credits,
        string $table,
        string $pivot,
        string $foreignKey
    ): void {
        $db = App::$db;
        foreach (array_slice(array_values(array_unique($credits)), 0, 8) as $name) {
            $name = mb_substr(trim((string)$name), 0, 120);
            if ($name === '') {
                continue;
            }
            $slug = self::slugify($name);
            $db->prepare("INSERT IGNORE INTO {$table} (slug, name) VALUES (?, ?)")
                ->execute([$slug, $name]);
            $statement = $db->prepare("SELECT id FROM {$table} WHERE slug=?");
            $statement->execute([$slug]);
            $creditId = (int)$statement->fetchColumn();
            $db->prepare("INSERT IGNORE INTO {$pivot} (video_id, {$foreignKey}) VALUES (?, ?)")
                ->execute([$videoId, $creditId]);
        }
    }

    public static function slugify(string $s): string {
        $s = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower(trim($s)));
        return trim($s, '-') ?: 'v';
    }
}
