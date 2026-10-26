#!/usr/bin/env python3
"""
End-to-end backend tests over HTTP against tests/serve.sh (SQLite shim, fresh DB).
Run:  tests/serve.sh &   then   python3 tests/test_backend.py
Covers: auth, CSRF, validation, uploads, every action, and cross-user data isolation.
"""
import re, sys, io, json, struct, zlib, requests

BASE = 'http://127.0.0.1:8099'
passed = failed = 0
def check(name, cond, extra=''):
    global passed, failed
    if cond: passed += 1; print(f'  ok   {name}')
    else: failed += 1; print(f'  FAIL {name} {extra}')

def png_bytes(w=4, h=6, color=(200, 160, 90)):
    raw = b''.join(b'\x00' + bytes(color) * w for _ in range(h))
    def chunk(t, d): return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    return b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw)) + chunk(b'IEND', b'')

class Client:
    def __init__(self, name):
        self.name = name; self.s = requests.Session(); self.token = None
    def refresh_token(self):
        r = self.s.get(BASE + '/homepage.php'); m = re.search(r'name="csrf_token" value="([0-9a-f]+)"', r.text)
        self.token = m.group(1) if m else None
        return r
    def register(self, pw='secret123'):
        self.refresh_token()
        return self.s.post(BASE + '/register.php', data={'register': '1', 'username': self.name, 'password': pw, 'confirm': pw, 'csrf_token': self.token},
                           headers={'X-Requested-With': 'XMLHttpRequest'})
    def login(self, pw='secret123'):
        self.refresh_token()
        return self.s.post(BASE + '/login.php', data={'login': '1', 'username': self.name, 'password': pw, 'csrf_token': self.token},
                           headers={'X-Requested-With': 'XMLHttpRequest'})
    def act(self, action, files=None, token=True, **data):
        d = {'action': action, **{k: v for k, v in data.items()}}
        if token: d['csrf_token'] = self.token
        return self.s.post(BASE + '/actions.php', data=d, files=files, headers={'X-Requested-With': 'XMLHttpRequest'}, allow_redirects=False)
    def j(self, action, **data):
        r = self.act(action, **data)
        try: return r.status_code, r.json()
        except Exception: return r.status_code, {'raw': r.text[:300]}
    def get(self, path, **kw): return self.s.get(BASE + path, headers={'X-Requested-With': 'XMLHttpRequest'}, **kw)

def add(c, title, genre='Sci-Fi', status='Plan to Watch', rating=0, **extra):
    st, js = c.j('movie_add', movie_title=title, genre=genre, status=status, rating=rating, **extra)
    assert st == 200 and js.get('ok'), (title, st, js)
    return js['movie']['watchlist_id']

def main():
    global passed, failed
    print('== auth & CSRF')
    a, b = Client('alice'), Client('bob')
    check('register alice', a.register().json().get('ok') is True)
    check('register bob', b.register().json().get('ok') is True)
    check('register without CSRF fails', Client('eve').s.post(BASE + '/register.php', data={'register': '1', 'username': 'eve1', 'password': 'secret123', 'confirm': 'secret123'}, headers={'X-Requested-With': 'XMLHttpRequest'}).status_code == 422)
    check('wrong password rejected', Client('alice').login('nope').status_code == 401)
    check('login alice', a.login().json().get('ok') is True)
    check('login bob', b.login().json().get('ok') is True)
    check('actions without login -> 401 JSON', Client('x').act('movie_delete', token=False, movie_id=1).status_code == 401)
    check('actions GET -> 405 (no state change over GET)', a.s.get(BASE + '/actions.php?action=movie_delete&movie_id=1', allow_redirects=False).status_code in (302, 405))
    check('POST without CSRF token -> 403', a.act('movie_add', token=False, movie_title='X', genre='Drama').status_code == 403)
    tok = a.token; a.token = 'deadbeef'
    check('POST with wrong CSRF token -> 403', a.act('movie_add', movie_title='X', genre='Drama').status_code == 403)
    a.token = tok
    check('unknown action -> 400', a.act('drop_database').status_code == 400)

    print('== legacy endpoints are neutralised')
    vid = add(a, 'Legacy Check')
    check('delete_all.php (no login) does not delete', (requests.get(BASE + '/delete_all.php', allow_redirects=False).status_code in (301, 302)) and a.get(f'/movie.php?id={vid}').json().get('ok'))
    check('delete.php?id= GET does not delete', (a.s.get(BASE + f'/delete.php?id={vid}', allow_redirects=False), a.get(f'/movie.php?id={vid}').json().get('ok'))[1])
    check('logout via GET does not log out', (a.s.get(BASE + '/logout.php', allow_redirects=False), a.get(f'/movie.php?id={vid}').json().get('ok'))[1])

    print('== movies: add / validation / duplicates')
    inter = add(a, 'Interstellar', 'Sci-Fi', 'Watched', 5, runtime_minutes=169, release_year=2014)
    arrival = add(a, 'Arrival', 'Sci-Fi', 'Plan to Watch', 0, runtime_minutes=116, release_year=2016)
    dune = add(a, 'Dune', 'Sci-Fi', 'Plan to Watch', 0, priority='High', runtime_minutes=155, release_year=2021)
    ghost = add(a, 'Ghost Story', 'Horror', 'Plan to Watch', 0, priority='Low')
    b1 = add(b, 'Bob Private Movie', 'Drama', 'Watched', 4)
    check('bad status rejected', a.j('movie_add', movie_title='Z', genre='Drama', status='Hacked')[0] == 422)
    check('rating 9 rejected', a.j('movie_add', movie_title='Z', genre='Drama', rating='9')[0] == 422)
    check('bad genre rejected', a.j('movie_add', movie_title='Z', genre='<script>')[0] == 422)
    check('empty title rejected', a.j('movie_add', movie_title='  ', genre='Drama')[0] == 422)
    check('256-char title rejected', a.j('movie_add', movie_title='x' * 256, genre='Drama')[0] == 422)
    check('duplicate title (case/space-insensitive) rejected', a.j('movie_add', movie_title=' interstellar ', genre='Sci-Fi')[0] == 422)
    check('same title for another user is fine', b.j('movie_add', movie_title='Interstellar', genre='Sci-Fi')[0] == 200)
    check('watched on add creates 1 history entry', a.get(f'/movie.php?id={inter}').json()['movie']['watch_count'] == 1)

    print('== uploads')
    png = png_bytes()
    st, js = a.j('movie_add', movie_title='With Cover', genre='Drama') if False else (None, None)
    r = a.act('movie_add', files={'cover_file': ('evil.php', png, 'image/png')}, movie_title='Cover PNG as .php', genre='Drama')
    js = r.json(); url = js['movie']['cover_url']
    check('png named .php stored with real extension', r.status_code == 200 and url.endswith('.png'), url)
    check('stored cover is served as an image', requests.get(BASE + '/' + url).headers.get('Content-Type', '').startswith('image/'))
    r = a.act('movie_add', files={'cover_file': ('x.png', b'<?php system($_GET[0]); ?>', 'image/png')}, movie_title='Fake image', genre='Drama')
    check('PHP code disguised as image rejected', r.status_code == 422, r.text[:120])
    r = a.act('movie_add', files={'cover_file': ('x.gif', b'GIF89a;<?php echo 1;?>', 'image/gif')}, movie_title='Fake gif', genre='Drama')
    check('GIF header + PHP payload rejected', r.status_code == 422, r.text[:120])
    r = a.act('movie_add', files={'cover_file': ('big.png', png + b'\0' * (5 * 1024 * 1024 + 10), 'image/png')}, movie_title='Too big', genre='Drama')
    check('oversize upload rejected', r.status_code == 422)
    mid_cover = js['movie']['watchlist_id']
    r = a.act('movie_edit', files={'cover_file': ('n.png', png_bytes(color=(1, 2, 3)), 'image/png')}, movie_id=mid_cover, movie_title='Cover PNG as .php', genre='Drama', status='Plan to Watch', rating=0)
    new_url = r.json()['movie']['cover_url']
    check('replacing cover deletes old file', new_url != url and requests.get(BASE + '/' + url).status_code == 404)

    print('== favorites / priority / status / notes')
    check('favorite on', a.j('movie_favorite', movie_id=inter, value='1')[1]['movie']['is_favorite'] == 1)
    check('favorite independent of status (Plan to Watch)', a.j('movie_favorite', movie_id=arrival, value='1')[1]['movie']['status'] == 'Plan to Watch')
    check('favorite off', a.j('movie_favorite', movie_id=arrival, value='0')[1]['movie']['is_favorite'] == 0)
    check('priority change', a.j('movie_priority', movie_id=arrival, priority='High')[1]['movie']['priority'] == 'High')
    check('bad priority rejected', a.j('movie_priority', movie_id=arrival, priority='Urgent')[0] == 422)
    check('start watching', a.j('movie_status', movie_id=arrival, status='Watching')[1]['movie']['status'] == 'Watching')
    js = a.j('movie_status', movie_id=arrival, status='Watched')[1]
    check('status -> Watched records watch (count 1, last date set)', js['movie']['watch_count'] == 1 and js['movie']['last_watched_at'] and len(js['history']) == 1)
    js = a.j('movie_notes', movie_id=arrival, personal_notes='Great ending <script>alert(1)</script>', notes_spoiler='1')[1]
    check('notes saved with spoiler flag', js['movie']['personal_notes'].startswith('Great ending') and js['movie']['notes_spoiler'] == 1)
    js = a.j('movie_notes', movie_id=arrival, personal_notes='', notes_spoiler='1')[1]
    check('notes cleared (spoiler flag reset)', js['movie']['personal_notes'] is None and js['movie']['notes_spoiler'] == 0)

    print('== rewatch & history')
    js = a.j('movie_watch', movie_id=inter, watched_at='2026-06-03', rating='5', notes='Great movie. Rewatched it.')[1]
    check('Watched Again: count 2, no duplicate movie', js['movie']['watch_count'] == 2 and len(js['history']) == 2)
    js = a.j('movie_watch', movie_id=inter, watched_at='2026-10-12', rating='4')[1]
    import datetime; today = datetime.date.today().isoformat()   # the first watch (added as Watched) is dated today
    check('count 3, last watched = newest date', js['movie']['watch_count'] == 3 and js['movie']['last_watched_at'] == today, js['movie'].get('last_watched_at'))
    check('history ordered newest first', [h['watched_at'] for h in js['history']] == [today, '2026-10-12', '2026-06-03'], js['history'])
    check('future date rejected', a.j('movie_watch', movie_id=inter, watched_at='2099-01-01')[0] == 422)
    check('garbage date rejected', a.j('movie_watch', movie_id=inter, watched_at='2026-13-45')[0] == 422)
    hid = js['history'][0]['id']
    js = a.j('history_delete', history_id=hid)[1]
    check('deleting a watch entry resyncs count & last date', js['movie']['watch_count'] == 2 and js['movie']['last_watched_at'] == '2026-10-12', js['movie']['last_watched_at'])
    check('no extra Interstellar rows', a.get('/movie.php?id=%d' % inter).json()['movie']['movie_title'] == 'Interstellar')

    print('== tags')
    js = a.j('movie_tag_add', movie_id=inter, tag='Mind-Bending')[1]
    check('tag created + attached', [t['name'] for t in js['movie']['tags']] == ['Mind-Bending'])
    a.j('movie_tag_add', movie_id=inter, tag='  #rewatch ')
    a.j('movie_tag_add', movie_id=dune, tag='mind-bending')
    js = a.get(f'/movie.php?id={dune}').json()
    check('tag names are case-insensitive unique (reused, not duplicated)', len(js['all_tags']) == 2 and js['movie']['tags'][0]['name'] == 'Mind-Bending')
    check('multiple tags on one movie', len(a.get(f'/movie.php?id={inter}').json()['movie']['tags']) == 2)
    tid = js['movie']['tags'][0]['id']
    check('rename tag', a.j('tag_rename', tag_id=tid, name='Mind Bending')[1].get('ok'))
    check('rename to existing name rejected', a.j('tag_rename', tag_id=tid, name='rewatch')[0] == 422)
    check('remove tag from movie', not a.j('movie_tag_remove', movie_id=dune, tag_id=tid)[1]['movie']['tags'])
    check('empty tag rejected', a.j('movie_tag_add', movie_id=dune, tag='   ')[0] == 422)
    check('51-char tag rejected', a.j('movie_tag_add', movie_id=dune, tag='t' * 51)[0] == 422)

    print('== collections')
    st, js = a.j('collection_create', name='Christopher Nolan', description='Mind-benders')
    check('create collection', st == 200 and js['collection_id'])
    cid = js['collection_id']
    check('duplicate collection name rejected', a.j('collection_create', name='christopher nolan')[0] == 422)
    check('add movie to collection', a.j('collection_add_movie', collection_id=cid, movie_id=inter)[1]['movie']['collections'][0]['name'] == 'Christopher Nolan')
    check('adding twice is harmless', a.j('collection_add_movie', collection_id=cid, movie_id=inter)[0] == 200)
    a.j('collection_add_movie', collection_id=cid, movie_id=dune)
    check('movie can be in several collections', (lambda: a.j('collection_create', name='Sci-Fi')[0] == 200 and a.j('collection_add_movie', collection_id=a.j('collection_create', name='Weekend')[1]['collection_id'], movie_id=inter)[0] == 200)())
    check('rename/describe collection', a.j('collection_update', collection_id=cid, name='Nolan', description='x')[1].get('ok'))
    check('remove movie from collection', not [c for c in a.j('collection_remove_movie', collection_id=cid, movie_id=dune)[1]['movie']['collections'] if c['name'] == 'Nolan'])

    print('== surprise me')
    r = a.get('/surprise.php').json()
    check('surprise returns a Plan-to-Watch film of alice', r['movie']['status'] == 'Plan to Watch' and r['movie']['watchlist_id'] != b1 and r['pool'] >= 2)
    seen = set()
    for _ in range(30):
        r = a.get('/surprise.php?priority=High').json(); seen.add(r['movie']['watchlist_id'] if r['movie'] else None)
    check('priority filter respected', seen == {dune}, seen)
    shown = []
    for _ in range(20):
        r = a.get('/surprise.php?exclude=' + ','.join(map(str, shown))).json()
        if not r['movie']: break
        shown.append(r['movie']['watchlist_id'])
    check('"pick again" never repeats and ends with exhausted', len(shown) == len(set(shown)) >= 2 and r['reason'] == 'exhausted', (shown, r))
    r = a.get('/surprise.php?max_runtime=100').json()
    check('runtime filter excludes films without runtime', r['reason'] == 'none')
    r = b.get('/surprise.php').json()
    check('bob never gets alice\'s movies', r['reason'] == 'none' or r['movie']['watchlist_id'] not in (inter, arrival, dune, ghost))

    print('== DATA ISOLATION (bob attacking alice)')
    check('bob cannot read alice movie detail', b.get(f'/movie.php?id={inter}').status_code == 404)
    for action, data in [('movie_delete', {}), ('movie_favorite', {'value': '1'}), ('movie_priority', {'priority': 'Low'}), ('movie_status', {'status': 'Watching'}),
                         ('movie_notes', {'personal_notes': 'pwned'}), ('movie_watch', {'watched_at': '2026-01-01'}), ('movie_tag_add', {'tag': 'x'}),
                         ('movie_edit', {'movie_title': 'Hacked', 'genre': 'Drama', 'status': 'Watched'})]:
        check(f'bob cannot {action} on alice movie', b.j(action, movie_id=inter, **data)[0] == 404)
    check('alice movie unchanged after attacks', (lambda m: m['movie_title'] == 'Interstellar' and m['status'] == 'Watched' and m['personal_notes'] is None)(a.get(f'/movie.php?id={inter}').json()['movie']))
    a_hist = a.get(f'/movie.php?id={inter}').json()['history'][0]['id']
    check('bob cannot delete alice history', b.j('history_delete', history_id=a_hist)[0] == 404)
    bob_tag = b.j('movie_tag_add', movie_id=b1, tag='BobTag')[1]['movie']['tags'][0]['id']
    alice_tag = a.get(f'/movie.php?id={inter}').json()['movie']['tags'][0]['id']
    check("bob cannot attach alice's tag to bob's movie", b.j('movie_tag_remove', movie_id=b1, tag_id=alice_tag)[0] == 404)
    check("bob cannot rename/delete alice's tag", b.j('tag_rename', tag_id=alice_tag, name='x')[0] == 404 and b.j('tag_delete', tag_id=alice_tag)[0] == 404)
    check("bob cannot use alice's collection", b.j('collection_add_movie', collection_id=cid, movie_id=b1)[0] == 404)
    check("bob cannot rename/delete alice's collection", b.j('collection_update', collection_id=cid, name='x')[0] == 404 and b.j('collection_delete', collection_id=cid)[0] == 404)
    check("bob cannot put alice's movie in his collection", (lambda c: b.j('collection_add_movie', collection_id=c, movie_id=inter)[0] == 404)(b.j('collection_create', name='Mine')[1]['collection_id']))
    check("alice's tags/collections intact", len(a.get(f'/movie.php?id={inter}').json()['all_tags']) == 2 and any(c['name'] == 'Nolan' for c in a.get(f'/movie.php?id={inter}').json()['all_collections']))
    check('bob "clear all" only clears bob', (b.j('movies_delete_all')[0] == 200) and a.get(f'/movie.php?id={inter}').json().get('ok') and b.get(f'/movie.php?id={b1}').status_code == 404)

    print('== cascades')
    tmp = add(a, 'Cascade Me', 'Drama', 'Watched', 3)
    a.j('movie_tag_add', movie_id=tmp, tag='cascade-tag'); a.j('collection_add_movie', collection_id=cid, movie_id=tmp)
    import sqlite3
    db = sqlite3.connect('/tmp/cv_test.sqlite')
    n = lambda q: db.execute(q).fetchone()[0]
    check('links exist before delete', n(f'select count(*) from movie_tags where movie_id={tmp}') == 1 and n(f'select count(*) from watch_history where movie_id={tmp}') == 1)
    a.j('movie_delete', movie_id=tmp)
    db.close(); db = sqlite3.connect('/tmp/cv_test.sqlite')
    check('deleting a movie cascades history, tag + collection links (no orphans)', n(f'select count(*) from movie_tags where movie_id={tmp}') == 0 and n(f'select count(*) from watch_history where movie_id={tmp}') == 0 and n(f'select count(*) from collection_movies where movie_id={tmp}') == 0)
    check('...but the tag and collection themselves survive', n("select count(*) from tags where name='cascade-tag'") == 1)
    a.j('collection_delete', collection_id=cid)
    db.close(); db = sqlite3.connect('/tmp/cv_test.sqlite')
    check('deleting a collection keeps its movies', n(f'select count(*) from movie_watchlist where watchlist_id={inter}') == 1 and n(f'select count(*) from collection_movies where collection_id={cid}') == 0)

    print(f'\n{passed} passed, {failed} failed')
    sys.exit(1 if failed else 0)


if __name__ == '__main__':
    main()
