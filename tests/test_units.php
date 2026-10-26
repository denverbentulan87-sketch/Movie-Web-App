<?php
// php -n -d extension=mbstring tests/test_units.php
require __DIR__ . '/../includes/statistics.php';
require __DIR__ . '/../includes/recommend.php';
$p = $f = 0;
function ok(string $n, bool $c, $x = '') { global $p, $f; if ($c) { $p++; echo "  ok   $n\n"; } else { $f++; echo "  FAIL $n " . json_encode($x) . "\n"; } }
function mv($id, $t, $g, $st, $r, $o = []) { return $o + ['watchlist_id' => $id, 'movie_title' => $t, 'genre' => $g, 'status' => $st, 'rating' => $r, 'is_favorite' => 0, 'priority' => 'Normal', 'watch_count' => 0, 'last_watched_at' => null, 'runtime_minutes' => null, 'release_year' => null, 'cover_url' => null]; }
function hi($id, $mid, $d, $r = null) { return ['history_id' => $id, 'movie_id' => $mid, 'watched_at' => $d, 'rating' => $r]; }
$now = new DateTimeImmutable('2026-10-25');

echo "== stats: empty / missing data\n";
$s = cv_stats([], [], $now);
ok('empty vault: no division by zero, nulls not zeros', $s['total'] === 0 && $s['avg_rating'] === null && $s['completed_pct'] === null && $s['watch_minutes'] === null && $s['top_genre'] === null && $s['best_genre'] === null && $s['most_watched'] === null);
$s = cv_stats([mv(1, 'A', 'Drama', 'Watched', 0, ['watch_count' => 1])], [hi(1, 1, '2026-10-01')], $now);
ok('unrated film does not make avg rating 0', $s['avg_rating'] === null && $s['rated_count'] === 0);
ok('no runtime => watch time unknown (null), not 0', $s['watch_minutes'] === null && $s['watch_time_missing'] === 1);

echo "== stats: values\n";
$movies = [
  mv(1, 'Interstellar', 'Sci-Fi', 'Watched', 5, ['watch_count' => 3, 'runtime_minutes' => 100, 'last_watched_at' => '2026-10-12', 'is_favorite' => 1]),
  mv(2, 'Arrival', 'Sci-Fi', 'Watched', 4, ['watch_count' => 1, 'runtime_minutes' => 50, 'last_watched_at' => '2026-10-02']),
  mv(3, 'Solo5', 'Horror', 'Watched', 5, ['watch_count' => 1, 'last_watched_at' => '2026-09-01']),
  mv(4, 'Plan', 'Drama', 'Plan to Watch', 0),
  mv(5, 'Now', 'Drama', 'Watching', 3),
];
$hist = [hi(1, 1, '2026-10-12', 5), hi(2, 1, '2026-06-03', 5), hi(3, 1, '2025-12-25', 4), hi(4, 2, '2026-10-02', 4), hi(5, 3, '2026-09-01', 5)];
$s = cv_stats($movies, $hist, $now);
ok('counts', $s['total'] === 5 && $s['watched'] === 3 && $s['watching'] === 1 && $s['planned'] === 1 && $s['favorites'] === 1);
ok('avg rating over rated films only: (5+4+5+3)/4 = 4.3', $s['avg_rating'] === 4.3, $s['avg_rating']);
ok('completed % = 3/5 = 60', $s['completed_pct'] === 60);
ok('watch time = 100*3 + 50*1 = 350 min; covers 2, missing 1', $s['watch_minutes'] === 350 && $s['watch_time_covered'] === 2 && $s['watch_time_missing'] === 1, $s);
ok('total watches = 5', $s['watch_count'] === 5);
ok('most watched genre = Sci-Fi (4 watches)', $s['top_genre']['name'] === 'Sci-Fi' && $s['top_genre']['watches'] === 4);
ok('highest-rated genre prefers genres with 2+ ratings (Sci-Fi 4.5 beats lone 5★ Horror)', $s['best_genre']['name'] === 'Sci-Fi' && $s['best_genre']['avg'] === 4.5, $s['best_genre']);
ok('most watched / rewatched', $s['most_watched']['movie_title'] === 'Interstellar' && $s['most_rewatched']['movie_title'] === 'Interstellar');
ok('this month = 2 distinct films (Interstellar, Arrival)', $s['watched_this_month'] === 2, $s['watched_this_month']);
ok('this year = 3 distinct films', $s['watched_this_year'] === 3);
ok('monthly has 12 buckets ending Oct 2026', count($s['monthly']) === 12 && array_key_last($s['monthly']) === '2026-10' && array_key_first($s['monthly']) === '2025-11' && $s['monthly']['2026-10'] === 2);
$none = cv_stats([mv(1, 'A', 'Drama', 'Watched', 4, ['watch_count' => 1])], [], $now);
ok('no rewatch => most_rewatched null', $none['most_rewatched'] === null);

echo "== wrapped\n";
ok('years newest first', cv_wrapped_years($hist) === [2026, 2025]);
$w = cv_wrapped($movies, $hist, 2026);
ok('2026: 4 watches of 3 films', $w['watches'] === 4 && $w['movies'] === 3);
ok('2026 hours from known runtimes only (100+100... Interstellar x2 + Arrival = 250 min)', $w['minutes'] === 250 && $w['watch_time_missing'] === 1, $w['minutes']);
ok('2026 avg rating from per-watch ratings (5+5+4+5)/4=4.8', $w['avg_rating'] === 4.8, $w['avg_rating']);
ok('2026 top genre Sci-Fi (3)', $w['top_genre']['name'] === 'Sci-Fi' && $w['top_genre']['count'] === 3);
ok('2026 most watched = Interstellar x2', $w['most_watched']['movie']['movie_title'] === 'Interstellar' && $w['most_watched']['score'] === 2);
ok('2026 Interstellar first watched in 2025 => both 2026 watches are rewatches', $w['most_rewatched']['score'] === 2, $w['most_rewatched']);
ok('2026 highest rated: 5★ tie -> Interstellar (watched more)', $w['highest_rated']['movie']['movie_title'] === 'Interstellar' && $w['highest_rated']['score'] === 5);
ok('busiest month = October (2)', $w['busiest_month']['month'] === 10 && $w['busiest_month']['count'] === 2);
$w25 = cv_wrapped($movies, $hist, 2025);
ok('2025: first-ever watch is not a rewatch', $w25['watches'] === 1 && $w25['most_rewatched'] === null);
$w24 = cv_wrapped($movies, $hist, 2024);
ok('empty year is safe', $w24['watches'] === 0 && $w24['avg_rating'] === null && $w24['top_genre'] === null && $w24['busiest_month'] === null);

echo "== recommendations\n";
$few = cv_recommendations([mv(1, 'A', 'Sci-Fi', 'Watched', 5), mv(2, 'B', 'Sci-Fi', 'Plan to Watch', 0)], [], []);
ok('too little data => no suggestions (not made up)', $few['enough'] === false && $few['picks'] === []);
$lib = [
  mv(1, 'Interstellar', 'Sci-Fi', 'Watched', 5, ['is_favorite' => 1]), mv(2, 'Arrival', 'Sci-Fi', 'Watched', 5), mv(3, 'Dune2', 'Sci-Fi', 'Watched', 4),
  mv(4, 'Bad Horror', 'Horror', 'Watched', 1),
  mv(5, 'Blade Runner', 'Sci-Fi', 'Plan to Watch', 0), mv(6, 'Scary', 'Horror', 'Plan to Watch', 0), mv(7, 'Rom', 'Romance', 'Plan to Watch', 0),
  mv(8, 'Tagged', 'Romance', 'Plan to Watch', 0), mv(9, 'Watching', 'Sci-Fi', 'Watching', 0),
];
$tags = [1 => [['id' => 10, 'name' => 'Mind-Bending']], 8 => [['id' => 10, 'name' => 'Mind-Bending']]];
$rec = cv_recommendations($lib, $tags, []);
$titles = array_map(fn($x) => $x['movie']['movie_title'], $rec['picks']);
ok('enough data', $rec['enough'] === true);
ok('only Plan to Watch films are suggested', !array_diff($titles, ['Blade Runner', 'Scary', 'Rom', 'Tagged']), $titles);
ok('Sci-Fi ranked first, disliked Horror excluded', $titles[0] === 'Blade Runner' && !in_array('Scary', $titles, true), $titles);
ok('shared tag lifts a film above plain Romance', array_search('Tagged', $titles) !== false && (array_search('Rom', $titles) === false || array_search('Tagged', $titles) < array_search('Rom', $titles)), $titles);
ok('reasons are explained', str_contains(implode(' ', $rec['picks'][0]['reasons']), 'Because you rated'), $rec['picks'][0]['reasons']);
ok('"because" lists top loved films', $rec['because'][0]['title'] === 'Interstellar' && count($rec['because']) === 3);

echo "\n$p passed, $f failed\n"; exit($f ? 1 : 0);
