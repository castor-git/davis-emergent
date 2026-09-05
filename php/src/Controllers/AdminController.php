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
        View::render('admin/dashboard', ['title'=>'Admin','counts'=>$counts,'sources'=>$sources,'ads'=>$ads,'positions'=>$positions], 'admin');
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
