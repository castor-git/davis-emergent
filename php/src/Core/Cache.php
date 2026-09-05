<?php
namespace App\Core;

class Cache {
    public static function remember(string $key, int $ttl, callable $cb) {
        $db = App::$db;
        $st = $db->prepare("SELECT v, expires_at FROM cache WHERE k = ?");
        $st->execute([$key]);
        $row = $st->fetch();
        if ($row && strtotime($row['expires_at']) > time()) {
            return json_decode($row['v'], true);
        }
        $val = $cb();
        $expires = date('Y-m-d H:i:s', time() + $ttl);
        $ins = $db->prepare("REPLACE INTO cache (k, v, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$key, json_encode($val), $expires]);
        return $val;
    }
    public static function forget(string $prefix = ''): int {
        $db = App::$db;
        if ($prefix === '') { $db->exec("TRUNCATE cache"); return 0; }
        $st = $db->prepare("DELETE FROM cache WHERE k LIKE ?");
        $st->execute([$prefix . '%']);
        return $st->rowCount();
    }
}
