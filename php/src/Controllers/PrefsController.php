<?php
namespace App\Controllers;

use App\Core\View;
use App\Support\Preferences;

class PrefsController {
    private function validType(): string {
        $t = strtolower((string)($_POST['type'] ?? $_GET['type'] ?? 'category'));
        return in_array($t, ['category','tag'], true) ? $t : 'category';
    }

    public function pin(): void {
        $slug = preg_replace('/[^a-z0-9\-]/', '', (string)($_POST['slug'] ?? ''));
        $type = $this->validType();
        if ($slug) Preferences::togglePin($slug, $type);
        View::json($this->state());
    }
    public function hide(): void {
        $slug = preg_replace('/[^a-z0-9\-]/', '', (string)($_POST['slug'] ?? ''));
        $type = $this->validType();
        if ($slug) Preferences::toggleHide($slug, $type);
        View::json($this->state());
    }
    public function stateJson(): void {
        View::json($this->state());
    }

    private function state(): array {
        $db = \App\Core\App::$db;
        $out = ['ok'=>true];
        foreach (['category', 'tag'] as $type) {
            $pins = Preferences::pins($type); $hides = Preferences::hides($type);
            $labels = [];
            $slugs = array_unique(array_merge($pins, $hides));
            if ($slugs) {
                $table = $type === 'tag' ? 'tags' : 'categories';
                $ph = implode(',', array_fill(0, count($slugs), '?'));
                $st = $db->prepare("SELECT slug, name, video_count FROM $table WHERE slug IN ($ph)");
                $st->execute($slugs);
                foreach ($st->fetchAll() as $r) $labels[$r['slug']] = ['name'=>$r['name'], 'count'=>(int)$r['video_count']];
            }
            $table = $type === 'tag' ? 'tags' : 'categories';
            $all = $db->query("SELECT slug, name, video_count FROM $table WHERE video_count>0 ORDER BY video_count DESC LIMIT 60")->fetchAll();
            $out[$type] = [
                'pins' => $pins, 'hides' => $hides, 'labels' => $labels,
                'all' => array_map(fn($c)=>['slug'=>$c['slug'],'name'=>$c['name'],'count'=>(int)$c['video_count']], $all),
            ];
        }
        // Backwards compatibility keys used by earlier drawer JS
        $out['pins'] = $out['category']['pins'];
        $out['hides'] = $out['category']['hides'];
        $out['labels'] = $out['category']['labels'];
        $out['all'] = $out['category']['all'];
        return $out;
    }
}
