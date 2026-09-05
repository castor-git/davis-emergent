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
    <button class="menubtn" id="menubtn" aria-label="Menu">☰</button>
  </div>
</header>

<main class="wrap main" data-testid="main-content">
<?= $content ?>
</main>

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
