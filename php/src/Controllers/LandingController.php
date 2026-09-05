<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Landing;

class LandingController {
    public function show(array $p): void {
        $l = Landing::bySlug($p['slug']);
        if (!$l) { http_response_code(404); View::render('pages/404',['title'=>'Not Found']); return; }
        Landing::incrementViews((int)$l['id']);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = Landing::videos($l, $page, 24);

        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? (!empty($_SERVER['HTTPS']) ? 'https' : 'http');
        $url = $scheme . '://' . $host . '/l/' . rawurlencode($l['slug']);
        $seoTitle = $l['meta_title'] ?: ($l['title'] . ' — DAVISPORN');
        $intro = trim((string)$l['intro']);
        $seoDesc = $l['meta_description'] ?: (mb_substr(strip_tags($intro), 0, 160) ?: ($l['title'] . ' — curated adult video collection on DAVISPORN.'));
        $ogImage = $l['og_image'] ?: ($data['items'][0]['thumbnail'] ?? '');
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $head = "\n<meta property=\"og:type\" content=\"video.other\">\n"
              . "<meta property=\"og:site_name\" content=\"DAVISPORN\">\n"
              . "<meta property=\"og:url\" content=\"" . $e($url) . "\">\n"
              . "<meta property=\"og:title\" content=\"" . $e($seoTitle) . "\">\n"
              . "<meta property=\"og:description\" content=\"" . $e($seoDesc) . "\">\n"
              . ($ogImage ? "<meta property=\"og:image\" content=\"" . $e($ogImage) . "\">\n" : '')
              . "<meta name=\"twitter:card\" content=\"" . ($ogImage ? 'summary_large_image' : 'summary') . "\">\n"
              . "<meta name=\"twitter:title\" content=\"" . $e($seoTitle) . "\">\n"
              . "<meta name=\"twitter:description\" content=\"" . $e($seoDesc) . "\">\n"
              . ($ogImage ? "<meta name=\"twitter:image\" content=\"" . $e($ogImage) . "\">\n" : '');

        View::render('pages/landing', [
            'title' => $seoTitle,
            'meta_description' => $seoDesc,
            'head_extra' => $head,
            'landing' => $l,
            'items' => $data['items'],
            'pages' => $data['pages'],
            'page' => $data['page'],
            'total' => $data['total'],
        ]);
    }
}
