<?php
namespace App\Support;

use App\Core\App;

class XVideosDeadCleaner {
    public const FULL_FEED =
        'https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-deleted-full.csv.gz';
    public const WEEK_FEED =
        'https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-deleted-week.csv.gz';
    private const BATCH_SIZE = 5000;

    public static function run(string $mode = 'week'): array {
        $mode = self::validateMode($mode);
        $db = App::$db;
        $locked = (int)$db->query("SELECT GET_LOCK('xvideos_deleted_cleanup', 0)")->fetchColumn();
        if ($locked !== 1) {
            return [
                'ok' => true,
                'skipped' => true,
                'hidden' => 0,
                'message' => 'Skipped: another deleted-video cleanup is running',
            ];
        }

        try {
            return self::streamAndHide($mode);
        } finally {
            $db->query("SELECT RELEASE_LOCK('xvideos_deleted_cleanup')");
        }
    }

    public static function runAsync(string $mode = 'week'): void {
        $mode = self::validateMode($mode);
        $root = dirname(__DIR__, 2);
        $command = sprintf(
            'nohup %s %s %s >> %s 2>&1 &',
            escapeshellarg(PHP_BINARY ?: 'php'),
            escapeshellarg($root . '/bin/cleanup_dead.php'),
            escapeshellarg($mode),
            escapeshellarg($root . '/storage/logs/dead-cleanup.log')
        );
        exec($command);
    }

    private static function streamAndHide(string $mode): array {
        set_time_limit(0);
        $url = self::feedUrl($mode);
        $handle = self::openGzipStream($url);
        $db = App::$db;
        $db->exec('DROP TEMPORARY TABLE IF EXISTS tmp_xvideos_deleted');
        $db->exec("CREATE TEMPORARY TABLE tmp_xvideos_deleted (
            page_url VARCHAR(500) CHARACTER SET ascii NOT NULL DEFAULT '',
            source_video_id VARCHAR(191) CHARACTER SET ascii NOT NULL DEFAULT '',
            PRIMARY KEY (page_url, source_video_id)
        ) ENGINE=InnoDB");
        $insert = $db->prepare(
            'INSERT IGNORE INTO tmp_xvideos_deleted (page_url, source_video_id) VALUES (?, ?)'
        );

        $feedRows = 0;
        $queued = 0;
        $hidden = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                foreach (self::urlsFromLine($line) as $url) {
                    $insert->execute([$url, self::videoIdentifier($url)]);
                    $feedRows++;
                    $queued++;
                    if ($queued >= self::BATCH_SIZE) {
                        $hidden += self::hideBatch();
                        $queued = 0;
                    }
                }
            }
            if ($queued > 0) {
                $hidden += self::hideBatch();
            }
        } finally {
            fclose($handle);
            $db->exec('DROP TEMPORARY TABLE IF EXISTS tmp_xvideos_deleted');
        }

        SourceManager::recount();
        $message = sprintf(
            'Cleanup %s OK — processed %d deleted URL(s), hidden %d video(s)',
            $mode,
            $feedRows,
            $hidden
        );
        $db->prepare('UPDATE sources SET last_status=? WHERE slug=?')->execute([
            $message,
            'xvideos_csv',
        ]);
        \App\Core\Cache::forget();
        return [
            'ok' => true,
            'skipped' => false,
            'feed_rows' => $feedRows,
            'hidden' => $hidden,
            'message' => $message,
        ];
    }

    private static function hideBatch(): int {
        $sql = "UPDATE videos v
                JOIN tmp_xvideos_deleted d
                  ON v.page_url = d.page_url
                  OR (d.source_video_id <> '' AND v.source_video_id = d.source_video_id)
                SET v.is_available=0, v.unavailable_at=NOW()
                WHERE v.source='xvideos_csv' AND v.is_available=1";
        $statement = App::$db->prepare($sql);
        $statement->execute();
        App::$db->exec('TRUNCATE TABLE tmp_xvideos_deleted');
        return $statement->rowCount();
    }

    private static function openGzipStream(string $url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 90,
                'user_agent' => 'DavispornBot/1.0',
                'follow_location' => 0,
            ],
        ]);
        $handle = @fopen($url, 'r', false, $context);
        if (!$handle) {
            throw new \RuntimeException('Cannot open official XVideos deleted-URL feed');
        }
        if (stream_filter_append($handle, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 47]) === false) {
            fclose($handle);
            throw new \RuntimeException('Cannot read XVideos GZIP feed');
        }
        return $handle;
    }

    private static function feedUrl(string $mode): string {
        $key = $mode === 'full' ? 'deleted_full_feed_url' : 'deleted_feed_url';
        $fallback = $mode === 'full' ? self::FULL_FEED : self::WEEK_FEED;
        $url = trim((string)App::config("sources.xvideos_csv.{$key}", $fallback));
        $allowed = $mode === 'full' ? self::FULL_FEED : self::WEEK_FEED;
        if (!hash_equals($allowed, $url)) {
            throw new \RuntimeException('Deleted-feed URL must use the official XVideos GZIP export');
        }
        return $url;
    }

    private static function urlsFromLine(string $line): array {
        $urls = [];
        if (preg_match_all('~https?://[^\s"\',;]+~i', $line, $matches)) {
            foreach ($matches[0] as $raw) {
                $url = self::normalizeUrl($raw);
                if ($url !== null) {
                    $urls[$url] = true;
                }
            }
        }
        return array_keys($urls);
    }

    private static function normalizeUrl(string $raw): ?string {
        $parts = parse_url(trim($raw));
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = '/' . ltrim((string)($parts['path'] ?? ''), '/');
        $officialHost = $host === 'xvideos.com' || str_ends_with($host, '.xvideos.com');
        if (($parts['scheme'] ?? '') === '' || !$officialHost || $path === '/') {
            return null;
        }
        return 'https://' . $host . rtrim($path, '/');
    }

    private static function videoIdentifier(string $url): string {
        if (preg_match('~/video\.?([a-z0-9]+)(?:/|$)~i', $url, $match)) {
            return strtolower($match[1]);
        }
        return '';
    }

    private static function validateMode(string $mode): string {
        if (!in_array($mode, ['full', 'week'], true)) {
            throw new \InvalidArgumentException('Cleanup mode must be full or week');
        }
        return $mode;
    }
}