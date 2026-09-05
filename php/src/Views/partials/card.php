<?php use App\Core\View; $v = $video; $dur = (int)$v['duration']; $mm = str_pad(intval($dur/60),2,'0',STR_PAD_LEFT); $ss = str_pad($dur%60,2,'0',STR_PAD_LEFT); ?>
<div class="card" data-testid="video-card">
  <a class="thumb" href="/video/<?= View::e($v['slug']) ?>">
    <img loading="lazy" src="<?= View::e($v['thumbnail'] ?: 'https://picsum.photos/seed/'.$v['id'].'/640/360') ?>" alt="<?= View::e($v['title']) ?>" <?php if(!empty($v['preview'])): ?>data-preview="<?= View::e($v['preview']) ?>"<?php endif; ?>>
    <span class="qual <?= strtolower($v['quality'])==='4k'?'uhd':'' ?>"><?= View::e($v['quality']) ?></span>
    <span class="dur"><?= $mm . ':' . $ss ?></span>
  </a>
  <div class="info">
    <a class="title" href="/video/<?= View::e($v['slug']) ?>" data-testid="video-title-link"><?= View::e($v['title']) ?></a>
    <div class="meta">
      <span><?= number_format((int)$v['views']) ?> views</span>
      <span class="rating">★ <?= number_format((float)$v['rating'],1) ?></span>
    </div>
  </div>
</div>
