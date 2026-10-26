import re, sys
from playwright.sync_api import sync_playwright
B = 'http://127.0.0.1:8099'
errs = []
def ok(n, c): print(('  ok   ' if c else '  FAIL ') + n); errs.append(n) if not c else None
with sync_playwright() as p:
    br = p.chromium.launch(); ctx = br.new_context(viewport={'width': 1360, 'height': 900}); pg = ctx.new_page()
    logs = []
    pg.on('console', lambda m: logs.append(m.text) if m.type in ('error', 'warning') else None)
    pg.on('pageerror', lambda e: logs.append('PAGEERROR ' + str(e)))
    pg.goto(B + '/homepage.php?modal=login'); pg.fill('#loginUser', 'carol'); pg.fill('#loginPass', 'secret123'); pg.click('#loginForm button[type=submit]')
    pg.wait_for_url('**/index.php'); pg.wait_for_selector('.movie-card')
    ok('dashboard loads with cards', pg.locator('.movie-card').count() >= 6)
    ok('Plan sections: Must watch / Next / Someday', all(pg.locator(f'.section-title:text-is("{t}")').count() for t in ['Must watch', 'Next', 'Someday']))
    pg.screenshot(path='/tmp/shots/dash.png', full_page=True)
    # detail modal
    pg.click('.movie-card[aria-label^="Interstellar"] .card-btn-info', force=True); pg.wait_for_selector('#detailModal.open')
    pg.wait_for_selector('#dHistory .hist-item')
    ok('history loaded (2 watches)', pg.locator('#dHistory .hist-item').count() == 2)
    ok('summary shows count', 'Watched 2 times' in pg.inner_text('#dWatchSummary'))
    pg.screenshot(path='/tmp/shots/detail.png')
    pg.click('#dFav'); pg.wait_for_selector('#dFav[aria-pressed="false"]')
    ok('favorite toggles off in modal + card', pg.locator('.movie-card[aria-label^="Interstellar"] .card-fav:not([hidden])').count() == 0)
    pg.fill('#dTagInput', 'Weekend'); pg.press('#dTagInput', 'Enter'); pg.wait_for_selector('#dTags >> text=Weekend')
    ok('tag added without reload', True)
    pg.click('#dWatchBtn'); pg.fill('#dwNotes', 'browser test'); pg.click('#dWatchForm [type=submit]'); pg.wait_for_selector('#dWatchSummary:has-text("3 times")')
    ok('Watched again => 3 times', True)
    pg.click('[data-notes-edit]'); pg.fill('#dnText', 'spoiler note'); pg.check('#dnSpoiler'); pg.click('#dNotesForm [type=submit]')
    pg.wait_for_selector('.spoiler-warn'); ok('spoiler notes hidden behind warning', not pg.locator('.notes-text').is_visible())
    pg.click('[data-spoiler-show]'); ok('spoiler reveal works', pg.locator('.notes-text').is_visible())
    pg.keyboard.press('Escape'); pg.wait_for_load_state('load'); pg.wait_for_selector('.movie-card')
    # surprise
    pg.click('#surpriseBtn'); pg.wait_for_selector('.surprise-card')
    ok('Surprise Me shows a Plan-to-Watch film', pg.locator('.surprise-body h3').inner_text() in ('Dune', 'Ex Machina', 'The Conjuring'))
    pg.select_option('#sPriority', 'High'); pg.click('#surpriseGo'); pg.wait_for_selector('.surprise-body h3:text-is("Dune")')
    pg.screenshot(path='/tmp/shots/surprise.png'); pg.keyboard.press('Escape')
    # filters panel + clear
    pg.click('#advToggle'); ok('filter panel opens', pg.locator('#advPanel').is_visible())
    pg.screenshot(path='/tmp/shots/filters.png')
    pg.goto(B + '/index.php?favorite=1&status=Watched&genre=Sci-Fi&rating_min=4'); ok('combined filters: Clear filters link shown', pg.locator('.clear-link').count() == 1)
    pg.click('.clear-link'); pg.wait_for_url(B + '/index.php')
    # live search
    pg.fill('#searchInput', 'conj'); ok('live search narrows', pg.inner_text('#foundNum') == '1')
    pg.fill('#searchInput', '')
    # pages
    for path, sel in [('/collections.php', '.coll-card'), ('/stats.php', '.journey-tile'), ('/wrapped.php', '.wrapped')]:
        pg.goto(B + path); pg.wait_for_selector(sel); pg.screenshot(path=f'/tmp/shots{path.replace(".php","")}.png', full_page=True)
    pg.goto(B + '/collections.php'); pg.click('[data-new-collection]'); pg.fill('#collName', 'Horror Night'); pg.click('#collSubmit'); pg.wait_for_selector('.coll-card >> text=Horror Night'); ok('collection created via form', True)
    pg.goto(B + '/wrapped.php'); pg.check('#shareTitles')
    with pg.expect_download() as d: pg.click('#wrappedShare')
    ok('share card downloads a PNG', d.value.suggested_filename.endswith('.png'))
    # logout via POST
    pg.click('.btn-logout'); pg.click('#logoutModal [type=submit]'); pg.wait_for_url('**/homepage.php')
    pg.goto(B + '/index.php'); ok('after logout, vault is protected', 'homepage.php' in pg.url)
    # mobile
    m = br.new_context(viewport={'width': 390, 'height': 800}, is_mobile=True).new_page(); m.on('pageerror', lambda e: logs.append('PAGEERROR ' + str(e)))
    m.goto(B + '/homepage.php?modal=login'); m.fill('#loginUser', 'carol'); m.fill('#loginPass', 'secret123'); m.click('#loginForm button[type=submit]'); m.wait_for_url('**/index.php'); m.wait_for_selector('.movie-card')
    ok('mobile: no horizontal scroll', m.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))
    m.screenshot(path='/tmp/shots/mobile.png', full_page=False)
    for path in ['/stats.php', '/collections.php', '/wrapped.php']:
        m.goto(B + path); ok(f'mobile {path}: no horizontal scroll', m.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'))
    csp = [l for l in logs if 'Content Security Policy' in l or 'Refused' in l]
    ok('no CSP violations', not csp); print(csp[:3])
    other = [l for l in logs if l not in csp and 'fonts.g' not in l and 'ERR_' not in l and 'Failed to load resource' not in l]
    ok('no JS errors', not other); print(other[:5])
    br.close()
print('FAILED:' if errs else 'ALL OK', errs); sys.exit(1 if errs else 0)
