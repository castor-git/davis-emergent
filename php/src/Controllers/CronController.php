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
            SourceManager::importAsync($r['slug'], SourceManager::limitFor($r['slug'], 300));
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
        // Give fresh drafts an AI cover so they are share-ready the moment admin publishes them
        \App\Support\CoverGenerator::generateMissing(3);
        \App\Support\LandingCopyGenerator::generateMissing(3);
    }

    public function weeklyDigest(): void {
        $secret = getenv('WEBHOOK_CRON_SECRET') ?: '';
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = (str_starts_with($auth, 'Bearer ')) ? substr($auth, 7) : '';
        if (!$secret || !hash_equals($secret, $token)) {
            View::json(['ok'=>false,'error'=>'unauthorized'], 401);
            return;
        }
        View::json(['ok'=>true,'event'=>'weekly-digest-accepted']);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else { ignore_user_abort(true); flush(); }

        try {
            $to = getenv('DIGEST_TO_EMAIL') ?: '';
            if (!$to) { error_log('digest: DIGEST_TO_EMAIL missing'); return; }
            $d = \App\Support\DigestBuilder::build();
            $r = \App\Support\EmailService::send($to, $d['subject'], $d['html']);
            \App\Core\App::$db->exec("CREATE TABLE IF NOT EXISTS digests (id INT AUTO_INCREMENT PRIMARY KEY, sent_to VARCHAR(191), status INT, note TEXT, subject VARCHAR(255), summary TEXT, sent_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
            $st = \App\Core\App::$db->prepare("INSERT INTO digests (sent_to, status, note, subject, summary) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$to, (int)($r['status'] ?? 0), (string)($r['error'] ?? ($r['reason'] ?? '')), $d['subject'], $d['text_summary']]);
        } catch (\Throwable $e) {
            error_log('weekly digest failure: ' . $e->getMessage());
        }
    }

    // Cron endpoints must ack 2xx immediately; enqueue/background the actual work.
    public function deadCleanup(): void {
        $secret = getenv('WEBHOOK_CRON_SECRET') ?: '';
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
        if (!$secret || !hash_equals($secret, $token)) {
            View::json(['ok' => false, 'error' => 'unauthorized'], 401);
            return;
        }
        View::json(['ok' => true, 'event' => 'dead-cleanup-accepted']);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            ignore_user_abort(true);
            flush();
        }
        \App\Support\XVideosDeadCleaner::runAsync();
    }
}
