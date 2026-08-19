/**
 * Regression guard for the provider's "My Spaces" screen (WALK-6, provider side).
 *
 * WHAT IT GUARDS — one bug, three faces, all reported from a live Codespace:
 *   (a) "los botones de acciones no funcionan": View/Edit rendered as
 *       `<a class="btn btn-sm">View</a>` with NO href.
 *   (b) "no puedo hacer logout": clicking the avatar did nothing at all.
 *   (c) "no veo forma de borrar el espacio": the Schedule/Cancel deletion button was
 *       simply absent from the DOM.
 *
 * Root cause (one): `formatType(space.type)` did `type.replace(...)` and `spaces.type`
 * is NULLABLE. The TypeError was thrown from a template expression, i.e. DURING the
 * update pass, so every binding after it in that view was skipped (no href, no @if
 * branch), and because the throw escapes ApplicationRef.tick() the navbar — refreshed
 * AFTER the router-outlet content in the same tick — stopped repainting too, which is (b).
 *
 * THE FIXTURE IS THE TEST. Without a type-less row on screen this script proves nothing.
 * It used to borrow one from the seed data (DisputeDemoSeeder minted "Demo Dispute
 * Billboard" with type = null) — but that row was ALSO visible garbage in the client
 * search ("Unspecified / Not set"), so the seeder was fixed to give it a real type. A
 * guard that depends on demo data it does not own is a guard that turns green the day
 * someone tidies the demo data. So the script now MINTS ITS OWN fixture: it nulls
 * `type`/`price_per_day` on one of the provider's spaces via `php artisan tinker` before
 * logging in, and restores the exact previous values in a `finally`, pass or fail. If the
 * fixture cannot be created the run FAILS — it never passes for lack of data.
 *
 * The project has no wired-up frontend test runner, so this is a standalone Playwright
 * script, a sibling of e2e-navbar.mjs.
 *
 * HOW TO RUN
 *   # 1. backend + db
 *   service postgresql start
 *   python3 mvp-init.py --fresh
 *   (cd backend && php artisan serve --port=8000 &)
 *   # 2. frontend — MUST be reached on http://localhost:4200; the API CORS allow-list
 *   #    names that exact origin and rejects 127.0.0.1
 *   (cd frontend && npx ng serve --port=4200 &)
 *   # 3. this script — it shells out to `php artisan` in ../backend, so run it on the
 *   #    host that owns the backend (there is no docker daemon here)
 *   node frontend/e2e-provider-spaces.mjs
 *
 * Playwright, the chromium binary and the way to reach `php artisan` are all resolved at
 * runtime by ./e2e-env.mjs — nothing about this host is hardcoded here. If a tool is
 * missing the script exits 2 with the line to run; exit 1 still means the app failed.
 * Override with BASE=… PW=… E2E_USER=… E2E_PASS=… BACKEND_DIR=… if your paths differ.
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { launchOptions, loadChromium, makeArtisan, orExit } from './e2e-env.mjs';

const BASE = process.env.BASE || 'http://localhost:4200';
const USER = process.env.E2E_USER || 'provider1@pubads.test';
const PASS = process.env.E2E_PASS || 'password';
const BACKEND = process.env.BACKEND_DIR ||
  path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'backend');

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? `\n        ${detail}` : ''}`);
};

// Everything host-shaped is settled HERE, before the database is touched or a browser is
// started: a missing tool must never surface as a half-run guard.
const { chromium } = await orExit(() => loadChromium());
const LAUNCH = await orExit(() => launchOptions());

// ── the fixture: one type-less listing, minted and reverted by this script ──────────
// `tinker --execute` is the way in, over whichever artisan this host can reach — native
// where the backend runs on the host, `docker compose exec` where it runs in a container.
const artisan = await orExit(() => makeArtisan(BACKEND));

/** Values crossing the JS→PHP boundary go as base64: no quoting/escaping to get wrong. */
const phpStr = (value) => `base64_decode('${Buffer.from(value).toString('base64')}')`;

/** Nulls type+price on the provider's first TYPED space (never on one already null, or a
 *  crashed earlier run would teach us to "restore" a null). Returns the saved values. */
function mintUntypedFixture() {
  const out = artisan(`
    $u = App\\Models\\User::where('email', ${phpStr(USER)})->first();
    $s = $u ? App\\Models\\Space::where('user_id', $u->id)->whereNotNull('type')
              ->orderBy('id')->first() : null;
    if ($s) {
      echo 'E2E_FIXTURE ' . json_encode([
        'id' => $s->id, 'name' => $s->name,
        'type' => $s->type, 'price_per_day' => $s->price_per_day,
      ]) . PHP_EOL;
      $s->forceFill(['type' => null, 'price_per_day' => null])->save();
    }
  `);
  const found = /E2E_FIXTURE (\{.*\})/.exec(out);
  if (!found) {
    throw new Error(`could not mint the type-less fixture in ${BACKEND} — no typed space ` +
      `for ${USER}? Seed first (python3 mvp-init.py --fresh).\nartisan said: ${out.trim()}`);
  }
  const fixture = JSON.parse(found[1]);
  console.log(`INFO  fixture: space ${fixture.id} "${fixture.name}" temporarily has ` +
              `type=null (was ${fixture.type}, ${fixture.price_per_day}/day)`);
  return fixture;
}

/** Put the row back exactly as it was. Must run even when the checks blow up. */
function restoreFixture(fixture) {
  const out = artisan(`
    $d = json_decode(${phpStr(JSON.stringify(fixture))}, true);
    $s = App\\Models\\Space::find($d['id']);
    if ($s) {
      $s->forceFill(['type' => $d['type'], 'price_per_day' => $d['price_per_day']])->save();
      echo 'E2E_RESTORED ' . $s->id . ' ' . $s->type . ' ' . $s->price_per_day . PHP_EOL;
    }
  `);
  if (!/E2E_RESTORED/.test(out)) {
    throw new Error(`fixture NOT restored — space ${fixture.id} is left with a null type. ` +
      `artisan said: ${out.trim()}`);
  }
  console.log(`INFO  fixture restored: ${out.match(/E2E_RESTORED.*/)[0]}`);
}

// Every error is collected, not just uncaught ones: Angular routes template exceptions
// through its ErrorHandler, which console.errors them and does NOT reach 'pageerror'.
// That is exactly how this bug showed up — silently, in the console only.
let phase = 'login';
const errors = [];                      // { phase, text }
const record = t => errors.push({ phase, text: t.split('\n')[0] });

let browser;
let page;

/**
 * Two known-noisy lines that are NOT this screen's business. Named explicitly rather
 * than filtered by a vague pattern, so each one has to be argued for:
 *
 *  · map tiles. The Edit screen's LocationPicker pulls OpenStreetMap tiles; a sandbox
 *    with no outbound internet answers ERR_TUNNEL_CONNECTION_FAILED. Environment.
 *  · NG0100 in LocationPicker. Reproduced on EVERY space edit screen (ids 1, 2 and 7
 *    alike, typed or not) before and after this fix — a pre-existing dev-mode warning
 *    that belongs to the space-form / location-picker area, not here.
 */
const isKnownForeignNoise = e =>
  /Failed to load resource: net::/.test(e.text) ||
  /NG0100:.*_LocationPickerComponent/.test(e.text);

/** A real mouse press, so hit-testing (z-index, overlays) is exercised for real. */
async function realClick(selector) {
  const box = await page.locator(selector).first().boundingBox();
  if (!box) throw new Error(`no box for ${selector}`);
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.mouse.down();
  await page.waitForTimeout(60);
  await page.mouse.up();
  await page.waitForTimeout(400);
}

async function login() {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[type=email]', USER);
  await page.fill('input[type=password]', PASS);
  await page.click('button[type=submit]');
  await page.waitForSelector('.avatar-btn', { timeout: 30000 });
  await page.waitForTimeout(2000);
}

/** Reach the list the way a user does: an in-app router navigation, NOT a full reload.
 *  A reload happens to hide the bug's worst face, because a fresh bootstrap re-runs the
 *  create pass; the SPA hop is the honest path. */
async function gotoSpacesViaSidebar() {
  phase = 'spaces-list';
  await page.locator('a[href="/provider/spaces"]:visible').first().click();
  await page.waitForSelector('table tbody tr, .empty-state, .alert-error', { timeout: 30000 });
  await page.waitForTimeout(1500);
}

// The fixture is minted BEFORE the browser starts, and everything below runs inside a
// try/finally so the row goes back to its real values on every exit path — green, red,
// or a mid-run exception. A test may borrow the database; it may not keep it.
const fixture = mintUntypedFixture();

try {
  browser = await chromium.launch(LAUNCH);
  page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  page.on('console', m => { if (m.type() === 'error') record(m.text()); });
  page.on('pageerror', e => record(`uncaught: ${e.message}`));

  await login();
  await gotoSpacesViaSidebar();

  const rows = await page.locator('tbody tr').count();
  check(rows > 0, 'the provider has listings to render (the bug needs rows)', `rows=${rows}`);

  // --- 0. the fixture really reached the screen ------------------------------------
  // Not a warning: if the type-less row is not rendered, every check below is testing a
  // screen the bug could never have touched, and a green run would be a lie.
  const untyped = await page.evaluate(() =>
    [...document.querySelectorAll('tbody tr')].filter(tr => /Unspecified/.test(tr.textContent)).length);
  check(untyped > 0, 'the type-less fixture is on screen (this run can catch the bug)',
    `space ${fixture.id} "${fixture.name}" set to type=null -> ${untyped} "Unspecified" ` +
    'row(s) rendered; zero means stale data or the space is not listed');

  // --- 1. View and Edit resolve to real hrefs ---------------------------------------
  const links = await page.evaluate(() => [...document.querySelectorAll('td.actions')].map(td => ({
    html: td.outerHTML,
    hrefs: [...td.querySelectorAll('a')].map(a => a.getAttribute('href')),
    buttons: [...td.querySelectorAll('button')].map(b => b.textContent.trim()),
  })));
  const allHrefs = links.every(l => l.hrefs.length === 2 && l.hrefs.every(h => /^\/provider\/spaces\/\d+/.test(h)));
  check(allHrefs, 'every row\'s View and Edit anchors carry a resolved href',
    links.map((l, i) => `row ${i}: ${JSON.stringify(l.hrefs)}`).join('\n        '));

  // --- 2. the deletion control is rendered ------------------------------------------
  const allButtons = links.every(l =>
    l.buttons.length === 1 && /^(Schedule deletion|Cancel deletion)$/.test(l.buttons[0]));
  check(allButtons, 'every row renders its Schedule/Cancel deletion button',
    links.map((l, i) => `row ${i}: ${JSON.stringify(l.buttons)}`).join('\n        '));

  // --- 3. View navigates to a detail screen that actually loads ----------------------
  const viewHref = links[0].hrefs[0];
  phase = 'space-detail';
  await realClick('td.actions a');
  await page.waitForTimeout(2500);
  const detail = await page.evaluate(() => ({
    url: location.pathname,
    hasDetail: !!document.querySelector('.detail-card, .detail-grid'),
    err: (document.querySelector('.alert-error') || {}).textContent,
  }));
  check(detail.url === viewHref && detail.hasDetail, 'View opens the space detail screen',
    `url=${detail.url} (expected ${viewHref}) detailCard=${detail.hasDetail} error=${detail.err || 'none'}`);

  // --- 4. Edit navigates to a form that actually loads -------------------------------
  await gotoSpacesViaSidebar();
  const editHref = (await page.evaluate(() =>
    document.querySelectorAll('td.actions a')[1].getAttribute('href')));
  phase = 'space-form';
  await page.evaluate(() => document.querySelectorAll('td.actions a')[1].click());
  await page.waitForTimeout(2500);
  const form = await page.evaluate(() => ({
    url: location.pathname,
    inputs: document.querySelectorAll('form input, form select, form textarea').length,
    err: (document.querySelector('.alert-error') || {}).textContent,
  }));
  check(form.url === editHref && form.inputs > 0, 'Edit opens the space form with fields',
    `url=${form.url} (expected ${editHref}) fields=${form.inputs} error=${form.err || 'none'}`);

  // --- 5. the avatar menu still works ON THIS SCREEN, and Sign Out really signs out ---
  await gotoSpacesViaSidebar();
  await realClick('.avatar-btn');
  const menu = await page.evaluate(() => {
    const d = document.querySelector('.dropdown');
    if (!d) return { open: false };
    const r = d.getBoundingClientRect();
    const item = [...document.querySelectorAll('.dropdown-item')].pop().getBoundingClientRect();
    const top = document.elementFromPoint(item.x + item.width / 2, item.y + item.height / 2);
    return { open: true, topmostOverSignOut: `${top.tagName}.${top.className}`, h: Math.round(r.height) };
  });
  check(menu.open, 'the avatar opens the user menu on /provider/spaces',
    'menuOpen flips but the view never repaints when the page throws during the update pass');
  check(menu.open && menu.topmostOverSignOut.includes('dropdown-item'),
    'nothing paints over "Sign Out"', `topmost was ${menu.topmostOverSignOut}`);

  // Guarded: when the bug is live the menu never paints, and clicking a button that is
  // not in the DOM should be reported as a failed check, not crash the run.
  if (menu.open) {
    phase = 'sign-out';
    await realClick('.dropdown-item');
    await page.waitForTimeout(2500);
    const after = await page.evaluate(() => ({
      url: location.pathname,
      token: localStorage.getItem('auth_token'),
      navbar: !!document.querySelector('app-navbar'),
    }));
    check(after.url === '/login' && after.token === null && !after.navbar,
      'Sign Out really ends the session',
      `url=${after.url} token=${after.token} navbarStillUp=${after.navbar}`);
  } else {
    check(false, 'Sign Out really ends the session', 'skipped: the menu never opened');
  }

  // --- 6. zero console errors -------------------------------------------------------
  // The guarded screen gets the strict version: NOTHING at all, no exemptions. The bug
  // was invisible except as a console error, so this is the check that actually holds.
  const onList = errors.filter(e => e.phase === 'spaces-list');
  check(onList.length === 0, '/provider/spaces produces no console errors at all',
    onList.map(e => e.text).join('\n        '));

  const elsewhere = errors.filter(e => e.phase !== 'spaces-list' && !isKnownForeignNoise(e));
  check(elsewhere.length === 0, 'the rest of the run produces no console errors either',
    elsewhere.map(e => `[${e.phase}] ${e.text}`).join('\n        '));

  const noise = errors.filter(isKnownForeignNoise);
  if (noise.length) {
    const kinds = [...new Set(noise.map(e => e.text.slice(0, 90)))];
    console.log(`WARN  ${noise.length} known foreign console line(s) ignored (see ` +
                `isKnownForeignNoise):\n        ${kinds.join('\n        ')}`);
  }
} catch (e) {
  // An exception is a failed run, not an excuse to skip the summary (or the restore).
  check(false, `the run reached the end without throwing (phase: ${phase})`, e.message);
} finally {
  if (browser) await browser.close().catch(() => {});
  try {
    restoreFixture(fixture);
  } catch (e) {
    check(false, 'the fixture was restored to its seeded values', e.message);
  }
}

console.log(failures === 0 ? '\nALL CHECKS PASSED' : `\n${failures} CHECK(S) FAILED`);
process.exit(failures === 0 ? 0 : 1);
