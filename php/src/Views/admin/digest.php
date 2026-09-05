<?php use App\Core\View; ?>
<?php if (!empty($_GET['msg'])): ?><div class="flash" data-testid="digest-flash"><?= View::e($_GET['msg']) ?></div><?php endif; ?>
<h1>Weekly digest</h1>
<p class="muted">Runs every Monday at 09:00 UTC via <code>.emergent/crons.yml</code> → <code>POST /api/cron/weekly-digest</code>.</p>

<div class="stat-grid">
  <div class="stat"><div class="k">Recipient</div><div class="v" style="font-size:1rem;color:#e9ecef" data-testid="digest-to"><?= View::e($to) ?></div></div>
  <div class="stat"><div class="k">Email key</div><div class="v" style="font-size:1rem;color:<?= $keyOk?'var(--hd)':'var(--accent)' ?>" data-testid="digest-key"><?= $keyOk?'CONFIGURED':'MISSING' ?></div></div>
</div>

<div style="display:flex;gap:10px;margin-bottom:14px">
  <a href="/admin" class="btn-ghost">← Back to admin</a>
  <a href="/admin/digest?action=send-now" class="btn-primary" data-testid="digest-send-now" onclick="return confirm('Send the digest email now?')">Send now</a>
</div>

<h2>Preview</h2>
<div style="background:#fff;border-radius:10px;border:1px solid var(--border);overflow:hidden" data-testid="digest-preview">
<?= $preview['html'] /* trusted server-side template */ ?>
</div>

<h2 style="margin-top:24px">Last 5 send attempts</h2>
<table class="data" data-testid="digest-history">
  <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th><th>Note</th></tr></thead>
  <tbody>
  <?php foreach ($last as $d): ?>
    <tr><td><?= View::e($d['sent_at']) ?></td><td><?= View::e($d['sent_to']) ?></td><td><?= View::e($d['subject']) ?></td><td><span class="pill <?= ((int)$d['status']>=200 && (int)$d['status']<300)?'on':'off' ?>"><?= (int)$d['status'] ?></span></td><td class="muted" style="font-size:.82rem;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= View::e($d['note'] ?: '—') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$last): ?><tr><td colspan="5" class="muted" style="text-align:center;padding:20px">No send attempts yet.</td></tr><?php endif; ?>
  </tbody>
</table>
