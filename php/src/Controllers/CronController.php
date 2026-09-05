<?php
namespace App\Controllers;

use App\Core\{App, View};
use App\Support\SourceManager;

class CronController {
    // Cron endpoints must ack 2xx immediately; enqueue/background the actual work.
    public function nightlyImport(): void {
        $secret = getenv('WEBHOOK_CRON_SECRET') ?: '';
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = (str_starts_with($auth, 'Bearer ')) ? substr($auth, 7) : '';
        if (!$secret || !hash_equals($secret, $token)) {
            View::json(['ok'=>false,'error'=>'unauthorized'], 401);
            return;
        }
        // Ack immediately; execute in a fork so the dispatcher only waits for the status line.
        View::json(['ok'=>true,'event'=>'nightly-import-accepted']);
        // Flush and detach
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else { ignore_user_abort(true); flush(); }

        // Run imports for every enabled source (except demo, which is only for seeding)
        $db = App::$db;
        $rows = $db->query("SELECT slug FROM sources WHERE enabled=1 AND slug <> 'demo'")->fetchAll();
        foreach ($rows as $r) {
            try { SourceManager::import($r['slug'], 200); }
            catch (\Throwable $e) { /* status already saved by SourceManager */ }
        }
        \App\Core\Cache::forget();
    }

    public function dailySuggest(): void {
        $secret = getenv('WEBHOOK_CRON_SECRET') ?: '';
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = (str_starts_with($auth, 'Bearer ')) ? substr($auth, 7) : '';
        if (!$secret || !hash_equals($secret, $token)) {
            View::json(['ok'=>false,'error'=>'unauthorized'], 401);
            return;
        }
        View::json(['ok'=>true,'event'=>'daily-suggest-accepted']);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else { ignore_user_abort(true); flush(); }

        try { \App\Support\LandingSuggester::run(3); }
        catch (\Throwable $e) { error_log('landing suggester: ' . $e->getMessage()); }
    }
}
