<?php
namespace App\Support;

use App\Core\App;
use App\Models\Landing;

/**
 * Generates safe-for-work "premium dark" social covers for landing pages via the
 * Python AI sidecar (/api/ai/image → Gemini Nano Banana) and stores them under public/generated/.
 */
class CoverGenerator {
    public const DIR = '/generated';

    public static function prompt(array $l): string {
        $title = mb_substr(trim(preg_replace('/\s*—\s*curated$/iu', '', $l['title'] ?? '')), 0, 60) ?: 'Curated collection';
        return "Design a premium dark social-media cover image in 16:9 landscape format (1200x630 style). "
            . "Abstract composition only: deep charcoal/black background (#0b0d12), bold glowing red (#e10600) geometric shapes and light streaks, "
            . "subtle film grain, cinematic depth, high contrast editorial poster look. "
            . "Large bold white sans-serif headline centered that reads exactly: \"{$title}\". "
            . "Small caption in the lower-left corner reading: \"DAVISPORN · curated collection\". "
            . "Strictly no people, no faces, no bodies, no nudity, no explicit or suggestive imagery — abstract typography poster only.";
    }

    /** Generate + persist a cover for one landing. Returns the public path (e.g. /generated/landing-3-...png). */
    public static function generate(int $landingId): string {
        $l = Landing::find($landingId);
        if (!$l) throw new \RuntimeException('Landing not found');

        $img = self::callSidecar(self::prompt($l));
        $ext = str_contains($img['mime_type'], 'jpeg') ? 'jpg' : (str_contains($img['mime_type'], 'webp') ? 'webp' : 'png');
        $dir = dirname(__DIR__, 2) . '/public' . self::DIR;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = sprintf('landing-%d-%s.%s', $landingId, substr(md5(uniqid('', true)), 0, 8), $ext);
        if (file_put_contents("$dir/$name", $img['bytes']) === false) throw new \RuntimeException('Cannot write cover file');

        self::deleteOld($l['og_image'] ?? '');
        App::$db->prepare("UPDATE landings SET og_image=? WHERE id=?")->execute([self::DIR . "/$name", $landingId]);
        \App\Core\Cache::forget();
        return self::DIR . "/$name";
    }

    /** Auto-cover drafts (active=0) that still have no og_image — used by the daily cron. */
    public static function generateMissing(int $max = 3): array {
        $rows = App::$db->query("SELECT id FROM landings WHERE active=0 AND (og_image IS NULL OR og_image='') ORDER BY id DESC LIMIT " . (int)$max)->fetchAll();
        $done = [];
        foreach ($rows as $r) {
            try { $done[(int)$r['id']] = self::generate((int)$r['id']); }
            catch (\Throwable $e) { error_log('cover generator #' . $r['id'] . ': ' . $e->getMessage()); }
        }
        return $done;
    }

    private static function callSidecar(string $prompt): array {
        $secret = getenv('WEBHOOK_CRON_SECRET') ?: '';
        if (!$secret) throw new \RuntimeException('WEBHOOK_CRON_SECRET missing');
        $base = rtrim(getenv('AI_SIDECAR_URL') ?: 'http://127.0.0.1:8001', '/');
        $ch = curl_init($base . '/api/ai/image');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['prompt' => $prompt]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 150,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Internal-Secret: ' . $secret],
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false) throw new \RuntimeException('AI sidecar unreachable: ' . $err);
        $data = json_decode($resp, true);
        if ($code >= 400 || empty($data['data'])) {
            throw new \RuntimeException('AI image failed (HTTP ' . $code . '): ' . mb_substr((string)($data['detail'] ?? $resp), 0, 200));
        }
        $bytes = base64_decode($data['data'], true);
        if (!$bytes) throw new \RuntimeException('AI image: invalid base64 payload');
        return ['bytes' => $bytes, 'mime_type' => (string)($data['mime_type'] ?? 'image/png')];
    }

    private static function deleteOld(string $path): void {
        if (!str_starts_with($path, self::DIR . '/')) return;
        $file = dirname(__DIR__, 2) . '/public' . $path;
        if (is_file($file)) @unlink($file);
    }
}
