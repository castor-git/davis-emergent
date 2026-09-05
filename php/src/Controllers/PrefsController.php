<?php
namespace App\Controllers;

use App\Core\View;
use App\Support\Preferences;

class PrefsController {
    public function pin(): void {
        $slug = preg_replace('/[^a-z0-9\-]/', '', (string)($_POST['slug'] ?? ''));
        if ($slug) Preferences::togglePin($slug);
        View::json(['ok'=>true,'pins'=>Preferences::pins(),'hides'=>Preferences::hides()]);
    }
    public function hide(): void {
        $slug = preg_replace('/[^a-z0-9\-]/', '', (string)($_POST['slug'] ?? ''));
        if ($slug) Preferences::toggleHide($slug);
        View::json(['ok'=>true,'pins'=>Preferences::pins(),'hides'=>Preferences::hides()]);
    }
    public function state(): void {
        // Attach category names so the drawer can render pretty labels
        $db = \App\Core\App::$db;
        $pins = Preferences::pins(); $hides = Preferences::hides();
        $labels = [];
        $slugs = array_unique(array_merge($pins, $hides));
        if ($slugs) {
            $ph = implode(',', array_fill(0, count($slugs), '?'));
            $st = $db->prepare("SELECT slug, name, video_count FROM categories WHERE slug IN ($ph)");
            $st->execute($slugs);
            foreach ($st->fetchAll() as $r) $labels[$r['slug']] = ['name'=>$r['name'], 'count'=>(int)$r['video_count']];
        }
        // All categories for the search picker
        $all = $db->query("SELECT slug, name, video_count FROM categories WHERE video_count>0 ORDER BY video_count DESC LIMIT 60")->fetchAll();
        View::json([
            'pins' => $pins, 'hides' => $hides, 'labels' => $labels,
            'all' => array_map(fn($c)=>['slug'=>$c['slug'],'name'=>$c['name'],'count'=>(int)$c['video_count']], $all),
        ]);
    }
}
