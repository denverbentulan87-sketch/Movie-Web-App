<?php 
include 'db.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

/* ADD MOVIE */
if(isset($_POST['add_movie'])){
    $stmt = $conn->prepare("INSERT INTO movie_watchlist (user_id,movie_title,genre,status,rating,date_added) VALUES (?,?,?,?,?,NOW())");
    $stmt->bind_param("isssi", $user_id, $_POST['movie_title'], $_POST['genre'], $_POST['status'], $_POST['rating']);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

/* EDIT MOVIE */
if(isset($_POST['edit_movie'])){
    $stmt = $conn->prepare("UPDATE movie_watchlist SET movie_title=?, genre=?, status=?, rating=? WHERE watchlist_id=? AND user_id=?");
    $stmt->bind_param("sssiii", $_POST['movie_title'], $_POST['genre'], $_POST['status'], $_POST['rating'], $_POST['id'], $user_id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

/* DELETE SINGLE */
if(isset($_GET['delete_id'])){
    $stmt = $conn->prepare("DELETE FROM movie_watchlist WHERE watchlist_id=? AND user_id=?");
    $stmt->bind_param("ii", $_GET['delete_id'], $user_id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

/* DELETE ALL */
if(isset($_POST['delete_all'])){
    $stmt = $conn->prepare("DELETE FROM movie_watchlist WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: index.php");
    exit();
}

// SEARCH + FILTER + SORT
$search        = $_GET['search'] ?? "";
$status_filter = $_GET['status'] ?? "";
$genre_filter  = $_GET['genre']  ?? "";
$sort          = $_GET['sort']   ?? "recent";

$query  = "SELECT * FROM movie_watchlist WHERE user_id=?";
$params = [$user_id];
$types  = "i";

if (!empty($search)) {
    $query .= " AND movie_title LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if (!empty($status_filter)) {
    $query .= " AND status=?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($genre_filter)) {
    $query .= " AND genre=?";
    $params[] = $genre_filter;
    $types .= "s";
}

switch ($sort) {
    case "rating":  $query .= " ORDER BY rating DESC"; break;
    case "title":   $query .= " ORDER BY movie_title ASC"; break;
    case "oldest":  $query .= " ORDER BY date_added ASC"; break;
    default:        $query .= " ORDER BY date_added DESC";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// STATS
$total = $watched = $watching = $unwatched = $rating_sum = $rating_count = 0;
$data  = [];
$genres_all = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
    $total++;
    if ($row['status'] == 'watched')   $watched++;
    if ($row['status'] == 'watching')  $watching++;
    if ($row['status'] == 'unwatched') $unwatched++;
    if (!empty($row['rating'])) { $rating_sum += $row['rating']; $rating_count++; }
    if (!empty($row['genre']) && !in_array($row['genre'], $genres_all)) $genres_all[] = $row['genre'];
}

$avg = $rating_count ? round($rating_sum / $rating_count, 1) : "—";
$watch_pct = $total ? round(($watched / $total) * 100) : 0;

// Fetch all genres for filter dropdown (unfiltered)
$gstmt = $conn->prepare("SELECT DISTINCT genre FROM movie_watchlist WHERE user_id=? AND genre != '' ORDER BY genre");
$gstmt->bind_param("i", $user_id);
$gstmt->execute();
$gresult = $gstmt->get_result();
$all_genres = [];
while ($g = $gresult->fetch_assoc()) $all_genres[] = $g['genre'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault — My Watchlist</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ─── RESET & BASE ─────────────────────────────────── */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
    --gold:       #d4a853;
    --gold-light: #e8c87a;
    --gold-dim:   rgba(212,168,83,0.15);
    --bg:         #080810;
    --surface:    #0f0f1a;
    --surface2:   #161625;
    --surface3:   #1e1e30;
    --border:     rgba(212,168,83,0.12);
    --text:       #e8e6e0;
    --muted:      #7a7898;
    --watched:    #22c55e;
    --watching:   #f59e0b;
    --unwatched:  #6366f1;
    --danger:     #ef4444;
    --radius:     14px;
    --shadow:     0 8px 32px rgba(0,0,0,0.6);
}

html { scroll-behavior: smooth; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Outfit', sans-serif;
    font-weight: 400;
    min-height: 100vh;
    overflow-x: hidden;
}

/* ─── GRAIN OVERLAY ────────────────────────────────── */
body::before {
    content:'';
    position:fixed;
    inset:0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events:none;
    z-index:0;
    opacity:.5;
}

/* ─── AMBIENT GLOW ─────────────────────────────────── */
.glow-orb {
    position:fixed;
    border-radius:50%;
    filter:blur(120px);
    pointer-events:none;
    z-index:0;
    opacity:.18;
}
.glow-orb-1 { width:600px; height:600px; background:var(--gold); top:-200px; left:-150px; }
.glow-orb-2 { width:400px; height:400px; background:#6c3db5; bottom:-100px; right:-100px; }

/* ─── LAYOUT ───────────────────────────────────────── */
.app {
    position: relative;
    z-index: 1;
    max-width: 1140px;
    margin: 0 auto;
    padding: 36px 20px 80px;
}

/* ─── HEADER ───────────────────────────────────────── */
.header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 40px;
    padding-bottom: 28px;
    border-bottom: 1px solid var(--border);
}

.header-brand { display:flex; flex-direction:column; gap:6px; }

.brand-label {
    font-size: 10px;
    letter-spacing: 4px;
    text-transform: uppercase;
    color: var(--gold);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}
.brand-label::before {
    content:'';
    display:inline-block;
    width:20px; height:1px;
    background:var(--gold);
}

.brand-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(32px, 5vw, 52px);
    font-weight: 900;
    line-height: 1;
    background: linear-gradient(135deg, #fff 30%, var(--gold-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-actions { display:flex; gap:10px; align-items:center; }

/* ─── BUTTONS ──────────────────────────────────────── */
.btn-primary {
    background: linear-gradient(135deg, var(--gold), #b8892e);
    color: #000;
    padding: 11px 22px;
    border-radius: var(--radius);
    font-family: 'Outfit', sans-serif;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 4px 20px rgba(212,168,83,0.3);
}
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(212,168,83,0.45); }

.btn-ghost {
    background: var(--surface2);
    color: var(--muted);
    padding: 11px 18px;
    border-radius: var(--radius);
    font-family: 'Outfit', sans-serif;
    font-weight: 500;
    font-size: 13px;
    cursor: pointer;
    border: 1px solid var(--border);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all .2s;
}
.btn-ghost:hover { color:var(--danger); border-color:rgba(239,68,68,.3); background:rgba(239,68,68,.06); }

/* ─── STATS ────────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 28px;
}

.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: transform .2s;
}
.stat-card::after {
    content:'';
    position:absolute;
    inset:0;
    background: linear-gradient(135deg, rgba(212,168,83,0.04) 0%, transparent 60%);
    pointer-events:none;
}
.stat-card:hover { transform: translateY(-3px); }

.stat-label {
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 10px;
}

.stat-value {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    font-weight: 700;
    color: var(--gold-light);
    line-height: 1;
}

.stat-sub {
    font-size: 12px;
    color: var(--muted);
    margin-top: 6px;
}

.stat-progress {
    margin-top: 12px;
    height: 3px;
    background: var(--surface3);
    border-radius: 99px;
    overflow: hidden;
}

.stat-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    border-radius: 99px;
    transition: width .8s ease;
}

/* ─── TOOLBAR ──────────────────────────────────────── */
.toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.toolbar input,
.toolbar select {
    padding: 11px 16px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text);
    font-family: 'Outfit', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    appearance: none;
}
.toolbar input { flex: 1; min-width: 180px; }
.toolbar select { min-width: 150px; cursor: pointer; }
.toolbar input:focus,
.toolbar select:focus {
    border-color: rgba(212,168,83,0.5);
    box-shadow: 0 0 0 3px rgba(212,168,83,0.08);
}
.toolbar input::placeholder { color: var(--muted); }

.toolbar-btn {
    padding: 11px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--muted);
    font-family: 'Outfit', sans-serif;
    font-size: 13px;
    cursor: pointer;
    transition: all .2s;
    white-space: nowrap;
}
.toolbar-btn:hover, .toolbar-btn.active {
    background: var(--gold-dim);
    border-color: var(--gold);
    color: var(--gold-light);
}

/* ─── RESULTS INFO ─────────────────────────────────── */
.results-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    font-size: 13px;
    color: var(--muted);
}
.results-count { color: var(--text); font-weight: 500; }

/* ─── MOVIE CARDS ──────────────────────────────────── */
.movies-list { display: flex; flex-direction: column; gap: 10px; }

.movie-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: transform .2s, border-color .2s, box-shadow .2s;
    animation: fadeSlideIn .35s ease both;
    position: relative;
    overflow: hidden;
}
.movie-card::before {
    content:'';
    position:absolute;
    left:0; top:0; bottom:0;
    width: 3px;
    border-radius: 3px 0 0 3px;
}
.movie-card.status-watched::before   { background: var(--watched); }
.movie-card.status-watching::before  { background: var(--watching); }
.movie-card.status-unwatched::before { background: var(--unwatched); }

.movie-card:hover {
    transform: translateX(4px);
    border-color: rgba(212,168,83,0.25);
    box-shadow: var(--shadow);
}

@keyframes fadeSlideIn {
    from { opacity:0; transform:translateY(12px); }
    to   { opacity:1; transform:translateY(0); }
}

.movie-rank {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    color: var(--surface3);
    min-width: 32px;
    font-weight: 700;
    text-align: center;
}

.movie-info { flex: 1; min-width: 0; }

.movie-title {
    font-size: 16px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 6px;
}

<<<<<<< HEAD
.btn-delete {
    color:#ff4d4d;
    border:1px solid rgba(255, 0, 0, 0.34);
    padding:6px 12px;
    border-radius:10px;
    text-decoration:none;
=======
.movie-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.genre-tag {
    background: var(--surface2);
    border: 1px solid var(--border);
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    color: var(--muted);
    font-weight: 500;
    letter-spacing: .5px;
>>>>>>> 4c47f97545920b789af964f8f26f95857a546d4a
}

.status-badge {
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .5px;
    text-transform: uppercase;
}
.status-badge.watched   { background:rgba(34,197,94,.15);  color:#4ade80; }
.status-badge.watching  { background:rgba(245,158,11,.15); color:#fbbf24; }
.status-badge.unwatched { background:rgba(99,102,241,.15); color:#818cf8; }

.movie-date { font-size: 11px; color: var(--muted); }

.movie-rating {
    display: flex;
    align-items: center;
    gap: 5px;
    min-width: 80px;
    justify-content: flex-end;
}

.rating-stars {
    display: flex;
    gap: 2px;
}

.star {
    width: 10px; height: 10px;
    fill: var(--gold);
    opacity: .25;
}
.star.lit { opacity: 1; }

.rating-num {
    font-family: 'Playfair Display', serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--gold-light);
    min-width: 32px;
    text-align: right;
}

.movie-actions { display: flex; gap: 8px; }

.card-btn {
    padding: 7px 14px;
    border-radius: 10px;
    font-family: 'Outfit', sans-serif;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    border: 1px solid transparent;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.card-btn-edit {
    background: var(--surface2);
    color: var(--text);
    border-color: var(--border);
}
.card-btn-edit:hover { background: var(--surface3); border-color: var(--gold); color: var(--gold-light); }

.card-btn-del {
    background: transparent;
    color: var(--muted);
    border-color: var(--border);
}
.card-btn-del:hover { background:rgba(239,68,68,.1); border-color:rgba(239,68,68,.4); color:var(--danger); }

/* ─── EMPTY STATE ──────────────────────────────────── */
.empty {
    text-align: center;
    padding: 80px 20px;
    color: var(--muted);
}
.empty-icon { font-size: 56px; margin-bottom: 16px; opacity:.5; }
.empty h3 { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--text); margin-bottom: 8px; }
.empty p { font-size: 14px; }

/* ─── MODALS ───────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(8px);
    z-index: 100;
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.modal-overlay.open { display: flex; }

.modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    width: 100%;
    max-width: 440px;
    padding: 32px;
    animation: modalIn .3s cubic-bezier(.34,1.56,.64,1);
    box-shadow: 0 24px 80px rgba(0,0,0,0.8);
    position: relative;
}

@keyframes modalIn {
    from { opacity:0; transform:scale(.92) translateY(20px); }
    to   { opacity:1; transform:scale(1) translateY(0); }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    font-weight: 700;
}

.modal-close {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    transition: all .2s;
}
.modal-close:hover { color: var(--text); background: var(--surface3); }

.form-group { margin-bottom: 16px; }

.form-label {
    display: block;
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 7px;
    font-weight: 500;
}

.form-input,
.form-select {
    width: 100%;
    padding: 12px 16px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-family: 'Outfit', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    appearance: none;
}
.form-input:focus, .form-select:focus {
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(212,168,83,0.1);
}
.form-input::placeholder { color: var(--muted); }

.star-picker {
    display: flex;
    gap: 6px;
    align-items: center;
}
.star-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 22px;
    opacity: .3;
    transition: opacity .15s, transform .15s;
    line-height: 1;
}
.star-btn:hover, .star-btn.on { opacity: 1; transform: scale(1.1); }
.rating-hidden { display: none; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.modal-footer { display: flex; gap: 10px; margin-top: 24px; }
.modal-footer .btn-primary { flex: 1; justify-content: center; padding: 13px; }
.modal-footer .btn-cancel {
    padding: 13px 20px;
    border-radius: var(--radius);
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--muted);
    cursor: pointer;
    font-family: 'Outfit', sans-serif;
    font-size: 14px;
    transition: all .2s;
}
.modal-footer .btn-cancel:hover { color: var(--text); }

/* ─── CONFIRM MODAL ────────────────────────────────── */
.confirm-icon { font-size: 40px; text-align: center; margin-bottom: 12px; }
.confirm-text { text-align: center; color: var(--muted); font-size: 14px; margin-bottom: 4px; }
.confirm-title { text-align: center; font-family: 'Playfair Display', serif; font-size: 20px; margin-bottom: 8px; }

/* ─── FOOTER ───────────────────────────────────────── */
.footer {
    margin-top: 60px;
    display: flex;
    justify-content: center;
}

/* ─── SCROLLBAR ────────────────────────────────────── */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 99px; }

/* ─── RESPONSIVE ───────────────────────────────────── */
@media(max-width:768px){
    .stats-grid { grid-template-columns: repeat(2,1fr); }
    .header { flex-direction:column; align-items:flex-start; gap:16px; }
    .movie-rank { display:none; }
    .movie-rating { display:none; }
}
@media(max-width:480px){
    .stats-grid { grid-template-columns: repeat(2,1fr); }
    .toolbar { flex-direction:column; }
    .toolbar input, .toolbar select { min-width:100%; }
}
</style>
</head>
<body>

<!-- Ambient glow -->
<div class="glow-orb glow-orb-1"></div>
<div class="glow-orb glow-orb-2"></div>

<div class="app">

    <!-- ── HEADER ── -->
    <div class="header">
        <div class="header-brand">
            <div class="brand-label">Cinema Vault</div>
            <div class="brand-title">My Watchlist</div>
            <div class ="welcome-user">
                Welcome back, <span style="color: var(--gold-light); font-weight: 600;"><?= htmlspecialchars($username) ?></span>!
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-primary" onclick="openModal('addModal')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Movie
            </button>
            <?php if($total > 0): ?>
            <button class="btn-ghost" onclick="openModal('deleteAllModal')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                Clear All
            </button>
            <?php endif; ?>
            <button class="btn-ghost" onclick="openModal('logoutModal')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Logout
            </button>
        </div>
    </div>

    <!-- ── STATS ── -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Films</div>
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-sub">In your vault</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Watched</div>
            <div class="stat-value"><?= $watched ?></div>
            <div class="stat-sub"><?= $watch_pct ?>% completion</div>
            <div class="stat-progress"><div class="stat-progress-fill" style="width:<?= $watch_pct ?>%"></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Watching Now</div>
            <div class="stat-value"><?= $watching ?></div>
            <div class="stat-sub">In progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Avg Rating</div>
            <div class="stat-value"><?= $avg ?></div>
            <div class="stat-sub"><?= $rating_count ?> rated films</div>
        </div>
    </div>

    <!-- ── TOOLBAR ── -->
    <form method="GET" class="toolbar" id="filterForm">
        <input type="text" name="search" placeholder="🔍  Search titles..." value="<?= htmlspecialchars($search) ?>" oninput="this.form.submit()">

        <select name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="watched"   <?= $status_filter=='watched'  ?'selected':'' ?>>✅ Watched</option>
            <option value="watching"  <?= $status_filter=='watching' ?'selected':'' ?>>▶️ Watching</option>
            <option value="unwatched" <?= $status_filter=='unwatched'?'selected':'' ?>>📋 Unwatched</option>
        </select>

        <select name="genre" onchange="this.form.submit()">
            <option value="">All Genres</option>
            <?php foreach($all_genres as $g): ?>
            <option value="<?= htmlspecialchars($g) ?>" <?= $genre_filter==$g?'selected':'' ?>><?= htmlspecialchars($g) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="sort" onchange="this.form.submit()">
            <option value="recent" <?= $sort=='recent'?'selected':'' ?>>🕐 Newest First</option>
            <option value="oldest" <?= $sort=='oldest'?'selected':'' ?>>🕰 Oldest First</option>
            <option value="rating" <?= $sort=='rating'?'selected':'' ?>>⭐ Top Rated</option>
            <option value="title"  <?= $sort=='title' ?'selected':'' ?>>🔤 A–Z</option>
        </select>

        <?php if($search || $status_filter || $genre_filter): ?>
        <a href="index.php" class="toolbar-btn">✕ Clear</a>
        <?php endif; ?>
    </form>

    <!-- ── RESULTS BAR ── -->
    <div class="results-bar">
        <span><span class="results-count"><?= count($data) ?></span> <?= count($data)==1?'film':'films' ?> found</span>
        <?php if($search || $status_filter || $genre_filter): ?>
        <span>Filtered results</span>
        <?php endif; ?>
    </div>

    <!-- ── MOVIES LIST ── -->
    <div class="movies-list">
        <?php if(empty($data)): ?>
        <div class="empty">
            <div class="empty-icon">🎬</div>
            <h3>No films here</h3>
            <p>Add your first movie to start building your vault.</p>
        </div>
        <?php else: ?>
        <?php foreach ($data as $i => $row): ?>
        <div class="movie-card status-<?= $row['status'] ?>" style="animation-delay:<?= $i * 0.04 ?>s">
            <div class="movie-rank"><?= str_pad($i+1, 2, '0', STR_PAD_LEFT) ?></div>

            <div class="movie-info">
                <div class="movie-title"><?= htmlspecialchars($row['movie_title']) ?></div>
                <div class="movie-meta">
                    <?php if($row['genre']): ?>
                    <span class="genre-tag"><?= htmlspecialchars($row['genre']) ?></span>
                    <?php endif; ?>
                    <span class="status-badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                    <span class="movie-date"><?= date('M j, Y', strtotime($row['date_added'])) ?></span>
                </div>
            </div>

            <div class="movie-rating">
                <?php if($row['rating']): ?>
                <div class="rating-stars">
                    <?php for($s=1;$s<=5;$s++): $lit = ($s <= round($row['rating']/2)); ?>
                    <svg class="star <?= $lit?'lit':'' ?>" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    <?php endfor; ?>
                </div>
                <span class="rating-num"><?= $row['rating'] ?></span>
                <?php else: ?>
                <span style="color:var(--muted);font-size:12px;">—</span>
                <?php endif; ?>
            </div>

            <div class="movie-actions">
                <button class="card-btn card-btn-edit" onclick="openEditModal(
                    <?= $row['watchlist_id'] ?>,
                    '<?= addslashes(htmlspecialchars($row['movie_title'])) ?>',
                    '<?= addslashes(htmlspecialchars($row['genre'])) ?>',
                    '<?= $row['status'] ?>',
                    '<?= $row['rating'] ?>'
                )">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </button>
                <a href="?delete_id=<?= $row['watchlist_id'] ?>" class="card-btn card-btn-del"
                   onclick="return confirm('Remove \'<?= addslashes($row['movie_title']) ?>\' from your vault?')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- /.app -->


<!-- ══════════════════════════════════════════════════ -->
<!--  ADD MODAL                                         -->
<!-- ══════════════════════════════════════════════════ -->
<div id="addModal" class="modal-overlay">
<div class="modal">
    <div class="modal-header">
        <div class="modal-title">Add to Vault</div>
        <button class="modal-close" onclick="closeModals()">✕</button>
    </div>
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Movie Title</label>
            <input class="form-input" name="movie_title" placeholder="e.g. Interstellar" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Genre</label>
                <input class="form-input" name="genre" placeholder="e.g. Sci-Fi">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="unwatched">📋 Unwatched</option>
                    <option value="watching">▶️ Watching</option>
                    <option value="watched">✅ Watched</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Rating (1–10)</label>
            <div class="star-picker" id="addStarPicker">
                <?php for($s=1;$s<=10;$s++): ?>
                <button type="button" class="star-btn" data-val="<?=$s?>" onclick="setRating('add',<?=$s?>)">★</button>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="rating" id="addRatingVal">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModals()">Cancel</button>
            <button class="btn-primary" name="add_movie" type="submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Save to Vault
            </button>
        </div>
    </form>
</div>
</div>


<!-- ══════════════════════════════════════════════════ -->
<!--  EDIT MODAL                                        -->
<!-- ══════════════════════════════════════════════════ -->
<div id="editModal" class="modal-overlay">
<div class="modal">
    <div class="modal-header">
        <div class="modal-title">Edit Film</div>
        <button class="modal-close" onclick="closeModals()">✕</button>
    </div>
    <form method="POST">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-group">
            <label class="form-label">Movie Title</label>
            <input class="form-input" name="movie_title" id="edit_title" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Genre</label>
                <input class="form-input" name="genre" id="edit_genre">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" id="edit_status">
                    <option value="unwatched">📋 Unwatched</option>
                    <option value="watching">▶️ Watching</option>
                    <option value="watched">✅ Watched</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Rating (1–10)</label>
            <div class="star-picker" id="editStarPicker">
                <?php for($s=1;$s<=10;$s++): ?>
                <button type="button" class="star-btn" data-val="<?=$s?>" onclick="setRating('edit',<?=$s?>)">★</button>
                <?php endfor; ?>
            </div>
            <input type="hidden" name="rating" id="editRatingVal">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="closeModals()">Cancel</button>
            <button class="btn-primary" name="edit_movie" type="submit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v13z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Update Film
            </button>
        </div>
    </form>
</div>
</div>


<!-- ══════════════════════════════════════════════════ -->
<!--  DELETE ALL MODAL                                  -->
<!-- ══════════════════════════════════════════════════ -->
<div id="deleteAllModal" class="modal-overlay">
<div class="modal">
    <div class="confirm-icon">🗑️</div>
    <div class="confirm-title">Clear Entire Vault?</div>
    <div class="confirm-text">This will permanently delete all <?= $total ?> film<?= $total!=1?'s':'' ?> from your watchlist. This cannot be undone.</div>
    <form method="POST">
        <div class="modal-footer" style="margin-top:24px;">
            <button type="button" class="btn-cancel" onclick="closeModals()">Keep Films</button>
            <button class="btn-primary" name="delete_all" type="submit" style="background:linear-gradient(135deg,#ef4444,#b91c1c); box-shadow:0 4px 20px rgba(239,68,68,0.3);">
                Yes, Delete All
            </button>
        </div>
    </form>
</div>
</div>


<!-- ══════════════════════════════════════════════════ -->
<!--  LOGOUT MODAL                                      -->
<!-- ══════════════════════════════════════════════════ -->
<div id="logoutModal" class="modal-overlay">
<div class="modal">
    <div class="confirm-icon">👋</div>
    <div class="confirm-title">Leaving already?</div>
    <div class="confirm-text">You'll need to log back in to access your vault.</div>
    <div class="modal-footer" style="margin-top:24px;">
        <button type="button" class="btn-cancel" onclick="closeModals()">Stay</button>
        <a href="logout.php" class="btn-primary" style="text-align:center; justify-content:center;">Logout</a>
    </div>
</div>
</div>


<script>
/* ── Modal helpers ─────────────────────────────────── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
    document.body.style.overflow = '';
}
// close on backdrop click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if(e.target === overlay) closeModals(); });
});
// close on Escape
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModals(); });

/* ── Open edit modal ───────────────────────────────── */
function openEditModal(id, title, genre, status, rating) {
    document.getElementById('edit_id').value    = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_genre').value = genre;
    document.getElementById('edit_status').value= status;
    setRating('edit', parseInt(rating) || 0);
    openModal('editModal');
}

/* ── Star rating picker ────────────────────────────── */
function setRating(prefix, val) {
    const picker = document.getElementById(prefix + 'StarPicker');
    const hidden = document.getElementById(prefix + 'RatingVal');
    hidden.value = val;
    picker.querySelectorAll('.star-btn').forEach(btn => {
        btn.classList.toggle('on', parseInt(btn.dataset.val) <= val);
    });
}

// Hover preview for star pickers
document.querySelectorAll('.star-picker').forEach(picker => {
    const prefix = picker.id.replace('StarPicker','').toLowerCase();
    picker.querySelectorAll('.star-btn').forEach(btn => {
        btn.addEventListener('mouseenter', () => {
            const hover = parseInt(btn.dataset.val);
            picker.querySelectorAll('.star-btn').forEach(b => {
                b.style.opacity = parseInt(b.dataset.val) <= hover ? '1' : '0.3';
            });
        });
        btn.addEventListener('mouseleave', () => {
            const current = parseInt(document.getElementById(prefix+'RatingVal').value) || 0;
            picker.querySelectorAll('.star-btn').forEach(b => {
                b.style.opacity = parseInt(b.dataset.val) <= current ? '1' : '0.3';
            });
        });
    });
});
</script>
</body>
</html>