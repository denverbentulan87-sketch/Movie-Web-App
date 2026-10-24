/* ==========================================================================
   Cinema Vault — dashboard behaviour
   Server data arrives via window.CV (see index.php).
   ========================================================================== */
(() => {
  'use strict';

  const CV = window.CV || { existing: [], flash: {} };
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* storage unavailable */ } }
  };

  /* ── TOAST ── */
  const toastEl = $('#toast');
  let toastTimer;
  function toast(msg, kind = '') {
    toastEl.className = 'toast ' + kind;
    toastEl.innerHTML = '<svg class="i"><use href="#i-' + (kind === 'warn' ? 'alert' : 'check-circle') + '"/></svg><span></span>';
    toastEl.lastChild.textContent = msg;
    requestAnimationFrame(() => toastEl.classList.add('show'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), 3200);
  }

  /* ── GREETING ── */
  const greet = $('#greeting');
  if (greet) {
    const hr = new Date().getHours();
    greet.textContent = hr < 5 ? 'Late night viewing' : hr < 12 ? 'Good morning' : hr < 18 ? 'Good afternoon' : 'Good evening';
  }

  /* ── NAV SHADOW ── */
  const navbar = $('#navbar');
  const onScroll = () => navbar.classList.toggle('scrolled', scrollY > 10);
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  /* ── COUNT-UP ── */
  if (!reduceMotion) {
    $$('[data-count]').forEach(el => {
      const end = parseInt(el.dataset.count, 10);
      if (!end) return;
      const t0 = performance.now(), dur = 1000;
      (function step(t) {
        const p = Math.min((t - t0) / dur, 1);
        el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(step);
      })(t0);
    });
  }

  /* ── VIEW TOGGLE (rows / grid) ── */
  const netflix = $('#netflix-view'), grid = $('#grid-view');
  function setView(v) {
    netflix.hidden = v !== 'netflix';
    grid.hidden = v !== 'grid';
    $$('.view-btn').forEach(b => b.classList.toggle('active', b.dataset.view === v));
    store.set('cv_view', v);
    applySearch();
  }
  $$('.view-btn').forEach(b => b.addEventListener('click', () => setView(b.dataset.view)));

  /* ── ROW ARROWS ── */
  function updateArrows(row) {
    const wrap = row.parentElement;
    const max = row.scrollWidth - row.clientWidth - 4;
    $('.row-btn-left', wrap).hidden = row.scrollLeft < 8;
    $('.row-btn-right', wrap).hidden = row.scrollLeft >= max;
  }
  $$('.row').forEach(row => {
    updateArrows(row);
    row.addEventListener('scroll', () => updateArrows(row), { passive: true });
  });
  addEventListener('resize', () => $$('.row').forEach(updateArrows));
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-scroll]');
    if (!b) return;
    const row = document.getElementById(b.dataset.scroll);
    row.scrollBy({ left: b.dataset.dir * Math.max(row.clientWidth * .8, 300), behavior: 'smooth' });
  });

  /* ── MODALS ── */
  let lastFocus = null;
  function openModal(id) {
    const m = document.getElementById(id);
    lastFocus = document.activeElement;
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      const f = $('input:not([type=hidden]):not([type=file]), textarea', m) || $('.modal-close', m) || $('.btn', m);
      // confirm dialogs: focus the safe action; forms: focus the first field
      const target = m.getAttribute('role') === 'alertdialog' ? $('[data-close]', m) : f;
      target && target.focus({ preventScroll: true });
    }, 80);
  }
  function closeModal(id) {
    const m = document.getElementById(id);
    m.classList.remove('open');
    if (!$('.modal-backdrop.open')) document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus && document.contains(lastFocus)) lastFocus.focus({ preventScroll: true });
  }
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-open]');
    if (o) { openModal(o.dataset.open); return; }
    if (e.target.closest('[data-open-add]')) { openAdd(); return; }
    const c = e.target.closest('[data-close]');
    if (c) closeModal(c.closest('.modal-backdrop').id);
  });
  $$('.modal-backdrop').forEach(el => el.addEventListener('mousedown', e => { if (e.target === el) closeModal(el.id); }));
  document.addEventListener('keydown', e => {
    const open = $$('.modal-backdrop.open');
    if (e.key === 'Escape' && open.length) { closeModal(open[open.length - 1].id); return; }
    if (e.key === 'Tab' && open.length) {                                   // keep focus inside the dialog
      const m = open[open.length - 1];
      const f = $$('button:not([disabled]), input:not([type=hidden]):not([type=file]), select, textarea, a[href]', m).filter(x => x.offsetParent !== null);
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !open.length) {
      e.preventDefault(); $('#searchInput').focus();                         // quick search
    }
  });

  /* ── STAR PICKERS ── */
  function setStars(px, val) {
    $('#' + px + 'Rating').value = val;
    $$('#' + px + 'StarPicker .star').forEach((s, i) => s.classList.toggle('on', i < val));
  }
  ['add', 'edit'].forEach(px => {
    const picker = $('#' + px + 'StarPicker');
    picker.addEventListener('click', e => {
      const b = e.target.closest('[data-v]'); if (!b) return;
      const v = +b.dataset.v, cur = +$('#' + px + 'Rating').value;
      setStars(px, v === cur && v !== 0 ? 0 : v);
    });
    picker.addEventListener('mouseover', e => {
      const b = e.target.closest('.star'); if (!b) return;
      $$('.star', picker).forEach((s, i) => s.classList.toggle('on', i < +b.dataset.v));
    });
    picker.addEventListener('mouseleave', () => setStars(px, +$('#' + px + 'Rating').value));
  });

  /* ── UPLOAD ZONES ── */
  function resetZone(zoneId, fileId) {
    const z = document.getElementById(zoneId);
    $('.uz-preview', z)?.remove();
    $('.uz-remove', z)?.remove();
    ['.uz-icon', '.uz-label', '.uz-hint'].forEach(s => { $(s, z).style.display = ''; });
    document.getElementById(fileId).value = '';
  }
  function showZonePreview(zoneId, fileId, src, allowRemove) {
    const z = document.getElementById(zoneId);
    ['.uz-icon', '.uz-label', '.uz-hint'].forEach(s => { $(s, z).style.display = 'none'; });
    let pv = $('.uz-preview', z);
    if (!pv) { pv = document.createElement('div'); pv.className = 'uz-preview'; z.appendChild(pv); }
    pv.innerHTML = '';
    const img = new Image(); img.alt = 'Cover preview'; img.src = src; pv.appendChild(img);
    const ch = document.createElement('div'); ch.className = 'uz-change'; ch.textContent = 'Click to choose a different image'; pv.appendChild(ch);
    if (allowRemove && !$('.uz-remove', z)) {
      const rb = document.createElement('button');
      rb.type = 'button'; rb.className = 'uz-remove'; rb.setAttribute('aria-label', 'Remove image');
      rb.innerHTML = '<svg class="i"><use href="#i-x"/></svg>';
      rb.addEventListener('click', ev => { ev.stopPropagation(); ev.preventDefault(); resetZone(zoneId, fileId); });
      z.appendChild(rb);
    }
  }
  function onFileChosen(input, zoneId) {
    const file = input.files && input.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) { toast('Choose an image file: JPG, PNG, WEBP or GIF.', 'warn'); input.value = ''; return; }
    if (file.size > 5 * 1024 * 1024) { toast('That image is over 5 MB. Choose a smaller one.', 'warn'); input.value = ''; return; }
    const fr = new FileReader();
    fr.onload = ev => showZonePreview(zoneId, input.id, ev.target.result, true);
    fr.readAsDataURL(file);
  }
  [['addZone', 'addFile'], ['editZone', 'editFile']].forEach(([zid, fid]) => {
    const z = document.getElementById(zid), f = document.getElementById(fid);
    f.addEventListener('change', () => onFileChosen(f, zid));
    z.addEventListener('dragover', e => { e.preventDefault(); z.classList.add('drag-over'); });
    z.addEventListener('dragleave', () => z.classList.remove('drag-over'));
    z.addEventListener('drop', e => {
      e.preventDefault(); z.classList.remove('drag-over');
      const file = e.dataTransfer.files[0]; if (!file) return;
      const dt = new DataTransfer(); dt.items.add(file); f.files = dt.files; onFileChosen(f, zid);
    });
  });

  /* ── ADD / EDIT ── */
  function openAdd() {
    $('#addForm').reset();
    resetZone('addZone', 'addFile');
    setStars('add', 0);
    openModal('addModal');
  }
  function openEdit(m) {
    $('#editId').value = m.watchlist_id;
    $('#editTitle').value = m.movie_title;
    $('#editDesc').value = m.description || '';
    $('#editGenre').value = m.genre;
    $('#editStatus').value = m.status;
    setStars('edit', parseInt(m.rating, 10) || 0);
    resetZone('editZone', 'editFile');
    if (m.cover_url) showZonePreview('editZone', 'editFile', m.cover_url, false);
    openModal('editModal');
  }

  /* ── DETAIL ── */
  let current = null;
  function openDetail(m) {
    current = m;
    const hero = $('#detailHero');
    hero.innerHTML = '';
    const ph = () => {
      const d = document.createElement('div'); d.className = 'detail-hero-ph';
      d.innerHTML = '<svg class="i"><use href="#i-film"/></svg>'; hero.prepend(d);
    };
    if (m.cover_url) {
      const img = new Image(); img.alt = m.movie_title; img.src = m.cover_url;
      img.onerror = () => { img.remove(); ph(); };
      hero.appendChild(img);
    } else ph();

    $('#dTitle').textContent = m.movie_title;
    const meta = $('#dMeta'); meta.innerHTML = '';
    const tags = [[m.genre, ''], [m.status, m.status === 'Watching' ? 'status-watching' : m.status === 'Watched' ? 'status-watched' : '']];
    if (m.date_added) { const y = new Date(String(m.date_added).replace(' ', 'T')).getFullYear(); if (y) tags.push(['Added ' + y, '']); }
    tags.filter(t => t[0]).forEach(([text, cls]) => {
      const s = document.createElement('span'); s.className = 'detail-tag ' + cls; s.textContent = text; meta.appendChild(s);
    });

    const r = parseInt(m.rating, 10) || 0, st = $('#dStars');
    st.setAttribute('aria-label', r ? r + ' out of 5 stars' : 'Not rated yet');
    st.innerHTML = '';
    for (let i = 1; i <= 5; i++) {
      st.insertAdjacentHTML('beforeend', '<svg class="i' + (i <= r ? ' on' : '') + '"><use href="#i-star"/></svg>');
    }
    $('#dDesc').textContent = (m.description || '').trim() || 'No description yet. Use Edit to add your notes.';
    openModal('detailModal');
  }
  $('#dEdit').addEventListener('click', () => { closeModal('detailModal'); openEdit(current); });
  $('#dDel').addEventListener('click', () => { closeModal('detailModal'); confirmDel(current); });

  /* ── DELETE ── */
  function confirmDel(m) {
    $('#delMsg').textContent = '“' + m.movie_title + '” will be removed from your vault for good.';
    $('#delLink').href = '?delete_id=' + encodeURIComponent(m.watchlist_id);
    openModal('deleteModal');
  }

  /* ── CARD EVENTS (one delegated handler: no inline JSON or quote-escaping issues) ── */
  function movieOf(card) { try { return JSON.parse(card.dataset.movie); } catch (e) { return null; } }
  document.addEventListener('click', e => {
    const card = e.target.closest('.movie-card'); if (!card) return;
    const m = movieOf(card); if (!m) return;
    const act = e.target.closest('[data-act]');
    if (act && act.dataset.act === 'edit') openEdit(m);
    else if (act && act.dataset.act === 'del') confirmDel(m);
    else openDetail(m);
  });
  document.addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('movie-card')) {
      e.preventDefault(); const m = movieOf(e.target); if (m) openDetail(m);
    }
  });

  /* ── LIVE SEARCH (instant, no page reload; Enter still submits to the server) ── */
  const searchInput = $('#searchInput'), searchClear = $('#searchClear');
  const foundNum = $('#foundNum'), foundWord = $('#foundWord'), searchEmpty = $('#searchEmpty');
  function applySearch() {
    const q = searchInput.value.trim().toLowerCase();
    searchClear.hidden = q === '';
    const activeView = netflix.hidden ? grid : netflix;
    let visible = 0;
    $$('.movie-card', activeView).forEach(c => {
      const hit = !q || c.dataset.title.includes(q);
      c.hidden = !hit; if (hit) visible++;
    });
    $$('[data-section]', netflix).forEach(s => { s.hidden = !$$('.movie-card:not([hidden])', s).length; });
    $$('.row', netflix).forEach(updateArrows);
    foundNum.textContent = netflix.hidden ? visible : $$('.movie-card:not([hidden])', netflix).length;
    foundWord.textContent = +foundNum.textContent === 1 ? 'film' : 'films';
    const total = $$('.movie-card', activeView).length;
    searchEmpty.hidden = !(q && total && !visible);
  }
  searchInput.addEventListener('input', applySearch);
  searchClear.addEventListener('click', () => { searchInput.value = ''; applySearch(); searchInput.focus(); });

  /* ── DUPLICATE GUARD (checks your whole vault, not just the current filter) ── */
  $('#addForm').addEventListener('submit', function (e) {
    const input = this.elements.movie_title, v = input.value.trim();
    if (CV.existing.includes(v.toLowerCase())) {
      e.preventDefault();
      toast('“' + v + '” is already in your vault.', 'warn');
      input.classList.add('invalid'); input.focus();
      setTimeout(() => input.classList.remove('invalid'), 2200);
    }
  });

  /* ── FLASH MESSAGES FROM THE SERVER ── */
  const fl = CV.flash || {};
  if (fl.added)  toast('Movie added to your vault.');
  if (fl.edited) toast('Changes saved.');
  if (fl.duplicate !== null && fl.duplicate !== undefined) toast('“' + fl.duplicate + '” is already in your vault.', 'warn');
  if (fl.added || fl.edited || (fl.duplicate !== null && fl.duplicate !== undefined)) {
    history.replaceState(null, '', location.pathname + location.search.replace(/[?&](added|edited|duplicate|title)=[^&]*/g, '').replace(/^&/, '?'));
  }

  /* init */
  const saved = store.get('cv_view');
  setView(saved === 'grid' ? 'grid' : 'netflix');
})();
