/**
 * Regression guard for the landing's signup form (WALK-8, steps W8-3..W8-5; EH-22).
 *
 * WHAT IT GUARDS — the fault Marco hit on 2026-11-10: every signup answered
 * `Unexpected token '<', "<!DOCTYPE "... is not valid JSON`. The landing posted to a
 * RELATIVE `/api/register`, i.e. to its own dev server on 4300, which has no API and
 * answers HTML; and the form painted the browser parser's message raw.
 *
 * It drives the real form on 4300 and checks:
 *   · an empty submit sends nothing to the API (shape validation stays in the browser);
 *   · a real signup as «Tengo espacios» reaches the API on 8000, lands on /gracias, and
 *     the account logs in with role `provider`;
 *   · the same email again says «Ese email ya tiene cuenta» and no parser text anywhere.
 *
 * THE FIXTURE IS ITS OWN: a fresh `e2e-landing-<time>@pubads.test`, deleted again in a
 * `finally` through artisan (native or `docker compose exec`, resolved by e2e-env.mjs).
 *
 * HOW TO RUN
 *   service postgresql start && python3 mvp-init.py
 *   (cd backend && php artisan serve --port=8000 &)        # or: docker compose up -d
 *   (cd landing && npx ng serve --port=4300 &)
 *   node frontend/e2e-landing-signup.mjs
 *
 * Exit 0 = all PASS · 1 = the app failed a check · 2 = this host cannot run it.
 * Override with LANDING=… API=…
 */
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CannotRun, bail, launchChromium, loadChromium, makeArtisan, orExit } from './e2e-env.mjs';

const LANDING = process.env.LANDING || 'http://localhost:4300';
const API = process.env.API || 'http://localhost:8000/api';
const BACKEND = process.env.BACKEND_DIR ||
  path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'backend');

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? `\n        ${detail}` : ''}`);
};

const { chromium } = await orExit(() => loadChromium());
const artisan = await orExit(() => makeArtisan(BACKEND));

const email = `e2e-landing-${Date.now()}@pubads.test`;
const PARSER = /Unexpected token|DOCTYPE|JSON/;
let browser;
let hostProblem = null;

try {
  browser = await launchChromium(chromium);
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const registerCalls = [];
  page.on('request', r => { if (r.url().includes('/register')) registerCalls.push(r.url()); });

  try {
    await page.goto(LANDING, { waitUntil: 'domcontentloaded' });
  } catch {
    throw new CannotRun(`La landing no contesta en ${LANDING}. Levántala: (cd landing && npx ng serve --port=4300 &) o docker compose up -d landing`);
  }
  const form = page.locator('form').first();
  await form.waitFor({ timeout: 30000 });

  // 1) empty submit: nothing leaves the browser
  await form.locator('button[type=submit]').click();
  await page.waitForTimeout(800);
  check(registerCalls.length === 0, 'W8-3  an empty form sends nothing to the API', `requests: ${registerCalls.join(', ')}`);

  // 2) real signup
  const fill = async () => {
    await form.getByText('Tengo espacios').click();
    await form.locator('#name').fill('E2E Landing');
    await form.locator('#email').fill(email);
    await form.locator('#password').fill('password');
    await form.locator('button[type=submit]').click();
    await page.waitForTimeout(2500);
  };
  await fill();
  check(registerCalls.some(u => u.startsWith(API.replace(/\/api$/, ''))),
    'W8-4  the signup goes to the API on :8000, not to the landing itself', `requests: ${registerCalls.join(', ')}`);
  check(new URL(page.url()).pathname === '/gracias', 'W8-4  a real signup lands on /gracias', `url: ${page.url()}`);

  const login = await fetch(`${API}/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password: 'password' }),
  }).then(r => r.json()).catch(() => ({}));
  check(login?.user?.role === 'provider', 'W8-4  «Tengo espacios» arrives as role provider', `role: ${login?.user?.role}`);

  // 3) same email again
  await page.goto(LANDING, { waitUntil: 'domcontentloaded' });
  await form.waitFor({ timeout: 30000 });
  await fill();
  const err = (await page.locator('.form-err').first().textContent().catch(() => '')) || '';
  check(/ya tiene cuenta/.test(err), 'W8-5  the same email says «Ese email ya tiene cuenta»', `said: ${JSON.stringify(err)}`);
  check(!PARSER.test(await page.locator('body').innerText()), 'W8-5  no parser text (Unexpected token / DOCTYPE / JSON) on screen');
} catch (e) {
  if (e instanceof CannotRun) hostProblem = e;
  else { failures++; console.log(`FAIL  run aborted: ${e.message}`); }
} finally {
  if (browser) await browser.close();
  try {
    artisan(`App\\Models\\User::where('email', '${email}')->get()->each(function ($u) { $a = $u->account; $u->tokens()->delete(); $u->delete(); if ($a) { $a->delete(); } });`);
  } catch (e) {
    console.log(`WARN  could not remove the fixture user ${email}: ${String(e.message).split('\n')[0]}`);
  }
}

if (hostProblem) bail(hostProblem);
console.log(failures ? `\n${failures} check(s) FAILED` : '\nall checks PASS');
process.exit(failures ? 1 : 0);
