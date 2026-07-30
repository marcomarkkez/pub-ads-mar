# Useful Commands

## Start Stack (Docker — canonical)

```bash
# Bring up backend + nginx + frontend + postgres
docker compose up -d --build

# Tail logs
docker compose logs -f backend
docker compose logs -f frontend

# Stop everything
docker compose down

# Stop + delete DB volume (fresh DB next boot)
docker compose down -v
```

Endpoints once up:
- API:      http://localhost:8000
- Frontend: http://localhost:4200
- DB:       localhost:5435 (host) → 5432 (container)

## Inside containers

```bash
# Artisan commands
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend php artisan route:list
docker compose exec backend php artisan tinker

# Composer
docker compose exec backend composer install
docker compose exec backend composer require <pkg>

# psql into containerized DB
docker compose exec db psql -U postgres -d pub_ads_mar

# Frontend shell (ng generate etc.)
docker compose exec frontend npx ng generate component my-comp
```

## Python helpers (MVP init + dashboard)

```bash
# Self-healing DB seed — run from the repo root. Idempotent: only reseeds when the
# DB is empty, otherwise just clears caches. Fixes the "credentials incorrect" login
# error that appears when the container starts with an empty database.
python3 mvp-init.py

# Force a clean wipe + reseed (migrate:fresh --seed), regardless of current state
python3 mvp-init.py --fresh

# Seed without the 3-chat dispute demo (DisputeDemoSeeder)
python3 mvp-init.py --no-dispute

# Serve the design dashboard over HTTP (design/design.html fetches design.json, so it
# MUST be served — opening it as a file:// URL blocks the fetch). Then open
# http://localhost:8080/design.html
cd design && python3 -m http.server 8080
```

After `mvp-init.py` you can log in at the SPA with any demo account below (password `password`).

## Legacy: Bare-metal start (no Docker)

```bash
# Backend API (http://localhost:8000)
cd backend
php artisan serve

# Frontend SPA (http://localhost:4200)
cd frontend
ng serve
```

Requires host PHP 8.4 with ext-dom/xml/pgsql + local PG 17 on port 5434.
Use Docker workflow instead unless you have a specific reason.

## Legacy: PostgreSQL on host (PG 17 on port 5434)

```bash
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5434 -d pub_ads_mar
```

> Always use port **5434**. Port 5432 is PG 18 (wrong password), port 5433 is PG 16.

## Frontend

```bash
cd frontend

# Install dependencies
npm install

# Type-check without building
npx tsc --noEmit

# Build for production
ng build
```

## Fix `ng` not recognized (Angular CLI on PATH)

If you see this error in PowerShell:
```
ng : The term 'ng' is not recognized as the name of a cmdlet, function, script file, or operable program.
```

It means Angular CLI isn't on your PATH. Three solutions, easiest first:

### Option 1 — Use `npx` (no setup, works immediately)

```bash
cd frontend
npx ng serve
npx ng build
npx ng generate component my-comp
```

`npx` finds the Angular CLI installed locally in `frontend/node_modules/.bin/` and runs it. **Recommended for project work** — no global install pollution.

### Option 2 — Install Angular CLI globally

```powershell
npm install -g @angular/cli@18
```

After install, restart your terminal and try `ng version`. If still not found, your npm global bin is not on PATH — see Option 3.

### Option 3 — Add npm global bin to Windows PATH (permanent fix)

1. Find your npm global folder:
   ```powershell
   npm config get prefix
   ```
   It usually prints something like `C:\Users\mucho\AppData\Roaming\npm`

2. Add it to PATH:
   - Press **Win + R**, type `sysdm.cpl`, press **Enter**
   - **Advanced** tab → **Environment Variables**
   - Under **User variables**, select **Path** → **Edit**
   - Click **New**, paste the path from step 1
   - **OK** on all dialogs
   - **Restart your terminal** (close and reopen — environment variables reload)

3. Verify:
   ```powershell
   ng version
   ```

**Backup of paths to add (in case PATH gets reset):**

| Variable | Path |
|----------|------|
| User PATH | `C:\Users\mucho\AppData\Roaming\npm` |
| User PATH | `C:\Users\mucho\go\bin` (for `bd` beads CLI) |
| User PATH | `C:\Program Files\PostgreSQL\17\bin` (for `psql`) |
| User PATH | `C:\php` (for `php` and `composer`, if installed there) |

After editing PATH, always **restart your terminal** so the changes take effect.

## Demo Accounts

| Email | Password | Role |
|-------|----------|------|
| client1@pubads.test | password | client |
| client2@pubads.test | password | client |
| provider1@pubads.test | password | provider |
| provider2@pubads.test | password | provider |
| manager@pubads.test | password | manager |
