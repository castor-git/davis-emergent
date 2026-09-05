<?php
namespace App\Support;

use App\Core\App;

/**
 * Notifies search engines that new URLs are available.
 * Uses IndexNow (Bing/Yandex/Seznam via a single POST) plus the deprecated
 * Google/Bing sitemap ping endpoints (best-effort — they may 404, that's fine).
 */
class PingService {
    public static function indexNowKey(): string {
        $path = __DIR__ . '/../../storage/indexnow.key';
        if (!is_file($path)) {
            @mkdir(dirname($path), 0777, true);
            file_put_contents($path, bin2hex(random_bytes(16)));
        }
        return trim((string)file_get_contents($path));
    }

    protected static function currentHost(): string {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
        return $scheme . '://' . $host;
    }

    /** Ping every search engine for a specific URL. Non-blocking-ish (2s timeout each). */
    public static function submit(string $url): array {
        $base = self::currentHost();
        $key = self::indexNowKey();
        $host = parse_url($base, PHP_URL_HOST);
        $sitemap = $base . '/sitemap.xml';
        $results = [];

        // IndexNow (Bing + Yandex + Seznam)
        $payload = json_encode([
            'host' => $host,
            'key' => $key,
            'keyLocation' => $base . '/' . $key . '.txt',
            'urlList' => [$url],
        ]);
        $results['indexnow'] = self::httpPost('https://api.indexnow.org/indexnow', $payload, ['Content-Type: application/json']);

        // Google sitemap ping (deprecated Jun 2023 but still returns something)
        $results['google'] = self::httpGet('https://www.google.com/ping?sitemap=' . urlencode($sitemap));
        // Bing sitemap ping (deprecated May 2022)
        $results['bing'] = self::httpGet('https://www.bing.com/ping?sitemap=' . urlencode($sitemap));

        // Log to DB for the admin dashboard
        $db = App::$db;
        $db->exec("CREATE TABLE IF NOT EXISTS pings (id INT AUTO_INCREMENT PRIMARY KEY, url VARCHAR(500), engine VARCHAR(32), status INT, note TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $st = $db->prepare("INSERT INTO pings (url, engine, status, note) VALUES (?, ?, ?, ?)");
        foreach ($results as $engine => $r) $st->execute([$url, $engine, $r['status'] ?? 0, $r['error'] ?? '']);

        return $results;
    }

    protected static function httpPost(string $url, string $body, array $headers = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $out = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => $code, 'body' => $out ?: null, 'error' => $err ?: null];
    }
    protected static function httpGet(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => $code, 'error' => $err ?: null];
    }

    public static function recentPings(int $limit = 10): array {
        $db = App::$db;
        $has = $db->query("SHOW TABLES LIKE 'pings'")->fetch();
        if (!$has) return [];
        $st = $db->prepare("SELECT url, engine, status, note, created_at FROM pings ORDER BY id DESC LIMIT ?");
        $st->bindValue(1, $limit, \PDO::PARAM_INT); $st->execute();
        return $st->fetchAll();
    }
}
