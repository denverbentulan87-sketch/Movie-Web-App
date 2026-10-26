<?php
/**
 * Applies the SQL files in /migrations to the database configured in db.php.
 *
 *     php migrate.php
 *
 * CLI only (never expose a migration runner over HTTP). Every migration is
 * idempotent, so running it twice is harmless. Works on MariaDB and MySQL 8:
 * on MySQL the "IF NOT EXISTS" clause of ALTER TABLE (a MariaDB extension) is
 * dropped and "already exists" errors are skipped instead.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/db.php';          // $conn, exception mode

$isMaria = stripos($conn->server_info, 'mariadb') !== false;
$tolerated = [1060 /* dup column */, 1061 /* dup key name */];

$conn->query("CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(64) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);
foreach ($files as $file) {
    $version = basename($file, '.sql');
    echo "→ $version\n";

    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));       // strip comments
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql . "\n")));

    $done = 0; $skipped = 0;
    foreach ($statements as $stmt) {
        if (!$isMaria && preg_match('/^ALTER TABLE/i', $stmt)) {
            $stmt = preg_replace('/\bIF NOT EXISTS\b\s*/i', '', $stmt);
        }
        try {
            $conn->query($stmt);
            $done++;
        } catch (mysqli_sql_exception $e) {
            if (in_array((int)$e->getCode(), $tolerated, true)) { $skipped++; continue; }
            fwrite(STDERR, "\nFAILED: " . $e->getMessage() . "\n--- statement ---\n$stmt\n");
            exit(1);
        }
    }
    $ins = $conn->prepare("INSERT IGNORE INTO schema_migrations (version) VALUES (?)");
    $ins->bind_param('s', $version);
    $ins->execute();
    echo "  $done statements applied, $skipped already in place.\n";
}
echo "Done.\n";
