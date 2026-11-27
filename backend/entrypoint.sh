#!/bin/sh
set -e

# ==============================================================================
# Backend Container Entrypoint Script (Audit 496 - Issue #182)
# ==============================================================================

echo "==> Memulai container MeetSpace backend..."

# 1. Pastikan symlink storage public terhubung (storage:link + permission)
if [ ! -L /var/www/public/storage ] && [ ! -d /var/www/public/storage ]; then
    echo "==> Menghubungkan symlink storage..."
    php artisan storage:link --force || true
elif [ -L /var/www/public/storage ] && [ ! -e /var/www/public/storage ]; then
    echo "==> Memperbaiki symlink storage yang terputus..."
    rm -f /var/www/public/storage
    php artisan storage:link --force || true
fi

# 2. Pastikan permission direktori writable oleh non-root user (www-data)
mkdir -p /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/app/public \
         /var/www/bootstrap/cache

# 3. Migrasi sebagai job rilis (BUKAN on-boot):
# Container TIDAK menjalankan migrasi otomatis saat boot untuk mencegah race condition
# saat aplikasi di-scale secara horizontal. Migrasi dijalankan terpisah via release job.

echo "==> Menjalankan perintah container: $@"
exec "$@"
