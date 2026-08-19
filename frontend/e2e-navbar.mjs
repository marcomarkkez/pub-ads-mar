/**
 * Regression guard for the top-bar user menu (WALK-6: log in, open the menu, sign out).
 *
 * The project has no wired-up frontend test runner (`ng test` is bare CLI scaffolding),
 * so this is a standalone Playwright script rather than a new test infrastructure.
 *
 * HOW TO RUN
 *   # 1. backend + db
 *   service postgresql start
 *   (cd backend && php artisan migrate:fresh --seed && php artisan serve --port=8000 &)
 *   # 2. frontend  (must be reached on http://localhost:4200 — the API's CORS allow-list
 *   #    names that exact origin, 127.0.0.1 is rejected)
 *   (cd frontend && npx ng serve --port=4200 &)
 *   # 3. this script
 *   node frontend/e2e-navbar.mjs
 *
 * Playwright and the chromium binary are resolved at runtime by ./e2e-env.mjs — nothing
 * about this host is hardcoded here. A missing tool exits 2 with the line to run; exit 1
 * still means the app failed a check.
 * Override the defaults with BASE=… and PW=… if your paths differ.
 *
 * WHAT IT GUARDS (each check maps to a bug that was live and is now fixed)
 *   1  the avatar button opens the dropdown at all
 *   2  nothing paints on top of the open menu — a toast column at z-index 9999 used to
 *      bury it completely, so the button looked dead ("no despliega nada")
 *   3  "Sign Out" really ends the session and leaves no user state behind
 *   4  navigating with the menu open does not strand the full-screen .backdrop on the
 *      next screen, where it ate every click
 *   5  the bar still sticks to the top once the page scrolls — `position: sticky` on
 *      .navbar never stuck, because its containing block is the 56px host element
 */
// Playwright is deliberately not a dependency of this app: it is a walk tool. Where it
// lives differs per host, so it is looked up rather than assumed.
import { launchOptions, loadChromium, orExit } from './e2e-env.mjs';

const { chromium } = await orExit(() => loadChromium());
const LAUNCH = await orExit(() => launchOptions());

const BASE = process.env.BASE || 'http://localhost:4200';
const USER = process.env.E2E_USER || 'client1@pubads.test';
const PASS = process.env.E2E_PASS || 'password';

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? `\n        ${detail}` : ''}`);
};

const browser = await chromium.launch(LAUNCH);
const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
page.on('pageerror', e => { failures++; console.log(`FAIL  uncaught page error: ${e.message}`); });

/** A real mouse press, so hit-testing (z-index, overlays) is exercised for real. */
async function realClick(selector) {
  const box = await page.locator(selector).boundingBox();
  if (!box) throw new Error(`no box for ${selector}`);
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.mouse.down();
  await page.waitForTimeout(60);
  await page.mouse.up();
  await page.waitForTimeout(300);
}

await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[type=email]', USER);
await page.fill('input[type=password]', PASS);
await page.click('button[type=submit]');
await page.waitForSelector('.avatar-btn', { timeout: 20000 });
await page.waitForTimeout(2000);

// --- 1. the button opens the menu -------------------------------------------------
await realClick('.avatar-btn');
check(await page.evaluate(() => !!document.querySelector('.dropdown')),
  'avatar button opens the dropdown');

// --- 2. nothing is painted over the open menu -------------------------------------
// Stack up toasts through the app's own service first: this is the exact state that
// used to hide the menu (login already shows one, WALK-6 adds more).
await page.evaluate(() => {
  const c = window.ng?.getComponent(document.querySelector('app-notification-toast'));
  if (!c) throw new Error('needs a development build (ng serve) for window.ng');
  c.notify.success('regression probe 1');
  c.notify.info('regression probe 2');
});
await page.waitForTimeout(400);
const occlusion = await page.evaluate(() => {
  const d = document.querySelector('.dropdown');
  const r = d.getBoundingClientRect();
  const hits = [];
  for (let f = 0.05; f < 1; f += 0.1) {
    const el = document.elementFromPoint(r.x + r.width / 2, r.y + r.height * f);
    if (el && !d.contains(el) && el !== d) hits.push(`y=${Math.round(r.y + r.height * f)} -> ${el.tagName}.${el.className}`);
  }
  const signOut = [...document.querySelectorAll('.dropdown-item')].pop().getBoundingClientRect();
  const overSignOut = document.elementFromPoint(signOut.x + signOut.width / 2, signOut.y + signOut.height / 2);
  return { hits, overSignOut: `${overSignOut.tagName}.${overSignOut.className}` };
});
check(occlusion.hits.length === 0, 'nothing paints over the open user menu',
  occlusion.hits.join('\n        '));
check(occlusion.overSignOut.includes('dropdown-item'),
  'the "Sign Out" button is the topmost element at its own centre',
  `topmost was ${occlusion.overSignOut}`);

// --- 3. sign out really ends the session ------------------------------------------
await realClick('.dropdown-item');
await page.waitForTimeout(2500);
const after = await page.evaluate(() => ({
  url: location.pathname,
  token: localStorage.getItem('auth_token'),
  navbar: !!document.querySelector('app-navbar'),
}));
check(after.url === '/login', 'sign out lands on /login', `url was ${after.url}`);
check(after.token === null, 'sign out clears the auth token', `token was ${after.token}`);
check(after.navbar === false, 'sign out tears the app chrome down');

// --- 4. navigating with the menu open leaves no stranded backdrop -----------------
await page.fill('input[type=email]', USER);
await page.fill('input[type=password]', PASS);
await page.click('button[type=submit]');
await page.waitForSelector('.avatar-btn', { timeout: 20000 });
await page.waitForTimeout(2000);
await realClick('.avatar-btn');
await realClick('.logo');                       // navbar link, sits above the backdrop
await page.waitForTimeout(1200);
const stranded = await page.evaluate(() => ({
  backdrop: !!document.querySelector('.backdrop'),
  dropdown: !!document.querySelector('.dropdown'),
  midPage: (document.elementFromPoint(640, 400) || {}).className,
}));
check(!stranded.backdrop && !stranded.dropdown,
  'navigating closes the menu and removes the backdrop',
  `backdrop=${stranded.backdrop} dropdown=${stranded.dropdown}; a click mid-page hits "${stranded.midPage}"`);

// --- 5. the bar sticks while the page scrolls -------------------------------------
await page.goto(`${BASE}/client/spaces`, { waitUntil: 'domcontentloaded' });
await page.waitForSelector('.avatar-btn', { timeout: 20000 });
await page.waitForTimeout(2000);
const sticky = await page.evaluate(() => {
  document.body.style.minHeight = '3000px';     // guarantee a scrollable page
  window.scrollTo(0, 600);
  const nav = document.querySelector('.navbar').getBoundingClientRect();
  const av = document.querySelector('.avatar-btn').getBoundingClientRect();
  const top = document.elementFromPoint(av.x + av.width / 2, av.y + av.height / 2);
  return {
    scrollY: window.scrollY,
    navTop: Math.round(nav.top),
    avatarClickable: !!top && !!top.closest('.avatar-btn'),
  };
});
check(sticky.scrollY > 0 && Math.abs(sticky.navTop) < 1 && sticky.avatarClickable,
  'the bar stays pinned to the top when the page scrolls',
  `scrollY=${sticky.scrollY} navbar top=${sticky.navTop} avatar clickable=${sticky.avatarClickable}`);

// and it still opens once scrolled
await realClick('.avatar-btn');
check(await page.evaluate(() => !!document.querySelector('.dropdown')),
  'the menu still opens on a scrolled page');

await browser.close();
console.log(failures === 0 ? '\nALL CHECKS PASSED' : `\n${failures} CHECK(S) FAILED`);
process.exit(failures === 0 ? 0 : 1);
