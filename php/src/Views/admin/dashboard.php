<?php use App\Core\View; ?>
<?php if (!empty($_GET['msg'])): ?><div class="flash" data-testid="admin-flash"><?= View::e($_GET['msg']) ?></div><?php endif; ?>
<h1>Dashboard</h1>
<div class="stat-grid">
  <div class="stat"><div class="k">Videos</div><div class="v"><?= $counts['videos'] ?></div></div>
  <div class="stat"><div class="k">Categories</div><div class="v"><?= $counts['categories'] ?></div></div>
  <div class="stat"><div class="k">Tags</div><div class="v"><?= $counts['tags'] ?></div></div>
  <div class="stat"><div class="k">Active sources</div><div class="v"><?= $counts['sources'] ?></div></div>
  <div class="stat"><div class="k">Active ads</div><div class="v"><?= $counts['ads'] ?></div></div>
  <div class="stat" data-testid="unavailable-videos"><div class="k">Hidden videos</div><div class="v"><?= $counts['unavailable'] ?></div></div>
</div>

<h2>Sources</h2>
<p class="muted">Enable a source, save its config (feed URL / API key), then click Import. Imports run in the background — the Message column shows RUNNING… and then the result. The scheduler re-runs enabled sources every 6 hours.</p>
<?php $inp = 'background:#0f1115;border:1px solid var(--border);color:var(--text);padding:6px 8px;border-radius:5px;font-size:.82rem'; ?>
<div class="table-scroll" data-testid="sources-table-scroll">
<table id="sources-data" class="data" data-testid="sources-table">
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
            <input type="url" name="feed_url" placeholder="<?= $s['slug']==='xvideos_csv' ? 'default: xvideos.com-export-week.csv.gz' : 'CSV feed URL' ?>" value="<?= View::e($cfg['feed_url'] ?? '') ?>" style="<?= $inp ?>" data-testid="cfg-feed-url-<?= View::e($s['slug']) ?>">
            <?php if ($s['slug'] === 'upornia_csv'): ?>
              <input type="url" value="<?= View::e($cfg['deleted_feed_url'] ?? '') ?>" readonly style="<?= $inp ?>" data-testid="cfg-deleted-feed-upornia">
              <span class="muted" style="font-size:.72rem">XML feed imports HD videos; deleted IDs are reconciled after each import.</span>
            <?php endif; ?>
            <?php if ($s['slug'] === 'xvideos_csv'): ?>
              <input type="url" value="<?= View::e($cfg['deleted_feed_url'] ?? '') ?>" readonly style="<?= $inp ?>" data-testid="cfg-deleted-feed-xvideos">
              <input type="url" value="<?= View::e($cfg['deleted_full_feed_url'] ?? '') ?>" readonly style="<?= $inp ?>" data-testid="cfg-deleted-full-feed-xvideos">
              <span class="muted" style="font-size:.72rem">Official 7-day feed runs nightly. Full feed is manual only.</span>
            <?php endif; ?>
          <?php elseif ($s['slug'] === 'xnxx_rapidapi'): ?>
            <input type="password" name="api_key" placeholder="RAPIDAPI_KEY" value="<?= View::e($cfg['api_key'] ?? '') ?>" style="<?= $inp ?>" data-testid="cfg-api-key-xnxx" autocomplete="off">
            <input type="text" name="host" placeholder="porn-xnxx-api.p.rapidapi.com" value="<?= View::e($cfg['host'] ?? '') ?>" style="<?= $inp ?>" data-testid="cfg-host-xnxx">
            <input type="text" name="queries" placeholder="search queries, comma-separated (milf,teen,anal…)" value="<?= View::e($cfg['queries'] ?? '') ?>" style="<?= $inp ?>" data-testid="cfg-queries-xnxx">
            <?php if (!empty($cfg['page_cursor'])): ?><span class="muted" style="font-size:.72rem">Next page cursor: <?= (int)$cfg['page_cursor'] ?></span><?php endif; ?>
          <?php else: ?>
            <span class="muted" style="font-size:.8rem">No config required</span>
          <?php endif; ?>
          <?php if ($s['slug'] !== 'demo'): ?>
            <input type="number" min="10" max="<?= $s['slug'] === 'upornia_csv' ? '10000' : '5000' ?>" name="import_limit" placeholder="rows per import (default 300)" value="<?= View::e($cfg['import_limit'] ?? '') ?>" style="<?= $inp ?>" data-testid="cfg-limit-<?= View::e($s['slug']) ?>">
            <button class="btn-ghost" style="padding:4px 10px;font-size:.78rem" data-testid="save-cfg-<?= View::e($s['slug']) ?>">Save config</button>
          <?php endif; ?>
        </form>
      </td>
      <td><?= View::e($s['last_import_at'] ?? '—') ?></td>
      <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= View::e($s['last_status'] ?? '') ?>" data-testid="status-<?= View::e($s['slug']) ?>"><?= View::e($s['last_status'] ?? '—') ?></td>
      <td style="text-align:right;white-space:nowrap">
        <form method="post" action="/admin/source/toggle" style="display:inline"><input type="hidden" name="slug" value="<?= View::e($s['slug']) ?>"><button class="btn-ghost" data-testid="toggle-<?= View::e($s['slug']) ?>"><?= $s['enabled']?'Disable':'Enable' ?></button></form>
        <form method="post" action="/admin/source/import" style="display:inline"><input type="hidden" name="slug" value="<?= View::e($s['slug']) ?>"><button class="btn-primary" data-testid="import-<?= View::e($s['slug']) ?>">Import</button></form>
        <?php if ($s['slug'] === 'xvideos_csv'): ?>
        <form method="post" action="/admin/source/cleanup-dead" style="display:inline"><input type="hidden" name="mode" value="week"><button class="btn-ghost" data-testid="cleanup-dead-xvideos">Clean 7-day deleted</button></form>
        <form method="post" action="/admin/source/cleanup-dead" style="display:inline" onsubmit="return confirm('Start the full deleted-URL backfill? This can take a long time.')"><input type="hidden" name="mode" value="full"><button class="btn-ghost" data-testid="cleanup-dead-full-xvideos">Full backfill</button></form>
        <?php endif; ?>
        <?php if ($s['slug'] === 'demo'): ?>
        <form method="post" action="/admin/source/purge-demo" style="display:inline" onsubmit="return confirm('Delete all demo videos? Real imported videos are kept.')"><button class="btn-ghost" style="color:#ff8887;border-color:rgba(225,6,0,.4)" data-testid="purge-demo">Purge demo</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2 style="margin-top:32px">Advertising slots</h2>
<p class="muted">Manage sponsor banners and network snippets rendered on the site. Slots available: <?= implode(', ', array_map(fn($k,$v)=>"<code>$k</code>",array_keys($positions), array_values($positions))) ?></p>
<div class="table-scroll" data-testid="ads-table-scroll">
<table id="ads-data" class="data" data-testid="ads-table">
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
</div>

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
<a href="/admin/digest" class="btn-ghost" data-testid="nav-digest" style="margin-left:8px">Weekly digest →</a>
<p class="muted" style="margin-top:14px;font-size:.85rem">Live-import cron: <code>0 */6 * * *</code> · Landing-suggest: <code>30 3 * * *</code> · Digest: <code>0 9 * * 1</code> UTC</p>

<h2 style="margin-top:32px">Search insights</h2>
<div class="stat-grid" data-testid="search-stats">
  <div class="stat"><div class="k">Unique keywords</div><div class="v"><?= (int)$search_stats['total_unique'] ?></div></div>
  <div class="stat"><div class="k">Total searches</div><div class="v"><?= (int)$search_stats['total_searches'] ?></div></div>
  <div class="stat"><div class="k">Zero-result queries</div><div class="v"><?= (int)$search_stats['zero_results'] ?></div></div>
</div>
<p class="muted">Top 20 keywords typed into the site search. Use them to spot content gaps and build landing pages that convert.</p>
<div class="table-scroll" data-testid="top-searches-scroll">
<table id="searches-data" class="data" data-testid="top-searches">
  <thead><tr><th style="width:60px">#</th><th>Keyword</th><th>Searches</th><th>Last results</th><th>Last seen</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($top_searches as $i => $s): ?>
    <tr <?= (int)$s['results_last'] === 0 ? 'style="background:rgba(225,6,0,.06)"' : '' ?>>
      <td><?= $i + 1 ?></td>
      <td><code data-testid="kw-<?= $i ?>"><?= View::e($s['q']) ?></code></td>
      <td><strong><?= (int)$s['count'] ?></strong></td>
      <td><?= (int)$s['results_last'] ?><?= (int)$s['results_last']===0 ? ' <span class="pill off" style="margin-left:6px">GAP</span>' : '' ?></td>
      <td class="muted"><?= View::e($s['last_seen']) ?></td>
      <td style="text-align:right"><a class="btn-ghost" style="padding:4px 10px;font-size:.78rem" href="/search?q=<?= urlencode($s['q']) ?>" target="_blank" data-testid="kw-open-<?= $i ?>">Open</a>
        <a class="btn-primary" style="padding:4px 10px;font-size:.78rem;margin-left:4px" href="/admin/landings/new?keyword=<?= urlencode($s['q']) ?>" data-testid="kw-landing-<?= $i ?>">+ Landing</a>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$top_searches): ?><tr><td colspan="6" class="muted" style="text-align:center;padding:20px">No searches yet — data will appear here as visitors use the search bar.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<h2 style="margin-top:32px">Landing pages</h2>
<p class="muted">Curated collection pages that turn search intent into visits. Click <strong>+ Landing</strong> next to a zero-result keyword above to spin one up in one click. Draft suggestions from the nightly job appear here with the <span class="pill off">DRAFT</span> badge.</p>
<div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
  <a href="/admin/landings/new" class="btn-primary" data-testid="new-landing">+ Create landing page</a>
  <?php if ($landings): ?>
    <span style="flex:1"></span>
    <div style="display:flex;gap:6px;align-items:center" data-testid="bulk-toolbar">
      <span class="muted" style="font-size:.8rem"><span id="bulk-count" data-testid="bulk-count">0</span> selected</span>
      <button form="landings-bulk-form" name="action" value="enable" class="btn-primary" style="padding:6px 12px;font-size:.82rem" onclick="return confirmBulk('enable and ping')" data-testid="bulk-enable">Enable + Ping</button>
      <button form="landings-bulk-form" name="action" value="reping" class="btn-ghost" style="padding:6px 12px;font-size:.82rem" onclick="return confirmBulk('re-ping search engines for')" data-testid="bulk-reping">Re-ping</button>
      <button form="landings-bulk-form" name="action" value="covers" class="btn-ghost" style="padding:6px 12px;font-size:.82rem" onclick="return confirmBulk('generate AI covers for')" data-testid="bulk-covers">Generate AI covers</button>
      <button form="landings-bulk-form" name="action" value="delete" class="btn-ghost" style="padding:6px 12px;font-size:.82rem;color:#ff8887;border-color:rgba(225,6,0,.4)" onclick="return confirmBulk('DELETE')" data-testid="bulk-delete">Delete</button>
    </div>
  <?php endif; ?>
</div>
<form id="landings-bulk-form" method="post" action="/admin/landings/bulk"></form>
<div class="table-scroll" data-testid="landings-table-scroll">
<table id="landings-data" class="data" data-testid="landings-table">
  <thead><tr>
    <th style="width:36px"><?php if ($landings): ?><input type="checkbox" id="bulk-all" data-testid="bulk-all"><?php endif; ?></th>
    <th>Slug</th><th>Title</th><th>Keyword</th><th>Cover</th><th>Tpl</th><th>Cat/Tag</th><th>Views</th><th>A/B</th><th>Status</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($landings as $l):
    $cats = json_decode($l['categories_json'] ?: '[]', true) ?: [];
    $tags = json_decode($l['tags_json'] ?: '[]', true) ?: [];
    $isDraft = !$l['active'] && !empty($l['suggested']);
    $abOn = !empty($l['title_variant_b']);
    $ab = $abOn ? \App\Models\Landing::abStats((int)$l['id']) : null;
    $ctr = function($imp,$clk){ return $imp>0 ? number_format($clk*100/$imp,1).'%' : '—'; };
  ?>
    <tr <?= $isDraft ? 'style="background:rgba(255,176,32,.06)"' : '' ?>>
      <td><input type="checkbox" form="landings-bulk-form" name="ids[]" value="<?= (int)$l['id'] ?>" class="bulk-cb" data-testid="bulk-cb-<?= (int)$l['id'] ?>"></td>
      <td><a href="/l/<?= View::e($l['slug']) ?>" target="_blank"><code>/l/<?= View::e($l['slug']) ?></code></a></td>
      <td><?= View::e($l['title']) ?><?= $isDraft ? ' <span class="pill off" style="background:rgba(255,176,32,.14);color:#ffd076;margin-left:6px">DRAFT SUGGEST</span>' : '' ?></td>
      <td><?= View::e($l['keyword'] ?: '—') ?></td>
      <td data-testid="landing-cover-<?= (int)$l['id'] ?>"><?= !empty($l['og_image']) ? '✓' : '—' ?></td>
      <td><code><?= View::e($l['template'] ?? 'grid') ?></code></td>
      <td class="muted" style="font-size:.82rem"><?= count($cats) ?>·<?= count($tags) ?></td>
      <td><?= (int)$l['views'] ?></td>
      <td style="font-size:.78rem;font-family:var(--font-mono)" data-testid="ab-cell-<?= (int)$l['id'] ?>">
        <?php if ($abOn): $winner = ($ab['A']['impressions']>=20 && $ab['B']['impressions']>=20) ? ((($ab['A']['clicks']/max(1,$ab['A']['impressions']))>($ab['B']['clicks']/max(1,$ab['B']['impressions'])))?'A':'B') : null; ?>
          <div>A: <?= $ab['A']['clicks'] ?>/<?= $ab['A']['impressions'] ?> <span class="muted">(<?= $ctr($ab['A']['impressions'],$ab['A']['clicks']) ?>)</span><?= $winner==='A'?' <span class="pill on" style="padding:1px 6px;font-size:.62rem">WIN</span>':'' ?></div>
          <div>B: <?= $ab['B']['clicks'] ?>/<?= $ab['B']['impressions'] ?> <span class="muted">(<?= $ctr($ab['B']['impressions'],$ab['B']['clicks']) ?>)</span><?= $winner==='B'?' <span class="pill on" style="padding:1px 6px;font-size:.62rem">WIN</span>':'' ?></div>
        <?php else: ?><span class="muted">—</span><?php endif; ?>
      </td>
      <td><span class="pill <?= $l['active']?'on':'off' ?>"><?= $l['active']?'ON':'OFF' ?></span></td>
      <td style="text-align:right;white-space:nowrap">
        <a class="btn-ghost" style="padding:4px 10px;font-size:.78rem" href="/admin/landings/edit?id=<?= (int)$l['id'] ?>" data-testid="landing-edit-<?= (int)$l['id'] ?>">Edit</a>
        <form method="post" action="/admin/landings/delete" style="display:inline" onsubmit="return confirm('Delete landing?')"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button class="btn-ghost" style="padding:4px 10px;font-size:.78rem" data-testid="landing-delete-<?= (int)$l['id'] ?>">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$landings): ?><tr><td colspan="11" class="muted" style="text-align:center;padding:20px">No landing pages yet — the Search insights section above suggests keywords to convert.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($landings): ?>
<script>
(function(){
  const all = document.getElementById('bulk-all');
  const cbs = document.querySelectorAll('.bulk-cb');
  const count = document.getElementById('bulk-count');
  function refresh(){ count.textContent = String(document.querySelectorAll('.bulk-cb:checked').length); }
  if (all) all.addEventListener('change', function(){ cbs.forEach(c=>c.checked=all.checked); refresh(); });
  cbs.forEach(c => c.addEventListener('change', refresh));
  window.confirmBulk = function(verb){
    const n = document.querySelectorAll('.bulk-cb:checked').length;
    if (!n) { alert('Select at least one landing.'); return false; }
    return confirm(`${verb} ${n} landing(s)?`);
  };
})();
</script>
<?php endif; ?>
