<?php
namespace App\Support;

use App\Core\App;

class UporniaDeletedCleaner {
    private const BATCH_SIZE = 1000;

    public static function run(): int {
        $db = App::$db;
        $locked = (int)$db->query("SELECT GET_LOCK('upornia_deleted_cleanup', 0)")->fetchColumn();
        if ($locked !== 1) {
            return 0;
        }
        try {
            return self::streamAndHide();
        } finally {
            $db->query("SELECT RELEASE_LOCK('upornia_deleted_cleanup')");
        }
    }

    private static function streamAndHide(): int {
        $url = trim((string)App::config('sources.upornia_csv.deleted_feed_url', ''));
        self::validateUrl($url);
        $context = stream_context_create([
            'http' => ['timeout' => 90, 'user_agent' => 'DavispornBot/1.0'],
        ]);
        $handle = @fopen($url, 'r', false, $context);
        if (!$handle) {
            throw new \RuntimeException('Cannot open Upornia deleted-video feed');
        }
        $ids = [];
        $hidden = 0;
        try {
            while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                $id = trim((string)($row[0] ?? ''));
                if (!ctype_digit($id)) {
                    continue;
                }
                $ids[$id] = true;
                if (count($ids) >= self::BATCH_SIZE) {
                    $hidden += self::hideIds(array_keys($ids));
                    $ids = [];
                }
            }
            if ($ids) {
                $hidden += self::hideIds(array_keys($ids));
            }
        } finally {
            fclose($handle);
        }
        return $hidden;
    }

    private static function hideIds(array $ids): int {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = App::$db->prepare(
            "UPDATE videos SET is_available=0, unavailable_at=NOW()
             WHERE source='upornia_csv' AND is_available=1 AND source_video_id IN ({$marks})"
        );
        $statement->execute($ids);
        return $statement->rowCount();
    }

    private static function validateUrl(string $url): void {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'upornia.com'
            || ($parts['path'] ?? '') !== '/api/videos_feed2.php') {
            $message = 'Upornia deleted feed must use the official HTTPS API endpoint';
            throw new \RuntimeException($message);
        }
    }
}