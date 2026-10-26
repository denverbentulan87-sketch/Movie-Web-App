<?php
/**
 * "Surprise Me": returns one random Plan-to-Watch movie of the signed-in user as JSON.
 * Read-only. Optional query params: genre, priority, min_rating, max_runtime, exclude (comma separated ids).
 */
require __DIR__ . '/includes/movies.php';
$uid = cv_require_login();

try {
    $exclude = array_filter(explode(',', (string)($_GET['exclude'] ?? '')), 'ctype_digit');
    $res = cv_surprise($uid, [
        'genre'       => is_string($_GET['genre'] ?? null) ? mb_substr($_GET['genre'], 0, 100) : '',
        'priority'    => (string)($_GET['priority'] ?? ''),
        'min_rating'  => (int)($_GET['min_rating'] ?? 0),
        'max_runtime' => (int)($_GET['max_runtime'] ?? 0),
    ], $exclude);
    cv_json(['ok' => true] + $res);
} catch (Throwable $e) {
    error_log('surprise.php: ' . $e->getMessage());
    cv_json(['ok' => false, 'error' => 'Something went wrong on our side. Please try again.'], 500);
}
