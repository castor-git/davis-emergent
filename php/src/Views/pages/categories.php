<?php use App\Core\View; ?>
<h1>All categories</h1>
<div class="chips" style="margin-top:20px">
  <?php foreach ($cats as $c): ?><a class="chip" href="/category/<?= View::e($c['slug']) ?>"><?= View::e($c['name']) ?> <span class="count"><?= (int)$c['video_count'] ?></span></a><?php endforeach; ?>
</div>
