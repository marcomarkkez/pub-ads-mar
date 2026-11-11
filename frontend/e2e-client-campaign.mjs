/**
 * Regression guard for the client's campaign screen (WALK-2, steps W2-1 and W2-2).
 *
 * WHAT IT GUARDS — three faults found on 2026-11-11 before the walk was walked:
 *   (a) moving ONE ad to another adset existed in the API (POST adsets/move) and had no
 *       control on screen, so W2-1 had no door;
 *   (b) deleting an adset asked "Delete adset and all its ads?" — the API has never
 *       deleted them, they go back to the campaign backlog ("Unassigned spaces");
 *   (c) after that delete the backlog was not reloaded, so the ads vanished from the
 *       screen until a refresh (BR-8).
 *
 * The API side is held by OwnershipChainTest; CampaignScreenMatchesApiTest reads the
 * component's source. This script is the only one that clicks: it proves the select is
 * there, that choosing an adset moves the ad, what the confirm dialog SAYS, and that the
 * backlog card comes back without a reload.
 *
 * THE FIXTURE IS ITS OWN. Through the API, as client1, it creates a campaign with two
 * adsets (A, B) and one space in the backlog moved into A. It deletes that campaign in a
 * `finally`, pass or fail. It touches no seeded row.
 *
 * HOW TO RUN (same stack as the other e2e-*.mjs)
 *   service postgresql start && python3 mvp-init.py
 *   (cd backend && php artisan serve --port=8000 &)        # or: docker compose up -d
 *   (cd frontend && npx ng serve --port=4200 &)
 *   node frontend/e2e-client-campaign.mjs
 *
 * Exit 0 = all PASS · 1 = the app failed a check · 2 = this host cannot run it (the line
 * to fix it is printed). Override with BASE=… API=… E2E_USER=… E2E_PASS=…
 */
import { launchChromium, loadChromium, orExit } from './e2e-env.mjs';

const BASE = process.env.BASE || 'http://localhost:4200';
const API = process.env.API || 'http://localhost:8000/api';
const USER = process.env.E2E_USER || 'client1@pubads.test';
const PASS = process.env.E2E_PASS || 'password';

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? `\n        ${detail}` : ''}`);
};

const { chromium } = await orExit(() => loadChromium());

async function api(method, path, body, token) {
  let res;
  try {
    res = await fetch(API + path, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch {
    console.error(`\nNO SE PUDO EJECUTAR (esto no es un fallo de la app)\n\nLa API no contesta en ${API}. Levanta el backend (docker compose up -d, o php artisan serve --port=8000).\n`);
    process.exit(2);
  }
  const text = await res.text();
  let json = null;
  try { json = JSON.parse(text); } catch { /* keep null */ }
  return { status: res.status, json };
}
const idOf = r => r.json?.id ?? r.json?.data?.id;
const listOf = r => (Array.isArray(r.json) ? r.json : r.json?.data) || [];

// ── fixture ──────────────────────────────────────────────────────────────────────────
const login = await api('POST', '/login', { email: USER, password: PASS });
if (!login.json?.token) {
  console.error(`\nNO SE PUDO EJECUTAR (esto no es un fallo de la app)\n\n${USER} no puede entrar (HTTP ${login.status}). ¿Base sembrada? python3 mvp-init.py\n`);
  process.exit(2);
}
const T = login.json.token;
const stamp = Date.now();
let campaignId = null;

const errors = [];
let browser;

try {
  const space = listOf(await api('GET', '/client/spaces/search', null, T))[0];
  if (!space) throw new Error('fixture: the space search returned nothing to put in the backlog');

  campaignId = idOf(await api('POST', '/client/campaigns', { name: `e2e campaign ${stamp}`, budget: 1000, status: 'draft' }, T));
  const adsetA = idOf(await api('POST', `/client/campaigns/${campaignId}/adsets`, { name: `A-${stamp}` }, T));
  const adsetB = idOf(await api('POST', `/client/campaigns/${campaignId}/adsets`, { name: `B-${stamp}` }, T));
  await api('POST', `/client/campaigns/${campaignId}/backlog`, { space_ids: [space.id] }, T);
  const orphan = listOf(await api('GET', `/client/campaigns/${campaignId}/orphans`, null, T))[0];
  const moved = await api('POST', `/client/campaigns/${campaignId}/adsets/move`, { ad_ids: [orphan?.id], adset_id: adsetA }, T);
  if (!campaignId || !adsetA || !adsetB || moved.status !== 201) {
    throw new Error(`fixture: could not build campaign/adsets/ad (move answered ${moved.status})`);
  }

  // ── browser ────────────────────────────────────────────────────────────────────────
  browser = await launchChromium(chromium);
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource: net::/.test(m.text())) errors.push(m.text().split('\n')[0]); });
  page.on('pageerror', e => errors.push(`uncaught: ${e.message}`));

  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[type=email]', USER);
  await page.fill('input[type=password]', PASS);
  await page.click('button[type=submit]');
  await page.waitForSelector('.avatar-btn', { timeout: 30000 });

  await page.goto(`${BASE}/client/campaigns/${campaignId}`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector(`text=A-${stamp}`, { timeout: 30000 });
  await page.waitForTimeout(800);

  // An adset's block is the bordered box that holds its name; its ads live inside it.
  const block = name => page.locator('strong', { hasText: name }).locator('xpath=ancestor::div[3]');

  // (a) the move control exists on the ad, and moving works without a reload
  const select = block(`A-${stamp}`).locator('select.ad-move');
  check(await select.count() === 1, 'W2-1  the ad in adset A has a «Move to…» control');
  if (await select.count() === 1) {
    await select.selectOption({ label: `B-${stamp}` });
    await page.waitForTimeout(1500);
    check(await block(`B-${stamp}`).locator('select.ad-move').count() === 1 &&
          await block(`A-${stamp}`).locator('select.ad-move').count() === 0,
      'W2-1  after choosing B the ad sits in B and no longer in A (no reload)');
  }

  // (b) the confirm tells the truth, (c) the backlog comes back without a reload
  let dialog = '';
  page.once('dialog', d => { dialog = d.message(); d.accept(); });
  await block(`B-${stamp}`).getByRole('button', { name: 'Delete' }).first().click();
  await page.waitForTimeout(1500);
  check(/NOT deleted/.test(dialog) && /Unassigned spaces/.test(dialog) && !/and all its ads/.test(dialog),
    'W2-2  deleting an adset says its ad is NOT deleted and goes back to «Unassigned spaces»',
    `confirm said: ${JSON.stringify(dialog)}`);
  check(await page.locator('h2', { hasText: 'Unassigned spaces (1)' }).count() === 1,
    'W2-2  «Unassigned spaces (1)» is back on screen without a reload');

  check(errors.length === 0, 'no console errors on the campaign screen', errors.join('\n        '));
} catch (e) {
  failures++;
  console.log(`FAIL  run aborted: ${e.message}`);
} finally {
  if (browser) await browser.close();
  if (campaignId) {
    const del = await api('DELETE', `/client/campaigns/${campaignId}`, null, T);
    if (del.status >= 300) console.log(`WARN  fixture campaign ${campaignId} could not be deleted (HTTP ${del.status})`);
  }
}

console.log(failures ? `\n${failures} check(s) FAILED` : '\nall checks PASS');
process.exit(failures ? 1 : 0);
