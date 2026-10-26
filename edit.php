<?php
/**
 * Edit one of the signed-in user's movies (movie_id). A movie that is missing or belongs
 * to somebody else gets the same 404. POST only, with a CSRF token.
 * Browser form → flash + redirect to the dashboard; JavaScript (fetch) → JSON.
 */
require __DIR__ . '/includes/action_runner.php';

cv_run_action('edit.php', function (int $uid): array {
    $mid = (int)($_POST['movie_id'] ?? 0);
    cv_edit_movie($uid, $mid, $_POST);
    return ['message' => 'Changes saved.', 'movie_id' => $mid, 'reload' => true];
});
