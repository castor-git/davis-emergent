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

        // Merge per-source DB config on top of the file-based defaults so admin-
        // saved feed URLs / API keys become the runtime source of truth.
        $rows = $db->query("SELECT slug, enabled, config FROM sources")->fetchAll();
        foreach ($rows as $r) {
            $slug = $r['slug'];
            if (!isset(App::$config['sources'][$slug])) continue;
            App::$config['sources'][$slug]['enabled'] = (bool)$r['enabled'];
            $cfg = json_decode($r['config'] ?: '{}', true) ?: [];
            foreach ($cfg as $k => $v) {
                if ($v !== '' && $v !== null) App::$config['sources'][$slug][$k] = $v;
            }
        }
    }
}
