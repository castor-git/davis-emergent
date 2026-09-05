<?php
namespace App\Models;

use App\Core\App;

class Taxonomy {
    public static function popularCategories(int $limit = 24): array {
        $st = App::$db->prepare("SELECT * FROM categories WHERE video_count > 0 ORDER BY video_count DESC LIMIT ?");
        $st->bindValue(1, $limit, \PDO::PARAM_INT); $st->execute();
        return $st->fetchAll();
    }
    public static function popularTags(int $limit = 40): array {
        $st = App::$db->prepare("SELECT * FROM tags WHERE video_count > 0 ORDER BY video_count DESC LIMIT ?");
        $st->bindValue(1, $limit, \PDO::PARAM_INT); $st->execute();
        return $st->fetchAll();
    }
    public static function categoryBySlug(string $slug): ?array {
        $st = App::$db->prepare("SELECT * FROM categories WHERE slug=?"); $st->execute([$slug]);
        return $st->fetch() ?: null;
    }
    public static function tagBySlug(string $slug): ?array {
        $st = App::$db->prepare("SELECT * FROM tags WHERE slug=?"); $st->execute([$slug]);
        return $st->fetch() ?: null;
    }
}
