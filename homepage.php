<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault — Your Personal Film Universe</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet">
<style>
:root {
  --gold: #d6a85f;
  --gold-soft: #f2cf8f;
  --gold-dim: rgba(214,168,95,.15);
  --bg: #07070c;
  --surface: #0d0e15;
  --surface2: #13141f;
  --border: rgba(255,255,255,.06);
  --text: #f5f5f5;
  --muted: #6b7280;
  --accent: #6366f1;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

html { scroll-behavior: smooth; }

body {
  font-family: 'Outfit', sans-serif;
  background: var(--bg);
  color: var(--text);
  overflow-x: hidden;
}

/* ── NOISE OVERLAY ── */
body::after {
  content: '';
  position: fixed;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='1'/%3E%3C/svg%3E");
  opacity: .025;
  pointer-events: none;
  z-index: 9999;
}

/* ── NAV ── */
nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 100;
  padding: 22px 6%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: linear-gradient(to bottom, rgba(7,7,12,.95), transparent);
  backdrop-filter: blur(2px);
}

.logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2rem;
  letter-spacing: 4px;
  color: var(--gold);
  text-decoration: none;
}

.nav-links {
  display: flex;
  gap: 36px;
  list-style: none;
}

.nav-links a {
  text-decoration: none;
  color: var(--muted);
  font-size: .88rem;
  font-weight: 500;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  transition: color .2s;
}

.nav-links a:hover { color: var(--gold); }

.nav-cta {
  display: flex;
  gap: 12px;
}

.btn-ghost {
  padding: 10px 22px;
  border: 1px solid rgba(214,168,95,.35);
  border-radius: 12px;
  background: transparent;
  color: var(--gold);
  font-family: 'Outfit', sans-serif;
  font-size: .85rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: .2s;
}

.btn-ghost:hover {
  background: var(--gold-dim);
}

.btn-gold {
  padding: 10px 22px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, var(--gold-soft), var(--gold));
  color: #111;
  font-family: 'Outfit', sans-serif;
  font-size: .85rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  transition: .2s;
}

.btn-gold:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 24px rgba(214,168,95,.3);
}

/* ── HERO ── */
.hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  overflow: hidden;
}

.hero-bg {
  position: absolute;
  inset: 0;
  background:
    url('https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=1925&auto=format&fit=crop') center/cover no-repeat;
  filter: brightness(.32) saturate(.7);
  transform: scale(1.04);
  animation: slowZoom 18s ease-in-out infinite alternate;
}

@keyframes slowZoom {
  from { transform: scale(1.04); }
  to   { transform: scale(1.12); }
}

.hero-glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse at 20% 50%, rgba(214,168,95,.18) 0%, transparent 50%),
    radial-gradient(ellipse at 80% 20%, rgba(99,102,241,.14) 0%, transparent 40%),
    linear-gradient(to bottom, transparent 60%, var(--bg) 100%);
}

.hero-content {
  position: relative;
  z-index: 2;
  padding: 0 6%;
  max-width: 760px;
}

.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--gold);
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: 3px;
  text-transform: uppercase;
  margin-bottom: 24px;
  opacity: 0;
  animation: fadeUp .7s .2s forwards;
}

.hero-eyebrow::before {
  content: '';
  width: 32px;
  height: 1px;
  background: var(--gold);
}

h1.hero-title {
  font-family: 'Playfair Display', serif;
  font-size: clamp(3.2rem, 7vw, 6.5rem);
  line-height: 1.02;
  font-weight: 700;
  margin-bottom: 28px;
  opacity: 0;
  animation: fadeUp .8s .35s forwards;
}

h1.hero-title em {
  font-style: italic;
  color: var(--gold-soft);
}

.hero-sub {
  font-size: 1.1rem;
  color: #9ca3af;
  line-height: 1.7;
  max-width: 500px;
  margin-bottom: 44px;
  font-weight: 300;
  opacity: 0;
  animation: fadeUp .8s .5s forwards;
}

.hero-actions {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  opacity: 0;
  animation: fadeUp .8s .65s forwards;
}

.btn-hero {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 16px 32px;
  border-radius: 16px;
  font-family: 'Outfit', sans-serif;
  font-size: .98rem;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: .25s ease;
}

.btn-hero-primary {
  background: linear-gradient(135deg, var(--gold-soft), var(--gold));
  color: #111;
}

.btn-hero-primary:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 40px rgba(214,168,95,.35);
}

.btn-hero-secondary {
  background: rgba(255,255,255,.06);
  color: white;
  border: 1px solid rgba(255,255,255,.1);
  backdrop-filter: blur(8px);
}

.btn-hero-secondary:hover {
  background: rgba(255,255,255,.1);
  transform: translateY(-2px);
}

.hero-stats {
  position: absolute;
  bottom: 60px;
  right: 6%;
  z-index: 2;
  display: flex;
  gap: 48px;
  opacity: 0;
  animation: fadeUp .8s .85s forwards;
}

.stat-item {
  text-align: right;
}

.stat-num {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.8rem;
  color: var(--gold);
  letter-spacing: 2px;
  line-height: 1;
}

.stat-label {
  font-size: .75rem;
  color: var(--muted);
  letter-spacing: 2px;
  text-transform: uppercase;
  margin-top: 4px;
}

@keyframes fadeUp {
  from { opacity:0; transform:translateY(24px); }
  to   { opacity:1; transform:translateY(0); }
}

/* ── MARQUEE ── */
.marquee-section {
  padding: 28px 0;
  background: linear-gradient(to right, var(--bg), var(--surface), var(--bg));
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  overflow: hidden;
}

.marquee-track {
  display: flex;
  gap: 60px;
  animation: marquee 28s linear infinite;
  width: max-content;
}

.marquee-item {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.1rem;
  letter-spacing: 4px;
  color: var(--muted);
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 60px;
}

.marquee-item::after {
  content: '✦';
  color: var(--gold);
  font-size: .7rem;
}

@keyframes marquee {
  from { transform: translateX(0); }
  to   { transform: translateX(-50%); }
}

/* ── FEATURES ── */
.features {
  padding: 120px 6%;
  position: relative;
}

.features::before {
  content: '';
  position: absolute;
  top: 0; left: 50%;
  transform: translateX(-50%);
  width: 1px;
  height: 80px;
  background: linear-gradient(to bottom, transparent, var(--gold));
}

.section-label {
  text-align: center;
  color: var(--gold);
  font-size: .75rem;
  font-weight: 600;
  letter-spacing: 4px;
  text-transform: uppercase;
  margin-bottom: 18px;
}

.section-title {
  text-align: center;
  font-family: 'Playfair Display', serif;
  font-size: clamp(2rem, 4vw, 3.2rem);
  font-weight: 700;
  margin-bottom: 16px;
  line-height: 1.2;
}

.section-sub {
  text-align: center;
  color: var(--muted);
  font-size: 1rem;
  max-width: 480px;
  margin: 0 auto 72px;
  line-height: 1.7;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  max-width: 1100px;
  margin: 0 auto;
}

.feature-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 40px 32px;
  position: relative;
  overflow: hidden;
  transition: .3s ease;
}

.feature-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(to right, transparent, var(--gold), transparent);
  opacity: 0;
  transition: .3s;
}

.feature-card:hover {
  transform: translateY(-6px);
  border-color: rgba(214,168,95,.2);
  box-shadow: 0 20px 50px rgba(0,0,0,.4);
}

.feature-card:hover::before { opacity: 1; }

.feature-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: var(--gold-dim);
  border: 1px solid rgba(214,168,95,.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  margin-bottom: 24px;
}

.feature-card h3 {
  font-size: 1.15rem;
  font-weight: 600;
  margin-bottom: 12px;
}

.feature-card p {
  color: var(--muted);
  font-size: .92rem;
  line-height: 1.7;
}

/* ── SHOWCASE ── */
.showcase {
  padding: 100px 6%;
  background: var(--surface);
  position: relative;
  overflow: hidden;
}

.showcase-inner {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: center;
}

.showcase-text .section-label,
.showcase-text .section-title { text-align: left; }

.showcase-text .section-title { margin-bottom: 20px; }

.showcase-text p {
  color: var(--muted);
  line-height: 1.8;
  margin-bottom: 18px;
  font-size: .96rem;
}

.feature-list {
  list-style: none;
  margin: 32px 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.feature-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: .92rem;
  color: #c4c4c4;
}

.feature-list li::before {
  content: '✦';
  color: var(--gold);
  font-size: .6rem;
  flex-shrink: 0;
}

.showcase-visual {
  position: relative;
}

.movie-stack {
  position: relative;
  height: 420px;
}

.movie-card-visual {
  position: absolute;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 30px 70px rgba(0,0,0,.6);
  transition: .4s ease;
}

.movie-card-visual img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.mv1 {
  width: 200px; height: 300px;
  top: 60px; left: 0;
  transform: rotate(-6deg);
  z-index: 1;
}

.mv2 {
  width: 210px; height: 315px;
  top: 30px; left: 100px;
  transform: rotate(-1deg);
  z-index: 3;
}

.mv3 {
  width: 190px; height: 285px;
  top: 80px; left: 200px;
  transform: rotate(5deg);
  z-index: 2;
}

.movie-stack:hover .mv1 { transform: rotate(-10deg) translateX(-15px); }
.movie-stack:hover .mv2 { transform: rotate(0deg) translateY(-12px); }
.movie-stack:hover .mv3 { transform: rotate(9deg) translateX(15px); }

.badge-float {
  position: absolute;
  bottom: 10px;
  right: -10px;
  background: linear-gradient(135deg, var(--gold-soft), var(--gold));
  color: #111;
  border-radius: 14px;
  padding: 14px 18px;
  font-weight: 700;
  font-size: .82rem;
  z-index: 10;
  box-shadow: 0 8px 24px rgba(214,168,95,.35);
  line-height: 1.4;
}

/* ── TESTIMONIALS ── */
.testimonials {
  padding: 120px 6%;
}

.testimonials-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  max-width: 1100px;
  margin: 0 auto;
}

.testi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 32px;
  transition: .3s;
}

.testi-card:hover {
  border-color: rgba(214,168,95,.2);
  transform: translateY(-4px);
}

.stars {
  color: var(--gold);
  font-size: .85rem;
  letter-spacing: 2px;
  margin-bottom: 16px;
}

.testi-card p {
  color: #c4c4c4;
  font-size: .92rem;
  line-height: 1.7;
  margin-bottom: 24px;
  font-style: italic;
}

.testi-author {
  display: flex;
  align-items: center;
  gap: 12px;
}

.avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--gold-dim);
  border: 1px solid rgba(214,168,95,.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: .85rem;
  color: var(--gold);
}

.author-name {
  font-weight: 600;
  font-size: .88rem;
}

.author-role {
  font-size: .78rem;
  color: var(--muted);
}

/* ── CTA SECTION ── */
.cta-section {
  padding: 120px 6%;
  position: relative;
  overflow: hidden;
  text-align: center;
}

.cta-section::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%,-50%);
  width: 700px;
  height: 700px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(214,168,95,.1) 0%, transparent 70%);
  pointer-events: none;
}

.cta-section .section-title {
  font-size: clamp(2.2rem, 5vw, 4rem);
  margin-bottom: 20px;
}

.cta-section .section-sub {
  margin-bottom: 44px;
}

.cta-buttons {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
}

/* ── FOOTER ── */
footer {
  padding: 60px 6% 40px;
  border-top: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
}

.footer-logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.8rem;
  letter-spacing: 4px;
  color: var(--gold);
  text-decoration: none;
}

.footer-links {
  display: flex;
  gap: 28px;
  list-style: none;
}

.footer-links a {
  text-decoration: none;
  color: var(--muted);
  font-size: .85rem;
  transition: color .2s;
}

.footer-links a:hover { color: var(--gold); }

.footer-copy {
  color: var(--muted);
  font-size: .8rem;
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
  .features-grid { grid-template-columns: 1fr 1fr; }
  .showcase-inner { grid-template-columns: 1fr; gap: 50px; }
  .testimonials-grid { grid-template-columns: 1fr 1fr; }
  .hero-stats { display: none; }
  .nav-links { display: none; }
}

@media (max-width: 600px) {
  .features-grid { grid-template-columns: 1fr; }
  .testimonials-grid { grid-template-columns: 1fr; }
  .nav-cta .btn-ghost { display: none; }
}
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <a class="logo" href="#">CINEMA VAULT</a>
  <ul class="nav-links">
    <li><a href="#features">Features</a></li>
    <li><a href="#showcase">How It Works</a></li>
    <li><a href="#reviews">Reviews</a></li>
  </ul>
  <div class="nav-cta">
    <a href="login.php" class="btn-ghost">Sign In</a>
    <a href="register.php" class="btn-gold">Join Free</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-glow"></div>
  <div class="hero-content">
    <div class="hero-eyebrow">Your personal cinema universe</div>
    <h1 class="hero-title">Every film you<br>love, <em>curated.</em></h1>
    <p class="hero-sub">Track what you've watched, build the perfect watchlist, and discover your next obsession — all in one beautifully designed vault.</p>
    <div class="hero-actions">
      <a href="register.php" class="btn-hero btn-hero-primary">
        <span>Start for Free</span>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <a href="login.php" class="btn-hero btn-hero-secondary">
        <span>Sign In</span>
      </a>
    </div>
  </div>
  <div class="hero-stats">
    <div class="stat-item">
      <div class="stat-num">500K+</div>
      <div class="stat-label">Movies</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">12K+</div>
      <div class="stat-label">Members</div>
    </div>
    <div class="stat-item">
      <div class="stat-num">4.9</div>
      <div class="stat-label">Rating</div>
    </div>
  </div>
</section>

<!-- MARQUEE -->
<div class="marquee-section">
  <div class="marquee-track">
    <div class="marquee-item">Watchlist</div>
    <div class="marquee-item">Favorites</div>
    <div class="marquee-item">Collections</div>
    <div class="marquee-item">Ratings</div>
    <div class="marquee-item">Discovery</div>
    <div class="marquee-item">Reviews</div>
    <div class="marquee-item">Watchlist</div>
    <div class="marquee-item">Favorites</div>
    <div class="marquee-item">Collections</div>
    <div class="marquee-item">Ratings</div>
    <div class="marquee-item">Discovery</div>
    <div class="marquee-item">Reviews</div>
    <!-- Duplicate for seamless loop -->
    <div class="marquee-item">Watchlist</div>
    <div class="marquee-item">Favorites</div>
    <div class="marquee-item">Collections</div>
    <div class="marquee-item">Ratings</div>
    <div class="marquee-item">Discovery</div>
    <div class="marquee-item">Reviews</div>
    <div class="marquee-item">Watchlist</div>
    <div class="marquee-item">Favorites</div>
    <div class="marquee-item">Collections</div>
    <div class="marquee-item">Ratings</div>
    <div class="marquee-item">Discovery</div>
    <div class="marquee-item">Reviews</div>
  </div>
</div>

<!-- FEATURES -->
<section class="features" id="features">
  <div class="section-label">Why Cinema Vault</div>
  <h2 class="section-title">Everything a cinephile needs</h2>
  <p class="section-sub">Built for movie lovers who take their film life seriously.</p>

  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon">🎬</div>
      <h3>Track Your Watches</h3>
      <p>Log every film you've seen with dates, ratings, and personal notes. Build a rich history of your cinema journey.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📋</div>
      <h3>Smart Watchlists</h3>
      <p>Create multiple curated watchlists — by genre, mood, director, or occasion. Never wonder what to watch next.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">⭐</div>
      <h3>Rate & Review</h3>
      <p>Give films your personal star rating and write reviews. Your own critical voice, preserved forever.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🔍</div>
      <h3>Discover New Films</h3>
      <p>Explore recommendations tailored to your taste. Find hidden gems and acclaimed classics you've missed.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">📊</div>
      <h3>Viewing Stats</h3>
      <p>See beautiful breakdowns of your watching habits — favorite genres, directors, eras, and more.</p>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🗄️</div>
      <h3>Your Personal Vault</h3>
      <p>All your film data in one secure, private space. Export, organize, and own your cinematic history.</p>
    </div>
  </div>
</section>

<!-- SHOWCASE -->
<section class="showcase" id="showcase">
  <div class="showcase-inner">
    <div class="showcase-text">
      <div class="section-label">How It Works</div>
      <h2 class="section-title">Start your collection in minutes</h2>
      <p>Cinema Vault is built around simplicity. No clutter, no noise — just a beautiful space for your films.</p>
      <ul class="feature-list">
        <li>Create your free account in seconds</li>
        <li>Search from a database of 500,000+ films</li>
        <li>Add movies to your watchlist or watched history</li>
        <li>Rate and review at your own pace</li>
        <li>Explore your personal stats and insights</li>
      </ul>
      <a href="register.php" class="btn-hero btn-hero-primary" style="display:inline-flex; margin-top:8px;">
        <span>Get Started Free</span>
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
    <div class="showcase-visual">
      <div class="movie-stack">
        <div class="movie-card-visual mv1">
          <img src="https://images.unsplash.com/photo-1542204165-65bf26472b9b?q=80&w=400&auto=format&fit=crop" alt="Movie">
        </div>
        <div class="movie-card-visual mv2">
          <img src="https://images.unsplash.com/photo-1485846234645-a62644f84728?q=80&w=400&auto=format&fit=crop" alt="Movie">
        </div>
        <div class="movie-card-visual mv3">
          <img src="https://images.unsplash.com/photo-1478720568477-152d9b164e26?q=80&w=400&auto=format&fit=crop" alt="Movie">
        </div>
        <div class="badge-float">
          ✦ 247 films<br>tracked
        </div>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials" id="reviews">
  <div class="section-label">From Our Members</div>
  <h2 class="section-title">Loved by film lovers</h2>
  <p class="section-sub">Join thousands of cinephiles who've made Cinema Vault their home base.</p>

  <div class="testimonials-grid">
    <div class="testi-card">
      <div class="stars">★★★★★</div>
      <p>"Cinema Vault completely changed how I engage with films. My watchlist is finally under control and I love seeing my stats at the end of the year."</p>
      <div class="testi-author">
        <div class="avatar">MR</div>
        <div>
          <div class="author-name">Marco R.</div>
          <div class="author-role">Film Enthusiast</div>
        </div>
      </div>
    </div>
    <div class="testi-card">
      <div class="stars">★★★★★</div>
      <p>"The design is stunning — it feels like the app was made by people who actually love movies. Clean, fast, and exactly what I needed."</p>
      <div class="testi-author">
        <div class="avatar">SL</div>
        <div>
          <div class="author-name">Sofia L.</div>
          <div class="author-role">Cinephile</div>
        </div>
      </div>
    </div>
    <div class="testi-card">
      <div class="stars">★★★★★</div>
      <p>"I've tried every movie tracker out there. Cinema Vault is the only one that I actually kept using. The watchlist feature alone is worth it."</p>
      <div class="testi-author">
        <div class="avatar">JK</div>
        <div>
          <div class="author-name">James K.</div>
          <div class="author-role">Movie Critic</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="section-label">Ready to Begin?</div>
  <h2 class="section-title">Your vault awaits.</h2>
  <p class="section-sub">Join for free and start building your personal cinema universe today.</p>
  <div class="cta-buttons">
    <a href="register.php" class="btn-hero btn-hero-primary">
      <span>Create Free Account</span>
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
    <a href="login.php" class="btn-hero btn-hero-secondary">Sign In</a>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <a class="footer-logo" href="#">CINEMA VAULT</a>
  <ul class="footer-links">
    <li><a href="#">Features</a></li>
    <li><a href="#">Privacy</a></li>
    <li><a href="#">Terms</a></li>
    <li><a href="#">Contact</a></li>
  </ul>
  <span class="footer-copy">© 2026 Cinema Vault. All rights reserved.</span>
</footer>

</body>
</html>