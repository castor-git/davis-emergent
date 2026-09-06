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
        $prefill = ['id'=>0,'slug'=>'','title'=>'','title_variant_b'=>'','keyword'=>'','intro'=>'','meta_title'=>'','meta_description'=>'','og_image'=>'','template'=>'grid','categories'=>[],'tags'=>[],'active'=>1];
        if ($id) {
            $l = \App\Models\Landing::find($id);
            if ($l) $prefill = [
                'id'=>$id, 'slug'=>$l['slug'], 'title'=>$l['title'], 'title_variant_b'=>$l['title_variant_b'] ?? '', 'keyword'=>$l['keyword'] ?? '',
                'intro'=>$l['intro'] ?? '', 'active'=>(int)$l['active'],
                'meta_title'=>$l['meta_title'] ?? '', 'meta_description'=>$l['meta_description'] ?? '', 'og_image'=>$l['og_image'] ?? '',
                'template'=>$l['template'] ?? 'grid',
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
            'meta_title' => $_POST['meta_title'] ?? '',
            'meta_description' => $_POST['meta_description'] ?? '',
            'og_image' => $_POST['og_image'] ?? '',
            'template' => $_POST['template'] ?? 'grid',
            'title_variant_b' => $_POST['title_variant_b'] ?? '',
            'categories' => $_POST['categories'] ?? [],
            'tags' => $_POST['tags'] ?? [],
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $newId = \App\Models\Landing::save($data, $id);
        \App\Core\Cache::forget();
        $l = \App\Models\Landing::find($newId);

        $msg = 'Landing saved: /l/' . $l['slug'];
        // Ping IndexNow + Google + Bing when the landing is active
        if ((int)$l['active'] === 1) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
            $url = $scheme . '://' . $host . '/l/' . rawurlencode($l['slug']);
            try {
                $r = \App\Support\PingService::submit($url);
                $codes = array_map(fn($x)=>$x['status'] ?? 0, $r);
                $msg .= ' — pinged: IndexNow=' . ($codes['indexnow'] ?? '?') . ' Google=' . ($codes['google'] ?? '?') . ' Bing=' . ($codes['bing'] ?? '?');
            } catch (\Throwable $e) { $msg .= ' — ping failed: ' . $e->getMessage(); }
        }
        header('Location: /admin?msg=' . urlencode($msg));
        exit;
    }

    public function deleteLanding(): void {
        $this->auth();
        \App\Models\Landing::delete((int)($_POST['id'] ?? 0));
        header('Location: /admin?msg=' . urlencode('Landing deleted'));
        exit;
    }

    public function bulkLanding(): void {
        $this->auth();
        $action = $_POST['action'] ?? '';
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $ids = array_values(array_filter($ids));
        if (!$ids) { header('Location: /admin?msg=' . urlencode('No landings selected')); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db = \App\Core\App::$db;
        $done = 0; $pings = 0;
        switch ($action) {
            case 'enable':
                $st = $db->prepare("UPDATE landings SET active=1, suggested=0 WHERE id IN ($ph)");
                $st->execute($ids);
                $done = $st->rowCount();
                // Ping every now-active landing
                $rs = $db->prepare("SELECT slug FROM landings WHERE id IN ($ph)");
                $rs->execute($ids);
                $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
                foreach ($rs->fetchAll() as $r) {
                    try { \App\Support\PingService::submit($scheme.'://'.$host.'/l/'.rawurlencode($r['slug'])); $pings++; }
                    catch (\Throwable $e) {}
                }
                $msg = "Enabled {$done} landing(s), pinged {$pings} URL(s)";
                break;
            case 'delete':
                $st = $db->prepare("DELETE FROM landings WHERE id IN ($ph)");
                $st->execute($ids);
                $done = $st->rowCount();
                $msg = "Deleted {$done} landing(s)";
                break;
            case 'reping':
                $rs = $db->prepare("SELECT slug FROM landings WHERE id IN ($ph) AND active=1");
                $rs->execute($ids);
                $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
                foreach ($rs->fetchAll() as $r) {
                    try { \App\Support\PingService::submit($scheme.'://'.$host.'/l/'.rawurlencode($r['slug'])); $pings++; }
                    catch (\Throwable $e) {}
                }
                $msg = "Re-pinged {$pings} active landing(s)";
                break;
            default:
                $msg = 'Unknown bulk action';
        }
        \App\Core\Cache::forget();
        header('Location: /admin?msg=' . urlencode($msg));
        exit;
    }

    public function saveSourceConfig(): void {
        $this->auth();
        $slug = $_POST['slug'] ?? '';
        // Only fields present in the request are touched; an explicitly submitted empty field clears that key.
        $fields = ['feed_url', 'api_key', 'host', 'queries', 'import_limit'];
        $st = App::$db->prepare("SELECT config FROM sources WHERE slug=?"); $st->execute([$slug]);
        $merged = json_decode($st->fetchColumn() ?: '{}', true) ?: [];
        foreach ($fields as $k) {
            if (!array_key_exists($k, $_POST)) continue;
            $v = trim((string)$_POST[$k]);
            $merged[$k] = $k === 'import_limit' ? ((int)$v ?: '') : $v;
        }
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
        $st = App::$db->prepare("SELECT enabled, config FROM sources WHERE slug=?"); $st->execute([$slug]);
        if ($row = $st->fetch()) {
            $on = (int)$row['enabled'] ? 0 : 1;
            $cfg = json_decode($row['config'] ?: '{}', true) ?: [];
            $cfg['enabled'] = (bool)$on;
            App::$db->prepare("UPDATE sources SET enabled=?, config=? WHERE slug=?")->execute([$on, json_encode($cfg), $slug]);
        }
        header('Location: /admin'); exit;
    }

    public function importSource(): void {
        $this->auth();
        $slug = $_POST['slug'] ?? '';
        if ($slug === 'demo') {
            $r = SourceManager::import($slug, 100);
            header('Location: /admin?msg=' . urlencode($r['message'])); exit;
        }
        SourceManager::importAsync($slug, SourceManager::limitFor($slug, 300));
        header('Location: /admin?msg=' . urlencode("Import of {$slug} started in background — refresh in ~30s to see the result")); exit;
    }

    public function purgeDemo(): void {
        $this->auth();
        $real = (int)App::$db->query("SELECT COUNT(*) FROM videos WHERE source <> 'demo'")->fetchColumn();
        if ($real === 0) { header('Location: /admin?msg=' . urlencode('Import a real source first — refusing to purge demo data into an empty site')); exit; }
        $n = SourceManager::purgeDemo();
        header('Location: /admin?msg=' . urlencode("Removed {$n} demo videos and orphaned categories/tags")); exit;
    }

    public function clearCache(): void {
        $this->auth();
        Cache::forget();
        header('Location: /admin?msg=' . urlencode('Cache cleared')); exit;
    }

    public function digest(): void {
        $this->auth();
        $preview = \App\Support\DigestBuilder::build();
        $to = getenv('DIGEST_TO_EMAIL') ?: '(not configured — set DIGEST_TO_EMAIL)';
        $keyOk = (bool)getenv('EMERGENT_EMAIL_KEY');

        // Last 5 send attempts
        $db = App::$db;
        $db->exec("CREATE TABLE IF NOT EXISTS digests (id INT AUTO_INCREMENT PRIMARY KEY, sent_to VARCHAR(191), status INT, note TEXT, subject VARCHAR(255), summary TEXT, sent_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $last = $db->query("SELECT * FROM digests ORDER BY id DESC LIMIT 5")->fetchAll();

        if (($_GET['action'] ?? '') === 'send-now') {
            $r = \App\Support\EmailService::send(getenv('DIGEST_TO_EMAIL') ?: 'delivered@resend.dev', $preview['subject'], $preview['html']);
            $st = $db->prepare("INSERT INTO digests (sent_to, status, note, subject, summary) VALUES (?,?,?,?,?)");
            $st->execute([$to, (int)($r['status'] ?? 0), (string)($r['error'] ?? ($r['reason'] ?? '')), $preview['subject'], $preview['text_summary']]);
            header('Location: /admin/digest?msg=' . urlencode('Send attempt: status ' . ($r['status'] ?? '—') . ' ' . ($r['reason'] ?? $r['error'] ?? 'ok')));
            exit;
        }

        View::render('admin/digest', ['title'=>'Weekly digest', 'to'=>$to, 'keyOk'=>$keyOk, 'preview'=>$preview, 'last'=>$last], 'admin');
    }
}
