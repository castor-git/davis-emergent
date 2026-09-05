<?php
namespace App\Controllers;

use App\Core\{View, App};

class SeoController {
    public function robots(): void {
        header('Content-Type: text/plain');
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') ?: (!empty($_SERVER['HTTPS']) ? 'https' : 'http');
        echo "User-agent: *\nAllow: /\nSitemap: {$scheme}://{$host}/sitemap.xml\n";
    }

    public function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') ?: (!empty($_SERVER['HTTPS']) ? 'https' : 'http');
        $host = $scheme . '://' . $host;
        $out = ['<?xml version="1.0" encoding="UTF-8"?>','<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach (['/', '/videos', '/categories', '/tags', '/terms', '/privacy', '/dmca', '/2257'] as $p) {
            $out[] = "<url><loc>{$host}{$p}</loc></url>";
        }
        $rows = App::$db->query("SELECT slug, published_at FROM videos ORDER BY id DESC LIMIT 5000")->fetchAll();
        foreach ($rows as $r) $out[] = "<url><loc>{$host}/video/".View::e($r['slug'])."</loc><lastmod>".date('c', strtotime($r['published_at']))."</lastmod></url>";
        foreach (App::$db->query("SELECT slug FROM categories")->fetchAll() as $r) $out[] = "<url><loc>{$host}/category/".View::e($r['slug'])."</loc></url>";
        foreach (App::$db->query("SELECT slug FROM tags")->fetchAll() as $r) $out[] = "<url><loc>{$host}/tag/".View::e($r['slug'])."</loc></url>";
        foreach (App::$db->query("SELECT slug, created_at FROM landings WHERE active=1")->fetchAll() as $r) $out[] = "<url><loc>{$host}/l/".View::e($r['slug'])."</loc><lastmod>".date('c', strtotime($r['created_at']))."</lastmod><changefreq>daily</changefreq><priority>0.7</priority></url>";
        $out[] = '</urlset>';
        echo implode("\n", $out);
    }

    public function categoriesIndex(): void {
        $cats = App::$db->query("SELECT * FROM categories WHERE video_count>0 ORDER BY name")->fetchAll();
        View::render('pages/categories', ['title'=>'All categories', 'cats'=>$cats]);
    }
    public function tagsIndex(): void {
        $tags = App::$db->query("SELECT * FROM tags WHERE video_count>0 ORDER BY name")->fetchAll();
        View::render('pages/tags', ['title'=>'All tags', 'tags'=>$tags]);
    }
    public function staticPage(string $slug): void {
        View::render('pages/legal/' . $slug, ['title' => ucfirst($slug)]);
    }
}
