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
│   │       └── RolePermission.php       # RBAC with caching
│   ├── bootstrap/
│   │   └── app.php                  # statefulApi(), role + permission middleware
│   ├── config/
│   │   ├── cors.php                 # CORS: allow localhost:4200, credentials: true
│   │   └── sanctum.php              # Stateful domains incl. localhost:4200
│   ├── database/
│   │   ├── migrations/              # 20 total (users, cache, jobs, tokens + 16 domain)
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
├── frontend/                        # Angular 18 SPA (scaffold only — no custom code yet)
│   ├── src/
│   │   └── app/                     # Default scaffold (app.component, app.routes, app.config)
│   └── angular.json
│
├── AGENTS.md                        # Agent onboarding (beads)
├── ARCHITECTURE.md                  # This file
├── CLAUDE.md                        # Agent instructions
├── README.md                        # Project readme
├── history.md                       # Session diary
└── install_beads.md                 # bd CLI install guide
```

### Planned Frontend Structure (not yet created)

```
frontend/src/app/
├── core/
│   ├── guards/                      # AuthGuard, RoleGuard
│   ├── interceptors/                # TokenInterceptor (Sanctum token)
│   └── services/                    # AuthService, ApiService
├── features/
│   ├── auth/                        # login, register pages
│   ├── client/                      # campaigns, adsets, ads, bookings, space search
│   ├── provider/                    # spaces, photos, availabilities, bookings, dashboard
│   └── manager/                     # CRM user management
└── shared/
    ├── components/                  # map, media-preview, booking-card
    └── models/                      # TypeScript interfaces
```

---

## Database Schema

> PostgreSQL 17 local **port 5434** — database: `pub_ads_mar` — user: postgres (trust auth, no password)

```
users
  id, name, email, password, role (enum: client|provider|manager),
  phone, company_name, address, is_active, remember_token, timestamps

personal_access_tokens  [Sanctum]
  id, tokenable_type, tokenable_id, name, token (hash),
  abilities, last_used_at, expires_at, timestamps

campaigns  [belongs to: user(client)]
  id, user_id, name, description, status, start_date, end_date, budget, timestamps

adsets  [belongs to: campaign]
  id, campaign_id, name, latitude, longitude, location_name, radius_km, status, timestamps

ads  [belongs to: adset]
  id, adset_id, name, media_type (enum: image|video|sound|gif),
  file_path, file_name, timestamps

spaces  [belongs to: user(provider)]
  id, user_id, name, type, latitude, longitude, price_per_day,
  width, height, is_active, timestamps

space_photos  [belongs to: space]
  id, space_id, file_path, file_name, is_primary, sort_order, timestamps

space_availabilities  [belongs to: space]
  id, space_id, start_date, end_date,
  status (enum: available|booked|blocked), timestamps

bookings  [belongs to: client_user + space + adset]
  id, client_user_id, space_id, adset_id,
  start_date, end_date, total_price,
  status (enum: pending|confirmed|cancelled|rejected), timestamps

payments  [belongs to: booking]
  id, booking_id, amount, status,
  payment_method (mocked), transaction_id, timestamps

conversations  [belongs to: space + client_user + provider_user]
  id, space_id, client_user_id, provider_user_id,
  unique(space_id, client_user_id), timestamps

messages  [belongs to: conversation + sender_user]
  id, conversation_id, sender_user_id, body, is_read, timestamps
```

### Relationship Map

```
User (client)  ──< Campaign ──< Adset ──< Ad
User (provider) ──< Space ──< SpacePhoto
                          └──< SpaceAvailability
Space + Adset + User(client) ──> Booking ──> Payment
Space + User(client) + User(provider) ──> Conversation ──< Message
```

---

## API Routes

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
GET|DELETE            /api/client/campaigns/{campaign}/adsets/{adset}/ads/{ad}
GET|POST              /api/client/bookings
GET|PUT               /api/client/bookings/{booking}
GET                   /api/client/spaces/search

# Provider routes — middleware: auth:sanctum, role:provider
GET|POST              /api/provider/spaces
GET|PUT|DELETE        /api/provider/spaces/{space}
POST                  /api/provider/spaces/{space}/photos
DELETE                /api/provider/spaces/{space}/photos/{photo}
GET|POST              /api/provider/spaces/{space}/availabilities
DELETE                /api/provider/spaces/{space}/availabilities/{availability}
GET                   /api/provider/bookings
PUT                   /api/provider/bookings/{booking}
GET                   /api/provider/dashboard

# Manager routes — middleware: auth:sanctum, role:manager
GET|POST              /api/manager/users
GET|PUT|DELETE        /api/manager/users/{user}

# Shared — middleware: auth:sanctum
GET|POST              /api/conversations
GET                   /api/conversations/{conversation}/messages
POST                  /api/conversations/{conversation}/messages
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
| Domain migrations (×10) | ✅ Done | campaigns → messages, all FK constraints |
| User model | ✅ Done | HasApiTokens + role helpers + relationships |
| Eloquent models (×10) | ✅ Done | All with relationships and casts |
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
| Angular features | ⏳ Pending | Not started |

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
