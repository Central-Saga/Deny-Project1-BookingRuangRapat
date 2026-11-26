# Saga MeetSpace — Frontend Web Application

Frontend web untuk sistem booking ruang rapat Saga MeetSpace dibangun menggunakan **Next.js 16 (App Router)**, **React 19**, **TypeScript**, dan **Tailwind CSS**.

---

## Prasyarat

- Node.js versi 20 atau lebih baru
- npm versi 10 atau lebih baru

---

## Cara Menjalankan dari Nol

### 1. Masuk ke direktori frontend
```bash
cd frontend
```

### 2. Siapkan file environment
```bash
cp .env.example .env.local
```

### 3. Pasang dependensi
```bash
npm install
```

### 4. Jalankan server pengembangan
```bash
npm run dev
```

Aplikasi web dapat diakses pada browser di [http://localhost:3000](http://localhost:3000).

---

## Menjalankan Pengujian & Build Produksi

- **Unit & Integration Tests (Vitest):**
  ```bash
  npm test
  ```
- **Build Produksi:**
  ```bash
  npm run build
  npm start
  ```

---

## Akun Demo untuk Login

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Karyawan | `karyawan@example.com` | `password` |

Waktu estimasi instalasi dan siap login: **≤ 3 menit**.
