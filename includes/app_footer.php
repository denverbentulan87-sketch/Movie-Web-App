<?php /* Footer with logout. Expects $username, $initial. */ ?>
<footer class="dash-footer">
  <div class="dash-footer-user">
    <span class="user-pill" title="Signed in as <?= h($username) ?>">
      <span class="avatar"><?= h($initial) ?></span>
      <span><strong><?= h($username) ?></strong></span>
    </span>
    <button type="button" class="btn btn-logout" data-open="logoutModal"><svg class="i"><use href="#i-logout"/></svg> Log out</button>
  </div>
</footer>
