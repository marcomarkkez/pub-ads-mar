# pub-ads-mar

Web-based advertising spaces marketplace built with **Laravel 12** (API) and **Angular 18** (SPA). Clients create geolocated ad campaigns and book provider spaces. Providers post spaces with photos, availability, and calendar sync. Managers administer users via a CRM panel.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 (API only) |
| Frontend | Angular 18 SPA (standalone components, lazy-loaded routes) |
| Database | PostgreSQL 17 (port 5434, trust auth) |
| Auth | Laravel Sanctum (token-based, Bearer header) |
| Maps | Leaflet + what3words autosuggest |
| RBAC | Custom role_permissions table with cached lookups |
| Payments | Mocked (no real gateway for MVP) |

---

## Quick Start

### Prerequisites

- **PHP 8.2+** with `pdo_pgsql` and `pgsql` extensions enabled in `php.ini`
- **Composer** (PHP package manager)
- **Node.js 20+** / npm
- **PostgreSQL 17** running on port **5434** (this project uses port 5434, not the default 5432)

### 1. Database Setup

Make sure PostgreSQL 17 is running on port 5434. Create the database if it doesn't exist:

```bash
# Windows (use full path if psql is not on PATH)
"C:\Program Files\PostgreSQL\17\bin\psql.exe" -U postgres -h 127.0.0.1 -p 5434 -c "CREATE DATABASE pub_ads_mar;"

# Linux/Mac
psql -U postgres -h 127.0.0.1 -p 5434 -c "CREATE DATABASE pub_ads_mar;"
```

### 2. Backend Setup (first time only)

```bash
cd backend
composer install
cp .env.example .env
```

Edit `backend/.env` and set these values:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5434
DB_DATABASE=pub_ads_mar
DB_USERNAME=postgres
DB_PASSWORD=
```

Then run:

```bash
php artisan key:generate
php artisan migrate:fresh --seed   # Creates tables + demo data
php artisan storage:link           # Enables file uploads
```

### 3. Frontend Setup (first time only)

```bash
cd frontend
npm install
```

### 4. Start Both Servers

You need **two terminal windows** — both servers must be running at the same time:

**Terminal 1 — Backend API:**
```bash
cd backend
php artisan serve
# API running at http://localhost:8000
```

**Terminal 2 — Frontend SPA:**
```bash
cd frontend
npx ng serve
# App running at http://localhost:4200
```

Open **http://localhost:4200** in your browser. Log in with any demo account (see below).

---

## User Roles

| Role | Capabilities |
|------|-------------|
| **Client** | Create campaigns/adsets/ads, search spaces by geolocation, book spaces, chat with providers |
| **Provider** | Post spaces with photos + availability, sync calendars (iCal/Google), manage bookings, chat |
| **Manager** | CRM panel: create/edit/delete users, manage RBAC permissions |
| **Admin** | Full user CRUD + RBAC permissions editor |
| **Support** | View and respond to support tickets |
| **Payments** | Approve/reject payments and proof-of-display |

---

## Demo Accounts

Seeded with `php artisan migrate:fresh --seed`. Password for all: **`password`**

| Role | Email |
|------|-------|
| Admin | admin@pubads.test |
| Support | support@pubads.test |
| Payments | payments@pubads.test |
| Provider | provider1@pubads.test |
| Provider | provider2@pubads.test |
| Client | client1@pubads.test |
| Client | client2@pubads.test |

---

## Project Structure

```
pub-ads-mar/
├── backend/          # Laravel 12 API (fully implemented)
│   ├── app/Http/Controllers/   # 23 controllers (Auth, Client, Provider, Admin, Support, Payments, Shared)
│   ├── app/Models/             # 15 Eloquent models
│   ├── app/Services/           # IcalSyncService
│   ├── database/migrations/    # 20 migrations
│   ├── database/seeders/       # Demo data + RBAC permissions
│   └── routes/api.php          # 77+ routes with per-route permission middleware
│
├── frontend/         # Angular 18 SPA (fully implemented)
│   └── src/app/
│       ├── core/               # Guards, interceptors, services, models
│       ├── features/           # 8 feature modules (admin, auth, client, dashboard, payments, provider, shared, support)
│       └── shared/             # Navbar, sidebar, notification toast, layouts
│
├── design.md         # THE canonical doc — schema, API routes, specs, stories, dev commands
├── CLAUDE.md         # Agent instructions
└── history.md        # Session diary
```

---

## API Overview

```
POST /api/register, /api/login         Public
POST /api/logout, GET /api/me          Any authenticated

/api/client/*       Campaigns, adsets, ads, bookings, space search, invoices, collaborators
/api/provider/*     Spaces, photos, availabilities, bookings, dashboard, calendar sync, proofs
/api/admin/*        User CRUD + RBAC permissions
/api/support/*      Ticket management
/api/payments/*     Payment approval + proof review
/api/conversations/* Messaging (any authenticated)
/api/tickets/*      User-facing ticket CRUD
```

---

## Key Features

- **Geolocation search** with Leaflet maps and bounding-box filtering
- **iCal calendar sync** from Google Calendar or .ics file upload with keyword filtering
- **RBAC permissions** with 66+ default permissions, admin editor, and per-route middleware
- **Real-time chat** with message filtering (strips phone numbers, emails, URLs)
- **File uploads** for ad media (image/video/sound/gif) and space photos
- **Proof-of-display** workflow for providers to upload proof, payments team to review

---

## Documentation

- **[design.md](design.md)** — THE single canonical design doc: overview, roles, specs, decisions, database schema, API/endpoint map, role/object graph, dev commands, implementation status, and the verbose user stories (§19). Absorbed the former ARCHITECTURE.md / cases.md / platform-graph.md (2026-06-27).
- **[history.md](history.md)** — Session-by-session development diary
