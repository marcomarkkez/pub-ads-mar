/**
 * Host resolution for the standalone Playwright guards (e2e-navbar.mjs, e2e-provider-spaces.mjs).
 *
 * WHY THIS FILE EXISTS. Both guards used to open with two absolute paths lifted from the
 * machine they were WRITTEN on:
 *
 *     import('playwright').catch(() => import('/opt/node22/lib/node_modules/playwright/index.js'))
 *     const PW = process.env.PW || '/opt/pw-browsers/chromium';
 *
 * On the Codespace that actually RUNS walkthrough 6 neither path exists — Node 24 there,
 * Node 22 here — so step W6-7 did not fail, it crashed with ERR_MODULE_NOT_FOUND before
 * the first check, printing a Node stack trace about a module path in a directory the
 * walker has never heard of. That says nothing about the app. A guard that only runs on
 * the machine it was written on guards nothing.
 *
 * So every host-shaped fact is resolved AT RUNTIME, and every failure to resolve one ends
 * in an instruction instead of a stack trace:
 *
 *   playwright   this repo depends on it nowhere on purpose (it is a walk tool, not app
 *                code). It may be a local devDependency, a global install, or absent.
 *                Absent is an answerable question: print the install line.
 *   chromium     either a preinstalled browser (PLAYWRIGHT_BROWSERS_PATH) or Playwright's
 *                own download. If no candidate is on disk we pass NO executablePath and
 *                let Playwright resolve it — its own "run playwright install" error is
 *                already the right message, better than one we invent.
 *   php artisan  native here (this sandbox has no docker daemon), `docker compose exec`
 *                in the Codespace. PROBED, never assumed — and probed with a query that
 *                opens the database, because `artisan --version` answers happily on a
 *                host whose .env points DB_HOST at a docker service it cannot resolve,
 *                which would pick the wrong runner and fail later, deeper, and vaguer.
 *
 * EXIT CODES. `CannotRun` means the guard never got to look at the app: the callers exit
 * 2. Exit 1 stays what it always was — it ran and the app failed a check. A walker has to
 * be able to tell "your app is broken" from "your machine is missing a tool" at a glance.
 */
import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, statSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
export const REPO_ROOT = path.resolve(HERE, '..');

/** Raised when the host cannot run the guard at all — as opposed to the app failing it. */
export class CannotRun extends Error {}

const firstLine = (e) => String(e && (e.stderr || e.message) || e).trim().split('\n')[0];

// ── playwright ───────────────────────────────────────────────────────────────────────

/** Directories from which `require('playwright')` could plausibly see the package. */
function moduleSearchRoots() {
  const roots = [];
  if (process.env.PLAYWRIGHT_DIR) roots.push(process.env.PLAYWRIGHT_DIR);
  roots.push(HERE, REPO_ROOT);          // frontend/node_modules, then the repo root's
  try {
    // `npm root -g` prints <prefix>/lib/node_modules; require() appends node_modules
    // itself, so the directory to search FROM is that path's parent.
    const g = execFileSync('npm', ['root', '-g'], { encoding: 'utf8', timeout: 20000 }).trim();
    if (g) roots.push(path.dirname(g));
  } catch { /* no npm on PATH — one candidate fewer, not a failure */ }
  return roots;
}

/**
 * Resolve Playwright's `chromium` from wherever it happens to live on this host.
 * @returns {Promise<{ chromium: object, from: string }>}
 */
export async function loadChromium() {
  const tried = [];
  for (const root of moduleSearchRoots()) {
    let entry;
    try {
      entry = createRequire(path.join(root, 'resolve-from-here.js')).resolve('playwright');
    } catch {
      tried.push(path.join(root, 'node_modules', 'playwright'));
      continue;
    }
    const mod = await import(pathToFileURL(entry).href);
    const chromium = (mod.default ?? mod).chromium;
    if (chromium) return { chromium, from: entry };
    tried.push(`${entry} (no exporta chromium)`);
  }
  throw new CannotRun(
    'Playwright no esta instalado en este host, y este repo no lo declara como dependencia\n' +
    'a proposito: es una herramienta de recorrido, no codigo de la app. Instalalo una vez:\n\n' +
    '    (cd frontend && npm install --no-save playwright && npx playwright install chromium)\n\n' +
    'y repite el paso. Si ya lo tienes en otro sitio, apunta PLAYWRIGHT_DIR a la carpeta\n' +
    'que contiene su node_modules. Buscado en:\n' +
    tried.map((t) => `    ${t}`).join('\n'));
}

/**
 * The chromium binary to launch, or `undefined` to let Playwright use its own.
 * `PW` still wins, but it is now VERIFIED: a stale PW pointing at nothing used to reach
 * Playwright as a launch failure about a path, several frames away from the cause.
 */
export function chromiumExecutablePath() {
  if (process.env.PW) {
    if (!existsSync(process.env.PW)) {
      throw new CannotRun(`PW=${process.env.PW} no existe. Quita PW para que Playwright ` +
        'use su propio navegador, o apuntalo al binario real.');
    }
    return process.env.PW;
  }
  const base = process.env.PLAYWRIGHT_BROWSERS_PATH;
  // "0" is Playwright's documented "keep browsers inside the package" value, not a path.
  if (base && base !== '0' && existsSync(base)) {
    for (const name of readdirSync(base).sort()) {
      if (!name.startsWith('chromium') || name.includes('headless')) continue;
      const nested = path.join(base, name, 'chrome-linux', 'chrome');   // download layout
      if (existsSync(nested)) return nested;
      const direct = path.join(base, name);                             // symlink-to-binary
      if (statSync(direct).isFile()) return direct;
    }
  }
  return undefined;
}

/** Launch options that work whether or not a preinstalled browser was found. */
export function launchOptions() {
  const executablePath = chromiumExecutablePath();
  return { args: ['--no-sandbox'], ...(executablePath ? { executablePath } : {}) };
}

// ── php artisan ──────────────────────────────────────────────────────────────────────

/**
 * Build a `tinker --execute` runner over whichever artisan this host can actually reach.
 *
 * The probe opens a database connection on purpose (see the header): `--version` would
 * green-light a native php whose .env names a docker-internal DB_HOST, and the guard
 * would then die minting its fixture instead of here, with a message about SQLSTATE
 * rather than about which artisan to use.
 *
 * @param {string} backendDir  the Laravel root, for the native invocation
 * @returns {(php: string) => string}
 */
export function makeArtisan(backendDir) {
  const candidates = [
    { label: `php artisan (nativo, en ${backendDir})`, cmd: 'php', argv: ['artisan'], cwd: backendDir },
    { label: 'docker compose exec -T backend php artisan',
      cmd: 'docker', argv: ['compose', 'exec', '-T', 'backend', 'php', 'artisan'], cwd: REPO_ROOT },
  ];
  const rejected = [];
  for (const c of candidates) {
    const run = (args, opts = {}) => execFileSync(c.cmd, [...c.argv, ...args],
      { cwd: c.cwd, encoding: 'utf8', timeout: 120000, ...opts });
    try {
      // getPdo() OPENS the connection; getDatabaseName() alone only reads config and
      // answers just as happily with the database down — which is how the first draft of
      // this probe green-lit a native artisan that could not reach Postgres at all.
      // tinker reports the failure on stdout and still exits 0, so the marker (which only
      // prints if getPdo() returned) is what decides, not the exit code.
      const out = run(['tinker', '--execute',
        "DB::connection()->getPdo(); echo 'ARTISAN_DB ' . DB::connection()->getDatabaseName();"],
        { stdio: ['ignore', 'pipe', 'pipe'] });
      if (!/ARTISAN_DB \S/.test(out)) throw new Error(`sin base de datos: ${out.trim()}`);
      console.log(`INFO  artisan: ${c.label}`);
      return (php) => run(['tinker', '--execute', php]);
    } catch (e) {
      rejected.push(`    ${c.label}\n      -> ${firstLine(e)}`);
    }
  }
  throw new CannotRun(
    'No hay forma de llegar a `php artisan` con base de datos viva desde este host.\n' +
    'Levanta la pila (docker compose up -d, o service postgresql start + backend/.env local)\n' +
    'y repite. Intentado:\n' + rejected.join('\n'));
}

// ── shared entry point ───────────────────────────────────────────────────────────────

/**
 * Run one resolution step, turning a `CannotRun` into an instruction and exit code 2.
 * Wrap the resolvers with this BEFORE the guard touches the database or the browser, so a
 * missing tool never gets to look like a half-finished run.
 */
export async function orExit(step) {
  try {
    return await step();
  } catch (e) {
    if (e instanceof CannotRun) {
      console.error(`\nNO SE PUDO EJECUTAR (esto no es un fallo de la app)\n\n${e.message}\n`);
      process.exit(2);
    }
    throw e;
  }
}
