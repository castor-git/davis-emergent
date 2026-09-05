<?php
namespace App\Models;

use App\Core\App;
use App\Support\SourceManager;

class Landing {
    public static function all(): array {
        return App::$db->query("SELECT * FROM landings ORDER BY created_at DESC")->fetchAll();
    }

    public static function bySlug(string $slug): ?array {
        $st = App::$db->prepare("SELECT * FROM landings WHERE slug=? AND active=1");
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public static function find(int $id): ?array {
        $st = App::$db->prepare("SELECT * FROM landings WHERE id=?"); $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function save(array $d, ?int $id = null): int {
        $db = App::$db;
        $slug = trim($d['slug'] ?? '');
        if ($slug === '') $slug = SourceManager::slugify($d['title'] ?? ($d['keyword'] ?? 'landing'));
        $slug = substr($slug, 0, 90);
        // Ensure unique slug on create
        if (!$id) {
            $base = $slug; $n = 2;
            while ((bool)$db->prepare("SELECT 1 FROM landings WHERE slug=?")->execute([$slug]) && $db->query("SELECT COUNT(*) FROM landings WHERE slug=".$db->quote($slug))->fetchColumn()) {
                $slug = $base . '-' . $n++; if ($n > 50) break;
            }
        }
        $payload = [
            $slug,
            $d['title'] ?: ucfirst($d['keyword'] ?? 'Landing'),
            $d['keyword'] ?? null,
            $d['intro'] ?? null,
            json_encode(array_values(array_filter(array_map('trim', (array)($d['categories'] ?? []))))),
            json_encode(array_values(array_filter(array_map('trim', (array)($d['tags'] ?? []))))),
            (int)!empty($d['active']),
        ];
        if ($id) {
            $st = $db->prepare("UPDATE landings SET slug=?, title=?, keyword=?, intro=?, categories_json=?, tags_json=?, active=? WHERE id=?");
            $st->execute([...$payload, $id]);
            return $id;
        }
        $st = $db->prepare("INSERT INTO landings (slug,title,keyword,intro,categories_json,tags_json,active) VALUES (?,?,?,?,?,?,?)");
        $st->execute($payload);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): void {
        App::$db->prepare("DELETE FROM landings WHERE id=?")->execute([$id]);
    }

    public static function videos(array $landing, int $page = 1, int $per = 24): array {
        $cats = json_decode($landing['categories_json'] ?: '[]', true) ?: [];
        $tags = json_decode($landing['tags_json'] ?: '[]', true) ?: [];
        $keyword = trim((string)($landing['keyword'] ?? ''));
        $conds = []; $params = [];
        if ($cats) {
            $ph = implode(',', array_fill(0, count($cats), '?'));
            $conds[] = "v.id IN (SELECT vc.video_id FROM video_categories vc JOIN categories c ON c.id=vc.category_id WHERE c.slug IN ($ph))";
            $params = array_merge($params, $cats);
        }
        if ($tags) {
            $ph = implode(',', array_fill(0, count($tags), '?'));
            $conds[] = "v.id IN (SELECT vt.video_id FROM video_tags vt JOIN tags t ON t.id=vt.tag_id WHERE t.slug IN ($ph))";
            $params = array_merge($params, $tags);
        }
        if ($keyword !== '') {
            $conds[] = "(v.title LIKE ? OR v.description LIKE ?)";
            $params[] = "%$keyword%"; $params[] = "%$keyword%";
        }
        // Union semantics: a video is included if it matches ANY chosen bucket.
        $where = $conds ? ('WHERE ' . implode(' OR ', $conds)) : 'WHERE 1=0';
        $sql = "SELECT v.* FROM videos v $where ORDER BY v.views DESC LIMIT ? OFFSET ?";
        $countSql = "SELECT COUNT(*) FROM videos v $where";
        $db = App::$db;
        $cst = $db->prepare($countSql); foreach ($params as $i=>$p) $cst->bindValue($i+1, $p); $cst->execute();
        $total = (int)$cst->fetchColumn();
        $st = $db->prepare($sql);
        $i = 1; foreach ($params as $p) { $st->bindValue($i++, $p); }
        $st->bindValue($i++, $per, \PDO::PARAM_INT);
        $st->bindValue($i, ($page-1)*$per, \PDO::PARAM_INT);
        $st->execute();
        return ['items'=>$st->fetchAll(), 'total'=>$total, 'page'=>$page, 'per'=>$per, 'pages'=>max(1,(int)ceil($total/$per))];
    }

    public static function incrementViews(int $id): void {
        App::$db->prepare("UPDATE landings SET views = views + 1 WHERE id=?")->execute([$id]);
    }
}
