<?php use App\Core\View; ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= View::e($title ?? 'DAVISPORN') ?></title>
<meta name="description" content="<?= View::e($meta_description ?? 'DAVISPORN — premium HD adult video network. Trending clips, thousands of scenes, updated daily.') ?>">
<meta name="rating" content="adult">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= View::e('https://' . ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
<?= $head_extra ?? '' ?>
<link rel="icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%23e10600'/><text x='16' y='22' text-anchor='middle' font-size='18' fill='%23fff' font-family='sans-serif' font-weight='900'>D</text></svg>">
<link rel="stylesheet" href="/assets/app.css">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite","name":"DAVISPORN","url":"https://<?= View::e($_SERVER['HTTP_HOST'] ?? '') ?>","potentialAction":{"@type":"SearchAction","target":"/search?q={q}","query-input":"required name=q"}}</script>
</head>
<body>

<div id="age-gate" hidden>
  <div class="age-card">
    <div class="brand-lg">DAVIS<span>PORN</span></div>
    <h2>Age verification</h2>
    <p>This site contains sexually explicit material. You must be at least 18 years old (or the age of majority in your jurisdiction) to enter.</p>
    <div class="age-actions">
      <button data-testid="age-confirm" id="age-confirm" class="btn-primary">I am 18 or older — Enter</button>
      <a href="https://www.google.com" class="btn-ghost" data-testid="age-leave">I am under 18 — Leave</a>
    </div>
    <p class="fine">By entering you agree to our <a href="/terms">Terms</a>, <a href="/privacy">Privacy</a>, <a href="/2257">2257 statement</a> and <a href="/dmca">DMCA policy</a>.</p>
  </div>
</div>

<header class="topbar" data-testid="topbar">
  <div class="wrap">
    <a href="/" class="brand" data-testid="nav-brand">DAVIS<span>PORN</span></a>
    <nav class="mainnav">
      <a href="/videos?sort=newest" data-testid="nav-new">New</a>
      <a href="/videos?sort=popular" data-testid="nav-popular">Popular</a>
      <a href="/videos?sort=rating" data-testid="nav-top">Top Rated</a>
      <a href="/categories" data-testid="nav-categories">Categories</a>
      <a href="/tags" data-testid="nav-tags">Tags</a>
    </nav>
    <form class="searchbox" action="/search" method="get" role="search">
      <input type="search" name="q" id="searchInput" placeholder="Search videos, categories, tags..." autocomplete="off" data-testid="search-input" value="<?= View::e($_GET['q'] ?? '') ?>">
      <button type="submit" data-testid="search-submit" aria-label="Search">⌕</button>
      <ul id="suggestions" class="suggest" hidden></ul>
    </form>
    <button class="prefbtn" id="prefbtn" data-testid="open-prefs" aria-label="Preferences" title="Your feed preferences">★</button>
    <button class="menubtn" id="menubtn" aria-label="Menu">☰</button>
  </div>
</header>

<main class="wrap main" data-testid="main-content">
<?= $content ?>
</main>

<aside id="pref-drawer" class="pref-drawer" hidden data-testid="pref-drawer">
  <div class="pref-head">
    <h3>Your feed preferences</h3>
    <button class="pref-close" id="pref-close" aria-label="Close" data-testid="close-prefs">×</button>
  </div>
  <p class="muted">Pin what you love, hide what you don't. Everything applies instantly and follows you across visits.</p>

  <div class="pref-tabs" role="tablist">
    <button class="pref-tab active" data-tab="category" data-testid="pref-tab-category">Categories</button>
    <button class="pref-tab" data-tab="tag" data-testid="pref-tab-tag">Tags</button>
  </div>

  <div class="pref-tabpanel" data-tab-panel="category">
    <div class="pref-section">
      <h4>📌 Pinned <span class="muted" id="pref-pins-count">0</span></h4>
      <div id="pref-pins-list" class="pref-list" data-testid="pref-pins-list"><span class="muted" style="font-size:.85rem">Nothing pinned yet.</span></div>
    </div>
    <div class="pref-section">
      <h4>✕ Hidden <span class="muted" id="pref-hides-count">0</span></h4>
      <div id="pref-hides-list" class="pref-list" data-testid="pref-hides-list"><span class="muted" style="font-size:.85rem">Nothing hidden.</span></div>
    </div>
    <div class="pref-section">
      <h4>Add category</h4>
      <input type="search" id="pref-search" placeholder="Type to search categories…" data-testid="pref-search" style="width:100%;background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px;font-family:inherit">
      <div id="pref-picker" class="pref-picker" data-testid="pref-picker"></div>
    </div>
  </div>

  <div class="pref-tabpanel" data-tab-panel="tag" hidden>
    <div class="pref-section">
      <h4>📌 Pinned <span class="muted" id="pref-tag-pins-count">0</span></h4>
      <div id="pref-tag-pins-list" class="pref-list" data-testid="pref-tag-pins-list"><span class="muted" style="font-size:.85rem">Nothing pinned yet.</span></div>
    </div>
    <div class="pref-section">
      <h4>✕ Hidden <span class="muted" id="pref-tag-hides-count">0</span></h4>
      <div id="pref-tag-hides-list" class="pref-list" data-testid="pref-tag-hides-list"><span class="muted" style="font-size:.85rem">Nothing hidden.</span></div>
    </div>
    <div class="pref-section">
      <h4>Add tag</h4>
      <input type="search" id="pref-tag-search" placeholder="Type to search tags…" data-testid="pref-tag-search" style="width:100%;background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px;font-family:inherit">
      <div id="pref-tag-picker" class="pref-picker" data-testid="pref-tag-picker"></div>
    </div>
  </div>

  <div class="pref-actions">
    <button class="btn-ghost" id="pref-clear-hides" data-testid="pref-clear-hides">Clear hidden</button>
    <button class="btn-primary" id="pref-apply" data-testid="pref-apply">Apply &amp; reload</button>
  </div>
</aside>
<div id="pref-backdrop" class="pref-backdrop" hidden></div>

<footer class="footer">
  <div class="wrap">
    <div class="foot-cols">
      <div>
        <div class="brand" style="font-size:1.4rem">DAVIS<span>PORN</span></div>
        <p class="muted">Adults only. 18 U.S.C. § 2257 compliant. All models were 18+ at the time of photography.</p>
      </div>
      <div>
        <h4>Explore</h4>
        <a href="/videos">All videos</a><a href="/categories">Categories</a><a href="/tags">Tags</a>
      </div>
      <div>
        <h4>Legal</h4>
        <a href="/terms" data-testid="foot-terms">Terms</a><a href="/privacy" data-testid="foot-privacy">Privacy</a><a href="/dmca" data-testid="foot-dmca">DMCA</a><a href="/2257" data-testid="foot-2257">2257</a>
      </div>
      <div>
        <h4>Site</h4>
        <a href="/sitemap.xml">Sitemap</a><a href="/robots.txt">robots.txt</a><a href="/admin">Admin</a>
      </div>
    </div>
    <p class="copy">© <?= date('Y') ?> DAVISPORN. Aggregator only — content is embedded from third parties.</p>
  </div>
</footer>

<script src="/assets/app.js"></script>
</body>
</html>
