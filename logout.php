<?php
require __DIR__ . '/includes/bootstrap.php';

// Signing out changes state, so it must be a POST carrying the CSRF token
// (otherwise any page could log a user out with an <img> tag).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'],
                  'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
    cv_redirect('homepage.php');
}
cv_redirect(cv_user_id() ? 'index.php' : 'homepage.php');
