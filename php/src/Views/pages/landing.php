<?php use App\Core\View; $l = $landing; $cats = json_decode($l['categories_json'] ?: '[]', true) ?: []; $tags = json_decode($l['tags_json'] ?: '[]', true) ?: []; $template = $l['template'] ?? 'grid'; ?>
<section class="hero landing-hero-<?= View::e($template) ?>" data-testid="landing-hero">
  <div class="badge-row" style="margin-bottom:12px">
    <span class="badge hot">CURATED · <?= strtoupper(View::e($template)) ?></span>
    <?php if (!empty($l['keyword'])): ?><span class="badge">Search intent: “<?= View::e($l['keyword']) ?>”</span><?php endif; ?>
    <span class="badge"><?= number_format((int)$total) ?> videos</span>
  </div>
  <h1><?= View::e($l['title']) ?></h1>
  <?php if (!empty($l['intro'])): ?><p style="max-width:820px;color:#c8cdd6"><?= nl2br(View::e($l['intro'])) ?></p><?php endif; ?>
  <?php if ($cats || $tags): ?>
  <div class="chips" style="margin-top:12px">
    <?php foreach ($cats as $slug): ?><a class="chip" href="/category/<?= View::e($slug) ?>"><?= View::e($slug) ?></a><?php endforeach; ?>
    <?php foreach ($tags as $slug): ?><a class="chip" href="/tag/<?= View::e($slug) ?>">#<?= View::e($slug) ?></a><?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<script type="application/ld+json">
{"@context":"https://schema.org","@type":"CollectionPage","name":"<?= View::e(str_replace('"','', $l['title'])) ?>","description":"<?= View::e(str_replace('"','', substr($l['intro'] ?? '',0,180))) ?>","url":"<?= View::e('/l/'.$l['slug']) ?>"}
</script>

<?php if (empty($items)): ?>
  <div class="doc"><p>This collection is still being populated. Come back soon — imports run every 6 hours.</p></div>

<?php elseif ($template === 'top10'): ?>
  <?php
    $top = array_slice($items, 0, 10);
    $host = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https') . '://' . ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
    $listItems = [];
    foreach ($top as $rank => $it) {
        $listItems[] = [
            '@type' => 'ListItem',
            'position' => $rank + 1,
            'url' => $host . '/video/' . $it['slug'],
            'item' => [
                '@type' => 'VideoObject',
                'name' => $it['title'],
                'thumbnailUrl' => $it['thumbnail'],
                'uploadDate' => date('c', strtotime($it['published_at'])),
                'duration' => 'PT' . (int)$it['duration'] . 'S',
                'contentUrl' => $host . '/video/' . $it['slug'],
            ],
        ];
    }
  ?>
  <script type="application/ld+json" data-testid="itemlist-schema"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $l['title'],
    'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
    'numberOfItems' => count($listItems),
    'itemListElement' => $listItems,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
  <ol class="top10-list" data-testid="landing-top10">
    <?php foreach ($top as $rank => $video): $rank++; ?>
      <li class="top10-item" data-rank="<?= $rank ?>">
        <span class="top10-rank">#<?= $rank ?></span>
        <div class="top10-body"><?php include __DIR__.'/../partials/card.php'; ?></div>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if (count($items) > 10): ?>
    <h3 style="margin-top:26px">More in this collection</h3>
    <div class="grid" data-testid="landing-grid">
      <?php foreach (array_slice($items, 10) as $video) include __DIR__.'/../partials/card.php'; ?>
    </div>
  <?php endif; ?>

<?php elseif ($template === 'editorial'): ?>
  <div class="editorial-lead" data-testid="landing-editorial">
    <?php $hero = $items[0]; ?>
    <div class="editorial-hero"><?php $video = $hero; include __DIR__.'/../partials/card.php'; ?></div>
    <div class="editorial-copy">
      <h2>Editor's pick</h2>
      <p class="muted"><?= View::e(mb_substr(strip_tags($l['intro'] ?? ''), 0, 320)) ?></p>
      <p><strong>Why we love it:</strong> handpicked scenes updated every 6 hours across <?= count($cats) ?> categories and <?= count($tags) ?> tags.</p>
    </div>
  </div>
  <?php if (count($items) > 1): ?>
    <h3 style="margin-top:26px">The rest of the story</h3>
    <div class="grid">
      <?php foreach (array_slice($items, 1) as $video) include __DIR__.'/../partials/card.php'; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="grid" data-testid="landing-grid">
    <?php foreach ($items as $video) include __DIR__.'/../partials/card.php'; ?>
  </div>
<?php endif; ?>

<?php if ($pages > 1):
  $base = '/l/' . $l['slug'] . '?';
  $start = max(1, $page - 3); $end = min($pages, $page + 3);
?>
<nav class="pager">
  <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $page<=1?'#':$base.'page='.($page-1) ?>">← Prev</a>
  <?php for ($i=$start; $i<=$end; $i++): ?><a class="<?= $i==$page?'cur':'' ?>" href="<?= $base.'page='.$i ?>"><?= $i ?></a><?php endfor; ?>
  <a class="<?= $page>=$pages?'disabled':'' ?>" href="<?= $page>=$pages?'#':$base.'page='.($page+1) ?>">Next →</a>
</nav>
<?php endif; ?>
