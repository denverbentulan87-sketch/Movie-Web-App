<?php
/**
 * Remove one movie (movie_id) from the signed-in user's vault, together with its watch
 * history, tag links and collection links. POST only, with a CSRF token — a plain link
 * such as delete.php?id=5 does nothing (it can't be triggered from another page).
 */
require __DIR__ . '/includes/action_runner.php';

cv_run_action('delete.php', function (int $uid): array {
    cv_delete_movie($uid, (int)($_POST['movie_id'] ?? 0));
    return ['message' => 'Removed from your vault.', 'deleted' => true, 'reload' => true];
});
