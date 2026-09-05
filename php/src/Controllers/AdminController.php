<?php
namespace App\Controllers;

use App\Core\{View, App, Cache};
use App\Support\SourceManager;

class AdminController {
    private function auth(): void {
        $u = App::config('admin.user'); $p = App::config('admin.pass');
        $sent_user = $_SERVER['PHP_AUTH_USER'] ?? '';
        $sent_pass = $_SERVER['PHP_AUTH_PW'] ?? '';
        if (!hash_equals($u, $sent_user) || !hash_equals($p, $sent_pass)) {
            header('WWW-Authenticate: Basic realm="Davisporn Admin"');
            http_response_code(401);
            echo 'Authentication required';
            exit;
        }
    }

    public function dashboard(): void {
        $this->auth();
        $db = App::$db;
        $counts = [
            'videos' => (int)$db->query("SELECT COUNT(*) FROM videos")->fetchColumn(),
            'categories' => (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
            'tags' => (int)$db->query("SELECT COUNT(*) FROM tags")->fetchColumn(),
            'sources' => (int)$db->query("SELECT COUNT(*) FROM sources WHERE enabled=1")->fetchColumn(),
            'ads' => (int)$db->query("SELECT COUNT(*) FROM ads WHERE active=1")->fetchColumn(),
        ];
        $sources = $db->query("SELECT * FROM sources ORDER BY slug")->fetchAll();
        foreach ($sources as &$s) { $s['config_arr'] = json_decode($s['config'] ?: '{}', true) ?: []; }
        $ads = \App\Models\Ad::all();
        $positions = \App\Models\Ad::positions();
        $top_searches = \App\Models\SearchQuery::top(20);
        $search_stats = \App\Models\SearchQuery::stats();
        $landings = \App\Models\Landing::all();
        View::render('admin/dashboard', ['title'=>'Admin','counts'=>$counts,'sources'=>$sources,'ads'=>$ads,'positions'=>$positions,'top_searches'=>$top_searches,'search_stats'=>$search_stats,'landings'=>$landings], 'admin');
    }

    public function landingForm(): void {
        $this->auth();
        $id = (int)($_GET['id'] ?? 0);
        $prefill = ['id'=>0,'slug'=>'','title'=>'','keyword'=>'','intro'=>'','categories'=>[],'tags'=>[],'active'=>1];
        if ($id) {
            $l = \App\Models\Landing::find($id);
            if ($l) $prefill = [
                'id'=>$id, 'slug'=>$l['slug'], 'title'=>$l['title'], 'keyword'=>$l['keyword'] ?? '',
                'intro'=>$l['intro'] ?? '', 'active'=>(int)$l['active'],
                'categories'=>json_decode($l['categories_json'] ?: '[]', true) ?: [],
                'tags'=>json_decode($l['tags_json'] ?: '[]', true) ?: [],
            ];
        } elseif (!empty($_GET['keyword'])) {
            $kw = trim($_GET['keyword']);
            $prefill['keyword'] = $kw;
            $prefill['title'] = ucwords($kw) . ' videos — curated';
            $prefill['slug'] = \App\Support\SourceManager::slugify($kw);
            $prefill['intro'] = "Handpicked scenes matching “{$kw}”. Fresh videos are added automatically every 6 hours.";
            // Suggest categories/tags that fuzzy-match the keyword
            $like = '%' . $kw . '%';
            $cs = App::$db->prepare("SELECT slug FROM categories WHERE name LIKE ? OR slug LIKE ? LIMIT 5"); $cs->execute([$like,$like]);
            $prefill['categories'] = array_column($cs->fetchAll(), 'slug');
            $ts = App::$db->prepare("SELECT slug FROM tags WHERE name LIKE ? OR slug LIKE ? LIMIT 5"); $ts->execute([$like,$like]);
            $prefill['tags'] = array_column($ts->fetchAll(), 'slug');
        }
        $all_cats = App::$db->query("SELECT slug, name FROM categories ORDER BY name")->fetchAll();
        $all_tags = App::$db->query("SELECT slug, name FROM tags ORDER BY name")->fetchAll();
        View::render('admin/landing_form', ['title'=>'Landing page','prefill'=>$prefill,'all_cats'=>$all_cats,'all_tags'=>$all_tags], 'admin');
    }

    public function saveLanding(): void {
        $this->auth();
        $id = (int)($_POST['id'] ?? 0) ?: null;
        $data = [
            'slug' => $_POST['slug'] ?? '',
            'title' => $_POST['title'] ?? '',
            'keyword' => $_POST['keyword'] ?? '',
            'intro' => $_POST['intro'] ?? '',
            'categories' => $_POST['categories'] ?? [],
            'tags' => $_POST['tags'] ?? [],
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $newId = \App\Models\Landing::save($data, $id);
        \App\Core\Cache::forget();
        $l = \App\Models\Landing::find($newId);
        header('Location: /admin?msg=' . urlencode('Landing saved: /l/' . $l['slug']));
        exit;
    }

    public function deleteLanding(): void {
        $this->auth();
        \App\Models\Landing::delete((int)($_POST['id'] ?? 0));
        header('Location: /admin?msg=' . urlencode('Landing deleted'));
        exit;
    }

    public function saveSourceConfig(): void {
        $this->auth();
        $slug = $_POST['slug'] ?? '';
        $config = [
            'feed_url' => trim($_POST['feed_url'] ?? ''),
            'api_key' => trim($_POST['api_key'] ?? ''),
            'host' => trim($_POST['host'] ?? ''),
        ];
        $st = App::$db->prepare("SELECT config FROM sources WHERE slug=?"); $st->execute([$slug]);
        $existing = json_decode($st->fetchColumn() ?: '{}', true) ?: [];
        $merged = array_merge($existing, array_filter($config, fn($v)=>$v!==''));
        // Also allow explicit clearing via empty submission
        foreach ($config as $k => $v) if ($v === '') $merged[$k] = '';
        App::$db->prepare("UPDATE sources SET config=? WHERE slug=?")->execute([json_encode($merged), $slug]);
        header('Location: /admin?msg=' . urlencode('Source config saved: ' . $slug));
        exit;
    }

    public function saveAd(): void {
        $this->auth();
        $id = (int)($_POST['id'] ?? 0) ?: null;
        $data = [
            'position' => $_POST['position'] ?? 'home_top',
            'kind' => $_POST['kind'] ?? 'banner',
            'title' => $_POST['title'] ?? '',
            'image_url' => $_POST['image_url'] ?? '',
            'link_url' => $_POST['link_url'] ?? '',
            'snippet_html' => $_POST['snippet_html'] ?? '',
            'weight' => (int)($_POST['weight'] ?? 1),
            'active' => isset($_POST['active']) ? 1 : 0,
            'starts_at' => $_POST['starts_at'] ?? '',
            'ends_at' => $_POST['ends_at'] ?? '',
        ];
        \App\Models\Ad::save($data, $id);
        \App\Core\Cache::forget();
        header('Location: /admin?msg=' . urlencode($id ? 'Ad updated' : 'Ad created'));
        exit;
    }

    public function deleteAd(): void {
        $this->auth();
        \App\Models\Ad::delete((int)($_POST['id'] ?? 0));
        \App\Core\Cache::forget();
        header('Location: /admin?msg=' . urlencode('Ad deleted'));
        exit;
    }

    public function toggleSource(): void {
        $this->auth();
        $slug = $_POST['slug'] ?? '';
        $st = App::$db->prepare("UPDATE sources SET enabled = 1 - enabled WHERE slug=?");
        $st->execute([$slug]);
        header('Location: /admin'); exit;
    }

    public function importSource(): void {
        $this->auth();
        $slug = $_POST['slug'] ?? '';
        $r = SourceManager::import($slug, 100);
        Cache::forget();
        header('Location: /admin?msg=' . urlencode($r['message'])); exit;
    }

    public function clearCache(): void {
        $this->auth();
        Cache::forget();
        header('Location: /admin?msg=' . urlencode('Cache cleared')); exit;
    }
}
