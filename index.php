<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: homepage.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

/* ── UPLOAD HELPER ── */
function handleUpload($field, $old_url = '') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return $old_url;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return $old_url;

    $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
    $mime    = mime_content_type($_FILES[$field]['tmp_name']);
    if (!in_array($mime, $allowed)) return $old_url;
    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) return $old_url;

    $upload_dir = __DIR__ . '/assets/covers/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext      = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $filename = uniqid('cover_', true) . '.' . $ext;
    $dest     = $upload_dir . $filename;

    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        if ($old_url && strpos($old_url, 'assets/covers/') !== false) {
            $old_path = __DIR__ . '/' . ltrim($old_url, '/');
            if (file_exists($old_path)) unlink($old_path);
        }
        return 'assets/covers/' . $filename;
    }
    return $old_url;
}

/* ── ADD MOVIE ── */
if (isset($_POST['add_movie'])) {
    // ── DUPLICATE CHECK ──
    $dup = $conn->prepare("SELECT watchlist_id FROM movie_watchlist WHERE user_id=? AND LOWER(TRIM(movie_title))=LOWER(TRIM(?))");
    $dup->bind_param("is", $user_id, $_POST['movie_title']);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        header("Location: index.php?duplicate=1&title=" . urlencode($_POST['movie_title']));
        exit();
    }

    $cover_url = handleUpload('cover_file');
    $stmt = $conn->prepare("INSERT INTO movie_watchlist (user_id,movie_title,genre,status,rating,date_added,description,cover_url) VALUES (?,?,?,?,?,NOW(),?,?)");
    $stmt->bind_param("isssiss", $user_id, $_POST['movie_title'], $_POST['genre'], $_POST['status'], $_POST['rating'], $_POST['description'], $cover_url);
    $stmt->execute();
    header("Location: index.php?added=1");
    exit();
}

/* ── EDIT MOVIE ── */
if (isset($_POST['edit_movie'])) {
    $cur = $conn->prepare("SELECT cover_url FROM movie_watchlist WHERE watchlist_id=? AND user_id=?");
    $cur->bind_param("ii", $_POST['id'], $user_id);
    $cur->execute();
    $cur_row   = $cur->get_result()->fetch_assoc();
    $old_cover = $cur_row ? $cur_row['cover_url'] : '';
    $cover_url = handleUpload('cover_file', $old_cover);

    $stmt = $conn->prepare("UPDATE movie_watchlist SET movie_title=?,genre=?,status=?,rating=?,description=?,cover_url=? WHERE watchlist_id=? AND user_id=?");
    $stmt->bind_param("ssssssii", $_POST['movie_title'], $_POST['genre'], $_POST['status'], $_POST['rating'], $_POST['description'], $cover_url, $_POST['id'], $user_id);
    $stmt->execute();
    header("Location: index.php?edited=1");
    exit();
}

/* ── DELETE SINGLE ── */
if (isset($_GET['delete_id'])) {
    $sel = $conn->prepare("SELECT cover_url FROM movie_watchlist WHERE watchlist_id=? AND user_id=?");
    $sel->bind_param("ii", $_GET['delete_id'], $user_id);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if ($row && $row['cover_url'] && strpos($row['cover_url'], 'assets/covers/') !== false) {
        $path = __DIR__ . '/' . $row['cover_url'];
        if (file_exists($path)) unlink($path);
    }
    $stmt = $conn->prepare("DELETE FROM movie_watchlist WHERE watchlist_id=? AND user_id=?");
    $stmt->bind_param("ii", $_GET['delete_id'], $user_id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

/* ── DELETE ALL ── */
if (isset($_POST['delete_all'])) {
    $sel = $conn->prepare("SELECT cover_url FROM movie_watchlist WHERE user_id=?");
    $sel->bind_param("i", $user_id);
    $sel->execute();
    foreach ($sel->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        if ($r['cover_url'] && strpos($r['cover_url'], 'assets/covers/') !== false) {
            $p = __DIR__ . '/' . $r['cover_url'];
            if (file_exists($p)) unlink($p);
        }
    }
    $stmt = $conn->prepare("DELETE FROM movie_watchlist WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

/* ── FETCH MOVIES ── */
$search        = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$genre_filter  = isset($_GET['genre'])  && $_GET['genre']  !== '' ? $_GET['genre']  : null;
$sort          = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order_clause  = $sort==='oldest' ? 'date_added ASC' : ($sort==='rating' ? 'rating DESC' : 'date_added DESC');

$query  = "SELECT * FROM movie_watchlist WHERE user_id=? AND movie_title LIKE ?";
$params = [$user_id, $search]; $types = "is";
if ($status_filter) { $query.=" AND status=?"; $params[]=$status_filter; $types.="s"; }
if ($genre_filter)  { $query.=" AND genre=?";  $params[]=$genre_filter;  $types.="s"; }
$query .= " ORDER BY $order_clause";
$stmt = $conn->prepare($query); $stmt->bind_param($types, ...$params); $stmt->execute();
$movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* STATS */
$total=$cnt=$sum=0; $watched=$watching=0;
foreach ($movies as $m) {
    $total++;
    if ($m['status']==='Watched')  $watched++;
    if ($m['status']==='Watching') $watching++;
    if ($m['rating']>0) { $sum+=$m['rating']; $cnt++; }
}
$avg_rating = $cnt>0 ? round($sum/$cnt,1) : 0;

/* GENRES */
$gs = $conn->prepare("SELECT DISTINCT genre FROM movie_watchlist WHERE user_id=?");
$gs->bind_param("i",$user_id); $gs->execute();
$genres = $gs->get_result()->fetch_all(MYSQLI_ASSOC);

/* GROUP BY STATUS */
$by_status=[];
foreach ($movies as $m) $by_status[$m['status']][]=$m;

/* ── OVERALL STATS (stay stable while filters change) ── */
$allq = $conn->prepare("SELECT movie_title, status, rating, genre FROM movie_watchlist WHERE user_id=?");
$allq->bind_param("i", $user_id); $allq->execute();
$allrows = $allq->get_result()->fetch_all(MYSQLI_ASSOC);
$ov_total = count($allrows); $ov_watched = $ov_watching = $ov_plan = $ov_rated = $ov_sum = 0; $genre_counts = [];
foreach ($allrows as $r) {
    if ($r['status']==='Watched')       $ov_watched++;
    elseif ($r['status']==='Watching')  $ov_watching++;
    else                                $ov_plan++;
    if ((int)$r['rating'] > 0) { $ov_rated++; $ov_sum += (int)$r['rating']; }
    if ($r['genre'] !== null && $r['genre'] !== '') $genre_counts[$r['genre']] = ($genre_counts[$r['genre']] ?? 0) + 1;
}
$ov_avg   = $ov_rated > 0 ? round($ov_sum / $ov_rated, 1) : 0;
$ov_pct   = $ov_total > 0 ? round($ov_watched / $ov_total * 100) : 0;
arsort($genre_counts);
$top_genres = array_slice($genre_counts, 0, 5, true);
$top_max    = $top_genres ? max($top_genres) : 1;

/* current filters + helper to build filter links */
$f_search = trim($_GET['search'] ?? '');
$f_status = $status_filter ?? '';
$f_genre  = $genre_filter ?? '';
function qs(array $over = []) {
    $q = array_intersect_key($_GET, array_flip(['search','status','genre','sort']));
    $q = array_merge($q, $over);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return 'index.php' . ($q ? '?' . http_build_query($q) : '');
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$initial = mb_strtoupper(mb_substr($username, 0, 1));
$json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

function statusClass($s) { return ['Watching'=>'watching','Watched'=>'watched'][$s] ?? 'plan'; }

function renderCard($movie, $h) {
    $cls = statusClass($movie['status']);
    $cov = $movie['cover_url'] ?? '';
    $rt  = (int)$movie['rating'];
    ob_start(); ?>
<article class="movie-card" tabindex="0" role="button" aria-label="<?= $h($movie['movie_title']) ?> — details"
         data-title="<?= $h(mb_strtolower($movie['movie_title'])) ?>"
         data-movie="<?= $h(json_encode($movie, JSON_UNESCAPED_UNICODE)) ?>">
  <div class="poster-frame">
    <?php if ($cov): ?>
      <img src="<?= $h($cov) ?>" alt="" class="movie-poster" loading="lazy" onerror="this.remove()">
    <?php endif; ?>
    <div class="movie-poster-placeholder"><svg class="i"><use href="#i-film"/></svg><span><?= $h($movie['movie_title']) ?></span></div>
  </div>
  <span class="card-status-badge badge-<?= $cls ?>"><?= $h($movie['status']) ?></span>
  <?php if ($rt): ?><span class="card-rating"><svg class="i i-fill"><use href="#i-star"/></svg><?= $rt ?></span><?php endif; ?>
  <div class="card-overlay">
    <div class="card-title"><?= $h($movie['movie_title']) ?></div>
    <div class="card-genre"><?= $h($movie['genre']) ?></div>
    <div class="card-actions">
      <button type="button" class="card-btn card-btn-info" data-act="info"><svg class="i"><use href="#i-play"/></svg>Info</button>
      <button type="button" class="card-btn card-btn-edit" data-act="edit" aria-label="Edit"><svg class="i"><use href="#i-edit"/></svg></button>
      <button type="button" class="card-btn card-btn-del" data-act="del" aria-label="Remove"><svg class="i"><use href="#i-trash"/></svg></button>
    </div>
  </div>
</article>
<?php return ob_get_clean(); }

$genre_options = ['Action','Adventure','Animation','Anime','Comedy','Crime','Documentary','Drama','Fantasy','Fiction','Horror','Romance','Sci-Fi','Thriller'];
$row_defs = [
  'Watching'      => ['Currently watching', 'clock'],
  'Watched'       => ['Watched',            'check-circle'],
  'Plan to Watch' => ['Plan to watch',      'bookmark'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault — <?= $h($username) ?>'s Watchlist</title>
<?php include 'includes/head.php'; ?>
<link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="dash">
<?php include 'includes/icons.php'; ?>

<!-- ══════════ NAV ══════════ -->
<nav class="app-nav" id="navbar" aria-label="Main">
  <a class="brand" href="index.php" aria-label="Cinema Vault — your vault"><svg class="brand-mark"><use href="#i-reel"/></svg><span>CINEMA VAULT</span></a>

  <div class="nav-right">
    <div class="view-toggle" role="group" aria-label="Layout">
      <button type="button" class="view-btn active" id="btnNetflix" data-view="netflix" aria-label="Rows view" title="Rows"><svg class="i"><use href="#i-rows"/></svg></button>
      <button type="button" class="view-btn" id="btnGrid" data-view="grid" aria-label="Grid view" title="Grid"><svg class="i"><use href="#i-grid"/></svg></button>
    </div>
    <button type="button" class="btn btn-gold" data-open-add><svg class="i"><use href="#i-plus"/></svg><span class="lbl">Add Movie</span></button>
    <button type="button" class="btn btn-ghost-danger" data-open="clearAllModal" title="Clear all"><svg class="i"><use href="#i-trash"/></svg><span class="lbl">Clear All</span></button>
    <span class="nav-sep" aria-hidden="true"></span>
    <button type="button" class="user-chip" data-open="logoutModal" title="Sign out">
      <span class="avatar"><?= $h($initial) ?></span>
      <span class="user-name"><?= $h($username) ?></span>
      <svg class="i"><use href="#i-logout"/></svg>
    </button>
  </div>
</nav>

<main class="dash-main">

<!-- ══════════ HEADER + OVERVIEW ══════════ -->
<header class="dash-hero">
  <div class="dash-hero-text">
    <p class="dash-kicker" id="greeting">Welcome back</p>
    <h1 class="dash-title"><?= $h($username) ?>'s vault</h1>
    <p class="dash-sub">
      <?php if ($ov_total === 0): ?>
        Nothing here yet. Add the first film you want to remember.
      <?php else: ?>
        <?= $ov_total ?> <?= $ov_total === 1 ? 'film' : 'films' ?> in your vault<?= $ov_watching ? ', ' . $ov_watching . ' in progress' : '' ?>.
      <?php endif; ?>
    </p>
  </div>
  <button type="button" class="btn btn-gold btn-lg hero-add" data-open-add><svg class="i"><use href="#i-plus"/></svg> Add a movie</button>
</header>

<section class="overview" aria-label="Your stats">
  <div class="stats">
    <a class="stat-card" href="<?= $h(qs(['status'=>'','genre'=>'','search'=>''])) ?>">
      <span class="stat-icon"><svg class="i"><use href="#i-film"/></svg></span>
      <span class="stat-label">Total films</span>
      <span class="stat-value" data-count="<?= $ov_total ?>"><?= $ov_total ?></span>
      <span class="stat-sub">In your vault</span>
    </a>
    <a class="stat-card" href="<?= $h(qs(['status'=>'Watched'])) ?>">
      <span class="stat-icon"><svg class="i"><use href="#i-check-circle"/></svg></span>
      <span class="stat-label">Watched</span>
      <span class="stat-value" data-count="<?= $ov_watched ?>"><?= $ov_watched ?></span>
      <span class="stat-sub"><?= $ov_pct ?>% complete</span>
      <span class="stat-bar" aria-hidden="true"><i style="--w:<?= $ov_pct ?>%"></i></span>
    </a>
    <a class="stat-card" href="<?= $h(qs(['status'=>'Watching'])) ?>">
      <span class="stat-icon"><svg class="i"><use href="#i-clock"/></svg></span>
      <span class="stat-label">Watching now</span>
      <span class="stat-value" data-count="<?= $ov_watching ?>"><?= $ov_watching ?></span>
      <span class="stat-sub">In progress</span>
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
          <a href="<?= $h(qs(['genre'=>$g])) ?>" class="taste-row<?= $f_genre === $g ? ' is-on' : '' ?>">
            <span class="taste-name"><?= $h($g) ?></span>
            <span class="taste-bar"><i style="--w:<?= round($n / $top_max * 100) ?>%"></i></span>
            <span class="taste-n"><?= $n ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
</section>

<div class="sprockets" aria-hidden="true"></div>

<!-- ══════════ FILTERS ══════════ -->
<form method="GET" class="filter-bar" id="filterForm" role="search">
  <div class="search-wrap input-wrap">
    <svg class="i i-lead"><use href="#i-search"/></svg>
    <input class="input has-lead has-trail" type="search" id="searchInput" name="search" placeholder="Search your vault" value="<?= $h($f_search) ?>" autocomplete="off" aria-label="Search titles">
    <button type="button" class="input-trail search-clear" id="searchClear" aria-label="Clear search" <?= $f_search === '' ? 'hidden' : '' ?>><svg class="i"><use href="#i-x"/></svg></button>
  </div>

  <div class="chips" role="group" aria-label="Status">
    <a class="chip<?= $f_status === '' ? ' is-on' : '' ?>" href="<?= $h(qs(['status'=>''])) ?>">All <b><?= $ov_total ?></b></a>
    <a class="chip<?= $f_status === 'Watching' ? ' is-on' : '' ?>" href="<?= $h(qs(['status'=>'Watching'])) ?>">Watching <b><?= $ov_watching ?></b></a>
    <a class="chip<?= $f_status === 'Watched' ? ' is-on' : '' ?>" href="<?= $h(qs(['status'=>'Watched'])) ?>">Watched <b><?= $ov_watched ?></b></a>
    <a class="chip<?= $f_status === 'Plan to Watch' ? ' is-on' : '' ?>" href="<?= $h(qs(['status'=>'Plan to Watch'])) ?>">Plan to watch <b><?= $ov_plan ?></b></a>
  </div>

  <?php if ($f_status !== ''): ?><input type="hidden" name="status" value="<?= $h($f_status) ?>"><?php endif; ?>
  <div class="filter-selects">
    <select class="select" name="genre" onchange="this.form.submit()" aria-label="Genre">
      <option value="">All genres</option>
      <?php foreach ($genres as $g): ?>
      <option value="<?= $h($g['genre']) ?>" <?= $f_genre === $g['genre'] ? 'selected' : '' ?>><?= $h($g['genre']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="select" name="sort" onchange="this.form.submit()" aria-label="Sort order">
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
      <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
      <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top rated</option>
    </select>
  </div>
  <p class="found" id="foundCount" aria-live="polite"><b id="foundNum"><?= $total ?></b> <span id="foundWord"><?= $total === 1 ? 'film' : 'films' ?></span> found</p>
</form>

<!-- ══════════ ROWS VIEW ══════════ -->
<div id="netflix-view">
<?php if (empty($movies)): ?>
  <div class="empty-state">
    <svg class="i empty-icon"><use href="#i-film"/></svg>
    <?php if ($ov_total === 0): ?>
      <h3>Your vault is empty</h3>
      <p>Add the first movie you want to remember.</p>
      <button type="button" class="btn btn-gold btn-lg" data-open-add><svg class="i"><use href="#i-plus"/></svg> Add your first movie</button>
    <?php else: ?>
      <h3>No films match these filters</h3>
      <p>Try a different search, or clear the filters.</p>
      <a class="btn btn-ghost btn-lg" href="index.php">Clear filters</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php foreach ($row_defs as $sk => [$label, $icon]):
    if (empty($by_status[$sk])) continue;
    $rid = 'row-' . md5($sk); ?>
  <section class="section" data-section>
    <div class="section-header">
      <span class="section-icon section-icon-<?= statusClass($sk) ?>"><svg class="i"><use href="#i-<?= $icon ?>"/></svg></span>
      <h2 class="section-title"><?= $h($label) ?></h2>
      <span class="section-count"><?= count($by_status[$sk]) ?></span>
    </div>
    <div class="row-wrap">
      <button type="button" class="row-btn row-btn-left" data-scroll="<?= $rid ?>" data-dir="-1" aria-label="Scroll left"><svg class="i"><use href="#i-chev-left"/></svg></button>
      <div class="row" id="<?= $rid ?>">
        <?php foreach ($by_status[$sk] as $m) echo renderCard($m, $h); ?>
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
  <div class="grid-section"><div class="movie-grid"><?php foreach ($movies as $m) echo renderCard($m, $h); ?></div></div>
<?php endif; ?>
</div>

<div class="empty-state search-empty" id="searchEmpty" hidden>
  <svg class="i empty-icon"><use href="#i-search"/></svg>
  <h3>No titles match your search</h3>
  <p>Check the spelling, or clear the search box.</p>
</div>

</main>

<button type="button" class="fab" data-open-add aria-label="Add movie"><svg class="i"><use href="#i-plus"/></svg></button>

<!-- ════════ MODALS ════════ -->

<!-- ADD -->
<div class="modal-backdrop" id="addModal" role="dialog" aria-modal="true" aria-labelledby="addTitle">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="addTitle">Add to vault</h2>
      <button type="button" class="modal-close" data-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <div class="modal-body">
      <form method="POST" enctype="multipart/form-data" id="addForm">
        <input type="hidden" name="add_movie" value="1">
        <div class="form-grid">
          <div class="field form-full">
            <label for="addMovieTitle">Movie or show title</label>
            <input class="input" type="text" id="addMovieTitle" name="movie_title" placeholder="e.g. One Piece" required>
          </div>
          <div class="field">
            <label for="addGenre">Genre</label>
            <select id="addGenre" name="genre" required>
              <option value="">Select a genre</option>
              <?php foreach ($genre_options as $g): ?><option><?= $h($g) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="addStatus">Status</label>
            <select id="addStatus" name="status" required>
              <option value="Plan to Watch">Plan to Watch</option>
              <option value="Watching">Watching</option>
              <option value="Watched">Watched</option>
            </select>
          </div>
          <div class="field form-full">
            <span class="field-label">Your rating</span>
            <div class="star-picker" id="addStarPicker" role="radiogroup" aria-label="Rating">
              <?php for ($s = 1; $s <= 5; $s++): ?><button type="button" class="star" data-v="<?= $s ?>" aria-label="<?= $s ?> star<?= $s>1?'s':'' ?>"><svg class="i"><use href="#i-star"/></svg></button><?php endfor; ?>
              <button type="button" class="star-clear" data-v="0">Clear</button>
            </div>
            <input type="hidden" name="rating" id="addRating" value="0">
          </div>
          <div class="field form-full">
            <span class="field-label">Cover image</span>
            <div class="upload-zone" id="addZone">
              <input type="file" id="addFile" name="cover_file" accept="image/*" aria-label="Choose a cover image">
              <svg class="i uz-icon"><use href="#i-image"/></svg>
              <div class="uz-label"><span>Choose a file</span> or drag it here</div>
              <div class="uz-hint">JPG, PNG, WEBP or GIF, up to 5 MB</div>
            </div>
          </div>
          <div class="field form-full">
            <label for="addDesc">Description</label>
            <textarea id="addDesc" name="description" placeholder="A short synopsis or your own notes"></textarea>
          </div>
        </div>
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
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="edit_movie" value="1">
        <input type="hidden" name="id" id="editId">
        <div class="form-grid">
          <div class="field form-full">
            <label for="editTitle">Movie or show title</label>
            <input class="input" type="text" name="movie_title" id="editTitle" required>
          </div>
          <div class="field">
            <label for="editGenre">Genre</label>
            <select name="genre" id="editGenre">
              <?php foreach ($genre_options as $g): ?><option><?= $h($g) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="editStatus">Status</label>
            <select name="status" id="editStatus">
              <option value="Plan to Watch">Plan to Watch</option>
              <option value="Watching">Watching</option>
              <option value="Watched">Watched</option>
            </select>
          </div>
          <div class="field form-full">
            <span class="field-label">Your rating</span>
            <div class="star-picker" id="editStarPicker" role="radiogroup" aria-label="Rating">
              <?php for ($s = 1; $s <= 5; $s++): ?><button type="button" class="star" data-v="<?= $s ?>" aria-label="<?= $s ?> star<?= $s>1?'s':'' ?>"><svg class="i"><use href="#i-star"/></svg></button><?php endfor; ?>
              <button type="button" class="star-clear" data-v="0">Clear</button>
            </div>
            <input type="hidden" name="rating" id="editRating" value="0">
          </div>
          <div class="field form-full">
            <span class="field-label">Cover image <small>Leave empty to keep the current one</small></span>
            <div class="upload-zone" id="editZone">
              <input type="file" id="editFile" name="cover_file" accept="image/*" aria-label="Choose a new cover image">
              <svg class="i uz-icon"><use href="#i-image"/></svg>
              <div class="uz-label"><span>Choose a file</span> or drag it here</div>
              <div class="uz-hint">Upload a new image to replace the current cover</div>
            </div>
          </div>
          <div class="field form-full">
            <label for="editDesc">Description</label>
            <textarea name="description" id="editDesc" placeholder="A short synopsis or your own notes"></textarea>
          </div>
        </div>
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
      <h2 class="detail-title" id="dTitle">—</h2>
      <div class="detail-meta" id="dMeta"></div>
      <div class="detail-stars" id="dStars" role="img"></div>
      <p class="detail-desc" id="dDesc">No description yet.</p>
      <div class="detail-actions">
        <button type="button" class="btn btn-gold" id="dEdit"><svg class="i"><use href="#i-edit"/></svg> Edit</button>
        <button type="button" class="btn btn-danger" id="dDel"><svg class="i"><use href="#i-trash"/></svg> Remove</button>
      </div>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM -->
<div class="modal-backdrop" id="deleteModal" role="alertdialog" aria-modal="true" aria-labelledby="delH">
  <div class="modal modal-sm">
    <div class="confirm">
      <div class="confirm-icon danger"><svg class="i"><use href="#i-trash"/></svg></div>
      <h3 id="delH">Remove this film?</h3>
      <p id="delMsg">This removes the film from your vault for good.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-soft" data-close>Keep it</button>
        <a class="btn btn-danger-solid" id="delLink" href="#"><svg class="i"><use href="#i-trash"/></svg> Remove</a>
      </div>
    </div>
  </div>
</div>

<!-- CLEAR ALL CONFIRM -->
<div class="modal-backdrop" id="clearAllModal" role="alertdialog" aria-modal="true" aria-labelledby="clearH">
  <div class="modal modal-sm">
    <form method="POST" class="confirm">
      <input type="hidden" name="delete_all" value="1">
      <div class="confirm-icon danger"><svg class="i"><use href="#i-alert"/></svg></div>
      <h3 id="clearH">Clear your entire vault?</h3>
      <p>This permanently deletes all your movies and their cover images. You can't undo it.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-soft" data-close>Cancel</button>
        <button type="submit" class="btn btn-danger-solid"><svg class="i"><use href="#i-trash"/></svg> Clear all</button>
      </div>
    </form>
  </div>
</div>

<!-- LOGOUT CONFIRM -->
<div class="modal-backdrop" id="logoutModal" role="alertdialog" aria-modal="true" aria-labelledby="logoutH">
  <div class="modal modal-sm">
    <div class="confirm">
      <div class="confirm-icon"><svg class="i"><use href="#i-logout"/></svg></div>
      <h3 id="logoutH">Leaving so soon?</h3>
      <div class="user-badge"><span class="avatar"><?= $h($initial) ?></span><span>Signed in as <strong><?= $h($username) ?></strong></span></div>
      <p>Your watchlist is saved and will be here when you come back.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-soft" data-close>Stay and watch</button>
        <a href="logout.php" class="btn btn-gold"><svg class="i"><use href="#i-logout"/></svg> Log out</a>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
window.CV = <?= json_encode([
  'username' => $username,
  'existing' => array_map(fn($r) => mb_strtolower(trim($r['movie_title'])), $allrows),
  'flash'    => [
      'added'     => isset($_GET['added']),
      'edited'    => isset($_GET['edited']),
      'duplicate' => isset($_GET['duplicate']) ? (string)($_GET['title'] ?? '') : null,
  ],
], $json_flags) ?>;
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
