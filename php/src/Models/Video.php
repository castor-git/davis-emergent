<?php
namespace App\Models;

use App\Core\App;

class Video {
    public static function paginate(array $filters, int $page = 1, int $per = 24): array {
        $where = ['v.is_available = 1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = "(v.title LIKE :q OR v.description LIKE :q)";
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['quality'])) { $where[] = "v.quality = :quality"; $params[':quality'] = $filters['quality']; }
        if (!empty($filters['source'])) { $where[] = "v.source = :src"; $params[':src'] = $filters['source']; }
        if (!empty($filters['min_duration'])) { $where[] = "v.duration >= :mind"; $params[':mind'] = (int)$filters['min_duration']; }
        if (!empty($filters['max_duration'])) { $where[] = "v.duration <= :maxd"; $params[':maxd'] = (int)$filters['max_duration']; }
        if (!empty($filters['featured'])) { $where[] = "v.is_featured = 1"; }
        $join = '';
        if (!empty($filters['category'])) {
            $join .= " JOIN video_categories vc ON vc.video_id = v.id JOIN categories c ON c.id = vc.category_id ";
            $where[] = "c.slug = :cat"; $params[':cat'] = $filters['category'];
        }
        if (!empty($filters['tag'])) {
            $join .= " JOIN video_tags vt ON vt.video_id = v.id JOIN tags t ON t.id = vt.tag_id ";
            $where[] = "t.slug = :tag"; $params[':tag'] = $filters['tag'];
        }
        $sort = match ($filters['sort'] ?? 'popular') {
            'newest' => 'v.published_at DESC',
            'rating' => 'v.rating DESC',
            'longest' => 'v.duration DESC',
            'random' => 'RAND()',
            default => 'v.views DESC',
        };
        $sql = "SELECT DISTINCT v.* FROM videos v {$join}";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY {$sort} LIMIT :lim OFFSET :off";
        $countSql = "SELECT COUNT(DISTINCT v.id) FROM videos v {$join}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        $db = App::$db;
        $cst = $db->prepare($countSql);
        foreach ($params as $k=>$v) $cst->bindValue($k, $v);
        $cst->execute();
        $total = (int)$cst->fetchColumn();
        $st = $db->prepare($sql);
        foreach ($params as $k=>$v) $st->bindValue($k, $v);
        $st->bindValue(':lim', $per, \PDO::PARAM_INT);
        $st->bindValue(':off', ($page-1)*$per, \PDO::PARAM_INT);
        $st->execute();
        return ['items'=>$st->fetchAll(), 'total'=>$total, 'page'=>$page, 'per'=>$per, 'pages'=>max(1, (int)ceil($total/$per))];
    }

    public static function bySlug(string $slug): ?array {
        $st = App::$db->prepare("SELECT * FROM videos WHERE slug=? AND is_available=1");
        $st->execute([$slug]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function categoriesFor(int $id): array {
        $st = App::$db->prepare("SELECT c.* FROM categories c JOIN video_categories vc ON vc.category_id=c.id WHERE vc.video_id=?");
        $st->execute([$id]); return $st->fetchAll();
    }
    public static function tagsFor(int $id): array {
        $st = App::$db->prepare("SELECT t.* FROM tags t JOIN video_tags vt ON vt.tag_id=t.id WHERE vt.video_id=?");
        $st->execute([$id]); return $st->fetchAll();
    }

    public static function related(int $id, int $limit = 8): array {
        $st = App::$db->prepare("
            SELECT v.*, COUNT(*) as score FROM videos v
            JOIN video_categories vc ON vc.video_id=v.id
            WHERE vc.category_id IN (SELECT category_id FROM video_categories WHERE video_id=?) AND v.id <> ? AND v.is_available=1
            GROUP BY v.id ORDER BY score DESC, v.views DESC LIMIT ?");
        $st->bindValue(1, $id, \PDO::PARAM_INT);
        $st->bindValue(2, $id, \PDO::PARAM_INT);
        $st->bindValue(3, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public static function incrementViews(int $id): void {
        App::$db->prepare("UPDATE videos SET views = views + 1 WHERE id=?")->execute([$id]);
    }

    public static function suggest(string $q, int $limit = 8): array {
        $st = App::$db->prepare("SELECT slug, title FROM videos WHERE title LIKE ? AND is_available=1 ORDER BY views DESC LIMIT ?");
        $st->bindValue(1, '%' . $q . '%');
        $st->bindValue(2, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}
