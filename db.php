<?php
/**
 * Database connection.
 * Defaults match a stock XAMPP install; override with environment variables
 * (CV_DB_HOST, CV_DB_USER, CV_DB_PASS, CV_DB_NAME) or edit the fallbacks below.
 *
 * mysqli runs in exception mode (PHP 8.1+ default) so a failed query can never
 * silently continue; callers catch mysqli_sql_exception where they need to.
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        getenv('CV_DB_HOST') ?: 'localhost',
        getenv('CV_DB_USER') ?: 'root',
        getenv('CV_DB_PASS') !== false ? getenv('CV_DB_PASS') : '',
        getenv('CV_DB_NAME') ?: 'movies_db'
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Cinema Vault DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Cinema Vault is temporarily unavailable. Please try again shortly.');
}
