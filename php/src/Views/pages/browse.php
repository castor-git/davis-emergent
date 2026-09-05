<?php use App\Core\View; $f = $filters; ?>
<h1><?= View::e($title) ?></h1>
<p class="muted" data-testid="results-count"><?= number_format((int)$total) ?> results</p>

<form class="filters" method="get" action="<?= View::e(strtok($_SERVER['REQUEST_URI'],'?')) ?>" data-testid="filters-form">
  <label>Search<input type="search" name="q" value="<?= View::e($f['q'] ?? '') ?>" placeholder="keywords"></label>
  <label>Quality
    <select name="quality" data-testid="filter-quality">
      <option value="">Any</option>
      <?php foreach (['HD','FullHD','4K'] as $q): ?>
        <option value="<?= $q ?>" <?= (($f['quality'] ?? '')===$q)?'selected':'' ?>><?= $q ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Min duration (min)<input type="number" min="0" name="min_duration" value="<?= View::e((int)($f['min_duration'] ?? 0) ?: '') ?>"></label>
  <label>Max duration (min)<input type="number" min="0" name="max_duration" value="<?= View::e((int)($f['max_duration'] ?? 0) ?: '') ?>"></label>
  <label>Source
    <select name="source" data-testid="filter-source">
      <option value="">Any</option>
      <?php foreach (\App\Core\App::$db->query("SELECT DISTINCT source FROM videos")->fetchAll() as $s): ?>
        <option value="<?= View::e($s['source']) ?>" <?= (($f['source'] ?? '')===$s['source'])?'selected':'' ?>><?= View::e($s['source']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Sort by
    <select name="sort" data-testid="filter-sort">
      <?php foreach (['popular'=>'Most viewed','newest'=>'Newest','rating'=>'Top rated','longest'=>'Longest','random'=>'Random'] as $k=>$lb): ?>
        <option value="<?= $k ?>" <?= (($f['sort'] ?? 'popular')===$k)?'selected':'' ?>><?= $lb ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="actions">
    <a href="<?= View::e(strtok($_SERVER['REQUEST_URI'],'?')) ?>" class="btn-ghost">Reset</a>
    <button class="btn-primary" data-testid="apply-filters">Apply filters</button>
  </div>
</form>

<?php if (!empty($items)): ?>
<div class="grid" data-testid="browse-grid">
  <?php foreach ($items as $video) include __DIR__.'/../partials/card.php'; ?>
</div>
<?php else: ?>
<div class="doc"><p>No videos match your filters. Try loosening the criteria or explore <a href="/categories">categories</a> and <a href="/tags">tags</a>.</p></div>
<?php endif; ?>

<?php if ($pages > 1):
  $qs = $_GET; unset($qs['page']);
  $base = strtok($_SERVER['REQUEST_URI'],'?') . '?' . http_build_query($qs) . ($qs?'&':'');
  $start = max(1, $page - 3); $end = min($pages, $page + 3);
?>
<nav class="pager" data-testid="pager">
  <a class="<?= $page<=1?'disabled':'' ?>" href="<?= $page<=1?'#':$base.'page='.($page-1) ?>">← Prev</a>
  <?php for ($i = $start; $i <= $end; $i++): ?>
    <a class="<?= $i==$page?'cur':'' ?>" href="<?= $base.'page='.$i ?>"><?= $i ?></a>
  <?php endfor; ?>
  <a class="<?= $page>=$pages?'disabled':'' ?>" href="<?= $page>=$pages?'#':$base.'page='.($page+1) ?>">Next →</a>
</nav>
<?php endif; ?>
