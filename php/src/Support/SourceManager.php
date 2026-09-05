<?php
namespace App\Support;

use App\Core\App;
use App\Adapters\SourceAdapter;
use App\Adapters\DemoAdapter;
use App\Adapters\UporniaCsvAdapter;
use App\Adapters\XVideosCsvAdapter;
use App\Adapters\XnxxRapidApiAdapter;

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

    public static function import(string $slug, int $limit = 100): array {
        $db = App::$db;
        try {
            $adapter = self::adapter($slug);
            $items = $adapter->fetch($limit);
            $inserted = 0; $updated = 0;
            foreach ($items as $v) {
                $slugStr = self::slugify($v['title']) . '-' . substr(md5($v['source'].$v['source_video_id']), 0, 6);
                $st = $db->prepare("INSERT INTO videos (source, source_video_id, slug, title, description, thumbnail, preview, embed_url, page_url, duration, views, rating, quality, is_featured, published_at)
                    VALUES (:source,:sid,:slug,:title,:desc,:thumb,:preview,:embed,:page,:dur,:views,:rating,:quality,:feat,:pub)
                    ON DUPLICATE KEY UPDATE title=VALUES(title), views=VALUES(views), rating=VALUES(rating)");
                $st->execute([
                    ':source'=>$v['source'], ':sid'=>$v['source_video_id'], ':slug'=>$slugStr,
                    ':title'=>$v['title'], ':desc'=>$v['description'] ?? '',
                    ':thumb'=>$v['thumbnail'] ?? '', ':preview'=>$v['preview'] ?? '',
                    ':embed'=>$v['embed_url'] ?? '', ':page'=>$v['page_url'] ?? '',
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
            }
            self::recount();
            $status = "OK — inserted {$inserted}, updated {$updated}";
            $upd = $db->prepare("UPDATE sources SET last_import_at=NOW(), last_status=? WHERE slug=?");
            $upd->execute([$status, $slug]);
            \App\Core\Cache::forget();
            return ['ok'=>true,'inserted'=>$inserted,'updated'=>$updated,'message'=>$status];
        } catch (\Throwable $e) {
            $upd = $db->prepare("UPDATE sources SET last_status=? WHERE slug=?");
            $upd->execute(['ERROR: ' . $e->getMessage(), $slug]);
            return ['ok'=>false,'message'=>$e->getMessage()];
        }
    }

    protected static function syncTaxonomy(int $videoId, array $cats, array $tags): void {
        $db = App::$db;
        foreach ($cats as $name) {
            $name = trim($name); if ($name === '') continue;
            $slug = self::slugify($name);
            $db->prepare("INSERT IGNORE INTO categories (slug, name) VALUES (?, ?)")->execute([$slug, $name]);
            $cid = (int)$db->query("SELECT id FROM categories WHERE slug=".$db->quote($slug))->fetchColumn();
            $db->prepare("INSERT IGNORE INTO video_categories (video_id, category_id) VALUES (?, ?)")->execute([$videoId, $cid]);
        }
        foreach ($tags as $name) {
            $name = trim($name); if ($name === '') continue;
            $slug = self::slugify($name);
            $db->prepare("INSERT IGNORE INTO tags (slug, name) VALUES (?, ?)")->execute([$slug, $name]);
            $tid = (int)$db->query("SELECT id FROM tags WHERE slug=".$db->quote($slug))->fetchColumn();
            $db->prepare("INSERT IGNORE INTO video_tags (video_id, tag_id) VALUES (?, ?)")->execute([$videoId, $tid]);
        }
    }

    public static function recount(): void {
        $db = App::$db;
        $db->exec("UPDATE categories c SET video_count = (SELECT COUNT(*) FROM video_categories vc WHERE vc.category_id = c.id)");
        $db->exec("UPDATE tags t SET video_count = (SELECT COUNT(*) FROM video_tags vt WHERE vt.tag_id = t.id)");
    }

    public static function slugify(string $s): string {
        $s = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower(trim($s)));
        return trim($s, '-') ?: 'v';
    }
}
