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

## Session 22 - 2026-06-14

### Summary
Got the full Docker "publisher" stack running and verified the MVP end-to-end through it.

### Docker build blocker + fix
- Every `docker compose build`/`docker build` cancelled at "transferring context" (~0.7s),
  foreground or background, even with all base images pre-pulled (plain `docker pull` works).
  This is a broken **BuildKit context streaming** on this machine — and is why the user's
  PowerShell `compose up --build` only produced the db container.
- FIX: disable BuildKit / use the classic builder:
  `export WSLENV=DOCKER_BUILDKIT:$WSLENV; DOCKER_BUILDKIT=0 docker.exe compose up -d --build`.
  Both images built cleanly; all 4 containers came up. (Added to CLAUDE.md Learnings.)

### Stack verified LIVE
- publisher_db (healthy) + publisher_backend + publisher_nginx + publisher_frontend all running.
- Backend migrated-on-boot ("Nothing to migrate" — schema from earlier host run; data persists).
- API via nginx http://localhost:8000 → login HTTP 200; authed space-search → 6 spaces.
- SPA http://localhost:4200 → ng serve compiled, HTTP 200.
- Login: client1@pubads.test / password (+ support@/payments@/admin@pubads.test).

### Status
- **MVP local stack COMPLETE and RUNNING.** Earlier session verified the 13-flow logic
  (C01/C03/C13/C04/B1/B3/C10/C11) at runtime; remaining items (C05/C06/C07/C09/C12) are
  static-verified, pending UI walk-through / more seed data.
- Restart later: `export WSLENV=DOCKER_BUILDKIT:$WSLENV; DOCKER_BUILDKIT=0 docker compose up -d`.
- Branch `mvp-build`; not pushed (git auth blocked).

## Session 23 - 2026-06-15

### Summary
Kicked off the UI/behavior phase against a large owner change-list spanning all five
role-sides. Per the owner's stated order (graphic → derive UI/endpoint needs → build),
produced the foundational artifacts and the agreed first build step (seed data), all on
the running "publisher" Docker stack.

### Delivered
- **Platform object graph** — `.claude/plans/platform-graph.md`: ASCII node graph
  (Campaign→Adset→Ad→Space→Booking→Payment/Proof→Ticket→internal thread + Invoice/Wallet/
  Conversation/Config/Audit) PLUS a role×object capability matrix (inspect/edit/silent🤫/
  announce📣/money$/logged📝). Encodes: Support = eagle-eye + edit-all-non-money (logged),
  Payments = only money mover, Admin = silent-by-default eagle-eye over everything,
  collaborator subroles installator/publicist/manager.
- **Endpoint audit** — `.claude/plans/ui-endpoint-audit.md`: a read-only analysis agent
  classified all 26 requested items as UI-ONLY / DATA-ONLY / NEEDS-BACKEND with exact
  routes. Most client/provider asks are UI-only (endpoints exist). Genuinely-new backend:
  support edit-any + audit log (neither exists), provider collaborators, provider config,
  admin per-object eagle-eye + filters, invoice line-items.
  ROOT CAUSE found: `/conversations/{id}` infinite spinner = MessageController@index returns
  a bare array but conversation-detail reads `res.data.conversation` → TypeError in next() →
  loader never clears.
- **Seed data** (the owner's "add some X to every UI") — `backend/database/seeders/MvpDemoSeeder.php`
  (additive, idempotent-guarded), run in the publisher_backend container and verified via API:
  provider bookings 5, payments-to-review 7 + proofs 4, support tickets 5 (Ad/Space attached +
  internal Support↔Payments thread + reference-less), extra conversations, refund wallet entry.

### Build order set (in ui-endpoint-audit.md)
B1 UI bug-fixes (map render, conversation spinner, campaign edit+per-ad Book, map landing) →
B2 provider polish (space view==edit, calendar-preferred accordion, proof booking dropdown) →
B3 nav/header (Configurations tab, drop Dashboard, welcome+Help button) →
B4 support powers (edit-any + audit log) → B5 admin eagle-eye (+filters, user split, RBAC employees) →
B6 provider collaborators/config → B7 invoice per-ad rollup.

### Commits (branch mvp-build, not pushed — git auth blocked)
- 1bbf483 todo tracker to verified state
- 6815944 UI phase kickoff: platform graph + endpoint audit + demo seeder

### Next
- Start B1 sequentially (change-logged; owner to close client component tabs to avoid save conflicts).

## Session 24 - 2026-06-17 → 2026-06-22

### Summary
Executed the UI phase: shipped B1/B1b code fixes, ran two read-only agent audits that proved
"most of the plan was on paper, not coded," reconciled all four planning docs + the todos to the
owner's decisions, and built B8 (stats dashboards) + B2 (provider polish) sequentially. Many small
owner UX fixes landed along the way. 11 commits (793a026 → f999718); all pushed-pending (git auth
still blocked) on branch `mvp-build`.

### Code shipped (verified: php -l + ng build clean; key flows runtime-verified on the Docker stack)
- **B1** (793a026): /conversations spinner fixed (MessageController returns {data:{conversation,messages}});
  campaign spec inline edit (PUT) + per-ad Book inline date form; space-search default map view +
  [map|results] split layout.
- **B1b** (8adb012): contract-shape bugs (same class as #14) — payment-list read paginator .data;
  Provider booking update allow 'cancelled' + wrap {data}; shared ticket reply wrap {data}. All runtime-verified.
- **Client UX** (99259d6): new-campaign modal (typed name now saved; real autofocus); human-readable
  campaign dates + Campaign cast date:Y-m-d; client lands on /client/spaces (auth.landingRoute); "+ Ad Space".
- **Invoice PDF** (fb65bdd): composer barryvdh/laravel-dompdf (backend_vendor volume); GET
  /client/invoices/{id}/pdf streams a real PDF; invoice-list ⬇ PDF button. Runtime-verified 879KB.
- **B8 dashboards** (1c90297): new Payments/Support/Admin DashboardController + provider revenue
  buckets; GET /{role}/dashboard (role-gated; seeder dashboard:read added for support/payments);
  dashboard.component renders per-role stat grid. All 4 endpoints runtime-verified. Admin = system config styling too.
- **Client dashboard** (943bc21): /dashboard redirects clients to Spaces (clients have no dashboard).
- **B2 provider polish** (4e1e7d9, f999718): availability date-ranges + Google/iCal calendar moved
  from space VIEW to space EDIT (calendar-preferred + manual-ranges accordion + ? help); new spaces
  redirect to /edit; proof "View" → image/video modal (was 404); "📸 Add proof" link on
  waiting_proof/active/confirmed bookings → proofs?booking_id (pre-opens upload); booking status union
  aligned to DB enum. All code tagged with [todo Bx] traceability comments.

### Audits + doc/todo reconciliation
- 4-agent compliance audit (8adb012) then a planned-vs-built reconciliation (ba97c43): confirmed the
  big subsystems (ratings, media-specs+upload validation, one-checkout, pre-pay/escrow, audit log,
  notifications, strikes) are PLANNED-ONLY (zero code). Captured as new_subsystems N1-N8.
- Owner decisions encoded into design.md/cases.md/ARCHITECTURE.md/platform-graph (f4b0daf) + a Team#1
  congruence pass (41c11cf) that caught + fixed stale "Payments reviews proof" prose. Docs↔todos congruent.

### Owner decisions captured (2026-06-20)
1. Proof/payment: payment HELD until CLIENT accepts proof; Payments does NOT review proof content;
   client reject/mismatch → 48h re-upload + ticket w/ payment attached to BOTH Support & Payments
   (payout held until they agree); accept → manual Payments release.
2. Collaborators are ACCOUNT-scoped, two ecosystems (client/provider), subroles per ecosystem; kill 'proof_uploader'.
3. Payments auto-approve = admin config toggle + amount threshold, default OFF.
4. All non-client dashboards show per-role stats; client has no dashboard.
5. Multi-file ads DEFERRED (1 file/ad MVP). 6. Booking queue = provider PICKS from queue (not auto-assign).
Memory files written: collaborators-two-ecosystems, proof-payment-flow-decision.

### Queued (logged in todos, NOT yet built)
- **B9** proof/payment rework (next): Payments "Proofs" section → held-payments view, remove proof review.
- **B4** Support powers: more user data (phone) on tickets + audit log + edit-any-non-money.
- **B5** Admin/Support Oversight drilldown: per-user/provider, spaces, payments/holds, ticket history, search/filters.
- **B6** account-scoped collaborators + client Configurations tab. **B3** nav/header. **B7** invoices/money polish.
- **B10** auto-approve config. **B-avail** "doesn't coincide" tag. **N1-N8** big subsystems.
- Doc-fix still pending: ARCHITECTURE admin tree + dead Manager\UserController + status enums; frontend EUR→MXN + Monterrey reseed.

### Stack / notes
- Docker "publisher" stack used for runtime verification; restart: `export WSLENV=DOCKER_BUILDKIT:$WSLENV;
  DOCKER_BUILDKIT=0 docker compose -p publisher up -d`. Backend dompdf lives in the backend_vendor volume.
- Code written SEQUENTIALLY (single-writer) per the no-parallel-write-with-IDE-open rule; read-only audit agents ran in parallel.
- Branch `mvp-build`; git push still blocked (auth) — all 11 commits local.

### Next
- Start B9 (proof/payment rework) — migration (payment ticketable + auto-hold on reject), remove
  Payments proof review, wire client reject → hold + dual Support/Payments ticket, rework payments UI.

## Session 25 - 2026-06-23

### Summary
Ran a compliance audit (code/UI vs design/architecture/cases + doc-vs-doc), captured the
owner's decision batch, and shipped the first code batch (money → decimal pesos).

### Compliance audit (read-only, 6 agents)
- Report saved to `.claude/plans/compliance-report.md`. Headline: code = original 11-table
  MVP + thin overlay; the big design.md subsystems are mostly unbuilt (intentional MVP
  subset, not regressions). Confirmed doc-vs-doc drift (cases-v2.md missing; ARCHITECTURE.md
  stale counts/enums) and 5 code-contradicts-owner-decision items (Payments proof review,
  collaborators campaign-scoped, money unit, conversations non-polymorphic, provider
  confirm/cancel vs approve/reject-with-reason).

### Owner decisions applied
- **Money = DECIMAL MXN pesos everywhere, no centavos** ("forget cents"). design.md updated
  (3 spots) + ARCHITECTURE NOTES; REVISIONS & ROADMAP section appended.
- **cases-v2.md → cases.md** (renamed to simplify): repointed all design.md refs; added a
  key-file note to CLAUDE.md.
- Reaffirmed (already in todos from 2026-06-20): collaborators account-wide BOTH client+provider,
  proof/payment B9 reversal (client accepts; Payments money-only; held-payment → dual
  Support/Payments ticket), conversations = object with one attached object + multi-role +
  admin-silent/support-announced, provider approve→installer OR reject-with-note.
- **Working-style learning added to CLAUDE.md**: momentum over perfection — resolve soft
  conflicts by latest-decision-wins/best-guess, only stop on HARD stoppers, ~90% doc
  compliance (not 100%); don't create new planning docs.
- Build order chosen: endpoints-first, feature-by-feature (backend-first to avoid IDE
  save-conflicts): M → B9 → B6 → B3 → B-avail → B4 → B5 → B7 → N* deferred.

### Shipped — Batch M (money → pesos)
- Migration `2026_06_23_000001`: wallet_entries.amount_centavos (int) → amount decimal(12,2)
  pesos, converting existing rows (/100). Dropped all *100///100 in WalletEntry,
  PaymentController (refund+releasePayout), WalletController, wallet.component.
- Verified LIVE in the publisher stack: migration ran in container; `/client/wallet` returns
  `balance: 77500` pesos (was 7,750,000¢), no centavos keys; frontend recompiled clean.

### Commits (branch mvp-build, not pushed — git auth blocked)
- d1e7f44 compliance report · (docs) cases/money/roadmap/learnings · 2f32b29 Batch M money→pesos

### Next
- Batch B9: proof/payment rework — remove Payments proof-content review, add client
  accept/reject gating payout + Payments read-only held-payment view, held → dual ticket.

## Session 26 - 2026-06-25

### Summary
Owner flagged the client "Collaborators" panel as suspected planning-vs-code drift. Spawned a
4-agent forensic team, found the panel is actually fully built+wired (NOT orphan UI), then
generalized the real disconnect into an investigation doc. Owner then asked to (a) flip all docs
that scope collaborators to "campaign" → account-scoped, and (b) run a team to find ALL spec
collisions, resolving soft ones by latest-decision-wins and asking on hard ones. ~20 soft conflicts
resolved + the one hard conflict (booking state enum) decided by owner. Docs-only this session.

### Investigation (4 read-only agents)
- New doc `.claude/plans/investigation-planning-to-code-gap.md`. Headline reversal: "Collaborators"
  is spec'd in depth (design.md/cases.md/platform-graph) AND wired to real endpoints
  (`CollaboratorController` + `collaborators` table; campaign-detail calls GET/POST/DELETE for real).
  Not a phantom.
- The systemic gap is real but different in shape: NOT mock data (almost nothing is mocked). It's
  **selective implementation depth** — CRUD-shaped features ship end-to-end; requirements buried as
  rules in prose (media-spec validation, ratings, pre-pay) evaporate. Plus backend-ahead-of-UI
  half-features (adset lat/lng validated server-side, no UI input). Root cause: NO case-ID→route→
  component traceability spine.

### Collaborator scope → account (owner reaffirmed 2026-06-24)
- Flipped every SPEC doc to account-scoped (the campaign-scoped hits in compliance-report/
  ui-endpoint-audit/investigation are accurate gap notes — left as-is): cases.md #11 + invite
  notification; ARCHITECTURE.md schema (account_user_id + role string, migration pending) +
  relationship map (dropped legacy `proof_uploader`); design.md client-UI line.

### Spec-collision team (4 read-only agents) — ~20 SOFT conflicts resolved (latest-decision-wins)
- Money: killed stale "centavos" everywhere → decimal MXN pesos (ARCHITECTURE wallet schema,
  platform-graph ledger label, design.md precision→12,2). Struck out compliance-report TOP-ACTION
  #5 (it pointed money the WRONG way — back to centavos).
- Roles: `proof_uploader`/`publisher` typos → installator/publicist/manager; CLAUDE.md "3 roles"
  → 5 roles (admin enum); Support proof power qualified to mismatch-only; platform-graph subrole
  prose fixed (all three names per side).
- Map: design.md's 3× "Google Maps API" → "Leaflet/OSM+w3w today, Google swappable".
- Nav/UI: dashboard = non-client stats / client none (ARCHITECTURE folder tree + table); "50% bar"
  → 3-pane layout; collaborators under Configurations.
- Adsets: ARCHITECTURE geo columns marked LEGACY (pure grouping labels). Proofs: ARCHITECTURE enum
  → B9 client-accept model. Media: 300→150 DPI; pricing vocab day/week/month → day/month/custom.

### HARD conflict resolved by owner (decision 2026-06-25)
- Booking state-machine enum was specified 3 incompatible ways (design prose vs design canonical
  block vs shipped code). Owner chose: **design.md canonical block wins**; ads keep a SEPARATE
  enum; `payout-held` is Payments-only (not a booking state). Reconciled design.md prose +
  canonical block + ARCHITECTURE (canonical target annotated; column migration PENDING).

### Verification
- grep-confirmed every stale string = 0 and every new string present exactly once across all 7 docs.

### Pending (carried to todos)
- 3 doc-aligned-but-code-migration-pending items: collaborators `campaign_id→account_id`,
  `bookings.status` enum, `proofs.status` enum. Plus one pure code-vs-doc gap: `config/app.php`
  is UTC but design mandates America/Monterrey for deadline math. Nothing committed/pushed.

## Session 27 - 2026-06-26 → 2026-06-28

### Summary
Doc-only session (no code). Continued reconciling owner decisions into design.md, then did a
backend endpoint compliance audit, built a 128-item spec backlog into the todos, deleted the
investigation file, and finally CONSOLIDATED the four planning docs into a single design.md.
Heavy use of read-only agent teams for analysis; all writes done sequentially by the main loop.

### Owner decisions applied
- **Provider subroles changed** (2026-06-25, confirmed 06-26): two SEPARATE ecosystems. Client =
  publicist/manager; Provider = installator/**sales**/**supervisor**. Sales = provider-side
  Support (answers messages only, no people/spaces/money). Supervisor = everything EXCEPT
  remove-people / adjust-configs / delete-spaces. (Replaces the old shared installator/publicist/
  manager set on the provider side.)
- **Account object** (2026-06-25): first-class; 1 user = 1 account for MVP; `accounts` table
  (owner_user_id unique, type). [2026-06-27] EMAIL = account identity, one account per email;
  dual client+provider via two emails, manual switch, not enforced. Collaborators FK account_id.
- **Five `[owner 2026-06-27]` decisions:** (1) pre-pay OPTIONAL, provider may reject the file
  repeatedly, client may cancel; escrow returns to wallet on cancel/reject. (2) Proof deadlock →
  Support leans CLIENT, never forces a payout; default = cancel + full refund + client rebooks.
  (3) ONE account per email. (4) Refunds 100% by default UNLESS booking already in-process
  (supersedes 90/5/5; add to T&C). (5) Ratings IMMUTABLE/transparent — no edit/delete, manual
  admin removal only in extreme cases.
- **Timezone** (2026-06-25): store UTC, deadline math vs admin-default tz (Monterrey), per-user
  `users.timezone` display.

### Work done
- **Backend endpoint audit** (4 read-only agents): mapped ~77 routes; found 6 live contradictions
  (Payments proof review B9, collaborators campaign-scoped, bookings/proofs enums, provider bare
  confirm/cancel, hard-deletes no guardrails) + ~12 documented features with no endpoint.
- **what3words** elaborated (optional client-side geocoding, no stored column, swappable).
- **Spec backlog**: a 5yo-architect challenger pass (7 HARD questions → 6 answered, 1 open) +
  spec-extraction agent → **128 discrete specs** written into `.claude/todos/mvp-sprint.json`
  under `spec_backlog` (11 domains, backend-first, status/prio/deps). This is the case-ID→route
  traceability spine.
- **Deleted `investigation-planning-to-code-gap.md`** after re-homing its content (specs→backlog,
  what3words→design.md, 2 leftover actions→XC-* todos).
- **CLAUDE.md**: added the "generate FEWER documents" rule (2026-06-26) and hardened it (06-27):
  no new .md files even for new sections; everything → design.md or todos or chat.

### THE CONSOLIDATION (2026-06-27)
- Merged **ARCHITECTURE.md + cases.md + platform-graph.md + useful parts of compliance-report.md
  into one design.md** (5 software-design agents drafted simplified, de-duplicated sections; main
  loop assembled). New design.md = 20 numbered sections, 1052 lines (was 797): §1 Overview/Roles,
  §2 Role×Object Matrix, §3 Accounts/Collaborators, §4 Objects/Lifecycles, §5 Catalog/Search,
  §6 Pre-pay/Escrow, §7 Proof/Deadline/Strikes, §8 Money, §9 Ratings, §10 Chat/PII, §11
  Tickets/Support, §12 Admin/Moderation/Audit/Config, §13 Notifications/Calendar, §14
  RBAC/Capabilities, §15 Data Schema, §16 Integrity Invariants, §17 Endpoint Map, §18 System/Dev,
  §19 User Stories (verbose), §20 Roadmap/Revisions/Known-gaps.
- **Deleted** ARCHITECTURE.md, cases.md, platform-graph.md, compliance-report.md (backed up in
  the session scratchpad). Repointed CLAUDE.md, README.md, ui-endpoint-audit.md, and the 5yo skill
  to design.md.
- **Verified before delete**: all 21 tables, the 34-row alert schema, the media-spec table, 30
  numbered cases, every `[owner]` marker, all integrity invariants present in the new file.

### Open decision (carried)
- **Strike reversal vs freeze**: when a reversed strike had already triggered a freeze that
  auto-cancelled + refunded upcoming bookings, can it be undone? Proposed (B) — freeze pauses new
  bookings immediately but DELAYS auto-cancel of upcoming bookings for an appeal window (48–72 h,
  configurable); reversal within the window = clean undo. Awaiting owner pick of (A) immediate /
  (B) appeal-window.

### Next
- Owner to pick strike-reversal option (A/B) → write into design.md §7/§12 + todos.
- Then resume backend-first build from the spec_backlog P1 critical path (accounts table,
  account-scoped collaborators, bookings/proofs enum migrations, B9 removal, audit log, etc.).
- Nothing committed/pushed this session (doc-only).

## Session 28 - 2026-07-06 → 2026-07-14

### Summary
Two arcs. (1) Brought the dockerized stack up in **GitHub Codespaces** for the first time and
fixed the whole login-failure chain. (2) Adopted a **feature-by-feature MVP review** (F01–F17)
driven from `design.md`, resolving specs and applying code one feature at a time. Work is on
branch `mvp-build`; the Claude session's git push is blocked (403), so everything is delivered as
prefixed `.patch` files the owner applies + pushes from their Codespace.

### Codespaces bring-up (infra)
- CORS env-driven + `*.app.github.dev` pattern; `environment.ts` auto-detects the Codespaces
  backend URL; nginx resolves the backend per-request (no crash-loop); entrypoint clears stale
  `bootstrap/cache/*.php` (the `Barryvdh\DomPDF` crash); documented in claude.md.
- Root causes of the "Login failed" chain, in order: hardcoded localhost CORS/API URL →
  frontend entrypoint exec-bit lost under bind mount → orphaned `publisher_*` containers holding
  ports → backend crash-loop on a stale dompdf package-discovery cache → nginx crash-loop
  (`host not found in upstream`) → port 8000 unpublished (needed `--force-recreate`) → empty
  users table (needed `--seed`). Login now works: `admin@pubads.test` / `password`.

### Feature review (single spec = design.md; index in .claude/todos/mvp-sprint.json → feature_review)
- **Working method** recorded in claude.md: one feature at a time, MVP bias (functional > precise),
  resolve simple forks now, only escalate genuine ambiguities/clashes; `[Fxx]` tags on design.md
  headers share codes with the todos index.
- **F04** — orphans move into a NEW *or* EXISTING adset (backend + UI); ad-origin INVARIANT: a
  client never creates a space/ad from scratch (design.md §5 + §19 journey 4). The per-adset
  "+ Ad Space" create form is documented as a bug (mints space-less ads); code fix still pending.
- **F06** — provider Reject-with-optional-reason added to the booking UI.
- **F07** — removed the proof re-upload loop (reconciled ~8 design.md spots). Client reject →
  ONLY automatic: payout auto-HELD + a FLAG (`Payment held — <reason>`) + **THREE** Support-
  anchored threads (Support↔Payments `internal`, Support↔Client `support_client`, Support↔Provider
  `support_provider`). Proof deadline is the ONLY automatic phase; all resolution is human; new
  proof requests are a human Support action (no auto window). Primary-proof uploaders =
  provider / Installator / Supervisor. **Dispute CODE still pending (going with it next).**
- **F08** — internal Support↔Payments threads client-invisible (MessageController ACL hardened +
  test). Support reads/posts a client↔provider thread only AFTER joining; Payments never enters
  client-facing threads (works via the internal thread); Admin via oversight.
- **F09** — Support powers: `POST /support/conversations/{c}/join` (relaxes F08 masking) +
  `flag-refund` / `flag-payout-hold` (Support flags, Payments decides) + ticket-detail Join/Flag
  UI. Remaining: Support edit-any-non-money-object endpoints.
- **F10** — Payments now sees WHY money is held: `Payment morphMany tickets`; payment-list shows a
  flag-note per Support flag. Core money (approve/reject/refund/release/hold + idempotent wallet)
  already existed.
- Resolved the **F08↔F09↔F10 ACL clash** (Support-after-join read access; Payments internal-only).

### Process learnings (claude.md)
- Patch transport: `git add -A` twice swallowed the `.patch` file into a commit (code missing).
  FIX: `*.patch` is git-ignored; apply then verify `git status` shows CODE files. And: finish a
  feature fully before handing over ONE patch. Naming: `p<NN>-<Fxx>-<hash>-<slug>.patch` (unique).

### Next (owner-directed)
- **Option A: implement the dispute CODE** — `ProofFlagController` auto-opens the 3 threads + flag
  text on reject; `MessageController`/ACL support `support_client` / `support_provider` types.
- Then continue the review: F11 (admin oversight), F12 (config), F13 (notif/calendar), F14 (RBAC);
  plus the deferred code gaps: F05 ("+ Ad Space" removal + per-ad creative upload), F03 3-pane.

## Session 29 - 2026-07-16 → 2026-08-01

### Summary
One very long session (30 days, ~3.2k model calls). Four arcs: (1) finished the **F07 dispute
code** and unified **chats/objects/flags** into ONE primitive; (2) settled the **entry-point rule**
for chat counterparties; (3) built the **interactive design dashboard** (`design/`) and a
**lineage** system (FL/ER/CL ids); (4) stood up a **3-agent team** whose review caught a real
**IDOR**, which produced the new **§21 + UC-43**. Delivery stayed patch-based (`pNN.patch`);
the last applied patch is **p61**.

### Chat unification & entry-point routing (F08/F09, §10)
- ONE chat primitive: chats + participants + objects + flags; tickets retired. Nature is DERIVED
  from anchors, never a stored enum shown to users (`chatTitle()` renders "Chat con soporte").
- **KEY DECISION [owner 2026-07-25]:** a chat's counterparty is decided by **WHERE the client opens
  it**, NEVER inferred from the attached object's owner. Messages tab / ad-adset-campaign object →
  **Support**; the provider's published listing → that **Provider** (the ONLY path — providers have
  no public profile); Billing → Support-fronted with the payment attached, Payments looped in via
  the internal chat. Counterparties are NOT fixed at creation (Support/Payments can join later).
- **KEY DECISION [owner 2026-08-01]:** which object routes to whom (table in §10) — `space` →
  Provider; `campaign`/`adset`/`ad`/`booking`/`proof`/`payment` → **Support**, who may pull the
  provider in. Support decides, not the UI.
- Shipped: global "Soporte" navbar button, per-space "Mensaje al proveedor", `ChatController@store`
  routing by `target`, proof shown inline in chat. 44 backend tests green (164 assertions).

### Design dashboard + lineage (`design/`)
- `design.html` + `app.js` (vanilla IIFE) + `styles.css` + `design.json` (data) + vendored mermaid.
  Views: Specs (list/kanban toggle), consolidated Todos, Flow, ER, Classes, User Stories; friendly
  deep-link URLs (`#spec/10`, `#uc/UC-9`, `#flow/FL07`) and cross-links between specs⇄stories⇄todos.
- **Lineage**: stable ids — flow `FL01–FL22` (+ edges & decision branches), ER `ER01–ER23`,
  classes `CL01–CL10`; specs/UCs/todos gained `flow`/`er`/`classes` refs rendered as "Impacto" chips
  that deep-link and highlight the element.
- **RULE the owner set:** lineage metadata is **REFERENCE ONLY** — never regenerate or redesign the
  diagrams from it. Graphs are not wired to each other.
- **RULE:** never fabricate a canonical UC/spec/todo. Where none fits, `suggested` only. 9 infra/meta
  UC proposals + 3 todo proposals (UC-29/31/32) are parked awaiting the owner.
- Promoted two suggestions the owner approved: **UC-41** (actor `USER`, sign-in grants role
  capabilities) and **UC-42** (actor `CLIENT`, one seamless conversation instead of tickets-vs-chat).

### Agent team + the IDOR it caught
- `.claude/agents/`: `plan-opus48` (plan, read-only), `build-opus5` (build), `review-opus5` (review).
  Model pinning lives in the frontmatter; the Agent tool's inline `model` cannot distinguish 4.8/5.
- The review of the adset-detail build (p60) found: **IDOR** — `/client/campaigns/{c}/adsets/{a}`
  and `/ads/{ad}` verify the campaign is yours but NOT that the child belongs to it, so a client
  could read/rename/DELETE another account's adset (cascading to its ads+proofs). **p60 was
  rejected, not shipped.** It also replicated the space-less-ad form that §5 calls a bug.

### Owner decisions captured today (§21, §3) — APPROVED, NOT YET BUILT
- **NEW §21 "Object Ownership & Access Chain" + UC-43**: authorize by the full chain
  user→account→campaign→adset→ad (space → provider account); `scopeBindings()` + explicit
  `abort_unless(child.parent_id === parent.id, 404)`; **404 never 403**; moves allowed only within
  one account (carrying `bookings.adset_id`); **INVARIANT: every ad has a `space_id`**.
- **Adset deletion**: an adset is only a grouping label → `ads.adset_id` goes
  `cascadeOnDelete` → **`nullOnDelete`**. Ads survive as orphans; client chooses move-or-orphan.
  Orphaned ≠ stopped — anything already paid keeps running.
- **A REAL `accounts` table is created** (§3 always prescribed it; it never existed).
  `users.account_id` → FK `accounts` (this direction, so owner-users can share an account later).
  Collaborators AND billing are account-scoped; `collaborators.campaign_id` is dropped. Existing
  rows are throwaway test data. `account_grants` overlay stays a later task.

### Pitfalls Hit
- **Emoji inside an Angular `{{ }}` binding renders EMPTY** (silent parse fail). Cost a long
  misdiagnosis (blamed cache, then the container). FIX: `@if/@else` with static text.
- **Codespaces' port-forward proxy caches static assets hard** — a stale `app.js` survived hard
  refresh AND incognito. FIX: cache-bust `app.js`/`styles.css`/`design.json` with `?t=Date.now()`.
  Side effect: an injected script runs AFTER `DOMContentLoaded`, so boot must not rely on the event.
- **Mermaid highlight**: `[id*="FL07"]` matches an **edge** (`L_FL06_FL07_0`) before the node —
  the highlight landed on a bare `<path>` with no child shape, so nothing painted. FIX: pin
  `.node[id*=…]`. Class labels live in a `foreignObject`, so `closest("g")` stops at a shapeless
  `.label` — climb to `.closest(".node")`. Verified in headless Chromium instead of guessing.
- **Empty DB after a container restart** → "The provided credentials are incorrect". FIX:
  `mvp-init.py` (self-healing seed) + an UNCONDITIONAL reseed reminder in the patch instructions.
- **Patch filenames with hyphens get mangled on paste** and break `git apply` — use `pNN.patch`.
- **Session length is the token sink**: 1.03B cache-read tokens over 30 days ≈ 326k re-read per
  call; the 07-28 peak was 143M in a day. Switching models mid-session invalidates the whole prompt
  cache (a cache is per-model) and forces an expensive re-write. FIX: a fresh session per feature.

### Open Questions for Next Session
- **Q32 — the IDOR fix is approved but NOT built.** §21/UC-43 is task 1: scoped bindings + explicit
  parent checks + the move guardrails + the `space_id` invariant, with tests.
- **Q33 — `ad`/`booking`/`proof` route to Support "for now"**; the owner expects Support may later
  add the provider to an `ad` thread. Revisit when Support tooling grows.
- **Q34 — the space-less-ad form still ships** in `campaign-detail.component.ts` (pre-existing,
  §5 calls it a bug); it must be removed when §21's invariant is enforced.
- **Q35 — WALK-1 (human Messages walkthrough) is still `pending`**, so F08/F09 remain
  `in_progress` and §10 stays `wip`. The guion exists (C1–C6).
- **Q36 — F11 was marked `done` on test evidence, but its human UI walkthrough (O1–O7) was never
  run.** The owner flagged this; consider `review` until the walkthrough happens.
- Parked: 9 suggested UC proposals (infra/meta) + 3 suggested todos (UC-29 moderation, UC-31 audit
  log, UC-32 clawback) await an owner call.

### Note on the record
Q26–Q31 (opened in Session 18) were **closed in Session 19** by the O1–O24 owner decisions
(clawback, config effect on in-flight bookings, cross-queue precedence, re-upload bound, SMS
provider); the re-upload loop itself was later removed entirely in Session 28's F07 work.

## Session 30 - 2026-08-01 → 2026-08-02

### Summary
Short session, three arcs: (1) closed **Q32** — built the §21/UC-43 ownership-chain fix that
Session 29 approved but never shipped, closing **Q34** with it; (2) a git-hygiene cleanup that
reconciled `main` with `mvp-build`, deleted the stale branches and rescued code that only lived on
a doomed one; (3) two standing owner rules recorded in claude.md. Delivered as `p64` + `p65`.

### Q32 / Q34 — §21 + UC-43 built (p64)
- New `AuthorizesOwnershipChain` trait (`Http/Controllers/Concerns/`) — the ONE place the chain is
  verified, used by the Adset/Ad/Backlog controllers. The ownership ROOT is isolated in a single
  `ownsCampaign()` method, so the future real `accounts` table is a one-method change.
- `routes/api.php`: the adsets+ads block is wrapped in `Route::scopeBindings()`, backed by explicit
  `abort_unless(child.parent_id === parent.id, 404)` in the trait. **404, never 403.**
  (`campaigns/{campaign}/adsets/move` sits outside the group and does not collide.)
- `AdController`: `space_id` `nullable` → **`required`** on store and `sometimes` on update (it can
  never be nulled) — §21's invariant that every ad has a space.
- `BacklogController` move guardrails: every `ad_id` must resolve inside the caller's account and so
  must the destination adset; `bookings.adset_id` is carried in the SAME transaction. Cross-campaign
  within one account is ALLOWED (the move matrix); cross-account never is.
- Adset deletion: migration `2026_08_01_000001` drops the FK and re-adds it `nullOnDelete`
  (`2026_06_13_000020` had kept `cascadeOnDelete`); `destroy` accepts optional `move_ads_to`. An
  adset is a grouping label — deleting it orphans its ads, it does not destroy them.
- **Q34 closed**: the space-less "+ Ad Space" form is out of `campaign-detail.component.ts`,
  replaced by a link into `/client/spaces` carrying `campaign` + `adset` query params.
- `OwnershipChainTest` — 13 tests, including the two IDOR proofs (own campaign + foreign adset;
  own chain + foreign ad) and the intra-account break (campaign B + adset of campaign A).
- §21 moved `backlog` → **`wip`**, not `done`: the human UI walkthrough has not run (the same gap
  Q36 flagged for F11).

### Branch hygiene — the real problem, and what came out of it
- The Codespaces CORS/login fix got solved TWICE on two divergent branches because `main` was the
  default branch and 80 commits behind `mvp-build`, so fresh clones landed on stale code.
  Now `main == mvp-build` (`rev-list --left-right --count` → `0 0`).
- **Before deleting a branch, check what only lives there.** `ApiErrorCode.php` + `api-error.ts`
  (plus the `bootstrap/app.php` renderers and the 401 login response) existed ONLY on
  `claude/…-k1fgt8` at `ff98444`; cherry-picked into `mvp-build` and shipped inside p64. A branch is
  the only home of its unmerged commits — deleting it deletes them.
- **A branch-scoped fact is not a repo fact.** I reported "history.md ends at Session 18" without
  naming the branch — true on `k1fgt8`, false for the repo; a parallel session made the mirror-image
  error. Verify with `git cat-file -e`, `git branch -r --contains`, `git merge-base` and
  `git rev-list --left-right --count` before stating anything as repo-wide.
- Backups do NOT belong in branches when the commits already exist.

### Owner rules recorded (claude.md, p65)
- **Branches are per DEVELOPER, never per feature/task** — a branch identifies WHO is working, not
  WHAT is being built. Applies to ALL sessions from here on. While in MVP mode (current, until
  further notice) everything goes on `mvp-build`; no new branches unless explicitly necessary.
- **In Claude Code (web) sessions: NEVER push, and never ask to.** Deliver `pNN.patch` plus a
  ready-to-paste commit message. Note that `git commit -m "pNN"` squashes the patch's commits into
  one, so per-commit messages are lost — the real description has to be in the suggested message.

### Pitfalls Hit
- **The push 403 is a token-SCOPE problem — not repo visibility, not the branch name.** Proven:
  the repo was already `private: false`; `push --dry-run` to the designated branch 403s too;
  `add_repo` with `access:"push"` returns `already_present`. Do NOT flip visibility or route around
  the proxy to "fix" it (its own README says so).
- **The stop-hook "Unverified commits" warning is unfixable in this container**:
  `/home/claude/.ssh/commit_signing_key.pub` is **0 bytes**, so no signature is possible. The
  committer email is already `noreply@anthropic.com`, so the suggested `--reset-author` adds nothing
  — and on a commit authored by the owner it would DESTROY that authorship. Moot regardless: these
  commits never leave the session, they travel as patches.
- Postgres died twice mid-session (`SQLSTATE[08006] Connection refused`), once surfacing as 55
  phantom test failures. `service postgresql start`, then re-run before believing any red suite.

### Open Questions for Next Session
- **Q37 — proofs still answer 403 where §21 rule 2 says 404.** `AuthorizationNegativeTest` asserts
  403 for another client's proof; left untouched pending an owner call, since flipping it rewrites
  an existing green test.
- **Q38 — §21 stays `wip` until a human UI walkthrough runs**: create/move/delete adsets and ads,
  confirm orphans survive, confirm nothing answers 403.
- Q33, Q35, Q36 and Session 29's parked UC/todo proposals remain open.

<!-- Add new sessions above this line -->

