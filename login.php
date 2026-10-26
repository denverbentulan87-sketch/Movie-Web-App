<?php

require __DIR__ . '/includes/bootstrap.php';

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function login_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

// already signed in
if (isset($_SESSION['user_id'])) {
    if ($isAjax) login_json(['ok' => true, 'redirect' => 'index.php']);
    header("Location: index.php");
    exit();
}

// not a form submission: show the modal on the landing page
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    header("Location: homepage.php?modal=login");
    exit();
}

if (!csrf_valid()) {
    if ($isAjax) login_json(['ok' => false, 'error' => 'Your session expired. Please reload the page and try again.'], 403);
    cv_redirect('homepage.php?modal=login');
}

// basic brute-force brake: 5 failures lock this session for 60 seconds
if (($_SESSION['login_lock_until'] ?? 0) > time()) {
    $error = 'Too many attempts. Please wait a minute and try again.';
    if ($isAjax) login_json(['ok' => false, 'error' => $error, 'field' => 'password'], 429);
    $_SESSION['auth_flash'] = ['modal' => 'login', 'error' => $error, 'user' => ''];
    cv_redirect('homepage.php?modal=login');
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

$stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row && password_verify($password, $row['password'])) {
    session_regenerate_id(true);
    unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
    $_SESSION['user_id']  = (int)$row['user_id'];
    $_SESSION['username'] = $row['username'];
    if ($isAjax) login_json(['ok' => true, 'redirect' => 'index.php']);
    header("Location: index.php");
    exit();
}

usleep(300000);   // slow down guessing
$_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
if ($_SESSION['login_fails'] >= 5) { $_SESSION['login_lock_until'] = time() + 60; $_SESSION['login_fails'] = 0; }
$error = 'Invalid username or password.';
if ($isAjax) login_json(['ok' => false, 'error' => $error, 'field' => 'password'], 401);

$_SESSION['auth_flash'] = ['modal' => 'login', 'error' => $error, 'user' => $username];
header("Location: homepage.php?modal=login");
exit();
