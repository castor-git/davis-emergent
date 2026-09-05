<?php
require __DIR__ . '/../src/autoload.php';

use App\Core\{App, Router, View};
use App\Controllers\{HomeController, BrowseController, AdminController, SeoController};

App::boot();

// Serve static assets directly if requested via router.php (dev server fallback)
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
if (preg_match('/\.(css|js|png|jpg|jpeg|svg|ico|webp|gif)$/i', $path)) {
    $file = __DIR__ . $path;
    if (is_file($file)) return false; // let PHP built-in server serve it
}

$router = new Router();

$router->get('/', fn() => (new HomeController())->index());
$router->get('/videos', fn() => (new BrowseController())->videos());
$router->get('/search', fn() => (new BrowseController())->search());
$router->get('/api/suggest', fn() => (new BrowseController())->suggest());
$router->get('/video/{slug}', fn($p) => (new BrowseController())->video($p));
$router->get('/category/{slug}', fn($p) => (new BrowseController())->category($p));
$router->get('/tag/{slug}', fn($p) => (new BrowseController())->tag($p));

$router->get('/categories', fn() => (new SeoController())->categoriesIndex());
$router->get('/tags', fn() => (new SeoController())->tagsIndex());
$router->get('/robots.txt', fn() => (new SeoController())->robots());
$router->get('/sitemap.xml', fn() => (new SeoController())->sitemap());
$router->get('/terms', fn() => (new SeoController())->staticPage('terms'));
$router->get('/privacy', fn() => (new SeoController())->staticPage('privacy'));
$router->get('/dmca', fn() => (new SeoController())->staticPage('dmca'));
$router->get('/2257', fn() => (new SeoController())->staticPage('c2257'));

$router->get('/admin', fn() => (new AdminController())->dashboard());
$router->post('/admin/source/toggle', fn() => (new AdminController())->toggleSource());
$router->post('/admin/source/import', fn() => (new AdminController())->importSource());
$router->post('/admin/cache/clear', fn() => (new AdminController())->clearCache());

// Health for FastAPI proxy
$router->get('/api/health', fn() => View::json(['ok'=>true,'app'=>'davisporn-php']));

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $uri);
} catch (\Throwable $e) {
    http_response_code(500);
    if (getenv('APP_DEBUG')) { echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>'; }
    else { View::render('pages/500', ['title'=>'Server error']); }
}
