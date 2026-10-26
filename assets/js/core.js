/* ==========================================================================
   Cinema Vault — shared front-end core (all signed-in pages)
   Toasts, modals (focus trap, Escape), CSRF-aware POST helper, nav shadow.
   Exposes window.CVCore.
   ========================================================================== */
(() => {
  'use strict';
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = () => ($('meta[name="csrf-token"]') || {}).content || '';
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* unavailable */ } }
  };

  /* ── toast ── */
  const toastEl = $('#toast');
  let toastTimer;
  function toast(msg, kind = '') {
    if (!toastEl) return;
    toastEl.className = 'toast ' + (kind === 'warn' ? 'warn' : '');
    toastEl.innerHTML = '<svg class="i"><use href="#i-' + (kind === 'warn' ? 'alert' : 'check-circle') + '"/></svg><span></span>';
    toastEl.lastChild.textContent = msg;
    requestAnimationFrame(() => toastEl.classList.add('show'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), kind === 'warn' ? 5000 : 3200);
  }

  /* ── modals ── */
  let lastFocus = null;
  function openModal(id) {
    const m = document.getElementById(id); if (!m) return;
    lastFocus = document.activeElement;
    m.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => {
      const f = $('input:not([type=hidden]):not([type=file]), textarea', m) || $('.modal-close', m) || $('.btn', m);
      const target = m.getAttribute('role') === 'alertdialog' ? $('[data-close]', m) : f;
      target && target.focus({ preventScroll: true });
    }, 80);
    document.dispatchEvent(new CustomEvent('cv:modal-open', { detail: { id } }));
  }
  function closeModal(id) {
    const m = document.getElementById(id); if (!m) return;
    m.classList.remove('open');
    if (!$('.modal-backdrop.open')) document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus && document.contains(lastFocus)) lastFocus.focus({ preventScroll: true });
    document.dispatchEvent(new CustomEvent('cv:modal-close', { detail: { id } }));
  }
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-open]');
    if (o) { openModal(o.dataset.open); return; }
    const c = e.target.closest('[data-close]');
    if (c) closeModal(c.closest('.modal-backdrop').id);
  });
  $$('.modal-backdrop').forEach(el => el.addEventListener('mousedown', e => { if (e.target === el) closeModal(el.id); }));
  document.addEventListener('keydown', e => {
    const open = $$('.modal-backdrop.open');
    if (e.key === 'Escape' && open.length) { closeModal(open[open.length - 1].id); return; }
    if (e.key === 'Tab' && open.length) {
      const m = open[open.length - 1];
      const f = $$('button:not([disabled]), input:not([type=hidden]):not([type=file]), select, textarea, a[href]', m).filter(x => x.offsetParent !== null);
      if (!f.length) return;
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  });

  /* ── POST to actions.php (CSRF token attached) → parsed JSON; throws Error(message) on failure ── */
  async function post(action, fields = {}) {
    const fd = new FormData();
    fd.append('action', action); fd.append('csrf_token', csrf());
    Object.entries(fields).forEach(([k, v]) => { if (v !== undefined && v !== null) fd.append(k, v); });
    let res, data;
    try {
      res = await fetch('actions.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      data = await res.json();
    } catch (e) { throw new Error('Could not reach the server. Check your connection and try again.'); }
    if (res.status === 401) { location.href = 'homepage.php?modal=login'; throw new Error('Please sign in again.'); }
    if (!res.ok || !data.ok) throw new Error(data.error || 'Something went wrong. Please try again.');
    return data;
  }
  async function getJSON(url) {
    let res, data;
    try { res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }); data = await res.json(); }
    catch (e) { throw new Error('Could not reach the server. Check your connection and try again.'); }
    if (res.status === 401) { location.href = 'homepage.php?modal=login'; throw new Error('Please sign in again.'); }
    if (!res.ok || !data.ok) throw new Error(data.error || 'Something went wrong. Please try again.');
    return data;
  }

  /** Reload the page but keep a toast message for after the reload. */
  function reloadWith(msg) {
    try { if (msg) sessionStorage.setItem('cv_toast', msg); } catch (e) { /* ignore */ }
    location.reload();
  }
  try { const t = sessionStorage.getItem('cv_toast'); if (t) { sessionStorage.removeItem('cv_toast'); toast(t); } } catch (e) { /* ignore */ }

  /* ── server flash messages ── */
  ((window.CV || {}).flash || []).forEach(f => toast(f.msg, f.kind === 'ok' ? '' : 'warn'));

  /* ── nav shadow ── */
  const navbar = $('#navbar');
  if (navbar) { const f = () => navbar.classList.toggle('scrolled', scrollY > 10); addEventListener('scroll', f, { passive: true }); f(); }

  /* ── busy state for async buttons ── */
  async function busy(btn, fn) {
    if (btn) { btn.disabled = true; btn.classList.add('is-busy'); }
    try { return await fn(); } finally { if (btn) { btn.disabled = false; btn.classList.remove('is-busy'); } }
  }

  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  window.CVCore = { $, $$, toast, openModal, closeModal, post, getJSON, reloadWith, busy, store, esc, csrf };
})();
