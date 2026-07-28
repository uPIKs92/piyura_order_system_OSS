#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "Ensuring storage and bootstrap/cache are writable..."

if [[ "$(id -u)" -eq 0 ]]; then
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R ug+rwx storage bootstrap/cache
else
  if groups | tr ' ' '\n' | grep -qx www-data; then
    chmod -R g+rwX storage bootstrap/cache 2>/dev/null || true
  fi
  if command -v setfacl >/dev/null 2>&1; then
    setfacl -R -m "u:$(whoami):rwX" -m "d:u:$(whoami):rwX" storage bootstrap/cache 2>/dev/null || true
    setfacl -R -m "u:www-data:rwX" -m "d:u:www-data:rwX" storage bootstrap/cache 2>/dev/null || true
  fi
fi

php artisan config:clear

echo "Local dev setup done."
echo "HQ:    http://admin.localhost:8084/login"
echo "Shop:  http://{slug}.localhost:8084/login"
