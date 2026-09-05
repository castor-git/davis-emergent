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
})();
