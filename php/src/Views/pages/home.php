<?php use App\Core\View; ?>
<section class="hero" data-testid="hero">
  <div class="badge-row" style="margin-bottom:14px">
    <span class="badge hot">● LIVE NETWORK</span>
    <span class="badge">Updated daily</span>
    <span class="badge">HD · FullHD · 4K</span>
  </div>
  <h1>Premium HD adult tube — the finest network.</h1>
  <p>Aggregated feeds from top studios and networks, curated in one dark, fast and mobile-first destination.</p>
  <a href="/videos?sort=popular" class="btn-primary" data-testid="hero-explore">Explore popular</a>
</section>

<section class="section">
  <div class="section-head"><h2>Featured</h2><a href="/videos?featured=1">See all →</a></div>
  <div class="grid">
    <?php foreach ($featured as $video) include __DIR__.'/../partials/card.php'; ?>
  </div>
</section>

<section class="section">
  <div class="section-head"><h2>Popular categories</h2><a href="/categories">All categories →</a></div>
  <div class="chips" data-testid="cat-chips">
    <?php foreach ($categories as $c): ?>
      <a class="chip" href="/category/<?= View::e($c['slug']) ?>" data-testid="chip-cat-<?= View::e($c['slug']) ?>"><?= View::e($c['name']) ?> <span class="count"><?= (int)$c['video_count'] ?></span></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <div class="section-head"><h2>Most viewed</h2><a href="/videos?sort=popular">More →</a></div>
  <div class="grid">
    <?php foreach ($popular as $video) include __DIR__.'/../partials/card.php'; ?>
  </div>
</section>

<section class="section">
  <div class="section-head"><h2>Newest</h2><a href="/videos?sort=newest">More →</a></div>
  <div class="grid">
    <?php foreach ($newest as $video) include __DIR__.'/../partials/card.php'; ?>
  </div>
</section>

<section class="section">
  <div class="section-head"><h2>Trending tags</h2><a href="/tags">All tags →</a></div>
  <div class="chips">
    <?php foreach ($tags as $t): ?>
      <a class="chip" href="/tag/<?= View::e($t['slug']) ?>">#<?= View::e($t['name']) ?> <span class="count"><?= (int)$t['video_count'] ?></span></a>
    <?php endforeach; ?>
  </div>
</section>
