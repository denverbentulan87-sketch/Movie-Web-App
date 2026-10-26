<?php
/**
 * JSON detail for one movie (history, notes, tags, collections) used by the detail modal.
 * Read-only; scoped to the signed-in user, and a movie that is missing or belongs to
 * somebody else returns the same 404.
 */
require __DIR__ . '/includes/movies.php';
$uid = cv_require_login();

try {
    cv_json(['ok' => true] + cv_movie_bundle($uid, (int)($_GET['id'] ?? 0)));
} catch (CvInputError $e) {
    cv_json(['ok' => false, 'error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 422);
} catch (Throwable $e) {
    error_log('movie.php: ' . $e->getMessage());
    cv_json(['ok' => false, 'error' => 'Something went wrong on our side. Please try again.'], 500);
}
