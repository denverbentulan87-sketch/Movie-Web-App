<?php /* Section tabs under the page header. Expects $active. */
$tabs = ['vault' => ['index.php', 'film', 'Vault'], 'collections' => ['collections.php', 'folder', 'Collections'],
         'stats' => ['stats.php', 'chart', 'Stats'], 'wrapped' => ['wrapped.php', 'award', 'Wrapped']]; ?>
<nav class="subnav" aria-label="Sections">
  <?php foreach ($tabs as $k => [$href, $icon, $label]): ?>
    <a href="<?= h($href) ?>" class="subnav-link<?= $active === $k ? ' is-on' : '' ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><svg class="i"><use href="#i-<?= $icon ?>"/></svg><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
