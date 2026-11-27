#!/bin/sh
set -e

# ==============================================================================
# Release Phase Job (Audit 496 - Issue #182)
# ==============================================================================
# Dijalankan sekali saat proses deployment (release phase), terpisah dari boot container web.
# ==============================================================================

echo "==> Menjalankan Release Job: Migrasi Database..."
php artisan migrate --force

echo "==> Menjalankan optimasi konfigurasi dan rute..."
php artisan config:cache || true
php artisan route:cache || true

echo "==> Release Job selesai dengan sukses."
