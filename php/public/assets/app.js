// DAVISPORN client-side interactions
(function(){
  // Age gate
  const KEY = 'davisporn_age_ok';
  const gate = document.getElementById('age-gate');
  if (gate){
    if (localStorage.getItem(KEY) !== '1') gate.hidden = false;
    const btn = document.getElementById('age-confirm');
    if (btn) btn.addEventListener('click', function(){
      localStorage.setItem(KEY, '1');
      gate.hidden = true;
    });
  }

  // Autocomplete search
  const input = document.getElementById('searchInput');
  const list = document.getElementById('suggestions');
  let t = null;
  if (input && list){
    input.addEventListener('input', function(){
      const q = this.value.trim();
      clearTimeout(t);
      if (q.length < 2){ list.hidden = true; list.innerHTML = ''; return; }
      t = setTimeout(function(){
        fetch('/api/suggest?q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(d => {
            if (!d.items || !d.items.length){ list.hidden = true; return; }
            list.innerHTML = d.items.map(i => '<li data-slug="'+i.slug+'"><a href="/video/'+i.slug+'">'+escape(i.title)+'</a></li>').join('');
            list.hidden = false;
          }).catch(() => { list.hidden = true; });
      }, 180);
    });
    document.addEventListener('click', function(e){ if (!list.contains(e.target) && e.target !== input) list.hidden = true; });
  }
  function escape(s){ return String(s).replace(/[&<>\"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // Card preview swap on hover
  document.querySelectorAll('[data-preview]').forEach(function(img){
    const orig = img.getAttribute('src');
    const prev = img.getAttribute('data-preview');
    const p = img.closest('.card');
    if (!p || !prev) return;
    p.addEventListener('mouseenter', () => { img.src = prev; });
    p.addEventListener('mouseleave', () => { img.src = orig; });
  });

  // Mobile nav toggle (simple: show/hide mainnav)
  const mb = document.getElementById('menubtn');
  if (mb) mb.addEventListener('click', function(){
    const nav = document.querySelector('.mainnav');
    if (!nav) return;
    if (getComputedStyle(nav).display === 'none'){ nav.style.display = 'flex'; nav.style.flexDirection='column'; nav.style.position='absolute'; nav.style.top='60px'; nav.style.right='16px'; nav.style.background='#131519'; nav.style.padding='10px'; nav.style.borderRadius='10px'; nav.style.border='1px solid #22262d'; }
    else nav.style.display = 'none';
  });
  // Category preferences (pin / hide) — POST to /api/prefs/{pin|hide}
  document.querySelectorAll('.chip-wrap .chip-act').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault(); e.stopPropagation();
      const wrap = btn.closest('.chip-wrap'); if (!wrap) return;
      const slug = wrap.getAttribute('data-slug'); const act = btn.getAttribute('data-act');
      const fd = new FormData(); fd.append('slug', slug);
      fetch('/api/prefs/' + act, {method:'POST', body: fd, credentials:'same-origin'})
        .then(r=>r.json()).then(function(){ location.reload(); })
        .catch(function(){});
    });
  });
  // Preferences drawer
  const openBtn = document.getElementById('prefbtn');
  const drawer = document.getElementById('pref-drawer');
  const backdrop = document.getElementById('pref-backdrop');
  const closeBtn = document.getElementById('pref-close');
  const pinsEl = document.getElementById('pref-pins-list');
  const hidesEl = document.getElementById('pref-hides-list');
  const pinsCnt = document.getElementById('pref-pins-count');
  const hidesCnt = document.getElementById('pref-hides-count');
  const pickerEl = document.getElementById('pref-picker');
  const searchEl = document.getElementById('pref-search');
  const applyBtn = document.getElementById('pref-apply');
  const clearHidesBtn = document.getElementById('pref-clear-hides');
  let prefState = {pins:[], hides:[], labels:{}, all:[]};

  function renderPrefState(){
    const label = s => (prefState.labels[s] && prefState.labels[s].name) || s;
    pinsCnt.textContent = '(' + prefState.pins.length + ')';
    hidesCnt.textContent = '(' + prefState.hides.length + ')';
    pinsEl.innerHTML = prefState.pins.length
      ? prefState.pins.map(s => '<span class="pref-item pin" data-slug="'+s+'" data-testid="pref-pin-'+s+'">📌 '+label(s)+'<button class="pref-x" data-remove="pin" data-slug="'+s+'" aria-label="Unpin">×</button></span>').join('')
      : '<span class="muted" style="font-size:.85rem">Nothing pinned yet.</span>';
    hidesEl.innerHTML = prefState.hides.length
      ? prefState.hides.map(s => '<span class="pref-item hide" data-slug="'+s+'" data-testid="pref-hide-'+s+'">✕ '+label(s)+'<button class="pref-x" data-remove="hide" data-slug="'+s+'" aria-label="Unhide">×</button></span>').join('')
      : '<span class="muted" style="font-size:.85rem">Nothing hidden.</span>';
    renderPicker(searchEl ? searchEl.value : '');
  }
  function renderPicker(q){
    q = (q||'').trim().toLowerCase();
    const rows = prefState.all
      .filter(c => q === '' || c.name.toLowerCase().includes(q) || c.slug.includes(q))
      .slice(0, 40)
      .map(c => {
        const pinned = prefState.pins.includes(c.slug);
        const hidden = prefState.hides.includes(c.slug);
        return '<div class="pref-row" data-slug="'+c.slug+'" data-testid="pref-picker-row-'+c.slug+'">'
          + '<div><span class="name">'+c.name+'</span><span class="cnt">'+c.count+'</span></div>'
          + '<div class="acts">'
          +   '<button data-toggle="pin" class="'+(pinned?'on-pin':'')+'">'+(pinned?'📌 pinned':'pin')+'</button>'
          +   '<button data-toggle="hide" class="'+(hidden?'on-hide':'')+'">'+(hidden?'✕ hidden':'hide')+'</button>'
          + '</div></div>';
      });
    pickerEl.innerHTML = rows.length ? rows.join('') : '<p class="muted" style="font-size:.82rem;padding:8px">No categories match.</p>';
  }
  function loadPrefs(){
    return fetch('/api/prefs', {credentials:'same-origin'}).then(r=>r.json()).then(s => { prefState = s; renderPrefState(); });
  }
  function toggle(act, slug){
    const fd = new FormData(); fd.append('slug', slug);
    return fetch('/api/prefs/' + act, {method:'POST', body: fd, credentials:'same-origin'}).then(r=>r.json())
      .then(s => { prefState.pins = s.pins; prefState.hides = s.hides; renderPrefState(); });
  }
  function openDrawer(){
    drawer.hidden = false; backdrop.hidden = false;
    loadPrefs();
  }
  function closeDrawer(){ drawer.hidden = true; backdrop.hidden = true; }
  if (openBtn) openBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (backdrop) backdrop.addEventListener('click', closeDrawer);
  if (searchEl) searchEl.addEventListener('input', function(){ renderPicker(this.value); });
  if (drawer) drawer.addEventListener('click', function(e){
    const rm = e.target.closest('[data-remove]');
    if (rm) { toggle(rm.getAttribute('data-remove'), rm.getAttribute('data-slug')); return; }
    const tg = e.target.closest('[data-toggle]');
    if (tg) { toggle(tg.getAttribute('data-toggle'), tg.closest('.pref-row').getAttribute('data-slug')); return; }
  });
  if (applyBtn) applyBtn.addEventListener('click', function(){ location.reload(); });
  if (clearHidesBtn) clearHidesBtn.addEventListener('click', function(){
    Promise.all(prefState.hides.map(s => toggle('hide', s))).then(()=>{});
  });
})();
