<?php
/**
 * Cinema Vault — the one place that changes data.
 *
 * Every request must be:  POST  +  signed in  +  carrying a valid CSRF token.
 * Each handler works only on rows owned by the signed-in user (see includes/movies.php).
 *
 * Forms post here normally (flash message + redirect back); the JavaScript posts the
 * same fields with fetch() and receives JSON, including a refreshed copy of the movie.
 */
require __DIR__ . '/includes/movies.php';

$uid  = cv_require_login();
$ajax = cv_is_ajax();
$back = cv_safe_return($_POST['return_to'] ?? '');

function cv_fail(string $msg, int $status, bool $ajax, string $back): never {
    if ($ajax) cv_json(['ok' => false, 'error' => $msg], $status);
    cv_flash($msg, 'warn');
    cv_redirect($back);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    cv_fail('That link can not be opened directly.', 405, $ajax, 'index.php');
}
if (!csrf_valid()) {
    cv_fail('Your session expired. Please reload the page and try again.', 403, $ajax, $back);
}

$mid = (int)($_POST['movie_id'] ?? 0);

/** action name => handler. Each returns ['message' => string, 'movie_id' => ?int, 'deleted' => ?bool, 'reload' => ?bool, …extra]. */
$handlers = [
    /* ── movies ── */
    'movie_add' => function () use ($uid) {
        $id = cv_add_movie($uid, $_POST);
        return ['message' => 'Movie added to your vault.', 'movie_id' => $id, 'reload' => true];
    },
    'movie_edit' => function () use ($uid, $mid) {
        cv_edit_movie($uid, $mid, $_POST);
        return ['message' => 'Changes saved.', 'movie_id' => $mid, 'reload' => true];
    },
    'movie_delete' => function () use ($uid, $mid) {
        cv_delete_movie($uid, $mid);
        return ['message' => 'Removed from your vault.', 'deleted' => true, 'reload' => true];
    },
    'movies_delete_all' => function () use ($uid) {
        $n = cv_delete_all_movies($uid);
        return ['message' => $n ? 'Your vault has been cleared.' : 'Your vault was already empty.', 'reload' => true];
    },
    'movie_favorite' => function () use ($uid, $mid) {
        $on = ($_POST['value'] ?? '') === '1';
        cv_set_favorite($uid, $mid, $on);
        return ['message' => $on ? 'Added to favorites.' : 'Removed from favorites.', 'movie_id' => $mid];
    },
    'movie_priority' => function () use ($uid, $mid) {
        cv_set_priority($uid, $mid, (string)($_POST['priority'] ?? ''));
        return ['message' => 'Priority updated.', 'movie_id' => $mid, 'reload' => true];
    },
    'movie_status' => function () use ($uid, $mid) {
        cv_change_status($uid, $mid, (string)($_POST['status'] ?? ''));
        return ['message' => 'Status updated.', 'movie_id' => $mid, 'reload' => true];
    },
    'movie_notes' => function () use ($uid, $mid) {
        cv_set_notes($uid, $mid, $_POST['personal_notes'] ?? '', ($_POST['notes_spoiler'] ?? '') === '1');
        $empty = trim((string)($_POST['personal_notes'] ?? '')) === '';
        return ['message' => $empty ? 'Notes cleared.' : 'Notes saved.', 'movie_id' => $mid];
    },

    /* ── watching / history ── */
    'movie_watch' => function () use ($uid, $mid) {
        $rating = cv_rating($_POST['rating'] ?? 0);
        $notes  = cv_str($_POST['notes'] ?? '', 1000, 'Notes', false, true);
        $before = cv_need_movie($uid, $mid)['watch_count'];
        cv_record_watch($uid, $mid, $_POST['watched_at'] ?? '', $rating ?: null, $notes);
        return ['message' => $before > 0 ? 'Recorded another watch.' : 'Marked as watched.', 'movie_id' => $mid, 'reload' => true];
    },
    'history_delete' => function () use ($uid) {
        $movie = cv_delete_history($uid, (int)($_POST['history_id'] ?? 0));
        return ['message' => 'Watch entry removed.', 'movie_id' => $movie, 'reload' => true];
    },

    /* ── tags ── */
    'movie_tag_add' => function () use ($uid, $mid) {
        cv_tag_movie($uid, $mid, $_POST['tag'] ?? '');
        return ['message' => 'Tag added.', 'movie_id' => $mid];
    },
    'movie_tag_remove' => function () use ($uid, $mid) {
        cv_untag_movie($uid, $mid, (int)($_POST['tag_id'] ?? 0));
        return ['message' => 'Tag removed.', 'movie_id' => $mid];
    },
    'tag_create' => function () use ($uid) {
        cv_find_or_create_tag($uid, cv_tag_name($_POST['name'] ?? ''));
        return ['message' => 'Tag saved.', 'reload' => true];
    },
    'tag_rename' => function () use ($uid) {
        cv_rename_tag($uid, (int)($_POST['tag_id'] ?? 0), $_POST['name'] ?? '');
        return ['message' => 'Tag renamed.', 'reload' => true];
    },
    'tag_delete' => function () use ($uid) {
        cv_delete_tag($uid, (int)($_POST['tag_id'] ?? 0));
        return ['message' => 'Tag deleted.', 'reload' => true];
    },

    /* ── collections ── */
    'collection_create' => function () use ($uid) {
        $id = cv_create_collection($uid, $_POST['name'] ?? '', $_POST['description'] ?? '');
        return ['message' => 'Collection created.', 'collection_id' => $id, 'reload' => true];
    },
    'collection_update' => function () use ($uid) {
        cv_update_collection($uid, (int)($_POST['collection_id'] ?? 0), $_POST['name'] ?? '', $_POST['description'] ?? '');
        return ['message' => 'Collection updated.', 'reload' => true];
    },
    'collection_delete' => function () use ($uid) {
        cv_delete_collection($uid, (int)($_POST['collection_id'] ?? 0));
        return ['message' => 'Collection deleted. Your movies are untouched.', 'reload' => true, 'goto' => 'collections.php'];
    },
    'collection_add_movie' => function () use ($uid, $mid) {
        cv_collection_add($uid, (int)($_POST['collection_id'] ?? 0), $mid);
        return ['message' => 'Added to collection.', 'movie_id' => $mid, 'reload' => true];
    },
    'collection_remove_movie' => function () use ($uid, $mid) {
        cv_collection_remove($uid, (int)($_POST['collection_id'] ?? 0), $mid);
        return ['message' => 'Removed from collection.', 'movie_id' => $mid, 'reload' => true];
    },
];

$action = (string)($_POST['action'] ?? '');
if (!isset($handlers[$action])) cv_fail('Unknown action.', 400, $ajax, $back);

try {
    $r = $handlers[$action]();
} catch (CvInputError $e) {
    cv_fail($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 422, $ajax, $back);
} catch (Throwable $e) {
    error_log('actions.php [' . $action . ']: ' . $e->getMessage());
    cv_fail('Something went wrong on our side. Please try again.', 500, $ajax, $back);
}

if ($ajax) {
    $payload = ['ok' => true, 'message' => $r['message'], 'reload' => !empty($r['reload'])] + array_diff_key($r, ['message' => 1, 'reload' => 1, 'movie_id' => 1]);
    if (!empty($r['movie_id']) && empty($r['deleted'])) {
        try { $payload += cv_movie_bundle($uid, (int)$r['movie_id']); } catch (Throwable $e) { /* movie gone: nothing to refresh */ }
    }
    cv_json($payload);
}

cv_flash($r['message']);
cv_redirect(isset($r['goto']) ? $r['goto'] : $back);
