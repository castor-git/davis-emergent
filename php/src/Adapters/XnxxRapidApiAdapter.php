<?php
namespace App\Adapters;

use App\Core\App;

/**
 * XNXX videos through RapidAPI.
 * Endpoint hosts vary; we call {host}/search?query=... or /latest and normalize response.
 */
class XnxxRapidApiAdapter implements SourceAdapter {
    public function slug(): string { return 'xnxx_rapidapi'; }
    public function label(): string { return 'XNXX (RapidAPI)'; }

    public function fetch(int $limit = 60): iterable {
        $key = App::config('sources.xnxx_rapidapi.api_key');
        $host = App::config('sources.xnxx_rapidapi.host');
        if (!$key) throw new \RuntimeException('RapidAPI key missing');

        $ch = curl_init("https://{$host}/latest?page=1");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'X-RapidAPI-Key: ' . $key,
                'X-RapidAPI-Host: ' . $host,
            ],
        ]);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) throw new \RuntimeException('RapidAPI request failed: ' . $err);
        $data = json_decode($resp, true);
        $list = $data['data'] ?? $data['videos'] ?? $data['results'] ?? [];
        $out = [];
        foreach (array_slice($list, 0, $limit) as $i => $item) {
            $out[] = [
                'source' => $this->slug(),
                'source_video_id' => (string)($item['id'] ?? $item['video_id'] ?? ('xnxx-' . $i)),
                'title' => $item['title'] ?? 'Untitled',
                'description' => $item['description'] ?? ($item['title'] ?? ''),
                'thumbnail' => $item['thumbnail'] ?? $item['image'] ?? '',
                'preview' => $item['preview'] ?? '',
                'embed_url' => $item['embed'] ?? $item['embed_url'] ?? '',
                'page_url' => $item['url'] ?? '',
                'duration' => (int)($item['duration'] ?? 0),
                'views' => (int)($item['views'] ?? 0),
                'rating' => (float)($item['rating'] ?? 8.0),
                'quality' => $item['quality'] ?? 'HD',
                'is_featured' => 0,
                'published_at' => date('Y-m-d H:i:s'),
                'categories' => (array)($item['categories'] ?? []),
                'tags' => (array)($item['tags'] ?? $item['keywords'] ?? []),
            ];
        }
        return $out;
    }
}
