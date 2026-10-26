<?php
/**
 * Shared request pipeline for every file that changes data
 * (create.php, edit.php, delete.php, delete_all.php and actions.php).
 *
 * cv_run_action() enforces, in this order:
 *   signed in  →  POST only  →  valid CSRF token  →  run the handler  →  respond.
 * A browser form gets a flash message + redirect; the JavaScript (fetch) gets JSON.
 * Handlers only ever touch rows owned by the signed-in user (see includes/movies.php).
 *
 * The handler receives the user id and returns:
 *   ['message' => string, 'movie_id' => ?int, 'deleted' => ?bool, 'reload' => ?bool, 'goto' => ?string, …extra]
 */
require_once __DIR__ . '/movies.php';

function cv_fail(string $msg, int $status, bool $ajax, string $back): never {
    if ($ajax) cv_json(['ok' => false, 'error' => $msg], $status);
    cv_flash($msg, 'warn');
    cv_redirect($back);
}

function cv_run_action(string $label, callable $handler): never {
    $uid  = cv_require_login();
    $ajax = cv_is_ajax();
    $back = cv_safe_return($_POST['return_to'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        cv_fail('That link can not be opened directly.', 405, $ajax, 'index.php');
    }
    if (!csrf_valid()) {
        cv_fail('Your session expired. Please reload the page and try again.', 403, $ajax, $back);
    }

    try {
        $r = $handler($uid);
    } catch (CvInputError $e) {
        cv_fail($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 422, $ajax, $back);
    } catch (Throwable $e) {
        error_log($label . ': ' . $e->getMessage());
        cv_fail('Something went wrong on our side. Please try again.', 500, $ajax, $back);
    }

    if ($ajax) {
        $payload = ['ok' => true, 'message' => $r['message'], 'reload' => !empty($r['reload'])]
                 + array_diff_key($r, ['message' => 1, 'reload' => 1, 'movie_id' => 1]);
        if (!empty($r['movie_id']) && empty($r['deleted'])) {
            try { $payload += cv_movie_bundle($uid, (int)$r['movie_id']); } catch (Throwable $e) { /* movie gone: nothing to refresh */ }
        }
        cv_json($payload);
    }

    cv_flash($r['message']);
    cv_redirect(isset($r['goto']) ? $r['goto'] : $back);
}
