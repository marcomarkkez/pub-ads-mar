# Architecture — pub-ads-mar

Advertising spaces marketplace. Clients book provider ad spaces. Managers administer users.

---

## Folder Structure

```
pub-ads-mar/
├── .agents/skills/                  # Agent skills (session-recorder, todo-tracker, skill-creator)
├── .beads/                          # Beads issue tracker database
├── .claude/skills/                  # Claude skill symlinks
│
├── backend/                         # Laravel 12 API (fully implemented)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Controller.php           # Base controller
│   │   │   │   ├── AuthController.php       # register, login, logout, me
│   │   │   │   ├── Client/
│   │   │   │   │   ├── CampaignController.php
│   │   │   │   │   ├── AdsetController.php
│   │   │   │   │   ├── AdController.php
│   │   │   │   │   ├── BookingController.php
│   │   │   │   │   ├── SpaceSearchController.php
│   │   │   │   │   ├── CollaboratorController.php
│   │   │   │   │   └── InvoiceController.php
│   │   │   │   ├── Provider/
│   │   │   │   │   ├── SpaceController.php
│   │   │   │   │   ├── SpacePhotoController.php
│   │   │   │   │   ├── SpaceAvailabilityController.php  # + syncIcal, importIcal
│   │   │   │   │   ├── BookingController.php
│   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   └── ProofController.php
│   │   │   │   ├── Admin/
│   │   │   │   │   ├── UserController.php         # CRM user CRUD
│   │   │   │   │   └── PermissionController.php   # RBAC permissions CRUD
│   │   │   │   ├── Support/
│   │   │   │   │   └── TicketController.php       # Ticket management
│   │   │   │   ├── Payments/
│   │   │   │   │   ├── PaymentController.php      # Payment approve/reject
│   │   │   │   │   └── ProofReviewController.php  # Proof review
│   │   │   │   ├── Manager/
│   │   │   │   │   └── UserController.php         # Legacy (kept for compat)
│   │   │   │   └── Shared/
│   │   │   │       ├── ConversationController.php
│   │   │   │       ├── MessageController.php
│   │   │   │       └── TicketController.php       # User ticket CRUD
│   │   │   └── Middleware/
│   │   │       ├── RoleMiddleware.php             # role:client,provider,...
│   │   │       └── PermissionMiddleware.php       # permission:resource,action
│   │   ├── Services/
│   │   │   └── IcalSyncService.php                # iCal calendar parsing
│   │   └── Models/
│   │       ├── User.php                 # + hasPermission(), getPermissions()
│   │       ├── Campaign.php
│   │       ├── Adset.php
│   │       ├── Ad.php
│   │       ├── Space.php
│   │       ├── SpacePhoto.php
│   │       ├── SpaceAvailability.php
│   │       ├── Booking.php
│   │       ├── Payment.php
│   │       ├── Conversation.php
│   │       ├── Message.php
│   │       ├── Proof.php
│   │       ├── Ticket.php
│   │       ├── TicketMessage.php
│   │       ├── Collaborator.php
│   │       ├── Invoice.php
│   │       ├── RolePermission.php       # RBAC with caching
│   │       ├── WalletEntry.php          # client wallet ledger
│   │       └── SystemConfiguration.php  # admin-tunable key/value settings
│   ├── bootstrap/
│   │   └── app.php                  # statefulApi(), role + permission middleware
│   ├── config/
│   │   ├── cors.php                 # CORS: allow localhost:4200, credentials: true
│   │   └── sanctum.php              # Stateful domains incl. localhost:4200
│   ├── database/
│   │   ├── migrations/              # 26 total (users, cache, jobs, tokens + 22 domain/alter)
│   │   └── seeders/
│   │       ├── DatabaseSeeder.php   # Demo: 7 users, 4 spaces, 2 campaigns
│   │       └── RolePermissionSeeder.php  # Default RBAC permissions (~66 rows)
│   ├── routes/
│   │   └── api.php                  # 77+ API routes, each with permission middleware
│   ├── tests/
│   │   ├── test_all_endpoints.py    # 96 endpoint tests (Python)
│   │   └── insert_data.py           # Fake data generator (--scale small/medium/large)
│   ├── storage/app/public/          # Uploaded files (space photos, ads)
│   └── .env                         # DB_CONNECTION=pgsql, DB_PORT=5434, DB_DATABASE=pub_ads_mar
│
├── frontend/                        # Angular 18 SPA (fully implemented)
│   ├── src/
│   │   └── app/
│   │       ├── core/
│   │       │   ├── guards/          # authGuard, guestGuard, roleGuard
│   │       │   ├── interceptors/    # Auth interceptor (Bearer token, error handling)
│   │       │   ├── models/          # TypeScript interfaces for all entities
│   │       │   └── services/        # AuthService (signals), NotificationService
│   │       ├── features/
│   │       │   ├── admin/           # User list/form (CRM), Permissions editor (RBAC matrix)
│   │       │   ├── auth/            # Login, Register (with role selector)
│   │       │   ├── client/          # Campaigns, Adsets, Ads, Space search (Leaflet), Bookings, Invoices
│   │       │   ├── dashboard/       # Role-specific quick actions
│   │       │   ├── payments/        # Payment list (approve/reject), Proof review
│   │       │   ├── provider/        # Spaces (list/form/detail + photos + calendar sync), Bookings, Proofs
│   │       │   ├── shared/          # Conversations + chat, Tickets
│   │       │   └── support/         # Ticket list + detail (status update + reply)
│   │       └── shared/
│   │           ├── components/      # Navbar, Sidebar, NotificationToast, LocationPicker (Leaflet+w3w)
│   │           └── layouts/         # MainLayout, AuthLayout
│   └── angular.json
│
├── AGENTS.md                        # Agent onboarding (beads)
├── ARCHITECTURE.md                  # This file
├── CLAUDE.md                        # Agent instructions
├── README.md                        # Project readme
├── history.md                       # Session diary
└── install_beads.md                 # bd CLI install guide
```

### Frontend Feature Components

| Module | Components |
|--------|-----------|
| admin | User list (CRM table + pagination), User form (create/edit), Permissions editor (RBAC matrix) |
| auth | Login page, Register page (with role selector) |
| client | Campaign list/form/detail, Space search (Leaflet map), Booking list, Invoice list |
| dashboard | Role-specific quick actions |
| payments | Payment list (approve/reject), Proof review list (approve/reject) |
| provider | Space list/form/detail (photos + availability + calendar sync), Booking list, Proof list |
| shared | Conversation list + chat detail, Ticket list/form/detail |
| support | Ticket list (status filter), Ticket detail (status update + reply) |

---

## Database Schema

> PostgreSQL 17 local **port 5434** — database: `pub_ads_mar` — user: postgres (trust auth, no password)

```
users
  id, name, email, password, role (enum: client|provider|admin|support|payments),
  phone, company_name, address, is_active, remember_token, timestamps

personal_access_tokens  [Sanctum]
  id, tokenable_type, tokenable_id, name, token (hash),
  abilities, last_used_at, expires_at, timestamps

campaigns  [belongs to: user(client)]
  id, user_id, name, description, status, start_date, end_date, budget, timestamps

adsets  [belongs to: campaign]
  id, campaign_id, name, latitude, longitude, location_name, radius_km, status, timestamps

ads  [belongs to: adset (nullable) + space + provider_user; reached from campaign via adset]
  id, adset_id (nullable — backlog/orphan ads), space_id, provider_user_id,
  name, media_type (enum: image|video|sound|gif), file_path, file_name,
  price, pricing_unit (day|month|custom), start_date, end_date,
  status (enum: draft|pending_approval|approved|rejected|active|paused|completed|cancelled),
  proof_deadline, timestamps

spaces  [belongs to: user(provider)]
  id, user_id, name, type, latitude, longitude,
  price_per_day, price_per_month, pricing_unit (day|month|custom),
  description, location_text, width, height,
  ical_url, calendar_keyword, is_active, timestamps

space_photos  [belongs to: space]
  id, space_id, file_path, file_name, is_primary, sort_order, timestamps

space_availabilities  [belongs to: space]
  id, space_id, start_date, end_date,
  status (enum: available|booked|blocked), source (manual|ical), timestamps

bookings  [belongs to: client_user + space + ad + adset]
  id, client_user_id, space_id, ad_id (nullable), adset_id (nullable),
  start_date, end_date, total_price,
  status (enum: pending|waiting_approval|confirmed|active|waiting_proof|completed|cancelled|rejected),
  config_snapshot (json — e.g. proof_deadline_days), timestamps

payments  [belongs to: booking]
  id, booking_id, amount,
  status (enum: pending|completed|failed|refunded|held|released),
  payment_method (mocked), payment_platform (mercadopago|paypal),
  transaction_id, approved_by_payments, approved_by_user_id, timestamps

conversations  [belongs to: space + client_user + provider_user]
  id, space_id, client_user_id, provider_user_id,
  type (direct|support…), support_joined_at,
  unique(space_id, client_user_id), timestamps

messages  [belongs to: conversation + sender_user]
  id, conversation_id, sender_user_id, body, kind (user|system…), is_read, timestamps

proofs  [belongs to: ad + booking + uploaded_by_user]
  id, ad_id, booking_id, uploaded_by_user_id, media_type (image|video),
  file_path, file_name, notes,
  status (enum: pending_review|approved|rejected),
  reviewed_by_user_id, reviewed_at, deadline, timestamps

tickets  [belongs to: user(reporter) + assigned_to_user; polymorphic ticketable]
  id, user_id, ticketable_type, ticketable_id, subject, description,
  status (enum: open|in_progress|waiting_user|resolved|closed),
  priority (enum: low|medium|high|urgent),
  assigned_to_user_id, timestamps
  -- ticketable_type covers App\Models\Ad|Adset|Campaign|Space

ticket_messages  [belongs to: ticket + user]
  id, ticket_id, user_id, body, is_internal (staff-only notes), timestamps

collaborators  [belongs to: campaign + invited_by_user + user]
  id, campaign_id, invited_by_user_id, user_id (nullable until registered),
  email, role (enum: proof_uploader),
  status (enum: pending|accepted|revoked),
  unique(campaign_id, email), timestamps

invoices  [belongs to: campaign]
  id, campaign_id, invoice_number (unique), total_amount,
  status (enum: draft|issued|paid|overdue|cancelled),
  issued_at, due_at, timestamps

role_permissions  [RBAC]
  id, role, resource, action, allowed,
  unique(role, resource, action), timestamps

wallet_entries  [belongs to: user]
  id, user_id, amount_centavos (signed),
  type (enum: refund|withdrawal|escrow_capture|escrow_release|adjustment),
  ref_type, ref_id, idempotency_key (unique), timestamps

system_configurations  [admin-tunable settings, e.g. proof_deadline_days]
  id, key (unique), value, updated_by_user_id, timestamps
```

### Relationship Map

```
User (client)  ──< Campaign ──< Adset ──< Ad   (adset_id nullable → orphan/backlog ads)
                       │                  └──> Space + User(provider)   (ad belongs to space + provider)
                       ├──< Collaborator   (campaign invites, proof_uploader)
                       └──< Invoice
User (provider) ──< Space ──< SpacePhoto
                          └──< SpaceAvailability   (source: manual | ical)
Space + Ad + Adset + User(client) ──> Booking ──> Payment
Ad + Booking + User(uploader) ──> Proof
Space + User(client) + User(provider) ──> Conversation ──< Message
User ──< Ticket >── ticketable (polymorphic: Ad | Adset | Campaign | Space) ──< TicketMessage
User ──< WalletEntry
RolePermission        (role × resource × action — RBAC matrix)
SystemConfiguration   (admin-tunable key/value, e.g. proof_deadline_days)
```

---

## API Routes

> Every route below `auth:sanctum` also carries a granular
> `permission:resource,action` middleware (RBAC via `role_permissions`).
> The `permission:` clause is omitted here for brevity — see `routes/api.php`.

```
POST   /api/register
POST   /api/login
POST   /api/logout                        auth:sanctum
GET    /api/me                            auth:sanctum

# Client routes — middleware: auth:sanctum, role:client
GET|POST              /api/client/campaigns
GET|PUT|DELETE        /api/client/campaigns/{campaign}
GET|POST              /api/client/campaigns/{campaign}/adsets
GET|PUT|DELETE        /api/client/campaigns/{campaign}/adsets/{adset}
GET|POST              /api/client/campaigns/{campaign}/adsets/{adset}/ads
GET|PUT|DELETE        /api/client/campaigns/{campaign}/adsets/{adset}/ads/{ad}
GET|POST              /api/client/bookings
GET|PUT               /api/client/bookings/{booking}
GET                   /api/client/spaces/search
GET|POST              /api/client/campaigns/{campaign}/collaborators
DELETE                /api/client/campaigns/{campaign}/collaborators/{collaborator}
GET                   /api/client/invoices
GET                   /api/client/invoices/{invoice}
POST                  /api/client/campaigns/{campaign}/backlog       # add backlog/orphan ad
GET                   /api/client/campaigns/{campaign}/orphans       # list orphan ads
POST                  /api/client/campaigns/{campaign}/adsets/move   # move ad into adset
POST                  /api/client/proofs/{proof}/flag-mismatch
GET                   /api/client/wallet

# Provider routes — middleware: auth:sanctum, role:provider
GET|POST              /api/provider/spaces
GET|PUT|DELETE        /api/provider/spaces/{space}
POST                  /api/provider/spaces/{space}/photos
DELETE                /api/provider/spaces/{space}/photos/{photo}
GET|POST              /api/provider/spaces/{space}/availabilities
DELETE                /api/provider/spaces/{space}/availabilities/{availability}
POST                  /api/provider/spaces/{space}/sync-ical         # pull from ical_url
POST                  /api/provider/spaces/{space}/import-ical       # upload .ics file
GET                   /api/provider/bookings
PUT                   /api/provider/bookings/{booking}
GET                   /api/provider/dashboard
GET|POST              /api/provider/proofs
GET                   /api/provider/proofs/{proof}

# Admin routes — middleware: auth:sanctum, role:admin
GET|POST              /api/admin/users
GET|PUT|DELETE        /api/admin/users/{user}
GET                   /api/admin/permissions                         # no permission mw (anti-lockout)
GET|PUT               /api/admin/permissions/{role}
PATCH                 /api/admin/permissions/{role}/{resource}
GET                   /api/admin/oversight/conversations             # eagle-eye, read-only
GET                   /api/admin/oversight/conversations/{conversation}/messages
GET                   /api/admin/oversight/tickets
GET                   /api/admin/oversight/tickets/{ticket}
GET|PUT               /api/admin/configurations                      # system_configurations

# Support routes — middleware: auth:sanctum, role:support
GET                   /api/support/tickets
GET|PUT               /api/support/tickets/{ticket}
POST                  /api/support/tickets/{ticket}/reply

# Payments routes — middleware: auth:sanctum, role:payments
GET                   /api/payments/payments
GET                   /api/payments/payments/{payment}
POST                  /api/payments/payments/{payment}/approve
POST                  /api/payments/payments/{payment}/reject
POST                  /api/payments/payments/{payment}/refund        # permission:payments,refund
POST                  /api/payments/payments/{payment}/payout/release
POST                  /api/payments/payments/{payment}/payout/hold
GET                   /api/payments/proofs
POST                  /api/payments/proofs/{proof}/approve
POST                  /api/payments/proofs/{proof}/reject

# Shared — middleware: auth:sanctum (any authenticated role)
GET|POST              /api/conversations
GET|POST              /api/conversations/{conversation}/messages
GET|POST              /api/tickets
GET                   /api/tickets/{ticket}
POST                  /api/tickets/{ticket}/reply
```

---

## Auth Flow

```
Client → POST /api/login → Laravel Sanctum → token (plaintext)
Client stores token in localStorage
Every request: Authorization: Bearer {token}
Backend: auth:sanctum middleware validates token
Role check: RoleMiddleware compares user->role with required role
```

---

## Data Flow

```
Angular SPA (localhost:4200)
    │  HTTP + Bearer token
    ▼
Laravel API (localhost:8000/api)
    │  Eloquent ORM
    ▼
PostgreSQL 17 (localhost:5434 / pub_ads_mar)
    │
    ├── File uploads → storage/app/public/ (served via /storage symlink)
    └── Media types: image, video, sound, gif
```

---

## Dev Commands

### Docker (canonical — recommended)

```bash
# Build + start all services (backend + nginx + frontend + postgres)
docker compose up -d --build

# Logs
docker compose logs -f backend

# Artisan / composer inside container
docker compose exec backend php artisan migrate:fresh --seed
docker compose exec backend composer install

# psql into containerized DB
docker compose exec db psql -U postgres -d pub_ads_mar

# Stop / reset
docker compose down            # stop containers, keep data
docker compose down -v         # stop + delete DB volume
```

Ports: API `localhost:8000` · Frontend `localhost:4200` · DB `localhost:5435`.

The bare-metal commands below are kept as a fallback. New work should use Docker.

### Backend (Laravel)

```bash
cd backend

# Start API server
php artisan serve                    # → http://localhost:8000

# Database
php artisan migrate                  # Run migrations
php artisan migrate:fresh --seed     # Reset + seed demo data
php artisan db:seed                  # Seed without reset

# Storage
php artisan storage:link             # Create public symlink for uploads

# Debug
php artisan route:list               # List all registered routes
php artisan tinker                   # Interactive REPL

# PostgreSQL path (add to system PATH for convenience)
# C:\Program Files\PostgreSQL\17\bin\
```

### Frontend (Angular)

```bash
cd frontend

npm install                          # Install dependencies
ng serve                             # → http://localhost:4200
ng build                             # Production build
ng generate component features/...  # Generate component
```

### Database (local PostgreSQL 17)

```bash
# Using full path (if psql not in PATH) — always use -p 5434
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5434

# Common commands inside psql
\l                  # List databases
\c pub_ads_mar      # Connect to database
\dt                 # List tables
```

---

## Implementation Status

| Component | Status | Notes |
|-----------|--------|-------|
| Laravel scaffold | ✅ Done | `backend/` — fresh Laravel 12 |
| Angular scaffold | ✅ Done | `frontend/` — Angular 18 with SSR |
| PostgreSQL DB | ✅ Done | Local PG 17, db: `pub_ads_mar` |
| `.env` config | ✅ Done | pgsql, port 5434, trust auth (no password) |
| PHP pdo_pgsql ext | ✅ Done | Enabled in C:\php\php.ini |
| Sanctum install | ✅ Done | v4.3.1 via `php artisan install:api` |
| CORS config | ✅ Done | Allow localhost:4200 with credentials |
| Users migration | ✅ Done | role/phone/company_name/address/is_active added |
| Domain migrations | ✅ Done | 26 total: 3 framework + personal_access_tokens + 15 domain (campaigns → invoices) + role_permissions + 2 alters (calendar_keyword, thread cols) + wallet_entries + system_configurations |
| User model | ✅ Done | HasApiTokens + role helpers + relationships + walletEntries |
| Eloquent models (×18) | ✅ Done | User + 17 domain models, all with relationships and casts |
| RoleMiddleware | ✅ Done | Checks user role, returns 403 |
| PermissionMiddleware | ✅ Done | Granular RBAC: permission:resource,action per route |
| RBAC permissions table | ✅ Done | role_permissions with cached lookups (60min) |
| Admin Permissions API | ✅ Done | 4 endpoints: list, show, update, updateResource |
| API controllers (×23) | ✅ Done | Auth, Client(7), Provider(6), Admin(2), Support(1), Payments(2), Shared(3) |
| API routes (api.php) | ✅ Done | 77+ routes, each with permission middleware |
| Storage link | ✅ Done | public symlink created |
| iCal calendar sync | ✅ Done | IcalSyncService, artisan command, provider endpoints |
| Demo seeders | ✅ Done | 7 users (5 roles), 4 spaces, 2 campaigns, default permissions |
| Fake data script | ✅ Done | insert_data.py (--scale small/medium/large) |
| Endpoint test script | ✅ Done | test_all_endpoints.py — 96/96 passing |
| Angular features (40+ files) | ✅ Done | 8 feature modules, core services, guards, interceptors, Leaflet maps, global styles |
| LocationPicker component | ✅ Done | Leaflet + what3words autosuggest, drag-to-pick |
| Calendar sync UI | ✅ Done | iCal URL input, keyword filter, Sync Now, Upload .ics |

---

## Key Design Decisions

- **Single `users` table** with `role` enum + `role_permissions` for granular RBAC
- **Nested routes** (`campaigns/{id}/adsets/{id}/ads`) — enforces ownership
- **Token-based auth** (Sanctum) not session cookies — SPA-friendly
- **Mocked payments** — no real gateway for MVP
- **Geolocation search** — bounding-box filter for MVP (PostGIS upgrade path later)
- **Message filtering** — strip phone numbers, emails, URLs via regex
- **Separate booking controllers per role** — Client creates/cancels, Provider confirms/rejects

---

## Demo Accounts

All seeded with `php artisan migrate:fresh --seed`. Password for all: **`password`**

| Role | Email | Company |
|------|-------|---------|
| Admin | admin@pubads.test | PubAds Admin |
| Support | support@pubads.test | — |
| Payments | payments@pubads.test | — |
| Provider | provider1@pubads.test | Dupont Affichage |
| Provider | provider2@pubads.test | Laurent Media |
| Client | client1@pubads.test | Mendez Marketing |
| Client | client2@pubads.test | Martin Digital |

Demo also seeds 4 spaces (billboard, digital screen, bus shelter, metro panel — all in Paris), 2 campaigns with adsets, ads, bookings, payments, availability windows (Mar–Jun 2026), and default role permissions (~66 rows).
