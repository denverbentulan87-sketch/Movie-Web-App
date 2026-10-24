<?php
include 'db.php';
session_start();

$loggedIn  = isset($_SESSION['user_id']);
$authModal = '';          // 'login' | 'register' | ''  -> which tab opens on load
$authError = '';
$authNote  = '';
$oldUser   = '';

if (!$loggedIn && isset($_SESSION['auth_flash'])) {
    $flash     = $_SESSION['auth_flash'];
    unset($_SESSION['auth_flash']);
    $authModal = $flash['modal'] ?? '';
    $authError = $flash['error'] ?? '';
    $oldUser   = $flash['user']  ?? '';
}

/* open the modal from links like homepage.php?modal=login */
if (!$loggedIn && $authModal === '') {
    if (isset($_GET['registered'])) { $authModal = 'login'; $authNote = 'Account created. Sign in to open your vault.'; }
    elseif (($_GET['modal'] ?? '') === 'login')    $authModal = 'login';
    elseif (($_GET['modal'] ?? '') === 'register') $authModal = 'register';
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault - Your Personal Film Universe</title>
<meta name="description" content="Track what you've watched, build the perfect watchlist and rate every film in one beautifully designed vault.">
<?php include 'includes/head.php'; ?>
<link rel="stylesheet" href="assets/css/landing.css">
<script>document.documentElement.classList.add('js');</script>
</head>
<body class="landing">
<?php include 'includes/icons.php'; ?>

<div class="scroll-progress" id="scrollProgress" aria-hidden="true"></div>
<a class="skip-link" href="#features">Skip to content</a>

<!-- ══════════ NAV ══════════ -->
<nav class="site-nav" id="siteNav" aria-label="Main">
  <a class="brand" href="#top" aria-label="Cinema Vault home">
    <svg class="brand-mark"><use href="#i-reel"/></svg>
    <span>CINEMA VAULT</span>
  </a>

  <ul class="nav-links" id="navLinks">
    <li><a href="#features">Features</a></li>
    <li><a href="#showcase">How It Works</a></li>
    <li><a href="#reviews">Reviews</a></li>
  </ul>

  <div class="nav-cta">
    <?php if ($loggedIn): ?>
      <a href="index.php" class="btn btn-gold"><svg class="i"><use href="#i-film"/></svg> Open my vault</a>
    <?php else: ?>
      <a href="homepage.php?modal=login" class="btn btn-ghost nav-signin" data-auth="login">Sign In</a>
      <a href="homepage.php?modal=register" class="btn btn-gold" data-auth="register">Join Free</a>
    <?php endif; ?>
    <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks">
      <svg class="i"><use href="#i-menu"/></svg>
    </button>
  </div>
</nav>

<!-- ══════════ HERO ══════════ -->
<header class="hero" id="top">
  <div class="hero-bg"></div>
  <div class="hero-glow"></div>

  <!-- big reel: turns with the scroll -->
  <svg class="hero-reel" id="heroReel" viewBox="0 0 400 400" aria-hidden="true">
    <circle cx="200" cy="200" r="190" fill="none" stroke="currentColor" stroke-width="1.5"/>
    <circle cx="200" cy="200" r="168" fill="none" stroke="currentColor" stroke-width=".8" stroke-dasharray="3 9"/>
    <g fill="none" stroke="currentColor" stroke-width="1.5">
      <circle cx="200" cy="82"  r="44"/><circle cx="312" cy="163" r="44"/>
      <circle cx="269" cy="295" r="44"/><circle cx="131" cy="295" r="44"/>
      <circle cx="88"  cy="163" r="44"/>
    </g>
    <circle cx="200" cy="200" r="34" fill="none" stroke="currentColor" stroke-width="1.5"/>
    <circle cx="200" cy="200" r="10" fill="currentColor"/>
  </svg>

  <div class="hero-inner">
    <div class="hero-content">
      <p class="hero-eyebrow">Your personal cinema universe</p>
      <h1 class="hero-title">Every film<br><em>you love, curated.</em></h1>
      <p class="hero-sub">Track what you've watched, build the perfect watchlist, and discover your next obsession — all in one beautifully designed vault.</p>
      <div class="hero-actions">
        <?php if ($loggedIn): ?>
          <a href="index.php" class="btn btn-gold btn-lg">Open my vault <svg class="i"><use href="#i-arrow-right"/></svg></a>
        <?php else: ?>
          <a href="homepage.php?modal=register" class="btn btn-gold btn-lg" data-auth="register">Start for Free <svg class="i"><use href="#i-arrow-right"/></svg></a>
          <a href="homepage.php?modal=login" class="btn btn-soft btn-lg" data-auth="login">Sign In</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- poster fan (tilts toward the pointer) -->
    <div class="hero-visual" id="heroVisual" aria-hidden="true">
     <div class="fan" id="fan">
      <div class="poster p1">
        <svg class="poster-art" aria-hidden="true"><use href="#art-midnight" width="100%" height="100%"/></svg>
        <span class="poster-rate">★ 4.8</span>
        <span class="poster-title">Midnight<br>Reel</span>
      </div>
      <div class="poster p2">
        <svg class="poster-art" aria-hidden="true"><use href="#art-frame" width="100%" height="100%"/></svg>
        <span class="poster-rate">★ 5.0</span>
        <span class="poster-title">The Last<br>Frame</span>
      </div>
      <div class="poster p3">
        <svg class="poster-art" aria-hidden="true"><use href="#art-golden" width="100%" height="100%"/></svg>
        <span class="poster-rate">★ 4.6</span>
        <span class="poster-title">Golden<br>Hour</span>
      </div>
     </div>
      <div class="chip chip-a"><svg class="i"><use href="#i-check-circle"/></svg> Watched</div>
      <div class="chip chip-b"><svg class="i"><use href="#i-clock"/></svg> Watching now</div>
    </div>
  </div>

  <div class="hero-stats">
    <div class="stat-item"><div class="stat-num"><span data-count="10" data-suffix="+">10+</span></div><div class="stat-label">Movies</div></div>
    <div class="stat-item"><div class="stat-num"><span data-count="12" data-suffix="+">12+</span></div><div class="stat-label">Members</div></div>
    <div class="stat-item"><div class="stat-num"><span data-count="4.9" data-decimals="1">4.9</span></div><div class="stat-label">Rating</div></div>
  </div>

  <a href="#features" class="scroll-cue" aria-label="Scroll to features"><span></span></a>
</header>

<!-- ══════════ MARQUEE ══════════ -->
<div class="marquee-section" aria-hidden="true">
  <div class="marquee-track">
    <?php for ($r = 0; $r < 4; $r++) foreach (['Watchlist','Favorites','Collections','Ratings','Discovery','Reviews'] as $w): ?>
      <div class="marquee-item"><?= $w ?><svg class="i i-fill"><use href="#i-star"/></svg></div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════ FEATURES ══════════ -->
<section class="features" id="features">
  <div class="section-head">
    <div class="section-label">Why Cinema Vault</div>
    <h2 class="section-title">Everything a cinephile needs</h2>
    <p class="section-sub">Built for movie lovers who take their film life seriously.</p>
  </div>

  <div class="features-grid">
    <?php
    $features = [
      ['film',     'Track Your Watches',   "Log every film you've seen with dates, ratings, and personal notes. Build a rich history of your cinema journey."],
      ['bookmark', 'Smart Watchlists',     'Create multiple curated watchlists — by genre, mood, director, or occasion. Never wonder what to watch next.'],
      ['star',     'Rate & Review',        'Give films your personal star rating and write reviews. Your own critical voice, preserved forever.'],
      ['compass',  'Discover New Films',   'Explore recommendations tailored to your taste. Find hidden gems and acclaimed classics you\'ve missed.'],
      ['chart',    'Viewing Stats',        'See beautiful breakdowns of your watching habits — favorite genres, directors, eras, and more.'],
      ['lock',     'Your Personal Vault',  'All your film data in one secure, private space. Export, organize, and own your cinematic history.'],
    ];
    foreach ($features as [$ic, $t, $d]): ?>
    <article class="feature-card" data-reveal>
      <div class="feature-icon"><svg class="i"><use href="#i-<?= $ic ?>"/></svg></div>
      <h3><?= $h($t) ?></h3>
      <p><?= $h($d) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<div class="sprockets" aria-hidden="true"></div>

<!-- ══════════ SHOWCASE (steps + live demo card) ══════════ -->
<section class="showcase" id="showcase">
  <div class="showcase-inner">
    <div class="showcase-text">
      <div class="section-label">How It Works</div>
      <h2 class="section-title">Start your collection in minutes</h2>
      <p>Cinema Vault is built around simplicity. No clutter, no noise — just a beautiful space for your films.</p>
      <ol class="steps">
        <li>Create your free account in seconds</li>
        <li>Search from a database of 500,000+ films</li>
        <li>Add movies to your watchlist or watched history</li>
        <li>Rate and review at your own pace</li>
        <li>Explore your personal stats and insights</li>
      </ol>
      <?php if ($loggedIn): ?>
        <a href="index.php" class="btn btn-gold btn-lg">Open my vault <svg class="i"><use href="#i-arrow-right"/></svg></a>
      <?php else: ?>
        <a href="homepage.php?modal=register" class="btn btn-gold btn-lg" data-auth="register">Get Started Free <svg class="i"><use href="#i-arrow-right"/></svg></a>
      <?php endif; ?>
    </div>

    <div class="showcase-visual">
      <div class="demo-back demo-back-1" aria-hidden="true"><svg class="poster-art" aria-hidden="true"><use href="#art-midnight" width="100%" height="100%"/></svg></div>
      <div class="demo-back demo-back-2" aria-hidden="true"><svg class="poster-art" aria-hidden="true"><use href="#art-golden" width="100%" height="100%"/></svg></div>

      <div class="demo-card" id="demoCard" data-status="watching">
        <div class="demo-poster">
          <svg class="demo-art" aria-hidden="true"><use href="#art-demo" width="100%" height="100%"/></svg>
          <span class="demo-badge" id="demoBadge">Watching</span>
        </div>
        <div class="demo-body">
          <p class="demo-hint">Try it — this card is live</p>
          <h3 class="demo-title">The Last Frame</h3>
          <p class="demo-meta">Drama · added today</p>

          <div class="demo-pills" role="group" aria-label="Status">
            <button type="button" class="pill" data-status="plan">Plan to Watch</button>
            <button type="button" class="pill is-on" data-status="watching">Watching</button>
            <button type="button" class="pill" data-status="watched">Watched</button>
          </div>

          <div class="demo-stars" id="demoStars" role="radiogroup" aria-label="Your rating">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <button type="button" class="dstar" role="radio" aria-checked="false" aria-label="<?= $s ?> star<?= $s>1?'s':'' ?>" data-v="<?= $s ?>"><svg class="i"><use href="#i-star"/></svg></button>
            <?php endfor; ?>
            <span class="demo-score" id="demoScore">Not rated</span>
          </div>
        </div>
      </div>
      <div class="badge-float"><svg class="i"><use href="#i-sparkle"/></svg> 247 films<br>tracked</div>
    </div>
  </div>
</section>

<!-- ══════════ TESTIMONIALS ══════════ -->
<section class="testimonials" id="reviews">
  <div class="section-head">
    <div class="section-label">From Our Members</div>
    <h2 class="section-title">Loved by film lovers</h2>
    <p class="section-sub">Join thousands of cinephiles who've made Cinema Vault their home base.</p>
  </div>

  <div class="testimonials-grid">
    <?php
    $reviews = [
      ['MR', 'Marco R.',  'Film Enthusiast', "Cinema Vault completely changed how I engage with films. My watchlist is finally under control and I love seeing my stats at the end of the year."],
      ['SL', 'Sofia L.',  'Cinephile',       "The design is stunning — it feels like the app was made by people who actually love movies. Clean, fast, and exactly what I needed."],
      ['JK', 'James K.',  'Movie Critic',    "I've tried every movie tracker out there. Cinema Vault is the only one that I actually kept using. The watchlist feature alone is worth it."],
    ];
    foreach ($reviews as [$in, $nm, $rl, $tx]): ?>
    <figure class="testi-card" data-reveal>
      <div class="stars" aria-label="5 out of 5 stars"><?php for($s=0;$s<5;$s++): ?><svg class="i i-fill"><use href="#i-star"/></svg><?php endfor; ?></div>
      <blockquote>“<?= $h($tx) ?>”</blockquote>
      <figcaption class="testi-author">
        <div class="avatar"><?= $in ?></div>
        <div><div class="author-name"><?= $h($nm) ?></div><div class="author-role"><?= $h($rl) ?></div></div>
      </figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
</section>

<!-- ══════════ CTA ══════════ -->
<section class="cta-section">
  <svg class="cta-reel" viewBox="0 0 64 64" aria-hidden="true"><use href="#i-reel"/></svg>
  <div class="section-label">Ready to Begin?</div>
  <h2 class="section-title">Your vault awaits.</h2>
  <p class="section-sub">Join for free and start building your personal cinema universe today.</p>
  <div class="cta-buttons">
    <?php if ($loggedIn): ?>
      <a href="index.php" class="btn btn-gold btn-lg">Open my vault <svg class="i"><use href="#i-arrow-right"/></svg></a>
    <?php else: ?>
      <a href="homepage.php?modal=register" class="btn btn-gold btn-lg" data-auth="register">Create Free Account <svg class="i"><use href="#i-arrow-right"/></svg></a>
      <a href="homepage.php?modal=login" class="btn btn-soft btn-lg" data-auth="login">Sign In</a>
    <?php endif; ?>
  </div>
</section>

<!-- ══════════ FOOTER ══════════ -->
<footer class="site-footer">
  <a class="brand" href="#top"><svg class="brand-mark"><use href="#i-reel"/></svg><span>CINEMA VAULT</span></a>
  <ul class="footer-links">
    <li><a href="#features">Features</a></li>
    <li><a href="#showcase">How It Works</a></li>
    <li><a href="#reviews">Reviews</a></li>
  </ul>
  <span class="footer-copy">© 2026 Cinema Vault. All rights reserved.</span>
</footer>

<button class="to-top" id="toTop" aria-label="Back to top"><svg class="i"><use href="#i-arrow-up"/></svg></button>

<?php if (!$loggedIn): ?>
<!-- ══════════ AUTH MODAL ══════════ -->
<div class="modal-backdrop auth-backdrop<?= $authModal ? ' open' : '' ?>" id="authModal" role="dialog" aria-modal="true" aria-labelledby="authHeading" data-initial="<?= $h($authModal) ?>">
  <div class="modal auth">

    <aside class="auth-side" aria-hidden="true">
      <svg class="auth-reel" viewBox="0 0 64 64"><use href="#i-reel"/></svg>
      <div class="auth-side-copy">
        <p class="auth-quote">Every film you love, in one place.</p>
        <ul>
          <li><svg class="i"><use href="#i-bookmark"/></svg> Build watchlists in seconds</li>
          <li><svg class="i"><use href="#i-star"/></svg> Rate and review every film</li>
          <li><svg class="i"><use href="#i-shield"/></svg> Private to you, always</li>
        </ul>
      </div>
      <div class="auth-strip"></div>
    </aside>

    <section class="auth-main">
      <button type="button" class="modal-close auth-close" data-auth-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>

      <h2 class="sr-only" id="authHeading">Sign in or create an account</h2>

      <div class="auth-tabs" role="tablist" aria-label="Account">
        <button type="button" role="tab" id="tab-login" aria-controls="panel-login" class="auth-tab" data-tab="login">Sign in</button>
        <button type="button" role="tab" id="tab-register" aria-controls="panel-register" class="auth-tab" data-tab="register">Create account</button>
        <span class="auth-tab-ink" aria-hidden="true"></span>
      </div>

      <!-- LOGIN -->
      <div class="auth-panel" id="panel-login" role="tabpanel" aria-labelledby="tab-login">
        <h3 class="auth-title">Welcome back</h3>
        <p class="auth-sub">Continue tracking your favorite movies and watchlists.</p>

        <div class="form-msg ok" data-msg="login-ok" role="status" <?= $authNote ? '' : 'hidden' ?>><svg class="i"><use href="#i-check-circle"/></svg><span><?= $h($authNote) ?></span></div>
        <div class="form-msg error" data-msg="login" role="alert" <?= ($authError && $authModal==='login') ? '' : 'hidden' ?>><svg class="i"><use href="#i-alert"/></svg><span><?= $authModal==='login' ? $h($authError) : '' ?></span></div>

        <form method="POST" action="login.php" id="loginForm" novalidate>
          <div class="field">
            <label for="loginUser">Username</label>
            <div class="input-wrap">
              <svg class="i i-lead"><use href="#i-user"/></svg>
              <input class="input has-lead" type="text" name="username" id="loginUser" autocomplete="username" required value="<?= $authModal==='login' ? $h($oldUser) : '' ?>" placeholder="Your username">
            </div>
          </div>
          <div class="field">
            <label for="loginPass">Password</label>
            <div class="input-wrap">
              <svg class="i i-lead"><use href="#i-lock"/></svg>
              <input class="input has-lead has-trail" type="password" name="password" id="loginPass" autocomplete="current-password" required placeholder="Your password">
              <button type="button" class="input-trail" data-toggle-pass="loginPass" aria-label="Show password"><svg class="i"><use href="#i-eye"/></svg></button>
            </div>
          </div>
          <button type="submit" name="login" value="1" class="btn btn-gold btn-lg btn-block auth-submit"><span class="btn-label">Sign In</span><span class="spinner" aria-hidden="true"></span></button>
        </form>

        <p class="auth-switch">New here? <button type="button" class="link-btn" data-tab="register">Create an account</button></p>
      </div>

      <!-- REGISTER -->
      <div class="auth-panel" id="panel-register" role="tabpanel" aria-labelledby="tab-register" hidden>
        <h3 class="auth-title">Create your account</h3>
        <p class="auth-sub">Build your personal movie collection and watchlist.</p>

        <div class="form-msg error" data-msg="register" role="alert" <?= ($authError && $authModal==='register') ? '' : 'hidden' ?>><svg class="i"><use href="#i-alert"/></svg><span><?= $authModal==='register' ? $h($authError) : '' ?></span></div>

        <form method="POST" action="register.php" id="registerForm" novalidate>
          <div class="field">
            <label for="regUser">Username</label>
            <div class="input-wrap">
              <svg class="i i-lead"><use href="#i-user"/></svg>
              <input class="input has-lead" type="text" name="username" id="regUser" autocomplete="username" required minlength="3" value="<?= $authModal==='register' ? $h($oldUser) : '' ?>" placeholder="At least 3 characters">
            </div>
          </div>
          <div class="field">
            <label for="regPass">Password</label>
            <div class="input-wrap">
              <svg class="i i-lead"><use href="#i-lock"/></svg>
              <input class="input has-lead has-trail" type="password" name="password" id="regPass" autocomplete="new-password" required minlength="6" placeholder="At least 6 characters">
              <button type="button" class="input-trail" data-toggle-pass="regPass" aria-label="Show password"><svg class="i"><use href="#i-eye"/></svg></button>
            </div>
            <div class="strength" id="strength" aria-live="polite"><span></span><span></span><span></span><span></span><em id="strengthText">Use 6+ characters</em></div>
          </div>
          <div class="field">
            <label for="regConfirm">Confirm password</label>
            <div class="input-wrap">
              <svg class="i i-lead"><use href="#i-lock"/></svg>
              <input class="input has-lead has-trail" type="password" name="confirm" id="regConfirm" autocomplete="new-password" required placeholder="Type it again">
              <button type="button" class="input-trail" data-toggle-pass="regConfirm" aria-label="Show password"><svg class="i"><use href="#i-eye"/></svg></button>
            </div>
            <p class="match-hint" id="matchHint" hidden></p>
          </div>
          <button type="submit" name="register" value="1" class="btn btn-gold btn-lg btn-block auth-submit"><span class="btn-label">Create Account</span><span class="spinner" aria-hidden="true"></span></button>
        </form>

        <p class="auth-switch">Already have an account? <button type="button" class="link-btn" data-tab="login">Sign in</button></p>
      </div>
    </section>
  </div>
</div>
<?php endif; ?>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="assets/js/landing.js"></script>
</body>
</html>
