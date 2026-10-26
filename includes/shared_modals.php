<?php /* Modals available on every signed-in page: Surprise Me, Log out, toast. Expects $username, $initial. */
$surpriseGenres = array_map(fn($r) => $r['genre'], db_all("SELECT DISTINCT genre FROM movie_watchlist WHERE user_id = ? AND status = 'Plan to Watch' AND genre IS NOT NULL AND genre <> '' ORDER BY genre", 'i', [cv_user_id()]));
?>
<!-- SURPRISE ME -->
<div class="modal-backdrop" id="surpriseModal" role="dialog" aria-modal="true" aria-labelledby="surpriseH">
  <div class="modal surprise-modal">
    <div class="modal-header">
      <h2 class="modal-title" id="surpriseH"><span aria-hidden="true">🎲</span> Surprise Me</h2>
      <button type="button" class="modal-close" data-close aria-label="Close"><svg class="i"><use href="#i-x"/></svg></button>
    </div>
    <div class="modal-body">
      <p class="surprise-lead">A random pick from your Plan to Watch list.</p>
      <form class="surprise-filters" id="surpriseFilters">
        <div class="field"><label for="sGenre">Genre</label>
          <select id="sGenre" name="genre"><option value="">Any genre</option><?php foreach ($surpriseGenres as $g): ?><option><?= h($g) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="sPriority">Priority</label>
          <select id="sPriority" name="priority"><option value="">Any priority</option><option value="High">Must watch (High)</option><option value="Normal">Next (Normal)</option><option value="Low">Someday (Low)</option></select></div>
        <div class="field"><label for="sRuntime">Runtime</label>
          <select id="sRuntime" name="max_runtime"><option value="">Any length</option><option value="90">Up to 90 min</option><option value="120">Up to 2 hours</option><option value="150">Up to 2½ hours</option></select></div>
        <div class="field"><label for="sRating">Rating</label>
          <select id="sRating" name="min_rating"><option value="">Any rating</option><option value="3">3★ and up</option><option value="4">4★ and up</option><option value="5">5★ only</option></select></div>
      </form>
      <p class="surprise-note">Runtime and rating filters only match films where you've filled those in.</p>
      <div class="surprise-result" id="surpriseResult" aria-live="polite"></div>
      <div class="surprise-actions">
        <button type="button" class="btn btn-gold btn-lg" id="surpriseGo"><span aria-hidden="true">🎲</span> <span id="surpriseGoLabel">Pick a movie</span></button>
      </div>
    </div>
  </div>
</div>

<!-- LOGOUT CONFIRM -->
<div class="modal-backdrop" id="logoutModal" role="alertdialog" aria-modal="true" aria-labelledby="logoutH">
  <div class="modal modal-sm">
    <form method="POST" action="logout.php" class="confirm">
      <?= csrf_field() ?>
      <div class="confirm-icon"><svg class="i"><use href="#i-logout"/></svg></div>
      <h3 id="logoutH">Leaving so soon?</h3>
      <div class="user-badge"><span class="avatar"><?= h($initial) ?></span><span>Signed in as <strong><?= h($username) ?></strong></span></div>
      <p>Your watchlist is saved and will be here when you come back.</p>
      <div class="confirm-actions">
        <button type="button" class="btn btn-soft" data-close>Stay and watch</button>
        <button type="submit" class="btn btn-gold"><svg class="i"><use href="#i-logout"/></svg> Log out</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
