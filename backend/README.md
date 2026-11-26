# Saga MeetSpace — Backend REST API

Backend API untuk sistem booking ruang rapat Saga MeetSpace dibangun menggunakan **Laravel 11**, **PHP 8.3**, **PostgreSQL 16**, dan **Laravel Sanctum**.

---

## Prasyarat

- PHP versi 8.3 dengan ekstensi pdo_pgsql, pgsql, mbstring, bcmath
- Composer versi 2
- PostgreSQL 16 (atau SQLite untuk pengetesan lokal)

---

## Cara Menjalankan dari Nol

### 1. Masuk ke direktori backend
```bash
cd backend
```

### 2. Salin environment configuration
```bash
cp .env.example .env
```

### 3. Pasang dependensi
```bash
composer install
```

### 4. Buat kunci aplikasi (Application Key)
```bash
php artisan key:generate
```

### 5. Jalankan migrasi dan seeder database
```bash
php artisan migrate --seed --force
```

### 6. Jalankan server lokal
```bash
php artisan serve --port=8000
```

API dapat diakses di [http://localhost:8000/api](http://localhost:8000/api).

---

## Akun Demo Pengujian

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Karyawan | `karyawan@example.com` | `password` |

---

## Pengujian Otomatis

Jalankan seluruh test suite dengan zero-config (in-memory SQLite):
```bash
php artisan test
```

Waktu estimasi instalasi dan siap melayani request login: **≤ 5 menit**.
