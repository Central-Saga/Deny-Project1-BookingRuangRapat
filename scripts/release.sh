#!/bin/sh
set -e

# ==============================================================================
# Monorepo Release Script (Audit 496 - Issue #182)
# ==============================================================================

echo "==> Menjalankan migrasi database sebagai release job terpisah..."
cd backend
php artisan migrate --force

echo "==> Menghubungkan storage link..."
php artisan storage:link --force || true

echo "==> Release phase selesai."
