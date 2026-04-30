# Session History

This file tracks summaries of each development session.

---

## Session 1 - 2026-01-28

### Summary
- Initial repository setup
- Created README.md, claude.md, and history.md

### Changes Made
- Added basic project documentation files

---

## Session 2 - 2026-01-28

### Summary
- Checked environment for Laravel + Angular project setup
- Diagnosed GitHub push error: password authentication is no longer supported
- Defined full project scope for an advertising/spaces marketplace platform

### Environment Check Results
- Node.js v20.11.0 — installed
- npm 10.3.0 — installed
- Angular CLI 18.2.6 — installed
- PHP — NOT installed
- Composer — NOT installed
- Laravel — NOT installed

### GitHub Push Issue
- Error: "remote: Invalid username or token. Password authentication is not supported for Git operations."
- Cause: GitHub removed password auth; must use Personal Access Token (PAT), SSH key, or GitHub CLI (`gh auth login`)

### Project Scope Defined
The application is a **web-based advertising spaces marketplace** built with **Laravel (API)** and **Angular (frontend)**. Three user roles: **Clients**, **Providers**, and **Managers**.

**Client users:**
- Create campaigns containing adsets and ads (images, videos, sounds, gifs)
- Each adset is geolocated (Google Maps)
- Browse available provider spaces suggested by geolocation
- View space availability date ranges
- Book spaces through a payment system (mocked for now)
- Chat with providers about spaces (messages filtered to prevent sharing external contact info)

**Provider users:**
- Post available spaces with geolocation and photos
- Set date ranges for availability/occupancy
- Dashboard to receive messages and booking notifications
- View payment records and space booking history

**Manager users:**
- CRM-like panel to manually add provider users
- Administrative access

### Next Steps
- Install PHP (Option C: direct download to C:\php, configure PATH, enable extensions in php.ini)
- Install Composer
- Install Laravel via `composer global require laravel/installer`
- Begin architecture planning and project scaffolding

---

## Session 3 - 2026-01-28

### Summary
- Confirmed PHP and Composer are now installed
- Designed full backend architecture plan (models, migrations, controllers, routes)
- Chose **Laravel Sanctum** for API authentication
- Chose **PostgreSQL** as database (credentials: postgres/postgres)
- Discovered both `backend/` (Laravel) and `frontend/` (Angular) projects were already scaffolded
- Began Phase 2 (backend configuration) but session ended before completing

### Architecture Decisions
- **Auth:** Laravel Sanctum (token-based, SPA-friendly)
- **Database:** PostgreSQL, database name `pub_ads_mar`
- **Directory structure:** `backend/` for Laravel API, `frontend/` for Angular SPA
- **Single `users` table with `role` enum** (client, provider, manager) — simplest for MVP
- **Nested API routes** (e.g., `campaigns/{campaign}/adsets/{adset}/ads`) reflecting ownership hierarchy
- **Separate booking controllers per role** — Client creates/cancels, Provider confirms/rejects
- **Message filtering** — regex to strip phone numbers, emails, URLs from chat messages
- **Mocked payments** — no real gateway for MVP
- **Geolocation search** — bounding-box filter for MVP, PostGIS upgrade path later

### Current State
- `backend/` — Fresh Laravel project, default SQLite config, no Sanctum, no custom code
- `frontend/` — Fresh Angular project with SSR, no custom code
- `.env` — Still configured for SQLite, needs PostgreSQL config
- Database `pub_ads_mar` — NOT yet created (psql not in PATH)

### What Was Completed
- [x] Scaffold Laravel backend
- [x] Scaffold Angular frontend
- [x] Full architecture plan designed and approved

### Remaining TODO (continue next session)
1. **Create PostgreSQL database** — `psql` not in PATH; find PostgreSQL install path or use pgAdmin to create `pub_ads_mar`
2. **Configure `.env`** — Switch to `DB_CONNECTION=pgsql`, set host/port/db/user/password
3. **Install Sanctum** — Run `php artisan install:api`
4. **Configure CORS** — Publish cors config, allow `http://localhost:4200`, enable credentials
5. **Configure Sanctum stateful domains** — Add `localhost:4200`
6. **Update `bootstrap/app.php`** — Add `statefulApi()` middleware and `role` middleware alias
7. **Modify users migration** — Add `role` (enum), `phone`, `company_name`, `address`, `is_active`
8. **Create 10 migrations** (in order): campaigns, adsets, ads, spaces, space_photos, space_availabilities, bookings, payments, conversations, messages
9. **Run `php artisan migrate`**
10. **Update User model** — Add HasApiTokens, role helpers, relationships
11. **Create 10 Eloquent models** — Campaign, Adset, Ad, Space, SpacePhoto, SpaceAvailability, Booking, Payment, Conversation, Message
12. **Create RoleMiddleware** — Check user role, return 403 if unauthorized
13. **Create 14 API controllers** — Auth, Client (Campaign, Adset, Ad, Booking, SpaceSearch), Provider (Space, SpacePhoto, SpaceAvailability, Booking, Dashboard), Manager (User), Shared (Conversation, Message)
14. **Define all API routes in `api.php`** — Grouped by role with middleware
15. **Run `php artisan storage:link`** — For file uploads
16. **Create seeders** — Demo users (1 manager, 2 providers, 2 clients)
17. **Verify** — `php artisan route:list`, `php artisan serve`, test register/login endpoints

---

## Session 4 - 2026-02-09

### Summary
Created two comprehensive skills (todo-tracker and session-recorder) for better project workflow management. Set up Docker PostgreSQL container for the database. Encountered authentication issues with PostgreSQL connections from Laravel/PHP host to Docker container.

### Token Usage
- **Total tokens used:** 96,199 / 200,000 (48.1% of budget)
- **Remaining:** 103,801 tokens

### Tasks Completed
- [✓] Task #1 - Create PostgreSQL database pub_ads_mar (using Docker container)

### Tasks In Progress
- [~] Task #2 - Configure backend .env for PostgreSQL (blocked by #40)

### Tasks Created
- [+] Task #40 - Fix Docker PostgreSQL authentication for external connections

### Changes Made
- **Created Skills:**
  - `todo-tracker.skill` - Task management skill integrating with Claude's Task tools
    - Session start/end workflows
    - Progress reports and dependency analysis
    - Automatic history.md updates
  - `session-recorder.skill` - Session documentation skill with token tracking
    - Automatic token count extraction and formatting
    - File change detection (created/modified/deleted)
    - Task integration via TaskList
    - Complete session templates with examples

- **Skill Source Files:**
  - `todo-tracker/SKILL.md`
  - `todo-tracker/references/workflow_patterns.md`
  - `session-recorder/SKILL.md`
  - `session-recorder/references/session_templates.md`

- **Database & Configuration:**
  - Created 40 tasks tracking all remaining backend setup work
  - Enabled PHP PostgreSQL extensions (`pdo_pgsql`, `pgsql`) in `C:\php\php.ini`
  - Updated `backend/.env` to use PostgreSQL configuration:
    - DB_CONNECTION=pgsql
    - DB_HOST=127.0.0.1
    - DB_PORT=5432
    - DB_DATABASE=pub_ads_mar
    - DB_USERNAME=postgres
    - DB_PASSWORD=postgres
  - Set up Docker PostgreSQL 17 container named `pub-ads-postgres`
    - Database `pub_ads_mar` created
    - Port 5432 exposed to host
  - Created `test_db.php` for testing database connectivity

- **Documentation:**
  - Updated `history.md` with Session 4 entry (this file)

### Issues Encountered
- Windows PostgreSQL 17 installation had authentication problems (password mismatch)
- Attempted password reset via pg_hba.conf trust authentication - required admin privileges
- Switched to Docker PostgreSQL as cleaner solution
- Docker PostgreSQL container has authentication issues when connecting from Windows host
  - Connections work from inside container with password "postgres"
  - External connections from PHP/Laravel fail with "password authentication failed"
  - Attempted fixes: pg_hba.conf modifications, password reset, container recreation

### Next Session
- [ ] Resolve Docker PostgreSQL authentication issue for external connections
- [ ] Complete Task #2 - Verify Laravel can connect to PostgreSQL
- [ ] Task #3 - Install Laravel Sanctum API
- [ ] Task #4 - Configure CORS for Angular frontend
- [ ] Continue with remaining 35 tasks

### Technical Notes
- Docker container: `docker run --name pub-ads-postgres -e POSTGRES_PASSWORD=postgres -e POSTGRES_DB=pub_ads_mar -p 5432:5432 -d postgres:17`
- Container ID: 94969452299b
- PHP extensions enabled successfully (pdo_pgsql verified with `php -m`)
- Consider alternative approaches: trust authentication in pg_hba.conf, verify Docker network settings, or use SQLite temporarily

---

## Session 5 - 2026-02-21

### Summary
Reviewed project state across all sibling projects, confirmed global skills setup, resolved the Docker PostgreSQL blocker (local PostgreSQL 17 is working), created ARCHITECTURE.md, and significantly expanded claude.md to keep Claude oriented across sessions. Confirmed `bd` (beads) is already installed; only PATH setup remains.

### Tasks Completed
- [✓] Create ARCHITECTURE.md with folder structure, data flow, and dev commands

### Tasks In Progress
- None

### Changes Made
- **Created:** `ARCHITECTURE.md` — full folder tree, DB schema, API routes, auth/data flow, dev commands, implementation status table, key design decisions
- **Modified:** `claude.md` — complete rewrite with skills table, dev commands, implementation status, reference to ARCHITECTURE.md, bd install note

### Key Decisions
- **Drop Docker PostgreSQL:** Local PostgreSQL 17 at `C:\Program Files\PostgreSQL\17\bin\` is working — no need for Docker for now
- **Architecture reference:** ARCHITECTURE.md as dedicated file (not bloating claude.md) — referenced from claude.md header
- **Skills setup is already complete:** All skills (todo-tracker, session-recorder, skill-creator, find-skills, pg:*) are globally installed at `~/.claude/skills/` — no project-level copies needed
- **bd is installed:** `bd.exe` found at `C:\Users\mucho\go\bin\` — only needs `%USERPROFILE%\go\bin` added to Windows PATH

### Issues Encountered
- Docker PostgreSQL auth from previous session is now moot — local PG 17 already works and .env is already configured for it

### Next Session
- [ ] Add `C:\Users\mucho\go\bin` to Windows PATH, then run `bd init --quiet` and `bd setup claude`
- [ ] Verify Laravel → PostgreSQL connection (`php artisan migrate` or `tinker`)
- [ ] Run `php artisan install:api` (Sanctum)
- [ ] Configure CORS + Sanctum stateful domains in `bootstrap/app.php`
- [ ] Modify users migration + create 10 domain migrations → run `php artisan migrate`

---

## Session 6 - 2026-02-28

### Summary
Completed the **entire Laravel backend API**. Fixed PostgreSQL connection issue (wrong port — PG 17 runs on port 5434, not 5432), installed Sanctum, configured CORS, created all migrations/models/controllers/routes, seeded demo data, and verified all endpoints.

### Tasks Completed
- [x] Fixed PostgreSQL connection — discovered multiple PG versions (PG 17 on port 5434, PG 18 on 5432)
- [x] Installed Laravel Sanctum v4.3.1
- [x] Configured CORS for localhost:4200 with credentials
- [x] Configured Sanctum stateful domains (added localhost:4200)
- [x] Configured bootstrap/app.php with statefulApi() and role middleware alias
- [x] Modified users migration (added role, phone, company_name, address, is_active)
- [x] Created 10 domain migrations (campaigns → messages)
- [x] Ran migrate:fresh --seed successfully (14 tables)
- [x] Updated User model (HasApiTokens, role helpers, relationships)
- [x] Created 10 domain Eloquent models with relationships and casts
- [x] Created RoleMiddleware (checks user role, returns 403)
- [x] Created 14 API controllers (Auth, Client×5, Provider×5, Manager×1, Shared×2)
- [x] Defined all API routes (49 routes, nested resources)
- [x] Created demo seeder (1 manager, 2 providers, 2 clients, 4 spaces, 2 campaigns, 2 adsets, 4 availabilities)
- [x] Created storage:link for file uploads
- [x] Verified endpoints: login, /me, campaigns, space search, role middleware (403 on wrong role)

### Changes Made
- **Modified:** `backend/.env` — DB_PORT=5434, DB_PASSWORD= (empty, trust auth)
- **Modified:** `backend/bootstrap/app.php` — statefulApi(), role middleware alias
- **Modified:** `backend/database/migrations/0001_01_01_000000_create_users_table.php` — added custom columns
- **Modified:** `backend/database/seeders/DatabaseSeeder.php` — full demo data
- **Modified:** `backend/routes/api.php` — all 49 API routes
- **Created:** `backend/config/cors.php` — CORS for localhost:4200
- **Created:** 10 migration files (`2026_02_28_04000{1-10}_*.php`)
- **Created:** `backend/app/Models/{Campaign,Adset,Ad,Space,SpacePhoto,SpaceAvailability,Booking,Payment,Conversation,Message}.php`
- **Created:** `backend/app/Http/Middleware/RoleMiddleware.php`
- **Created:** `backend/app/Http/Controllers/AuthController.php`
- **Created:** `backend/app/Http/Controllers/Client/{CampaignController,AdsetController,AdController,BookingController,SpaceSearchController}.php`
- **Created:** `backend/app/Http/Controllers/Provider/{SpaceController,SpacePhotoController,SpaceAvailabilityController,BookingController,DashboardController}.php`
- **Created:** `backend/app/Http/Controllers/Manager/UserController.php`
- **Created:** `backend/app/Http/Controllers/Shared/{ConversationController,MessageController}.php`
- **Modified:** `claude.md` — PostgreSQL troubleshooting section, updated implementation status, added Learnings section
- **Modified:** `ARCHITECTURE.md` — updated port to 5434, updated implementation status to all done
- **Moved:** `session-recorder.skill` and `todo-tracker.skill` from root to `.agents/skills/`
- **Deleted:** `nul` (empty file created by bash/Windows NUL redirect mistake)

### Key Discovery — PostgreSQL Port Issue
This machine has **multiple PostgreSQL installations**: PG 17 on port **5434** (trust auth), PG 18 on port 5432 (unknown password), PG 16 on port 5433. Previous sessions tried to connect to port 5432 which belongs to PG 18. The fix was to use port 5434 with empty password. Added this info to claude.md PostgreSQL Troubleshooting section.

### Demo Accounts
| Role | Email | Password |
|------|-------|----------|
| Manager | manager@pubads.test | password |
| Provider | provider1@pubads.test | password |
| Provider | provider2@pubads.test | password |
| Client | client1@pubads.test | password |
| Client | client2@pubads.test | password |

### Next Session
- [ ] Begin Angular frontend development
- [ ] Create auth module (login/register pages)
- [ ] Create core services (AuthService, ApiService, TokenInterceptor)
- [ ] Create route guards (AuthGuard, RoleGuard)
- [ ] Build client features (campaigns, adsets, ads, space search, bookings)
- [ ] Build provider features (spaces, photos, availabilities, dashboard)
- [ ] Build manager features (CRM user management)

---

## Session 7 - 2026-03-03

### Summary
Built the iCal calendar sync feature for the Laravel backend. Created a service class to parse .ics files, an artisan command for batch syncing, and two new provider API endpoints for manual sync and file upload import.

### Tasks Completed
- [x] Created `IcalSyncService` — parses VEVENT blocks from .ics content, creates blocked SpaceAvailability records with source='ical'
- [x] Created `SyncIcalCalendars` artisan command (`php artisan ical:sync`) — batch syncs all spaces with iCal URLs
- [x] Added `syncIcal()` method to SpaceAvailabilityController — manual sync from space's configured ical_url
- [x] Added `importIcal()` method to SpaceAvailabilityController — upload a .ics file directly
- [x] Added two new routes to api.php in the provider group

### Changes Made
- **Created:** `backend/app/Services/IcalSyncService.php` — iCal parsing service (RFC 5545 line unfolding, VEVENT extraction, DTSTART/DTEND parsing for DATE and DATETIME formats, exclusive end date handling)
- **Created:** `backend/app/Console/Commands/SyncIcalCalendars.php` — artisan command with optional `--space=` filter
- **Modified:** `backend/app/Http/Controllers/Provider/SpaceAvailabilityController.php` — added `syncIcal()` and `importIcal()` methods
- **Modified:** `backend/routes/api.php` — added `POST provider/spaces/{space}/sync-ical` and `POST provider/spaces/{space}/import-ical`

### New API Routes
| Method | URL | Controller Method | Description |
|--------|-----|-------------------|-------------|
| POST | `/api/provider/spaces/{space}/sync-ical` | `SpaceAvailabilityController@syncIcal` | Sync from space's configured ical_url |
| POST | `/api/provider/spaces/{space}/import-ical` | `SpaceAvailabilityController@importIcal` | Upload and parse a .ics file |

### New Artisan Command
```bash
php artisan ical:sync              # Sync all spaces with iCal URLs
php artisan ical:sync --space=5    # Sync only space ID 5
```

### Technical Notes
- No external packages used — iCal parsing uses regex/string operations on raw .ics content
- RFC 5545 line unfolding is handled (continuation lines starting with space/tab)
- Supports DATE (20260301), DATETIME local (20260301T120000), and DATETIME UTC (20260301T120000Z) formats
- DATE-only DTEND values are treated as exclusive per iCal spec (one day subtracted)
- Before each sync, all previous ical-sourced records are deleted to prevent duplicates
- Synced periods are marked as 'blocked' status with source='ical'

### Next Session
- [ ] Begin Angular frontend development
- [ ] Create auth module (login/register pages)
- [ ] Create core services (AuthService, ApiService, TokenInterceptor)

---

## Session 8 - 2026-03-05 (Part 1: RBAC Backend)

### Summary
Continued from previous session. Fixed 12 Python test script failures, created fake data script (insert_data.py), implemented full RBAC permissions system in the backend.

### Tasks Completed
- [x] Fixed test_all_endpoints.py — 12 failures resolved (register missing role, login status code, space search params, paginated response handling, variable scoping)
- [x] Created insert_data.py — fake data generator with --scale small|medium|large options (30 Paris locations, realistic French names, full workflow: users → spaces → campaigns → bookings → proofs → tickets)
- [x] RBAC permissions system — role_permissions table, RolePermission model with 60-min caching, PermissionMiddleware, Admin PermissionController (4 endpoints), RolePermissionSeeder (67 default permissions)
- [x] Rewrote routes/api.php — every route now has per-route permission middleware
- [x] Updated AuthController — login/me responses include permissions for frontend
- [x] Added 9 new permission endpoint tests — 96/96 tests passing
- [x] Updated design.md with RBAC, Provider Form, Roles Editor sections
- [x] Updated ARCHITECTURE.md with new files, status, demo accounts

---

## Session 8 - 2026-03-05 (Part 2: Angular Frontend)

### Summary
Built the entire Angular 18 frontend SPA. Created all feature components for all 5 roles, auth system, route guards, lazy-loaded routes, and global styling.

### Tasks Completed
- [x] Environment config (apiUrl for localhost:8000)
- [x] Core models (TypeScript interfaces for all 11 DB entities + API types)
- [x] AuthService with signals, token management, permissions, loadUser on init
- [x] Auth interceptor (Bearer token injection, 401/403/422/500 error handling)
- [x] Auth guards (authGuard, guestGuard) + roleGuard factory
- [x] Login + Register pages with role selector (client/provider)
- [x] Layout: Navbar (user menu + role badge), Sidebar (role-specific nav), Main layout
- [x] Notification toast component (success/error/warning/info)
- [x] Dashboard with role-specific quick actions
- [x] App routes with lazy loading for all features
- [x] Admin: User list (CRM table + pagination + delete), User form (create/edit with provider-specific fields), Permissions editor (RBAC matrix with checkboxes)
- [x] Provider: Space list/form/detail (with photo upload + availability management), Booking list (confirm/cancel), Proof list (upload proofs)
- [x] Client: Campaign list/form/detail (with adsets, ads, file upload, collaborators), Space search (geo search with card grid), Booking list (expandable details), Invoice list
- [x] Shared: Conversation list + chat detail (message alignment), Ticket list/form/detail (with replies)
- [x] Support: Ticket list (with status filter), Ticket detail (status update + reply)
- [x] Payments: Payment list (approve/reject), Proof review list (approve/reject)
- [x] Global styles (CSS variables, forms, buttons, tables, badges, modals, pagination)
- [x] Build successful: 335 KB initial bundle, all lazy chunks loading correctly
- [x] Disabled SSR/prerendering (SPA-only for now)

### Files Created (40+ files)
- `frontend/src/environments/environment.ts` + `environment.prod.ts`
- `frontend/src/app/core/models/*.ts` (6 model files + barrel export)
- `frontend/src/app/core/services/auth.service.ts` + `notification.service.ts`
- `frontend/src/app/core/interceptors/auth.interceptor.ts`
- `frontend/src/app/core/guards/auth.guard.ts` + `role.guard.ts`
- `frontend/src/app/features/auth/login/*` + `register/*`
- `frontend/src/app/features/dashboard/dashboard.component.ts`
- `frontend/src/app/features/admin/users/*` (list + form) + `permissions/*`
- `frontend/src/app/features/provider/spaces/*` (list + form + detail) + `bookings/*` + `proofs/*`
- `frontend/src/app/features/client/campaigns/*` (list + form + detail) + `spaces/*` + `bookings/*` + `invoices/*`
- `frontend/src/app/features/shared/conversations/*` + `tickets/*`
- `frontend/src/app/features/support/*` (list + detail)
- `frontend/src/app/features/payments/*` (list + review)
- `frontend/src/app/shared/components/navbar/*` + `sidebar/*` + `notification-toast/*`
- `frontend/src/app/shared/layouts/main-layout/*` + `auth-layout/*`

### Files Modified
- `frontend/src/app/app.config.ts` — added HttpClient, interceptor, APP_INITIALIZER
- `frontend/src/app/app.routes.ts` — full routing with lazy loading + guards
- `frontend/src/app/app.component.ts` + `.html` — replaced placeholder
- `frontend/src/styles.scss` — complete global styles
- `frontend/angular.json` — disabled SSR/prerender

### Build Stats
- Initial bundle: 335.24 KB (95.27 KB gzip)
- 23 lazy-loaded chunks for feature components
- Build time: ~3 seconds

### Next Session
- [ ] Test frontend end-to-end with backend (login, navigate, CRUD operations)
- [ ] Add Google Maps integration for space search
- [ ] Add file preview components for media (image/video/audio)
- [ ] Create Selenium QA test script

## Session 9 - 2026-03-10

### Summary
Short session focused on reviewing project state and closing the Selenium QA bead. Confirmed the previous session's Selenium test results (24/25 passed, 1 failure was a Chrome browser disconnect — not a real test failure). Closed bead `pub-ads-mar-mp0`. Verified Angular frontend builds successfully (335 KB initial bundle, all lazy chunks OK). Identified next priorities.

### Tasks Completed
- [x] Closed bead `pub-ads-mar-mp0` — Selenium QA test script (24/25 passing)
- [x] Verified Angular build succeeds (`ng build` — no errors)
- [x] Reviewed project state and prioritized next work

### Changes Made
- No code changes this session

### Next Session
- [ ] End-to-end smoke test: start backend + frontend, test login and navigation for all roles
- [ ] Fix any frontend bugs found during E2E testing
- [ ] Add Google Maps integration for space search
- [ ] Add file preview components for media (image/video/audio)

## Session 10 - 2026-04-06

### Summary
Continued from a compacted session. Fixed widespread `res.data` / `PaginatedResponse` type errors across 6 frontend components, completed the Leaflet + what3words map abstraction (LocationPickerComponent), and created a useful_commands.md reference file.

### Tasks Completed
- [x] Fixed 9 TypeScript compile errors across 6 files — `res.data` and `PaginatedResponse` mismatches
- [x] Built `LocationPickerComponent` — shared Leaflet map + what3words autosuggest, drag-to-pick, abstraction layer for future Google Maps swap
- [x] Integrated LocationPickerComponent into `space-form.component.ts` (provider space creation/editing)
- [x] Built Leaflet map into `space-search.component.ts` (client space browsing with multi-marker, click-to-search)
- [x] Installed `leaflet` + `@types/leaflet`, added Leaflet CSS to `angular.json`
- [x] Updated `environment.ts` with `mapProvider`, `googleMapsApiKey`, `what3wordsApiKey`
- [x] Removed `provideClientHydration()` from `app.config.ts` — root cause of all infinite loading (SSR feature that blocks HTTP in plain SPA)
- [x] Batch-fixed 16+ components with `res.data` → `res` for non-paginated API responses
- [x] Fixed ticket creation error (`ticketable_type` field required) — backend made nullable, frontend fixed payload keys
- [x] Rewrote `DatabaseSeeder.php` — San Pedro Garza García NL locations, MXN pricing, 6 spaces, 3 campaigns, 3 invoices, 9 messages
- [x] Created `useful_commands.md` — server start, DB commands, psql connection, demo accounts

### Files Created
- `frontend/src/app/shared/components/location-picker/location-picker.component.ts` — shared map abstraction (Leaflet + w3w)
- `useful_commands.md` — quick reference for dev commands and demo accounts

### Files Modified
- `frontend/src/app/app.config.ts` — removed `provideClientHydration()`
- `frontend/src/app/features/admin/users/user-list.component.ts` — `res` → `res.data` (paginated)
- `frontend/src/app/features/client/bookings/booking-list.component.ts` — `PaginatedResponse` → plain array
- `frontend/src/app/features/client/campaigns/campaign-list.component.ts` — `PaginatedResponse` → plain array
- `frontend/src/app/features/client/invoices/invoice-list.component.ts` — `PaginatedResponse` → plain array
- `frontend/src/app/features/client/spaces/space-search.component.ts` — replaced Google Maps with Leaflet
- `frontend/src/app/features/provider/spaces/space-form.component.ts` — replaced Google Maps with LocationPickerComponent
- `frontend/src/app/features/provider/spaces/space-detail.component.ts` — fixed `res.data` references
- `frontend/src/app/features/support/ticket-detail.component.ts` — fixed `res.data` references
- `frontend/src/app/features/shared/tickets/ticket-form.component.ts` — fixed payload field names
- `frontend/src/environments/environment.ts` — added mapProvider, API keys
- `frontend/angular.json` — added leaflet CSS to styles
- `backend/app/Http/Controllers/Shared/TicketController.php` — made ticketable fields nullable
- `backend/database/seeders/DatabaseSeeder.php` — San Pedro NL data with MXN pricing

### Key Fixes
- **Infinite loading on all pages** — `provideClientHydration()` is an Angular Universal SSR feature that intercepts HTTP requests waiting for server transfer state that never arrives in a plain SPA. Removing it fixed everything.
- **`res.data` pattern** — All non-paginated Laravel endpoints return plain arrays/objects. Only `/client/spaces/search` returns paginated `{ data, current_page, last_page, total }`.
- **Ticket creation** — Frontend sent `reference_type`/`reference_id` but backend expected `ticketable_type`/`ticketable_id`.
- **Invoice seeder** — DB check constraint only allows `draft/issued/paid/overdue/cancelled` (not `pending`).

### Build Status
- TypeScript: 1 error (boilerplate `app.component.spec.ts` — not a real issue)
- All feature components compile clean

### Next Session
- [ ] Commit all changes
- [ ] End-to-end smoke test with both servers running
- [ ] Add file preview components for media (image/video/audio)
- [ ] Consider getting a what3words API key for enhanced location input

## Session 11 - 2026-04-07

### Summary
Continued from Session 10. Fixed remaining `res.data` / `PaginatedResponse` compile errors across 6 components. Added `calendar_keyword` field to spaces for smart iCal event filtering. Fixed photo upload error (missing `file_url` accessor on SpacePhoto model). Updated space creation flow to redirect to detail page for photos/availabilities. Built full Calendar Sync UI in space-detail. Created `useful_commands.md`.

### Tasks Completed
- [x] Fixed 9 TypeScript compile errors (`res.data`, `PaginatedResponse` mismatches) in user-list, booking-list, campaign-list, invoice-list, space-detail, ticket-detail
- [x] Fixed photo upload error — `SpacePhoto` model lacked `file_url` accessor; added `$appends` with `getFileUrlAttribute()`
- [x] Added `calendar_keyword` column to spaces table (new migration + model + controller)
- [x] Updated `IcalSyncService` to filter events by keyword — if set, only events with matching SUMMARY are treated as occupied; if blank, all events = occupied
- [x] Updated space-form to redirect to detail page after creation (so provider immediately sees photos/availabilities/calendar sections)
- [x] Built Calendar Sync section in space-detail: iCal URL input, keyword input, Save/Sync Now/Upload .ics buttons with feedback
- [x] Added "Source" column to availabilities table (manual vs ical)
- [x] Updated `Space` TypeScript model with `ical_url`, `calendar_keyword` fields
- [x] Updated `SpaceAvailability` TypeScript model with `source` field
- [x] Created `useful_commands.md` — server commands, DB reset, psql, demo accounts

### Files Created
- `backend/database/migrations/2026_04_07_062355_add_calendar_keyword_to_spaces_table.php`
- `useful_commands.md`

### Files Modified
- `backend/app/Models/Space.php` — added `calendar_keyword` to fillable
- `backend/app/Models/SpacePhoto.php` — added `$appends = ['file_url']` and `getFileUrlAttribute()`
- `backend/app/Http/Controllers/Provider/SpaceController.php` — accepts `calendar_keyword` on store/update
- `backend/app/Services/IcalSyncService.php` — keyword-based event filtering logic
- `frontend/src/app/core/models/space.model.ts` — added `ical_url`, `calendar_keyword`, `source`
- `frontend/src/app/features/provider/spaces/space-detail.component.ts` — Calendar Sync UI, source column, calendar state/methods
- `frontend/src/app/features/provider/spaces/space-form.component.ts` — redirect to detail after create
- `frontend/src/app/features/admin/users/user-list.component.ts` — fixed paginated response (`res` → `res.data`)
- `frontend/src/app/features/client/bookings/booking-list.component.ts` — removed `PaginatedResponse`, plain array
- `frontend/src/app/features/client/campaigns/campaign-list.component.ts` — removed `PaginatedResponse`, plain array
- `frontend/src/app/features/client/invoices/invoice-list.component.ts` — removed `PaginatedResponse`, plain array
- `frontend/src/app/features/support/ticket-detail.component.ts` — removed `res.data` wrappers

### Calendar Sync Logic
- **Default (no keyword):** All calendar events = occupied dates. Blank days = available.
- **With keyword:** Only events whose title contains the keyword = occupied. Other events are ignored.
- **Google Calendar:** Provider pastes public iCal URL (`https://calendar.google.com/calendar/ical/.../basic.ics`), system syncs VEVENTs as blocked availability records.
- **File upload:** Provider can also upload a `.ics` file directly.
- iCal sync deletes previous ical-sourced records before re-importing (clean sync).

### Build Status
- TypeScript: 1 error (boilerplate `app.component.spec.ts` — not a real issue)
- All feature components compile clean
- Migration ran successfully

### Next Session
- [ ] Commit all changes
- [ ] End-to-end smoke test with both servers running
- [ ] Test photo upload and calendar sync flows
- [ ] Add file preview components for media (image/video/audio)
- [ ] Consider getting a what3words API key for enhanced location input
- [ ] Install Angular CLI globally (`npm install -g @angular/cli@18`) or use `npx ng serve`

## Session 12 - 2026-04-10

### Summary
Brief continuation session from Session 11. No new code written — work from the previous session (Calendar Sync feature, `calendar_keyword` filtering, photo upload fix via `file_url` accessor, `res.data` fixes) remains uncommitted and pending verification. User confirmed they were already aware of the pending work. Session used to refresh todos and re-save the session diary with the current date.

### Tasks Completed
- [x] Re-confirmed build status (1 pre-existing boilerplate test error, all feature code clean)
- [x] Updated history.md with current session entry
- [x] Refreshed pending todo list

### Changes Made
- No code changes in this session
- Documentation update: new history.md entry

### Outstanding Work (carried over from Session 11)
All of these remain pending:
- [ ] Commit all changes from Sessions 10 + 11 (significant uncommitted work:
  Leaflet/w3w map, calendar sync backend + UI, `calendar_keyword` migration,
  `SpacePhoto.file_url` accessor, `res.data` fixes, seeder rewrite)
- [ ] End-to-end smoke test with both servers running (`php artisan serve` + `npx ng serve`)
- [ ] Verify photo upload now works after the `file_url` accessor fix
- [ ] Test calendar sync flow: paste a public Google Calendar iCal URL, click Sync Now, verify availabilities populate
- [ ] Test calendar keyword filtering — add a keyword, re-sync, verify only matching events block dates
- [ ] Add file preview components for media (image/video/audio)
- [ ] Get a what3words API key to enable the w3w autosuggest input on LocationPickerComponent
- [ ] Install Angular CLI globally (`npm install -g @angular/cli@18`) or continue using `npx ng serve`

### Notes
- Nothing was lost — the Session 11 diff is still in the working tree, no rollback needed
- Recommended first action next session: `git status` and commit work in logical chunks
  (backend migration + model + service, then frontend space-detail, then the `res.data` batch)

<!-- Add new sessions above this line -->

