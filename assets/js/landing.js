/* ==========================================================================
   Cinema Vault — landing page behaviour
   ========================================================================== */
(() => {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const finePointer  = matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ── TOAST ── */
  const toastEl = $('#toast');
  let toastTimer;
  function toast(msg, kind = '') {
    if (!toastEl) return;
    toastEl.className = 'toast ' + kind;
    toastEl.innerHTML = '<svg class="i"><use href="#i-' + (kind === 'warn' ? 'alert' : 'check-circle') + '"/></svg><span></span>';
    toastEl.lastChild.textContent = msg;
    requestAnimationFrame(() => toastEl.classList.add('show'));
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), 3200);
  }

  /* ── NAV + SCROLL EFFECTS ── */
  const nav      = $('#siteNav');
  const progress = $('#scrollProgress');
  const reel     = $('#heroReel');
  const toTop    = $('#toTop');
  let ticking = false;

  function onScroll() {
    const y   = window.scrollY;
    const max = document.documentElement.scrollHeight - innerHeight;
    nav.classList.toggle('scrolled', y > 24);
    progress.style.transform = 'scaleX(' + (max > 0 ? y / max : 0) + ')';
    if (reel && !reduceMotion) reel.style.setProperty('--reel-rot', (y * 0.12) + 'deg');
    toTop.classList.toggle('show', y > 700);
    ticking = false;
  }
  addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();
  toTop.addEventListener('click', () => scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' }));

  /* active section link */
  const links = $$('.nav-links a');
  const spy = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + e.target.id));
    });
  }, { rootMargin: '-45% 0px -50% 0px' });
  ['features', 'showcase', 'reviews'].forEach(id => { const el = document.getElementById(id); if (el) spy.observe(el); });

  /* mobile menu */
  const toggle  = $('#navToggle');
  const navList = $('#navLinks');
  function setMenu(open) {
    navList.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', open);
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    toggle.querySelector('use').setAttribute('href', open ? '#i-x' : '#i-menu');
  }
  toggle.addEventListener('click', () => setMenu(!navList.classList.contains('open')));
  navList.addEventListener('click', e => { if (e.target.closest('a')) setMenu(false); });
  document.addEventListener('click', e => { if (!e.target.closest('#siteNav')) setMenu(false); });

  /* ── COUNT-UP STATS ── */
  const counters = $$('[data-count]');
  const countObs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      countObs.unobserve(e.target);
      const el = e.target;
      const end = parseFloat(el.dataset.count);
      const dec = parseInt(el.dataset.decimals || '0', 10);
      const suf = el.dataset.suffix || '';
      if (reduceMotion) return;
      const t0 = performance.now(), dur = 1400;
      (function step(t) {
        const p = Math.min((t - t0) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = (end * eased).toFixed(dec) + (p === 1 ? suf : '');
        if (p < 1) requestAnimationFrame(step);
      })(t0);
    });
  }, { threshold: .6 });
  counters.forEach(c => countObs.observe(c));

  /* ── HERO POSTER TILT ── */
  const hero = $('.hero'), fan = $('#fan');
  if (hero && fan && finePointer && !reduceMotion) {
    hero.addEventListener('pointermove', e => {
      const r = hero.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width  - .5;
      const y = (e.clientY - r.top)  / r.height - .5;
      fan.style.setProperty('--ry', (x * 14).toFixed(2) + 'deg');
      fan.style.setProperty('--rx', (-y * 10).toFixed(2) + 'deg');
    });
    hero.addEventListener('pointerleave', () => { fan.style.setProperty('--ry', '0deg'); fan.style.setProperty('--rx', '0deg'); });
  }

  /* ── FEATURE CARD SPOTLIGHT ── */
  $$('.feature-card').forEach(card => {
    card.addEventListener('pointermove', e => {
      const r = card.getBoundingClientRect();
      card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      card.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });

  /* ── REVEAL ON SCROLL ── */
  const revealObs = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (!e.isIntersecting) return;
      const sibs = [...e.target.parentElement.children].filter(c => c.hasAttribute('data-reveal'));
      e.target.style.transitionDelay = (Math.min(sibs.indexOf(e.target), 5) * 70) + 'ms';
      e.target.classList.add('in');
      revealObs.unobserve(e.target);
      setTimeout(() => { e.target.style.transitionDelay = ''; }, 1200);
    });
  }, { threshold: .15, rootMargin: '0px 0px -40px 0px' });
  $$('[data-reveal]').forEach(el => revealObs.observe(el));

  /* ── LIVE DEMO CARD ── */
  const demo = $('#demoCard');
  if (demo) {
    const labels = { plan: 'Plan to Watch', watching: 'Watching', watched: 'Watched' };
    const pills  = $$('.pill', demo), stars = $$('.dstar', demo);
    const badge  = $('#demoBadge'), score = $('#demoScore');
    let rating = 0;
    const paint = n => stars.forEach((s, i) => { s.classList.toggle('on', i < n); });
    pills.forEach(p => p.addEventListener('click', () => {
      pills.forEach(x => x.classList.toggle('is-on', x === p));
      demo.dataset.status = p.dataset.status;
      badge.textContent = labels[p.dataset.status];
    }));
    stars.forEach(s => {
      s.addEventListener('mouseenter', () => paint(+s.dataset.v));
      s.addEventListener('focus', () => paint(+s.dataset.v));
      s.addEventListener('click', () => {
        rating = (rating === +s.dataset.v) ? 0 : +s.dataset.v;
        stars.forEach((x, i) => x.setAttribute('aria-checked', rating === i + 1));
        score.textContent = rating ? rating + ' / 5' + (rating === 5 ? ' — a classic' : '') : 'Not rated';
        paint(rating);
      });
    });
    $('#demoStars').addEventListener('mouseleave', () => paint(rating));
    $('#demoStars').addEventListener('focusout', () => paint(rating));
  }

  /* ══════════════ AUTH MODAL ══════════════ */
  const modal = $('#authModal');
  if (!modal) return;                       // already signed in

  const tabsWrap = $('.auth-tabs', modal);
  const tabs     = $$('.auth-tab', modal);
  const panels   = { login: $('#panel-login'), register: $('#panel-register') };
  let current = 'login', lastFocus = null;

  function setTab(name, focusField = true) {
    current = name;
    tabsWrap.dataset.active = name;
    tabs.forEach(t => {
      const on = t.dataset.tab === name;
      t.setAttribute('aria-selected', on);
      t.tabIndex = on ? 0 : -1;
    });
    Object.entries(panels).forEach(([k, el]) => { el.hidden = k !== name; });
    if (focusField) setTimeout(() => { const f = $('input', panels[name]); f && f.focus({ preventScroll: true }); }, 60);
  }

  function openAuth(name = 'login') {
    lastFocus = document.activeElement;
    setTab(name, false);
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    setMenu(false);
    setTimeout(() => {
      const target = $('.input.invalid', panels[current]) || $('input', panels[current]);
      target && target.focus({ preventScroll: true });
    }, 120);
  }
  function closeAuth() {
    modal.classList.remove('open');
    document.body.style.overflow = '';
    if (location.search.includes('modal=') || location.search.includes('registered=')) {
      history.replaceState(null, '', location.pathname);
    }
    lastFocus && lastFocus.focus && lastFocus.focus({ preventScroll: true });
  }

  // triggers (links keep working without JS via ?modal=)
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-auth]');
    if (t) { e.preventDefault(); openAuth(t.dataset.auth); }
  });
  modal.addEventListener('mousedown', e => { if (e.target === modal) closeAuth(); });
  $$('[data-auth-close]', modal).forEach(b => b.addEventListener('click', closeAuth));
  tabs.forEach(t => t.addEventListener('click', () => { clearMsgs(); setTab(t.dataset.tab); }));
  $$('.link-btn[data-tab]', modal).forEach(b => b.addEventListener('click', () => { clearMsgs(); setTab(b.dataset.tab); }));

  // keyboard: Esc, arrow keys on tabs, focus trap
  document.addEventListener('keydown', e => {
    if (!modal.classList.contains('open')) return;
    if (e.key === 'Escape') { closeAuth(); return; }
    if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && document.activeElement.classList.contains('auth-tab')) {
      const next = current === 'login' ? 'register' : 'login';
      setTab(next, false); $('#tab-' + next).focus();
    }
    if (e.key === 'Tab') {
      const f = $$('button:not([disabled]):not([hidden]), input, a[href]', modal).filter(el => el.offsetParent !== null);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* password show / hide */
  $$('[data-toggle-pass]', modal).forEach(btn => btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.togglePass);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.querySelector('use').setAttribute('href', show ? '#i-eye-off' : '#i-eye');
    input.focus();
  }));

  /* password strength + match hint */
  const regPass = $('#regPass'), regConfirm = $('#regConfirm');
  const strength = $('#strength'), strengthText = $('#strengthText'), matchHint = $('#matchHint');
  function scorePassword(p) {
    if (!p) return 0;
    let s = 0;
    if (p.length >= 6) s++;
    if (p.length >= 10) s++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
    if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) s++;
    else if (/\d|[^A-Za-z0-9]/.test(p) && s < 4 && p.length >= 8) s = Math.max(s, 3);
    return Math.min(s, 4);
  }
  function updateStrength() {
    const v = regPass.value, s = v.length < 6 ? (v ? 1 : 0) : Math.max(scorePassword(v), 1);
    strength.dataset.s = s;
    strengthText.textContent = !v ? 'Use 6+ characters' : v.length < 6 ? 'Too short' : ['', 'Weak', 'Fair', 'Good', 'Strong'][s];
    updateMatch();
  }
  function updateMatch() {
    if (!regConfirm.value) { matchHint.hidden = true; return; }
    const ok = regConfirm.value === regPass.value;
    matchHint.hidden = false;
    matchHint.className = 'match-hint ' + (ok ? 'good' : 'bad');
    matchHint.textContent = ok ? 'Passwords match' : 'Passwords do not match yet';
  }
  regPass.addEventListener('input', updateStrength);
  regConfirm.addEventListener('input', updateMatch);

  /* messages */
  function clearMsgs() {
    $$('.form-msg', modal).forEach(m => { m.hidden = true; });
    $$('.input.invalid', modal).forEach(i => i.classList.remove('invalid'));
  }
  function showMsg(key, text) {
    const box = $('[data-msg="' + key + '"]', modal);
    if (!box) return;
    box.hidden = false;
    box.lastElementChild.textContent = text;
    box.style.animation = 'none'; void box.offsetWidth; box.style.animation = '';   // replay the shake
  }
  $$('.input', modal).forEach(i => i.addEventListener('input', () => i.classList.remove('invalid')));

  function mark(form, field) {
    const map = { username: 'username', password: 'password', confirm: 'confirm' };
    const el = form.elements[map[field]];
    if (el) { el.classList.add('invalid'); el.focus(); }
  }

  /* submit via fetch (server still works as a normal POST without JS) */
  async function submitForm(form, kind) {
    const btn = $('.auth-submit', form);
    clearMsgs();

    const u = form.elements.username.value.trim();
    const p = form.elements.password.value;
    if (kind === 'register') {
      let err = '', field = '';
      if (u.length < 3)                              { err = 'Username must be at least 3 characters.'; field = 'username'; }
      else if (p.length < 6)                         { err = 'Password must be at least 6 characters.'; field = 'password'; }
      else if (p !== form.elements.confirm.value)    { err = 'Passwords do not match.'; field = 'confirm'; }
      if (err) { showMsg('register', err); mark(form, field); return; }
    } else if (!u || !p) {
      showMsg('login', 'Enter your username and password.');
      mark(form, !u ? 'username' : 'password'); return;
    }

    const fd = new FormData(form);
    fd.set(kind, '1');
    btn.classList.add('loading'); btn.disabled = true;

    try {
      const res  = await fetch('homepage.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await res.json();

      if (data.ok && data.redirect) {
        toast('Welcome back — opening your vault…');
        setTimeout(() => { location.href = data.redirect; }, 450);
        return;                                      // keep the spinner until the page changes
      }
      if (data.ok && data.switch === 'login') {
        form.reset(); updateStrength(); matchHint.hidden = true;
        setTab('login', false);
        const lf = $('#loginForm');
        lf.elements.username.value = data.username || '';
        lf.elements.password.value = '';
        showMsg('login-ok', data.message);
        toast('Account created');
        setTimeout(() => lf.elements.password.focus(), 80);
      } else {
        showMsg(kind, data.error || 'Something went wrong. Please try again.');
        if (data.field) mark(form, data.field);
        if (kind === 'login') form.elements.password.value = '';
      }
    } catch (err) {
      showMsg(kind, 'Could not reach the server. Check your connection and try again.');
    }
    btn.classList.remove('loading'); btn.disabled = false;
  }
  $('#loginForm').addEventListener('submit',    e => { e.preventDefault(); submitForm(e.currentTarget, 'login'); });
  $('#registerForm').addEventListener('submit', e => { e.preventDefault(); submitForm(e.currentTarget, 'register'); });

  /* initial state (server may have opened it after a failed no-JS submit or via ?modal=) */
  setTab(modal.dataset.initial === 'register' ? 'register' : 'login', false);
  if (modal.classList.contains('open')) { document.body.style.overflow = 'hidden'; lastFocus = null; setTimeout(() => { const t = $('.input.invalid', panels[current]) || $('input', panels[current]); t && t.focus({ preventScroll: true }); }, 200); }
  if (location.hash === '#login' || location.hash === '#register') openAuth(location.hash.slice(1));
})();
