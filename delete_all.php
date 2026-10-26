<?php
/**
 * Clear the signed-in user's whole vault. Only that user's movies are removed; tags and
 * collections themselves are kept. POST only, with a CSRF token.
 */
require __DIR__ . '/includes/action_runner.php';

cv_run_action('delete_all.php', function (int $uid): array {
    $n = cv_delete_all_movies($uid);
    return ['message' => $n ? 'Your vault has been cleared.' : 'Your vault was already empty.', 'reload' => true];
});
