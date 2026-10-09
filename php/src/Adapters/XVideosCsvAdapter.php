<?php
namespace App\Adapters;

use App\Core\App;

/**
 * XVideos webmaster CSV export (https://info.xvideos.net/db).
 * Semicolon-separated, no header, 15 columns:
 * 0 url | 1 title | 2 duration ("375 sec") | 3 thumb | 4 embed iframe | 5 tags (csv) | 6 pornstars (csv)
 * 7 numeric id | 8 main category | 9 quality (1080P/720P/SD) | 10 uploader | 11 - | 12 upload date | 13 preview thumb | 14 views
 * Supports .csv.gz (streamed, stops after $limit rows so the 800MB full export is never fully downloaded) and plain .csv.
 */
class XVideosCsvAdapter implements SourceAdapter {
    public const DEFAULT_FEED = 'https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-export-week.csv.gz';

    public function slug(): string { return 'xvideos_csv'; }
    public function label(): string { return 'XVideos CSV Import'; }

    public function fetch(int $limit = 500): iterable {
        $url = App::config('sources.xvideos_csv.feed_url') ?: self::DEFAULT_FEED;
        if (str_ends_with(strtolower($url), '.zip')) {
            throw new \RuntimeException('ZIP feeds cannot be streamed — use the .csv.gz variant of the same export');
        }
        $ctx = stream_context_create(['http' => ['timeout' => 60, 'user_agent' => 'DavispornBot/1.0', 'follow_location' => 1]]);
        $fh = @fopen($url, 'r', false, $ctx);
        if (!$fh) throw new \RuntimeException('Cannot open XVideos feed: ' . $url);
        if (str_ends_with(strtolower($url), '.gz')) {
            stream_filter_append($fh, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 47]);
        }
        $out = [];
        while (count($out) < $limit && ($row = fgetcsv($fh, 0, ';', '"', '')) !== false) {
            if (count($row) < 9 || trim($row[0]) === '') continue;
            $item = $this->mapRow($row);
            if ($item) $out[] = $item;
        }
        fclose($fh);
        return $out;
    }

    private function mapRow(array $r): ?array {
        $r = array_pad($r, 15, '');
        $pageUrl = trim($r[0]);
        if (!preg_match('~/video\.?([a-z0-9]+)/~i', $pageUrl, $m)) return null;
        $code = $m[1];
        $embed = '';
        if (preg_match('~src="([^"]+)"~', $r[4], $em)) $embed = $em[1];
        if (!$embed) $embed = 'https://www.xvideos.com/embedframe/' . $code;
        $cat = trim($r[8]);
        $cats = ($cat !== '' && strcasecmp($cat, 'unknown') !== 0) ? [str_replace('_', ' ', $cat)] : [];
        $tags = array_slice(array_values(array_filter(array_map('trim', explode(',', $r[5])))), 0, 25);
        $stars = array_slice(array_values(array_filter(array_map('trim', explode(',', $r[6])))), 0, 8);
        $title = self::cleanTitle($r[1]);
        return [
            'source' => $this->slug(),
            'source_video_id' => trim($r[7]) !== '' ? trim($r[7]) : $code,
            'title' => $title,
            'description' => $title . ($stars ? ' — starring ' . implode(', ', $stars) : '') . ($r[10] !== '' ? ' · uploaded by ' . trim($r[10]) : ''),
            'thumbnail' => trim($r[3]),
            'preview' => trim($r[13]),
            'embed_url' => $embed,
            'page_url' => $pageUrl,
            'duration' => (int)preg_replace('/\D+/', '', $r[2]),
            'views' => (int)preg_replace('/\D+/', '', $r[14]),
            'rating' => 8.0,
            'quality' => self::mapQuality($r[9]),
            'is_featured' => 0,
            'published_at' => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($r[12])) ? trim($r[12]) . ' 00:00:00' : date('Y-m-d H:i:s'),
            'categories' => $cats,
            'tags' => array_merge($tags, $stars),
            'actors' => $stars,
            'studios' => trim($r[10]) !== '' ? [trim($r[10])] : [],
        ];
    }

    public static function cleanTitle(string $t): string {
        // Feed mangles entities as "&#039_" / "&mdash_" — restore the trailing ";" then decode
        $t = preg_replace('/&(#?[a-z0-9]+)_/i', '&$1;', $t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $t)) ?: 'Untitled';
    }

    public static function mapQuality(string $q): string {
        $q = strtoupper(trim($q));
        return match (true) {
            str_starts_with($q, '2160') || str_contains($q, '4K') => '4K',
            str_starts_with($q, '1080') => 'FullHD',
            str_starts_with($q, '720') => 'HD',
            $q === '' => 'HD',
            default => 'SD',
        };
    }
}
