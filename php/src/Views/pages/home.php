<?php use App\Core\View; ?>
<?php $position='home_top'; include __DIR__.'/../partials/ad.php'; ?>
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

<?php if (!empty($taste_row)): ?>
<section class="section" data-testid="taste-row">
  <div class="section-head">
    <h2>Because you like <span style="color:var(--gold)"><?= View::e($taste_row['category']['name']) ?></span> <span class="badge" style="margin-left:8px;font-size:.65rem;background:rgba(255,176,32,.16);color:#ffd076;border-color:rgba(255,176,32,.4);vertical-align:middle">FOR YOU</span></h2>
    <a href="/category/<?= View::e($taste_row['category']['slug']) ?>">See all <?= (int)$taste_row['category']['video_count'] ?> →</a>
  </div>
  <div class="grid" data-testid="taste-grid">
    <?php foreach ($taste_row['items'] as $video) include __DIR__.'/../partials/card.php'; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($trending_landings)): ?>
<section class="section">
  <div class="section-head"><h2>Trending collections<?php if (!empty($top_category)): ?> <span class="badge hot" style="margin-left:8px;font-size:.65rem;vertical-align:middle" data-testid="taste-badge">TUNED TO YOUR TASTE</span><?php endif; ?></h2><a href="/admin" class="muted" style="font-size:.75rem">manage →</a></div>
  <div class="landing-tiles" data-testid="trending-landings">
    <?php foreach ($trending_landings as $tl):
      $ctr = ((int)$tl['imps'] >= 5 && (int)$tl['imps'] > 0) ? number_format(($tl['clks']*100)/$tl['imps'],1).'%' : null;
      $isForYou = !empty($top_category) && !empty($tl['boost']);
    ?>
    <a class="landing-tile<?= $isForYou ? ' for-you' : '' ?>" href="/l/<?= View::e($tl['slug']) ?>" data-testid="trending-tile-<?= (int)$tl['id'] ?>">
      <div class="landing-tile-bg" <?php if (!empty($tl['og_image'])): ?>style="background-image:url('<?= View::e($tl['og_image']) ?>')"<?php endif; ?>></div>
      <div class="landing-tile-content">
        <div style="display:flex;gap:6px;flex-wrap:wrap">
          <span class="badge hot"><?= strtoupper(View::e($tl['template'] ?? 'grid')) ?></span>
          <?php if ($isForYou): ?><span class="badge" style="background:rgba(255,176,32,.18);color:#ffd076;border-color:rgba(255,176,32,.4)" data-testid="for-you-<?= (int)$tl['id'] ?>">★ FOR YOU</span><?php endif; ?>
        </div>
        <h3><?= View::e($tl['title']) ?></h3>
        <?php if (!empty($tl['keyword'])): ?><div class="muted" style="font-size:.78rem">#<?= View::e($tl['keyword']) ?></div><?php endif; ?>
        <div class="landing-tile-meta">
          <span>👁 <?= number_format((int)$tl['views']) ?></span>
          <?php if ($ctr !== null): ?><span class="rating" style="color:var(--gold)">CTR <?= $ctr ?></span><?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php $position='home_middle'; include __DIR__.'/../partials/ad.php'; ?>

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
