<?php
use App\Core\View;

$v = $video;
$dur = (int)$v['duration'];
$mm = str_pad((string)intval($dur / 60), 2, '0', STR_PAD_LEFT);
$ss = str_pad((string)($dur % 60), 2, '0', STR_PAD_LEFT);
?>
<div class="card" data-testid="video-card-<?= (int)$v['id'] ?>">
  <a class="thumb" href="/video/<?= View::e($v['slug']) ?>">
    <img
      loading="lazy"
      src="<?= View::e($v['thumbnail'] ?: 'https://picsum.photos/seed/' . $v['id'] . '/640/360') ?>"
      alt="<?= View::e($v['title']) ?>"
      <?php if (!empty($v['preview'])): ?>
        data-preview="<?= View::e($v['preview']) ?>"
      <?php endif; ?>
    >
    <span class="qual <?= strtolower($v['quality']) === '4k' ? 'uhd' : '' ?>">
      <?= View::e($v['quality']) ?>
    </span>
    <span class="dur"><?= $mm . ':' . $ss ?></span>
  </a>
  <div class="info">
    <a
      class="title"
      href="/video/<?= View::e($v['slug']) ?>"
      data-testid="video-title-link-<?= (int)$v['id'] ?>"
    >
      <?= View::e($v['title']) ?>
    </a>
    <div class="meta">
      <span class="rating">★ <?= number_format((float)$v['rating'], 1) ?></span>
      <span data-testid="video-views-<?= (int)$v['id'] ?>">
        <?= number_format((int)$v['views']) ?> views
      </span>
    </div>
    <?php if (!empty($v['actors'])): ?>
      <div class="credit-row" data-testid="video-actors-<?= (int)$v['id'] ?>">
        <span>Aktorzy:</span>
        <?php foreach ($v['actors'] as $actor): ?>
          <a
            href="/actor/<?= View::e($actor['slug']) ?>"
            data-testid="video-actor-<?= (int)$v['id'] ?>-<?= View::e($actor['slug']) ?>"
          >
            <?= View::e($actor['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($v['studios'])): ?>
      <div class="credit-row" data-testid="video-studios-<?= (int)$v['id'] ?>">
        <span>Wytwórnia:</span>
        <?php foreach ($v['studios'] as $studio): ?>
          <a
            href="/studio/<?= View::e($studio['slug']) ?>"
            data-testid="video-studio-<?= (int)$v['id'] ?>-<?= View::e($studio['slug']) ?>"
          >
            <?= View::e($studio['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($v['card_tags'])): ?>
      <div class="card-tags" data-testid="video-tags-<?= (int)$v['id'] ?>">
        <?php foreach ($v['card_tags'] as $tag): ?>
          <a
            href="/tag/<?= View::e($tag['slug']) ?>"
            data-testid="video-tag-<?= (int)$v['id'] ?>-<?= View::e($tag['slug']) ?>"
          >
            #<?= View::e($tag['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
