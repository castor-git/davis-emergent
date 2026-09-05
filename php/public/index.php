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
$router->get('/l/{slug}', fn($p) => (new \App\Controllers\LandingController())->show($p));
$router->post('/api/ab/click', fn() => (new \App\Controllers\LandingController())->abClick());
$router->get('/robots.txt', fn() => (new SeoController())->robots());
$router->get('/sitemap.xml', fn() => (new SeoController())->sitemap());
$router->get('/terms', fn() => (new SeoController())->staticPage('terms'));
$router->get('/privacy', fn() => (new SeoController())->staticPage('privacy'));
$router->get('/dmca', fn() => (new SeoController())->staticPage('dmca'));
$router->get('/2257', fn() => (new SeoController())->staticPage('c2257'));

$router->get('/admin', fn() => (new AdminController())->dashboard());
$router->post('/admin/source/toggle', fn() => (new AdminController())->toggleSource());
$router->post('/admin/source/import', fn() => (new AdminController())->importSource());
$router->post('/admin/source/config', fn() => (new AdminController())->saveSourceConfig());
$router->post('/admin/ads/save', fn() => (new AdminController())->saveAd());
$router->post('/admin/ads/delete', fn() => (new AdminController())->deleteAd());
$router->get('/admin/landings/new', fn() => (new AdminController())->landingForm());
$router->get('/admin/landings/edit', fn() => (new AdminController())->landingForm());
$router->post('/admin/landings/save', fn() => (new AdminController())->saveLanding());
$router->post('/admin/landings/delete', fn() => (new AdminController())->deleteLanding());
$router->post('/admin/landings/bulk', fn() => (new AdminController())->bulkLanding());
$router->post('/admin/cache/clear', fn() => (new AdminController())->clearCache());
$router->get('/admin/digest', fn() => (new AdminController())->digest());

// Cron webhook (called by the Emergent platform scheduler)
$router->post('/api/cron/nightly-import', fn() => (new \App\Controllers\CronController())->nightlyImport());
$router->post('/api/cron/daily-suggest', fn() => (new \App\Controllers\CronController())->dailySuggest());
$router->post('/api/cron/weekly-digest', fn() => (new \App\Controllers\CronController())->weeklyDigest());

// IndexNow key verification file — must be reachable at /{key}.txt
$router->get('/{key}.txt', function($p) {
    $key = \App\Support\PingService::indexNowKey();
    if ($p['key'] !== $key) { http_response_code(404); echo 'not found'; return; }
    header('Content-Type: text/plain');
    echo $key;
});

// Health for FastAPI proxy
$router->get('/api/health', fn() => View::json(['ok'=>true,'app'=>'davisporn-php']));
$router->post('/api/prefs/pin', fn() => (new \App\Controllers\PrefsController())->pin());
$router->post('/api/prefs/hide', fn() => (new \App\Controllers\PrefsController())->hide());
$router->get('/api/prefs', fn() => (new \App\Controllers\PrefsController())->state());

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $uri);
} catch (\Throwable $e) {
    http_response_code(500);
    if (getenv('APP_DEBUG')) { echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>'; }
    else { View::render('pages/500', ['title'=>'Server error']); }
}
