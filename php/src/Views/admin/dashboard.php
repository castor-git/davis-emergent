<?php use App\Core\View; ?>
<?php if (!empty($_GET['msg'])): ?><div class="flash" data-testid="admin-flash"><?= View::e($_GET['msg']) ?></div><?php endif; ?>
<h1>Dashboard</h1>
<div class="stat-grid">
  <div class="stat"><div class="k">Videos</div><div class="v"><?= $counts['videos'] ?></div></div>
  <div class="stat"><div class="k">Categories</div><div class="v"><?= $counts['categories'] ?></div></div>
  <div class="stat"><div class="k">Tags</div><div class="v"><?= $counts['tags'] ?></div></div>
  <div class="stat"><div class="k">Active sources</div><div class="v"><?= $counts['sources'] ?></div></div>
  <div class="stat"><div class="k">Active ads</div><div class="v"><?= $counts['ads'] ?></div></div>
</div>

<h2>Sources</h2>
<p class="muted">Enable a source, save its config (feed URL / API key), then click Import. The scheduler runs enabled sources every night at 03:00 UTC.</p>
<table class="data" data-testid="sources-table">
  <thead><tr><th>Slug</th><th>Label</th><th>Status</th><th>Config</th><th>Last import</th><th>Message</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($sources as $s): $cfg = $s['config_arr']; ?>
    <tr>
      <td><code><?= View::e($s['slug']) ?></code></td>
      <td><?= View::e($s['label']) ?></td>
      <td><span class="pill <?= $s['enabled']?'on':'off' ?>"><?= $s['enabled']?'ENABLED':'DISABLED' ?></span></td>
      <td style="min-width:280px">
        <form method="post" action="/admin/source/config" style="display:flex;flex-direction:column;gap:4px" data-testid="cfg-<?= View::e($s['slug']) ?>">
          <input type="hidden" name="slug" value="<?= View::e($s['slug']) ?>">
          <?php if (str_contains($s['slug'], 'csv')): ?>
            <input type="url" name="feed_url" placeholder="CSV feed URL" value="<?= View::e($cfg['feed_url'] ?? '') ?>" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:6px 8px;border-radius:5px;font-size:.82rem">
          <?php elseif ($s['slug'] === 'xnxx_rapidapi'): ?>
            <input type="text" name="api_key" placeholder="RAPIDAPI_KEY" value="<?= View::e($cfg['api_key'] ?? '') ?>" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:6px 8px;border-radius:5px;font-size:.82rem">
            <input type="text" name="host" placeholder="RAPIDAPI_HOST (optional)" value="<?= View::e($cfg['host'] ?? '') ?>" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:6px 8px;border-radius:5px;font-size:.82rem">
          <?php else: ?>
            <span class="muted" style="font-size:.8rem">No config required</span>
          <?php endif; ?>
          <?php if ($s['slug'] !== 'demo'): ?>
            <button class="btn-ghost" style="padding:4px 10px;font-size:.78rem" data-testid="save-cfg-<?= View::e($s['slug']) ?>">Save config</button>
          <?php endif; ?>
        </form>
      </td>
      <td><?= View::e($s['last_import_at'] ?? '—') ?></td>
      <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= View::e($s['last_status'] ?? '—') ?></td>
      <td style="text-align:right;white-space:nowrap">
        <form method="post" action="/admin/source/toggle" style="display:inline"><input type="hidden" name="slug" value="<?= View::e($s['slug']) ?>"><button class="btn-ghost" data-testid="toggle-<?= View::e($s['slug']) ?>"><?= $s['enabled']?'Disable':'Enable' ?></button></form>
        <form method="post" action="/admin/source/import" style="display:inline"><input type="hidden" name="slug" value="<?= View::e($s['slug']) ?>"><button class="btn-primary" data-testid="import-<?= View::e($s['slug']) ?>">Import</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2 style="margin-top:32px">Advertising slots</h2>
<p class="muted">Manage sponsor banners and network snippets rendered on the site. Slots available: <?= implode(', ', array_map(fn($k,$v)=>"<code>$k</code>",array_keys($positions), array_values($positions))) ?></p>
<table class="data" data-testid="ads-table">
  <thead><tr><th>Position</th><th>Kind</th><th>Title</th><th>Weight</th><th>Active</th><th>Window</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($ads as $a): ?>
    <tr>
      <td><code><?= View::e($a['position']) ?></code></td>
      <td><?= View::e($a['kind']) ?></td>
      <td><?= View::e($a['title'] ?: '—') ?></td>
      <td><?= (int)$a['weight'] ?></td>
      <td><span class="pill <?= $a['active']?'on':'off' ?>"><?= $a['active']?'ON':'OFF' ?></span></td>
      <td><?= View::e(($a['starts_at'] ?? '—') . ' → ' . ($a['ends_at'] ?? '—')) ?></td>
      <td style="text-align:right">
        <form method="post" action="/admin/ads/delete" style="display:inline" onsubmit="return confirm('Delete this ad?')"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn-ghost" data-testid="ad-delete-<?= (int)$a['id'] ?>">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$ads): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:20px">No ads yet — create one below.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3 style="margin-top:24px">Create ad slot</h3>
<form method="post" action="/admin/ads/save" data-testid="ad-form" style="background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Position
    <select name="position" data-testid="ad-position" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
      <?php foreach ($positions as $k=>$lb): ?><option value="<?= $k ?>"><?= View::e($lb) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Kind
    <select name="kind" data-testid="ad-kind" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
      <option value="banner">Banner (self-managed image + link)</option>
      <option value="snippet">Snippet (AdSense/ExoClick HTML/JS)</option>
    </select>
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Title
    <input name="title" data-testid="ad-title" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Link URL (banner)
    <input name="link_url" data-testid="ad-link" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase;grid-column:1/-1">Image URL (banner)
    <input name="image_url" data-testid="ad-image" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase;grid-column:1/-1">Snippet HTML/JS (snippet)
    <textarea name="snippet_html" data-testid="ad-snippet" rows="4" placeholder="<script async src='...'></script><ins class='adsbygoogle' ...></ins>" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px;font-family:var(--font-mono);font-size:.82rem"></textarea>
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Weight
    <input type="number" name="weight" value="1" min="1" data-testid="ad-weight" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
  </label>
  <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;color:#c8cdd6"><input type="checkbox" name="active" value="1" checked data-testid="ad-active"> Active</label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Starts at (optional)
    <input type="datetime-local" name="starts_at" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Ends at (optional)
    <input type="datetime-local" name="ends_at" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:8px;border-radius:6px">
  </label>
  <div style="grid-column:1/-1;text-align:right"><button class="btn-primary" data-testid="ad-save">Create ad slot</button></div>
</form>

<h2 style="margin-top:32px">Diagnostics</h2>
<form method="post" action="/admin/cache/clear" style="display:inline"><button class="btn-primary" data-testid="clear-cache">Clear cache</button></form>
<p class="muted" style="margin-top:14px;font-size:.85rem">Live-import cron: <code>0 */6 * * *</code> (UTC) via <code>.emergent/crons.yml</code> → <code>POST /api/cron/nightly-import</code></p>

<h2 style="margin-top:32px">Search insights</h2>
<div class="stat-grid" data-testid="search-stats">
  <div class="stat"><div class="k">Unique keywords</div><div class="v"><?= (int)$search_stats['total_unique'] ?></div></div>
  <div class="stat"><div class="k">Total searches</div><div class="v"><?= (int)$search_stats['total_searches'] ?></div></div>
  <div class="stat"><div class="k">Zero-result queries</div><div class="v"><?= (int)$search_stats['zero_results'] ?></div></div>
</div>
<p class="muted">Top 20 keywords typed into the site search. Use them to spot content gaps and build landing pages that convert.</p>
<table class="data" data-testid="top-searches">
  <thead><tr><th style="width:60px">#</th><th>Keyword</th><th>Searches</th><th>Last results</th><th>Last seen</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($top_searches as $i => $s): ?>
    <tr <?= (int)$s['results_last'] === 0 ? 'style="background:rgba(225,6,0,.06)"' : '' ?>>
      <td><?= $i + 1 ?></td>
      <td><code data-testid="kw-<?= $i ?>"><?= View::e($s['q']) ?></code></td>
      <td><strong><?= (int)$s['count'] ?></strong></td>
      <td><?= (int)$s['results_last'] ?><?= (int)$s['results_last']===0 ? ' <span class="pill off" style="margin-left:6px">GAP</span>' : '' ?></td>
      <td class="muted"><?= View::e($s['last_seen']) ?></td>
      <td style="text-align:right"><a class="btn-ghost" style="padding:4px 10px;font-size:.78rem" href="/search?q=<?= urlencode($s['q']) ?>" target="_blank" data-testid="kw-open-<?= $i ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$top_searches): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:20px">No searches yet — data will appear here as visitors use the search bar.</td></tr><?php endif; ?>
  </tbody>
</table>
