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
        if (!empty($filters['actor'])) {
            $join .= ' JOIN video_actors va ON va.video_id=v.id JOIN actors a ON a.id=va.actor_id ';
            $where[] = 'a.slug = :actor';
            $params[':actor'] = $filters['actor'];
        }
        if (!empty($filters['studio'])) {
            $join .= ' JOIN video_studios vs ON vs.video_id=v.id JOIN studios s ON s.id=vs.studio_id ';
            $where[] = 's.slug = :studio';
            $params[':studio'] = $filters['studio'];
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
        return [
            'items' => self::enrichCards($st->fetchAll()),
            'total' => $total,
            'page' => $page,
            'per' => $per,
            'pages' => max(1, (int)ceil($total / $per)),
        ];
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

    public static function actorsFor(int $id): array {
        $statement = App::$db->prepare(
            'SELECT a.* FROM actors a JOIN video_actors va ON va.actor_id=a.id WHERE va.video_id=?'
        );
        $statement->execute([$id]);
        return $statement->fetchAll();
    }

    public static function studiosFor(int $id): array {
        $statement = App::$db->prepare(
            'SELECT s.* FROM studios s JOIN video_studios vs ON vs.studio_id=s.id WHERE vs.video_id=?'
        );
        $statement->execute([$id]);
        return $statement->fetchAll();
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
        return self::enrichCards($st->fetchAll());
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

    public static function enrichCards(array $items): array {
        if (!$items) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', array_column($items, 'id'))));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $db = App::$db;
        $actors = self::creditsByVideo(
            "SELECT va.video_id, a.slug, a.name FROM video_actors va
             JOIN actors a ON a.id=va.actor_id WHERE va.video_id IN ({$marks}) ORDER BY a.name",
            $ids
        );
        $studios = self::creditsByVideo(
            "SELECT vs.video_id, s.slug, s.name FROM video_studios vs
             JOIN studios s ON s.id=vs.studio_id WHERE vs.video_id IN ({$marks}) ORDER BY s.name",
            $ids
        );
        $tags = self::creditsByVideo(
            "SELECT vt.video_id, t.slug, t.name FROM video_tags vt
             JOIN tags t ON t.id=vt.tag_id WHERE vt.video_id IN ({$marks}) ORDER BY t.name",
            $ids
        );
        foreach ($items as &$item) {
            $id = (int)$item['id'];
            $item['actors'] = array_slice($actors[$id] ?? [], 0, 3);
            $item['studios'] = array_slice($studios[$id] ?? [], 0, 2);
            $item['card_tags'] = array_slice($tags[$id] ?? [], 0, 3);
        }
        unset($item);
        return $items;
    }

    private static function creditsByVideo(string $sql, array $ids): array {
        $statement = App::$db->prepare($sql);
        $statement->execute($ids);
        $grouped = [];
        foreach ($statement->fetchAll() as $row) {
            $grouped[(int)$row['video_id']][] = [
                'slug' => $row['slug'],
                'name' => $row['name'],
            ];
        }
        return $grouped;
    }
}
