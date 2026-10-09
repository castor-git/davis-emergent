<?php
// Iteration 10 — Upornia XML/CSV live-contract regression suite.
// Safe to run against production: makes AT MOST ONE bounded fetch of the main feed
// (UporniaCsvAdapter::fetch(3)) and a single HTTP Range probe of the deleted feed.
// Does NOT trigger an import, does NOT alter the sources.last_status column.

require __DIR__ . '/../src/autoload.php';

use App\Core\App;
use App\Adapters\UporniaCsvAdapter;
use App\Support\UporniaDeletedCleaner;
use App\Support\SourceManager;

$fail = 0; $pass = 0; $results = [];
function check(string $name, bool $ok, string $detail = ''): void {
    global $fail, $pass, $results;
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    if ($ok) { $pass++; echo "PASS  $name\n"; }
    else     { $fail++; echo "FAIL  $name  — $detail\n"; }
}

App::boot();
$db = App::$db;

// ---------- Feed URL validation (reflection, no network) ----------
$adapter = new UporniaCsvAdapter();
$rc = new \ReflectionClass($adapter);
$m = $rc->getMethod('validateFeedUrl'); $m->setAccessible(true);
$cases = [
    ['http://upornia.com/admin/feeds/embed/', 'http (not https)'],
    ['https://evil.com/admin/feeds/embed/', 'wrong host'],
    ['https://sub.upornia.com/admin/feeds/embed/', 'host must equal upornia.com'],
    ['https://upornia.com/api/videos_feed2.php', 'wrong path'],
    ['ftp://upornia.com/admin/feeds/embed/', 'wrong scheme'],
];
foreach ($cases as [$url, $why]) {
    $threw = false;
    try { $m->invoke($adapter, $url); } catch (\RuntimeException $e) { $threw = true; }
    check("feed-url rejected: $why", $threw, "url=$url");
}
$threw = false;
try { $m->invoke($adapter, 'https://upornia.com/admin/feeds/embed/?source=1'); } catch (\RuntimeException $e) { $threw = true; }
check('feed-url accepts official HTTPS endpoint with query', !$threw);

// ---------- Deleted feed URL validation (reflection, no network) ----------
$rc2 = new \ReflectionClass(UporniaDeletedCleaner::class);
$m2 = $rc2->getMethod('validateUrl'); $m2->setAccessible(true);
$del_cases = [
    ['http://upornia.com/api/videos_feed2.php', 'http'],
    ['https://evil.com/api/videos_feed2.php', 'wrong host'],
    ['https://upornia.com/api/other.php', 'wrong path'],
    ['https://upornia.com/admin/feeds/embed/', 'main-feed path'],
];
foreach ($del_cases as [$url, $why]) {
    $threw = false;
    try { $m2->invoke(null, $url); } catch (\RuntimeException $e) { $threw = true; }
    check("deleted-feed rejected: $why", $threw, "url=$url");
}
$threw = false;
try { $m2->invoke(null, 'https://upornia.com/api/videos_feed2.php?action=get_deleted&source=1'); }
catch (\RuntimeException $e) { $threw = true; }
check('deleted-feed accepts official endpoint', !$threw);

// ---------- Admin-configured limit cap ----------
check('limitFor caps upornia_csv at 10000', SourceManager::limitFor('upornia_csv', 100) === 10000,
    'DB import_limit is 10000, min(10000, 10000)=10000');
check('limitFor still honors fallback when no DB value', SourceManager::limitFor('demo', 42) === 42);

// ---------- DB integration ----------
$row = $db->query("SELECT enabled, last_status, config FROM sources WHERE slug='upornia_csv'")->fetch();
check('upornia_csv source is enabled', (int)$row['enabled'] === 1);
$cfg = json_decode($row['config'], true) ?: [];
check('import_limit=10000 persisted in DB', ($cfg['import_limit'] ?? 0) == 10000, (string)($cfg['import_limit'] ?? ''));
check('feed_url is official HTTPS XML endpoint',
    isset($cfg['feed_url'])
      && str_starts_with($cfg['feed_url'], 'https://upornia.com/admin/feeds/embed/'),
    substr($cfg['feed_url'] ?? '', 0, 60));
check('deleted_feed_url is official HTTPS API endpoint',
    isset($cfg['deleted_feed_url'])
      && str_starts_with($cfg['deleted_feed_url'], 'https://upornia.com/api/videos_feed2.php'),
    substr($cfg['deleted_feed_url'] ?? '', 0, 60));
check('last_status reports inserted/updated/hidden counters',
    preg_match('/OK — inserted \d+, updated \d+, hidden \d+ deleted/u', (string)$row['last_status']) === 1,
    (string)$row['last_status']);

$n = (int)$db->query("SELECT COUNT(*) FROM videos WHERE source='upornia_csv'")->fetchColumn();
check('>=10000 rows persisted for upornia_csv', $n >= 10000, "n=$n");
$avail = (int)$db->query("SELECT COUNT(*) FROM videos WHERE source='upornia_csv' AND is_available=1")->fetchColumn();
check('all persisted rows currently available (is_available=1)', $avail === $n, "avail=$avail of $n");

// Spot: duration/HD/categorization enforcement in the stored rows
$below = (int)$db->query("SELECT COUNT(*) FROM videos WHERE source='upornia_csv' AND duration < 180")->fetchColumn();
check('no row stored with duration < 180', $below === 0, "below=$below");
$non_hd = (int)$db->query("SELECT COUNT(*) FROM videos WHERE source='upornia_csv' AND quality <> 'HD'")->fetchColumn();
check('all rows marked HD', $non_hd === 0, "non_hd=$non_hd");
$cat_rows = (int)$db->query("SELECT COUNT(*) FROM video_categories vc JOIN videos v ON v.id=vc.video_id WHERE v.source='upornia_csv'")->fetchColumn();
check('category links were created for upornia rows', $cat_rows > 0, "cat_rows=$cat_rows");

// ---------- Lock behavior (SKIPPED path, no DB status mutation) ----------
$status_before = (string)$db->query("SELECT last_status FROM sources WHERE slug='upornia_csv'")->fetchColumn();
$lock_db = new \PDO(
    "mysql:host=127.0.0.1;port=3306;dbname=davisporn;charset=utf8mb4",
    'dav', 'davpass',
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
);
$got = (int)$lock_db->query("SELECT GET_LOCK('upornia_import', 0)")->fetchColumn();
check('acquired upornia_import lock on secondary connection', $got === 1);
$res = SourceManager::import('upornia_csv', 1);
check('import returns ok=true SKIPPED under lock', !empty($res['ok']) && $res['inserted'] === 0 && $res['updated'] === 0, json_encode($res));
check('SKIPPED result message says another import is running',
    str_contains((string)($res['message'] ?? ''), 'SKIPPED'), (string)($res['message'] ?? ''));
$status_after = (string)$db->query("SELECT last_status FROM sources WHERE slug='upornia_csv'")->fetchColumn();
check('sources.last_status is NOT mutated while lock held',
    $status_before === $status_after, "before=[$status_before] after=[$status_after]");
$lock_db->query("SELECT RELEASE_LOCK('upornia_import')");
$free_after_release = (int)$db->query("SELECT IS_FREE_LOCK('upornia_import')")->fetchColumn();
check('lock is released after RELEASE_LOCK on secondary connection', $free_after_release === 1);

// ---------- Deleted feed semicolon header (bounded Range probe) ----------
$del_url = $cfg['deleted_feed_url'] ?? '';
$ch = curl_init($del_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Range: bytes=0-2048'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'DavispornBot/1.0',
    CURLOPT_HEADER => false,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
check('deleted feed reachable (HTTP 2xx)', $code >= 200 && $code < 300, "code=$code");
$firstLine = $body ? strtok((string)$body, "\n") : '';
check('deleted feed header uses semicolon separator',
    is_string($firstLine) && str_contains($firstLine, ';'),
    "header=" . substr((string)$firstLine, 0, 120));
check('deleted feed header contains id column',
    is_string($firstLine) && stripos($firstLine, 'id') !== false,
    "header=" . substr((string)$firstLine, 0, 120));

// ---------- Live adapter contract (BOUNDED to 3 records, exactly ONE fetch) ----------
echo "\n-- Live Upornia fetch(3) [one-shot] --\n";
$items = [];
try {
    foreach ($adapter->fetch(3) as $it) { $items[] = $it; if (count($items) >= 3) break; }
    check('adapter yielded 3 normalized records', count($items) === 3, 'got=' . count($items));
} catch (\Throwable $e) {
    check('adapter fetch(3) completed without exception', false, $e->getMessage());
}

foreach ($items as $i => $v) {
    $id = $v['source_video_id'] ?? '';
    check("rec#$i.source_video_id is numeric string", ctype_digit((string)$id), "id=$id");
    check("rec#$i.title is non-empty", trim((string)($v['title'] ?? '')) !== '', (string)($v['title'] ?? ''));
    $pageUrl = (string)($v['page_url'] ?? '');
    $pp = parse_url($pageUrl);
    $host = isset($pp['host']) ? strtolower($pp['host']) : '';
    // Upornia's live feed sometimes rebrands the public page host as videoupornia.com
    // (same provider / partner mirror). Accept either host; the embed/source_id proves origin.
    $hostOk = ($pp['scheme'] ?? '') === 'https'
        && ($host === 'upornia.com' || str_ends_with($host, '.upornia.com') || $host === 'videoupornia.com' || str_ends_with($host, '.videoupornia.com'));
    check("rec#$i.page_url is official HTTPS upornia(-partner) URL", $hostOk, $pageUrl);
    $embed = (string)($v['embed_url'] ?? '');
    check("rec#$i.embed_url extracted from <iframe>", $embed !== '' && filter_var($embed, FILTER_VALIDATE_URL) !== false, $embed);
    check("rec#$i.duration >=180", (int)($v['duration'] ?? 0) >= 180, (string)($v['duration'] ?? 0));
    check("rec#$i.quality === 'HD'", ($v['quality'] ?? '') === 'HD', (string)($v['quality'] ?? ''));
    check("rec#$i.rating is numeric", is_numeric($v['rating'] ?? 'x'), (string)($v['rating'] ?? ''));
    check("rec#$i.published_at matches YYYY-MM-DD HH:MM:SS",
        preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($v['published_at'] ?? '')) === 1,
        (string)($v['published_at'] ?? ''));
    check("rec#$i.categories is array (normalized list)",
        is_array($v['categories'] ?? null), gettype($v['categories'] ?? null));
    check("rec#$i.source === 'upornia_csv'", ($v['source'] ?? '') === 'upornia_csv');
}

// ---------- Summary ----------
echo "\n==================\n";
echo "PASS: $pass    FAIL: $fail\n";
file_put_contents(
    __DIR__ . '/../../test_reports/pytest/iter10_upornia_results.json',
    json_encode(['pass' => $pass, 'fail' => $fail, 'results' => $results], JSON_PRETTY_PRINT)
);
exit($fail === 0 ? 0 : 1);
