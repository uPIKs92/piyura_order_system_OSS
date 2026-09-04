# Order Tracker

Point-of-sale & order management system — Laravel 13 + React 19 SPA.  
Built for small-to-medium businesses with Owner/Staff role separation, Google Sheets sync, and installable PWA support.

---

## Features

### ✅ Phase 1 — Foundation (Complete)
- **Authentication:** Sanctum SPA login/logout, rate limiting (5/min lockout)
- **Password Reset:** Owner-initiated reset for Staff accounts
- **Role-Based Access Control:** Owner (full) / Staff (read-only products, no user mgmt)
- **User Management:** Owner-only CRUD, active/inactive toggle
- **Product & Category Management:** CRUD with Staff read-only access

### ✅ Phase 2 — Orders & Operations (Complete)
- **Orders CRUD:** Draft → Pending → Diproses → Dikirim → Selesai / Cancelled state machine
- **Stock & Price Snapshots:** Cost/price frozen per line item at order time
- **Optimistic Locking:** `version` column prevents lost updates (409 on conflict)
- **Draft Auto-Save:** 7-day expiry with `orders:auto-cancel` cleanup job
- **Payments:** Multi-payment per order, partial/overpaid/change tracking
- **Google Sheets Sync:** Outbox-based reliable sync (FR7), OAuth2 connect flow
- **Reports & Dashboard:** Revenue, tax (PPN), best-selling, top staff analytics
- **Backup & Import:** Owner data export, bulk product/category import
- **Invoices & Receipts:** PDF generation, thermal receipt printing
- **Tax Report:** UI-driven report generation
- **Activity Logging:** Audit trail via Spatie Activitylog
- **Security Hardening:** Security headers, PII export controls, logo validation

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3+ |
| API Auth | Sanctum (SPA sessions) |
| Frontend | React 19, TypeScript, Vite, Tailwind CSS 4 |
| Database | MySQL 8+ (or SQLite for local dev) |
| API Auth | Sanctum (SPA tokens) |
| Frontend | React 19, react-router-dom, shadcn/ui, Tailwind v4 |
| Database | MySQL 8+ (22 tables) |
| Queue | Laravel Queue (database) |
| Exports | Laravel Excel / DOMPDF |
| Activity Log | Spatie Activitylog |
| External | Google Sheets API (OAuth2), Google Service Account |

---

## Quick Start (Local)

```bash
git clone https://github.com/uPIKs92/piyura_order_system_OSS.git order-tracker
cd order-tracker

cp .env.example .env
# Edit .env: DB_DATABASE=order_tracker, DB_USERNAME=ot_user (or keep sqlite)

composer install
npm install && npm run build

php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8084
```

**Default owner login:** `owner@localhost` / `password`  
Override via `SEED_OWNER_EMAIL` and `SEED_OWNER_PASSWORD` in `.env` before seeding.

---

## Deployment (Free Self-Hosted Edition)

Single-tenant, self-hosted. One host, one shop. No SaaS, no Turnstile, no HQ.

### Requirements
- PHP 8.3+, Composer
- Node 20+, npm
- MySQL 8+ (or MariaDB 10.6+)
- Nginx (or Apache) + PHP-FPM
- Domain pointing at the server (single host — no wildcard subdomains)

### Steps

```bash
git clone https://github.com/uPIKs92/piyura_order_system_OSS.git order-tracker
cd order-tracker

cp .env.example .env
```

Edit `.env`:

```dotenv
APP_URL=https://your-domain.com
APP_ENV=production
DB_DATABASE=order_tracker
DB_USERNAME=ot_user
DB_PASSWORD=<secret>
SESSION_DOMAIN=your-domain.com
SANCTUM_STATEFUL_DOMAINS=your-domain.com
# Initial shop seed — applied by `php artisan migrate --seed` on a fresh database
SEED_TENANT_SLUG=default
SEED_TENANT_NAME="My Shop"
SEED_OWNER_EMAIL=owner@your-domain.com
SEED_OWNER_PASSWORD=<strong-password>
```

Then:

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build

php artisan key:generate
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### Nginx server block (single host)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/order-tracker/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

HTTPS via Certbot: `certbot --nginx -d your-domain.com`

### Default login

Use `SEED_OWNER_EMAIL` / `SEED_OWNER_PASSWORD` from `.env`. Change password after first login via owner user settings.

### Queue + scheduler (optional)

For sheets sync, auto-cancel, backups:

```bash
php artisan queue:work --daemon
```

Cron:

```cron
* * * * * cd /var/www/html/order-tracker && php artisan schedule:run >> /dev/null 2>&1
```

### Backups

`php artisan backup:run` (Spatie backup). Configure disk in `config/backup.php`.

---

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Login (rate-limited: 5/min) |
| POST | `/api/password/reset` | Execute password reset |
| GET | `/api/public/tenant` | Current shop branding |

### Authenticated (All Roles)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/logout` | Logout |
| GET | `/api/user` | Current user info |
| GET | `/api/categories` | List categories |
| GET | `/api/products` | List products |

### Owner Only
| Method | Endpoint | Description |
|--------|----------|-------------|
| CRUD | `/api/users` | User management |
| CRUD | `/api/categories`, `/api/products` | Catalog management |
| GET | `/api/tenant/export` | Export shop data |

---

## RBAC Matrix

| Feature | Owner | Staff |
|---------|-------|-------|
| Manage Staff | ✅ | ❌ |
| Create/Edit/Delete Products | ✅ | ❌ |
| View Products | ✅ | ✅ |
| Place Orders | ✅ | ✅ |
| Manage Settings | ✅ | ❌ |
## Architecture

- **Backend:** Controllers `app/Http/Controllers/Api/`, Policies `app/Policies/`, Services `app/Services/`
- **Frontend:** React 19 SPA, Blade shell (`app.blade.php`), shadcn/ui + Tailwind v4
- **State Management:** React Query / local state via Fetch API through ApiProvider
- **Single-Tenant Core:** Schema keeps `tenant_id` columns as internal invariants with a no-op global scope — one shop per install
- **Reliable Sync:** Outbox pattern (`sheets_sync_outbox`) — no data loss on partial failures
- **Scheduled Jobs:** `sheets:dispatch` (5 min), `orders:auto-cancel` (hourly) — both `withoutOverlapping`
- **Mobile-First:** iOS HCI bottom tabs, glassmorphism, safe-area handling, PWA installable

---

## Documentation

| File | Description |
|------|-------------|
| [Docs Index](./docs/README.md) | Master index — status-coded map of all docs (reference / active / future / archived) |
| [PRD](./docs/PRD.md) | Product Requirements Document |
| [Architecture](./docs/ARCHITECTURE.md) | System architecture & data flow |
| [DB Schema](./docs/DATABASE.md) | Database schema & relationships |
| `docs/features/` | Per-feature implementation details |

---

## Deployment

Production-ready via Nginx reverse-proxy (port 8084).  
See `docs/environment-migration.md` for server setup.

---

## License

Released under the [GNU General Public License v3.0](./LICENSE).  
Copyright (C) 2026 Taufik Kurniawan