<?php
/**
 * Cinema Vault — statistics & "Movie Wrapped".
 *
 * Pure functions: they take the user's movies / watch history as arrays and return
 * plain data, so they are easy to test and never touch another user's rows.
 *
 * Missing data is never silently treated as zero:
 *   - watch time only counts films that have a runtime and reports how many it covered
 *   - ratings only average films that were actually rated (rating > 0)
 *   - "best" genre / movie values are null when there isn't enough data
 */

/** Largest value of $by among $rows, ties broken by $tie (higher wins). Returns the row or null. */
function cv_stats_top(array $rows, callable $by, ?callable $tie = null, $min = 1): ?array {
    $best = null; $bv = null; $bt = null;
    foreach ($rows as $r) {
        $v = $by($r);
        if ($v === null || $v < $min) continue;
        $t = $tie ? $tie($r) : 0;
        if ($best === null || $v > $bv || ($v === $bv && $t > $bt)) { $best = $r; $bv = $v; $bt = $t; }
    }
    return $best;
}

function cv_minutes_label(int $minutes): string {
    if ($minutes <= 0) return '0 min';
    $h = intdiv($minutes, 60); $m = $minutes % 60;
    return $h > 0 ? ($m ? "{$h} hr {$m} min" : "{$h} hr") : "{$m} min";
}

/**
 * Library-wide statistics.
 * @param array $movies  rows from cv_all_movies()
 * @param array $history rows from cv_all_history()
 */
function cv_stats(array $movies, array $history, ?DateTimeImmutable $now = null): array {
    $now = $now ?? new DateTimeImmutable('now');
    $out = ['total' => count($movies), 'watched' => 0, 'watching' => 0, 'planned' => 0, 'favorites' => 0];
    $rated = 0; $ratingSum = 0; $watchEvents = 0;
    $genreLib = []; $genreWatches = []; $genreRatings = []; $ratingDist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $watchedWithRuntime = 0; $watchedNoRuntime = 0; $minutes = 0;

    foreach ($movies as $m) {
        if ($m['status'] === 'Watched') $out['watched']++;
        elseif ($m['status'] === 'Watching') $out['watching']++;
        else $out['planned']++;
        if ($m['is_favorite']) $out['favorites']++;
        $g = (string)($m['genre'] ?? '');
        if ($g !== '') {
            $genreLib[$g] = ($genreLib[$g] ?? 0) + 1;
            if ($m['watch_count'] > 0) $genreWatches[$g] = ($genreWatches[$g] ?? 0) + $m['watch_count'];
        }
        if ($m['rating'] > 0) {
            $rated++; $ratingSum += $m['rating']; $ratingDist[min(5, $m['rating'])]++;
            if ($g !== '') $genreRatings[$g][] = $m['rating'];
        }
        $watchEvents += $m['watch_count'];
        if ($m['watch_count'] > 0) {
            if (!empty($m['runtime_minutes'])) { $minutes += $m['runtime_minutes'] * $m['watch_count']; $watchedWithRuntime++; }
            else $watchedNoRuntime++;
        }
    }

    $out['completed_pct'] = $out['total'] > 0 ? (int)round($out['watched'] / $out['total'] * 100) : null;
    $out['rated_count']   = $rated;
    $out['avg_rating']    = $rated > 0 ? round($ratingSum / $rated, 1) : null;
    $out['rating_dist']   = $ratingDist;
    $out['watch_count']   = $watchEvents;
    $out['watch_minutes'] = $watchedWithRuntime > 0 ? $minutes : null;
    $out['watch_time_covered'] = $watchedWithRuntime;
    $out['watch_time_missing'] = $watchedNoRuntime;

    // most watched genre = most total watches
    arsort($genreWatches);
    $out['top_genre'] = $genreWatches ? ['name' => array_key_first($genreWatches), 'watches' => reset($genreWatches)] : null;

    // highest-rated genre: prefer genres with 2+ rated films so one 5★ can't win by luck
    $cands = array_filter($genreRatings, fn($r) => count($r) >= 2) ?: $genreRatings;
    $best = null;
    foreach ($cands as $g => $rs) {
        $avg = array_sum($rs) / count($rs);
        if ($best === null || $avg > $best['avg'] || ($avg == $best['avg'] && count($rs) > $best['n'])) $best = ['name' => $g, 'avg' => round($avg, 1), 'n' => count($rs)];
    }
    $out['best_genre'] = $best;

    $out['most_watched']   = cv_stats_top($movies, fn($m) => $m['watch_count'] ?: null, fn($m) => (string)$m['last_watched_at']);
    $out['most_rewatched'] = cv_stats_top($movies, fn($m) => $m['watch_count'] > 1 ? $m['watch_count'] - 1 : null, fn($m) => (string)$m['last_watched_at']);

    // period counts come from the history (distinct movies per period)
    $ym = $now->format('Y-m'); $y = $now->format('Y');
    $mSet = []; $ySet = []; $months = [];
    for ($i = 11; $i >= 0; $i--) $months[$now->modify("first day of this month")->modify("-$i month")->format('Y-m')] = 0;
    foreach ($history as $h) {
        $d = (string)$h['watched_at'];
        if (substr($d, 0, 4) === $y) $ySet[$h['movie_id']] = true;
        if (substr($d, 0, 7) === $ym) $mSet[$h['movie_id']] = true;
        $k = substr($d, 0, 7);
        if (isset($months[$k])) $months[$k]++;
    }
    $out['watched_this_month'] = count($mSet);
    $out['watched_this_year']  = count($ySet);
    $out['monthly'] = $months;                                   // 'YYYY-MM' => watch events

    arsort($genreLib);
    $out['genres'] = $genreLib;                                  // genre => films in library
    $out['history_total'] = count($history);
    return $out;
}

/** Years that have at least one watch event, newest first. */
function cv_wrapped_years(array $history): array {
    $ys = [];
    foreach ($history as $h) $ys[(int)substr((string)$h['watched_at'], 0, 4)] = true;
    krsort($ys);
    return array_keys($ys);
}

/** "Movie Wrapped" for one calendar year. */
function cv_wrapped(array $movies, array $history, int $year): array {
    $byId = []; foreach ($movies as $m) $byId[$m['watchlist_id']] = $m;
    $prefix = sprintf('%04d', $year);

    // first-ever watch date per movie (to tell a first watch from a rewatch)
    $first = [];
    foreach ($history as $h) {
        $mid = $h['movie_id']; $d = (string)$h['watched_at'];
        if (!isset($first[$mid]) || $d < $first[$mid]) $first[$mid] = $d;
    }

    $events = [];                                   // movie_id => [events in $year]
    $total = 0; $ratingSum = 0; $ratingN = 0; $minutes = 0; $withRt = 0; $noRt = 0;
    $genres = []; $months = array_fill(1, 12, 0); $rewatchEvents = []; $bestRated = [];
    foreach ($history as $h) {
        if (substr((string)$h['watched_at'], 0, 4) !== $prefix) continue;
        $mid = $h['movie_id']; $m = $byId[$mid] ?? null; if (!$m) continue;
        $events[$mid][] = $h; $total++;
        $months[(int)substr($h['watched_at'], 5, 2)]++;
        if ($h['rating']) { $ratingSum += $h['rating']; $ratingN++; $bestRated[$mid] = max($bestRated[$mid] ?? 0, $h['rating']); }
        if (!empty($m['runtime_minutes'])) { $minutes += $m['runtime_minutes']; $withRt++; } else $noRt++;
        if (($m['genre'] ?? '') !== '') $genres[$m['genre']] = ($genres[$m['genre']] ?? 0) + 1;
    }
    // a watch is a rewatch unless it is the film's very first viewing ever
    foreach ($events as $mid => $evs) {
        $r = count($evs) - (substr((string)$first[$mid], 0, 4) === $prefix ? 1 : 0);
        if ($r > 0) $rewatchEvents[$mid] = $r;
    }

    $latest = fn($mid) => max(array_column($events[$mid], 'watched_at'));
    $pick = function (array $scores) use ($byId, $latest) {          // [movie_id => score] -> movie row + score
        $bestId = null; $bs = null; $bl = null;
        foreach ($scores as $mid => $sc) {
            $l = $latest($mid);
            if ($bestId === null || $sc > $bs || ($sc === $bs && $l > $bl)) { $bestId = $mid; $bs = $sc; $bl = $l; }
        }
        return $bestId === null ? null : ['movie' => $byId[$bestId], 'score' => $bs];
    };

    arsort($genres);
    $topGenre = $genres ? ['name' => array_key_first($genres), 'count' => reset($genres)] : null;
    $mostWatched = $pick(array_map('count', $events));
    $mostRewatched = $rewatchEvents ? $pick($rewatchEvents) : null;
    $highest = null;
    if ($bestRated) {                      // top rating of the year; ties go to the title watched most
        $top = max($bestRated); $cand = [];
        foreach ($bestRated as $mid => $r) if ($r === $top) $cand[$mid] = count($events[$mid]);
        $highest = $pick($cand); $highest['score'] = $top;
    }
    $busyMonth = null; $mx = max($months);
    if ($mx > 0) $busyMonth = ['month' => (int)array_search($mx, $months, true), 'count' => $mx];

    return [
        'year' => $year,
        'watches' => $total,
        'movies' => count($events),
        'minutes' => $withRt > 0 ? $minutes : null,
        'watch_time_covered' => $withRt, 'watch_time_missing' => $noRt,
        'avg_rating' => $ratingN > 0 ? round($ratingSum / $ratingN, 1) : null,
        'rated_watches' => $ratingN,
        'top_genre' => $topGenre,
        'most_watched' => $mostWatched, 'most_rewatched' => $mostRewatched, 'highest_rated' => $highest,
        'months' => $months, 'busiest_month' => $busyMonth,
        'genres' => array_slice($genres, 0, 5, true),
    ];
}
