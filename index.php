<?php
/**
 * Cinema Vault — dashboard (the vault).
 * Read-only page: every change goes through POST + CSRF: create.php, edit.php, delete.php, delete_all.php, actions.php.
 */
require __DIR__ . '/includes/movies.php';
require __DIR__ . '/includes/recommend.php';

if (!cv_user_id()) cv_redirect('homepage.php');
$user_id  = cv_user_id();
$username = (string)($_SESSION['username'] ?? '');
$initial  = mb_strtoupper(mb_substr($username, 0, 1));

/* ── filters (everything validated in cv_parse_filters) ── */
$f = cv_parse_filters($_GET);
$allCollections = cv_user_collections($user_id);
$activeCollection = null;
foreach ($allCollections as $c) if ($c['id'] === $f['collection']) $activeCollection = $c;
if (!$activeCollection) $f['collection'] = 0;          // unknown / someone else's id: ignore

$allTags = cv_user_tags($user_id);
$movies  = cv_fetch_movies($user_id, $f);
$all     = cv_all_movies($user_id);
$total   = count($movies);

/* ── overview numbers (stay stable while filters change) ── */
$ov_total = count($all); $ov_watched = $ov_watching = $ov_plan = $ov_rated = $ov_sum = $ov_fav = 0;
$genre_counts = []; $genres = []; $years = [];
foreach ($all as $r) {
    if ($r['status'] === 'Watched') $ov_watched++; elseif ($r['status'] === 'Watching') $ov_watching++; else $ov_plan++;
    if ($r['rating'] > 0) { $ov_rated++; $ov_sum += $r['rating']; }
    if ($r['is_favorite']) $ov_fav++;
    if (($r['genre'] ?? '') !== '') { $genre_counts[$r['genre']] = ($genre_counts[$r['genre']] ?? 0) + 1; $genres[$r['genre']] = true; }
    if ($r['release_year']) $years[$r['release_year']] = true;
}
$ov_avg = $ov_rated > 0 ? round($ov_sum / $ov_rated, 1) : 0;
$ov_pct = $ov_total > 0 ? (int)round($ov_watched / $ov_total * 100) : 0;
arsort($genre_counts);
$top_genres = array_slice($genre_counts, 0, 5, true);
$top_max = $top_genres ? max($top_genres) : 1;
ksort($genres); krsort($years);

$activeCount = cv_active_filter_count($f);
$advCount = $activeCount - ($f['search'] !== '' ? 1 : 0) - ($f['status'] !== '' ? 1 : 0) - ($f['genre'] !== '' ? 1 : 0) - ($f['favorite'] === '1' ? 1 : 0) - ($f['collection'] ? 1 : 0);

/** Link to this page with some filters changed. */
function qs(array $over = []): string {
    global $f;
    $q = array_merge($f, $over);
    if ($q['sort'] === 'newest') $q['sort'] = '';
    $q = array_filter($q, fn($v) => $v !== '' && $v !== 0 && $v !== [] && $v !== null);
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

/* ── Up next: the Plan to Watch film you care about most (priority, then the one waiting longest) ── */
$upNext = null;
$prioRank = ['High' => 0, 'Normal' => 1, 'Low' => 2];
foreach ($all as $r) {
    if ($r['status'] !== 'Plan to Watch') continue;
    if (!$upNext) { $upNext = $r; continue; }
    $a = $prioRank[$r['priority']] ?? 1; $b = $prioRank[$upNext['priority']] ?? 1;
    if ($a < $b || ($a === $b && (string)$r['date_added'] < (string)$upNext['date_added'])) $upNext = $r;
}

/* ── Picked For You (only when not filtering) ── */
$rec = ['enough' => false, 'because' => [], 'picks' => []];
if ($activeCount === 0) $rec = cv_recommendations($all, cv_tag_map($user_id), cv_collection_map($user_id), 6);

$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
function statusClass($s) { return ['Watching' => 'watching', 'Watched' => 'watched'][$s] ?? 'plan'; }

function renderCard(array $movie): string {
    $cls = statusClass($movie['status']);
    $cov = $movie['cover_url'] ?? '';
    $rt  = (int)$movie['rating'];
    $hay = mb_strtolower(implode(' ', array_merge(
        [$movie['movie_title'], $movie['genre'] ?? ''],
        array_column($movie['tags'], 'name'), array_column($movie['collections'], 'name'),
        [$movie['notes_search'] ?? ''])));
    $payload = $movie; unset($payload['notes_search']);
    ob_start(); ?>
<article class="movie-card" tabindex="0" role="button" aria-label="<?= h($movie['movie_title']) ?> — details"
         data-id="<?= (int)$movie['watchlist_id'] ?>"
         data-title="<?= h($hay) ?>"
         data-movie="<?= h(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>">
  <div class="poster-frame">
    <?php if ($cov): ?>
      <img src="<?= h($cov) ?>" alt="" class="movie-poster" loading="lazy" data-hide-on-error>
    <?php endif; ?>
    <div class="movie-poster-placeholder"><svg class="i"><use href="#i-film"/></svg><span><?= h($movie['movie_title']) ?></span></div>
  </div>
  <span class="card-status-badge badge-<?= $cls ?>"><?= h($movie['status']) ?></span>
  <?php if ($rt): ?><span class="card-rating"><svg class="i i-fill"><use href="#i-star"/></svg><?= $rt ?></span><?php endif; ?>
  <span class="card-fav" <?= $movie['is_favorite'] ? '' : 'hidden' ?> title="Favorite"><svg class="i i-fill"><use href="#i-heart"/></svg><span class="sr-only">Favorite</span></span>
  <?php if ($movie['watch_count'] > 1): ?><span class="card-watches" title="Watched <?= (int)$movie['watch_count'] ?> times">×<?= (int)$movie['watch_count'] ?></span><?php endif; ?>
  <div class="card-overlay">
    <div class="card-title"><?= h($movie['movie_title']) ?></div>
    <div class="card-genre"><?= h($movie['genre']) ?></div>
    <div class="card-actions">
      <button type="button" class="card-btn card-btn-info" data-act="info"><svg class="i"><use href="#i-play"/></svg>Info</button>
      <?php if ($movie['status'] === 'Plan to Watch'): ?>
      <button type="button" class="card-btn" data-act="start" aria-label="Start watching" title="Start watching"><svg class="i"><use href="#i-clock"/></svg></button>
      <?php endif; ?>
      <button type="button" class="card-btn card-btn-fav<?= $movie['is_favorite'] ? ' is-on' : '' ?>" data-act="fav" aria-pressed="<?= $movie['is_favorite'] ? 'true' : 'false' ?>" aria-label="Favorite" title="Favorite"><svg class="i"><use href="#i-heart"/></svg></button>
      <button type="button" class="card-btn card-btn-edit" data-act="edit" aria-label="Edit"><svg class="i"><use href="#i-edit"/></svg></button>
      <button type="button" class="card-btn card-btn-del" data-act="del" aria-label="Remove"><svg class="i"><use href="#i-trash"/></svg></button>
    </div>
  </div>
</article>
<?php return ob_get_clean(); }

$genre_options = CV_GENRES;
/* Plan to Watch is split into three priority sections. */
$row_defs = [
  'w'  => ['Currently watching', 'clock',        'watching', fn($m) => $m['status'] === 'Watching'],
  'd'  => ['Watched',            'check-circle', 'watched',  fn($m) => $m['status'] === 'Watched'],
  'ph' => ['Must watch',         'flame',        'high',     fn($m) => $m['status'] === 'Plan to Watch' && $m['priority'] === 'High',   'High priority'],
  'pn' => ['Next',               'pin',          'normal',   fn($m) => $m['status'] === 'Plan to Watch' && $m['priority'] === 'Normal', 'Normal priority'],
  'pl' => ['Someday',            'hourglass',    'low',      fn($m) => $m['status'] === 'Plan to Watch' && $m['priority'] === 'Low',    'Low priority'],
];
$selectedTags = array_flip($f['tags']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault - My Watchlist</title>
<?php include 'includes/head.php'; ?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/vault.css">
</head>
<body class="dash">
<?php include 'includes/icons.php'; ?>
<?php $active = 'vault'; $showViewToggle = true; include 'includes/app_nav.php'; ?>

<main class="dash-main">
<?php include 'includes/subnav.php'; ?>

<!-- ══════════ HEADER + OVERVIEW ══════════ -->
<header class="dash-hero">
  <div class="dash-hero-text">
    <p class="dash-kicker" id="greeting">Welcome back</p>
    <h1 class="dash-title"><?= $activeCollection ? h($activeCollection['name']) : h($username) ?></h1>
    <p class="dash-sub">
      <?php if ($activeCollection): ?>
        <?= $activeCollection['description'] !== '' ? h($activeCollection['description']) : 'A collection in your vault.' ?>
        · <?= $activeCollection['count'] ?> <?= $activeCollection['count'] === 1 ? 'film' : 'films' ?>
      <?php elseif ($ov_total === 0): ?>
        Nothing here yet. Add the first film you want to remember.
      <?php else: ?>
        <?= $ov_total ?> <?= $ov_total === 1 ? 'film' : 'films' ?> in your vault<?= $ov_watching ? ', ' . $ov_watching . ' in progress' : '' ?><?= $ov_fav ? ', ' . $ov_fav . ' favorite' . ($ov_fav === 1 ? '' : 's') : '' ?>.
      <?php endif; ?>
    </p>
  </div>
  <?php if ($upNext): ?>
  <div class="hero-next">
    <button type="button" class="next-card" data-pick="<?= (int)$upNext['watchlist_id'] ?>" aria-label="Up next: <?= h($upNext['movie_title']) ?>. Open details">
      <span class="next-poster">
        <?php if ($upNext['cover_url']): ?><img src="<?= h($upNext['cover_url']) ?>" alt="" loading="lazy" data-hide-on-error><?php endif; ?>
        <svg class="i"><use href="#i-film"/></svg>
      </span>
      <span class="next-info">
        <small>Up next</small>
        <strong><?= h($upNext['movie_title']) ?></strong>
        <em><?= ['High' => 'Must watch', 'Normal' => 'Next', 'Low' => 'Someday'][$upNext['priority']] ?? 'Plan to watch' ?></em>
      </span>
      <svg class="i next-go" aria-hidden="true"><use href="#i-chev-right"/></svg>
    </button>
  </div>
  <?php endif; ?>
</header>

<?php if ($activeCollection): ?>
<div class="coll-banner">
  <a class="btn btn-soft" href="collections.php"><svg class="i"><use href="#i-chev-left"/></svg> All collections</a>
  <button type="button" class="btn btn-ghost" id="collEditBtn" data-id="<?= $activeCollection['id'] ?>" data-name="<?= h($activeCollection['name']) ?>" data-description="<?= h($activeCollection['description']) ?>"><svg class="i"><use href="#i-edit"/></svg> Rename / describe</button>
  <button type="button" class="btn btn-danger" id="collDelBtn" data-id="<?= $activeCollection['id'] ?>" data-name="<?= h($activeCollection['name']) ?>"><svg class="i"><use href="#i-trash"/></svg> Delete collection</button>
  <form method="POST" action="actions.php" class="coll-add">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="collection_add_movie">
    <input type="hidden" name="collection_id" value="<?= $activeCollection['id'] ?>">
    <input type="hidden" name="return_to" value="<?= h(qs()) ?>">
    <label class="sr-only" for="collAddMovie">Add a movie to this collection</label>
    <select class="select" id="collAddMovie" name="movie_id" required>
      <option value="">Add a movie…</option>
      <?php $already = array_flip(array_column(db_all('SELECT movie_id FROM collection_movies WHERE collection_id = ? AND user_id = ?', 'ii', [$activeCollection['id'], $user_id]), 'movie_id'));
            foreach ($all as $m): if (isset($already[$m['watchlist_id']])) continue; ?>
        <option value="<?= (int)$m['watchlist_id'] ?>"><?= h($m['movie_title']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-gold"><svg class="i"><use href="#i-plus"/></svg> Add</button>
  </form>
</div>
<?php endif; ?>

<section class="overview" aria-label="Your stats">
  <div class="stats">
    <a class="stat-card" href="index.php">
      <span class="stat-icon"><svg class="i"><use href="#i-film"/></svg></span>
      <span class="stat-label">Total films</span>
      <span class="stat-value" data-count="<?= $ov_total ?>"><?= $ov_total ?></span>
      <span class="stat-sub">In your vault</span>
    </a>
    <a class="stat-card" href="<?= h(qs(['status' => 'Watched', 'collection' => 0])) ?>">
      <span class="stat-icon"><svg class="i"><use href="#i-check-circle"/></svg></span>
      <span class="stat-label">Watched</span>
      <span class="stat-value" data-count="<?= $ov_watched ?>"><?= $ov_watched ?></span>
      <span class="stat-sub"><?= $ov_pct ?>% complete</span>
      <span class="stat-bar" aria-hidden="true"><i style="--w:<?= $ov_pct ?>%"></i></span>
    </a>
    <a class="stat-card" href="<?= h(qs(['status' => 'Watching', 'collection' => 0])) ?>">
      <span class="stat-icon"><svg class="i"><use href="#i-clock"/></svg></span>
      <span class="stat-label">Watching now</span>
      <span class="stat-value" data-count="<?= $ov_watching ?>"><?= $ov_watching ?></span>
      <span class="stat-sub">In progress</span>
    </a>
    <a class="stat-card" href="<?= h(qs(['favorite' => '1', 'status' => '', 'collection' => 0])) ?>">
      <span class="stat-icon"><svg class="i"><use href="#i-heart"/></svg></span>
      <span class="stat-label">Favorites</span>
      <span class="stat-value" data-count="<?= $ov_fav ?>"><?= $ov_fav ?></span>
      <span class="stat-sub">Any status</span>
    </a>
    <div class="stat-card stat-static">
      <span class="stat-icon"><svg class="i"><use href="#i-star"/></svg></span>
      <span class="stat-label">Avg rating</span>
      <span class="stat-value"><?= $ov_avg ?: '—' ?></span>
      <span class="stat-sub stat-stars" aria-label="<?= $ov_rated ?> rated films">
        <?php for ($s = 1; $s <= 5; $s++): ?><svg class="i i-fill<?= $s <= round($ov_avg) ? ' on' : '' ?>"><use href="#i-star"/></svg><?php endfor; ?>
        <em><?= $ov_rated ?> rated</em>
      </span>
    </div>
  </div>

  <aside class="taste" aria-label="Top genres">
    <h2 class="panel-title">Your taste</h2>
    <?php if (!$top_genres): ?>
      <p class="taste-empty">Add a few films and your favorite genres will show up here.</p>
    <?php else: ?>
      <ul class="taste-list">
        <?php foreach ($top_genres as $g => $n): ?>
        <li>
          <a href="<?= h(qs(['genre' => $g])) ?>" class="taste-row<?= $f['genre'] === $g ? ' is-on' : '' ?>">
            <span class="taste-name"><?= h($g) ?></span>
            <span class="taste-bar"><i style="--w:<?= round($n / $top_max * 100) ?>%"></i></span>
            <span class="taste-n"><?= $n ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
</section>

<?php if ($rec['enough'] && $rec['picks']): ?>
<!-- ══════════ PICKED FOR YOU ══════════ -->
<section class="picked" aria-labelledby="pickedH">
  <div class="picked-head">
    <h2 class="section-title" id="pickedH">Picked for you</h2>
    <p class="picked-because">
      Because you rated
      <?php foreach ($rec['because'] as $i => $b): ?><?= $i ? ', ' : ' ' ?><strong><?= h($b['title']) ?></strong><?= $b['rating'] ? ' <span class="picked-stars" aria-label="' . $b['rating'] . ' out of 5">' . str_repeat('★', $b['rating']) . '</span>' : ' <span class="picked-stars">♥</span>' ?><?php endforeach; ?>
    </p>
  </div>
  <ul class="picked-list">
    <?php foreach ($rec['picks'] as $p): $pm = $p['movie']; ?>
    <li>
      <button type="button" class="picked-item" data-pick="<?= (int)$pm['watchlist_id'] ?>">
        <span class="picked-poster">
          <?php if ($pm['cover_url']): ?><img src="<?= h($pm['cover_url']) ?>" alt="" loading="lazy" data-hide-on-error><?php endif; ?>
          <svg class="i"><use href="#i-film"/></svg>
        </span>
        <span class="picked-info">
          <strong><?= h($pm['movie_title']) ?></strong>
          <small><?= h($pm['genre']) ?></small>
          <?php foreach ($p['reasons'] as $r): ?><em><?= h($r) ?></em><?php endforeach; ?>
        </span>
      </button>
    </li>
    <?php endforeach; ?>
  </ul>
  <p class="picked-note">Suggested from your own ratings, favorites, genres, tags and collections. Not AI, and only from films already in your Plan to Watch list.</p>
</section>
<?php endif; ?>

<div class="sprockets" aria-hidden="true"></div>

<!-- ══════════ FILTERS ══════════ -->
<form method="GET" action="index.php" class="filter-bar" id="filterForm" role="search">
  <div class="search-wrap input-wrap">
    <svg class="i i-lead"><use href="#i-search"/></svg>
    <input class="input has-lead has-trail" type="search" id="searchInput" name="search" placeholder="Search titles, genres, tags, collections, notes" value="<?= h($f['search']) ?>" autocomplete="off" aria-label="Search your vault">
    <button type="button" class="input-trail search-clear" id="searchClear" aria-label="Clear search" <?= $f['search'] === '' ? 'hidden' : '' ?>><svg class="i"><use href="#i-x"/></svg></button>
  </div>

  <div class="chips" role="group" aria-label="Status">
    <a class="chip<?= $f['status'] === '' && $f['favorite'] === '' ? ' is-on' : '' ?>" href="<?= h(qs(['status' => '', 'favorite' => ''])) ?>">All <b><?= $ov_total ?></b></a>
    <a class="chip<?= $f['status'] === 'Watching' ? ' is-on' : '' ?>" href="<?= h(qs(['status' => 'Watching'])) ?>">Watching <b><?= $ov_watching ?></b></a>
    <a class="chip<?= $f['status'] === 'Watched' ? ' is-on' : '' ?>" href="<?= h(qs(['status' => 'Watched'])) ?>">Watched <b><?= $ov_watched ?></b></a>
    <a class="chip<?= $f['status'] === 'Plan to Watch' ? ' is-on' : '' ?>" href="<?= h(qs(['status' => 'Plan to Watch'])) ?>">Plan to watch <b><?= $ov_plan ?></b></a>
    <a class="chip chip-fav<?= $f['favorite'] === '1' ? ' is-on' : '' ?>" href="<?= h(qs(['favorite' => $f['favorite'] === '1' ? '' : '1'])) ?>" aria-pressed="<?= $f['favorite'] === '1' ? 'true' : 'false' ?>"><svg class="i i-fill"><use href="#i-heart"/></svg> Favorites <b><?= $ov_fav ?></b></a>
  </div>

  <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= h($f['status']) ?>"><?php endif; ?>
  <?php if ($f['favorite'] === '1'): ?><input type="hidden" name="favorite" value="1"><?php endif; ?>
  <?php if ($f['collection']): ?><input type="hidden" name="collection" value="<?= (int)$f['collection'] ?>"><?php endif; ?>

  <div class="filter-selects">
    <select class="select" name="genre" data-autosubmit aria-label="Genre">
      <option value="">All genres</option>
      <?php foreach (array_keys($genres) as $g): ?><option value="<?= h($g) ?>" <?= $f['genre'] === $g ? 'selected' : '' ?>><?= h($g) ?></option><?php endforeach; ?>
    </select>
    <select class="select" name="sort" data-autosubmit aria-label="Sort order">
      <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first', 'rating' => 'Top rated', 'title' => 'Title A–Z', 'watched' => 'Recently watched', 'most_watched' => 'Most watched', 'year' => 'Release year'] as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-soft filter-toggle" id="advToggle" aria-expanded="<?= $advCount ? 'true' : 'false' ?>" aria-controls="advPanel"><svg class="i"><use href="#i-filter"/></svg> Filters<?= $advCount ? ' <b class="filter-count">' . $advCount . '</b>' : '' ?></button>
  </div>

  <div class="adv-panel" id="advPanel" <?= $advCount ? '' : 'hidden' ?>>
    <div class="adv-grid">
      <div class="field"><label for="fPriority">Priority <small>Plan to Watch</small></label>
        <select id="fPriority" name="priority"><option value="">Any</option>
          <?php foreach (['High' => 'Must watch (High)', 'Normal' => 'Next (Normal)', 'Low' => 'Someday (Low)'] as $k => $lbl): ?><option value="<?= $k ?>" <?= $f['priority'] === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="fRating">Rating</label>
        <select id="fRating" name="rating_min"><option value="">Any</option>
          <?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>" <?= $f['rating_min'] === $r ? 'selected' : '' ?>><?= $r ?>★ and up</option><?php endfor; ?></select></div>
      <div class="field"><label for="fYear">Release year</label>
        <select id="fYear" name="year"><option value="">Any</option>
          <?php foreach (array_keys($years) as $y): ?><option <?= $f['year'] === $y ? 'selected' : '' ?>><?= (int)$y ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="fWatches">Times watched</label>
        <select id="fWatches" name="watches"><option value="">Any</option>
          <?php foreach (['never' => 'Never watched', '1' => '1 or more', '2' => 'Rewatched (2+)', '3' => '3 or more', '5' => '5 or more'] as $k => $lbl): ?><option value="<?= $k ?>" <?= $f['watches'] === (string)$k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="fCollection">Collection</label>
        <select id="fCollection" name="collection"><option value="">Any</option>
          <?php foreach ($allCollections as $c): ?><option value="<?= $c['id'] ?>" <?= $f['collection'] === $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?> (<?= $c['count'] ?>)</option><?php endforeach; ?></select></div>
    </div>
    <fieldset class="adv-tags">
      <legend>Tags <small>films must have all selected</small></legend>
      <?php if (!$allTags): ?><p class="adv-empty">No tags yet. Add one from any film's details.</p><?php endif; ?>
      <div class="tag-picks">
        <?php foreach ($allTags as $t): ?>
        <label class="tag-pick"><input type="checkbox" name="tags[]" value="<?= $t['id'] ?>" <?= isset($selectedTags[$t['id']]) ? 'checked' : '' ?>><span><?= h($t['name']) ?> <b><?= $t['count'] ?></b></span></label>
        <?php endforeach; ?>
      </div>
      <button type="button" class="link-btn" data-open="tagsModal"><svg class="i"><use href="#i-tag"/></svg> Manage tags</button>
    </fieldset>
    <div class="adv-actions">
      <button type="submit" class="btn btn-gold"><svg class="i"><use href="#i-check"/></svg> Apply filters</button>
      <?php if ($activeCount): ?><a class="btn btn-ghost" href="<?= h($f['collection'] ? qs(['search'=>'','status'=>'','genre'=>'','favorite'=>'','priority'=>'','rating_min'=>0,'year'=>0,'tags'=>[],'watches'=>'']) : 'index.php') ?>">Clear filters</a><?php endif; ?>
    </div>
  </div>

  <p class="found" id="foundCount" aria-live="polite">
    <b id="foundNum"><?= $total ?></b> <span id="foundWord"><?= $total === 1 ? 'film' : 'films' ?></span> found
    <?php if ($activeCount): ?> · <a class="clear-link" href="<?= h($f['collection'] ? qs(['search'=>'','status'=>'','genre'=>'','favorite'=>'','priority'=>'','rating_min'=>0,'year'=>0,'tags'=>[],'watches'=>'']) : 'index.php') ?>">Clear filters</a><?php endif; ?>
  </p>
</form>

<!-- ══════════ ROWS VIEW ══════════ -->
<div id="netflix-view">
<?php if (empty($movies)): ?>
  <div class="empty-state">
    <svg class="i empty-icon"><use href="#i-<?= $f['favorite'] === '1' && $activeCount === 1 ? 'heart' : 'film' ?>"/></svg>
    <?php if ($ov_total === 0): ?>
      <h3>Your vault is empty</h3>
      <p>Add the first movie you want to remember.</p>
      <button type="button" class="btn btn-gold btn-lg" data-open-add><svg class="i"><use href="#i-plus"/></svg> Add your first movie</button>
    <?php elseif ($f['favorite'] === '1' && $activeCount === 1): ?>
      <h3>No favorites yet</h3>
      <p>Movies you mark as favorites will appear here. Tap the heart on any film.</p>
      <a class="btn btn-ghost btn-lg" href="index.php">Browse your vault</a>
    <?php elseif ($activeCollection && $activeCount === 1): ?>
      <h3>This collection is empty</h3>
      <p>Use “Add a movie…” above, or open any film and choose Add to collection.</p>
    <?php else: ?>
      <h3>No films match these filters</h3>
      <p>Try a different search, or clear the filters.</p>
      <a class="btn btn-ghost btn-lg" href="index.php">Clear filters</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php foreach ($row_defs as $key => $def):
    [$label, $icon, $tone, $test] = $def; $caption = $def[4] ?? '';
    $group = array_values(array_filter($movies, $test));
    if (!$group) continue;
    $rid = 'row-' . $key; ?>
  <section class="section" data-section>
    <div class="section-header">
      <span class="section-icon section-icon-<?= $tone ?>"><svg class="i"><use href="#i-<?= $icon ?>"/></svg></span>
      <h2 class="section-title"><?= h($label) ?></h2>
      <?php if ($caption): ?><span class="section-caption"><?= h($caption) ?></span><?php endif; ?>
      <span class="section-count"><?= count($group) ?></span>
    </div>
    <div class="row-wrap">
      <button type="button" class="row-btn row-btn-left" data-scroll="<?= $rid ?>" data-dir="-1" aria-label="Scroll left"><svg class="i"><use href="#i-chev-left"/></svg></button>
      <div class="row" id="<?= $rid ?>">
        <?php foreach ($group as $m) echo renderCard($m); ?>
      </div>
      <button type="button" class="row-btn row-btn-right" data-scroll="<?= $rid ?>" data-dir="1" aria-label="Scroll right"><svg class="i"><use href="#i-chev-right"/></svg></button>
    </div>
  </section>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ══════════ GRID VIEW ══════════ -->
<div id="grid-view" hidden>
<?php if (!empty($movies)): ?>
  <div class="grid-section"><div class="movie-grid"><?php foreach ($movies as $m) echo renderCard($m); ?></div></div>
<?php endif; ?>
</div>

<div class="empty-state search-empty" id="searchEmpty" hidden>
  <svg class="i empty-icon"><use href="#i-search"/></svg>
  <h3>No titles match your search</h3>
  <p>Check the spelling, or clear the search box.</p>
</div>

</main>
<?php include 'includes/app_footer.php'; ?>

<button type="button" class="fab" data-open-add aria-label="Add movie"><svg class="i"><use href="#i-plus"/></svg></button>

<!-- ════════ MODALS ════════ -->
<?php
/** Shared field markup for the add and edit forms. $p = 'add' | 'edit'. */
function movieFormFields(string $p, array $genre_options): void { ?>
        <div class="form-grid">
          <div class="field form-full">
            <label for="<?= $p ?>MovieTitle">Movie or show title</label>
            <input class="input" type="text" id="<?= $p ?>MovieTitle" name="movie_title" placeholder="e.g. One Piece" maxlength="255" required>
          </div>
          <div class="field">
            <label for="<?= $p ?>Genre">Genre</label>
            <select id="<?= $p ?>Genre" name="genre" required>
              <option value="">Select a genre</option>
              <?php foreach ($genre_options as $g): ?><option><?= h($g) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="<?= $p ?>Status">Status</label>
            <select id="<?= $p ?>Status" name="status" required>
              <option value="Plan to Watch">Plan to Watch</option>
              <option value="Watching">Watching</option>
              <option value="Watched">Watched</option>
            </select>
          </div>
          <div class="field form-full" id="<?= $p ?>PriorityField">
            <label for="<?= $p ?>Priority">Priority <small>for Plan to Watch</small></label>
            <select id="<?= $p ?>Priority" name="priority">
              <option value="High">Must watch (High)</option>
              <option value="Normal" selected>Next (Normal)</option>
              <option value="Low">Someday (Low)</option>
            </select>
          </div>
          <div class="field form-full">
            <span class="field-label">Your rating</span>
            <div class="star-picker" id="<?= $p ?>StarPicker" role="radiogroup" aria-label="Rating">
              <?php for ($s = 1; $s <= 5; $s++): ?><button type="button" class="star" data-v="<?= $s ?>" aria-label="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>"><svg class="i"><use href="#i-star"/></svg></button><?php endfor; ?>
              <button type="button" class="star-clear" data-v="0">Clear</button>
            </div>
            <input type="hidden" name="rating" id="<?= $p ?>Rating" value="0">
          </div>
          <div class="field form-full">
            <span class="field-label">Cover image <?= $p === 'edit' ? '<small>Leave empty to keep the current one</small>' : '' ?></span>
            <div class="upload-zone" id="<?= $p ?>Zone">
              <input type="file" id="<?= $p ?>File" name="cover_file" accept="image/jpeg,image/png,image/gif,image/webp" aria-label="Choose a cover image">
              <svg class="i uz-icon"><use href="#i-image"/></svg>
              <div class="uz-label"><span>Choose a file</span> or drag it here</div>
              <div class="uz-hint"><?= $p === 'edit' ? 'Upload a new image to replace the current cover' : 'JPG, PNG, WEBP or GIF, up to 5 MB' ?></div>
            </div>
          </div>
          <div class="field form-full">
            <label for="<?= $p ?>Desc">About <small>synopsis or other facts. Your own thoughts go in My notes</small></label>
            <textarea id="<?= $p ?>Desc" name="description" maxlength="5000" placeholder="A short synopsis"></textarea>
          </div>
          <details class="form-full more-details">
            <summary>More details <small>optional</small></summary>
            <div class="form-grid">
              <div class="field"><label for="<?= $p ?>Year">Release year</label><input class="input" type="number" id="<?= $p ?>Year" name="release_year" min="1880" max="<?= (int)date('Y') + 5 ?>" inputmode="numeric"></div>
              <div class="field"><label for="<?= $p ?>Runtime">Runtime <small>minutes</small></label><input class="input" type="number" id="<?= $p ?>Runtime" name="runtime_minutes" min="1" max="999" inputmode="numeric"></div>
              <div class="field"><label for="<?= $p ?>Director">Director</label><input class="input" type="text" id="<?= $p ?>Director" name="director" maxlength="255"></div>
              <div class="field"><label for="<?= $p ?>Language">Language</label><input class="input" type="text" id="<?= $p ?>Language" name="language" maxlength="50"></div>
              <div class="field"><label for="<?= $p ?>Country">Country</label><input class="input" type="text" id="<?= $p ?>Country" name="country" maxlength="100"></div>
              <div class="field form-full"><label for="<?= $p ?>Cast">Cast</label><input class="input" type="text" id="<?= $p ?>Cast" name="cast_members" maxlength="2000" placeholder="Comma separated"></div>
            </div>
          </details>
        </div>
<?php } ?>

<!-- ADD -->
<div class="modal-backdrop" id="addModal" role="dialog" aria-modal="true" aria-labelledby="addTitle">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="addTitle">Add to vault</h2>
      <button type="button" class="modal-close" data-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <div class="modal-body">
      <form method="POST" action="create.php" enctype="multipart/form-data" id="addForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="movie_add">
        <input type="hidden" name="return_to" value="<?= h(qs()) ?>">
        <?php movieFormFields('add', $genre_options); ?>
        <button type="submit" class="btn btn-gold btn-lg btn-block modal-submit"><svg class="i"><use href="#i-plus"/></svg> Add to vault</button>
      </form>
    </div>
  </div>
</div>

<!-- EDIT -->
<div class="modal-backdrop" id="editModal" role="dialog" aria-modal="true" aria-labelledby="editTitleH">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="editTitleH">Edit movie</h2>
      <button type="button" class="modal-close" data-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <div class="modal-body">
      <form method="POST" action="edit.php" enctype="multipart/form-data" id="editForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="movie_edit">
        <input type="hidden" name="return_to" value="<?= h(qs()) ?>">
        <input type="hidden" name="movie_id" id="editId">
        <?php movieFormFields('edit', $genre_options); ?>
        <button type="submit" class="btn btn-gold btn-lg btn-block modal-submit"><svg class="i"><use href="#i-check"/></svg> Save changes</button>
      </form>
    </div>
  </div>
</div>

<!-- DETAIL -->
<div class="modal-backdrop" id="detailModal" role="dialog" aria-modal="true" aria-labelledby="dTitle">
  <div class="modal detail-modal">
    <button type="button" class="modal-close detail-close" data-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>
    <div class="detail-hero" id="detailHero"></div>
    <div class="detail-body">
      <div class="detail-head">
        <h2 class="detail-title" id="dTitle">—</h2>
        <button type="button" class="fav-btn" id="dFav" aria-pressed="false" aria-label="Add to favorites" title="Favorite"><svg class="i"><use href="#i-heart"/></svg></button>
      </div>
      <div class="detail-meta" id="dMeta"></div>
      <div class="detail-stars" id="dStars" role="img"></div>
      <div class="detail-error" id="dError" role="alert" hidden></div>

      <section class="dsec" aria-labelledby="dsStatusH">
        <h3 id="dsStatusH">Status</h3>
        <div class="seg" id="dStatus" role="group" aria-labelledby="dsStatusH">
          <button type="button" data-status="Plan to Watch">Plan to watch</button>
          <button type="button" data-status="Watching">Watching</button>
          <button type="button" data-status="Watched">Watched</button>
        </div>
        <div id="dPriorityWrap" hidden>
          <h3 id="dsPriorityH">Priority</h3>
          <div class="seg seg-priority" id="dPriority" role="group" aria-labelledby="dsPriorityH">
            <button type="button" data-priority="High"><svg class="i"><use href="#i-flame"/></svg> Must watch</button>
            <button type="button" data-priority="Normal"><svg class="i"><use href="#i-pin"/></svg> Next</button>
            <button type="button" data-priority="Low"><svg class="i"><use href="#i-hourglass"/></svg> Someday</button>
          </div>
        </div>
      </section>

      <section class="dsec" aria-labelledby="dsWatchH">
        <h3 id="dsWatchH">Viewing</h3>
        <p class="watch-summary" id="dWatchSummary"></p>
        <button type="button" class="btn btn-soft" id="dWatchBtn"><svg class="i"><use href="#i-repeat"/></svg> <span id="dWatchBtnLbl">Watched again</span></button>
        <form class="inline-form" id="dWatchForm" hidden>
          <div class="field"><label for="dwDate">Date watched</label><input class="input" type="date" id="dwDate" name="watched_at" required></div>
          <div class="field"><label for="dwRating">Rating</label>
            <select id="dwRating" name="rating"><option value="0">No rating</option><?php for ($r = 5; $r >= 1; $r--): ?><option value="<?= $r ?>"><?= str_repeat('★', $r) ?> (<?= $r ?>)</option><?php endfor; ?></select></div>
          <div class="field form-full"><label for="dwNotes">Note <small>optional</small></label><input class="input" type="text" id="dwNotes" name="notes" maxlength="1000" placeholder="How was it this time?"></div>
          <div class="inline-actions"><button type="submit" class="btn btn-gold"><svg class="i"><use href="#i-check"/></svg> Save watch</button><button type="button" class="btn btn-soft" id="dwCancel">Cancel</button></div>
        </form>
      </section>

      <section class="dsec" aria-labelledby="dsAboutH">
        <h3 id="dsAboutH">About</h3>
        <p class="detail-desc" id="dDesc">No description yet.</p>
        <dl class="info-list" id="dInfo"></dl>
      </section>

      <section class="dsec" aria-labelledby="dsNotesH">
        <h3 id="dsNotesH">My notes <small>private to you</small></h3>
        <div id="dNotesView"></div>
        <form class="notes-form" id="dNotesForm" hidden>
          <label class="sr-only" for="dnText">My notes</label>
          <textarea class="input" id="dnText" name="personal_notes" maxlength="5000" rows="4" placeholder="Your own thoughts. Great ending, need to rewatch…"></textarea>
          <label class="check"><input type="checkbox" id="dnSpoiler" name="notes_spoiler" value="1"> Contains spoilers <small>hidden until you choose to show them</small></label>
          <div class="inline-actions"><button type="submit" class="btn btn-gold"><svg class="i"><use href="#i-check"/></svg> Save notes</button><button type="button" class="btn btn-soft" id="dnCancel">Cancel</button></div>
        </form>
      </section>

      <section class="dsec" aria-labelledby="dsTagsH">
        <h3 id="dsTagsH">Tags</h3>
        <ul class="pill-list" id="dTags"></ul>
        <form class="add-row" id="dTagForm">
          <label class="sr-only" for="dTagInput">Add a tag</label>
          <input class="input" id="dTagInput" name="tag" list="tagList" maxlength="50" placeholder="Add a tag, e.g. Rewatch" autocomplete="off">
          <datalist id="tagList"></datalist>
          <button type="submit" class="btn btn-soft"><svg class="i"><use href="#i-plus"/></svg> Add</button>
        </form>
      </section>

      <section class="dsec" aria-labelledby="dsColsH">
        <h3 id="dsColsH">Collections</h3>
        <ul class="pill-list" id="dCols"></ul>
        <form class="add-row" id="dColForm">
          <label class="sr-only" for="dColSelect">Add to a collection</label>
          <select class="select" id="dColSelect" name="collection_id"></select>
          <button type="submit" class="btn btn-soft"><svg class="i"><use href="#i-plus"/></svg> Add</button>
        </form>
        <p class="adv-empty" id="dColsEmpty" hidden>You have no collections yet. <a href="collections.php">Create one</a>.</p>
      </section>

      <section class="dsec" aria-labelledby="dsHistH">
        <h3 id="dsHistH">Watch history</h3>
        <ol class="hist-list" id="dHistory"></ol>
      </section>

      <div class="detail-actions">
        <button type="button" class="btn btn-gold" id="dEdit"><svg class="i"><use href="#i-edit"/></svg> Edit</button>
        <button type="button" class="btn btn-danger" id="dDel"><svg class="i"><use href="#i-trash"/></svg> Remove</button>
      </div>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM (POST + CSRF) -->
<div class="modal-backdrop" id="deleteModal" role="alertdialog" aria-modal="true" aria-labelledby="delH">
  <div class="modal modal-sm">
    <form method="POST" action="delete.php" class="confirm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="movie_delete">
      <input type="hidden" name="movie_id" id="delId">
      <input type="hidden" name="return_to" value="<?= h(qs()) ?>">
      <div class="confirm-icon danger"><svg class="i"><use href="#i-trash"/></svg></div>
      <h3 id="delH">Remove this film?</h3>
      <p id="delMsg">This removes the film from your vault for good.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-soft" data-close>Keep it</button>
        <button type="submit" class="btn btn-danger-solid"><svg class="i"><use href="#i-trash"/></svg> Remove</button>
      </div>
    </form>
  </div>
</div>

<!-- CLEAR ALL CONFIRM -->
<div class="modal-backdrop" id="clearAllModal" role="alertdialog" aria-modal="true" aria-labelledby="clearH">
  <div class="modal modal-sm">
    <form method="POST" action="delete_all.php" class="confirm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="movies_delete_all">
      <input type="hidden" name="return_to" value="index.php">
      <div class="confirm-icon danger"><svg class="i"><use href="#i-alert"/></svg></div>
      <h3 id="clearH">Clear your entire vault?</h3>
      <p>This permanently deletes all your movies, their watch history and cover images. Your tags and collections stay. You can't undo it.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-soft" data-close>Cancel</button>
        <button type="submit" class="btn btn-danger-solid"><svg class="i"><use href="#i-trash"/></svg> Clear all</button>
      </div>
    </form>
  </div>
</div>

<!-- MANAGE TAGS -->
<div class="modal-backdrop" id="tagsModal" role="dialog" aria-modal="true" aria-labelledby="tagsH">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="tagsH">Manage tags</h2>
      <button type="button" class="modal-close" data-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <div class="modal-body">
      <form method="POST" action="actions.php" class="add-row tag-new">
        <?= csrf_field() ?><input type="hidden" name="action" value="tag_create"><input type="hidden" name="return_to" value="<?= h(qs()) ?>">
        <label class="sr-only" for="newTag">New tag</label>
        <input class="input" id="newTag" name="name" maxlength="50" placeholder="New tag, e.g. Comfort Movie" required>
        <button type="submit" class="btn btn-gold"><svg class="i"><use href="#i-plus"/></svg> Create</button>
      </form>
      <?php if (!$allTags): ?><p class="adv-empty">No tags yet. Create one above, or add one from a film's details.</p><?php endif; ?>
      <ul class="tag-manage">
        <?php foreach ($allTags as $t): ?>
        <li>
          <form method="POST" action="actions.php" class="tm-rename">
            <?= csrf_field() ?><input type="hidden" name="action" value="tag_rename"><input type="hidden" name="tag_id" value="<?= $t['id'] ?>"><input type="hidden" name="return_to" value="<?= h(qs()) ?>">
            <label class="sr-only" for="tg<?= $t['id'] ?>">Rename tag <?= h($t['name']) ?></label>
            <input class="input" id="tg<?= $t['id'] ?>" name="name" value="<?= h($t['name']) ?>" maxlength="50" required>
            <span class="tm-count" title="Films with this tag"><?= $t['count'] ?></span>
            <button type="submit" class="btn btn-soft">Rename</button>
          </form>
          <form method="POST" action="actions.php" class="tm-delete" data-confirm="Delete the tag “<?= h($t['name']) ?>”? It will be removed from <?= $t['count'] ?> film<?= $t['count'] === 1 ? '' : 's' ?>.">
            <?= csrf_field() ?><input type="hidden" name="action" value="tag_delete"><input type="hidden" name="tag_id" value="<?= $t['id'] ?>"><input type="hidden" name="return_to" value="<?= h(qs()) ?>">
            <button type="submit" class="btn btn-ghost-danger" aria-label="Delete tag <?= h($t['name']) ?>"><svg class="i"><use href="#i-trash"/></svg></button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<?php if ($activeCollection) { $collReturn = qs(); include 'includes/collection_modals.php'; } ?>
<?php include 'includes/shared_modals.php'; ?>

<script nonce="<?= h(cv_nonce()) ?>">
window.CV = <?= json_encode([
  'username'    => $username,
  'existing'    => array_map(fn($r) => mb_strtolower(trim($r['movie_title'])), $all),
  'flash'       => cv_pull_flash(),
  'tags'        => $allTags,
  'collections' => array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $allCollections),
  'today'       => date('Y-m-d'),
  'filtered'    => $activeCount > 0,
  'returnTo'    => qs(),
], $json_flags) ?>;
</script>
<script src="assets/js/core.js"></script>
<script src="assets/js/dashboard.js"></script>
<script src="assets/js/vault.js"></script>
</body>
</html>
