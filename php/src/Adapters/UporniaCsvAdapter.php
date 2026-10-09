<?php
namespace App\Adapters;

use App\Core\App;

class UporniaCsvAdapter implements SourceAdapter {
    public function slug(): string { return 'upornia_csv'; }
    public function label(): string { return 'Upornia XML Feed'; }

    public function fetch(int $limit = 100): iterable {
        $url = App::config('sources.upornia_csv.feed_url');
        if (!$url) {
            throw new \RuntimeException('Upornia feed URL is not configured');
        }
        if (!class_exists('XMLReader')) {
            throw new \RuntimeException('PHP XMLReader extension is required for the Upornia feed');
        }
        return $this->parseXml($url, $limit);
    }

    private function parseXml(string $url, int $limit): \Generator {
        $this->validateFeedUrl($url);
        $context = stream_context_create([
            'http' => ['timeout' => 90, 'user_agent' => 'DavispornBot/1.0'],
        ]);
        libxml_set_streams_context($context);
        $reader = new \XMLReader();
        if (!@$reader->open($url, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('Cannot open Upornia XML feed');
        }
        $seen = 0;
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'video') {
                    continue;
                }
                $xml = simplexml_load_string(
                    $reader->readOuterXML(),
                    \SimpleXMLElement::class,
                    LIBXML_NONET | LIBXML_NOCDATA
                );
                if (!$xml) {
                    continue;
                }
                $item = $this->mapVideo($xml);
                if ($item !== null) {
                    yield $item;
                    $seen++;
                    if ($seen >= $limit) {
                        break;
                    }
                }
            }
        } finally {
            $reader->close();
        }
        if ($seen === 0) {
            throw new \RuntimeException('Upornia XML feed had no valid video records');
        }
    }

    private function mapVideo(\SimpleXMLElement $video): ?array {
        $id = trim((string)$video->id);
        $title = $this->cleanText((string)$video->title);
        $pageUrl = trim((string)$video->link);
        if ($id === '' || $title === '' || !filter_var($pageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }
        $embed = $this->embedUrl((string)$video->embed);
        $thumbnail = trim((string)($video->screens->screen[0] ?? ''));
        $categories = $this->splitList((string)$video->categories);
        $tags = array_merge(
            $this->splitList((string)$video->tags),
            $this->splitList((string)$video->models)
        );
        $published = trim((string)$video->post_date);
        return [
            'source' => $this->slug(),
            'source_video_id' => $id,
            'title' => $title,
            'description' => $this->cleanText((string)$video->description) ?: $title,
            'thumbnail' => $thumbnail,
            'preview' => trim((string)$video->preview_url),
            'embed_url' => $embed,
            'page_url' => $pageUrl,
            'duration' => (int)$video->duration,
            'views' => (int)$video->popularity,
            'rating' => (float)$video->rating,
            'quality' => 'HD',
            'is_featured' => 0,
            'published_at' => $this->publishedAt($published),
            'categories' => $categories,
            'tags' => array_slice(array_values(array_unique($tags)), 0, 30),
        ];
    }

    private function validateFeedUrl(string $url): void {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || $host !== 'upornia.com'
            || $path !== '/admin/feeds/embed/') {
            $message = 'Upornia feed must use the configured official HTTPS XML endpoint';
            throw new \RuntimeException($message);
        }
    }

    private function embedUrl(string $markup): string {
        $decoded = html_entity_decode($markup, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('~\ssrc=["\']([^"\']+)["\']~i', $decoded, $match)) {
            return trim($match[1]);
        }
        return '';
    }

    private function splitList(string $value): array {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function cleanText(string $value): string {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', strip_tags($decoded)) ?: '');
    }

    private function publishedAt(string $value): string {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        return date('Y-m-d H:i:s');
    }
}
