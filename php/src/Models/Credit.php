<?php
namespace App\Models;

use App\Core\App;

class Credit {
    public static function actorBySlug(string $slug): ?array {
        $statement = App::$db->prepare('SELECT * FROM actors WHERE slug=? AND video_count>0');
        $statement->execute([$slug]);
        return $statement->fetch() ?: null;
    }

    public static function studioBySlug(string $slug): ?array {
        $statement = App::$db->prepare('SELECT * FROM studios WHERE slug=? AND video_count>0');
        $statement->execute([$slug]);
        return $statement->fetch() ?: null;
    }
}