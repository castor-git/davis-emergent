<?php
namespace App\Adapters;

use App\Core\App;

/**
 * Upornia CSV feed adapter.
 * Expected CSV columns: video_id,title,description,tags,categories,duration,thumb,preview,embed,page_url,views
 */
class UporniaCsvAdapter implements SourceAdapter {
    public function slug(): string { return 'upornia_csv'; }
    public function label(): string { return 'Upornia CSV Feed'; }

    public function fetch(int $limit = 100): iterable {
        $url = App::config('sources.upornia_csv.feed_url');
        if (!$url) { throw new \RuntimeException('Upornia CSV feed URL is not configured'); }
        return $this->parseCsv($url, $limit);
    }

    protected function parseCsv(string $url, int $limit): iterable {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'DavispornBot/1.0']]);
        $fh = @fopen($url, 'r', false, $ctx);
        if (!$fh) { throw new \RuntimeException('Cannot open Upornia CSV feed'); }
        $header = fgetcsv($fh);
        if (!$header) { fclose($fh); return []; }
        $header = array_map(fn($h)=>strtolower(trim($h)), $header);
        $out = [];
        $i = 0;
        while (($row = fgetcsv($fh)) !== false && $i < $limit) {
            $r = array_combine($header, array_pad($row, count($header), ''));
            $out[] = [
                'source' => $this->slug(),
                'source_video_id' => (string)($r['video_id'] ?? md5($r['title'] ?? uniqid())),
                'title' => $r['title'] ?? 'Untitled',
                'description' => $r['description'] ?? '',
                'thumbnail' => $r['thumb'] ?? '',
                'preview' => $r['preview'] ?? '',
                'embed_url' => $r['embed'] ?? '',
                'page_url' => $r['page_url'] ?? '',
                'duration' => (int)($r['duration'] ?? 0),
                'views' => (int)($r['views'] ?? 0),
                'rating' => 8.0,
                'quality' => 'HD',
                'is_featured' => 0,
                'published_at' => date('Y-m-d H:i:s'),
                'categories' => array_filter(array_map('trim', explode('|', $r['categories'] ?? ''))),
                'tags' => array_filter(array_map('trim', explode('|', $r['tags'] ?? ''))),
            ];
            $i++;
        }
        fclose($fh);
        return $out;
    }
}
