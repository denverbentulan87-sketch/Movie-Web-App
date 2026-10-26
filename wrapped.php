<?php
/** Cinema Vault — yearly "Movie Wrapped". Private: shown only to the signed-in user; the share card is drawn in the browser. */
require __DIR__ . '/includes/movies.php';
require __DIR__ . '/includes/statistics.php';
if (!cv_user_id()) cv_redirect('homepage.php');
$user_id = cv_user_id(); $username = (string)($_SESSION['username'] ?? ''); $initial = mb_strtoupper(mb_substr($username, 0, 1));

$movies = cv_all_movies($user_id);
$history = cv_all_history($user_id);
$years = cv_wrapped_years($history);
$thisYear = (int)date('Y');
$year = (int)($_GET['year'] ?? 0);
if (!in_array($year, $years, true)) $year = $years[0] ?? $thisYear;
$w = cv_wrapped($movies, $history, $year);
$hours = $w['minutes'] !== null ? (int)round($w['minutes'] / 60) : null;
$monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
$mMax = max(1, max($w['months']));
$share = ['year' => $year, 'movies' => $w['movies'], 'hours' => $hours, 'avg' => $w['avg_rating'],
          'topGenre' => $w['top_genre']['name'] ?? null, 'mostWatched' => $w['most_watched']['movie']['movie_title'] ?? null,
          'highestRated' => $w['highest_rated']['movie']['movie_title'] ?? null];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Movie Wrapped - Cinema Vault</title>
<?php include 'includes/head.php'; ?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/vault.css">
</head>
<body class="dash">
<?php include 'includes/icons.php'; ?>
<?php $active = 'wrapped'; include 'includes/app_nav.php'; ?>
<main class="dash-main">
<?php include 'includes/subnav.php'; ?>

<form method="GET" class="wrapped-year">
  <label for="wy">Year</label>
  <select class="select" id="wy" name="year" data-autosubmit>
    <?php foreach ($years ?: [$thisYear] as $y): ?><option <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
  </select>
  <noscript><button class="btn btn-soft" type="submit">Show</button></noscript>
</form>

<?php if ($w['watches'] === 0): ?>
  <div class="empty-state"><svg class="i empty-icon"><use href="#i-award"/></svg><h3>No watches in <?= $year ?> yet</h3><p>Mark films as watched and your year in movies will build itself here.</p><a class="btn btn-gold btn-lg" href="index.php">Open my vault</a></div>
<?php else: ?>
<article class="wrapped" aria-labelledby="wH">
  <p class="wrapped-kicker">Cinema Vault</p>
  <h1 class="wrapped-title" id="wH">Your <?= $year ?> Movie Wrapped</h1>

  <div class="wrapped-big">
    <div><span><?= $w['movies'] ?></span><small><?= $w['movies'] === 1 ? 'Movie watched' : 'Movies watched' ?></small></div>
    <?php if ($hours !== null): ?><div><span><?= $hours ?></span><small>Hours watched</small></div><?php endif; ?>
    <?php if ($w['avg_rating'] !== null): ?><div><span><?= $w['avg_rating'] ?><i>★</i></span><small>Average rating</small></div><?php endif; ?>
  </div>
  <?php if ($w['watches'] > $w['movies']): ?><p class="wrapped-note"><?= $w['watches'] ?> watches in total, rewatches included.</p><?php endif; ?>
  <?php if ($hours !== null && $w['watch_time_missing']): ?><p class="wrapped-note">Hours count only the <?= $w['watch_time_covered'] ?> of <?= $w['watch_time_covered'] + $w['watch_time_missing'] ?> watches whose film has a runtime.</p><?php endif; ?>

  <dl class="wrapped-facts">
    <?php if ($w['top_genre']): ?><div><dt>Top genre</dt><dd><?= h($w['top_genre']['name']) ?><small><?= $w['top_genre']['count'] ?> watch<?= $w['top_genre']['count'] === 1 ? '' : 'es' ?></small></dd></div><?php endif; ?>
    <?php if ($w['most_watched']): ?><div><dt>Most watched</dt><dd><?= h($w['most_watched']['movie']['movie_title']) ?><small><?= $w['most_watched']['score'] ?>×</small></dd></div><?php endif; ?>
    <?php if ($w['highest_rated']): ?><div><dt>Highest rated</dt><dd><?= h($w['highest_rated']['movie']['movie_title']) ?><small><?= str_repeat('★', $w['highest_rated']['score']) ?></small></dd></div><?php endif; ?>
    <div><dt>Most rewatched</dt><dd><?= $w['most_rewatched'] ? h($w['most_rewatched']['movie']['movie_title']) . '<small>' . $w['most_rewatched']['score'] . ' rewatch' . ($w['most_rewatched']['score'] === 1 ? '' : 'es') . '</small>' : '<span class="na">No rewatches this year</span>' ?></dd></div>
    <?php if ($w['busiest_month']): ?><div><dt>Busiest month</dt><dd><?= h(DateTime::createFromFormat('!n', (string)$w['busiest_month']['month'])->format('F')) ?><small><?= $w['busiest_month']['count'] ?> watch<?= $w['busiest_month']['count'] === 1 ? '' : 'es' ?></small></dd></div><?php endif; ?>
  </dl>

  <div class="cols cols-sm" role="img" aria-label="Watches per month in <?= $year ?>">
    <?php foreach ($w['months'] as $m => $n): ?>
    <div class="col" title="<?= $monthNames[$m] ?>: <?= $n ?>"><span class="col-v"><?= $n ?: '' ?></span><span class="col-bar"><i style="--h:<?= round($n / $mMax * 100) ?>%"></i></span><span class="col-l"><?= $monthNames[$m][0] ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($w['genres']): ?>
  <ul class="wrapped-genres"><?php foreach ($w['genres'] as $g => $n): ?><li><?= h($g) ?> <b><?= $n ?></b></li><?php endforeach; ?></ul>
  <?php endif; ?>
</article>

<section class="share-box" aria-labelledby="shH">
  <h2 class="panel-title" id="shH">Share your year</h2>
  <p>Creates a picture on your device. Nothing is uploaded and there is no public link. It never includes your username or notes.</p>
  <label class="check"><input type="checkbox" id="shareTitles"> Include movie titles (most watched, highest rated)</label>
  <button type="button" class="btn btn-gold" id="wrappedShare"><svg class="i"><use href="#i-share"/></svg> Save as image</button>
  <script type="application/json" id="wrappedData"><?= json_encode($share, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</section>
<?php endif; ?>
</main>
<?php include 'includes/app_footer.php'; ?>
<?php include 'includes/shared_modals.php'; ?>
<script nonce="<?= h(cv_nonce()) ?>">window.CV = <?= json_encode(['flash' => cv_pull_flash()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/core.js"></script>
<script src="assets/js/vault.js"></script>
</body>
</html>
