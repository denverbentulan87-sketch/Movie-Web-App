<?php
include 'db.php';
session_start();

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function register_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

// already signed in
if (isset($_SESSION['user_id'])) {
    if ($isAjax) register_json(['ok' => true, 'redirect' => 'index.php']);
    header("Location: index.php");
    exit();
}

// not a form submission: show the modal on the landing page
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['register'])) {
    header("Location: homepage.php?modal=register");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm']  ?? '';
$error    = '';
$field    = '';

if (strlen($username) < 3) {
    $error = 'Username must be at least 3 characters.'; $field = 'username';
} elseif (strlen($password) < 6) {
    $error = 'Password must be at least 6 characters.'; $field = 'password';
} elseif ($password !== $confirm) {
    $error = 'Passwords do not match.'; $field = 'confirm';
} else {
    $check = $conn->prepare("SELECT user_id FROM users WHERE username=?");
    $check->bind_param("s", $username);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $error = 'Username already taken.'; $field = 'username';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins  = $conn->prepare("INSERT INTO users (username, password) VALUES (?,?)");
        $ins->bind_param("ss", $username, $hash);

        $dup = false;
        try {
            $created = $ins->execute();
            $dup     = !$created && $ins->errno === 1062;
        } catch (mysqli_sql_exception $e) {     // PHP 8.1+ throws instead of returning false
            $created = false;
            $dup     = $e->getCode() === 1062;  // same name registered at the same moment
        }

        if ($created) {
            if ($isAjax) register_json(['ok' => true, 'switch' => 'login', 'username' => $username,
                                        'message' => 'Account created. Sign in to open your vault.']);
            header("Location: homepage.php?registered=1");
            exit();
        }
        $error = $dup ? 'Username already taken.' : 'Could not create the account. Please try again.';
        $field = 'username';
    }
}

if ($isAjax) register_json(['ok' => false, 'error' => $error, 'field' => $field], 422);

$_SESSION['auth_flash'] = ['modal' => 'register', 'error' => $error, 'user' => $username];
header("Location: homepage.php?modal=register");
exit();
