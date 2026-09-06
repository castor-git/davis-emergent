<?php
namespace App\Adapters;

use App\Core\App;

/**
 * XNXX through RapidAPI (porn-xnxx-api.p.rapidapi.com).
 * POST /search {q, page} -> {count, page, results:[{title, thumbnail, duration, views, video_link}]}
 * Each configured query is fetched once per import; the page cursor rotates between runs so
 * the 6-hourly cron keeps discovering new videos without burning extra quota.
 */
class XnxxRapidApiAdapter implements SourceAdapter {
    public const DEFAULT_HOST = 'porn-xnxx-api.p.rapidapi.com';
    public const DEFAULT_QUERIES = 'milf,teen,anal,amateur,lesbian,asian,latina,ebony,big tits,blowjob,hardcore,pov';
    private const MAX_PAGE = 8;

    public function slug(): string { return 'xnxx_rapidapi'; }
    public function label(): string { return 'XNXX (RapidAPI)'; }

    public function fetch(int $limit = 400): iterable {
        $key = App::config('sources.xnxx_rapidapi.api_key');
        $host = App::config('sources.xnxx_rapidapi.host') ?: self::DEFAULT_HOST;
        if (!$key) throw new \RuntimeException('RapidAPI key missing');
        $queries = array_values(array_filter(array_map('trim', explode(',', App::config('sources.xnxx_rapidapi.queries') ?: self::DEFAULT_QUERIES))));
        $page = max(1, (int)App::config('sources.xnxx_rapidapi.page_cursor', 1));

        $out = []; $errors = [];
        foreach ($queries as $q) {
            if (count($out) >= $limit) break;
            try {
                foreach ($this->search($host, $key, $q, $page) as $item) {
                    $mapped = $this->map($item, $q);
                    if ($mapped) $out[] = $mapped;
                    if (count($out) >= $limit) break;
                }
            } catch (\Throwable $e) { $errors[] = "$q: " . $e->getMessage(); }
        }
        if (!$out && $errors) throw new \RuntimeException(implode(' | ', array_slice($errors, 0, 3)));
        $this->saveCursor($page >= self::MAX_PAGE ? 1 : $page + 1);
        return $out;
    }

    private function search(string $host, string $key, string $q, int $page): array {
        $ch = curl_init("https://{$host}/search");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['q' => $q, 'page' => $page]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-RapidAPI-Key: ' . $key, 'X-RapidAPI-Host: ' . $host],
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) throw new \RuntimeException('curl: ' . $err);
        $data = json_decode($resp, true);
        if ($code >= 400 || !is_array($data)) throw new \RuntimeException("HTTP {$code}: " . substr((string)($data['message'] ?? $data['error'] ?? $resp), 0, 120));
        return $data['results'] ?? [];
    }

    private function map(array $item, string $q): ?array {
        $link = trim($item['video_link'] ?? '');
        if (!preg_match('~/video-([a-z0-9]+)/~i', $link, $m)) return null;
        $id = $m[1];
        $title = trim(preg_replace('/\s+/', ' ', (string)($item['title'] ?? '')));
        $title = $title !== '' ? mb_convert_case($title, MB_CASE_TITLE, 'UTF-8') : 'Untitled';
        $tags = array_values(array_unique(array_filter(array_map('trim', explode(' ', $q)), fn($w) => mb_strlen($w) > 2)));
        return [
            'source' => $this->slug(),
            'source_video_id' => $id,
            'title' => $title,
            'description' => $title . ' — ' . ucfirst($q) . ' video from XNXX.',
            'thumbnail' => trim($item['thumbnail'] ?? ''),
            'preview' => '',
            'embed_url' => 'https://www.xnxx.com/embedframe/' . $id,
            'page_url' => $link,
            'duration' => self::parseDuration((string)($item['duration'] ?? '')),
            'views' => self::parseViews((string)($item['views'] ?? '')),
            'rating' => 8.0,
            'quality' => 'HD',
            'is_featured' => 0,
            'published_at' => date('Y-m-d H:i:s'),
            'categories' => [ucwords($q)],
            'tags' => array_merge([$q], $tags),
        ];
    }

    public static function parseDuration(string $d): int {
        $d = strtolower(trim($d));
        if ($d === '') return 0;
        if (str_contains($d, ':')) {
            $parts = array_reverse(array_map('intval', explode(':', $d)));
            return ($parts[0] ?? 0) + 60 * ($parts[1] ?? 0) + 3600 * ($parts[2] ?? 0);
        }
        $s = 0;
        if (preg_match('/(\d+)\s*h/', $d, $m)) $s += 3600 * (int)$m[1];
        if (preg_match('/(\d+)\s*min/', $d, $m)) $s += 60 * (int)$m[1];
        if (preg_match('/(\d+)\s*sec/', $d, $m)) $s += (int)$m[1];
        return $s ?: (int)$d * 60;
    }

    public static function parseViews(string $v): int {
        $v = strtolower(str_replace([',', ' '], '', trim($v)));
        if (!preg_match('/^([\d.]+)\s*([kmb])?/', $v, $m)) return 0;
        $mult = ['k' => 1e3, 'm' => 1e6, 'b' => 1e9][$m[2] ?? ''] ?? 1;
        return (int)min(PHP_INT_MAX, round((float)$m[1] * $mult));
    }

    private function saveCursor(int $page): void {
        $db = App::$db;
        $st = $db->prepare("SELECT config FROM sources WHERE slug=?"); $st->execute([$this->slug()]);
        $cfg = json_decode($st->fetchColumn() ?: '{}', true) ?: [];
        $cfg['page_cursor'] = $page;
        $db->prepare("UPDATE sources SET config=? WHERE slug=?")->execute([json_encode($cfg), $this->slug()]);
    }
}
