<?php
/**
 * Cinema Vault — "Picked For You".
 *
 * This is a transparent, rule-based ranking of the films ALREADY in the user's
 * Plan to Watch list. It is NOT AI and it does not look at other users or at any
 * external catalogue. Signals used (all from the user's own data):
 *   - ratings   (5★ = strong like, 1★ = dislike)
 *   - favorites (extra weight)
 *   - genres    (affinity learned from rated/favorite films)
 *   - tags and collections shared with films they loved
 *   - priority  (a small nudge so "must watch" films surface)
 *
 * When a movie API is connected later (the schema already has external_movie_id,
 * release_date, runtime, director, cast, language, country), this module can also
 * score unseen catalogue titles; the interface of cv_recommendations() stays the same.
 */

const CV_REC_MIN_SIGNALS = 3;      // need at least this many rated/favorite films before suggesting anything

/**
 * @param array $movies         rows from cv_all_movies()
 * @param array $tagMap         [movie_id => [{id,name}]]
 * @param array $collectionMap  [movie_id => [{id,name}]]
 * @return array{enough:bool, because:array, picks:array}
 */
function cv_recommendations(array $movies, array $tagMap, array $collectionMap, int $limit = 6): array {
    $loved = [];                      // films the user clearly liked
    $genreAff = [];                   // genre => affinity score
    $signals = 0;

    foreach ($movies as $m) {
        $r = (int)$m['rating']; $fav = !empty($m['is_favorite']);
        if ($r === 0 && !$fav) continue;
        $signals++;
        $w = $r > 0 ? ($r - 3) / 2 : 0;                 // 5★ +1, 4★ +.5, 3★ 0, 2★ -.5, 1★ -1
        if ($fav) $w += 1;
        if ($m['watch_count'] > 1) $w += min(1, 0.25 * ($m['watch_count'] - 1));
        $g = (string)($m['genre'] ?? '');
        if ($g !== '') $genreAff[$g] = ($genreAff[$g] ?? 0) + $w;
        if ($r >= 4 || $fav) $loved[] = $m;
    }

    usort($loved, fn($a, $b) => [$b['rating'], (int)$b['is_favorite'], $b['watch_count']] <=> [$a['rating'], (int)$a['is_favorite'], $a['watch_count']]);
    $because = array_map(fn($m) => ['id' => $m['watchlist_id'], 'title' => $m['movie_title'], 'rating' => (int)$m['rating'], 'favorite' => !empty($m['is_favorite'])],
                         array_slice($loved, 0, 3));

    if ($signals < CV_REC_MIN_SIGNALS || !$loved) return ['enough' => false, 'because' => $because, 'picks' => []];

    // tags / collections attached to loved films
    $likedTags = []; $likedCols = [];
    foreach ($loved as $m) {
        foreach ($tagMap[$m['watchlist_id']] ?? [] as $t) $likedTags[$t['id']][] = $m;
        foreach ($collectionMap[$m['watchlist_id']] ?? [] as $c) $likedCols[$c['id']][] = $m;
    }

    $picks = [];
    foreach ($movies as $m) {
        if ($m['status'] !== 'Plan to Watch') continue;
        $score = 0.0; $reasons = [];
        $g = (string)($m['genre'] ?? '');

        if ($g !== '' && ($genreAff[$g] ?? 0) != 0) {
            $score += $genreAff[$g];
            if ($genreAff[$g] > 0) {
                $src = array_values(array_filter($loved, fn($l) => $l['genre'] === $g));
                $reasons[] = $src ? 'Because you rated ' . $src[0]['movie_title'] . ($src[0]['rating'] ? ' ' . str_repeat('★', $src[0]['rating']) : ' a favorite')
                                  : "You like $g";
            }
        }
        foreach ($tagMap[$m['watchlist_id']] ?? [] as $t) {
            if (isset($likedTags[$t['id']])) {
                $score += 0.5;
                if (count($reasons) < 3) $reasons[] = 'Tagged “' . $t['name'] . '” like ' . $likedTags[$t['id']][0]['movie_title'];
            }
        }
        foreach ($collectionMap[$m['watchlist_id']] ?? [] as $c) {
            if (isset($likedCols[$c['id']])) {
                $score += 0.5;
                if (count($reasons) < 3) $reasons[] = 'In “' . $c['name'] . '” with ' . $likedCols[$c['id']][0]['movie_title'];
            }
        }
        if (!empty($m['is_favorite'])) { $score += 0.5; $reasons[] = 'One of your favorites'; }
        if ($m['priority'] === 'High') { $score += 0.75; $reasons[] = 'Marked must-watch'; }
        elseif ($m['priority'] === 'Low') $score -= 0.25;

        if ($score > 0) $picks[] = ['movie' => $m, 'score' => round($score, 2), 'reasons' => array_slice(array_values(array_unique($reasons)), 0, 2)];
    }
    usort($picks, fn($a, $b) => [$b['score'], $b['movie']['watchlist_id']] <=> [$a['score'], $a['movie']['watchlist_id']]);

    return ['enough' => true, 'because' => $because, 'picks' => array_slice($picks, 0, $limit)];
}
