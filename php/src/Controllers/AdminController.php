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
        ];
        $sources = $db->query("SELECT * FROM sources ORDER BY slug")->fetchAll();
        View::render('admin/dashboard', ['title'=>'Admin', 'counts'=>$counts, 'sources'=>$sources], 'admin');
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
