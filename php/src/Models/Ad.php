<?php
namespace App\Models;

use App\Core\App;

class Ad {
    public static function forPosition(string $position, int $limit = 1): array {
        $st = App::$db->prepare("SELECT * FROM ads
            WHERE position = ? AND active = 1
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (ends_at IS NULL OR ends_at >= NOW())
            ORDER BY weight DESC, RAND() LIMIT ?");
        $st->bindValue(1, $position);
        $st->bindValue(2, $limit, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public static function all(): array {
        return App::$db->query("SELECT * FROM ads ORDER BY position, weight DESC, id DESC")->fetchAll();
    }

    public static function find(int $id): ?array {
        $st = App::$db->prepare("SELECT * FROM ads WHERE id=?"); $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function save(array $d, ?int $id = null): int {
        $db = App::$db;
        if ($id) {
            $st = $db->prepare("UPDATE ads SET position=?,kind=?,title=?,image_url=?,link_url=?,snippet_html=?,weight=?,active=?,starts_at=?,ends_at=? WHERE id=?");
            $st->execute([$d['position'],$d['kind'],$d['title'],$d['image_url'],$d['link_url'],$d['snippet_html'],(int)$d['weight'],(int)!empty($d['active']),$d['starts_at'] ?: null,$d['ends_at'] ?: null,$id]);
            return $id;
        }
        $st = $db->prepare("INSERT INTO ads (position,kind,title,image_url,link_url,snippet_html,weight,active,starts_at,ends_at) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $st->execute([$d['position'],$d['kind'],$d['title'],$d['image_url'],$d['link_url'],$d['snippet_html'],(int)$d['weight'],(int)!empty($d['active']),$d['starts_at'] ?: null,$d['ends_at'] ?: null]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id): void {
        App::$db->prepare("DELETE FROM ads WHERE id=?")->execute([$id]);
    }

    public static function positions(): array {
        return ['home_top'=>'Home — top','home_middle'=>'Home — middle','video_pre'=>'Video — before player','video_sidebar'=>'Video — sidebar'];
    }
}
