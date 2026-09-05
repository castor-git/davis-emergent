<?php
namespace App\Adapters;

use App\Core\App;

/** XVideos CSV import adapter (columns: id,title,tags,category,duration_seconds,thumb_url,embed_frame,url,views_count) */
class XVideosCsvAdapter extends UporniaCsvAdapter {
    public function slug(): string { return 'xvideos_csv'; }
    public function label(): string { return 'XVideos CSV Import'; }
    public function fetch(int $limit = 100): iterable {
        $url = App::config('sources.xvideos_csv.feed_url');
        if (!$url) { throw new \RuntimeException('XVideos CSV feed URL is not configured'); }
        $items = [];
        foreach ($this->parseCsv($url, $limit) as $r) {
            $r['source'] = $this->slug();
            $items[] = $r;
        }
        return $items;
    }
}
