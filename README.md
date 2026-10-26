# Cinema Vault

A personal movie library and tracker (PHP 8.1+ / MySQL or MariaDB).

Statuses: Plan to Watch · Watching · Watched — plus favorites, priorities, tags, collections,
rewatch tracking, watch history, private notes, advanced search, stats, a yearly "Movie Wrapped",
Surprise Me and rule-based "Picked for you".

## Setup
1. Import `movies_db.sql` into MySQL/MariaDB (database `movies_db`).
2. **Upgrading or fresh install: run the migration** (idempotent, keeps all existing data):
   `php migrate.php`  (or import `migrations/001_vault_expansion.sql` in phpMyAdmin on MariaDB 10.4+).
3. Credentials default to a stock XAMPP install; override with env vars `CV_DB_HOST`, `CV_DB_USER`,
   `CV_DB_PASS`, `CV_DB_NAME`, or edit `db.php`. Set `CV_DEBUG=1` to show PHP errors while developing.
4. Serve with PHP (XAMPP, or `php -S localhost:8000`) and open `homepage.php`.

## Structure
| File | Purpose |
|---|---|
| `homepage.php`, `login.php`, `register.php`, `logout.php` | Landing page and auth (CSRF-protected; logout is POST). |
| `index.php` | The vault: rows/grid, filters, Plan-to-Watch priority sections, Picked for you, detail modal. Read-only. |
| `actions.php` | **The only endpoint that changes data.** POST + login + CSRF; every handler is scoped to the signed-in user. |
| `movie.php`, `surprise.php` | Read-only JSON (movie detail + history; random Plan-to-Watch pick). |
| `collections.php`, `stats.php`, `wrapped.php` | Collections list, statistics, Movie Wrapped. |
| `includes/bootstrap.php` | Session hardening, security headers/CSP, CSRF, auth guard, prepared-statement helpers. |
| `includes/movies.php` | Domain logic: validation, ownership-checked queries, uploads, watch history, tags, collections. |
| `includes/statistics.php`, `includes/recommend.php` | Pure functions for stats / Wrapped / recommendations. |
| `migrations/`, `migrate.php` | Schema changes. |
| `assets/js/core.js` · `dashboard.js` · `vault.js` | Shared core · vault page · shared page behaviours. |
| `tests/` | Dev-only test harness (see below). |
| `delete.php`, `delete_all.php`, `create.php`, `edit.php` | Retired (insecure/broken); they just redirect. |

## Tests (no MySQL needed)
`tests/serve.sh &` starts the app on a SQLite-backed `mysqli` stand-in, then:
`python3 tests/test_backend.py`, `python3 tests/test_pages.py`, `php -n -d extension=mbstring tests/test_units.php`,
`python3 tests/browser_smoke.py` (needs Playwright). The shim does not validate MySQL-specific syntax — run the
migration and a manual pass on your real MariaDB/MySQL too.
