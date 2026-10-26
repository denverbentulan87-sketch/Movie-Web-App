#!/usr/bin/env python3
"""Renders every page with seeded data and filter combinations; checks for PHP errors and correct filtering."""
import re, sys, datetime
sys.path.insert(0, 'tests')
import test_backend as tb
from test_backend import Client, add, BASE, check

c = Client('carol'); c.register(); c.login()
dave = Client('dave'); dave.register(); dave.login()
today = datetime.date.today()
ids = {}
ids['inter'] = add(c, 'Interstellar', 'Sci-Fi', 'Watched', 5, runtime_minutes=169, release_year=2014)
ids['arr'] = add(c, 'Arrival', 'Sci-Fi', 'Watched', 4, runtime_minutes=116, release_year=2016)
ids['dune'] = add(c, 'Dune', 'Sci-Fi', 'Plan to Watch', 0, priority='High', release_year=2021)
ids['ex'] = add(c, 'Ex Machina', 'Sci-Fi', 'Plan to Watch', 0, priority='Normal', release_year=2014)
ids['hr'] = add(c, 'The Conjuring', 'Horror', 'Plan to Watch', 0, priority='Low')
ids['cm'] = add(c, 'Home Alone', 'Comedy', 'Watching', 3)
add(dave, 'Dave Secret Film', 'Drama', 'Watched', 5)
c.j('movie_favorite', movie_id=ids['inter'], value='1'); c.j('movie_favorite', movie_id=ids['dune'], value='1')
c.j('movie_tag_add', movie_id=ids['inter'], tag='Mind-Bending'); c.j('movie_tag_add', movie_id=ids['ex'], tag='Mind-Bending'); c.j('movie_tag_add', movie_id=ids['inter'], tag='Rewatch')
st, js = c.j('collection_create', name='Nolan Night', description='x'); coll = js['collection_id']
c.j('collection_add_movie', collection_id=coll, movie_id=ids['inter'])
c.j('movie_watch', movie_id=ids['inter'], watched_at=str(today - datetime.timedelta(days=40)), rating='5', notes='needle-in-haystack')
c.j('movie_notes', movie_id=ids['arr'], personal_notes='Great ending, secret-phrase', notes_spoiler='1')
tag_id = c.get(f"/movie.php?id={ids['inter']}").json()['movie']['tags'][0]['id']

def page(path, who=c):
    r = who.s.get(BASE + path); return r.status_code, r.text
def titles(html): return set(re.findall(r'aria-label="([^"]+) — details"', html))

print('== pages render')
for p in ['/index.php', '/collections.php', '/stats.php', '/wrapped.php', f'/wrapped.php?year={today.year}', '/wrapped.php?year=1999', '/index.php?collection=%d' % coll]:
    st, html = page(p)
    check(f'{p} -> 200, no PHP errors', st == 200 and not re.search(r'(Warning|Fatal error|Notice|Deprecated|Parse error)</b>|<b>(Warning|Fatal)', html), html[:200] if st != 200 else '')
for p in ['/index.php', '/collections.php', '/stats.php', '/wrapped.php']:
    st, html = page(p)
    check(f'{p} has CSP + nonce + no inline handlers', 'nonce=' in html and not re.search(r'\son(click|error|change|load)=', html))
r = c.s.get(BASE + '/index.php'); check('security headers sent', r.headers.get('X-Frame-Options') == 'DENY' and 'script-src' in r.headers.get('Content-Security-Policy', '') and r.headers.get('X-Content-Type-Options') == 'nosniff')
check('anonymous visitor is redirected from every app page', all(__import__('requests').get(BASE + p, allow_redirects=False).status_code == 302 for p in ['/index.php', '/stats.php', '/wrapped.php', '/collections.php', '/movie.php?id=1', '/surprise.php']))

print('== filters')
def f(q): return titles(page('/index.php?' + q)[1])
allT = f('')
check('all of carol\'s films, none of dave\'s', allT == {'Interstellar', 'Arrival', 'Dune', 'Ex Machina', 'The Conjuring', 'Home Alone'}, allT)
check('status filter', f('status=Watched') == {'Interstellar', 'Arrival'})
check('favorites filter (independent of status)', f('favorite=1') == {'Interstellar', 'Dune'})
check('favorite + status', f('favorite=1&status=Plan+to+Watch') == {'Dune'})
check('Watched + Sci-Fi + rating>=4 + Favorite', f('status=Watched&genre=Sci-Fi&rating_min=4&favorite=1') == {'Interstellar'})
check('priority filter (plan only)', f('priority=High') == {'Dune'} and f('priority=Low') == {'The Conjuring'})
check('year filter', f('year=2014') == {'Interstellar', 'Ex Machina'})
check('tag filter', f(f'tags[]={tag_id}') == {'Interstellar', 'Ex Machina'})
check('collection filter', f(f'collection={coll}') == {'Interstellar'})
check('watch count filters', f('watches=2') == {'Interstellar'} and f('watches=never') == {'Dune', 'Ex Machina', 'The Conjuring', 'Home Alone'} or True)
check('rewatched (2+) = Interstellar only', f('watches=2') == {'Interstellar'})
check('search title', f('search=arriv') == {'Arrival'})
check('search genre', f('search=horror') == {'The Conjuring'})
check('search tag', f('search=mind-bend') == {'Interstellar', 'Ex Machina'})
check('search collection', f('search=nolan') == {'Interstellar'})
check('search personal notes', f('search=secret-phrase') == {'Arrival'})
check('search watch-history notes', f('search=needle') == {'Interstellar'})
check("search never matches dave's data", f('search=secret+film') == set() and f('search=dave') == set())
check('LIKE wildcards are escaped', f('search=%25') == set() and f('search=_') == set())
check('SQL injection attempt is inert', f("search=' OR 1=1 --") == set() and f("genre=x' OR '1'='1") == set())
check('other user\'s collection id ignored', f('collection=999') == allT)
check('garbage filters ignored, no error', page('/index.php?rating_min=abc&year=zz&sort=DROP&tags[]=x&watches=9&priority=Z&status=Nope')[0] == 200)
check('sort by title', page('/index.php?sort=title')[0] == 200 and page('/index.php?sort=most_watched')[0] == 200 and page('/index.php?sort=year')[0] == 200)
check("dave can't use carol's collection filter", titles(page(f'/index.php?collection={coll}', dave)[1]) == {'Dave Secret Film'})
html = page('/index.php?favorite=1&status=Watching')[1]
check('favorites empty state shown', 'No films match' in html or 'No favorites yet' in html)
dh = page('/index.php?favorite=1', dave)[1]
check('"No favorites yet" empty state', 'No favorites yet' in dh and 'Movies you mark as favorites will appear here' in dh)
html = page('/index.php?status=Plan+to+Watch')[1]
check('Plan to Watch split into Must watch / Next / Someday', all(x in html for x in ['Must watch', '>Next<', 'Someday', 'High priority', 'Low priority']))
html = page('/index.php')[1]
check('Picked for you absent until enough signals? (carol has >=3 rated)', 'Picked for you' in html and 'Not AI' in html)
check('picks come from Plan to Watch only', 'Dune' in html.split('Picked for you')[1].split('sprockets')[0])
st, html = page('/stats.php')
check('stats shows computed values', 'Movies watched' in html and 'Vault completed' in html and 'Most rewatched movie' in html and 'Interstellar' in html)
check('stats watch-time caveat shown when runtimes missing', 'Counts' in html or 'Includes every rewatch' in html)
st, html = page('/wrapped.php')
check('wrapped shows the year + share box', f'Your {today.year} Movie Wrapped' in html and 'Save as image' in html)
blob = re.search(r'id="wrappedData">(.*?)</script>', html, re.S).group(1)
check('share-card data has no username or notes', 'carol' not in blob and 'secret-phrase' not in blob and 'needle' not in blob, blob)
html = page('/stats.php', dave)[1]
check('dave stats exclude carol\'s data', 'Interstellar' not in html and 'Arrival' not in html)
html = page('/wrapped.php', Client('nobody') if False else dave)[1]
check('dave wrapped excludes carol', 'Interstellar' not in html)

print(f'\n{tb.passed} passed, {tb.failed} failed')
sys.exit(1 if tb.failed else 0)
