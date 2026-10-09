<?php
namespace App\Support;

use App\Core\App;

class XVideosDeadCleaner {
    public static function run(): array {
        $url = trim((string)App::config('sources.xvideos_csv.deleted_feed_url', ''));
        if ($url === '') {
            return ['ok' => true, 'skipped' => true, 'hidden' => 0, 'message' => 'Skipped: no deleted-URL feed configured'];
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            throw new \RuntimeException('Deleted-URL feed must be a valid HTTPS URL');
        }
        $context = stream_context_create([
            'http' => ['timeout' => 60, 'user_agent' => 'DavispornBot/1.0', 'follow_location' => 1],
        ]);
        $handle = @fopen($url, 'r', false, $context);
        if (!$handle) {
            throw new \RuntimeException('Cannot open XVideos deleted-URL feed');
        }
        if (str_ends_with(strtolower($url), '.gz')) {
            stream_filter_append($handle, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 47]);
        }

        $ids = [];
        $urls = [];
        while (($line = fgets($handle)) !== false) {
            foreach (self::identifiersFromLine($line) as $identifier) {
                if (ctype_digit($identifier)) {
                    $ids[$identifier] = true;
                } else {
                    $urls[$identifier] = true;
                }
            }
        }
        fclose($handle);
        $hidden = self::markUnavailable(array_keys($ids), array_keys($urls));
        SourceManager::recount();
        App::$db->prepare('UPDATE sources SET last_status=? WHERE slug=?')->execute([
            "Cleanup OK — hidden {$hidden} unavailable video(s)",
            'xvideos_csv',
        ]);
        \App\Core\Cache::forget();
        return ['ok' => true, 'skipped' => false, 'hidden' => $hidden, 'message' => "Hidden {$hidden} unavailable video(s)"];
    }

    public static function runAsync(): void {
        $root = dirname(__DIR__, 2);
        $command = sprintf(
            'nohup %s %s >> %s 2>&1 &',
            escapeshellarg(PHP_BINARY ?: 'php'),
            escapeshellarg($root . '/bin/cleanup_dead.php'),
            escapeshellarg($root . '/storage/logs/dead-cleanup.log')
        );
        exec($command);
    }

    private static function identifiersFromLine(string $line): array {
        $found = [];
        if (preg_match_all('~xvideos\.com/(?:video\.)?([a-z0-9]+)~i', $line, $matches)) {
            foreach ($matches[1] as $id) {
                $found[] = strtolower($id);
            }
        }
        foreach (preg_split('/[,;|\t\s]+/', trim($line)) as $field) {
            if (preg_match('/^\d{4,}$/', $field)) {
                $found[] = $field;
            }
        }
        return array_values(array_unique($found));
    }

    private static function markUnavailable(array $ids, array $codes): int {
        $db = App::$db;
        $sets = [];
        $params = [];
        if ($ids) {
            $sets[] = 'source_video_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        if ($codes) {
            $codeConditions = [];
            foreach ($codes as $code) {
                $codeConditions[] = 'page_url LIKE ?';
                $params[] = '%/' . $code . '/%';
            }
            $sets[] = '(' . implode(' OR ', $codeConditions) . ')';
        }
        if (!$sets) {
            return 0;
        }
        $sql = 'UPDATE videos SET is_available=0, unavailable_at=NOW() '
            . "WHERE source='xvideos_csv' AND is_available=1 AND (" . implode(' OR ', $sets) . ')';
        $statement = $db->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }
}