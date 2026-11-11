/**
 * Regression guard: a space's photo is SEEN by the client in Explore Spaces (WALK-2, 2026-11-11).
 *
 * Marco saw only the 📷 placeholder on every card and asked to make sure that "cuando haya
 * imágenes funcione". The seed has no photos, so the placeholder was right — but the path
 * a real photo takes had never been walked, and in the docker stack it was broken: the
 * backend stores uploads in the `backend_storage` named volume while nginx (port 8000, the
 * host that serves /storage/...) mounted only ./backend, so every photo answered 404.
 *
 * THE FIXTURE IS ITS OWN: as provider1 it uploads a generated PNG to one of its spaces,
 * checks as client1 that the card shows an <img> that really LOADED (naturalWidth > 0, not
 * just a src), and deletes the photo in a `finally`.
 *
 * HOW TO RUN: same stack as the other e2e-*.mjs (API on :8000, SPA on :4200), then
 *   node frontend/e2e-space-photo.mjs
 * Exit 0 = PASS · 1 = the app failed · 2 = this host cannot run it.
 */
import { launchChromium, loadChromium, orExit } from './e2e-env.mjs';

const BASE = process.env.BASE || 'http://localhost:4200';
const API = process.env.API || 'http://localhost:8000/api';

let failures = 0;
const check = (ok, label, detail = '') => {
  if (!ok) failures++;
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${label}${detail ? `\n        ${detail}` : ''}`);
};
const { chromium } = await orExit(() => loadChromium());

async function call(method, path, token, body, isForm = false) {
  try {
    const res = await fetch(API + path, {
      method,
      headers: { Accept: 'application/json', ...(isForm ? {} : { 'Content-Type': 'application/json' }), ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: isForm ? body : body ? JSON.stringify(body) : undefined,
    });
    const text = await res.text();
    try { return { status: res.status, json: JSON.parse(text) }; } catch { return { status: res.status, json: null }; }
  } catch {
    console.error(`\nNO SE PUDO EJECUTAR (esto no es un fallo de la app)\n\nLa API no contesta en ${API}.\n`);
    process.exit(2);
  }
}
const list = r => (Array.isArray(r.json) ? r.json : r.json?.data) || [];
const token = async email => (await call('POST', '/login', null, { email, password: 'password' })).json?.token;

const TP = await token('provider1@pubads.test');
const TC = await token('client1@pubads.test');
if (!TP || !TC) { console.error('\nNO SE PUDO EJECUTAR: la base no esta sembrada (python3 mvp-init.py)\n'); process.exit(2); }

// a real 64x64 PNG (solid colour), so `image` validation and the browser both accept it
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAAQ0lEQVR42u3PQQ0AAAgEoNe/tFZwc4MIpLdpnwABAgQIECBAgAABAgQIECBAgAABAgQIECBAgAABAgQIECBAgAABAgQIEHjAAj2rTt8oZlu9AAAAAElFTkSuQmCC', 'base64');

let browser, spaceId = null, photoId = null;
try {
  const mine = new Set(list(await call('GET', '/provider/spaces', TP)).map(s => s.id));
  const visible = list(await call('GET', '/client/spaces/search', TC));
  const target = visible.find(s => mine.has(s.id));
  if (!target) throw new Error('fixture: no space of provider1 shows up in the client search');
  spaceId = target.id;

  const form = new FormData();
  form.append('photo', new Blob([PNG], { type: 'image/png' }), 'e2e.png');
  form.append('is_primary', '1');
  const up = await call('POST', `/provider/spaces/${spaceId}/photos`, TP, form, true);
  photoId = up.json?.id ?? up.json?.data?.id;
  check(up.status === 201 && !!photoId, 'the provider can upload a photo to the space', `HTTP ${up.status}`);

  const url = up.json?.file_url ?? up.json?.data?.file_url;
  const raw = url ? await fetch(url).catch(() => null) : null;
  check(!!raw && raw.ok && /image\//.test(raw.headers.get('content-type') || ''),
    'the photo URL serves an image (in docker: nginx sees the storage volume)', `${url} -> ${raw ? raw.status + ' ' + raw.headers.get('content-type') : 'no answer'}`);

  browser = await launchChromium(chromium);
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[type=email]', 'client1@pubads.test');
  await page.fill('input[type=password]', 'password');
  await page.click('button[type=submit]');
  await page.waitForSelector('.avatar-btn', { timeout: 30000 });
  await page.goto(`${BASE}/client/spaces`, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector(`img[alt="${target.name.replace(/"/g, '\\"')}"]`, { timeout: 30000 }).catch(() => null);
  await page.waitForTimeout(1500);
  const img = await page.evaluate(name => {
    const el = [...document.querySelectorAll('img')].find(i => i.alt === name);
    return el ? { src: el.src, w: el.naturalWidth } : null;
  }, target.name);
  check(!!img && img.w > 0, `the card of «${target.name}» shows the photo, loaded (not the 📷 placeholder)`, JSON.stringify(img));
} catch (e) {
  failures++;
  console.log(`FAIL  run aborted: ${e.message}`);
} finally {
  if (browser) await browser.close();
  if (spaceId && photoId) await call('DELETE', `/provider/spaces/${spaceId}/photos/${photoId}`, TP);
}
console.log(failures ? `\n${failures} check(s) FAILED` : '\nall checks PASS');
process.exit(failures ? 1 : 0);
