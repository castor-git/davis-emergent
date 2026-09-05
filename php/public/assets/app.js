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
  // Category & tag preferences (pin / hide) — POST to /api/prefs/{pin|hide}
  document.querySelectorAll('.chip-wrap .chip-act').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault(); e.stopPropagation();
      const wrap = btn.closest('.chip-wrap'); if (!wrap) return;
      const slug = wrap.getAttribute('data-slug');
      const type = wrap.getAttribute('data-type') || 'category';
      const act = btn.getAttribute('data-act');
      const fd = new FormData(); fd.append('slug', slug); fd.append('type', type);
      fetch('/api/prefs/' + act, {method:'POST', body: fd, credentials:'same-origin'})
        .then(r=>r.json()).then(function(){ location.reload(); })
        .catch(function(){});
    });
  });
  // Preferences drawer (Categories + Tags tabs)
  const openBtn = document.getElementById('prefbtn');
  const drawer = document.getElementById('pref-drawer');
  const backdrop = document.getElementById('pref-backdrop');
  const closeBtn = document.getElementById('pref-close');
  const applyBtn = document.getElementById('pref-apply');
  const clearHidesBtn = document.getElementById('pref-clear-hides');
  let prefState = {category:{pins:[],hides:[],labels:{},all:[]}, tag:{pins:[],hides:[],labels:{},all:[]}};
  const search = {category: null, tag: null};

  function ids(type){
    return type === 'tag'
      ? {pins:'pref-tag-pins-list', hides:'pref-tag-hides-list', pc:'pref-tag-pins-count', hc:'pref-tag-hides-count', picker:'pref-tag-picker', search:'pref-tag-search'}
      : {pins:'pref-pins-list', hides:'pref-hides-list', pc:'pref-pins-count', hc:'pref-hides-count', picker:'pref-picker', search:'pref-search'};
  }
  function labelOf(type, s){ return (prefState[type].labels[s] && prefState[type].labels[s].name) || s; }
  function pillPrefix(type){ return type === 'tag' ? '#' : ''; }

  function renderType(type){
    const el = ids(type); const st = prefState[type];
    document.getElementById(el.pc).textContent = '(' + st.pins.length + ')';
    document.getElementById(el.hc).textContent = '(' + st.hides.length + ')';
    document.getElementById(el.pins).innerHTML = st.pins.length
      ? st.pins.map(s => '<span class="pref-item pin" data-slug="'+s+'" data-type="'+type+'" data-testid="pref-'+(type==='tag'?'tag-':'')+'pin-'+s+'">📌 '+pillPrefix(type)+labelOf(type,s)+'<button class="pref-x" data-remove="pin" data-type="'+type+'" data-slug="'+s+'">×</button></span>').join('')
      : '<span class="muted" style="font-size:.85rem">Nothing pinned yet.</span>';
    document.getElementById(el.hides).innerHTML = st.hides.length
      ? st.hides.map(s => '<span class="pref-item hide" data-slug="'+s+'" data-type="'+type+'" data-testid="pref-'+(type==='tag'?'tag-':'')+'hide-'+s+'">✕ '+pillPrefix(type)+labelOf(type,s)+'<button class="pref-x" data-remove="hide" data-type="'+type+'" data-slug="'+s+'">×</button></span>').join('')
      : '<span class="muted" style="font-size:.85rem">Nothing hidden.</span>';
    renderPicker(type, search[type] || '');
  }
  function renderPicker(type, q){
    q = (q||'').trim().toLowerCase();
    const st = prefState[type];
    const rows = st.all
      .filter(c => q === '' || c.name.toLowerCase().includes(q) || c.slug.includes(q))
      .slice(0, 40)
      .map(c => {
        const pinned = st.pins.includes(c.slug);
        const hidden = st.hides.includes(c.slug);
        return '<div class="pref-row" data-slug="'+c.slug+'" data-type="'+type+'" data-testid="pref-'+(type==='tag'?'tag-':'')+'picker-row-'+c.slug+'">'
          + '<div><span class="name">'+pillPrefix(type)+c.name+'</span><span class="cnt">'+c.count+'</span></div>'
          + '<div class="acts">'
          +   '<button data-toggle="pin" class="'+(pinned?'on-pin':'')+'">'+(pinned?'📌 pinned':'pin')+'</button>'
          +   '<button data-toggle="hide" class="'+(hidden?'on-hide':'')+'">'+(hidden?'✕ hidden':'hide')+'</button>'
          + '</div></div>';
      });
    document.getElementById(ids(type).picker).innerHTML = rows.length ? rows.join('') : '<p class="muted" style="font-size:.82rem;padding:8px">No matches.</p>';
  }
  function loadPrefs(){
    return fetch('/api/prefs', {credentials:'same-origin'}).then(r=>r.json()).then(s => {
      prefState.category = s.category || {pins:[],hides:[],labels:{},all:[]};
      prefState.tag = s.tag || {pins:[],hides:[],labels:{},all:[]};
      renderType('category'); renderType('tag');
    });
  }
  function toggle(act, type, slug){
    const fd = new FormData(); fd.append('slug', slug); fd.append('type', type);
    return fetch('/api/prefs/' + act, {method:'POST', body: fd, credentials:'same-origin'}).then(r=>r.json())
      .then(s => { prefState.category = s.category; prefState.tag = s.tag; renderType('category'); renderType('tag'); });
  }
  function openDrawer(){ drawer.hidden = false; backdrop.hidden = false; loadPrefs(); }
  function closeDrawer(){ drawer.hidden = true; backdrop.hidden = true; }
  if (openBtn) openBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  // Tab switching
  document.querySelectorAll('.pref-tab').forEach(function(t){
    t.addEventListener('click', function(){
      document.querySelectorAll('.pref-tab').forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      const which = t.getAttribute('data-tab');
      document.querySelectorAll('[data-tab-panel]').forEach(p => p.hidden = p.getAttribute('data-tab-panel') !== which);
    });
  });

  // Live search per tab
  document.addEventListener('input', function(e){
    if (e.target && e.target.id === 'pref-search') { search.category = e.target.value; renderPicker('category', e.target.value); }
    if (e.target && e.target.id === 'pref-tag-search') { search.tag = e.target.value; renderPicker('tag', e.target.value); }
  });

  if (drawer) drawer.addEventListener('click', function(e){
    const rm = e.target.closest('[data-remove]');
    if (rm) { toggle(rm.getAttribute('data-remove'), rm.getAttribute('data-type') || 'category', rm.getAttribute('data-slug')); return; }
    const tg = e.target.closest('[data-toggle]');
    if (tg) {
      const row = tg.closest('.pref-row');
      toggle(tg.getAttribute('data-toggle'), row.getAttribute('data-type') || 'category', row.getAttribute('data-slug'));
      return;
    }
  });
  if (applyBtn) applyBtn.addEventListener('click', function(){ location.reload(); });
  if (clearHidesBtn) clearHidesBtn.addEventListener('click', function(){
    const jobs = [];
    prefState.category.hides.forEach(s => jobs.push(toggle('hide', 'category', s)));
    prefState.tag.hides.forEach(s => jobs.push(toggle('hide', 'tag', s)));
    Promise.all(jobs);
  });
})();
