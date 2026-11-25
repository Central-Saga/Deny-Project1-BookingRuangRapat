# PRODUCT REQUIREMENTS DOCUMENT (PRD) — SAGA MEETSPACE

**Sistem Pengelolaan & Pemesanan Ruang Rapat Kantor Central Saga**  
**Arsitektur Decoupled: Laravel 11 REST API + Next.js 16 App Router + PostgreSQL 16**

| Metadata | Nilai |
|---|---|
| **Nama Produk** | Saga MeetSpace |
| **Dokumen** | Product Requirements Document (PRD) |
| **Versi** | 1.0 — Standar 496 Ready |
| **Organisasi** | Central Saga |
| **Zona Waktu Operasional** | Asia/Makassar (WITA) |
| **Jam Operasional Kantor** | 08:00 – 17:00 WITA (Senin – Jumat) |
| **Backend** | Laravel 11, PHP 8.3, Laravel Sanctum |
| **Frontend** | Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS |
| **Database** | PostgreSQL 16 |
| **Otorisasi** | Kolom `users.role` (`admin` dan `karyawan`) |

---

## 1. Latar Belakang & Masalah

Kantor operasional Central Saga memerlukan sistem pemesanan ruang rapat yang tertib, transparan, dan andal guna mengatasi kendala-kendala berikut:
1. **Konflik Jadwal (Overlapping Booking):** Tabrakan jadwal penggunaan ruangan akibat pemesanan ganda pada slot waktu yang bersamaan.
2. **Kelebihan Kapasitas Ruangan:** Rapat dihadiri oleh jumlah peserta yang melebihi daya tampung fisik ruangan sehingga mengurangi kenyamanan.
3. **Pemesanan di Luar Jam Operasional:** Pengajuan rapat di luar jam kerja resmi atau pada akhir pekan yang tidak terkoordinasi dengan pengelola gedung.
4. **Penyalahgunaan Akses & Pembatalan Sembarangan (IDOR):** Karyawan dapat membatalkan atau mengubah jadwal rapat milik divisi atau pimpinan lain.
5. **Kurangnya Jejak Audit:** Tidak adanya pencatatan formal mengenai siapa yang menyetujui atau menolak pemesanan rapat.

---

## 2. Peran Pengguna (User Roles)

Sistem membedakan dua hak akses utama menggunakan kolom `users.role` (tanpa paket eksternal):

| Role | Deskripsi & Tanggung Jawab |
|---|---|
| **Admin** | Pengelola fasilitas kantor. Berhak mengelola master data ruangan (tambah/ubah/hapus/upload foto), mengelola data pengguna kantor, meninjau seluruh pengajuan booking, menyetujui atau menolak booking, serta memiliki override hak untuk membatalkan rapat. |
| **Karyawan** | Staf umum kantor. Berhak melihat daftar ruangan aktif, memeriksa ketersediaan slot waktu pada kalender, mengajukan pemesanan ruang rapat baru, serta membatalkan booking miliknya sendiri bila rencana rapat berubah. |

---

## 3. Matriks Hak Akses (RBAC)

| Modul / Aksi | Admin | Karyawan |
|---|:---:|:---:|
| Login & Logout API | ✅ | ✅ |
| Melihat Profil Pribadi (`/api/me`) | ✅ | ✅ |
| Melihat Ruangan Aktif & Ketersediaan | ✅ | ✅ |
| Membuat / Mengubah / Soft-delete Ruangan | ✅ | ❌ |
| Mengelola Akun Pengguna (`/api/v1/users`) | ✅ | ❌ |
| Membuat Pengajuan Booking Rapat | ✅ | ✅ |
| Melihat Seluruh Booking Kantor | ✅ | ❌ (Hanya milik sendiri) |
| Menyetujui / Menolak Booking (`approve`/`reject`) | ✅ | ❌ |
| Membatalkan Booking Milik Sendiri | ✅ | ✅ |
| Membatalkan Booking Pengguna Lain (Override) | ✅ | ❌ (403 Forbidden) |

---

## 4. Kebutuhan Fungsional & Acceptance Criteria (AC)

### AC-01: Autentikasi Pengguna & Manajemen Token Sesi
- **Deskripsi:** Pengguna dapat masuk ke dalam sistem menggunakan email dan kata sandi yang valid melalui endpoint `POST /api/login`.
- **Kriteria Penerimaan:**
  1. Kredensial valid menghasilkan respons `200 OK` disertai token Sanctum dan objek data user.
  2. Kredensial tidak valid menghasilkan respons `401 Unauthorized` dengan pesan galat yang seragam.
  3. Percobaan login dibatasi dengan rate limiter maksimum 5 permintaan per menit (`throttle:5,1`).
  4. Permintaan `POST /api/logout` dengan token valid menghapus record token dari database secara permanen sehingga tidak dapat digunakan kembali.
  5. Masa berlaku token Sanctum dibatasi 1440 menit (`sanctum.expiration = 1440`).

### AC-02: Role-Based Access Control (RBAC) & Proteksi Mass Assignment
- **Deskripsi:** Hak akses pengguna didasarkan sepenuhnya pada kolom `users.role` dan terlindungi dari manipulasi parameter.
- **Kriteria Penerimaan:**
  1. Kolom sensitif `role` dan `status` tidak dimasukkan ke dalam properti `$fillable` model `User` untuk mencegah eskalasi hak akses mass-assignment.
  2. Akses ke route administratif (`/api/v1/rooms` method POST/PUT/DELETE, `/api/v1/users`) oleh pengguna ber-role `karyawan` wajib menghasilkan respons `403 Forbidden`.

### AC-03: Manajemen Data Ruang Rapat
- **Deskripsi:** Admin dapat mengelola informasi ruangan yang tersedia untuk rapat.
- **Kriteria Penerimaan:**
  1. Data ruangan mencakup nama, kapasitas minimum 1 orang, lokasi ruangan, deskripsi, fasilitas (array JSON), dan status aktif.
  2. Pembuatan ruangan divalidasi oleh `StoreRoomRequest` dan perubahan divalidasi oleh `UpdateRoomRequest`.
  3. Upload foto ruangan divalidasi oleh `FileUploadRequest` terhadap MIME types (`image/jpeg`, `image/png`, `image/webp`), dimensi gambar yang proporsional, serta ukuran berkas maksimal 2MB.

### AC-04: Pengecualian Ruangan Nonaktif & Soft-Deleted
- **Deskripsi:** Ruangan yang dinonaktifkan atau dihapus secara lunak (soft-deleted) tidak dapat digunakan untuk pemesanan.
- **Kriteria Penerimaan:**
  1. Endpoint publik ketersediaan dan opsi booking ruangan hanya menampilkan ruangan dengan `is_active = true` dan `deleted_at IS NULL`.
  2. Upaya pembuatan booking pada ruangan yang berstatus nonaktif atau terhapus menghasilkan penolakan `422 Unprocessable Entity`.

### AC-05: Batasan Jam Operasional Kantor 08:00–17:00 WITA
- **Deskripsi:** Pemesanan ruang rapat hanya diizinkan pada hari dan jam kerja operasional kantor.
- **Kriteria Penerimaan:**
  1. Waktu mulai dan waktu selesai rapat harus berada dalam rentang jam **08:00 hingga 17:00 WITA**.
  2. Pemesanan rapat pada hari Sabtu atau Minggu (akhir pekan) wajib ditolak oleh sistem.
  3. Pelanggaran jam operasional menghasilkan respons `422 Unprocessable Entity` yang dibangkitkan oleh aturan validasi kustom `WithinWorkingHoursRule`.

### AC-06: Pembatasan Peserta Sesuai Kapasitas Ruangan
- **Deskripsi:** Jumlah peserta rapat tidak boleh melebihi daya tampung fisik ruangan yang dipesan.
- **Kriteria Penerimaan:**
  1. Input `participants_count` wajib berupa bilangan bulat positif lebih besar dari 0.
  2. Apabila `participants_count` melebihi atribut `capacity` dari ruangan yang dipilih, pengajuan wajib ditolak dengan HTTP `422 Unprocessable Entity` oleh aturan validasi kustom `WithinRoomCapacityRule`.

### AC-07: Pencegahan Konflik Jadwal & Pessimistic Locking
- **Deskripsi:** Sistem mencegah terjadinya pemesanan ganda (double-booking) pada ruangan dan waktu yang sama.
- **Kriteria Penerimaan:**
  1. Sistem mendeteksi seluruh skenario bentrok waktu: rentang waktu identik, tumpang tindih waktu mulai (overlap start), tumpang tindih waktu selesai (overlap end), serta rentang waktu yang mencakup pemesanan lain (enclosing).
  2. Pengecekan dan penyimpanan jadwal dieksekusi di dalam `DB::transaction` menggunakan pessimistic locking (`lockForUpdate`) guna mencegah race-condition akibat konkurensi tinggi.
  3. Pemesanan yang bertabrakan dengan jadwal aktif lain (`pending` atau `approved`) ditolak dengan respons galat yang jelas. Booking yang telah berstatus `cancelled` atau `rejected` tidak memblokir slot jadwal.

### AC-08: Pengajuan Pemesanan Ruang Rapat
- **Deskripsi:** Karyawan dapat mengajukan pemesanan ruang rapat melalui endpoint `POST /api/v1/bookings`.
- **Kriteria Penerimaan:**
  1. Payload pemesanan wajib memuat: `room_id`, `title`, `agenda`, `start_time`, `end_time`, dan `participants_count`.
  2. Sistem memvalidasi bahwa `end_time` harus bernilai lebih besar daripada `start_time` dan berada di masa depan (`after:now`).
  3. Pengajuan yang berhasil disimpan memiliki status default `pending`.

### AC-09: Sanitasi Input Judul & Agenda Rapat terhadap XSS
- **Deskripsi:** Sistem mensterilkan input teks bebas guna mencegah celah keamanan Cross-Site Scripting (XSS).
- **Kriteria Penerimaan:**
  1. Input teks pada kolom `title` dan `agenda` disanitasi menggunakan `strip_tags` dan `trim` melalui mutator model `Booking` serta FormRequest.
  2. Tag HTML berbahaya seperti `<script>`, `<iframe>`, atau `onload` dihapus secara otomatis sebelum data tersimpan ke basis data.

### AC-10: Persetujuan & Penolakan Booking oleh Admin
- **Deskripsi:** Admin meninjau pengajuan booking dan memberikan keputusan persetujuan atau penolakan.
- **Kriteria Penerimaan:**
  1. Admin dapat menyetujui pemesanan melalui `POST /api/v1/bookings/{id}/approve` (status berubah menjadi `approved`).
  2. Admin dapat menolak pemesanan melalui `POST /api/v1/bookings/{id}/reject` dengan menyertakan catatan penolakan (status berubah menjadi `rejected`).
  3. Sistem mencatat jejak audit `approved_by` (ID admin penilai) dan `decided_at` (waktu keputusan).

### AC-11: Pembatalan Pemesanan & Proteksi IDOR
- **Deskripsi:** Karyawan hanya dapat membatalkan pemesanan miliknya sendiri, dan wajib menyertakan alasan pembatalan.
- **Kriteria Penerimaan:**
  1. Karyawan yang mencoba membatalkan booking milik pengguna lain ditolak dengan respons `403 Forbidden` (`BookingPolicy::cancel`).
  2. Admin memiliki hak override untuk membatalkan booking milik pengguna manapun.
  3. Pembatalan dilakukan melalui `POST /api/v1/bookings/{id}/cancel` dan divalidasi oleh `CancelBookingRequest`, di mana kolom `reason` wajib diisi.

### AC-12: Pencarian & Filter Jadwal Pemesanan
- **Deskripsi:** Pengguna dapat mencari riwayat dan jadwal pemesanan berdasarkan parameter filter.
- **Kriteria Penerimaan:**
  1. Endpoint `GET /api/v1/bookings` mendukung query parameter `date=YYYY-MM-DD` untuk menyaring jadwal pada tanggal tertentu.
  2. Endpoint mendukung query parameter `room_id` untuk menyaring jadwal ruangan spesifik.
  3. Respons menyertakan metadata pagination yang baku (`current_page`, `per_page`, `total`, `last_page`).

### AC-13: Rate Limiting & Throttle API
- **Deskripsi:** Mencegah penyalahgunaan dan serangan denial of service pada endpoint pemesanan.
- **Kriteria Penerimaan:**
  1. Endpoint pembuatan booking (`POST /api/v1/bookings`) dan pembatalan (`POST /api/v1/bookings/{id}/cancel`) dibatasi maksimum 30 request per menit per user (`throttle:30,1`).
  2. Permintaan yang melampaui ambang batas menghasilkan respons `429 Too Many Requests`.

### AC-14: Skema Respons Galat Seragam (Uniform Error Response)
- **Deskripsi:** Seluruh respons galat API mengikuti format envelope JSON yang terstandarisasi.
- **Kriteria Penerimaan:**
  1. Seluruh error HTTP (401, 403, 404, 409, 422, 429, 500) mengembalikan struktur:
     ```json
     {
       "success": false,
       "message": "Pesan deskripsi kesalahan yang informatif",
       "errors": {}
     }
     ```
  2. Pada environment produksi (`APP_DEBUG=false`), detail trace internal error 500 disembunyikan dari respons demi keamanan.

### AC-15: Antarmuka Kalender Interaktif & Proteksi Double-Submit
- **Deskripsi:** Antarmuka web Next.js menyajikan jadwal secara visual dan mencegah pengiriman form berulang.
- **Kriteria Penerimaan:**
  1. Komponen kalender menampilkan status ketersediaan ruang rapat secara real-time.
  2. Formulir pemesanan dilengkapi validasi skema sisi klien (Zod) yang sinkron dengan aturan backend.
  3. Tombol konfirmasi pemesanan dilengkapi proteksi double-submit: tombol langsung dinonaktifkan (`disabled`) dan menampilkan indikator loading saat proses pengiriman berlangsung.
