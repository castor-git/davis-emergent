<?php
namespace App\Support;

use App\Core\App;
use App\Models\Landing;

class LandingCopyGenerator {
    public static function generate(int $landingId): array {
        $landing = Landing::find($landingId);
        if (!$landing) {
            throw new \RuntimeException('Landing not found');
        }
        $copy = self::callSidecar($landing);
        App::$db->prepare('UPDATE landings SET intro=?, meta_description=? WHERE id=?')
            ->execute([$copy['intro'], $copy['meta_description'], $landingId]);
        \App\Core\Cache::forget();
        return $copy;
    }

    public static function generateMissing(int $max = 3): array {
        $limit = max(1, min($max, 10));
        $sql = "SELECT id FROM landings
                WHERE (intro IS NULL OR intro='')
                   OR (meta_description IS NULL OR meta_description='')
                ORDER BY id DESC LIMIT {$limit}";
        $rows = App::$db->query($sql)->fetchAll();
        $done = [];
        foreach ($rows as $row) {
            try {
                $id = (int)$row['id'];
                self::generate($id);
                $done[] = $id;
            } catch (\Throwable $exception) {
                error_log('landing copy #' . $row['id'] . ': ' . $exception->getMessage());
            }
        }
        return $done;
    }

    private static function callSidecar(array $landing): array {
        $secret = getenv('WEBHOOK_CRON_SECRET') ?: '';
        if (!$secret) {
            throw new \RuntimeException('WEBHOOK_CRON_SECRET missing');
        }
        $base = rtrim(getenv('AI_SIDECAR_URL') ?: 'http://127.0.0.1:8001', '/');
        $payload = [
            'title' => (string)$landing['title'],
            'keyword' => (string)($landing['keyword'] ?? ''),
            'categories' => json_decode($landing['categories_json'] ?: '[]', true) ?: [],
            'tags' => json_decode($landing['tags_json'] ?: '[]', true) ?: [],
        ];
        $ch = curl_init($base . '/api/ai/landing-copy');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Internal-Secret: ' . $secret,
            ],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($response === false) {
            throw new \RuntimeException('AI sidecar unreachable: ' . $error);
        }
        $data = json_decode($response, true);
        $copy = $data['copy'] ?? null;
        if ($code >= 400 || !is_array($copy)) {
            $detail = $data['detail'] ?? $response;
            throw new \RuntimeException('AI copy failed (HTTP ' . $code . '): ' . mb_substr((string)$detail, 0, 200));
        }
        $intro = trim((string)($copy['intro'] ?? ''));
        $meta = trim((string)($copy['meta_description'] ?? ''));
        if ($intro === '' || $meta === '') {
            throw new \RuntimeException('AI copy response was incomplete');
        }
        return [
            'intro' => mb_substr(strip_tags($intro), 0, 1000),
            'meta_description' => mb_substr(strip_tags($meta), 0, 300),
        ];
    }
}