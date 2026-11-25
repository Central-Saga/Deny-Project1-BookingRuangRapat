# Dokumen Audit Kesiapan Sistem — Standar 496 (G0)

Dokumen ini mendokumentasikan spesifikasi akun uji, konfigurasi lingkungan, arsitektur sistem, dan daftar parameter audit Standar 496 untuk aplikasi **Saga MeetSpace — Sistem Booking Ruang Rapat**.

---

## 1. Identitas & Arsitektur Aplikasi

| Parameter | Keterangan |
|---|---|
| **Nama Aplikasi** | Saga MeetSpace — Sistem Booking Ruang Rapat |
| **Arsitektur** | Decoupled Monorepo (Backend API + Frontend Web) |
| **Backend** | Laravel 11 / PHP 8.3 (direktori: `backend/`) |
| **Frontend** | Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS (direktori: `frontend/`) |
| **Database** | PostgreSQL 16 (in-memory SQLite untuk unit & feature tests) |
| **Autentikasi** | Laravel Sanctum (Bearer Token dengan masa berlaku 1440 menit) |
| **Single Source of Truth Otorisasi** | Kolom `users.role` (`admin` atau `karyawan`) |

---

## 2. Akun Uji per Role (`seed_accounts`)

Sistem menyediakan dua akun uji bawaan yang dibuat melalui `backend/database/seeders/DatabaseSeeder.php`. Password untuk seluruh akun uji dikonfigurasi melalui environment variable `SEED_PASSWORD` (default: `password`).

| Role | Email | Password Env | Default Password | Status | Hak Akses Utama |
|---|---|---|---|---|---|
| **admin** | `admin@example.com` | `SEED_PASSWORD` | `password` | `active` | CRUD ruang rapat, manajemen pengguna (`/api/v1/users`), persetujuan/penolakan booking (`approve`/`reject`), override pembatalan booking seluruh user. |
| **karyawan** | `karyawan@example.com` | `SEED_PASSWORD` | `password` | `active` | Melihat ruangan aktif, melihat jadwal dan ketersediaan, mengajukan booking rapat, melihat dan membatalkan booking milik sendiri. |

### Konfigurasi SEED_PASSWORD
Variabel `SEED_PASSWORD` telah didefinisikan pada file:
- `.env.example` (root repository)
- `backend/.env.example` (backend Laravel)

Saat `php artisan db:seed` dijalankan, `DatabaseSeeder` membaca variabel `SEED_PASSWORD` dari environment atau menggunakan nilai default `password`.

---

## 3. Endpoint Uji Autentikasi

| Aksi | Method | Endpoint | Header / Payload | Respons Berhasil |
|---|---|---|---|---|
| **Login** | `POST` | `/api/login` | `{ "email": "admin@example.com", "password": "password" }` | `200 OK` + JSON token Sanctum & data user |
| **Cek Profil / Me** | `GET` | `/api/me` | `Authorization: Bearer <token>` | `200 OK` + profil user saat ini |
| **Logout** | `POST` | `/api/logout` | `Authorization: Bearer <token>` | `200 OK` (token dihapus permanen dari database) |

> **Catatan Keamanan:**
> - Rate limiter membatasi percobaan login maksimum 5 kali per menit per IP/akun (`throttle:5,1`).
> - Logout menghapus token dari tabel `personal_access_tokens` secara permanen sehingga token tidak dapat digunakan kembali.

---

## 4. Parameter Standar 496 yang Telah Diimplementasikan

Aplikasi telah memenuhi dan lulus automated tests untuk parameter-parameter audit Standar 496 berikut:

1. **Parameter 3, 5, 24 — SoftDeletes & Explicit Type Casts:**
   - Model `Room` dan `Booking` menggunakan trait `SoftDeletes`.
   - Seluruh atribut model memiliki type casting eksplisit (termasuk casting array untuk fasilitas ruangan).
2. **Parameter 4 — Atribut Ruangan Lengkap:**
   - Menyimpan atribut kapasitas (`capacity`), lokasi (`location`), fasilitas (`facilities`), dan deskripsi (`description`).
3. **Parameter 7, 16 — Route Versioning Prefix `/v1`:**
   - Seluruh endpoint API bisnis diprefiks dengan `/api/v1` (`/api/v1/rooms`, `/api/v1/bookings`, `/api/v1/users`).
4. **Parameter 10.2, 26 — Konfigurasi Expiration & Revocation Token Sanctum:**
   - `sanctum.expiration = 1440` menit (tidak abadi).
   - Logout menghapus token saat ini atau seluruh token perangkat user.
5. **Parameter 14 — FormRequest Dedicated:**
   - Validasi menggunakan FormRequest terpisah: `StoreRoomRequest`, `UpdateRoomRequest`, `CancelBookingRequest`, dan `FileUploadRequest`.
6. **Parameter 14, 21, 22 — Custom Validation Rules:**
   - `WithinWorkingHoursRule`: Membatasi jam operasional kantor 08:00–17:00 WITA dan menolak hari libur / weekend (HTTP 422).
   - `WithinRoomCapacityRule`: Menolak pemesanan ruang rapat jika jumlah peserta melebihi kapasitas ruangan (HTTP 422).
7. **Parameter 21 — Soft-Deleted Room Exclusion:**
   - Ruangan yang di-soft-delete tidak muncul di opsi pemesanan dan ditolak HTTP 422 jika diajukan booking.
8. **Parameter 21, 32 — Pencegahan IDOR via BookingPolicy:**
   - Karyawan hanya dapat membatalkan booking miliknya sendiri; percobaan membatalkan rapat user lain menghasilkan HTTP 403 Forbidden.
   - Admin memiliki wewenang override pembatalan.
9. **Parameter 28 — Whitelist CORS Origin:**
   - `backend/config/cors.php` mengizinkan origin frontend yang sah (`http://localhost:3000`, `http://127.0.0.1:3000`) dan melarang wildcard `*`.
10. **Parameter 29 — RateLimiter Throttle:**
    - Throttle 30 request per menit pada endpoint pemesanan dan pembatalan rapat (`throttle:30,1`).
11. **Parameter 34 — Uniform Error Response Schema:**
    - Seluruh kesalahan API diformat konsisten dengan skema `{ "success": false, "message": "...", "errors": [...] }`.
12. **Parameter 37 — Transaksi Database dengan Pessimistic Locking:**
    - `DB::transaction` dengan `lockForUpdate` mencegah tabrakan slot waktu pemesanan (race condition).
13. **Parameter 51 — Sanitasi Input XSS:**
    - Input judul dan agenda rapat disanitasi menggunakan `strip_tags` sebelum disimpan ke database.
14. **Parameter 52 — Double-Submit Protection:**
    - Tombol konfirmasi booking di antarmuka frontend langsung dinonaktifkan saat pengajuan dikirim untuk mencegah multiple request.

---

## 5. Verifikasi Pengujian Otomatis

Seluruh suite pengujian otomatis dapat dijalankan secara lokal dengan hasil sukses 100%:

- **Backend Feature & Unit Tests:**
  ```powershell
  cd backend
  php artisan test
  ```
  *Hasil:* 157 passed (825 assertions).

- **Frontend Vitest Unit Tests:**
  ```powershell
  cd frontend
  npm test
  ```
  *Hasil:* 5 test files passed (22 passed).
