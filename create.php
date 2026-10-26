<?php
/**
 * Add a movie to the signed-in user's vault.
 * POST only, with a CSRF token. Fields: movie_title, genre, status, rating, description,
 * cover (file) or cover_url, plus the optional extras handled by cv_parse_movie_input().
 * Browser form → flash + redirect to the dashboard; JavaScript (fetch) → JSON.
 */
require __DIR__ . '/includes/action_runner.php';

cv_run_action('create.php', function (int $uid): array {
    $id = cv_add_movie($uid, $_POST);
    return ['message' => 'Movie added to your vault.', 'movie_id' => $id, 'reload' => true];
});
