# Deployment Guide

## Pre-deploy

```bash
php artisan down --retry=60 --render=errors::503
```

Set `MAINTENANCE_ALLOWED_IPS` in `.env` (comma-separated) so owner IPs bypass maintenance mode via `AllowOwnerIpDuringMaintenance` middleware.

## Deploy steps

```bash
git pull origin master
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan pwa:icons        # regenerate PWA icon matrix (Android + iOS)
php artisan pwa:splash       # regenerate iOS launch images per device
php artisan queue:restart
php artisan up
```

> **PWA assets**: `public/manifest.json`, `public/sw.js`, `public/icons/*`, and
> `public/icons/splash/*` are committed, so a normal deploy already serves them.
> Re-run `pwa:icons` + `pwa:splash` only if branding (`config/branding.php`) or
> the icon design changed. The service worker caches are versioned (`VERSION`
> constant in `public/sw.js`); bump that string when you ship changes that
> should evict old client caches.

## Health check

Load balancer should probe:

```
GET /api/health
```

Returns `200` with `{"status":"ok"}`.

## Rollback

1. `php artisan down`
2. Restore previous release / git checkout
3. `php artisan migrate` if needed
4. `php artisan up`

## Scheduler (production)

Ensure cron runs:

```
* * * * * cd /var/www/html/order-tracker && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker:

```
php artisan queue:work database --sleep=3 --tries=3
```

### Local development

See **[LOCAL_DEV.md](LOCAL_DEV.md)** for nginx, `.env`, cookies, and troubleshooting on `*.localhost:8084`.

### Production domain (wildcard subdomains)

See **[PRODUCTION.md](PRODUCTION.md)** for DNS, TLS, nginx, `.env`, first deploy, and verification on a real domain.

Quick reference:

```
*.your-domain.com  A/AAAA  → VPS IP
your-domain.com    A/AAAA  → VPS IP
```

nginx: `deploy/nginx/order-tracker.production.conf` — `server_name your-domain.com *.your-domain.com;`

```env
APP_URL=https://your-domain.com
SESSION_DOMAIN=.your-domain.com
SESSION_SECURE_COOKIE=true
```

Shared apex (marketing on `sos`, HQ on `sos-admin`, apex owned by another app): see **[PRODUCTION.md §11](PRODUCTION.md#11-shared-apex-domain)** and `deploy/nginx/order-tracker.shared-apex.conf`.
