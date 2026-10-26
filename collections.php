<?php
/** Cinema Vault — collections overview. Opening a collection shows it in the vault (index.php?collection=ID). */
require __DIR__ . '/includes/movies.php';
if (!cv_user_id()) cv_redirect('homepage.php');
$user_id = cv_user_id(); $username = (string)($_SESSION['username'] ?? ''); $initial = mb_strtoupper(mb_substr($username, 0, 1));
$collections = cv_user_collections($user_id);
$collReturn = 'collections.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collections - Cinema Vault</title>
<?php include 'includes/head.php'; ?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/vault.css">
</head>
<body class="dash">
<?php include 'includes/icons.php'; ?>
<?php $active = 'collections'; include 'includes/app_nav.php'; ?>
<main class="dash-main">
<?php include 'includes/subnav.php'; ?>
<header class="dash-hero">
  <div class="dash-hero-text">
    <p class="dash-kicker">Organize</p>
    <h1 class="dash-title">Collections</h1>
    <p class="dash-sub"><?= count($collections) ?: 'No' ?> collection<?= count($collections) === 1 ? '' : 's' ?>. A film can live in as many as you like.</p>
  </div>
  <div class="hero-buttons"><button type="button" class="btn btn-gold btn-lg" data-new-collection><svg class="i"><use href="#i-plus"/></svg> New collection</button></div>
</header>

<?php if (!$collections): ?>
  <div class="empty-state">
    <svg class="i empty-icon"><use href="#i-folder"/></svg>
    <h3>No collections yet</h3>
    <p>Group films however you like: a director, a mood, “Horror Night”, “Movies to watch with friends”.</p>
    <button type="button" class="btn btn-gold btn-lg" data-new-collection><svg class="i"><use href="#i-plus"/></svg> Create your first collection</button>
  </div>
<?php else: ?>
  <ul class="coll-grid">
    <?php foreach ($collections as $c): ?>
    <li class="coll-card">
      <a class="coll-link" href="index.php?collection=<?= $c['id'] ?>" aria-label="<?= h($c['name']) ?>, <?= $c['count'] ?> films">
        <span class="coll-cover coll-cover-<?= max(1, min(4, count($c['covers']))) ?>">
          <?php foreach ($c['covers'] as $url): ?><img src="<?= h($url) ?>" alt="" loading="lazy" data-hide-on-error><?php endforeach; ?>
          <?php if (!$c['covers']): ?><svg class="i"><use href="#i-folder"/></svg><?php endif; ?>
        </span>
        <span class="coll-info">
          <strong><?= h($c['name']) ?></strong>
          <small><?= $c['count'] ?> <?= $c['count'] === 1 ? 'film' : 'films' ?></small>
          <?php if ($c['description'] !== ''): ?><span class="coll-desc"><?= h($c['description']) ?></span><?php endif; ?>
        </span>
      </a>
      <div class="coll-actions">
        <button type="button" class="btn btn-soft" data-edit-collection data-id="<?= $c['id'] ?>" data-name="<?= h($c['name']) ?>" data-description="<?= h($c['description']) ?>"><svg class="i"><use href="#i-edit"/></svg> Edit</button>
        <button type="button" class="btn btn-ghost-danger" data-delete-collection data-id="<?= $c['id'] ?>" data-name="<?= h($c['name']) ?>" aria-label="Delete <?= h($c['name']) ?>"><svg class="i"><use href="#i-trash"/></svg></button>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
</main>
<?php include 'includes/app_footer.php'; ?>
<?php include 'includes/collection_modals.php'; include 'includes/shared_modals.php'; ?>
<script nonce="<?= h(cv_nonce()) ?>">window.CV = <?= json_encode(['flash' => cv_pull_flash()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/core.js"></script>
<script src="assets/js/vault.js"></script>
</body>
</html>
