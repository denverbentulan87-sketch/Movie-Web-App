<?php
/** Cinema Vault — advanced statistics. All figures are computed from the signed-in user's rows only. */
require __DIR__ . '/includes/movies.php';
require __DIR__ . '/includes/statistics.php';
if (!cv_user_id()) cv_redirect('homepage.php');
$user_id = cv_user_id(); $username = (string)($_SESSION['username'] ?? ''); $initial = mb_strtoupper(mb_substr($username, 0, 1));

$movies = cv_all_movies($user_id);
$s = cv_stats($movies, cv_all_history($user_id));
$hours = $s['watch_minutes'] !== null ? (int)round($s['watch_minutes'] / 60) : null;
$monthMax = max(1, max($s['monthly']));
$genreMax = $s['genres'] ? max($s['genres']) : 1;
$ratingMax = max(1, max($s['rating_dist']));
$stars = fn($n) => str_repeat('★', $n);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stats - Cinema Vault</title>
<?php include 'includes/head.php'; ?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/vault.css">
</head>
<body class="dash">
<?php include 'includes/icons.php'; ?>
<?php $active = 'stats'; include 'includes/app_nav.php'; ?>
<main class="dash-main">
<?php include 'includes/subnav.php'; ?>
<header class="dash-hero">
  <div class="dash-hero-text">
    <p class="dash-kicker">Your numbers</p>
    <h1 class="dash-title">Your movie journey</h1>
    <p class="dash-sub">Based on <?= $s['total'] ?> film<?= $s['total'] === 1 ? '' : 's' ?> and <?= $s['history_total'] ?> recorded watch<?= $s['history_total'] === 1 ? '' : 'es' ?>.</p>
  </div>
</header>

<?php if ($s['total'] === 0): ?>
  <div class="empty-state"><svg class="i empty-icon"><use href="#i-chart"/></svg><h3>Nothing to measure yet</h3><p>Add a few films and mark some as watched. Your stats will appear here.</p><a class="btn btn-gold btn-lg" href="index.php?add=1"><svg class="i"><use href="#i-plus"/></svg> Add a movie</a></div>
<?php else: ?>

<section class="journey" aria-label="Highlights">
  <div class="journey-tile"><span class="journey-n"><?= $s['watched'] ?></span><span class="journey-l">Movies watched</span></div>
  <div class="journey-tile"><span class="journey-n"><?= $s['avg_rating'] !== null ? $s['avg_rating'] . ' <i>★</i>' : '—' ?></span><span class="journey-l">Average rating</span><small><?= $s['rated_count'] ? 'from ' . $s['rated_count'] . ' rated film' . ($s['rated_count'] === 1 ? '' : 's') : 'Rate a film to see this' ?></small></div>
  <div class="journey-tile"><span class="journey-n"><?= $hours !== null ? $hours . ' <i>hrs</i>' : '—' ?></span><span class="journey-l">Watch time</span>
    <small><?php if ($hours === null): ?>Add runtimes to see this<?php elseif ($s['watch_time_missing']): ?>Counts <?= $s['watch_time_covered'] ?> of <?= $s['watch_time_covered'] + $s['watch_time_missing'] ?> watched films that have a runtime<?php else: ?>Includes every rewatch<?php endif; ?></small></div>
  <div class="journey-tile"><span class="journey-n"><?= $s['completed_pct'] ?><i>%</i></span><span class="journey-l">Vault completed</span><small><?= $s['watched'] ?> of <?= $s['total'] ?> watched</small></div>
</section>

<section class="figures" aria-label="Details">
  <dl class="fig-grid">
    <div><dt>Total movies</dt><dd><?= $s['total'] ?></dd></div>
    <div><dt>Watching now</dt><dd><?= $s['watching'] ?></dd></div>
    <div><dt>Planned</dt><dd><?= $s['planned'] ?></dd></div>
    <div><dt>Favorites</dt><dd><?= $s['favorites'] ?></dd></div>
    <div><dt>Total watches</dt><dd><?= $s['watch_count'] ?></dd></div>
    <div><dt>Watched this month</dt><dd><?= $s['watched_this_month'] ?></dd></div>
    <div><dt>Watched this year</dt><dd><?= $s['watched_this_year'] ?></dd></div>
    <div><dt>Most watched genre</dt><dd><?= $s['top_genre'] ? h($s['top_genre']['name']) . '<small>' . $s['top_genre']['watches'] . ' watches</small>' : '<span class="na">Not enough data</span>' ?></dd></div>
    <div><dt>Highest-rated genre</dt><dd><?= $s['best_genre'] ? h($s['best_genre']['name']) . '<small>' . $s['best_genre']['avg'] . ' ★ over ' . $s['best_genre']['n'] . ' rated</small>' : '<span class="na">Rate some films</span>' ?></dd></div>
    <div><dt>Most watched movie</dt><dd><?= $s['most_watched'] ? h($s['most_watched']['movie_title']) . '<small>' . $s['most_watched']['watch_count'] . '×</small>' : '<span class="na">Nothing watched yet</span>' ?></dd></div>
    <div><dt>Most rewatched movie</dt><dd><?= $s['most_rewatched'] ? h($s['most_rewatched']['movie_title']) . '<small>rewatched ' . ($s['most_rewatched']['watch_count'] - 1) . '×</small>' : '<span class="na">No rewatches yet</span>' ?></dd></div>
  </dl>
</section>

<div class="chart-grid">
  <section class="panel chart" aria-labelledby="c1">
    <h2 class="panel-title" id="c1">Watch activity <small>last 12 months</small></h2>
    <?php if (array_sum($s['monthly']) === 0): ?><p class="taste-empty">No watches recorded in the last 12 months.</p><?php else: ?>
    <div class="cols" role="img" aria-label="Watches per month for the last 12 months">
      <?php foreach ($s['monthly'] as $ym => $n): $d = DateTime::createFromFormat('!Y-m', $ym); ?>
      <div class="col" title="<?= h($d->format('F Y')) ?>: <?= $n ?> watch<?= $n === 1 ? '' : 'es' ?>">
        <span class="col-v"><?= $n ?: '' ?></span>
        <span class="col-bar"><i style="--h:<?= round($n / $monthMax * 100) ?>%"></i></span>
        <span class="col-l"><?= h($d->format('M')) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <table class="sr-only"><caption>Watches per month</caption><tbody><?php foreach ($s['monthly'] as $ym => $n): ?><tr><th><?= h($ym) ?></th><td><?= $n ?></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </section>

  <section class="panel chart" aria-labelledby="c2">
    <h2 class="panel-title" id="c2">Genres <small>films in your vault</small></h2>
    <?php if (!$s['genres']): ?><p class="taste-empty">Genres will appear here.</p><?php else: ?>
    <ul class="hbars">
      <?php foreach (array_slice($s['genres'], 0, 8, true) as $g => $n): ?>
      <li title="<?= h($g) ?>: <?= $n ?>"><a href="index.php?genre=<?= urlencode($g) ?>"><span class="hb-name"><?= h($g) ?></span><span class="hb-bar"><i style="--w:<?= round($n / $genreMax * 100) ?>%"></i></span><span class="hb-n"><?= $n ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>

  <section class="panel chart" aria-labelledby="c3">
    <h2 class="panel-title" id="c3">Your ratings <small><?= $s['rated_count'] ?> rated</small></h2>
    <?php if (!$s['rated_count']): ?><p class="taste-empty">Rate a film to see how you score things.</p><?php else: ?>
    <ul class="hbars">
      <?php for ($r = 5; $r >= 1; $r--): $n = $s['rating_dist'][$r]; ?>
      <li title="<?= $r ?> stars: <?= $n ?>"><a href="index.php?rating_min=<?= $r ?>"><span class="hb-name" aria-label="<?= $r ?> stars"><?= $stars($r) ?></span><span class="hb-bar"><i style="--w:<?= round($n / $ratingMax * 100) ?>%"></i></span><span class="hb-n"><?= $n ?></span></a></li>
      <?php endfor; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
</main>
<?php include 'includes/app_footer.php'; ?>
<?php include 'includes/shared_modals.php'; ?>
<script nonce="<?= h(cv_nonce()) ?>">window.CV = <?= json_encode(['flash' => cv_pull_flash()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/core.js"></script>
<script src="assets/js/vault.js"></script>
</body>
</html>
