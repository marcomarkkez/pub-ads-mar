# Claude Agents

Instructions and context for Claude AI agents working on this project.

## Project Overview

Web-based advertising spaces marketplace built with **Laravel 12** (API backend) and **Angular 18** (frontend). Five system roles (`client | provider | admin | support | payments`) plus per-account collaborator subroles. **Everything canonical — roles, specs, decisions, schema, routes, the role/object graph, and user stories — lives in [design.md](design.md), the SINGLE design doc. Not duplicated here.** In short: clients create campaigns with geolocated adsets and ads and book provider spaces; providers post spaces; admin/support/payments staff run operations.

**See [design.md](design.md) for full folder structure, database schema, API routes, data flow, dev commands, specs, user stories, and implementation status.**

---

## Guidelines

- Follow existing code conventions
- Write clear commit messages
- Test changes before committing
- Always append a new entry to `history.md` at the end of each session
- **Generate FEWER documents (owner rule, 2026-06-26, hardened 2026-06-27).** design.md is the
  SINGLE canonical home — schema, routes, specs, decisions, user stories, and the role/object
  graph all live there as sections (ARCHITECTURE.md / cases.md / platform-graph.md were merged
  into it and DELETED 2026-06-27). Do NOT create a new `.md` file — not even for a new topic or section: if it
  fits in design.md (and it almost always does), add a section there. Actionable work goes in the
  todos JSON (`.claude/todos/`). EVERYTHING ELSE — analyses, audits, reports, summaries, answers —
  is printed in the Claude UI chat, NOT written to a file, unless the owner explicitly asks for a
  file. Temporary working files must be folded into design.md/todos and deleted once consumed.

## Key Files

| File | Purpose |
|------|---------|
| `design.md` | THE single canonical doc — overview, roles, specs, decisions, schema, API/endpoint map, role/object graph, dev commands, status, AND the verbose user stories (§19). Absorbed ARCHITECTURE.md + cases.md + platform-graph.md (deleted 2026-06-27). |
| `history.md` | Session diary — append entries, never delete |
| `claude.md` | This file — agent instructions |
| `install_beads.md` | Guide for installing the `bd` task CLI |
| `backend/.env` | Environment config (DB, keys) |
| `backend/routes/api.php` | All API route definitions |
| `backend/app/Http/Controllers/` | API controllers grouped by role |
| `backend/app/Models/` | Eloquent models |

---

## Tech Stack

- **Backend:** Laravel 12 (API only) — `backend/` directory
- **Frontend:** Angular 18 SPA — `frontend/` directory
- **Database:** PostgreSQL 17 local **port 5434** — db: `pub_ads_mar`, user: `postgres`, no password (trust auth)
- **Auth:** Laravel Sanctum (token-based, Bearer token in header)
- **CORS:** Allow `http://localhost:4200` with credentials
- **File storage:** Laravel `storage/app/public/` via `php artisan storage:link`

---

## User Roles

Five system roles — `client`, `provider`, `admin`, `support`, `payments` — plus per-account
**collaborator subroles** (installator / publicist / manager). The canonical role + permission
spec and the collaborator model live in **[design.md](design.md)** (§Roles, §Collaborator Roles);
they are not duplicated here to avoid drift.

---

## Database Schema (11 tables)

| Table | Owner | Key Columns |
|-------|-------|-------------|
| users | — | role (client/provider/admin/support/payments), phone, company_name, address, is_active |
| campaigns | user (client) | name, description, status, start_date, end_date, budget |
| adsets | campaign | name, latitude, longitude, location_name, radius_km, status |
| ads | adset | name, media_type (image/video/sound/gif), file_path, file_name |
| spaces | user (provider) | name, type, latitude, longitude, price_per_day, width, height, is_active |
| space_photos | space | file_path, file_name, is_primary, sort_order |
| space_availabilities | space | start_date, end_date, status (available/booked/blocked) |
| bookings | client + space + adset | start_date, end_date, total_price, status |
| payments | booking | amount, status, payment_method (mocked), transaction_id |
| conversations | space + client + provider | unique per (space_id, client_user_id) |
| messages | conversation + sender | body, is_read |

---

## API Route Structure

```
POST /api/register, POST /api/login             — public
POST /api/logout, GET /api/me                   — any authenticated

/api/client/*    role:client   — campaigns, adsets, ads, bookings, space search
/api/provider/*  role:provider — spaces, photos, availabilities, bookings, dashboard
/api/manager/*   role:manager  — user CRUD (CRM)
/api/conversations/*           — any authenticated — messaging
```

---

## Dev Commands

```bash
# Backend
cd backend
php artisan serve                 # API at http://localhost:8000
php artisan migrate               # Run pending migrations
php artisan migrate:fresh --seed  # Reset DB + seed demo data
php artisan route:list            # List all routes
php artisan storage:link          # Link storage for file uploads

# Frontend
cd frontend
npm install
ng serve                          # SPA at http://localhost:4200

# PostgreSQL (local PG 17 on port 5434)
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5434 -d pub_ads_mar
```

---

## Skills Available in This Project

These skills are globally installed and available in all sessions:

| Skill | Trigger |
|-------|---------|
| `todo-tracker` | Session start/end, task management |
| `session-recorder` | Save session to history.md with token count |
| `skill-creator` | Create or update skills |
| `find-skills` | Discover installable skills |
| `pg:design-postgres-tables` | Design PostgreSQL schemas |

The `skill-creator` tool is also installed locally at `.agents/skills/skill-creator/` (symlinked to `.claude/skills/`).

---

## Implementation Status

See [design.md](design.md) §18 (System & Dev → Implementation status) for the full table.

**Completed:** Full backend API — Laravel scaffold, PostgreSQL DB, Sanctum auth, CORS, 14 migrations, 11 models, RoleMiddleware, 14 controllers, 49 routes, demo seeders, storage link. All endpoints tested and working.

**Next up (unblocked):**
1. Angular frontend development (auth, client, provider, manager features)
2. Frontend auth service + token interceptor
3. UI components (map, media preview, booking card)

---

## Notes

- `history.md` is the session diary — always append, never overwrite previous entries. Insert new entries before the `<!-- Add new sessions above this line -->` marker.
- Payments are mocked (no real gateway) for MVP.
- Geolocation search uses bounding-box filter for MVP; PostGIS is the upgrade path.
- Message body is filtered (strip phone numbers, emails, URLs via regex) to prevent direct contact sharing.
- The `bd` CLI (beads) can be installed for persistent task tracking — see `install_beads.md`. Install via: `go install github.com/steveyegge/beads/cmd/bd@latest` then add `%USERPROFILE%\go\bin` to Windows PATH.

---

## Docker Workflow (Canonical)

Dev is now Docker-first. `docker compose up -d --build` from repo root brings
up the whole stack: `backend` (PHP 8.4-fpm), `nginx` (:8000), `frontend`
(ng serve :4200), `db` (postgres:17-alpine, host :5435 → container :5432).

Key files: `docker-compose.yml`, `backend/Dockerfile`, `backend/docker/`,
`frontend/Dockerfile`. Plan lives at `.claude/plans/dockerize-stack.md`.

The PostgreSQL section below is now **legacy** — only relevant when running
without Docker.

---

## PostgreSQL Troubleshooting (Legacy — bare-metal only)

This machine has **multiple PostgreSQL installations** on different ports:

| Version | Port | Auth | Status |
|---------|------|------|--------|
| PG 17 | **5434** | `trust` (no password) | **Use this one** |
| PG 18 | 5432 | `scram-sha-256` (unknown password) | Do NOT use |
| PG 16 | 5433 | `scram-sha-256` | Do NOT use |

**Always use port 5434** for this project. The `.env` must have:
```
DB_PORT=5434
DB_PASSWORD=
```

The `pg_hba.conf` for PG 17 (`C:\Program Files\PostgreSQL\17\data\pg_hba.conf`) has `trust` for 127.0.0.1/32 and ::1/128, meaning no password is needed for local TCP connections.

**Common pitfall:** If you see "password authentication failed for user postgres", you're hitting the wrong PostgreSQL instance (port 5432 = PG 18). Always specify `-p 5434` in psql commands.

---

## Learnings / Common Mistakes

- **Windows `NUL` vs bash `/dev/null`:** On Windows with bash shell, never redirect to `NUL` — bash treats it as a literal filename and creates a `nul` file. Always use `/dev/null` instead.
- **Skill files go in `.agents/skills/`**, not the project root. If `.skill` files appear in the root, move them to `.agents/skills/`.
- **Don't create files in the project root** unless they're documented in design.md. Keep the root clean.
- **Beads `bd.exe` needs the sqlite backend here, not dolt.** The installed `bd.exe` (v0.49.3 dev) was built without CGO, so it CANNOT open the dolt backend ("dolt backend requires CGO"). It only worked while a background daemon (sqlite-backed, over a socket) was alive; once that daemon dies, direct CLI calls fall through to dolt and fail. Fix applied 2026-06-13: re-init to sqlite (`metadata.json` backend=sqlite; `bd init --backend sqlite`). Old dolt store is in `.beads-backup-20260613/`. Invoke as `/mnt/c/Users/mucho/go/bin/bd.exe --db .beads/beads.db ...` (it's a Windows binary, not on the WSL PATH).
- **Never run parallel agents that WRITE files while the user has files open in the IDE.** The first MVP build workflow ran parallel implementers in the same working tree; they wrote files the user had open → "content is newer" save conflicts, AND 4 packets failed mid-run. Rule: for code-mutating agent work, write SEQUENTIALLY (one area at a time), keep a change log (`.claude/mvp-changelog.md`) for surgical rollback, and have the user close affected tabs first.
- **OneDrive sync causes transient `ENOENT`/"file modified since read" on edits.** The repo lives under OneDrive; edits to large files (e.g. `design.md`) intermittently report `ENOENT` even though they LAND — and a blind retry can apply the edit TWICE (duplicate blocks). Rule: after an `ENOENT` on an edit, `grep -c` the inserted text before retrying; don't blindly re-edit.
- **Postgres must be running for backend verification.** Bare-metal PG17 on port 5434 (or Docker on 5435) is often down in a fresh WSL shell; `php artisan migrate` then fails with `SQLSTATE[08006] Connection refused`. Start PG/Docker before runtime checks; static checks (`php -l`, `ng build`) work without it.
- **Momentum over perfection — soft vs hard stops (owner working style).** The owner wants forward progress, not 100% compliance. Resolve ambiguous/"soft" logic conflicts yourself by **latest-decision-wins** (the most recently edited doc / newest owner message overrides older text) or by best-guess of the most sensible scenario, and KEEP BUILDING. Only stop to ask on a **HARD stopper**: something you genuinely cannot infer, or a destructive/irreversible/outward-facing action. Target ~**90% doc compliance** (design.md), not 100% — decisions have changed over time, so some older doc text is intentionally stale. The owner reviews the running app and prunes features afterward. Don't create new planning docs unless asked; edit design.md / todos in place.
- **Docker from WSL: use `docker.exe`, and DISABLE BuildKit to build.** The WSL `docker` socket is `root:docker` and the user isn't in the `docker` group (and sudo needs a password), so use the Windows binary `/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe`. BuildKit context streaming is BROKEN on this machine — every `docker compose build`/`docker build` cancels at "transferring context" (~0.7s) whether foreground or background, even with all base images pre-pulled (plain `docker pull` works fine). FIX: classic builder. `export WSLENV=DOCKER_BUILDKIT:$WSLENV; DOCKER_BUILDKIT=0 docker.exe compose up -d --build`. The stack is project **publisher** (db/backend/nginx/frontend); API :8000, SPA :4200, db :5435. Backend auto-migrates on boot.


<!-- BEGIN BEADS INTEGRATION v:1 profile:minimal hash:ca08a54f -->
## Beads Issue Tracker

This project uses **bd (beads)** for issue tracking. Run `bd prime` to see full workflow context and commands.

### Quick Reference

```bash
bd ready              # Find available work
bd show <id>          # View issue details
bd update <id> --claim  # Claim work
bd close <id>         # Complete work
```

### Rules

- Use `bd` for ALL task tracking — do NOT use TodoWrite, TaskCreate, or markdown TODO lists
- Run `bd prime` for detailed command reference and session close protocol
- Use `bd remember` for persistent knowledge — do NOT use MEMORY.md files

## Session Completion

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd dolt push
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
<!-- END BEADS INTEGRATION -->
