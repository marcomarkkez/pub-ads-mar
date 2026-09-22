/**
 * WALK-2 · UC-5 — the client's Explore Spaces map, walked by a machine.
 *
 * This ACCOMPANIES the human walk, it never replaces it (BR-20). What it is here to catch
 * is the class of map bug a person cannot see by looking: how many requests one gesture
 * fires, and whether a state that should be dismissible actually is.
 *
 *   node e2e-client-map.mjs            # against localhost:4200 + localhost:8000
 *   BASE=http://127.0.0.1:4200 node e2e-client-map.mjs
 *
 * Note on the basemap: OpenStreetMap's tiles are BLOCKED in the agent sandbox, so check 5
 * runs for real there and is skipped elsewhere. That is deliberate — the sandbox's
 * limitation is the exact failure Marco hit in the wild (2026-09-22), so it is the one
 * environment where the "el mapa base no está disponible" notice can be proven to appear.
 */
import { chromium } from '/opt/node22/lib/node_modules/playwright/index.mjs';

const BASE = process.env.BASE || 'http://localhost:4200';
const EMAIL = process.env.EMAIL || 'client1@pubads.test';
const PASSWORD = process.env.PASSWORD || 'password';

const results = [];
const check = (name, ok, detail) => {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ' — ' + detail : ''}`);
};

/** Tile requests never resolve in the sandbox; they are the environment, not a defect. */
const isKnownForeignNoise = (text) =>
  /tile\.openstreetmap\.org/.test(text) || /ERR_TUNNEL_CONNECTION_FAILED/.test(text);

const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium',
  args: ['--no-sandbox'],
});
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();

const consoleErrors = [];
page.on('console', (m) => {
  if (m.type() === 'error' && !isKnownForeignNoise(m.text())) consoleErrors.push(m.text().slice(0, 300));
});
page.on('pageerror', (e) => {
  if (!isKnownForeignNoise(String(e))) consoleErrors.push('PAGEERROR ' + String(e).slice(0, 300));
});

const searches = [];
page.on('request', (r) => {
  if (r.url().includes('/client/spaces/search')) searches.push(r.url().replace(/^.*search\?/, ''));
});

try {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[type=email]', EMAIL);
  await page.fill('input[type=password]', PASSWORD);
  await page.click('button[type=submit]');
  await page.waitForURL(/client\/spaces/, { timeout: 20000 });
  await page.waitForSelector('.map-el.leaflet-container', { timeout: 20000 });
  await page.waitForTimeout(2500);

  const box = await page.locator('.map-el').boundingBox();

  /**
   * A point inside the map that is bare canvas — no marker, no popup, no zoom control.
   * Hard-coded corners are not safe: the controls live in two of them, markers move with
   * every search, and a "bare" click that actually lands on a marker turns a passing check
   * into a phantom bug report.
   */
  const bareMapPoint = () => page.evaluate(() => {
    const el = document.querySelector('.map-el');
    const r = el.getBoundingClientRect();
    for (let fx = 0.1; fx < 0.95; fx += 0.1) {
      for (let fy = 0.1; fy < 0.95; fy += 0.1) {
        const x = Math.round(r.x + r.width * fx);
        const y = Math.round(r.y + r.height * fy);
        const hit = document.elementFromPoint(x, y);
        if (!hit || !el.contains(hit)) continue;
        if (hit.closest('.leaflet-marker-icon, .leaflet-control, .leaflet-popup')) continue;
        return { x, y };
      }
    }
    return null;
  });

  const markerHandles = async () => {
    const out = { center: null, spaces: [] };
    for (const h of await page.$$('.leaflet-marker-icon')) {
      const html = await h.evaluate((n) => n.innerHTML);
      if (html.includes('#2563eb')) out.center = h;
      else if (html.includes('#e11d48') || html.includes('#f97316')) out.spaces.push(h);
    }
    return out;
  };

  // ── 1 · one click, one search ───────────────────────────────────────────────
  searches.length = 0;
  const firstPoint = await bareMapPoint();
  await page.mouse.click(firstPoint.x, firstPoint.y);
  await page.waitForTimeout(1800);
  check('un clic en el mapa dispara exactamente una búsqueda', searches.length === 1, `${searches.length} búsqueda(s)`);

  // ── 2 · a double click zooms and searches NOTHING ───────────────────────────
  // The regression this pins: Leaflet fires 'click' for each half of a double click, so
  // this used to move the search origin and fire the same query twice while the map zoomed.
  searches.length = 0;
  const dblPoint = await bareMapPoint();
  await page.mouse.dblclick(dblPoint.x, dblPoint.y);
  await page.waitForTimeout(2200);
  check('un doble clic no dispara ninguna búsqueda (solo hace zoom)', searches.length === 0, `${searches.length} búsqueda(s)`);

  // ── 3 · the detail popup is dismissible, three ways ─────────────────────────
  const { spaces } = await markerHandles();
  if (spaces.length >= 2) {
    await spaces[0].click();
    await page.waitForTimeout(600);
    const opened = await page.locator('.leaflet-popup').count();

    await spaces[1].click();
    await page.waitForTimeout(600);
    const afterOther = await page.locator('.leaflet-popup').count();

    const bare = await bareMapPoint();
    await page.mouse.click(bare.x, bare.y);
    await page.waitForTimeout(1800);
    const afterBare = await page.locator('.leaflet-popup').count();

    check(
      'el panel del anuncio se abre, se reemplaza y se cierra al clicar fuera',
      opened === 1 && afterOther === 1 && afterBare === 0,
      `abierto=${opened} otro=${afterOther} fuera=${afterBare}`,
    );
  } else {
    check('el panel del anuncio se abre, se reemplaza y se cierra al clicar fuera', false, `solo ${spaces.length} marcador(es)`);
  }

  // ── 4 · List ⇄ Map leaves the origin dot centred ────────────────────────────
  await page.click('button:has-text("List View")');
  await page.waitForTimeout(400);
  await page.click('button:has-text("Map View")');
  await page.waitForTimeout(1600);
  const offset = await page.evaluate(() => {
    const el = document.querySelector('.map-el');
    const rect = el.getBoundingClientRect();
    const dot = [...document.querySelectorAll('.leaflet-marker-icon')].find((i) => (i.innerHTML || '').includes('#2563eb'));
    if (!dot) return null;
    // Measure where the dot actually IS on screen. Its inline transform is NOT the answer:
    // it is relative to .leaflet-map-pane, which carries a transform of its own after any
    // pan — reading the marker's transform alone reported a perfectly centred map as
    // hundreds of pixels off, which is how this check first "found" a bug that wasn't one.
    const d = dot.getBoundingClientRect();
    return {
      dx: Math.round(d.left + d.width / 2 - (rect.left + rect.width / 2)),
      dy: Math.round(d.top + d.height / 2 - (rect.top + rect.height / 2)),
    };
  });
  check(
    'al volver de List View el punto de búsqueda sigue centrado',
    offset !== null && Math.abs(offset.dx) <= 2 && Math.abs(offset.dy) <= 2,
    offset ? `desvío (${offset.dx}, ${offset.dy})px` : 'no se encontró el punto azul',
  );

  // ── 5 · a refused basemap SAYS so ───────────────────────────────────────────
  const tilesReachable = await page.evaluate(() => document.querySelectorAll('.leaflet-tile-loaded').length > 0);
  if (tilesReachable) {
    console.log('SKIP  el aviso de mapa base caído (las teselas sí cargan en este entorno)');
  } else {
    const notice = await page.locator('.basemap-notice').count();
    check('sin teselas, la pantalla avisa en vez de mostrar un hueco mudo', notice === 1, `${notice} aviso(s)`);
  }

  check('la consola queda limpia', consoleErrors.length === 0, consoleErrors.join(' | ').slice(0, 300));
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.ok);
console.log('');
if (failed.length === 0) {
  console.log(`ALL CHECKS PASSED (${results.length})`);
} else {
  console.log(`${failed.length} OF ${results.length} CHECKS FAILED`);
  process.exitCode = 1;
}
