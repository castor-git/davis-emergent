<?php use App\Core\View; $v = $video; ?>
<article class="video-detail" data-testid="video-detail">
  <div>
    <?php $position='video_pre'; include __DIR__.'/../partials/ad.php'; ?>
    <div class="player" data-testid="video-player">
      <?php if (!empty($v['embed_url'])): ?>
        <iframe src="<?= View::e($v['embed_url']) ?>" allowfullscreen loading="lazy" referrerpolicy="no-referrer"></iframe>
      <?php else: ?>
        <div class="placeholder">
          <div>
            <p style="font-size:1.2rem;color:#c8cdd6;margin:0 0 10px">▶ Demo preview</p>
            <p style="font-size:.85rem">This entry has no external embed. Connect a real source in the admin to display the player.</p>
            <img src="<?= View::e($v['thumbnail']) ?>" alt="preview" style="max-height:180px;margin:14px auto 0;border-radius:8px">
          </div>
        </div>
      <?php endif; ?>
    </div>
    <h1><?= View::e($v['title']) ?></h1>
    <div class="stats" data-testid="video-stats">
      <span>👁 <?= number_format((int)$v['views']) ?> views</span>
      <span>★ <?= number_format((float)$v['rating'],1) ?></span>
      <span><?= View::e($v['quality']) ?></span>
      <span><?= gmdate((int)$v['duration']>=3600?'H:i:s':'i:s', (int)$v['duration']) ?></span>
      <span>Source: <em><?= View::e($v['source']) ?></em></span>
    </div>
    <div class="description">
      <p><?= nl2br(View::e($v['description'])) ?></p>
    </div>
    <script type="application/ld+json">
    {"@context":"https://schema.org","@type":"VideoObject",
      "name":"<?= View::e(str_replace('"','', $v['title'])) ?>",
      "description":"<?= View::e(str_replace('"','', $v['description'])) ?>",
      "thumbnailUrl":"<?= View::e($v['thumbnail']) ?>",
      "uploadDate":"<?= date('c', strtotime($v['published_at'])) ?>",
      "duration":"PT<?= (int)$v['duration'] ?>S"}
    </script>
  </div>
  <aside data-testid="video-sidebar">
    <?php $position='video_sidebar'; include __DIR__.'/../partials/ad.php'; ?>
    <div class="taxo">
      <h3>Categories</h3>
      <div class="chips">
        <?php foreach ($cats as $c): ?><a class="chip" href="/category/<?= View::e($c['slug']) ?>"><?= View::e($c['name']) ?></a><?php endforeach; ?>
      </div>
    </div>
    <div class="taxo">
      <h3>Tags</h3>
      <div class="chips">
        <?php foreach ($tags_list as $t): ?><a class="chip" href="/tag/<?= View::e($t['slug']) ?>">#<?= View::e($t['name']) ?></a><?php endforeach; ?>
      </div>
    </div>
    <?php if (!empty($related)): ?>
    <h3>Related</h3>
    <?php foreach (array_slice($related, 0, 4) as $video): ?>
      <div style="margin-bottom:10px"><?php include __DIR__.'/../partials/card.php'; ?></div>
    <?php endforeach; ?>
    <?php endif; ?>
  </aside>
</article>

<?php if (!empty($related) && count($related) > 4): ?>
<section class="section">
  <div class="section-head"><h2>You may also like</h2></div>
  <div class="grid">
    <?php foreach (array_slice($related, 4) as $video) include __DIR__.'/../partials/card.php'; ?>
  </div>
</section>
<?php endif; ?>
