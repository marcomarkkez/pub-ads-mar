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

## Session 13 - 2026-04-12

### Summary
Audit and cleanup session focused on git hygiene and Windows environment fixes. No feature code written — but a major hidden issue was discovered: the `backend/` folder was registered as a gitlink (embedded git repo), meaning none of the actual Laravel code was being tracked in the parent repo. Fixed it, removed a stale `nul` file blocking `git add`, and diagnosed the `ng not recognized` PowerShell error.

### Tasks Completed
- [x] Audited untracked files for sensitive info before potential push to GitHub
- [x] Diagnosed `backend/` showing as gitlink (mode 160000) — found `backend/.git/` from Laravel installer's auto `git init`
- [x] Fixed embedded git repo: `rm -rf backend/.git`, `git rm --cached -f backend`, `git add backend/`
- [x] Removed `backend/nul` — leftover from a previous bash `> NUL` redirect mistake (already documented in claude.md Learnings). Windows treats `nul` as reserved device name so git couldn't open it
- [x] Diagnosed `ng not recognized` error — Angular CLI installed globally at `C:\Users\mucho\AppData\Roaming\npm\` but that folder not on User PATH
- [x] Added "Fix `ng` not recognized" section to `useful_commands.md` with 3 solutions (npx, global install, manual PATH edit) + backup table of all PATH entries the project needs

### Key Findings
- **Embedded repo / gitlink issue:** Laravel installer ran `git init` inside `backend/`. The parent repo then recorded `backend` as a gitlink pointing to commit `51cd697...` instead of tracking the actual files. **Effect:** pushing to GitHub would have resulted in an empty/broken `backend` entry. **Fix verified:** after deleting inner `.git` and re-adding, `git ls-files --stage backend/composer.json` now shows mode `100644` (regular file).
- **No root `.gitignore` exists** — only `backend/.gitignore` and `frontend/.gitignore`. Anything outside those two folders has no protection. Recommended adding a root `.gitignore` (not yet done).
- **`useful_commands.md` contains demo passwords** (all `password`) — flagged as a minor concern before pushing publicly.
- **npm Windows quirk:** Node.js installer adds `C:\Program Files\nodejs\` to PATH (for `node`/`npm`) but does NOT add `%APPDATA%\Roaming\npm\` (where globally-installed CLIs live). Users must add it manually.

### Files Modified
- `useful_commands.md` — added "Fix `ng` not recognized" section with npx / global install / PATH edit instructions + backup table of all PATH entries

### Outstanding Sensitive-Info Concerns Before Push
- [ ] Create root `.gitignore` to protect against accidental commits of `.env`, IDE configs, OS files
- [ ] Sanitize `useful_commands.md` — either remove demo passwords or move them to a gitignored file
- [ ] Verify `backend/.env` is NOT staged (should be excluded by `backend/.gitignore`)
- [ ] Run a manual scan: `git diff --cached` before any push

### Outstanding Work (carried over)
- [ ] Commit all changes from Sessions 10 + 11 + 13 (Leaflet/w3w map, calendar sync, `calendar_keyword`, photo `file_url` fix, `res.data` batch, seeder, useful_commands additions, backend re-tracking)
- [ ] End-to-end smoke test with both servers (`php artisan serve` + `ng serve` / `npx ng serve`)
- [ ] Verify photo upload after `file_url` accessor fix
- [ ] Test calendar sync flow with a real Google Calendar public iCal URL
- [ ] Test calendar keyword filtering
- [ ] Add file preview components for media (image/video/audio)
- [ ] Get a what3words API key (optional)
- [ ] Permanently fix `ng` on PATH via PowerShell `[Environment]::SetEnvironmentVariable` snippet provided in chat

## Session 14 - 2026-05-21

### Summary
Documentation audit and cleanup session. Updated README.md (was empty), fixed ARCHITECTURE.md (frontend was marked as "Pending" despite 40+ files being implemented), initialized beads issue tracker, and created 6 tracking issues for outstanding work.

### Tasks Completed
- [x] Wrote README.md — project overview, tech stack, quick start, user roles, demo accounts, project structure, API overview, key features, documentation links
- [x] Updated ARCHITECTURE.md — replaced "scaffold only" frontend tree with actual implemented structure, replaced "Planned Frontend Structure" with feature component table, changed status from "Pending" to "Done" with details
- [x] Initialized beads (`bd init`) — previous DB was missing
- [x] Created 6 beads issues for outstanding work (E2E test, photo upload, calendar sync, file previews, root .gitignore, pending commit)
- [x] Updated history.md with this session entry

### Beads Issues Created
| ID | Title |
|----|-------|
| pub-ads-mar-o60 | End-to-end smoke test |
| pub-ads-mar-5uo | Verify photo upload flow |
| pub-ads-mar-un1 | Test calendar sync flow |
| pub-ads-mar-do3 | Add file preview components |
| pub-ads-mar-ajb | Create root .gitignore |
| pub-ads-mar-3sv | Commit all pending changes |

### Files Modified
- `README.md` — full rewrite (was "Nothing here")
- `ARCHITECTURE.md` — frontend tree, feature table, implementation status
- `history.md` — this entry
- `CLAUDE.md` — beads integration section appended by `bd init`

### Notes
- Beads dolt auto-push fails due to missing git auth — issues are saved locally only
- All uncommitted work from Sessions 10-13 is still in the working tree
- Next priority: commit everything, then run E2E smoke test

## Session 15 - 2026-05-31

### Summary
Dockerized the full stack to eliminate host PHP/extension/Postgres-version drift. Triggered by `php artisan serve` failing because composer.lock pins PHP 8.4 packages while host has PHP 8.3.6 with missing ext-dom/xml. Designed the architecture in SDC mode, then scaffolded all Docker files. Build succeeded after fixing Docker credential helper config and regenerating the frontend npm lockfile.

### Tasks Completed
- [x] SDC planning session — wrote full implementation plan to `.claude/plans/dockerize-stack.md`
- [x] Created `backend/Dockerfile` (php:8.4-fpm-alpine + ext-pdo_pgsql/dom/xml/mbstring/zip/gd/bcmath/intl/opcache + Composer)
- [x] Created `backend/docker/entrypoint.sh` (auto `.env`, `storage:link`, optional `migrate`)
- [x] Created `backend/docker/nginx.conf` (Laravel vhost, fastcgi_pass backend:9000)
- [x] Created `frontend/Dockerfile` (node:20-alpine + ng serve with polling for WSL HMR)
- [x] Created `docker-compose.yml` (4 services: db, backend, nginx, frontend; named volumes for vendor/node_modules/pgdata/storage; healthchecks; bridge network)
- [x] Created `.dockerignore` at repo root, `backend/`, `frontend/`
- [x] Updated `backend/.env.example` — DB_CONNECTION=pgsql, DB_HOST=db, DB_PORT=5432
- [x] Updated `useful_commands.md` — Docker-first commands, demoted bare-metal to legacy section
- [x] Updated `ARCHITECTURE.md` — added Docker section to Dev Commands
- [x] Updated `CLAUDE.md` — added Docker Workflow section, marked PG troubleshooting as legacy
- [x] Pre-flight: stopped host PG17 service (`Stop-Service postgresql-x64-17` as Admin), fixed CRLF on entrypoint.sh
- [x] Fixed Docker credsStore error (`sed -i '/credsStore/d' ~/.docker/config.json`)
- [x] Regenerated `frontend/package-lock.json` (chokidar/readdirp drift broke `npm ci`)
- [x] `docker compose build` succeeded for both backend and frontend images

### Beads Issues Created
| ID | Title | Status |
|----|-------|--------|
| pub-ads-mar-q08 | Dockerize backend (PHP 8.4-fpm + extensions + Composer) | in_progress |
| pub-ads-mar-bmx | Dockerize frontend (Node 20 + Angular dev server) | open |
| pub-ads-mar-5q4 | Add docker-compose.yml orchestrating backend/nginx/frontend/db | open |
| pub-ads-mar-ulf | Migrate Postgres data from host PG17 (5434) into containerized db | open |
| pub-ads-mar-44h | Update CLAUDE.md/ARCHITECTURE.md/useful_commands.md for Docker workflow | open |

### Files Created
- `docker-compose.yml`
- `.dockerignore` (root)
- `backend/Dockerfile`, `backend/.dockerignore`, `backend/docker/entrypoint.sh`, `backend/docker/nginx.conf`
- `frontend/Dockerfile`, `frontend/.dockerignore`
- `.claude/plans/dockerize-stack.md`

### Files Modified
- `backend/.env.example` — switched sqlite → pgsql pointing at `db:5432`
- `useful_commands.md` — Docker section added on top, bare-metal kept as legacy
- `ARCHITECTURE.md` — Docker section under Dev Commands
- `CLAUDE.md` — Docker Workflow section added; PG Troubleshooting demoted to legacy
- `history.md` — this entry

### Architectural Decisions
- **Image base:** PHP 8.4-fpm-alpine (satisfies lockfile's symfony 8 / nesbot 3.11 requirement)
- **Port mapping for db:** host `5435` → container `5432` (avoids collision with host PG17 on 5434)
- **HMR:** `--poll 2000` + `CHOKIDAR_USEPOLLING=true` for WSL bind-mount file watching
- **Volumes:** named volumes for `vendor`, `node_modules`, `storage`, `pgdata` (avoid `/mnt/c` I/O penalty + persist across `down`)
- **Bare-metal kept as fallback** — Docker is canonical but legacy commands remain in docs

### Pitfalls Hit & Fixed
- `composer install` failed: host PHP 8.3 vs lockfile PHP 8.4 → Docker solves at runtime
- Docker pull failed with credentials error → removed `credsStore` from `~/.docker/config.json`
- `npm ci` failed: chokidar/readdirp version mismatch → ran `npm install` on host to regen lockfile
- CRLF on `entrypoint.sh` from Windows editor → `sed -i 's/\r$//'`

### Notes
- Stack built successfully but `docker compose up -d` + smoke tests not yet executed at session end
- Next session: bring stack up, verify endpoints (curl /api/me 401, /api/login 200, frontend :4200), run migrations + seed, document any boot-time fixes needed
- Data migration from host PG17 → containerized db is tracked as `pub-ads-mar-ulf` (deferred — start with fresh seeded DB for now)
- Dolt auto-push still failing (no git auth) — beads issues are local-only

## Session 16 - 2026-06-02

### Summary
SDC-mode planning session focused on user-scenario backlog design. The user identified a real UX gap (no link between Explore Spaces and campaign/ad creation, no way to attach a space to an ad in any flow), then drove a multi-round refinement of a v2 backlog. Spawned 4 parallel review agents, then a 5th agent to integrate user comments into a refined version. Output saved to `.claude/plans/cases-v2.md` for review. No production code touched.

### Tasks Completed
- [x] Entered SDC mode; read `~/.claude/agents/sensory_deprivation_chamber.md` for rules
- [x] Read `design.md` to anchor on existing terminology and original Case 1 narrative
- [x] Proposed v1 of 14 user scenarios across 5 roles
- [x] Spawned 3 parallel Explore agents to review v1 from UX, schema, and RBAC perspectives — consolidated into 28-scenario expansion proposal
- [x] Spawned 4 parallel Plan agents in a second round: (a) make 3 aspirational scenarios testable, (b) design proof-of-display 5-day SLA lifecycle, (c) redesign conversations as polymorphic threads, (d) decide cases.md organization (role vs object vs journey)
- [x] Synthesized agent outputs into a coherent v1.5 plan (price negotiation schema, ad rotation Option B, proof state machine, polymorphic conversations, hybrid-axis cases doc)
- [x] Refocused per user feedback onto pure narrative backlog stories (no schema/API talk)
- [x] Integrated user edits: removed all price-negotiation language; expanded provider "List a space" to require both physical and media-delivery specs; added dedicated notification/alert schema covering all refund-adjacent events
- [x] Spawned a 5th Plan agent to produce v2 with user comments baked in
- [x] Saved v2 to `.claude/plans/cases-v2.md` (~480 lines)

### Key Design Decisions Locked In
- **Prices are fixed** — no negotiation, no counter-offers. Provider queue actions are Approve / Reject only. (Reverses earlier `booking_offers` schema plan from this session.)
- **Conversations become polymorphic** — `conversation_subjects(conversation_id, subject_type, subject_id, role)` + `conversation_participants` (multi-party). A thread = identity; objects (space/booking/ad/ticket/campaign/adset) are attached metadata. Kind stored explicitly: `inquiry | booking_chat | support_ticket | dispute`. Same thread can travel inquiry → booking → ticket.
- **Proof-of-display 5-day SLA** — primary vs secondary proof distinction. Provider uploads PRIMARY (only this triggers payout). Client collaborators upload SECONDARY (verifications and mismatch flags). Day-3 / day-4 reminders. Day-5 auto-cancel + refund + strike. 3 strikes / 90 days → admin flag for freeze review. Implemented via `php artisan proofs:enforce-deadlines` daily cron.
- **Collaborator clarification** — design.md contradiction resolved: providers (not collaborators) own primary proof. Collaborators only file secondaries and mismatch flags. Verbatim paragraph drafted for design.md.
- **Ad rotation** — Option B (`ad_rotation_slots` with time-sliced weighted slots), keeping `bookings.ad_id` as default for backward compat. Time-sliced rotation needed; junction table alone (Option A) loses that.
- **Media-delivery specs per space type** — provider declares TWO sets: physical specs (size/resolution/frequency) AND media-file specs the client must conform to (PDF/X-1a, CMYK, 150 DPI, 1cm bleed, −14 LUFS, etc.). Validated at upload time with hard-fail and concrete error message.
- **Notification & alert schema** — 28-row table covering every refund-adjacent event (cancel pre/post-approval, provider cancel, auto-cancel, refund issued/failed, dispute opened/resolved, payout held/released, strike accrued, 3-strike alert) plus non-refund events. SMS added for day-4 reminder, provider-cancel, refund-failed, auto-cancel.
- **Cases doc structure** — hybrid axis: journey-primary, role+object+testability as tags. ID scheme `SC-NN-slug` (single namespace). 5×8 coverage matrix at the end for completeness verification.
- **PII filter becomes kind-aware** — strict in `inquiry`; relaxed in `booking_chat`; relaxed when Support/Payments joins (system message announces it).

### Files Created
- `.claude/plans/cases-v2.md` — full v2 backlog (15 client + 10 provider + 5 support + 5 payments + 5 admin scenarios; 3 cross-cutting sections incl. the 28-row alert table + media-specs table; 9 journeys; diff summary vs v1)

### Files Modified
- `history.md` — this entry

### Pitfalls Hit
- First pass tried to organize scenarios by actor only (Agent 3's recommendation) and missed end-to-end journey readability. Second pass argued by object only and hid the actor. Resolved with hybrid.
- v1 included three aspirational scenarios (price negotiation, media-type validation, ad rotation) that had no schema backing — flagged by data-model agent. User then deprecated negotiation entirely, simplifying scope.
- Initial conversation model assumed booking-tied threads; user correctly reframed conversations as polymorphic identity-first.
- Original design.md had a contradiction between "providers take proof photos" and "clients can add collaborators to upload proofs." Resolved as primary/secondary split.

### Open Questions for Next Session
- Should `cases-v2.md` be promoted to a canonical `cases.md` at repo root, or stay in `.claude/plans/`?
- Need a `design-clarifications.md` patch to fold the proof-collaborator clarification + the no-negotiation decision back into design.md.
- Technical-plan companion file (`*-technical-*.md`) for the schema migrations implied by v2: drop negotiation work, keep proof lifecycle + polymorphic conversations + ad rotation + media-spec storage on spaces.
- Red-team v2 for missing edge cases (collaborator declines invite, SMS provider outage, partial refund policy %, etc.).

### Beads Issues
- No new beads issues created this session (planning only; user has not asked to file work yet).
- Still open from session 15: pub-ads-mar-ulf (Postgres data migration into containerized db).
- Pending docker stack verification (todo items 10-12) carried over.

### Notes
- Session was entirely SDC-mode — no source code edited.
- Plan file (`cases-v2.md`) is the deliverable; user signed off on saving it for review.
- Next concrete action will likely be either red-team round, or `design.md` clarification patch, or filing beads issues for the v2 work (proof lifecycle, polymorphic conversations, media specs, alert schema, ad rotation).

## Session 17 - 2026-06-04

### Summary
SDC-mode planning session that pivoted `cases-v2.md` from a design.md replacement into a **compliance lens** and produced two new companion plan files that map every scenario against the canonical design.md. The header was rewritten: cases-v2 no longer "supersedes" design.md — instead design.md must be able to support every story, and gaps are tracked separately. Several client scenarios were expanded with new product detail, and a full gap analysis + proposed design.md additions were written. No production code touched.

### Tasks Completed
- [x] Reframed `cases-v2.md` status from "supersedes the v1 narrative in design.md" to "does NOT supersede design.md; isolated scenario backlog used as a compliance lens"
- [x] Expanded 5 client scenarios in `cases-v2.md` with new product behavior (details below)
- [x] Authored `.claude/plans/design-md-gap-analysis.md` — per-story compliance matrix classifying each cases-v2 story as COVERED / PARTIAL / SILENT / CONFLICT against verbatim design.md text
- [x] Authored `.claude/plans/design-md-proposals.md` — proposed module list, role-interaction matrix, paste-ready verbatim insert paragraphs, and 48 owner-only open questions

### Scenario Expansions Added to cases-v2.md (client)
- **#2 Date-flexibility filter** — ±X days tolerance window (e.g. ±10 days) on top of the date-range filter; greyed out when no date range is set.
- **#3 Provider star rating** — 1–5 stars + total count on the space detail; average of per-campaign ratings; only clients can rate, only after a campaign with that provider ended OR ran ≥30 days; one rating per client per campaign; optional comment with abuse-flagging.
- **#5 Orphan spaces list** — campaign view surfaces backlog spaces not yet in any adset; orphans are bulk-movable but cannot reach checkout; one-click "Move all to new adset."
- **#6 Inline provider upload instructions** — provider's media-delivery summary + free-text note shown next to the upload control (not in a tooltip), persisting through and after upload so the client can compare against the file sent.
- **#8 Pre-pay waiting list** — for "book it for later" ads, client may Pre-pay; funds captured into escrow and entered on the provider's per-space pre-pay waiting list; provider picks which offer to confirm when the slot opens; unfilled offers refund to wallet credit at expiry. Non-pre-pay entries stay queued (no funds) and resolve first-come-first-served.

### Files Created
- `.claude/plans/design-md-gap-analysis.md` (~150 lines) — compliance matrix across CLIENT (15) / PROVIDER (10) / and other roles; "biggest gaps" summary (Media-Delivery Spec Validator, 28-event Notification schema, proof upload/rejection workflow, PII masking, collaborator-scope CONFLICT); plus the "5-yo architect" challenger questions (But why? / What if? / Who owns this?).
- `.claude/plans/design-md-proposals.md` (~292 lines) — proposed module list (extends Campaign/Adset/Ad/Space with Geo Search, Calendar & Availability, Media-Delivery Spec Validator, Notification & Alert Dispatcher, etc.), role-interaction matrix, verbatim paste-in paragraphs, and 48 numbered open questions (Q1–Q48) spanning messaging, calendar, notifications, media specs, RBAC/freeze, geography/scope, and naming/terminology.

### Key Decisions / Reframes
- **design.md stays canonical.** cases-v2 is a backlog used to pressure-test design.md, not a replacement — reverses Session 16's "supersedes design.md" stance.
- **Collaborator scope is a known CONFLICT** — design.md says collaborators only "upload proofs"; cases-v2 grants review + chat (no billing). Flagged for owner resolution, not silently changed.
- Gap work is kept in three coordinated files: backlog (`cases-v2.md`) → gap matrix (`design-md-gap-analysis.md`) → proposed fixes (`design-md-proposals.md`). design.md itself is untouched until the owner approves.

### Open Questions for Next Session
- Owner to answer Q1–Q48 in `design-md-proposals.md` (especially the CONFLICT items: collaborator scope, proof ownership, "day 5" anchor, where escrow funds sit).
- Promote the proposed verbatim inserts into design.md once questions are resolved.
- Decide whether `cases-v2.md` graduates to a canonical `cases.md` at repo root (carried over from Session 16, still open).
- File beads issues for the implied work once design.md is reconciled (proof lifecycle, polymorphic conversations, media specs, alert schema, ad rotation, ratings, pre-pay waiting list).

### Beads Issues
- No new beads issues created (planning only).
- Still open from earlier sessions: pub-ads-mar-ulf (Postgres data migration into containerized db); docker stack verification carried over.

### Notes
- Entirely SDC-mode — no source code edited; deliverables are the three plan files under `.claude/plans/`.
- Dolt auto-push still failing (no git auth) — beads issues remain local-only.

## Session 18 - 2026-06-08

### Summary
Continued the SDC-style planning thread on the user-scenario backlog and the canonical design. Three phases: (1) folded all 21 bracketed `{...}` author comments in `cases-v2.md` into the scenario prose; (2) ran a 4-agent reconciliation team that produced a 25-question gap analysis and surfaced 6 hard conflicts between `cases-v2.md` and the canonical `design.md`; (3) after the user resolved all 6 conflicts and directed copying cases-v2 logic into design.md, applied the decisions + appended ~17 canonical subsystem sections, then ran a 3-agent review team (congruency / architecture critique / risk) and folded their load-bearing recommendations into a new ARCHITECTURE NOTES block. `design.md` grew 64 → 368 lines. No production code touched.

### Tasks Completed
- [x] Folded 21 bracketed author comments in `cases-v2.md` into scenario prose (client/provider/support/payments/admin + cross-cutting); only `{template}` placeholders remain in the alert table
- [x] Spawned 4-agent reconciliation team (CLIENT / PROVIDER / SUPPORT-PAYMENTS-ADMIN / cross-cutting) checking each story against design.md
- [x] Wrote `.claude/plans/design-md-reconciliation-questions.md` — 25 questions, 6 hard conflicts, silent-subsystem list, undefined-value list
- [x] User resolved all 6 hard conflicts; applied each as a targeted edit to design.md's top prose
- [x] Appended ~17 "CANONICAL SUBSYSTEMS" sections to design.md (collaborator roles, booking/proof lifecycle, strikes, wallet/refunds, pre-pay/escrow, ratings, media specs, catalog/search, chat/PII, internal threads, notifications, audit, rate limits, configurations, moderation/freeze, legal docs, calendar alerts)
- [x] Spawned 3-agent review team (congruency audit + architecture opinion + adversarial risk review)
- [x] Fixed defects they found: payout trigger (Payments-review gate + 48h re-upload flow), provider-freeze aligned to journey #7, 6 missing notification rows added, Admin health dashboard added
- [x] Folded architecture recommendations into a new design.md "ARCHITECTURE NOTES (data model & integrity)" section
- [x] Appended ROUND 2 section to the reconciliation-questions file (fixes applied + Q26–Q31 still open + condensed architecture verdict)
- [x] Filed 5 beads issues for follow-up work

### Key Decisions Locked In (the 6 conflicts)
- **Collaborators** get owner-configurable roles (permission sets chosen by the account owner/team; never billing). **Provider owns the PRIMARY proof**; collaborators file secondaries + flags.
- **Admin = eagle-eye** — sees everything incl. internal Support↔Payments threads.
- **Proof deadline** is Admin-configurable (default 5 days) via a System Configurations menu, not hard-coded.
- **Provider delete** allowed only with no open ticket and no booking in progress; otherwise unpublish and wait for current bookings to end.
- **File acceptance** — automated upload-time validation AND manual provider approval coexist; passing validation does not guarantee acceptance.
- **Money powers** — Support only FLAGS a refund/hold; Payments DECIDES and EXECUTES. Strike accrual is not a Payments concern (routes Support→Admin).

### Architecture Verdict (3-agent team)
Sound and buildable on Laravel+Angular+Postgres; the separation-of-powers model is well designed. **Biggest gap is the schema lagging the design** — ARCHITECTURE.md still has a 3-role user enum, 4-state bookings, and no proofs/wallet/escrow/strike/audit/notification/ratings/collaborator tables. Money must be built on an append-only ledger (MXN centavos) with idempotency + transactional escrow before real payments ship. ARCHITECTURE NOTES now codifies: wallet ledger, refund/payout idempotency, `display_start` anchored to calendar start in America/Monterrey tz, strikes decoupled from notifications + reversible, Postgres `EXCLUDE` against double-booking, per-account collaborator grant overlay, `flag`-vs-`execute` capabilities, separate-row internal threads, DB-level audit immutability.

### Files Created
- `.claude/plans/design-md-reconciliation-questions.md` — 25 Round-1 questions + ROUND 2 (fixes applied, Q26–Q31 open, verdict)

### Files Modified
- `design.md` — 6 conflict edits + ~17 canonical subsystem sections + ARCHITECTURE NOTES (64 → 368 lines)
- `.claude/plans/cases-v2.md` — 21 comments folded in; strike-alert recipients fixed; 6 notification rows added; freeze alert template updated
- `history.md` — this entry

### Beads Issues Created (this session)
- `pub-ads-mar-7r7` (P1) — Resolve open design owner-decisions (Q26–Q31 + Round-1 values)
- `pub-ads-mar-h6a` (P1) — Schema: money subsystem (wallet ledger, escrow, idempotency)
- `pub-ads-mar-4ri` (P1) — Schema: 5-role user enum + canonical booking/proof state machines
- `pub-ads-mar-2r6` (P2) — Schema: new subsystem tables (strikes, ratings, audit, notifications, collaborator overlay, media specs, conversations)
- `pub-ads-mar-8qi` (P2) — Update ARCHITECTURE.md to match design.md canonical subsystems

### Open Questions for Next Session (Q26–Q31)
- Q26 wallet clawback policy; Q27 config-change effect on in-flight bookings; Q28 pre-pay cross-queue precedence (funded vs older FCFS); Q29 re-upload clock during dispute; Q30 re-upload loop bound; Q31 SMS provider choice/defer. Plus residual values: refund-tier %, escrow expiry, rate-limit thresholds, audit retention, ratings comment visibility, date-flexibility semantics.

### Pitfalls Hit
- Folding the "strikes never Payments" comment created an internal contradiction in the cases-v2 notification table (rows still listed Payments / omitted Support) — caught by the review team and fixed.
- Promoting the cases-v2 alert table to "canonical" in design.md while it was still missing 6 events — fixed by adding the rows.
- Repeated transient `ENOENT`/"file modified since read" errors on `design.md` edits (OneDrive sync); edits still landed — verified each via grep.

### Beads / Git Notes
- `bd create`/`bd dep add` succeed locally but the auto-push to GitHub fails ("could not read Username for https://github.com") — beads remain local-only, consistent with prior sessions.
- Recommended next concrete step: reconcile ARCHITECTURE.md schema to design.md, starting with the money/ledger tables and the canonical booking/proof state enums (issues 4ri, h6a).

## Session 19 - 2026-06-12

### Summary
Closed out the design.md ↔ cases-v2.md reconciliation. The owner answered the
final ROUND 3 open decisions (O1–O24) in brackets across two turns; I applied all
24 to `design.md`, cleared every remaining `OWNER-DECISION` marker, and appended a
ROUND 4 closure section to `design-md-reconciliation-questions.md`. **No design
questions remain open** — the doc is fully reconciled across Rounds 1–4. Three
answers were logic-applied per the owner's "don't ask trivial questions" directive
(O5 cheapest-tier pricing, O8 per-ad go-live, O9 no geotag); O21 SMS got a
recommended default (Twilio/Vonage). `design.md` grew to 517 lines. No production
code touched.

### Tasks Completed
- [x] Applied O1–O24 owner decisions to `design.md` (pricing tiers, per-ad
  go-live, payout/gateway-fee/clawback rules, pre-pay precedence, re-upload
  bound, no-geotag, config-apply checkbox, removed date-flexibility filter,
  orphan checkout rule, radio map-range, calendar offline fallback + staleness
  clock, inquiry→booking transition, masked-thread full history, ticket-close
  semantics, street-address whitelist, strike appeal via Support, SMS provider,
  Monterrey launch scope)
- [x] Cleared all 3 remaining `OWNER-DECISION` markers in ARCHITECTURE NOTES
  (clawback, config effect, cross-queue precedence) → RESOLVED
- [x] Added a "Launch scope" block (Monterrey/Mexico-first, MXN centavos,
  America/Monterrey tz)
- [x] Appended ROUND 4 closure to `design-md-reconciliation-questions.md`
- [x] Appended this history entry

### Pitfalls Hit
- Recurring OneDrive `ENOENT`/"file modified since read" on `design.md` edits
  (known from Sessions 16/18). Two edits that reported ENOENT actually landed —
  and a couple landed TWICE; caught the duplicate "Go-live is PER-AD" and
  "Street-address whitelisting" blocks via grep and removed them. Lesson:
  grep-verify after every ENOENT instead of blindly retrying.

### Open Items / Next Session
- Design is done; the next concrete step is SCHEMA work, not design. Beads
  issues `4ri` (5-role enum + booking/proof state machines), `h6a` (money
  ledger/escrow), `2r6` (new subsystem tables), `8qi` (update ARCHITECTURE.md).
- `bd` CLI is not installed in this shell and `.beads/issues.jsonl` export is
  stale (Apr 29) — could not verify/update beads live. Dolt/git push still
  blocked (no auth), consistent with prior sessions.
- Carried over: whether `cases-v2.md` graduates to a canonical `cases.md`.

### Notes
- Planning-only session; deliverables are `design.md` (fully reconciled) and the
  Round-4 closure in `design-md-reconciliation-questions.md`.

## Session 20 - 2026-06-13

### Summary
MVP push toward a complete local version against a 13-item checklist. Diagnosed and
fixed beads (was on an unreadable dolt backend), ran a one-pass agent build team that
got 64 files in before aborting (parallel writes collided with the user's open IDE
files + 4 packets failed), checkpointed that work on a `mvp-build` branch, then
verified it statically and finished the one real gap (C03/C13 search→campaign frontend)
by hand. Runtime verification is BLOCKED because PostgreSQL is down.

### Beads diagnosis + fix
- `bd.exe` v0.49.3 (dev, no CGO) cannot open the configured **dolt** backend; it had
  only worked while a background sqlite daemon (dead now) served it over a socket.
- Fix: re-init to **sqlite** (`metadata.json` backend=sqlite, `bd init --backend sqlite`),
  23 issues restored from JSONL. Session-18 dolt-only issues were lost; recreated 5 fresh
  MVP issues (nez/5gk/4v7/wl7/c9x). Old `.beads` (incl. dolt) backed up at `.beads-backup-20260613/`.

### Build team (one-pass workflow, PARTIAL)
- 8 read-only audits → consolidation into file-partitioned packets → shared-core →
  parallel implementers → docsync → verify. Aborted mid-run (AbortError; 21 agents,
  ~919k tokens). 4 implement packets failed but most output landed coherent.
- Landed (php-lint clean): new controllers ConfigurationController, OversightController,
  BacklogController, ProofFlagController, WalletController; models SystemConfiguration,
  WalletEntry; PiiMaskingService; 7 migrations (thread cols, collaborators.role→string,
  ads.adset_id nullable + campaign_id, spaces.calendar_synced_at, wallet_entries,
  system_configurations, bookings.config_snapshot); plus wired routes/RBAC/models/Angular.
- Checkpointed as commit `60741f0` on branch `mvp-build`.

### Verification (static — DB down, no runtime test)
- `php -l` clean across all changed PHP; 95 routes all resolve to real Controller@method;
  model helpers/relations/columns all present; `ng build` SUCCESS (exit 0).
- Logic spot-checks passed: auto-ticket on proof flag (C09), render-time PII masking +
  space-address whitelist (C06), admin eagle-eye no-filter reads incl is_internal (C11),
  config apply-scope new_only|all + in-flight snapshot (C12), idempotent wallet refund/
  payout (C10), collaborator subroles installator/publicist/manager (C02), ticket-from-
  object (C04).

### Manual finish
- GAP found: C03/C13 frontend `space-search.component.ts` had single-select only and no
  "add to campaign". Added (by hand, sequential) a selection basket + "Add to new/existing
  campaign" wiring the existing backend backlog/orphan endpoints. `ng build` re-verified.

### Tasks Completed
- [x] Diagnose + fix beads (dolt→sqlite); recreate MVP issues
- [x] JSON todo tracker (`.claude/todos/mvp-sprint.json`) + change log (`.claude/mvp-changelog.md`)
- [x] One-pass agent build team (partial) + checkpoint commit on `mvp-build`
- [x] Static verification (php -l, route/method, model wiring, ng build)
- [x] Build C03/C13 frontend accumulate→campaign flow by hand
- [x] Reconcile ARCHITECTURE.md (agent docsync)
- [x] Remove `design-md-reconciliation-questions.md` (decisions folded into design.md; refs scrubbed)
- [x] Add 4 entries to CLAUDE.md Learnings (beads/CGO, parallel-write conflicts, OneDrive ENOENT, PG-down)

### Pitfalls Hit
- Parallel agents writing files the user had OPEN in the IDE → "content is newer" save
  conflicts + 4 packet failures. Lesson now in CLAUDE.md: write sequentially, log changes.
- Beads dolt/CGO trap (above).
- OneDrive transient ENOENT on edits (some landed twice; deduped).

### Blocker / Next Session
- **PostgreSQL is DOWN** (port 5434 refused; no Docker in WSL). To finish runtime verification:
  start PG17 (or Docker Desktop), then `cd backend && php artisan migrate` (7 additive migrations)
  + `php artisan db:seed`, then exercise the 13 flows. Until then the agent-written controllers
  are runtime-UNVERIFIED.
- Work lives on branch `mvp-build` (commit `60741f0` + follow-ups); `main` is the clean baseline.
  Not pushed (git auth still blocked).

## Session 21 - 2026-06-13 (continuation)

### Summary
Docker brought up as project "publisher"; ran a read-only verification agent team that
returned a precise blocking/major/minor bug list; applied all of it by hand (sequential,
no parallel writes). Saved mid-way for crash safety.

### Docker
- docker-compose.yml renamed to project **publisher** (publisher_db/backend/nginx/frontend).
- WSL `docker` socket is root:docker and muchored isn't in the docker group; use Windows
  `docker.exe` at "/mnt/c/Program Files/Docker/Docker/resources/bin/docker.exe" which bypasses it.
- db container UP and healthy. Backend/frontend image build must run in FOREGROUND (or by the
  user in PowerShell) — backgrounding the docker.exe build makes BuildKit cancel ("context canceled").
- Backend auto-migrates on boot (MIGRATE_ON_BOOT=true); compose env (DB_HOST=db) overrides host .env.

### Read-only verification team (7 agents) → fixes applied
Verdict "Go, conditionally". Bug class: Angular↔Laravel response-shape boundary + 3 RBAC/schema gaps.
- Backend: client proofs perm (B1); tickets.ticketable nullable migration (B2); Support index
  get() not paginate (B3); Proof file_url accessor (B4); RolePermission +configurations/+refund (M6);
  register returns permissions (M7); ProofFlag ownership guard + notes preserve (M11).
- Frontend: payment approve/reject res shape (M8); wallet centavos (M9); campaign-detail create
  handlers res shape + collaborator role field (M10); proof-review uploaded_by (M12); NEW orphan
  backlog UI + move-to-adset (B5); oversight ticketable_type (minor).

### Status / Next
- All php -l clean; `ng build` re-verify pending (next step). Then bring up full stack + walk 13 flows.
- Work on branch `mvp-build`; not pushed (git auth blocked). DB up via Docker "publisher".
- Detailed per-fix log in `.claude/mvp-changelog.md` STEP 6.

<!-- Add new sessions above this line -->

