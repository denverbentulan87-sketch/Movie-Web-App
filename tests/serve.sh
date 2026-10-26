#!/usr/bin/env bash
# Starts the app on http://127.0.0.1:8099 against a fresh SQLite test database (no MySQL needed).
cd "$(dirname "$0")/.."
export CV_TEST_DB="${CV_TEST_DB:-/tmp/cv_test.sqlite}"
rm -f "$CV_TEST_DB"
sqlite3 "$CV_TEST_DB" < tests/schema_sqlite.sql 2>/dev/null || python3 -c "
import sqlite3,sys; c=sqlite3.connect('$CV_TEST_DB'); c.executescript(open('tests/schema_sqlite.sql').read()); c.commit()"
export CV_DEBUG=1
exec php -n -d extension=sqlite3 -d extension=mbstring -d extension=ctype -d extension=fileinfo \
  -d upload_max_filesize=8M -d post_max_size=9M -d auto_prepend_file="$PWD/tests/mysqli_shim.php" \
  -d error_log=/tmp/cv_php_errors.log -S 127.0.0.1:8099
