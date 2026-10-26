<?php
/**
 * Cinema Vault — movie domain library.
 *
 * Rules every function here follows:
 *   1. Every query that touches user-owned data filters on the user id that is
 *      passed in explicitly ($uid) — never on an id taken from the request alone.
 *   2. All SQL uses prepared statements (db_* helpers from bootstrap.php).
 *   3. Input is validated/normalised here and failures raise CvInputError, which
 *      actions.php turns into a friendly message.
 */
require_once __DIR__ . '/bootstrap.php';

const CV_STATUSES   = ['Plan to Watch', 'Watching', 'Watched'];
const CV_PRIORITIES = ['Low', 'Normal', 'High'];
const CV_GENRES     = ['Action','Adventure','Animation','Anime','Comedy','Crime','Documentary','Drama','Fantasy','Fiction','Horror','Romance','Sci-Fi','Thriller'];
const CV_MAX_COVER_BYTES = 5 * 1024 * 1024;
const CV_MAX_TAGS_PER_USER = 200;
const CV_MAX_COLLECTIONS_PER_USER = 100;

class CvInputError extends RuntimeException {
    public function __construct(string $message, int $status = 422) { parent::__construct($message, $status); }
}

/* ══════════════════════════ validation helpers ══════════════════════════ */

/** Trim, drop control characters, enforce a max length. Returns null for empty. */
function cv_str($v, int $max, string $label, bool $required = false, bool $multiline = false): ?string {
    $v = is_string($v) ? $v : '';
    $v = preg_replace($multiline ? '/[^\P{Cc}\n\t]/u' : '/\p{Cc}/u', '', str_replace("\r\n", "\n", $v)) ?? '';
    $v = trim($v);
    if ($v === '') {
        if ($required) throw new CvInputError("$label is required.");
        return null;
    }
    if (mb_strlen($v) > $max) throw new CvInputError("$label must be $max characters or fewer.");
    return $v;
}

function cv_int_or_null($v, int $min, int $max, string $label): ?int {
    if ($v === null || $v === '') return null;
    if (!is_scalar($v) || !preg_match('/^\d{1,5}$/', trim((string)$v))) throw new CvInputError("$label must be a number.");
    $n = (int)$v;
    if ($n < $min || $n > $max) throw new CvInputError("$label must be between $min and $max.");
    return $n;
}

function cv_rating($v): int {
    if ($v === null || $v === '') return 0;
    if (!is_scalar($v) || !preg_match('/^[0-5]$/', trim((string)$v))) throw new CvInputError('Rating must be between 0 and 5.');
    return (int)$v;
}

function cv_date($v, string $label = 'Date'): string {
    $v = is_string($v) ? trim($v) : '';
    if ($v === '') return date('Y-m-d');
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) throw new CvInputError("$label is not a valid date.");
    if ($d > new DateTime('+1 day')) throw new CvInputError("$label can't be in the future.");
    if ((int)$d->format('Y') < 1900) throw new CvInputError("$label is too far in the past.");
    return $v;
}

function cv_enum($v, array $allowed, string $label, ?string $default = null): string {
    if (($v === null || $v === '') && $default !== null) return $default;
    if (!is_string($v) || !in_array($v, $allowed, true)) throw new CvInputError("Choose a valid $label.");
    return $v;
}

/** Validates the add/edit movie form. Returns column => value. */
function cv_parse_movie_input(array $in): array {
    return [
        'movie_title'     => cv_str($in['movie_title'] ?? '', 255, 'Title', true),
        'genre'           => cv_enum($in['genre'] ?? '', CV_GENRES, 'genre'),
        'status'          => cv_enum($in['status'] ?? '', CV_STATUSES, 'status', 'Plan to Watch'),
        'priority'        => cv_enum($in['priority'] ?? '', CV_PRIORITIES, 'priority', 'Normal'),
        'rating'          => cv_rating($in['rating'] ?? 0),
        'description'     => cv_str($in['description'] ?? '', 5000, 'Description', false, true),
        'runtime_minutes' => cv_int_or_null($in['runtime_minutes'] ?? '', 1, 999, 'Runtime'),
        'release_year'    => cv_int_or_null($in['release_year'] ?? '', 1880, (int)date('Y') + 5, 'Release year'),
        'director'        => cv_str($in['director'] ?? '', 255, 'Director'),
        'cast_members'    => cv_str($in['cast_members'] ?? '', 2000, 'Cast', false, true),
        'language'        => cv_str($in['language'] ?? '', 50, 'Language'),
        'country'         => cv_str($in['country'] ?? '', 100, 'Country'),
    ];
}

/* ══════════════════════════ covers (uploads) ═════════════════════════════ */

/** Resolve a stored cover URL to a file path, only if it is inside assets/covers. */
function cv_cover_path(?string $url): ?string {
    if (!$url || !preg_match('~^assets/covers/[A-Za-z0-9_][A-Za-z0-9_.\-]*$~', $url)) return null;
    $p = CV_ROOT . '/' . $url;
    return is_file($p) ? $p : null;
}
function cv_delete_cover(?string $url): void {
    if ($p = cv_cover_path($url)) @unlink($p);
}

/**
 * Validates and stores an uploaded cover. Returns the new URL, or $old when no
 * file was sent. The extension comes from the *detected* image type — never
 * from the client-supplied filename — so a ".php" upload can't be executed.
 */
function cv_handle_upload(string $field, ?string $old = null): ?string {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $old;
    }
    $f = $_FILES[$field];
    if (is_array($f['error'])) throw new CvInputError('Upload a single image.');
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) throw new CvInputError('That image is over 5 MB. Choose a smaller one.');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new CvInputError('The image could not be uploaded. Please try again.');
    if ($f['size'] > CV_MAX_COVER_BYTES) throw new CvInputError('That image is over 5 MB. Choose a smaller one.');
    if (!is_uploaded_file($f['tmp_name'])) throw new CvInputError('The image could not be uploaded. Please try again.');

    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new CvInputError('Choose a JPG, PNG, WEBP or GIF image.');
    if ($info[0] * $info[1] > 40_000_000) throw new CvInputError('That image is too large in pixels. Choose a smaller one.');

    $dir = CV_ROOT . '/assets/covers/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new CvInputError('Covers can not be saved right now.', 500);
    $name = 'cover_' . bin2hex(random_bytes(12)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) throw new CvInputError('The image could not be saved. Please try again.', 500);
    @chmod($dir . $name, 0644);

    if ($old) cv_delete_cover($old);
    return 'assets/covers/' . $name;
}

/* ══════════════════════════ movie lookup / shaping ═══════════════════════ */

function cv_find_movie(int $uid, int $id): ?array {
    return db_one('SELECT * FROM movie_watchlist WHERE watchlist_id = ? AND user_id = ?', 'ii', [$id, $uid]);
}
/** Same message for "missing" and "someone else's" so ids can't be probed. */
function cv_need_movie(int $uid, $id): array {
    $m = cv_find_movie($uid, (int)$id);
    if (!$m) throw new CvInputError('That movie could not be found.', 404);
    return $m;
}

/** Columns sent to the browser for cards. Personal notes are fetched on demand. */
const CV_CARD_COLUMNS = 'm.watchlist_id, m.movie_title, m.genre, m.status, m.rating, m.description, m.cover_url, m.date_added,
    m.is_favorite, m.priority, m.watch_count, m.last_watched_at, m.release_year, m.runtime_minutes, m.release_date,
    m.director, m.cast_members, m.language, m.country, m.external_movie_id';

function cv_normalize_movie(array $m): array {
    foreach (['watchlist_id', 'rating', 'is_favorite', 'watch_count', 'notes_spoiler'] as $k) {
        if (array_key_exists($k, $m)) $m[$k] = (int)$m[$k];
    }
    foreach (['runtime_minutes', 'release_year'] as $k) {
        if (array_key_exists($k, $m)) $m[$k] = $m[$k] === null ? null : (int)$m[$k];
    }
    $m['tags'] = $m['tags'] ?? [];
    $m['collections'] = $m['collections'] ?? [];
    return $m;
}

/** [movie_id => [ {id,name}, … ]] for every tag link the user owns. */
function cv_tag_map(int $uid): array {
    $map = [];
    $rows = db_all('SELECT mt.movie_id, t.tag_id, t.name FROM movie_tags mt
                    JOIN tags t ON t.tag_id = mt.tag_id AND t.user_id = mt.user_id
                    WHERE mt.user_id = ? ORDER BY t.name', 'i', [$uid]);
    foreach ($rows as $r) $map[(int)$r['movie_id']][] = ['id' => (int)$r['tag_id'], 'name' => $r['name']];
    return $map;
}
function cv_collection_map(int $uid): array {
    $map = [];
    $rows = db_all('SELECT cm.movie_id, c.collection_id, c.name FROM collection_movies cm
                    JOIN collections c ON c.collection_id = cm.collection_id AND c.user_id = cm.user_id
                    WHERE cm.user_id = ? ORDER BY c.name', 'i', [$uid]);
    foreach ($rows as $r) $map[(int)$r['movie_id']][] = ['id' => (int)$r['collection_id'], 'name' => $r['name']];
    return $map;
}

/** All tags with usage counts (for filters, pickers, management). */
function cv_user_tags(int $uid): array {
    $rows = db_all('SELECT t.tag_id, t.name, COUNT(mt.movie_id) AS n FROM tags t
                    LEFT JOIN movie_tags mt ON mt.tag_id = t.tag_id AND mt.user_id = t.user_id
                    WHERE t.user_id = ? GROUP BY t.tag_id, t.name ORDER BY t.name', 'i', [$uid]);
    return array_map(fn($r) => ['id' => (int)$r['tag_id'], 'name' => $r['name'], 'count' => (int)$r['n']], $rows);
}

/** All collections with movie counts and up to 4 poster urls for a cover collage. */
function cv_user_collections(int $uid): array {
    $rows = db_all('SELECT c.collection_id, c.name, c.description, c.created_at, COUNT(cm.movie_id) AS n FROM collections c
                    LEFT JOIN collection_movies cm ON cm.collection_id = c.collection_id AND cm.user_id = c.user_id
                    WHERE c.user_id = ? GROUP BY c.collection_id, c.name, c.description, c.created_at ORDER BY c.name', 'i', [$uid]);
    $covers = [];
    $cr = db_all("SELECT cm.collection_id, m.cover_url FROM collection_movies cm
                  JOIN movie_watchlist m ON m.watchlist_id = cm.movie_id AND m.user_id = cm.user_id
                  WHERE cm.user_id = ? AND m.cover_url IS NOT NULL AND m.cover_url <> ''
                  ORDER BY cm.added_at DESC, cm.movie_id DESC", 'i', [$uid]);
    foreach ($cr as $r) {
        $cid = (int)$r['collection_id'];
        if (count($covers[$cid] ?? []) < 4) $covers[$cid][] = $r['cover_url'];
    }
    return array_map(fn($r) => [
        'id' => (int)$r['collection_id'], 'name' => $r['name'], 'description' => (string)$r['description'],
        'count' => (int)$r['n'], 'covers' => $covers[(int)$r['collection_id']] ?? [],
    ], $rows);
}

/* ══════════════════════════ filtering / search ═══════════════════════════ */

const CV_SORTS = [
    'newest'       => 'm.date_added DESC, m.watchlist_id DESC',
    'oldest'       => 'm.date_added ASC, m.watchlist_id ASC',
    'rating'       => 'm.rating DESC, m.date_added DESC, m.watchlist_id DESC',
    'title'        => 'm.movie_title ASC, m.watchlist_id ASC',
    'watched'      => 'm.last_watched_at DESC, m.watchlist_id DESC',
    'most_watched' => 'm.watch_count DESC, m.last_watched_at DESC, m.watchlist_id DESC',
    'year'         => 'm.release_year DESC, m.watchlist_id DESC',
];

/** Normalises GET parameters into a safe filter set. Unknown values are dropped. */
function cv_parse_filters(array $q): array {
    $s = fn($k, $max = 100) => isset($q[$k]) && is_string($q[$k]) ? mb_substr(trim($q[$k]), 0, $max) : '';
    $tags = [];
    foreach ((array)($q['tags'] ?? []) as $t) {
        if (is_scalar($t) && ctype_digit((string)$t) && (int)$t > 0) $tags[(int)$t] = (int)$t;
    }
    $watches = $s('watches', 10);
    $genre = $s('genre');
    $rm = $s('rating_min', 1); $yr = $s('year', 4); $co = $s('collection', 10);
    return [
        'search'     => $s('search'),
        'status'     => in_array($s('status'), CV_STATUSES, true) ? $s('status') : '',
        'genre'      => $genre,
        'favorite'   => ($q['favorite'] ?? '') === '1' ? '1' : '',
        'priority'   => in_array($s('priority'), CV_PRIORITIES, true) ? $s('priority') : '',
        'rating_min' => ($rm !== '' && ctype_digit($rm) && (int)$rm >= 1 && (int)$rm <= 5) ? (int)$rm : 0,
        'year'       => ($yr !== '' && ctype_digit($yr)) ? (int)$yr : 0,
        'tags'       => array_slice(array_values($tags), 0, 10),
        'collection' => ($co !== '' && ctype_digit($co)) ? (int)$co : 0,
        'watches'    => in_array($watches, ['never', '1', '2', '3', '5'], true) ? $watches : '',
        'sort'       => isset(CV_SORTS[$s('sort', 20)]) ? $s('sort', 20) : 'newest',
    ];
}

/** Count of active filters (not counting sort), for the "Filters (n)" badge. */
function cv_active_filter_count(array $f): int {
    $n = 0;
    foreach (['search', 'status', 'genre', 'favorite', 'priority', 'rating_min', 'year', 'collection', 'watches'] as $k) {
        if ($f[$k] !== '' && $f[$k] !== 0) $n++;
    }
    return $n + count($f['tags']);
}

/** Builds the ownership-scoped movie query for a filter set. Returns [sql, types, params]. */
function cv_movie_query(int $uid, array $f, string $columns = CV_CARD_COLUMNS): array {
    $where = ['m.user_id = ?']; $types = 'i'; $params = [$uid];

    if ($f['search'] !== '') {
        $like = db_like(mb_strtolower($f['search']));
        $where[] = "(LOWER(m.movie_title) LIKE ? ESCAPE '|' OR LOWER(m.genre) LIKE ? ESCAPE '|' OR LOWER(m.personal_notes) LIKE ? ESCAPE '|'
            OR EXISTS (SELECT 1 FROM movie_tags mt JOIN tags t ON t.tag_id = mt.tag_id AND t.user_id = mt.user_id
                       WHERE mt.movie_id = m.watchlist_id AND mt.user_id = m.user_id AND LOWER(t.name) LIKE ? ESCAPE '|')
            OR EXISTS (SELECT 1 FROM collection_movies cm JOIN collections c ON c.collection_id = cm.collection_id AND c.user_id = cm.user_id
                       WHERE cm.movie_id = m.watchlist_id AND cm.user_id = m.user_id AND LOWER(c.name) LIKE ? ESCAPE '|')
            OR EXISTS (SELECT 1 FROM watch_history w WHERE w.movie_id = m.watchlist_id AND w.user_id = m.user_id AND LOWER(w.notes) LIKE ? ESCAPE '|'))";
        $types .= 'ssssss'; array_push($params, $like, $like, $like, $like, $like, $like);
    }
    if ($f['status'] !== '')   { $where[] = 'm.status = ?';   $types .= 's'; $params[] = $f['status']; }
    if ($f['genre'] !== '')    { $where[] = 'm.genre = ?';    $types .= 's'; $params[] = $f['genre']; }
    if ($f['favorite'] === '1') $where[] = 'm.is_favorite = 1';
    if ($f['priority'] !== '') { $where[] = "m.status = 'Plan to Watch' AND m.priority = ?"; $types .= 's'; $params[] = $f['priority']; }
    if ($f['rating_min'] > 0)  { $where[] = 'm.rating >= ?';  $types .= 'i'; $params[] = $f['rating_min']; }
    if ($f['year'] > 0)        { $where[] = 'm.release_year = ?'; $types .= 'i'; $params[] = $f['year']; }
    foreach ($f['tags'] as $tid) {
        $where[] = 'EXISTS (SELECT 1 FROM movie_tags mt WHERE mt.movie_id = m.watchlist_id AND mt.user_id = m.user_id AND mt.tag_id = ?)';
        $types .= 'i'; $params[] = $tid;
    }
    if ($f['collection'] > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM collection_movies cm WHERE cm.movie_id = m.watchlist_id AND cm.user_id = m.user_id AND cm.collection_id = ?)';
        $types .= 'i'; $params[] = $f['collection'];
    }
    if ($f['watches'] === 'never') $where[] = 'm.watch_count = 0';
    elseif ($f['watches'] !== '')  { $where[] = 'm.watch_count >= ?'; $types .= 'i'; $params[] = (int)$f['watches']; }

    $sql = "SELECT $columns FROM movie_watchlist m WHERE " . implode(' AND ', $where) . ' ORDER BY ' . CV_SORTS[$f['sort']];
    return [$sql, $types, $params];
}

/** Filtered movies with tags + collections attached. */
function cv_fetch_movies(int $uid, array $f): array {
    [$sql, $types, $params] = cv_movie_query($uid, $f, CV_CARD_COLUMNS . ", SUBSTR(m.personal_notes, 1, 1000) AS notes_search");
    $rows = db_all($sql, $types, $params);
    $tags = cv_tag_map($uid); $cols = cv_collection_map($uid);
    foreach ($rows as &$r) {
        $r = cv_normalize_movie($r);
        $r['tags'] = $tags[$r['watchlist_id']] ?? [];
        $r['collections'] = $cols[$r['watchlist_id']] ?? [];
    }
    return $rows;
}

/** Every movie of the user (lightweight, no filters) for stats and recommendations. */
function cv_all_movies(int $uid): array {
    $rows = db_all('SELECT m.watchlist_id, m.movie_title, m.genre, m.status, m.rating, m.cover_url, m.is_favorite, m.priority,
                           m.watch_count, m.last_watched_at, m.runtime_minutes, m.release_year, m.date_added
                    FROM movie_watchlist m WHERE m.user_id = ?', 'i', [$uid]);
    return array_map('cv_normalize_movie', $rows);
}

function cv_all_history(int $uid): array {
    $rows = db_all('SELECT history_id, movie_id, watched_at, rating FROM watch_history WHERE user_id = ? ORDER BY watched_at DESC, history_id DESC', 'i', [$uid]);
    return array_map(fn($r) => ['history_id' => (int)$r['history_id'], 'movie_id' => (int)$r['movie_id'],
        'watched_at' => $r['watched_at'], 'rating' => $r['rating'] === null ? null : (int)$r['rating']], $rows);
}

/* ══════════════════════════ detail payload ═══════════════════════════════ */

function cv_history_for(int $uid, int $movieId): array {
    $rows = db_all('SELECT history_id, watched_at, rating, notes FROM watch_history
                    WHERE movie_id = ? AND user_id = ? ORDER BY watched_at DESC, history_id DESC', 'ii', [$movieId, $uid]);
    return array_map(fn($r) => ['id' => (int)$r['history_id'], 'watched_at' => $r['watched_at'],
        'rating' => $r['rating'] === null ? null : (int)$r['rating'], 'notes' => (string)$r['notes']], $rows);
}

/** Everything the detail modal needs for one movie (ownership checked). */
function cv_movie_bundle(int $uid, int $id): array {
    $m = cv_need_movie($uid, $id);
    $m = cv_normalize_movie($m);
    unset($m['user_id']);
    $m['tags'] = cv_tag_map($uid)[$id] ?? [];
    $m['collections'] = cv_collection_map($uid)[$id] ?? [];
    return [
        'movie' => $m,
        'history' => cv_history_for($uid, $id),
        'all_tags' => cv_user_tags($uid),
        'all_collections' => array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], cv_user_collections($uid)),
    ];
}

/* ══════════════════════════ watch history ════════════════════════════════ */

/** Recomputes watch_count / last_watched_at from the history (single source of truth). */
function cv_resync_watch_stats(int $uid, int $movieId): void {
    db_exec('UPDATE movie_watchlist SET
                 watch_count = (SELECT COUNT(*) FROM watch_history WHERE movie_id = ? AND user_id = ?),
                 last_watched_at = (SELECT MAX(watched_at) FROM watch_history WHERE movie_id = ? AND user_id = ?)
             WHERE watchlist_id = ? AND user_id = ?', 'iiiiii', [$movieId, $uid, $movieId, $uid, $movieId, $uid]);
}

/** Runs $fn inside a transaction. Nested calls join the outer transaction (MySQL has no real nesting). */
function cv_transaction(callable $fn) {
    global $conn;
    static $depth = 0;
    if ($depth > 0) return $fn();
    $depth++;
    $conn->begin_transaction();
    try { $r = $fn(); $conn->commit(); return $r; }
    catch (Throwable $e) { $conn->rollback(); throw $e; }
    finally { $depth--; }
}

/**
 * Records one watch event: inserts history, marks the movie Watched, refreshes the
 * watch count and last-watched date, and (if given) updates the current rating.
 * Never creates a second movie row.
 */
function cv_record_watch(int $uid, int $movieId, ?string $date = null, ?int $rating = null, ?string $notes = null): void {
    cv_need_movie($uid, $movieId);
    $date = cv_date($date, 'Watched date');
    cv_transaction(function () use ($uid, $movieId, $date, $rating, $notes) {
        db_exec('INSERT INTO watch_history (movie_id, user_id, watched_at, rating, notes) VALUES (?, ?, ?, ?, ?)',
                'iisis', [$movieId, $uid, $date, $rating ?: null, $notes]);
        if ($rating) db_exec('UPDATE movie_watchlist SET rating = ? WHERE watchlist_id = ? AND user_id = ?', 'iii', [$rating, $movieId, $uid]);
        db_exec("UPDATE movie_watchlist SET status = 'Watched' WHERE watchlist_id = ? AND user_id = ?", 'ii', [$movieId, $uid]);
        cv_resync_watch_stats($uid, $movieId);
    });
}

function cv_delete_history(int $uid, int $historyId): int {
    $row = db_one('SELECT movie_id FROM watch_history WHERE history_id = ? AND user_id = ?', 'ii', [$historyId, $uid]);
    if (!$row) throw new CvInputError('That watch entry could not be found.', 404);
    $mid = (int)$row['movie_id'];
    cv_transaction(function () use ($uid, $historyId, $mid) {
        db_exec('DELETE FROM watch_history WHERE history_id = ? AND user_id = ?', 'ii', [$historyId, $uid]);
        cv_resync_watch_stats($uid, $mid);
    });
    return $mid;
}

/** Status change; moving into Watched records a watch event (unless already Watched). */
function cv_change_status(int $uid, int $movieId, string $status): void {
    $m = cv_need_movie($uid, $movieId);
    $status = cv_enum($status, CV_STATUSES, 'status');
    if ($status === $m['status']) return;
    if ($status === 'Watched') {
        cv_record_watch($uid, $movieId, date('Y-m-d'), (int)$m['rating'] ?: null, null);
    } else {
        db_exec('UPDATE movie_watchlist SET status = ? WHERE watchlist_id = ? AND user_id = ?', 'sii', [$status, $movieId, $uid]);
    }
}

/* ══════════════════════════ movie CRUD ═══════════════════════════════════ */

function cv_title_exists(int $uid, string $title, int $exceptId = 0): bool {
    return (bool)db_one('SELECT watchlist_id FROM movie_watchlist WHERE user_id = ? AND LOWER(TRIM(movie_title)) = LOWER(TRIM(?)) AND watchlist_id <> ?',
                        'isi', [$uid, $title, $exceptId]);
}

function cv_add_movie(int $uid, array $in): int {
    $d = cv_parse_movie_input($in);
    if (cv_title_exists($uid, $d['movie_title'])) throw new CvInputError('“' . $d['movie_title'] . '” is already in your vault.');
    $cover = cv_handle_upload('cover_file');
    try {
        return cv_transaction(function () use ($uid, $d, $cover) {
            db_exec('INSERT INTO movie_watchlist (user_id, movie_title, genre, status, priority, rating, description, cover_url, runtime_minutes,
                         release_year, director, cast_members, language, country, date_added)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())',
                'issssissiissss', [$uid, $d['movie_title'], $d['genre'], 'Plan to Watch', $d['priority'], $d['rating'], $d['description'], $cover,
                    $d['runtime_minutes'], $d['release_year'], $d['director'], $d['cast_members'], $d['language'], $d['country']]);
            global $conn;
            $id = (int)$conn->insert_id;
            if ($d['status'] === 'Watched') cv_record_watch($uid, $id, date('Y-m-d'), $d['rating'] ?: null, null);
            elseif ($d['status'] !== 'Plan to Watch') db_exec('UPDATE movie_watchlist SET status = ? WHERE watchlist_id = ? AND user_id = ?', 'sii', [$d['status'], $id, $uid]);
            return $id;
        });
    } catch (Throwable $e) {
        cv_delete_cover($cover);   // don't leave an orphan file behind
        throw $e;
    }
}

function cv_edit_movie(int $uid, int $id, array $in): void {
    $old = cv_need_movie($uid, $id);
    $d = cv_parse_movie_input($in);
    if (cv_title_exists($uid, $d['movie_title'], $id)) throw new CvInputError('“' . $d['movie_title'] . '” is already in your vault.');
    $cover = cv_handle_upload('cover_file', $old['cover_url']);
    $newFile = $cover !== $old['cover_url'];
    try {
        cv_transaction(function () use ($uid, $id, $d, $cover, $old) {
            // status is changed through cv_change_status so Watched creates a history entry
            db_exec('UPDATE movie_watchlist SET movie_title = ?, genre = ?, priority = ?, rating = ?, description = ?, cover_url = ?,
                         runtime_minutes = ?, release_year = ?, director = ?, cast_members = ?, language = ?, country = ?
                     WHERE watchlist_id = ? AND user_id = ?',
                'sssissiissssii',
                [$d['movie_title'], $d['genre'], $d['priority'], $d['rating'], $d['description'], $cover, $d['runtime_minutes'],
                 $d['release_year'], $d['director'], $d['cast_members'], $d['language'], $d['country'], $id, $uid]);
            // the current rating describes the most recent viewing: keep that history entry in step
            if ((int)$old['rating'] !== $d['rating']) {
                $last = db_one('SELECT history_id FROM watch_history WHERE movie_id = ? AND user_id = ? ORDER BY watched_at DESC, history_id DESC LIMIT 1', 'ii', [$id, $uid]);
                if ($last) db_exec('UPDATE watch_history SET rating = ? WHERE history_id = ? AND user_id = ?', 'iii', [$d['rating'] ?: null, $last['history_id'], $uid]);
            }
            cv_change_status($uid, $id, $d['status']);
        });
    } catch (Throwable $e) {
        if ($newFile) cv_delete_cover($cover);
        throw $e;
    }
}

function cv_delete_movie(int $uid, int $id): void {
    $m = cv_need_movie($uid, $id);
    db_exec('DELETE FROM movie_watchlist WHERE watchlist_id = ? AND user_id = ?', 'ii', [$id, $uid]);   // history/tags/collection links cascade
    cv_delete_cover($m['cover_url']);
}

function cv_delete_all_movies(int $uid): int {
    $covers = db_all('SELECT cover_url FROM movie_watchlist WHERE user_id = ?', 'i', [$uid]);
    $n = db_exec('DELETE FROM movie_watchlist WHERE user_id = ?', 'i', [$uid]);
    foreach ($covers as $c) cv_delete_cover($c['cover_url']);
    return $n;
}

function cv_set_favorite(int $uid, int $id, bool $on): void {
    cv_need_movie($uid, $id);
    db_exec('UPDATE movie_watchlist SET is_favorite = ? WHERE watchlist_id = ? AND user_id = ?', 'iii', [$on ? 1 : 0, $id, $uid]);
}

function cv_set_priority(int $uid, int $id, string $priority): void {
    cv_need_movie($uid, $id);
    $priority = cv_enum($priority, CV_PRIORITIES, 'priority');
    db_exec('UPDATE movie_watchlist SET priority = ? WHERE watchlist_id = ? AND user_id = ?', 'sii', [$priority, $id, $uid]);
}

function cv_set_notes(int $uid, int $id, $notes, bool $spoiler): void {
    cv_need_movie($uid, $id);
    $notes = cv_str($notes, 5000, 'Notes', false, true);
    db_exec('UPDATE movie_watchlist SET personal_notes = ?, notes_spoiler = ? WHERE watchlist_id = ? AND user_id = ?',
            'siii', [$notes, ($notes !== null && $spoiler) ? 1 : 0, $id, $uid]);
}

/* ══════════════════════════ tags ═════════════════════════════════════════ */

function cv_tag_name($raw): string {
    $n = is_string($raw) ? preg_replace('/\s+/u', ' ', ltrim(trim($raw), '#')) : '';
    $n = trim(preg_replace('/\p{Cc}/u', '', (string)$n) ?? '');
    if ($n === '') throw new CvInputError('Give the tag a name.');
    if (mb_strlen($n) > 50) throw new CvInputError('Tag names can be up to 50 characters.');
    return $n;
}

function cv_need_tag(int $uid, $id): array {
    $t = db_one('SELECT * FROM tags WHERE tag_id = ? AND user_id = ?', 'ii', [(int)$id, $uid]);
    if (!$t) throw new CvInputError('That tag could not be found.', 404);
    return $t;
}

function cv_find_or_create_tag(int $uid, string $name): int {
    $row = db_one('SELECT tag_id FROM tags WHERE user_id = ? AND name = ?', 'is', [$uid, $name]);
    if ($row) return (int)$row['tag_id'];
    if ((int)db_val('SELECT COUNT(*) FROM tags WHERE user_id = ?', 'i', [$uid]) >= CV_MAX_TAGS_PER_USER) {
        throw new CvInputError('You have reached the limit of ' . CV_MAX_TAGS_PER_USER . ' tags.');
    }
    try {
        db_exec('INSERT INTO tags (user_id, name) VALUES (?, ?)', 'is', [$uid, $name]);
        global $conn;
        return (int)$conn->insert_id;
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() !== 1062) throw $e;           // lost a race with another request: reuse the winner
        return (int)db_val('SELECT tag_id FROM tags WHERE user_id = ? AND name = ?', 'is', [$uid, $name]);
    }
}

function cv_rename_tag(int $uid, int $id, $raw): void {
    cv_need_tag($uid, $id);
    $name = cv_tag_name($raw);
    if (db_one('SELECT tag_id FROM tags WHERE user_id = ? AND name = ? AND tag_id <> ?', 'isi', [$uid, $name, $id])) {
        throw new CvInputError('You already have a tag called “' . $name . '”.');
    }
    db_exec('UPDATE tags SET name = ? WHERE tag_id = ? AND user_id = ?', 'sii', [$name, $id, $uid]);
}

function cv_delete_tag(int $uid, int $id): void {
    cv_need_tag($uid, $id);
    db_exec('DELETE FROM tags WHERE tag_id = ? AND user_id = ?', 'ii', [$id, $uid]);   // links cascade
}

function cv_tag_movie(int $uid, int $movieId, $rawName): void {
    cv_need_movie($uid, $movieId);
    $tagId = cv_find_or_create_tag($uid, cv_tag_name($rawName));
    db_exec('INSERT IGNORE INTO movie_tags (movie_id, tag_id, user_id) VALUES (?, ?, ?)', 'iii', [$movieId, $tagId, $uid]);
}

function cv_untag_movie(int $uid, int $movieId, int $tagId): void {
    cv_need_movie($uid, $movieId);
    cv_need_tag($uid, $tagId);
    db_exec('DELETE FROM movie_tags WHERE movie_id = ? AND tag_id = ? AND user_id = ?', 'iii', [$movieId, $tagId, $uid]);
}

/* ══════════════════════════ collections ══════════════════════════════════ */

function cv_need_collection(int $uid, $id): array {
    $c = db_one('SELECT * FROM collections WHERE collection_id = ? AND user_id = ?', 'ii', [(int)$id, $uid]);
    if (!$c) throw new CvInputError('That collection could not be found.', 404);
    return $c;
}

function cv_create_collection(int $uid, $name, $description): int {
    $name = cv_str($name, 100, 'Collection name', true);
    $description = cv_str($description, 500, 'Description', false, true);
    if ((int)db_val('SELECT COUNT(*) FROM collections WHERE user_id = ?', 'i', [$uid]) >= CV_MAX_COLLECTIONS_PER_USER) {
        throw new CvInputError('You have reached the limit of ' . CV_MAX_COLLECTIONS_PER_USER . ' collections.');
    }
    try {
        db_exec('INSERT INTO collections (user_id, name, description) VALUES (?, ?, ?)', 'iss', [$uid, $name, $description]);
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) throw new CvInputError('You already have a collection called “' . $name . '”.');
        throw $e;
    }
    global $conn;
    return (int)$conn->insert_id;
}

function cv_update_collection(int $uid, int $id, $name, $description): void {
    cv_need_collection($uid, $id);
    $name = cv_str($name, 100, 'Collection name', true);
    $description = cv_str($description, 500, 'Description', false, true);
    try {
        db_exec('UPDATE collections SET name = ?, description = ? WHERE collection_id = ? AND user_id = ?', 'ssii', [$name, $description, $id, $uid]);
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) throw new CvInputError('You already have a collection called “' . $name . '”.');
        throw $e;
    }
}

function cv_delete_collection(int $uid, int $id): void {
    cv_need_collection($uid, $id);
    db_exec('DELETE FROM collections WHERE collection_id = ? AND user_id = ?', 'ii', [$id, $uid]);   // links cascade; movies stay
}

function cv_collection_add(int $uid, int $collectionId, int $movieId): void {
    cv_need_collection($uid, $collectionId);
    cv_need_movie($uid, $movieId);
    db_exec('INSERT IGNORE INTO collection_movies (collection_id, movie_id, user_id) VALUES (?, ?, ?)', 'iii', [$collectionId, $movieId, $uid]);
}

function cv_collection_remove(int $uid, int $collectionId, int $movieId): void {
    cv_need_collection($uid, $collectionId);
    cv_need_movie($uid, $movieId);
    db_exec('DELETE FROM collection_movies WHERE collection_id = ? AND movie_id = ? AND user_id = ?', 'iii', [$collectionId, $movieId, $uid]);
}

/* ══════════════════════════ surprise me ══════════════════════════════════ */

/**
 * Random Plan-to-Watch pick for $uid. $exclude lists ids already shown this session.
 * Returns ['movie' => array|null, 'pool' => int, 'reason' => ''|'none'|'exhausted'].
 */
function cv_surprise(int $uid, array $opts, array $exclude = []): array {
    $where = ["m.user_id = ?", "m.status = 'Plan to Watch'"]; $types = 'i'; $params = [$uid];
    if (($opts['genre'] ?? '') !== '')    { $where[] = 'm.genre = ?';    $types .= 's'; $params[] = (string)$opts['genre']; }
    if (in_array($opts['priority'] ?? '', CV_PRIORITIES, true)) { $where[] = 'm.priority = ?'; $types .= 's'; $params[] = $opts['priority']; }
    if ((int)($opts['min_rating'] ?? 0) > 0)  { $where[] = 'm.rating >= ?'; $types .= 'i'; $params[] = min(5, (int)$opts['min_rating']); }
    if ((int)($opts['max_runtime'] ?? 0) > 0) { $where[] = 'm.runtime_minutes IS NOT NULL AND m.runtime_minutes <= ?'; $types .= 'i'; $params[] = min(999, (int)$opts['max_runtime']); }
    $base = ' FROM movie_watchlist m WHERE ' . implode(' AND ', $where);

    $total = (int)db_val('SELECT COUNT(*)' . $base, $types, $params);
    if ($total === 0) return ['movie' => null, 'pool' => 0, 'reason' => 'none'];

    $exclude = array_slice(array_values(array_unique(array_filter(array_map('intval', $exclude), fn($i) => $i > 0))), 0, 300);
    $t2 = $types; $p2 = $params; $w2 = '';
    if ($exclude) {
        $w2 = ' AND m.watchlist_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')';
        $t2 .= str_repeat('i', count($exclude)); $p2 = array_merge($p2, $exclude);
    }
    $pool = (int)db_val('SELECT COUNT(*)' . $base . $w2, $t2, $p2);
    if ($pool === 0) return ['movie' => null, 'pool' => 0, 'reason' => 'exhausted'];

    $pick = db_one('SELECT ' . CV_CARD_COLUMNS . $base . $w2 . ' ORDER BY m.watchlist_id LIMIT 1 OFFSET ?', $t2 . 'i', array_merge($p2, [random_int(0, $pool - 1)]));
    $pick = cv_normalize_movie($pick);
    $pick['tags'] = cv_tag_map($uid)[$pick['watchlist_id']] ?? [];
    return ['movie' => $pick, 'pool' => $pool, 'reason' => ''];
}
