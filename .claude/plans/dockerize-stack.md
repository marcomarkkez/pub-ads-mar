# Dockerize pub-ads-mar Stack — Implementation Plan

## Summary
Eliminate host PHP/extension/Postgres-version drift by containerizing all runtime
dependencies. After this work, `git clone && docker compose up` is the only
command needed to bring up a working dev environment (Laravel API, Angular SPA,
PostgreSQL 17). The host machine only needs Docker.

## Current State

- **Backend:** Laravel 12 in `backend/`, requires PHP ^8.2; lockfile pinned
  packages requiring PHP >= 8.4 (symfony 8, nesbot/carbon 3.11 -> symfony/clock 8).
- **Host PHP:** 8.3.6 on WSL Ubuntu, missing ext-dom / ext-xml / ext-curl.
- **Postgres:** Three local installs on the Windows host (PG16:5433, PG17:5434,
  PG18:5432). Project uses PG17 on 5434, `trust` auth, db `pub_ads_mar`.
- **Frontend:** Angular 18 in `frontend/`, served via `ng serve` on :4200.
- **Composer:** Not installed system-wide; just installed manually to
  `/usr/local/bin/composer`.

## Proposed Architecture

Four services orchestrated by `docker-compose.yml` at repo root.

| Service   | Image base                          | Purpose                  | Host port |
|-----------|-------------------------------------|--------------------------|-----------|
| `backend` | `php:8.4-fpm-alpine` + extensions   | Laravel API (php-fpm)    | n/a       |
| `nginx`   | `nginx:alpine`                      | Reverse-proxy to fpm     | 8000      |
| `frontend`| `node:20-alpine`                    | `ng serve` w/ HMR        | 4200      |
| `db`      | `postgres:17-alpine`                | Application database     | 5434      |

PHP extensions baked into `backend` image: `pdo_pgsql`, `pgsql`, `dom`, `xml`,
`mbstring`, `zip`, `gd`, `bcmath`, `opcache`, plus Composer.

Networking: single bridge network `pub-ads-net`. Backend reaches db via
hostname `db:5432`; frontend reaches backend via host port 8000 (or `backend`
hostname when SSR is added).

Volumes:
- `./backend:/var/www` — bind mount for live PHP edits
- `backend_vendor:/var/www/vendor` — named volume (avoid host I/O penalty)
- `./frontend:/app` + `frontend_node_modules:/app/node_modules` — same pattern
- `pgdata:/var/lib/postgresql/data` — persisted DB

Env overrides (compose `environment:` block on backend):
```
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=pub_ads_mar
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

## Proposed Changes

### New file: `docker-compose.yml` (repo root)
Defines 4 services, network, named volumes, healthchecks (`pg_isready`,
backend `php artisan about`).

### New file: `backend/Dockerfile`
Multi-stage:
1. `composer` stage: copy composer.json/lock, run `composer install --no-scripts --no-autoloader`.
2. Runtime stage: `php:8.4-fpm-alpine`, install ext via `docker-php-ext-install`,
   `apk add postgresql-dev libzip-dev libxml2-dev`, copy app + vendor, set perms
   on `storage/` and `bootstrap/cache/`.

### New file: `backend/docker/nginx.conf`
Standard Laravel nginx vhost: root `/var/www/public`, `fastcgi_pass backend:9000`.

### New file: `backend/docker/entrypoint.sh`
- `php artisan storage:link` (idempotent)
- `php artisan migrate --force` (only if `MIGRATE_ON_BOOT=true`)
- `exec php-fpm`

### New file: `frontend/Dockerfile`
`node:20-alpine`, `npm ci`, expose 4200, CMD
`ng serve --host 0.0.0.0 --poll 2000 --disable-host-check`.

### New file: `.dockerignore` in repo root, backend/, frontend/
Exclude `vendor/`, `node_modules/`, `.env`, `.git/`, `storage/logs/*`.

### Modified file: `backend/.env.example`
Set DB defaults to point at `db:5432` with password `postgres`.

### Modified file: `useful_commands.md`
Replace `cd backend && php artisan serve` with `docker compose up`.

### Modified file: `ARCHITECTURE.md`
Add "Docker" section in dev commands, mark PostgreSQL Troubleshooting as
historical.

### Modified file: `CLAUDE.md`
Note Docker is now the canonical dev path; demote the PG troubleshooting
section to a "legacy / bare-metal" appendix.

## Risk Assessment

| Risk                                          | Impact | Mitigation                                                       |
|-----------------------------------------------|--------|------------------------------------------------------------------|
| WSL `/mnt/c` bind-mount slow                  | Medium | Document moving repo into `\\wsl$\Ubuntu\home\...` as upgrade    |
| Angular HMR doesn't fire on bind mount        | Medium | `--poll 2000` + `CHOKIDAR_USEPOLLING=true`                       |
| Existing local PG17 data lost                 | Low    | Dump current DB → mount `*.sql` into `/docker-entrypoint-initdb.d` |
| Composer lockfile still pins PHP 8.4 pkgs     | Low    | Image uses PHP 8.4, so lockfile works as-is                      |
| Storage perms (php-fpm uid vs host uid)       | Medium | `chown -R www-data:www-data storage bootstrap/cache` in entrypoint |
| Port 5434 collision with host PG17            | High   | Map db to host `5434:5432` only if host PG17 is stopped; otherwise use `5435:5432` |

## Testing Strategy

1. `docker compose build` — all images build clean
2. `docker compose up -d` — all services Healthy
3. `curl http://localhost:8000/api/me` → expect 401 (auth working)
4. `docker compose exec backend php artisan migrate:fresh --seed` — DB reachable
5. `curl -X POST http://localhost:8000/api/login` with seeded creds → token
6. `http://localhost:4200` loads Angular app, HMR works on file change
7. `docker compose exec db psql -U postgres -d pub_ads_mar -c '\dt'` shows 11 tables
8. Stop + start: data persists in `pgdata` volume

## Rollback Plan

Docker is additive — no existing files deleted. To roll back:
1. `docker compose down -v` (removes containers + volumes)
2. Delete `docker-compose.yml`, `backend/Dockerfile`, `frontend/Dockerfile`,
   `backend/docker/`, `.dockerignore` files
3. Revert `.env.example`, `useful_commands.md`, `ARCHITECTURE.md`, `CLAUDE.md`
4. Resume bare-metal workflow (host PHP + host PG17 on 5434)

## Verification Checklist
- [ ] `docker compose build` passes
- [ ] `docker compose up` brings all 4 services to Healthy
- [ ] Login endpoint returns a token for seeded user
- [ ] Angular SPA loads at :4200 and consumes API at :8000
- [ ] DB data survives `docker compose restart`
- [ ] `useful_commands.md` updated
- [ ] `ARCHITECTURE.md` Docker section added
- [ ] Beads issues filed for follow-ups (CI image build, prod variant)
