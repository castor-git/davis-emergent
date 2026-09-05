<?php use App\Core\View; ?>
<?php if (!empty($_GET['msg'])): ?><div class="flash" data-testid="admin-flash"><?= View::e($_GET['msg']) ?></div><?php endif; ?>
<h1>Dashboard</h1>
<div class="stat-grid">
  <div class="stat"><div class="k">Videos</div><div class="v"><?= $counts['videos'] ?></div></div>
  <div class="stat"><div class="k">Categories</div><div class="v"><?= $counts['categories'] ?></div></div>
  <div class="stat"><div class="k">Tags</div><div class="v"><?= $counts['tags'] ?></div></div>
  <div class="stat"><div class="k">Active sources</div><div class="v"><?= $counts['sources'] ?></div></div>
</div>

<h2>Sources</h2>
<table class="data" data-testid="sources-table">
  <thead><tr><th>Slug</th><th>Label</th><th>Status</th><th>Last import</th><th>Message</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($sources as $s): ?>
    <tr>
      <td><code><?= View::e($s['slug']) ?></code></td>
      <td><?= View::e($s['label']) ?></td>
      <td><span class="pill <?= $s['enabled']?'on':'off' ?>"><?= $s['enabled']?'ENABLED':'DISABLED' ?></span></td>
      <td><?= View::e($s['last_import_at'] ?? '—') ?></td>
      <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= View::e($s['last_status'] ?? '—') ?></td>
      <td style="text-align:right;white-space:nowrap">
        <form method="post" action="/admin/source/toggle" style="display:inline"><input type="hidden" name="slug" value="<?= View::e($s['slug']) ?>"><button class="btn-ghost" data-testid="toggle-<?= View::e($s['slug']) ?>"><?= $s['enabled']?'Disable':'Enable' ?></button></form>
        <form method="post" action="/admin/source/import" style="display:inline"><input type="hidden" name="slug" value="<?= View::e($s['slug']) ?>"><button class="btn-primary" data-testid="import-<?= View::e($s['slug']) ?>">Import</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 style="margin-top:24px">Diagnostics</h2>
<form method="post" action="/admin/cache/clear" style="display:inline"><button class="btn-primary" data-testid="clear-cache">Clear cache</button></form>
