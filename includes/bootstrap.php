<?php
/**
 * Cinema Vault — shared bootstrap.
 *
 * Every page / endpoint includes this first. It provides:
 *   - hardened session start (HttpOnly, SameSite, strict mode)
 *   - security headers incl. a nonce-based Content-Security-Policy
 *   - CSRF token helpers
 *   - authentication guards
 *   - flash messages, JSON/redirect helpers, HTML escaping
 *   - tiny prepared-statement helpers (db_all / db_one / db_val / db_exec)
 *
 * Set the environment variable CV_DEBUG=1 to show PHP errors while developing.
 */

if (defined('CV_BOOTSTRAPPED')) return;
define('CV_BOOTSTRAPPED', true);
define('CV_ROOT', dirname(__DIR__));

/* ── errors: never leak internals to visitors ── */
$cvDebug = getenv('CV_DEBUG') === '1';
error_reporting(E_ALL);
ini_set('display_errors', $cvDebug ? '1' : '0');
ini_set('log_errors', '1');

if (($tz = getenv('CV_TIMEZONE')) && in_array($tz, timezone_identifiers_list(), true)) {
    date_default_timezone_set($tz);
}

/* ── session ── */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ── security headers ── */
function cv_nonce(): string {
    static $n = null;
    if ($n === null) $n = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    return $n;
}

function cv_security_headers(): void {
    if (headers_sent()) return;
    $n = cv_nonce();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'nonce-$n'; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com; "
         . "img-src 'self' data: blob: https://images.unsplash.com; connect-src 'self'; "
         . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}
cv_security_headers();

/* ── database ── */
require_once CV_ROOT . '/db.php';   // defines $conn (mysqli, exception mode)

function db_run(string $sql, string $types = '', array $params = []): mysqli_stmt {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}
function db_all(string $sql, string $types = '', array $params = []): array {
    return db_run($sql, $types, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function db_one(string $sql, string $types = '', array $params = []): ?array {
    return db_run($sql, $types, $params)->get_result()->fetch_assoc() ?: null;
}
function db_val(string $sql, string $types = '', array $params = []) {
    $r = db_one($sql, $types, $params);
    return $r ? reset($r) : null;
}
/** Runs a write; returns affected rows. */
function db_exec(string $sql, string $types = '', array $params = []): int {
    return db_run($sql, $types, $params)->affected_rows;
}
/** LIKE pattern with user input escaped (we use ESCAPE '|' in the SQL). */
function db_like(string $s): string {
    return '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $s) . '%';
}

/* ── output helpers ── */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function cv_is_ajax(): bool {
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function cv_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Only allow redirects back to our own pages (prevents open redirects). */
function cv_safe_return(?string $to, string $fallback = 'index.php'): string {
    $to = (string)$to;
    if (preg_match('~^(index|collections|stats|wrapped)\.php(\?[A-Za-z0-9_%=&\[\].,+\-]*)?$~', $to)) return $to;
    return $fallback;
}

function cv_redirect(string $to): never {
    header('Location: ' . $to);
    exit;
}

/* ── flash messages (shown as toasts on the next page) ── */
function cv_flash(string $msg, string $kind = 'ok'): void {
    $_SESSION['flash'][] = ['kind' => $kind, 'msg' => $msg];
}
function cv_pull_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ── CSRF ── */
function csrf_token(): string {
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}
function csrf_valid(): bool {
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/* ── authentication ── */
function cv_user_id(): int { return (int)($_SESSION['user_id'] ?? 0); }

function cv_require_login(): int {
    if (cv_user_id() > 0) return cv_user_id();
    if (cv_is_ajax()) cv_json(['ok' => false, 'error' => 'Please sign in again.', 'login' => true], 401);
    cv_redirect('homepage.php?modal=login');
}
