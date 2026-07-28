# Order Tracker

Point-of-sale & order management system — Laravel 13 + React 19 SPA.  
Built for small-to-medium businesses with Owner/Staff role separation.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3+ |
| API Auth | Sanctum (SPA sessions) |
| Frontend | React 19, TypeScript, Vite, Tailwind CSS 4 |
| Database | MySQL 8+ (or SQLite for local dev) |
| Queue | Laravel Queue (database) |
| Exports | Laravel Excel / DOMPDF |
| Activity Log | Spatie Activitylog |

---

## Quick Start (Local)

```bash
git clone <repo> /var/www/html/order-tracker
cd /var/www/html/order-tracker
git checkout single-tenant-free

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
git clone <repo> /var/www/html/order-tracker
cd /var/www/html/order-tracker
git checkout single-tenant-free

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

---

## License

Proprietary — internal business use.
