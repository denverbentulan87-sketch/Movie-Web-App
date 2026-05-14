<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinema Vault — <?= htmlspecialchars($username) ?>'s Watchlist</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--black:#0a0a0a;--dark:#141414;--surface:#1a1a1a;--card:#222;--border:#2a2a2a;--red:#e50914;--red-hover:#f40612;--gold:#f5c518;--text:#e5e5e5;--muted:#777;--white:#fff;--radius:6px;}
*{margin:0;padding:0;box-sizing:border-box;}html{scroll-behavior:smooth;}
body{background:var(--black);color:var(--text);font-family:'Outfit',sans-serif;min-height:100vh;overflow-x:hidden;}
::-webkit-scrollbar{width:6px;height:6px;}::-webkit-scrollbar-track{background:transparent;}::-webkit-scrollbar-thumb{background:#333;border-radius:3px;}

/* NAVBAR */
.navbar{position:fixed;top:0;left:0;right:0;z-index:900;padding:0 4%;height:68px;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(to bottom,rgba(0,0,0,.95),rgba(0,0,0,0));transition:background .3s;}
.navbar.scrolled{background:rgba(10,10,10,.97);box-shadow:0 2px 20px rgba(0,0,0,.5);}
.nav-logo{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--red);letter-spacing:2px;text-decoration:none;}
.nav-right{display:flex;align-items:center;gap:12px;}
.nav-user{font-size:.85rem;color:var(--muted);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:var(--radius);font-family:'Outfit',sans-serif;font-size:.875rem;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.btn-red{background:var(--red);color:#fff;}.btn-red:hover{background:var(--red-hover);transform:translateY(-1px);}
.btn-outline{background:transparent;color:var(--text);border:1px solid rgba(255,255,255,.3);}.btn-outline:hover{border-color:rgba(255,255,255,.7);background:rgba(255,255,255,.08);}
.btn-ghost{background:transparent;color:var(--muted);border:none;font-size:.8rem;padding:6px 12px;}.btn-ghost:hover{color:var(--text);}

/* HERO */
.hero{padding:120px 4% 60px;position:relative;overflow:hidden;}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 20% 50%,rgba(229,9,20,.08),transparent 60%),radial-gradient(ellipse at 80% 20%,rgba(245,197,24,.04),transparent 50%);pointer-events:none;}
.hero-title{font-family:'Bebas Neue',sans-serif;font-size:clamp(2rem,4vw,3.2rem);letter-spacing:3px;color:var(--white);margin-bottom:4px;}
.hero-sub{font-size:.95rem;color:var(--muted);}.hero-sub span{color:var(--gold);font-weight:600;}

/* STATS */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:0 4% 40px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:20px 24px;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--red);}
.stat-label{font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:10px;}
.stat-value{font-family:'Bebas Neue',sans-serif;font-size:2.8rem;color:var(--white);line-height:1;}
.stat-sub{font-size:.75rem;color:var(--muted);margin-top:4px;}

/* FILTER */
.filter-bar{padding:0 4% 32px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.search-wrap{flex:1;min-width:200px;position:relative;}
.search-wrap input{width:100%;padding:10px 16px 10px 40px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:'Outfit',sans-serif;font-size:.9rem;outline:none;transition:border .2s;}
.search-wrap input:focus{border-color:var(--red);}
.search-wrap::before{content:'🔍';position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.85rem;}
.filter-bar select{padding:10px 14px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:'Outfit',sans-serif;font-size:.85rem;cursor:pointer;outline:none;}
.filter-bar select:focus{border-color:var(--red);}

/* ROW */
.section{padding:0 4% 48px;}
.section-header{display:flex;align-items:center;gap:16px;margin-bottom:18px;}
.section-title{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;letter-spacing:1px;color:var(--white);}
.section-count{font-size:.75rem;color:var(--muted);background:var(--surface);padding:3px 10px;border-radius:20px;}
.row-wrap{position:relative;}
.row{display:flex;gap:10px;overflow-x:auto;padding-bottom:12px;scroll-behavior:smooth;scrollbar-width:none;}
.row::-webkit-scrollbar{display:none;}
.row-btn{position:absolute;top:50%;transform:translateY(-60%);z-index:10;width:40px;height:80px;background:rgba(20,20,20,.8);border:none;color:#fff;cursor:pointer;font-size:1.4rem;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);transition:background .2s;}
.row-btn:hover{background:rgba(40,40,40,.95);}
.row-btn-left{left:-8px;border-radius:0 var(--radius) var(--radius) 0;}
.row-btn-right{right:-8px;border-radius:var(--radius) 0 0 var(--radius);}

/* CARD */
.movie-card{flex:0 0 180px;position:relative;border-radius:var(--radius);overflow:hidden;cursor:pointer;transition:transform .3s,z-index 0s .3s;}
.movie-card:hover{transform:scale(1.08);z-index:10;transition:transform .3s,z-index 0s;}
.movie-poster{width:100%;aspect-ratio:2/3;object-fit:cover;}
.movie-poster-placeholder{width:100%;aspect-ratio:2/3;background:linear-gradient(135deg,#1a1a2e,#16213e 50%,#0f3460);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;}
.movie-poster-placeholder .icon{font-size:3rem;opacity:.4;}
.movie-poster-placeholder .ptitle{font-size:.75rem;color:rgba(255,255,255,.3);text-align:center;padding:0 8px;}
.card-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.95),rgba(0,0,0,.4) 50%,transparent);display:flex;flex-direction:column;justify-content:flex-end;padding:12px;opacity:0;transition:opacity .3s;}
.movie-card:hover .card-overlay{opacity:1;}
.card-title{font-size:.82rem;font-weight:700;color:#fff;margin-bottom:4px;line-height:1.2;}
.card-genre{font-size:.68rem;color:var(--muted);margin-bottom:8px;}
.card-actions{display:flex;gap:6px;}
.card-btn{flex:1;padding:5px;border:none;border-radius:3px;font-size:.7rem;font-weight:600;cursor:pointer;transition:all .15s;font-family:'Outfit',sans-serif;}
.card-btn-play{background:var(--white);color:#000;}.card-btn-play:hover{background:#ddd;}
.card-btn-edit{background:rgba(255,255,255,.15);color:#fff;}.card-btn-edit:hover{background:rgba(255,255,255,.25);}
.card-btn-del{background:rgba(229,9,20,.6);color:#fff;padding:5px 7px;flex:0;}.card-btn-del:hover{background:var(--red);}
.card-status-badge{position:absolute;top:8px;left:8px;padding:2px 8px;border-radius:3px;font-size:.6rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;}
.badge-watching{background:var(--red);color:#fff;}
.badge-watched{background:#1db954;color:#fff;}
.badge-plan{background:rgba(255,255,255,.15);color:#fff;backdrop-filter:blur(4px);}
.card-rating{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.7);padding:2px 6px;border-radius:3px;font-size:.65rem;color:var(--gold);font-weight:700;backdrop-filter:blur(4px);}

/* EMPTY */
.empty-state{text-align:center;padding:60px 20px;color:var(--muted);}
.empty-state .big-icon{font-size:4rem;margin-bottom:16px;opacity:.3;}
.empty-state h3{font-size:1.1rem;color:var(--text);margin-bottom:8px;}

/* MODAL */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:1000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s;backdrop-filter:blur(4px);}
.modal-backdrop.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:10px;width:min(560px,95vw);max-height:90vh;overflow-y:auto;position:relative;transform:scale(.95) translateY(20px);transition:transform .3s;border:1px solid var(--border);}
.modal-backdrop.open .modal{transform:scale(1) translateY(0);}
.detail-modal{background:var(--dark);border-radius:10px;width:min(700px,95vw);max-height:90vh;overflow-y:auto;position:relative;transform:scale(.95) translateY(20px);transition:transform .3s;border:1px solid var(--border);}
.modal-backdrop.open .detail-modal{transform:scale(1) translateY(0);}
.detail-hero{position:relative;height:320px;overflow:hidden;border-radius:10px 10px 0 0;}
.detail-hero img{width:100%;height:100%;object-fit:cover;}
.detail-hero-bg{width:100%;height:100%;background:linear-gradient(135deg,#0d0d1a,#1a0a0a);display:flex;align-items:center;justify-content:center;font-size:6rem;opacity:.3;}
.detail-hero-overlay{position:absolute;inset:0;background:linear-gradient(to top,var(--dark),transparent 60%);}
.detail-hero-content{position:absolute;bottom:0;left:0;right:0;padding:24px 28px;}
.detail-title{font-family:'Bebas Neue',sans-serif;font-size:2.4rem;color:#fff;letter-spacing:2px;}
.detail-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
.detail-tag{background:rgba(255,255,255,.1);padding:3px 10px;border-radius:3px;font-size:.72rem;color:rgba(255,255,255,.8);}
.detail-body{padding:24px 28px 28px;}
.detail-desc{font-size:.9rem;color:var(--muted);line-height:1.7;margin-bottom:20px;}
.detail-stars{display:flex;gap:3px;margin-bottom:20px;}
.star{font-size:1.2rem;color:var(--border);}.star.filled{color:var(--gold);}
.detail-actions{display:flex;gap:10px;}

/* FORM */
.modal-header{padding:24px 28px 0;display:flex;align-items:center;justify-content:space-between;}
.modal-title{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:1px;color:var(--white);}
.modal-close{background:none;border:none;color:var(--muted);font-size:1.4rem;cursor:pointer;padding:4px;line-height:1;}.modal-close:hover{color:var(--white);}
.modal-body{padding:20px 28px 28px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-full{grid-column:1/-1;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group label{font-size:.75rem;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);}
.form-group input,.form-group select,.form-group textarea{padding:10px 14px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);color:var(--text);font-family:'Outfit',sans-serif;font-size:.9rem;outline:none;transition:border .2s;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--red);}
.form-group textarea{resize:vertical;min-height:80px;}
.star-picker{display:flex;gap:6px;}
.star-picker .star{font-size:1.6rem;cursor:pointer;color:var(--border);transition:color .1s;}
.star-picker .star:hover,.star-picker .star.active{color:var(--gold);}

/* ── UPLOAD ZONE ── */
.upload-zone{
  position:relative; border:2px dashed var(--border); border-radius:var(--radius);
  background:var(--card); cursor:pointer; overflow:hidden;
  min-height:170px; display:flex; flex-direction:column;
  align-items:center; justify-content:center; gap:8px;
  transition:border-color .2s, background .2s;
}
.upload-zone:hover,.upload-zone.drag-over{border-color:var(--red);background:rgba(229,9,20,.05);}
.upload-zone input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;padding:0!important;border:none!important;background:transparent!important;}
.uz-icon{font-size:2.4rem;pointer-events:none;}
.uz-label{font-size:.85rem;color:var(--muted);text-align:center;pointer-events:none;}
.uz-label span{color:var(--red);font-weight:600;}
.uz-hint{font-size:.72rem;color:#555;pointer-events:none;}
.uz-preview{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;}
.uz-preview img{width:100%;height:100%;object-fit:cover;}
.uz-change{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.75);color:#fff;font-size:.75rem;text-align:center;padding:7px;opacity:0;transition:opacity .2s;pointer-events:none;font-family:'Outfit',sans-serif;}
.upload-zone:hover .uz-change{opacity:1;}
.uz-remove{position:absolute;top:8px;right:8px;z-index:5;background:rgba(229,9,20,.85);color:#fff;border:none;border-radius:50%;width:28px;height:28px;font-size:.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;}
.uz-remove:hover{background:var(--red);}

/* CONFIRM */
.confirm-modal{padding:32px;text-align:center;}
.confirm-modal .icon{font-size:3rem;margin-bottom:12px;}
.confirm-modal h3{font-size:1.2rem;color:var(--white);margin-bottom:8px;}
.confirm-modal p{font-size:.85rem;color:var(--muted);margin-bottom:24px;}
.confirm-actions{display:flex;gap:10px;justify-content:center;}

/* TOAST */
.toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%) translateY(100px);background:var(--surface);color:var(--text);padding:12px 24px;border-radius:40px;font-size:.85rem;border:1px solid var(--border);z-index:2000;transition:transform .3s;box-shadow:0 8px 32px rgba(0,0,0,.5);}
.toast.show{transform:translateX(-50%) translateY(0);}

/* VIEW TOGGLE */
.view-toggle{display:flex;gap:4px;background:var(--surface);padding:4px;border-radius:var(--radius);}
.view-btn{padding:6px 10px;border:none;background:none;color:var(--muted);cursor:pointer;border-radius:4px;font-size:.9rem;}
.view-btn.active{background:var(--card);color:var(--white);}

/* GRID */
.grid-section{padding:0 4% 48px;}
.movie-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;}

@media(max-width:768px){
  .stats{grid-template-columns:repeat(2,1fr);}
  .form-grid{grid-template-columns:1fr;}
  .detail-title{font-size:1.8rem;}
  .movie-card{flex:0 0 140px;}
}
</style>
</head>
<body>

<nav class="navbar" id="navbar">
  <a class="nav-logo" href="index.php">CINEMA VAULT</a>
  <div class="nav-right">
    <span class="nav-user">👤 <?= htmlspecialchars($username) ?></span>
    <div class="view-toggle">
      <button class="view-btn active" id="btnNetflix" onclick="setView('netflix')">▤ Rows</button>
      <button class="view-btn" id="btnGrid"    onclick="setView('grid')">⊞ Grid</button>
    </div>
    <button class="btn btn-red" onclick="openAddModal()">+ Add Movie</button>
    <button class="btn btn-outline" onclick="openModal('clearAllModal')">Clear All</button>
    <a class="btn btn-ghost" href="logout.php">Logout</a>
  </div>
</nav>

<div class="hero">
  <h1 class="hero-title">My Watchlist</h1>
  <p class="hero-sub">Welcome back, <span><?= htmlspecialchars($username) ?></span>!</p>
</div>

<div class="stats">
  <div class="stat-card">
    <div class="stat-label">Total Films</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-sub">In your vault</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Watched</div>
    <div class="stat-value"><?= $watched ?></div>
    <div class="stat-sub"><?= $total>0?round($watched/$total*100):0 ?>% completion</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Watching Now</div>
    <div class="stat-value"><?= $watching ?></div>
    <div class="stat-sub">In progress</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Avg Rating</div>
    <div class="stat-value"><?= $avg_rating?:'—' ?></div>
    <div class="stat-sub"><?= $cnt ?> rated films</div>
  </div>
</div>

<form method="GET" class="filter-bar" id="filterForm">
  <div class="search-wrap">
    <input type="text" name="search" placeholder="Search titles..." value="<?= htmlspecialchars($_GET['search']??'') ?>" oninput="debounce()" autocomplete="off">
  </div>
  <select name="status" onchange="this.form.submit()">
    <option value="">All Statuses</option>
    <option value="Watching"      <?= ($_GET['status']??'')==='Watching'     ?'selected':'' ?>>Watching</option>
    <option value="Watched"       <?= ($_GET['status']??'')==='Watched'      ?'selected':'' ?>>Watched</option>
    <option value="Plan to Watch" <?= ($_GET['status']??'')==='Plan to Watch'?'selected':'' ?>>Plan to Watch</option>
  </select>
  <select name="genre" onchange="this.form.submit()">
    <option value="">All Genres</option>
    <?php foreach($genres as $g): ?>
    <option value="<?= htmlspecialchars($g['genre']) ?>" <?= ($_GET['genre']??'')===$g['genre']?'selected':'' ?>><?= htmlspecialchars($g['genre']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" onchange="this.form.submit()">
    <option value="newest" <?= ($_GET['sort']??'')==='newest'?'selected':'' ?>>Newest First</option>
    <option value="oldest" <?= ($_GET['sort']??'')==='oldest'?'selected':'' ?>>Oldest First</option>
    <option value="rating" <?= ($_GET['sort']??'')==='rating'?'selected':'' ?>>Top Rated</option>
  </select>
  <span style="font-size:.82rem;color:var(--muted);"><?= $total ?> films found</span>
</form>

<?php
function renderCard($movie){
  $bid='badge-'.strtolower(str_replace([' ','/'],'',$movie['status']));
  if(strpos($bid,'plantowatch')!==false)$bid='badge-plan';
  $mj  = htmlspecialchars(json_encode($movie),ENT_QUOTES);
  $ts  = htmlspecialchars($movie['movie_title'],ENT_QUOTES);
  $cov = htmlspecialchars($movie['cover_url']??'');
  $rt  = (int)$movie['rating'];
  ob_start(); ?>
<div class="movie-card" onclick="openDetail(<?= $mj ?>)">
  <?php if($cov): ?>
    <img src="<?= $cov ?>" alt="" class="movie-poster" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
    <div class="movie-poster-placeholder" style="display:none"><div class="icon">🎬</div><div class="ptitle"><?= htmlspecialchars($movie['movie_title']) ?></div></div>
  <?php else: ?>
    <div class="movie-poster-placeholder"><div class="icon">🎬</div><div class="ptitle"><?= htmlspecialchars($movie['movie_title']) ?></div></div>
  <?php endif; ?>
  <span class="card-status-badge <?= $bid ?>"><?= htmlspecialchars($movie['status']) ?></span>
  <?php if($rt): ?><span class="card-rating">★ <?= $rt ?></span><?php endif; ?>
  <div class="card-overlay">
    <div class="card-title"><?= htmlspecialchars($movie['movie_title']) ?></div>
    <div class="card-genre"><?= htmlspecialchars($movie['genre']) ?></div>
    <div class="card-actions">
      <button class="card-btn card-btn-play" onclick="event.stopPropagation();openDetail(<?= $mj ?>)">▶ Info</button>
      <button class="card-btn card-btn-edit" onclick="event.stopPropagation();openEditModal(<?= $mj ?>)">✎ Edit</button>
      <button class="card-btn card-btn-del"  onclick="event.stopPropagation();confirmDel(<?= $movie['watchlist_id'] ?>,'<?= $ts ?>')">✕</button>
    </div>
  </div>
</div>
<?php return ob_get_clean(); }
?>

<!-- NETFLIX ROWS -->
<div id="netflix-view">
<?php if(empty($movies)): ?>
  <div class="empty-state"><div class="big-icon">🎬</div><h3>Your vault is empty</h3><p>Click "+ Add Movie" to get started.</p></div>
<?php else: ?>
  <?php $rl=['Watching'=>'🔴 Currently Watching','Watched'=>'✅ Watched','Plan to Watch'=>'📌 Plan to Watch'];
  foreach($rl as $sk=>$label):
    if(empty($by_status[$sk]))continue;
    $rid='row-'.md5($sk); ?>
  <div class="section">
    <div class="section-header">
      <h2 class="section-title"><?= $label ?></h2>
      <span class="section-count"><?= count($by_status[$sk]) ?></span>
    </div>
    <div class="row-wrap">
      <button class="row-btn row-btn-left"  onclick="scrollRow('<?= $rid ?>',-1)">&#8249;</button>
      <div class="row" id="<?= $rid ?>">
        <?php foreach($by_status[$sk] as $m) echo renderCard($m); ?>
      </div>
      <button class="row-btn row-btn-right" onclick="scrollRow('<?= $rid ?>',1)">&#8250;</button>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- GRID VIEW -->
<div id="grid-view" style="display:none">
<?php if(empty($movies)): ?>
  <div class="empty-state"><div class="big-icon">🎬</div><h3>Your vault is empty</h3></div>
<?php else: ?>
  <div class="grid-section"><div class="movie-grid"><?php foreach($movies as $m) echo renderCard($m); ?></div></div>
<?php endif; ?>
</div>

<!-- ════════ MODALS ════════ -->

<!-- ADD MODAL -->
<div class="modal-backdrop" id="addModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Add to Vault</h2>
      <button class="modal-close" onclick="closeModal('addModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="add_movie" value="1">
        <div class="form-grid">
          <div class="form-group form-full">
            <label>Movie / Show Title</label>
            <input type="text" name="movie_title" placeholder="e.g. One Piece" required>
          </div>
          <div class="form-group">
            <label>Genre</label>
            <select name="genre" required>
              <option value="">Select...</option>
              <option>Action</option><option>Adventure</option><option>Animation</option><option>Anime</option>
              <option>Comedy</option><option>Crime</option><option>Documentary</option><option>Drama</option>
              <option>Fantasy</option><option>Fiction</option><option>Horror</option>
              <option>Romance</option><option>Sci-Fi</option><option>Thriller</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" required>
              <option value="Plan to Watch">Plan to Watch</option>
              <option value="Watching">Watching</option>
              <option value="Watched">Watched</option>
            </select>
          </div>
          <div class="form-group form-full">
            <label>Your Rating</label>
            <div class="star-picker" id="addStarPicker">
              <span class="star" onclick="setStars('add',1)">★</span>
              <span class="star" onclick="setStars('add',2)">★</span>
              <span class="star" onclick="setStars('add',3)">★</span>
              <span class="star" onclick="setStars('add',4)">★</span>
              <span class="star" onclick="setStars('add',5)">★</span>
            </div>
            <input type="hidden" name="rating" id="addRating" value="0">
          </div>

          <!-- ★ FILE UPLOAD ZONE ★ -->
          <div class="form-group form-full">
            <label>Cover Image — upload from your device</label>
            <div class="upload-zone" id="addZone"
                 ondragover="onDragOver(event,'addZone')"
                 ondragleave="onDragLeave('addZone')"
                 ondrop="onDrop(event,'addZone','addFile')">
              <input type="file" id="addFile" name="cover_file" accept="image/*"
                     onchange="onFileChosen(this,'addZone')">
              <div class="uz-icon" id="addZone_icon">📁</div>
              <div class="uz-label" id="addZone_label"><span>Click to choose a file</span> or drag &amp; drop here</div>
              <div class="uz-hint" id="addZone_hint">JPG · PNG · WEBP · GIF &nbsp;|&nbsp; Max 5 MB</div>
            </div>
          </div>

          <div class="form-group form-full">
            <label>Description</label>
            <textarea name="description" placeholder="Short synopsis or personal notes..."></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-red" style="width:100%;margin-top:16px;justify-content:center;padding:12px;">+ Add to Vault</button>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-backdrop" id="editModal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">Edit Movie</h2>
      <button class="modal-close" onclick="closeModal('editModal')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="edit_movie" value="1">
        <input type="hidden" name="id" id="editId">
        <div class="form-grid">
          <div class="form-group form-full">
            <label>Movie / Show Title</label>
            <input type="text" name="movie_title" id="editTitle" required>
          </div>
          <div class="form-group">
            <label>Genre</label>
            <select name="genre" id="editGenre">
              <option>Action</option><option>Adventure</option><option>Animation</option><option>Anime</option>
              <option>Comedy</option><option>Crime</option><option>Documentary</option><option>Drama</option>
              <option>Fantasy</option><option>Fiction</option><option>Horror</option>
              <option>Romance</option><option>Sci-Fi</option><option>Thriller</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="editStatus">
              <option value="Plan to Watch">Plan to Watch</option>
              <option value="Watching">Watching</option>
              <option value="Watched">Watched</option>
            </select>
          </div>
          <div class="form-group form-full">
            <label>Your Rating</label>
            <div class="star-picker" id="editStarPicker">
              <span class="star" onclick="setStars('edit',1)">★</span>
              <span class="star" onclick="setStars('edit',2)">★</span>
              <span class="star" onclick="setStars('edit',3)">★</span>
              <span class="star" onclick="setStars('edit',4)">★</span>
              <span class="star" onclick="setStars('edit',5)">★</span>
            </div>
            <input type="hidden" name="rating" id="editRating" value="0">
          </div>

          <!-- ★ FILE UPLOAD ZONE ★ -->
          <div class="form-group form-full">
            <label>Cover Image <em style="text-transform:none;color:#555;font-style:normal;">— leave empty to keep current</em></label>
            <div class="upload-zone" id="editZone"
                 ondragover="onDragOver(event,'editZone')"
                 ondragleave="onDragLeave('editZone')"
                 ondrop="onDrop(event,'editZone','editFile')">
              <input type="file" id="editFile" name="cover_file" accept="image/*"
                     onchange="onFileChosen(this,'editZone')">
              <div class="uz-icon" id="editZone_icon">📁</div>
              <div class="uz-label" id="editZone_label"><span>Click to choose a file</span> or drag &amp; drop here</div>
              <div class="uz-hint" id="editZone_hint">Upload a new image to replace the current cover</div>
            </div>
          </div>

          <div class="form-group form-full">
            <label>Description</label>
            <textarea name="description" id="editDesc" placeholder="Short synopsis or personal notes..."></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-red" style="width:100%;margin-top:16px;justify-content:center;padding:12px;">Save Changes</button>
      </form>
    </div>
  </div>
</div>

<!-- DETAIL MODAL -->
<div class="modal-backdrop" id="detailModal">
  <div class="detail-modal">
    <button class="modal-close" onclick="closeModal('detailModal')" style="position:absolute;top:12px;right:14px;z-index:10;background:rgba(0,0,0,.6);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">✕</button>
    <div class="detail-hero" id="detailHero">
      <div class="detail-hero-bg">🎬</div>
      <div class="detail-hero-overlay"></div>
      <div class="detail-hero-content">
        <h2 class="detail-title" id="dTitle">—</h2>
        <div class="detail-meta" id="dMeta"></div>
      </div>
    </div>
    <div class="detail-body">
      <div class="detail-stars" id="dStars"></div>
      <p class="detail-desc"  id="dDesc">No description available.</p>
      <div class="detail-actions" id="dActions"></div>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM -->
<div class="modal-backdrop" id="deleteModal">
  <div class="modal" style="width:min(400px,90vw)">
    <div class="confirm-modal">
      <div class="icon">🗑</div>
      <h3>Remove this film?</h3>
      <p id="delMsg">This will permanently remove the film from your vault.</p>
      <div class="confirm-actions">
        <button class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
        <a class="btn btn-red" id="delLink">Remove</a>
      </div>
    </div>
  </div>
</div>

<!-- CLEAR ALL CONFIRM -->
<div class="modal-backdrop" id="clearAllModal">
  <div class="modal" style="width:min(400px,90vw)">
    <div class="confirm-modal">
      <form method="POST">
        <input type="hidden" name="delete_all" value="1">
        <div class="icon">⚠️</div>
        <h3>Clear entire vault?</h3>
        <p>Permanently deletes ALL movies and their cover images. Cannot be undone.</p>
        <div class="confirm-actions">
          <button type="button" class="btn btn-outline" onclick="closeModal('clearAllModal')">Cancel</button>
          <button type="submit" class="btn btn-red">Clear All</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
/* VIEW */
function setView(v){
  document.getElementById('netflix-view').style.display=v==='netflix'?'block':'none';
  document.getElementById('grid-view').style.display   =v==='grid'?'block':'none';
  document.getElementById('btnNetflix').classList.toggle('active',v==='netflix');
  document.getElementById('btnGrid').classList.toggle('active',v==='grid');
  localStorage.setItem('cv_view',v);
}
(()=>{const s=localStorage.getItem('cv_view');if(s)setView(s);})();

window.addEventListener('scroll',()=>document.getElementById('navbar').classList.toggle('scrolled',scrollY>10));
function scrollRow(id,d){document.getElementById(id).scrollBy({left:d*600,behavior:'smooth'});}

/* MODALS */
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
document.querySelectorAll('.modal-backdrop').forEach(el=>el.addEventListener('click',e=>{if(e.target===el)closeModal(el.id);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-backdrop.open').forEach(m=>closeModal(m.id));});

function openAddModal(){
  resetZone('addZone','addFile');
  setStars('add',0);
  openModal('addModal');
}

function openEditModal(m){
  document.getElementById('editId').value    = m.watchlist_id;
  document.getElementById('editTitle').value = m.movie_title;
  document.getElementById('editDesc').value  = m.description||'';
  document.getElementById('editGenre').value = m.genre;
  document.getElementById('editStatus').value= m.status;
  setStars('edit',parseInt(m.rating)||0);
  resetZone('editZone','editFile');
  // Show existing cover thumbnail inside zone as hint
  if(m.cover_url){ showZonePreview('editZone','editFile',m.cover_url,false); }
  openModal('editModal');
}

function openDetail(m){
  const hero=document.getElementById('detailHero');
  hero.innerHTML='';
  if(m.cover_url){
    const img=document.createElement('img');img.src=m.cover_url;img.alt=m.movie_title;
    img.onerror=()=>{img.style.display='none';addBg();};hero.appendChild(img);
  }else{addBg();}
  function addBg(){const b=document.createElement('div');b.className='detail-hero-bg';b.textContent='🎬';hero.appendChild(b);}
  const ov=document.createElement('div');ov.className='detail-hero-overlay';hero.appendChild(ov);
  const ct=document.createElement('div');ct.className='detail-hero-content';hero.appendChild(ct);
  ct.innerHTML=`<h2 class="detail-title">${esc(m.movie_title)}</h2><div id="_dm" class="detail-meta"></div>`;
  [m.genre,m.status,m.date_added?new Date(m.date_added).getFullYear():''].filter(Boolean)
    .forEach(t=>{const s=document.createElement('span');s.className='detail-tag';s.textContent=t;ct.querySelector('#_dm').appendChild(s);});
  let st='';for(let i=1;i<=5;i++)st+=`<span class="star ${i<=(parseInt(m.rating)||0)?'filled':''}">★</span>`;
  document.getElementById('dStars').innerHTML=st;
  document.getElementById('dDesc').textContent=m.description||'No description available.';
  const mj=escA(JSON.stringify(m));
  document.getElementById('dActions').innerHTML=`
    <button class="btn btn-red" onclick="closeModal('detailModal');openEditModal(${mj})">✎ Edit</button>
    <button class="btn btn-outline" onclick="closeModal('detailModal');confirmDel(${m.watchlist_id},'${escA(m.movie_title)}')">🗑 Remove</button>`;
  openModal('detailModal');
}

function esc(s){const d=document.createElement('div');d.appendChild(document.createTextNode(s||''));return d.innerHTML;}
function escA(s){return(s||'').replace(/\\/g,'\\\\').replace(/"/g,'&quot;').replace(/'/g,"&#39;");}

/* STARS */
function setStars(px,val){
  document.getElementById(px+'Rating').value=val;
  document.querySelectorAll('#'+px+'StarPicker .star').forEach((s,i)=>{
    s.style.color=i<val?'var(--gold)':'var(--border)';s.classList.toggle('active',i<val);
  });
}

/* ── UPLOAD ZONE LOGIC ── */
function resetZone(zoneId, fileId){
  const z=document.getElementById(zoneId);
  // Remove any preview
  const pv=z.querySelector('.uz-preview'); if(pv)pv.remove();
  const rb=z.querySelector('.uz-remove');  if(rb)rb.remove();
  // Restore placeholders
  z.querySelector('.uz-icon') .style.display='';
  z.querySelector('.uz-label').style.display='';
  z.querySelector('.uz-hint') .style.display='';
  // Clear file input
  const f=document.getElementById(fileId); if(f) f.value='';
}

function showZonePreview(zoneId, fileId, src, allowRemove=true){
  const z=document.getElementById(zoneId);
  // Hide placeholders
  z.querySelector('.uz-icon') .style.display='none';
  z.querySelector('.uz-label').style.display='none';
  z.querySelector('.uz-hint') .style.display='none';
  // Build preview
  let pv=z.querySelector('.uz-preview');
  if(!pv){pv=document.createElement('div');pv.className='uz-preview';z.appendChild(pv);}
  pv.innerHTML=`<img src="${src}" alt="cover" onerror="this.src=''">
    <div class="uz-change">📁 Click to choose a different image</div>`;
  // Remove button
  if(allowRemove){
    let rb=z.querySelector('.uz-remove');
    if(!rb){rb=document.createElement('button');rb.type='button';rb.className='uz-remove';z.appendChild(rb);}
    rb.textContent='✕';
    rb.onclick=e=>{e.stopPropagation();resetZone(zoneId,fileId);};
  }
}

function onFileChosen(input, zoneId){
  const file=input.files&&input.files[0];
  if(!file)return;
  if(!file.type.startsWith('image/')){alert('Please choose an image file (JPG, PNG, WEBP, GIF).');input.value='';return;}
  if(file.size>5*1024*1024){alert('Image must be under 5 MB.');input.value='';return;}
  const reader=new FileReader();
  reader.onload=e=>showZonePreview(zoneId,input.id,e.target.result,true);
  reader.readAsDataURL(file);
}

function onDragOver(e,zoneId){e.preventDefault();document.getElementById(zoneId).classList.add('drag-over');}
function onDragLeave(zoneId){document.getElementById(zoneId).classList.remove('drag-over');}
function onDrop(e,zoneId,fileId){
  e.preventDefault();
  document.getElementById(zoneId).classList.remove('drag-over');
  const file=e.dataTransfer.files[0];if(!file)return;
  const inp=document.getElementById(fileId);
  const dt=new DataTransfer();dt.items.add(file);inp.files=dt.files;
  onFileChosen(inp,zoneId);
}

/* DELETE */
function confirmDel(id,title){
  document.getElementById('delMsg').textContent=`Remove "${title}" from your vault?`;
  document.getElementById('delLink').href=`?delete_id=${id}`;
  openModal('deleteModal');
}

/* SEARCH */
let _dt;function debounce(){clearTimeout(_dt);_dt=setTimeout(()=>document.getElementById('filterForm').submit(),400);}

/* TOAST */
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),3000);}
<?php if(isset($_GET['added'])): ?>toast('🎬 Movie added to your vault!');<?php endif; ?>
<?php if(isset($_GET['edited'])): ?>toast('✅ Movie updated successfully!');<?php endif; ?>
</script>
</body>
</html>