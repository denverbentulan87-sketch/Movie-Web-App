<?php

include 'db.php';
session_start();

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

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT user_id, username, password FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row && password_verify($password, $row['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']  = $row['user_id'];
    $_SESSION['username'] = $row['username'];
    if ($isAjax) login_json(['ok' => true, 'redirect' => 'index.php']);
    header("Location: index.php");
    exit();
}

$error = 'Invalid username or password.';
if ($isAjax) login_json(['ok' => false, 'error' => $error, 'field' => 'password'], 401);

$_SESSION['auth_flash'] = ['modal' => 'login', 'error' => $error, 'user' => $username];
header("Location: homepage.php?modal=login");
exit();
