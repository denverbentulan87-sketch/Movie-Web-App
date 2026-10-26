/* ==========================================================================
   Cinema Vault — dashboard behaviour (index.php)
   Server data arrives via window.CV. Shared helpers come from core.js.
   ========================================================================== */
(() => {
  'use strict';
  const { $, $$, toast, openModal, closeModal, post, getJSON, reloadWith, busy, store, esc } = window.CVCore;
  const CV = window.CV || { existing: [], tags: [], collections: [] };
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ── GREETING ── */
  const greet = $('#greeting');
  if (greet) {
    const hr = new Date().getHours();
    greet.textContent = hr < 5 ? 'Late night viewing' : hr < 12 ? 'Good morning' : hr < 18 ? 'Good afternoon' : 'Good evening';
  }

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

  /* ── broken poster images: hide them (CSP forbids inline onerror) ── */
  document.addEventListener('error', e => {
    if (e.target.tagName === 'IMG' && e.target.hasAttribute('data-hide-on-error')) e.target.remove();
  }, true);

  /* ── selects that submit the filter form ── */
  $$('[data-autosubmit]').forEach(s => { s.dataset.bound = '1'; s.addEventListener('change', () => s.form.submit()); });

  /* ── filter panel ── */
  const advToggle = $('#advToggle'), advPanel = $('#advPanel');
  if (advToggle) advToggle.addEventListener('click', () => {
    const open = advPanel.hidden;
    advPanel.hidden = !open;
    advToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) $('select, input', advPanel).focus();
  });

  /* ── confirm before destructive tag deletion ── */
  document.addEventListener('submit', e => {
    const c = e.target.dataset && e.target.dataset.confirm;
    if (c && !confirm(c)) e.preventDefault();
  });

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
  $$('.row').forEach(row => { updateArrows(row); row.addEventListener('scroll', () => updateArrows(row), { passive: true }); });
  addEventListener('resize', () => $$('.row').forEach(updateArrows));
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-scroll]');
    if (!b) return;
    const row = document.getElementById(b.dataset.scroll);
    row.scrollBy({ left: b.dataset.dir * Math.max(row.clientWidth * .8, 300), behavior: 'smooth' });
  });

  document.addEventListener('click', e => { if (e.target.closest('[data-open-add]')) openAdd(); });
  document.addEventListener('keydown', e => {
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !$('.modal-backdrop.open')) {
      e.preventDefault(); $('#searchInput').focus();
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
    // priority only matters while the status is Plan to Watch
    const st = $('#' + px + 'Status'), pf = $('#' + px + 'PriorityField');
    const sync = () => { pf.hidden = st.value !== 'Plan to Watch'; };
    st.addEventListener('change', sync); sync();
  });

  /* ── UPLOAD ZONES ── */
  function resetZone(zoneId, fileId) {
    const z = document.getElementById(zoneId);
    $('.uz-preview', z)?.remove(); $('.uz-remove', z)?.remove();
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
    if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) { toast('Choose a JPG, PNG, WEBP or GIF image.', 'warn'); input.value = ''; return; }
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
    $('#addStatus').dispatchEvent(new Event('change'));
    openModal('addModal');
  }
  const val = v => (v === null || v === undefined ? '' : v);
  function openEdit(m) {
    $('#editId').value = m.watchlist_id;
    $('#editMovieTitle').value = m.movie_title;
    $('#editDesc').value = m.description || '';
    $('#editGenre').value = m.genre;
    $('#editStatus').value = m.status;
    $('#editPriority').value = m.priority || 'Normal';
    $('#editYear').value = val(m.release_year); $('#editRuntime').value = val(m.runtime_minutes);
    $('#editDirector').value = val(m.director); $('#editLanguage').value = val(m.language);
    $('#editCountry').value = val(m.country); $('#editCast').value = val(m.cast_members);
    $('#editStatus').dispatchEvent(new Event('change'));
    setStars('edit', parseInt(m.rating, 10) || 0);
    resetZone('editZone', 'editFile');
    if (m.cover_url) showZonePreview('editZone', 'editFile', m.cover_url, false);
    openModal('editModal');
  }

  /* ══════════ DETAIL MODAL ══════════ */
  let current = null;            // latest movie object being shown
  let history = [];
  let needsReload = false;       // layout changed (status/priority/…): refresh when the modal closes
  let reloadMsg = '';

  const fmtDate = d => {
    if (!d) return '';
    const dt = new Date(d + 'T00:00:00');
    return isNaN(dt) ? d : dt.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
  };
  const stars = n => '★'.repeat(n) + '☆'.repeat(5 - n);
  const showErr = msg => { const e = $('#dError'); e.textContent = msg || ''; e.hidden = !msg; };

  function cardsFor(id) { return $$('.movie-card[data-id="' + id + '"]'); }
  /** Keep the cards on the page in step with a changed movie (heart badge + data). */
  function patchCards(m) {
    cardsFor(m.watchlist_id).forEach(c => {
      let base = {}; try { base = JSON.parse(c.dataset.movie); } catch (e) { /* ignore */ }
      const merged = Object.assign(base, {
        is_favorite: m.is_favorite, tags: m.tags, collections: m.collections, priority: m.priority, rating: m.rating,
        status: m.status, watch_count: m.watch_count, last_watched_at: m.last_watched_at
      });
      c.dataset.movie = JSON.stringify(merged);
      const badge = $('.card-fav', c); if (badge) badge.hidden = !m.is_favorite;
      const fb = $('[data-act="fav"]', c);
      if (fb) { fb.classList.toggle('is-on', !!m.is_favorite); fb.setAttribute('aria-pressed', m.is_favorite ? 'true' : 'false'); }
    });
  }

  function renderDetail(m) {
    current = m;
    const hero = $('#detailHero'); hero.innerHTML = '';
    const ph = () => { const d = document.createElement('div'); d.className = 'detail-hero-ph'; d.innerHTML = '<svg class="i"><use href="#i-film"/></svg>'; hero.prepend(d); };
    if (m.cover_url) {
      const img = new Image(); img.alt = m.movie_title; img.src = m.cover_url;
      img.onerror = () => { img.remove(); ph(); };
      hero.appendChild(img);
    } else ph();

    $('#dTitle').textContent = m.movie_title;
    const fav = $('#dFav');
    fav.classList.toggle('is-on', !!m.is_favorite);
    fav.setAttribute('aria-pressed', m.is_favorite ? 'true' : 'false');
    fav.setAttribute('aria-label', m.is_favorite ? 'Remove from favorites' : 'Add to favorites');

    const meta = $('#dMeta'); meta.innerHTML = '';
    const bits = [[m.genre, ''], [m.status, m.status === 'Watching' ? 'status-watching' : m.status === 'Watched' ? 'status-watched' : '']];
    if (m.status === 'Plan to Watch') bits.push([{ High: 'Must watch', Normal: 'Next', Low: 'Someday' }[m.priority] || m.priority, 'prio-' + String(m.priority).toLowerCase()]);
    if (m.release_year) bits.push([String(m.release_year), '']);
    if (m.runtime_minutes) bits.push([m.runtime_minutes + ' min', '']);
    bits.filter(t => t[0]).forEach(([text, cls]) => { const s = document.createElement('span'); s.className = 'detail-tag ' + cls; s.textContent = text; meta.appendChild(s); });

    const r = parseInt(m.rating, 10) || 0, st = $('#dStars');
    st.setAttribute('aria-label', r ? r + ' out of 5 stars' : 'Not rated yet');
    st.innerHTML = '';
    for (let i = 1; i <= 5; i++) st.insertAdjacentHTML('beforeend', '<svg class="i' + (i <= r ? ' on' : '') + '"><use href="#i-star"/></svg>');

    $$('#dStatus button').forEach(b => { const on = b.dataset.status === m.status; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    $('#dPriorityWrap').hidden = m.status !== 'Plan to Watch';
    $$('#dPriority button').forEach(b => { const on = b.dataset.priority === m.priority; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });

    const n = parseInt(m.watch_count, 10) || 0;
    $('#dWatchSummary').innerHTML = n
      ? 'Watched <strong>' + n + (n === 1 ? ' time' : ' times') + '</strong>' + (m.last_watched_at ? '<br>Last watched: ' + esc(fmtDate(m.last_watched_at)) : '')
      : 'Not watched yet.';
    $('#dWatchBtnLbl').textContent = n ? 'Watched again' : 'Mark as watched';

    $('#dDesc').textContent = (m.description || '').trim() || 'No description yet. Use Edit to add one.';
    const info = $('#dInfo'); info.innerHTML = '';
    [['Director', m.director], ['Cast', m.cast_members], ['Language', m.language], ['Country', m.country]].filter(x => x[1]).forEach(([k, v]) => {
      info.insertAdjacentHTML('beforeend', '<div><dt>' + k + '</dt><dd>' + esc(v) + '</dd></div>');
    });

    renderNotes(m);
    renderPills('#dTags', m.tags, 'tag');
    renderPills('#dCols', m.collections, 'collection');
    renderLists(m);
  }

  function renderNotes(m) {
    const view = $('#dNotesView'); view.innerHTML = '';
    const text = (m.personal_notes || '').trim();
    if (!text) {
      view.innerHTML = '<p class="muted">You haven\'t written anything yet.</p><button type="button" class="btn btn-soft" data-notes-edit>Add notes</button>';
    } else {
      const p = document.createElement('p'); p.className = 'notes-text'; p.textContent = text;
      if (m.notes_spoiler) {
        p.hidden = true;
        const warn = document.createElement('div'); warn.className = 'spoiler-warn';
        warn.innerHTML = '<svg class="i"><use href="#i-alert"/></svg> Contains spoilers <button type="button" class="link-btn" data-spoiler-show>Show</button>';
        view.append(warn, p);
      } else view.append(p);
      const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-soft'; b.dataset.notesEdit = ''; b.innerHTML = '<svg class="i"><use href="#i-edit"/></svg> Edit notes';
      view.append(b);
    }
    $('#dNotesForm').hidden = true; view.hidden = false;
  }

  function renderPills(sel, items, kind) {
    const ul = $(sel); ul.innerHTML = '';
    if (!items.length) { ul.innerHTML = '<li class="muted">' + (kind === 'tag' ? 'No tags yet.' : 'Not in any collection.') + '</li>'; return; }
    items.forEach(it => {
      const li = document.createElement('li'); li.className = 'pill pill-' + kind;
      const a = document.createElement('a');
      a.href = kind === 'tag' ? 'index.php?tags%5B%5D=' + it.id : 'index.php?collection=' + it.id;
      a.textContent = it.name; a.title = 'Show films with this ' + kind;
      const x = document.createElement('button'); x.type = 'button'; x.className = 'pill-x';
      x.dataset[kind === 'tag' ? 'untag' : 'uncollect'] = it.id;
      x.setAttribute('aria-label', 'Remove ' + it.name); x.innerHTML = '<svg class="i"><use href="#i-x"/></svg>';
      li.append(a, x); ul.appendChild(li);
    });
  }

  function renderLists(m) {
    $('#tagList').innerHTML = (CV.tags || []).filter(t => !m.tags.some(x => x.id === t.id)).map(t => '<option value="' + esc(t.name) + '">').join('');
    const have = new Set(m.collections.map(c => c.id));
    const avail = (CV.collections || []).filter(c => !have.has(c.id));
    const sel = $('#dColSelect');
    sel.innerHTML = '<option value="">Choose a collection…</option>' + avail.map(c => '<option value="' + c.id + '">' + esc(c.name) + '</option>').join('');
    $('#dColForm').hidden = !avail.length;
    $('#dColsEmpty').hidden = (CV.collections || []).length > 0;
  }

  function renderHistory(list) {
    history = list;
    const ol = $('#dHistory'); ol.innerHTML = '';
    if (!list.length) { ol.innerHTML = '<li class="muted">No watches recorded yet.</li>'; return; }
    list.forEach(h => {
      const li = document.createElement('li'); li.className = 'hist-item';
      const top = document.createElement('div'); top.className = 'hist-top';
      const d = document.createElement('strong'); d.textContent = fmtDate(h.watched_at);
      top.appendChild(d);
      if (h.rating) { const s = document.createElement('span'); s.className = 'hist-stars'; s.textContent = stars(h.rating); s.setAttribute('aria-label', h.rating + ' out of 5 stars'); top.appendChild(s); }
      const del = document.createElement('button'); del.type = 'button'; del.className = 'pill-x'; del.dataset.histDel = h.id;
      del.setAttribute('aria-label', 'Delete watch on ' + fmtDate(h.watched_at)); del.innerHTML = '<svg class="i"><use href="#i-trash"/></svg>';
      top.appendChild(del); li.appendChild(top);
      if (h.notes) { const q = document.createElement('q'); q.textContent = h.notes; li.appendChild(q); }
      ol.appendChild(li);
    });
  }

  function applyBundle(b, { reload = false, message = '' } = {}) {
    if (b.all_tags) CV.tags = b.all_tags;
    if (b.all_collections) CV.collections = b.all_collections;
    if (b.movie) { renderDetail(b.movie); patchCards(b.movie); }
    if (b.history) renderHistory(b.history);
    if (reload) { needsReload = true; reloadMsg = message || reloadMsg; }
  }

  /** Run an action for the current movie, refresh the modal from the response. */
  async function act(btn, action, fields = {}, after) {
    showErr('');
    try {
      const r = await busy(btn, () => post(action, Object.assign({ movie_id: current.watchlist_id }, fields)));
      applyBundle(r, { reload: r.reload, message: r.message });
      if (!r.reload) toast(r.message);
      if (after) after(r);
      return r;
    } catch (err) { showErr(err.message); toast(err.message, 'warn'); return null; }
  }

  async function openDetail(m) {
    showErr('');
    $('#dWatchForm').hidden = true;
    renderDetail(Object.assign({ tags: [], collections: [] }, m));
    renderHistory([]);
    $('#dHistory').innerHTML = '<li class="muted">Loading…</li>';
    openModal('detailModal');
    try {
      const b = await getJSON('movie.php?id=' + encodeURIComponent(m.watchlist_id));
      if (current && current.watchlist_id === m.watchlist_id) applyBundle(b);
    } catch (err) { showErr(err.message); $('#dHistory').innerHTML = '<li class="muted">Could not load history.</li>'; }
  }
  window.CVOpenDetail = id => { const c = cardsFor(id)[0]; if (c) openDetail(JSON.parse(c.dataset.movie)); };

  document.addEventListener('cv:modal-close', e => {
    if (e.detail.id === 'detailModal' && needsReload) { needsReload = false; reloadWith(reloadMsg); }
  });

  // detail controls
  $('#dFav').addEventListener('click', e => act(e.currentTarget, 'movie_favorite', { value: current.is_favorite ? '0' : '1' }, () => { if (CV.filtered) needsReload = true; }));
  $('#dStatus').addEventListener('click', e => {
    const b = e.target.closest('[data-status]'); if (!b || b.dataset.status === current.status) return;
    act(b, 'movie_status', { status: b.dataset.status });
  });
  $('#dPriority').addEventListener('click', e => {
    const b = e.target.closest('[data-priority]'); if (!b || b.dataset.priority === current.priority) return;
    act(b, 'movie_priority', { priority: b.dataset.priority });
  });
  $('#dWatchBtn').addEventListener('click', () => {
    const f = $('#dWatchForm'); f.hidden = !f.hidden;
    if (!f.hidden) { f.elements.watched_at.value = CV.today; f.elements.watched_at.max = CV.today; f.elements.rating.value = current.rating || 0; f.elements.notes.value = ''; f.elements.watched_at.focus(); }
  });
  $('#dwCancel').addEventListener('click', () => { $('#dWatchForm').hidden = true; $('#dWatchBtn').focus(); });
  $('#dWatchForm').addEventListener('submit', e => {
    e.preventDefault(); const f = e.target;
    act(f.querySelector('[type=submit]'), 'movie_watch', { watched_at: f.elements.watched_at.value, rating: f.elements.rating.value, notes: f.elements.notes.value }, () => { f.hidden = true; });
  });
  $('#dNotesView').addEventListener('click', e => {
    if (e.target.closest('[data-spoiler-show]')) { $('.notes-text', $('#dNotesView')).hidden = false; $('.spoiler-warn', $('#dNotesView')).remove(); return; }
    if (e.target.closest('[data-notes-edit]')) {
      $('#dnText').value = current.personal_notes || ''; $('#dnSpoiler').checked = !!current.notes_spoiler;
      $('#dNotesView').hidden = true; $('#dNotesForm').hidden = false; $('#dnText').focus();
    }
  });
  $('#dnCancel').addEventListener('click', () => renderNotes(current));
  $('#dNotesForm').addEventListener('submit', e => {
    e.preventDefault();
    act(e.target.querySelector('[type=submit]'), 'movie_notes', { personal_notes: $('#dnText').value, notes_spoiler: $('#dnSpoiler').checked ? '1' : '0' });
  });
  $('#dTagForm').addEventListener('submit', async e => {
    e.preventDefault(); const inp = $('#dTagInput'); if (!inp.value.trim()) return;
    const r = await act(e.target.querySelector('[type=submit]'), 'movie_tag_add', { tag: inp.value });
    if (r) { inp.value = ''; if (CV.filtered) needsReload = true; }
  });
  $('#dColForm').addEventListener('submit', e => {
    e.preventDefault(); const v = $('#dColSelect').value; if (!v) return;
    act(e.target.querySelector('[type=submit]'), 'collection_add_movie', { collection_id: v });
  });
  $('#dTags').addEventListener('click', e => { const b = e.target.closest('[data-untag]'); if (b) act(b, 'movie_tag_remove', { tag_id: b.dataset.untag }, () => { if (CV.filtered) needsReload = true; }); });
  $('#dCols').addEventListener('click', e => { const b = e.target.closest('[data-uncollect]'); if (b) act(b, 'collection_remove_movie', { collection_id: b.dataset.uncollect }); });
  $('#dHistory').addEventListener('click', e => {
    const b = e.target.closest('[data-hist-del]'); if (!b) return;
    if (confirm('Delete this watch entry? The watch count will update.')) act(b, 'history_delete', { history_id: b.dataset.histDel });
  });
  $('#dEdit').addEventListener('click', () => { const m = current; closeModal('detailModal'); needsReload = false; openEdit(m); });
  $('#dDel').addEventListener('click', () => { const m = current; closeModal('detailModal'); needsReload = false; confirmDel(m); });

  /* ── DELETE (POST form with CSRF) ── */
  function confirmDel(m) {
    $('#delId').value = m.watchlist_id;
    $('#delMsg').textContent = '“' + m.movie_title + '” will be removed from your vault for good, along with its watch history and notes.';
    openModal('deleteModal');
  }

  /* ── CARD EVENTS ── */
  function movieOf(card) { try { return JSON.parse(card.dataset.movie); } catch (e) { return null; } }
  document.addEventListener('click', async e => {
    const card = e.target.closest('.movie-card'); if (!card) return;
    const m = movieOf(card); if (!m) return;
    const actBtn = e.target.closest('[data-act]');
    const kind = actBtn && actBtn.dataset.act;
    if (kind === 'edit') openEdit(m);
    else if (kind === 'del') confirmDel(m);
    else if (kind === 'fav') {
      try {
        const r = await busy(actBtn, () => post('movie_favorite', { movie_id: m.watchlist_id, value: m.is_favorite ? '0' : '1' }));
        patchCards(r.movie); toast(r.message);
        if (CV.filtered) reloadWith(r.message);
      } catch (err) { toast(err.message, 'warn'); }
    } else if (kind === 'start') {
      try { const r = await busy(actBtn, () => post('movie_status', { movie_id: m.watchlist_id, status: 'Watching' })); reloadWith('Started watching “' + m.movie_title + '”.'); }
      catch (err) { toast(err.message, 'warn'); }
    } else openDetail(m);
  });
  document.addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('movie-card')) {
      e.preventDefault(); const m = movieOf(e.target); if (m) openDetail(m);
    }
  });
  document.addEventListener('click', e => { const p = e.target.closest('[data-pick]'); if (p) window.CVOpenDetail(+p.dataset.pick); });

  /* ── LIVE SEARCH (instant; Enter still searches on the server) ── */
  const searchInput = $('#searchInput'), searchClear = $('#searchClear');
  const foundNum = $('#foundNum'), foundWord = $('#foundWord'), searchEmpty = $('#searchEmpty');
  function applySearch() {
    const q = searchInput.value.trim().toLowerCase();
    searchClear.hidden = q === '';
    const activeView = netflix.hidden ? grid : netflix;
    let visible = 0;
    $$('.movie-card', activeView).forEach(c => { const hit = !q || c.dataset.title.includes(q); c.hidden = !hit; if (hit) visible++; });
    $$('[data-section]', netflix).forEach(s => { s.hidden = !$$('.movie-card:not([hidden])', s).length; });
    $$('.row', netflix).forEach(updateArrows);
    foundNum.textContent = visible;
    foundWord.textContent = visible === 1 ? 'film' : 'films';
    const total = $$('.movie-card', activeView).length;
    searchEmpty.hidden = !(q && total && !visible);
  }
  searchInput.addEventListener('input', applySearch);
  searchClear.addEventListener('click', () => { searchInput.value = ''; applySearch(); searchInput.focus(); });

  /* ── DUPLICATE GUARD ── */
  $('#addForm').addEventListener('submit', function (e) {
    const input = this.elements.movie_title, v = input.value.trim();
    if (CV.existing.includes(v.toLowerCase())) {
      e.preventDefault();
      toast('“' + v + '” is already in your vault.', 'warn');
      input.classList.add('invalid'); input.focus();
      setTimeout(() => input.classList.remove('invalid'), 2200);
    }
  });

  /* ── loading state on form submit (uploads can take a moment) ── */
  ['addForm', 'editForm'].forEach(id => $('#' + id).addEventListener('submit', e => {
    if (e.defaultPrevented) return;
    const b = e.target.querySelector('[type=submit]'); if (b) { b.disabled = true; b.classList.add('is-busy'); }
  }));

  /* init */
  if (new URLSearchParams(location.search).get('add') === '1') { history.replaceState(null, '', 'index.php'); openAdd(); }
  const saved = store.get('cv_view');
  setView(saved === 'grid' ? 'grid' : 'netflix');
})();
