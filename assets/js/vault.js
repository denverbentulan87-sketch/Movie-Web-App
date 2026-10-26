/* ==========================================================================
   Cinema Vault — behaviour shared by all signed-in pages
   Surprise Me, collection create/edit/delete, Movie Wrapped share card.
   ========================================================================== */
(() => {
  'use strict';
  const { $, $$, toast, openModal, closeModal, post, getJSON, reloadWith, busy, esc } = window.CVCore;

  /* ── broken images (CSP forbids inline onerror); idempotent with dashboard.js ── */
  if (!window.__cvImgErr) {
    window.__cvImgErr = true;
    document.addEventListener('error', e => { if (e.target.tagName === 'IMG' && e.target.hasAttribute('data-hide-on-error')) e.target.remove(); }, true);
  }

  $$('[data-autosubmit]').forEach(s => { if (!s.dataset.bound) { s.dataset.bound = '1'; s.addEventListener('change', () => s.form.submit()); } });

  /* ══════════ SURPRISE ME ══════════ */
  const result = $('#surpriseResult'), go = $('#surpriseGo');
  if (result && go) {
    let shown = [];
    const stars = n => '★'.repeat(n) + '☆'.repeat(5 - n);

    function render(m) {
      result.innerHTML = '';
      const card = document.createElement('div'); card.className = 'surprise-card';
      const poster = document.createElement('div'); poster.className = 'surprise-poster';
      if (m.cover_url) { const img = new Image(); img.alt = ''; img.src = m.cover_url; img.onerror = () => img.remove(); poster.appendChild(img); }
      poster.insertAdjacentHTML('beforeend', '<svg class="i"><use href="#i-film"/></svg>');
      const body = document.createElement('div'); body.className = 'surprise-body';
      const h = document.createElement('h3'); h.textContent = m.movie_title;
      const facts = [m.genre, m.release_year, m.runtime_minutes ? m.runtime_minutes + ' min' : '',
        { High: 'Must watch', Normal: 'Next', Low: 'Someday' }[m.priority]].filter(Boolean);
      const meta = document.createElement('p'); meta.className = 'surprise-meta'; meta.textContent = facts.join(' · ');
      body.append(h, meta);
      if (m.rating > 0) { const r = document.createElement('p'); r.className = 'surprise-rating'; r.textContent = stars(m.rating); r.setAttribute('aria-label', 'Rated ' + m.rating + ' out of 5'); body.appendChild(r); }
      if (m.tags && m.tags.length) { const t = document.createElement('p'); t.className = 'surprise-tags'; t.textContent = m.tags.map(x => '#' + x.name).join('  '); body.appendChild(t); }
      if (m.description) { const d = document.createElement('p'); d.className = 'surprise-desc'; d.textContent = m.description.length > 220 ? m.description.slice(0, 217) + '…' : m.description; body.appendChild(d); }
      const row = document.createElement('div'); row.className = 'surprise-btns';
      const start = document.createElement('button'); start.type = 'button'; start.className = 'btn btn-gold';
      start.innerHTML = '<svg class="i"><use href="#i-play"/></svg> Start watching';
      start.addEventListener('click', async () => {
        try { await busy(start, () => post('movie_status', { movie_id: m.watchlist_id, status: 'Watching' })); reloadWith('Enjoy “' + m.movie_title + '”!'); }
        catch (err) { toast(err.message, 'warn'); }
      });
      row.appendChild(start);
      if (window.CVOpenDetail) {
        const det = document.createElement('button'); det.type = 'button'; det.className = 'btn btn-soft'; det.textContent = 'Details';
        det.addEventListener('click', () => { closeModal('surpriseModal'); window.CVOpenDetail(m.watchlist_id); });
        row.appendChild(det);
      }
      body.appendChild(row);
      card.append(poster, body); result.appendChild(card);
    }

    function message(html) { result.innerHTML = '<div class="surprise-empty">' + html + '</div>'; }

    async function pick() {
      const qs = new URLSearchParams(new FormData($('#surpriseFilters')));
      [...qs.keys()].forEach(k => { if (!qs.get(k)) qs.delete(k); });
      if (shown.length) qs.set('exclude', shown.join(','));
      try {
        const r = await busy(go, () => getJSON('surprise.php?' + qs));
        if (r.movie) { shown.push(r.movie.watchlist_id); render(r.movie); $('#surpriseGoLabel').textContent = 'Pick again'; result.focus && result.setAttribute('tabindex', '-1'); }
        else if (r.reason === 'exhausted') { shown = []; message('<p>You\'ve seen every match once.</p><p>Press the button to start over.</p>'); }
        else message('<p>Nothing matches.</p><p>Your Plan to Watch list has no films for these filters. Try loosening them, or <a href="index.php?status=Plan+to+Watch">review the list</a>.</p>');
      } catch (err) { message('<p>' + esc(err.message) + '</p>'); }
    }
    go.addEventListener('click', pick);
    $('#surpriseFilters').addEventListener('change', () => { shown = []; $('#surpriseGoLabel').textContent = 'Pick a movie'; });
    $('#surpriseFilters').addEventListener('submit', e => e.preventDefault());
    document.addEventListener('cv:modal-open', e => { if (e.detail.id === 'surpriseModal' && !result.firstChild) pick(); });
  }

  /* ══════════ COLLECTIONS: create / edit / delete modals ══════════ */
  const collForm = $('#collForm');
  if (collForm) {
    const setMode = (edit, c = {}) => {
      $('#collModalH').textContent = edit ? 'Edit collection' : 'New collection';
      $('#collAction').value = edit ? 'collection_update' : 'collection_create';
      $('#collId').value = edit ? c.id : '';
      $('#collName').value = c.name || ''; $('#collDesc').value = c.description || '';
      $('#collSubmit').lastChild.textContent = edit ? ' Save changes' : ' Create collection';
    };
    document.addEventListener('click', e => {
      const n = e.target.closest('[data-new-collection]');
      if (n) { setMode(false); openModal('collModal'); return; }
      const ed = e.target.closest('#collEditBtn, [data-edit-collection]');
      if (ed) { setMode(true, { id: ed.dataset.id, name: ed.dataset.name, description: ed.dataset.description }); openModal('collModal'); return; }
      const del = e.target.closest('#collDelBtn, [data-delete-collection]');
      if (del) {
        $('#collDelId').value = del.dataset.id;
        $('#collDelMsg').textContent = '“' + del.dataset.name + '” will be deleted. The films in it stay in your vault.';
        openModal('collDeleteModal');
      }
    });
    collForm.addEventListener('submit', e => { const b = $('#collSubmit'); if (b) { b.disabled = true; b.classList.add('is-busy'); } });
  }

  /* ══════════ MOVIE WRAPPED: shareable card (drawn locally, nothing is uploaded) ══════════ */
  const shareBtn = $('#wrappedShare');
  if (shareBtn) {
    shareBtn.addEventListener('click', async () => {
      const d = JSON.parse($('#wrappedData').textContent);
      const withTitles = $('#shareTitles').checked;
      try { await document.fonts.ready; } catch (e) { /* fine */ }
      const W = 1080, H = 1350, c = document.createElement('canvas'); c.width = W; c.height = H;
      const x = c.getContext('2d');
      const g = x.createLinearGradient(0, 0, W, H); g.addColorStop(0, '#2a1d3e'); g.addColorStop(.55, '#12131f'); g.addColorStop(1, '#07070c');
      x.fillStyle = g; x.fillRect(0, 0, W, H);
      const glow = x.createRadialGradient(W * .85, H * .1, 20, W * .85, H * .1, 620); glow.addColorStop(0, 'rgba(214,168,95,.35)'); glow.addColorStop(1, 'rgba(214,168,95,0)');
      x.fillStyle = glow; x.fillRect(0, 0, W, H);
      const gold = '#f2cf8f', soft = '#c4c4c4';
      const text = (t, px, py, size, color, weight = 600, font = 'Outfit, system-ui, sans-serif', align = 'left') => {
        x.font = weight + ' ' + size + 'px ' + font; x.fillStyle = color; x.textAlign = align; x.fillText(t, px, py);
      };
      text('CINEMA VAULT', 80, 120, 44, '#d6a85f', 400, '"Bebas Neue", Impact, sans-serif');
      text('YOUR ' + d.year + ' MOVIE WRAPPED', 80, 210, 62, '#fff', 700, '"Playfair Display", Georgia, serif');
      let y = 360;
      const big = (n, label) => { text(String(n), 80, y, 150, gold, 400, '"Bebas Neue", Impact, sans-serif'); text(label, 80, y + 46, 34, soft, 400); y += 190; };
      big(d.movies, d.movies === 1 ? 'Movie watched' : 'Movies watched');
      if (d.hours !== null) big(d.hours, 'Hours watched');
      if (d.avg !== null) big(d.avg + ' ★', 'Average rating');
      if (d.topGenre) { text('TOP GENRE', 80, y, 28, '#d6a85f', 600); text(d.topGenre, 80, y + 64, 64, '#fff', 700, '"Playfair Display", Georgia, serif'); y += 130; }
      if (withTitles) {
        [['MOST WATCHED', d.mostWatched], ['HIGHEST RATED', d.highestRated]].filter(r => r[1]).forEach(([k, v]) => {
          text(k, 80, y, 28, '#d6a85f', 600); text(v.length > 28 ? v.slice(0, 27) + '…' : v, 80, y + 54, 46, '#fff', 600); y += 110;
        });
      }
      text('Made with Cinema Vault', 80, H - 70, 28, '#8a909c', 400);
      c.toBlob(b => {
        const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = 'cinema-vault-wrapped-' + d.year + '.png';
        document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        toast('Your card was saved as an image.');
      }, 'image/png');
    });
  }
})();
