<?php
/**
 * RETIRED. This endpoint used to run `DELETE FROM movie_watchlist` with no login
 * check and no user filter, so anybody could wipe every user's vault with a
 * single request. Clearing a vault now lives in actions.php (POST + CSRF +
 * restricted to the signed-in user's own rows).
 */
header('Location: index.php');
exit;
