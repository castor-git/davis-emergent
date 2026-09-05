<?php
namespace App\Core;

class Seeder {
    public static function run(): void {
        $db = App::$db;
        foreach (App::config('sources') as $slug => $cfg) {
            $st = $db->prepare("INSERT IGNORE INTO sources (slug, label, enabled, config) VALUES (?, ?, ?, ?)");
            $st->execute([$slug, $cfg['label'], $cfg['enabled'] ? 1 : 0, json_encode($cfg)]);
        }
        $count = (int)$db->query("SELECT COUNT(*) FROM videos")->fetchColumn();
        if ($count === 0) {
            \App\Support\SourceManager::import('demo');
        }
    }
}
