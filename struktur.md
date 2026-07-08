# Struktur Proyek AKTA IAT

## Stack Teknologi

| Layer      | Teknologi                                        |
|------------|--------------------------------------------------|
| Backend    | Laravel 11 (PHP 8.2+)                            |
| Auth       | Laravel Sanctum (Bearer Token)                   |
| Frontend   | Blade + Vanilla JS (ES Modules via Vite)         |
| CSS        | Tailwind CSS                                     |
| Build Tool | Vite                                             |
| Database   | MySQL / MariaDB                                  |
| PDF        | DomPDF (via `barryvdh/laravel-dompdf`)           |

---

## Struktur Folder Utama

```
Akta_Iat/
├── app/
│   ├── Console/Commands/          # Artisan commands
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/               # Controller API (JSON response)
│   │   │   │   ├── Admin/         # Khusus role admin
│   │   │   │   └── *.php          # Controller per modul
│   │   │   ├── Auth/              # Auth Breeze controllers
│   │   │   ├── ReportPdfController.php
│   │   │   └── ProfileController.php
│   │   ├── Middleware/
│   │   │   └── EnsureAktaRole.php # Middleware cek role (akta.role)
│   │   └── Requests/
│   ├── Models/                    # Eloquent Models
│   ├── Services/                  # Business logic layer
│   │   ├── ActivityLogger.php     # Log aktivitas user
│   │   ├── AktaMenuService.php    # Build menu per role
│   │   ├── AppDataStore.php       # Key-value data store
│   │   ├── BirokrasiResolver.php  # Mapping cabang → approver steps
│   │   └── PlanTaskService.php    # Sinkronisasi task dari plan
│   └── Support/
│       └── DataKeys.php           # Konstanta key app_data
│
├── config/
│   ├── birokrasi.php              # 21 grup birokrasi (units + approvers)
│   └── ...
│
├── database/
│   └── migrations/                # Semua migrasi tabel
│
├── resources/
│   ├── js/
│   │   ├── app.js                 # Entry point Vite
│   │   ├── akta-shell.js          # Init global (auth, menu, sidebar)
│   │   ├── akta-plan-audit.js     # Halaman Plan Audit
│   │   ├── akta-task.js           # Halaman Task
│   │   ├── akta-audit.js          # Halaman Audit (detail pemeriksaan)
│   │   ├── akta-rekomendasi.js    # Halaman Rekomendasi
│   │   ├── akta-sk.js             # Halaman Surat Keputusan
│   │   ├── akta-pica.js           # Halaman PICA
│   │   ├── akta-grading.js        # Halaman Grading
│   │   ├── akta-report-audit.js   # Halaman Report Audit
│   │   ├── akta-dashboard.js      # Halaman Dashboard
│   │   ├── akta-database.js       # Halaman Database Master
│   │   ├── akta-users.js          # Halaman Manajemen User (admin)
│   │   ├── akta-monitoring.js     # Halaman Monitoring (admin)
│   │   ├── akta-bu-performance.js # Halaman BU Performance
│   │   ├── akta-profile.js        # Halaman Profil
│   │   └── akta-auth.js           # Halaman Login
│   │
│   └── views/
│       ├── akta/
│       │   ├── layouts/app.blade.php      # Layout utama (sidebar + topbar)
│       │   ├── login.blade.php
│       │   ├── dashboard.blade.php
│       │   ├── pages/                     # Halaman per fitur
│       │   │   ├── audit.blade.php
│       │   │   ├── plan-audit.blade.php
│       │   │   ├── task.blade.php
│       │   │   ├── rekomendasi.blade.php
│       │   │   ├── sk.blade.php
│       │   │   ├── pica.blade.php
│       │   │   ├── grading.blade.php
│       │   │   ├── report-audit.blade.php
│       │   │   ├── database.blade.php
│       │   │   ├── users.blade.php
│       │   │   ├── monitoring.blade.php
│       │   │   ├── bu-performance.blade.php
│       │   │   └── profile.blade.php
│       │   ├── partials/
│       │   │   ├── sidebar.blade.php
│       │   │   └── topbar.blade.php
│       │   └── pdf/
│       │       └── report-audit.blade.php
│       └── ...
│
├── routes/
│   ├── api.php                    # Semua route API (/api/*)
│   └── web.php                    # Route web (SPA shell + auth)
│
└── public/
    └── build/                     # Output Vite (setelah npm run build)
```

---

## Role & Akses

| Role           | Keterangan                                      |
|----------------|-------------------------------------------------|
| `admin`        | Akses penuh semua fitur + manajemen user/menu   |
| `manajer`      | Buat plan, approve SK, approve rekomendasi      |
| `auditor`      | Input semua data pemeriksaan, buat rekomendasi  |
| `koordinator`  | Alur birokrasi plan                             |
| `coo`          | Alur birokrasi plan / pinjaman cabang           |
| `bpk`          | Alur pinjaman cabang                            |
| `unit`         | Alur pinjaman cabang                            |
| `h1`           | Kepala wilayah / approver pinjaman cabang       |
| `unit_usaha`   | User cabang — isi rekomendasi sesuai birokrasi  |

Middleware `akta.role` digunakan pada route API untuk membatasi akses berdasarkan role.

---

## Alur Autentikasi

1. User `POST /api/auth/login` → dapat Bearer Token (Sanctum)
2. Token disimpan di `localStorage` (key: `akta_token`)
3. Semua request API menyertakan header `Authorization: Bearer <token>`
4. `GET /api/auth/me` → mendapat data user aktif (role, unit_usaha, dll)
5. Frontend memfilter tampilan tombol/aksi berdasarkan role user

---

## Modul & Fitur

### 1. Plan Audit
- Buat & kelola rencana audit (No. SPT, cabang, tim, tgl mulai-selesai)
- Alur status: `draft` → `pending_koordinator` → `pending_manajer` → `active` → `done`
- Log perubahan status di `plan_audit_logs`

### 2. Task
- Task terhubung ke plan audit
- Status: `todo` → `in_progress` → `done`
- Eksekusi task: input tgl mulai/selesai + lampiran

### 3. Rekomendasi
- Rekomendasi hasil audit oleh auditor
- Birokrasi sequential: setiap pihak (SO, RSS, Manajer IAT, dll) mengisi keputusannya sendiri
- Field `steps` (JSON) menyimpan history pengisian per pihak
- Status: `draft` → `open` → `in_progress` → `waiting_approval` → `approved` → `done`

### 4. Surat Keputusan (SK)
- Upload SK oleh auditor
- Alur approval: Manajer IAT → AFD

### 5. PICA
- Problem Identification & Corrective Action
- Terhubung ke rekomendasi dan task

### 6. Pemeriksaan (Audit Detail)
Modul pemeriksaan yang tersedia dalam satu halaman audit:
- **Kas** — saldo fisik vs buku
- **Bank** — saldo bank vs buku per rekening
- **SMH** (Stok Motor Honda) — scan fisik unit
- **Perlengkapan** — stok perlengkapan SMH
- **Materai** — saldo vs fisik per jenis
- **BPKB Onhand** — upload & scan BPKB
- **BPKB Inproses** — BPKB dalam proses
- **Kwitansi Gantung** — upload data kwitansi
- **Piutang Reguler** — upload data piutang
- **Piutang CDN** — piutang consignment
- **TTP Gantung** — titipan tanda persetujuan
- **Cek Fisik** — CF / STUJ / F.STNK
- **MT** (Mechanical Tools) — cek peralatan bengkel
- **HGP** (Harga Ganti Part) — saldo vs fisik spare part
- **HGA** (Harga Ganti Accessories) — accessories
- **SMH Tarikan** — motor tarikan
- **Grading** — penilaian cabang
- **Lampiran** — upload & merge PDF

### 7. Report Audit
- Ringkasan hasil audit per plan
- Export PDF via DomPDF

### 8. Database Master
- Data referensi: Unit Usaha, SMH (harga), Plafon, Perlengkapan, Grading, MT, HET

### 9. BU Performance
- Penilaian performa unit usaha per bulan

### 10. Pinjaman Cabang (BPK/BPB)
- Alur approval multi-level: Koordinator → Manajer → COO → Unit → BPK

### 11. Admin
- Manajemen user, role, menu
- Monitoring sistem & activity log

---

## Birokrasi Rekomendasi

Konfigurasi di `config/birokrasi.php`. Terdapat 21 grup wilayah.
Setiap grup mendefinisikan:
- `units[]` — daftar cabang (unit usaha) yang terdampak
- `approvers[]` — daftar role yang harus mengisi secara berurutan

Service `BirokrasiResolver` memetakan cabang → steps awal saat rekomendasi dibuat.

---

## Catatan Build

Setiap perubahan file JS harus di-build ulang:
```bash
npm run build
```

Untuk development:
```bash
npm run dev
```
