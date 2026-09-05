<?php use App\Core\View; $p = $prefill; ?>
<h1><?= $p['id'] ? 'Edit landing page' : 'Create landing page' ?></h1>
<p class="muted"><?= $p['id'] ? 'Update the curated collection.' : 'Turn a search intent into a curated collection page reachable at /l/{slug}.' ?></p>

<form method="post" action="/admin/landings/save" data-testid="landing-form" style="background:var(--panel);border:1px solid var(--border);border-radius:10px;padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:14px;max-width:900px">
  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Title
    <input name="title" value="<?= View::e($p['title']) ?>" required data-testid="l-title" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px">
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">URL slug (/l/…)
    <input name="slug" value="<?= View::e($p['slug']) ?>" data-testid="l-slug" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px" placeholder="auto from title">
  </label>
  <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Source keyword (search intent)
    <input name="keyword" value="<?= View::e($p['keyword']) ?>" data-testid="l-keyword" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px">
  </label>
  <label style="display:flex;align-items:center;gap:8px;font-size:.9rem;color:#c8cdd6"><input type="checkbox" name="active" value="1" <?= $p['active']?'checked':'' ?> data-testid="l-active"> Active (visible)</label>

  <label style="grid-column:1/-1;display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Intro copy
    <textarea name="intro" rows="4" data-testid="l-intro" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px;font-family:inherit"><?= View::e($p['intro']) ?></textarea>
  </label>

  <fieldset style="grid-column:1/-1;border:1px solid var(--border);border-radius:8px;padding:12px">
    <legend class="muted" style="padding:0 8px;font-size:.75rem;font-weight:700;text-transform:uppercase">SEO &amp; social sharing</legend>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Meta title (<= 60 chars)
        <input name="meta_title" maxlength="120" value="<?= View::e($p['meta_title']) ?>" data-testid="l-meta-title" placeholder="Defaults to page title" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px">
      </label>
      <label style="display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">OG image URL
        <input name="og_image" value="<?= View::e($p['og_image']) ?>" data-testid="l-og-image" placeholder="Defaults to first video thumbnail" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px">
      </label>
      <label style="grid-column:1/-1;display:flex;flex-direction:column;gap:4px;font-size:.75rem;color:var(--muted);font-weight:700;text-transform:uppercase">Meta description (<= 160 chars)
        <textarea name="meta_description" rows="2" maxlength="300" data-testid="l-meta-desc" placeholder="Defaults to intro copy trimmed to 160 chars" style="background:#0f1115;border:1px solid var(--border);color:var(--text);padding:9px;border-radius:6px;font-family:inherit"><?= View::e($p['meta_description']) ?></textarea>
      </label>
    </div>
    <?php if ($p['id'] && $p['slug']):
      $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
      $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
      $pubUrl = $scheme . '://' . $host . '/l/' . rawurlencode($p['slug']);
      $u = rawurlencode($pubUrl);
    ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
      <div class="muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Social preview tester</div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <a class="btn-ghost" style="padding:6px 12px;font-size:.82rem" href="https://cards-dev.twitter.com/validator" target="_blank" rel="noopener" data-testid="preview-x-validator">X Card Validator</a>
        <a class="btn-ghost" style="padding:6px 12px;font-size:.82rem" href="https://twitter.com/intent/tweet?url=<?= View::e($u) ?>" target="_blank" rel="noopener" data-testid="preview-x">Preview on X</a>
        <a class="btn-ghost" style="padding:6px 12px;font-size:.82rem" href="https://developers.facebook.com/tools/debug/?q=<?= View::e($u) ?>" target="_blank" rel="noopener" data-testid="preview-fb">Preview on Facebook</a>
        <a class="btn-ghost" style="padding:6px 12px;font-size:.82rem" href="https://www.linkedin.com/post-inspector/inspect/<?= View::e($u) ?>" target="_blank" rel="noopener" data-testid="preview-linkedin">Preview on LinkedIn</a>
        <span class="muted" style="font-size:.78rem;margin-left:6px">Live URL: <a href="<?= View::e($pubUrl) ?>" target="_blank" data-testid="preview-live-url"><?= View::e($pubUrl) ?></a></span>
      </div>
    </div>
    <?php else: ?>
    <p class="muted" style="font-size:.78rem;margin-top:10px">Publish this landing to unlock the social preview tester (X, Facebook, LinkedIn).</p>
    <?php endif; ?>
  </fieldset>

  <fieldset style="grid-column:1/-1;border:1px solid var(--border);border-radius:8px;padding:12px">
    <legend class="muted" style="padding:0 8px;font-size:.75rem;font-weight:700;text-transform:uppercase">Categories to include</legend>
    <div style="display:flex;flex-wrap:wrap;gap:6px;max-height:180px;overflow:auto">
      <?php foreach ($all_cats as $c): $checked = in_array($c['slug'], $p['categories']); ?>
        <label class="chip" style="cursor:pointer;<?= $checked ? 'background:var(--accent);color:#fff;border-color:var(--accent)':'' ?>">
          <input type="checkbox" name="categories[]" value="<?= View::e($c['slug']) ?>" <?= $checked?'checked':'' ?> style="display:none">
          <?= View::e($c['name']) ?>
        </label>
      <?php endforeach; ?>
      <?php if (!$all_cats): ?><span class="muted" style="font-size:.85rem">No categories imported yet — run a source import first.</span><?php endif; ?>
    </div>
  </fieldset>

  <fieldset style="grid-column:1/-1;border:1px solid var(--border);border-radius:8px;padding:12px">
    <legend class="muted" style="padding:0 8px;font-size:.75rem;font-weight:700;text-transform:uppercase">Tags to include</legend>
    <div style="display:flex;flex-wrap:wrap;gap:6px;max-height:180px;overflow:auto">
      <?php foreach ($all_tags as $t): $checked = in_array($t['slug'], $p['tags']); ?>
        <label class="chip" style="cursor:pointer;<?= $checked ? 'background:var(--accent);color:#fff;border-color:var(--accent)':'' ?>">
          <input type="checkbox" name="tags[]" value="<?= View::e($t['slug']) ?>" <?= $checked?'checked':'' ?> style="display:none">
          #<?= View::e($t['name']) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <div style="grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end">
    <a href="/admin" class="btn-ghost">Cancel</a>
    <button class="btn-primary" data-testid="l-save"><?= $p['id']?'Save changes':'Publish landing' ?></button>
  </div>
</form>

<script>
// Toggle chip styling on checkbox click
document.querySelectorAll('fieldset .chip input[type=checkbox]').forEach(function(cb){
  cb.parentElement.addEventListener('click', function(){
    setTimeout(function(){
      if (cb.checked){ cb.parentElement.style.background='var(--accent)'; cb.parentElement.style.color='#fff'; cb.parentElement.style.borderColor='var(--accent)'; }
      else { cb.parentElement.style.background=''; cb.parentElement.style.color=''; cb.parentElement.style.borderColor=''; }
    }, 0);
  });
});
</script>
