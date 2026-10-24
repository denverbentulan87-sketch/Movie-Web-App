# Cinema Vault

A PHP + MySQL movie watchlist.

## Setup
1. Import `movies_db.sql` into MySQL/MariaDB (database `movies_db`).
2. Adjust credentials in `db.php` if needed.
3. Serve the folder with PHP (XAMPP, or `php -S localhost:8000`) and open `homepage.php`.

## Structure
| File | Purpose |
|---|---|
| `homepage.php` | Landing page. Sign in and Create account are a modal here (handles the POSTs, fetch + no-JS fallback). |
| `index.php` | Dashboard (watchlist, add/edit/delete, filters, stats). |
| `login.php`, `register.php` | Redirect to the landing page and open the matching modal tab. |
| `logout.php` | Ends the session and returns to the landing page. |
| `includes/head.php` | Favicons, fonts and shared stylesheet, used by every page. |
| `includes/icons.php` | Inline SVG icon sprite (`<svg class="i"><use href="#i-film"/></svg>`). |
| `assets/css/style.css` | Shared design tokens, buttons, forms, modal system, toast. |
| `assets/css/landing.css`, `assets/js/landing.js` | Landing page + auth modal. |
| `assets/css/dashboard.css`, `assets/js/dashboard.js` | Dashboard. |
| `assets/img/` | Favicon (SVG + PNG 32px + Apple touch icon). |
