<?php use App\Core\View; ?>
<h1>All tags</h1>
<div class="chips" style="margin-top:20px">
  <?php foreach ($tags as $t): ?><a class="chip" href="/tag/<?= View::e($t['slug']) ?>">#<?= View::e($t['name']) ?> <span class="count"><?= (int)$t['video_count'] ?></span></a><?php endforeach; ?>
</div>
