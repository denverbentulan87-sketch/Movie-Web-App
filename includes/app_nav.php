<?php
/**
 * Shared top navigation for signed-in pages.
 * Expects: $active ('vault'|'collections'|'stats'|'wrapped'), $username, optional $showViewToggle (bool).
 */
$initial = mb_strtoupper(mb_substr($username, 0, 1));
$showViewToggle = $showViewToggle ?? false;
?>
<nav class="app-nav" id="navbar" aria-label="Main">
  <a class="brand" href="index.php" aria-label="Cinema Vault — your vault"><svg class="brand-mark"><use href="#i-reel"/></svg><span>CINEMA VAULT</span></a>

  <div class="nav-right">
    <?php if ($showViewToggle): ?>
    <div class="view-toggle" role="group" aria-label="Layout">
      <button type="button" class="view-btn active" id="btnNetflix" data-view="netflix" aria-label="Rows view" title="Rows"><svg class="i"><use href="#i-rows"/></svg></button>
      <button type="button" class="view-btn" id="btnGrid" data-view="grid" aria-label="Grid view" title="Grid"><svg class="i"><use href="#i-grid"/></svg></button>
    </div>
    <?php endif; ?>
    <button type="button" class="btn btn-ghost" data-open="surpriseModal" id="surpriseBtn"><span aria-hidden="true">🎲</span><span class="lbl">Surprise Me</span></button>
    <?php if ($showViewToggle): ?>
    <button type="button" class="btn btn-gold" data-open-add><svg class="i"><use href="#i-plus"/></svg><span class="lbl">Add Movie</span></button>
    <?php else: ?>
    <a class="btn btn-gold" href="index.php?add=1"><svg class="i"><use href="#i-plus"/></svg><span class="lbl">Add Movie</span></a>
    <?php endif; ?>
    <?php if ($showViewToggle): ?>
    <button type="button" class="btn btn-ghost-danger" data-open="clearAllModal" title="Clear all"><svg class="i"><use href="#i-trash"/></svg><span class="lbl">Clear All</span></button>
    <?php endif; ?>
    <span class="nav-sep" aria-hidden="true"></span>
    <span class="user-pill user-pill-menu" title="Signed in as <?= h($username) ?>">
      <span class="avatar"><?= h($initial) ?></span>
      <span class="user-name"><?= h($username) ?></span>
      <button type="button" class="pill-logout" data-open="logoutModal" aria-label="Log out"><svg class="i"><use href="#i-logout"/></svg><span class="lbl">Log out</span></button>
    </span>
  </div>
</nav>
