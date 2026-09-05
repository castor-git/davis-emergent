<?php
use App\Models\Ad;
use App\Core\View;
$position = $position ?? '';
$ads = Ad::forPosition($position, 1);
if (!$ads) return;
foreach ($ads as $ad):
?>
<div class="ad-slot" data-testid="ad-<?= View::e($position) ?>" data-position="<?= View::e($position) ?>">
  <span class="ad-tag">SPONSORED</span>
  <?php if ($ad['kind'] === 'snippet' && !empty($ad['snippet_html'])): ?>
    <div class="ad-snippet"><?= $ad['snippet_html'] /* trusted admin HTML */ ?></div>
  <?php else: ?>
    <a href="<?= View::e($ad['link_url'] ?: '#') ?>" target="_blank" rel="noopener sponsored" class="ad-banner">
      <?php if (!empty($ad['image_url'])): ?>
        <img src="<?= View::e($ad['image_url']) ?>" alt="<?= View::e($ad['title'] ?? 'Sponsored') ?>" loading="lazy">
      <?php endif; ?>
      <?php if (!empty($ad['title'])): ?><span class="ad-title"><?= View::e($ad['title']) ?></span><?php endif; ?>
    </a>
  <?php endif; ?>
</div>
<?php endforeach; ?>
