# Database Realisasi — AKTA IAT

Dokumentasi skema database seluruh tabel beserta relasi, kolom kunci, dan nilai enum.

---

## Tabel Utama

### `users`

| Kolom             | Tipe           | Keterangan                                          |
|-------------------|----------------|-----------------------------------------------------|
| `id`              | bigint PK      |                                                     |
| `username`        | varchar(100)   | Unique, digunakan login                             |
| `name`            | varchar        | Nama lengkap                                        |
| `display_name`    | varchar(200)   | Nama tampil (opsional)                              |
| `email`           | varchar        | Unique                                              |
| `password`        | varchar        | Hash bcrypt                                         |
| `role`            | varchar(50)    | Lihat nilai di bawah                                |
| `unit_usaha`      | varchar(100)   | Nama cabang (untuk role unit_usaha)                 |
| `wilayah`         | varchar        | Wilayah user                                        |
| `photo`           | varchar        | Path foto profil                                    |
| `is_disabled`     | boolean        | User dinonaktifkan                                  |
| `plain_password`  | varchar        | Plain text sementara (untuk distribusi kredensial)  |
| `created_by`      | varchar(100)   |                                                     |
| `timestamps`      |                |                                                     |

**Nilai `role`:** `admin`, `manajer`, `auditor`, `koordinator`, `coo`, `bpk`, `unit`, `h1`, `unit_usaha`

---

### `plan_audits`

| Kolom          | Tipe         | Keterangan                                    |
|----------------|--------------|-----------------------------------------------|
| `id`           | bigint PK    |                                               |
| `no_spt`       | varchar(100) | Nomor SPT audit                               |
| `cabang`       | varchar(150) | Nama cabang yang diaudit                      |
| `cabang_area`  | varchar(150) | Area/wilayah cabang                           |
| `jenis_audit`  | varchar(100) | Jenis audit                                   |
| `tgl_mulai`    | date         |                                               |
| `tgl_selesai`  | date         |                                               |
| `tgl_plan`     | date         | Tanggal pembuatan plan                        |
| `kepala_tim`   | varchar(150) | Username kepala tim audit                     |
| `tim`          | json         | Array username anggota tim                    |
| `status`       | varchar(50)  | Lihat nilai di bawah                          |
| `keterangan`   | text         |                                               |
| `created_by`   | varchar(100) |                                               |
| `updated_by`   | varchar(100) |                                               |
| `timestamps`   |              |                                               |

**Nilai `status`:** `draft`, `pending_koordinator`, `pending_manajer`, `active`, `done`, `cancelled`

---

### `plan_audit_logs`

| Kolom          | Tipe         | Keterangan                     |
|----------------|--------------|--------------------------------|
| `id`           | bigint PK    |                                |
| `plan_audit_id`| bigint FK    | → plan_audits                  |
| `status_from`  | varchar      |                                |
| `status_to`    | varchar      |                                |
| `actor`        | varchar      | Username yang melakukan aksi   |
| `note`         | text         |                                |
| `timestamps`   |              |                                |

---

### `audit_tasks`

| Kolom          | Tipe         | Keterangan                            |
|----------------|--------------|---------------------------------------|
| `id`           | bigint PK    |                                       |
| `plan_audit_id`| bigint FK    | → plan_audits (nullable, nullOnDelete)|
| `judul`        | varchar(200) |                                       |
| `kategori`     | varchar(100) |                                       |
| `assigned_to`  | varchar(150) | Username auditor yang ditugaskan      |
| `priority`     | varchar(50)  | `low`, `normal`, `high`               |
| `status`       | varchar(50)  | `todo`, `in_progress`, `done`         |
| `due_date`     | date         |                                       |
| `completed_at` | timestamp    |                                       |
| `catatan`      | text         |                                       |
| `created_by`   | varchar(100) |                                       |
| `updated_by`   | varchar(100) |                                       |
| `timestamps`   |              |                                       |

---

### `audit_recommendations`

| Kolom          | Tipe         | Keterangan                                                 |
|----------------|--------------|------------------------------------------------------------|
| `id`           | bigint PK    |                                                            |
| `plan_audit_id`| bigint FK    | → plan_audits (nullable)                                   |
| `audit_task_id`| bigint FK    | → audit_tasks (nullable)                                   |
| `judul`        | varchar(300) | Judul singkat rekomendasi (max 250 karakter diisi dari JS) |
| `deskripsi`    | text         | Isi lengkap rekomendasi auditor                            |
| `kategori`     | varchar(100) | Kas, Bank, Piutang, BPKB, HGP, KWT, MT, Grading, dll      |
| `prioritas`    | varchar(50)  | `rendah`, `sedang`, `tinggi`, `urgent`                     |
| `status`       | varchar(50)  | Lihat nilai di bawah                                       |
| `pic`          | varchar(150) | Person In Charge                                           |
| `deadline`     | date         |                                                            |
| `tgl_selesai`  | date         | Otomatis diisi saat status = done                          |
| `steps`        | json         | Array riwayat pengisian birokrasi (lihat struktur di bawah)|
| `created_by`   | varchar(100) |                                                            |
| `updated_by`   | varchar(100) |                                                            |
| `approved_by`  | varchar(100) |                                                            |
| `approved_at`  | timestamp    |                                                            |
| `timestamps`   |              |                                                            |

**Nilai `status`:** `draft`, `open`, `in_progress`, `waiting_approval`, `approved`, `done`, `cancelled`

**Struktur kolom `steps` (JSON array):**
```json
[
  {
    "step": "created",
    "role": null,
    "status": "done",
    "user": "auditor01",
    "time": "2026-07-01",
    "note": "Rekomendasi dibuat."
  },
  {
    "step": "SO",
    "role": "SO ALB",
    "status": "pending",
    "user": null,
    "time": null,
    "note": null
  },
  {
    "step": "isi_rekomendasi",
    "role": "unit_usaha",
    "status": "done",
    "user": "pic_cabang",
    "time": "2026-07-05",
    "note": "Tindak lanjut sudah dilakukan..."
  }
]
```

---

### `surat_keputusan`

| Kolom             | Tipe         | Keterangan                            |
|-------------------|--------------|---------------------------------------|
| `id`              | bigint PK    |                                       |
| `plan_audit_id`   | bigint FK    | → plan_audits                         |
| `no_spt`          | varchar(80)  |                                       |
| `unit_usaha`      | varchar(150) |                                       |
| `jenis_audit`     | varchar(80)  |                                       |
| `no_sk`           | varchar(120) |                                       |
| `file_sk`         | json         | Array path file SK                    |
| `status`          | varchar(50)  | `pending_manajer`, `approved`, dll    |
| `steps`           | json         | Riwayat approval                      |
| `uploaded_by`     | varchar(100) |                                       |
| `uploaded_by_name`| varchar(150) |                                       |
| `timestamps`      |              |                                       |

---

### `picas`

| Kolom                    | Tipe         | Keterangan                                   |
|--------------------------|--------------|----------------------------------------------|
| `id`                     | bigint PK    |                                              |
| `audit_recommendation_id`| bigint FK    | → audit_recommendations (nullable)           |
| `plan_audit_id`          | bigint FK    | → plan_audits (nullable)                     |
| `audit_task_id`          | bigint FK    | → audit_tasks (nullable)                     |
| `pica_no`                | varchar(80)  |                                              |
| `title`                  | varchar(200) |                                              |
| `problem`                | text         |                                              |
| `root_cause`             | text         |                                              |
| `corrective_action`      | text         |                                              |
| `preventive_action`      | text         |                                              |
| `pic`                    | varchar(150) |                                              |
| `priority`               | varchar(40)  | `rendah`, `sedang`, `tinggi`, `urgent`       |
| `status`                 | varchar(40)  | `open`, `in_progress`, `closed`, `cancelled` |
| `target_date`            | date         |                                              |
| `actual_date`            | date         |                                              |
| `evidence`               | json         | Array path file bukti                        |
| `notes`                  | text         |                                              |
| `forwarded_to_unit`      | boolean      | Flag sudah diteruskan ke unit                |
| `closed_by`              | varchar(100) |                                              |
| `close_note`             | text         |                                              |
| `created_by`             | varchar(100) |                                              |
| `updated_by`             | varchar(100) |                                              |
| `timestamps`             |              |                                              |

---

## Tabel Pemeriksaan

Semua tabel pemeriksaan memiliki kolom `plan_audit_id` (FK ke plan_audits) dan `created_by`/`updated_by`.

### `pemeriksaan_kas`

| Kolom         | Tipe           | Keterangan                    |
|---------------|----------------|-------------------------------|
| `nama_pos`    | varchar(200)   | Nama kasir / pos kas          |
| `saldo_fisik` | decimal(18,2)  |                               |
| `saldo_buku`  | decimal(18,2)  |                               |
| `selisih`     | decimal(18,2)  | = saldo_fisik - saldo_buku    |
| `keterangan`  | text           |                               |
| `detail_json` | json           | Detail perhitungan            |

---

### `pemeriksaan_bank`

| Kolom        | Tipe           | Keterangan                        |
|--------------|----------------|-----------------------------------|
| `nama_bank`  | varchar(150)   |                                   |
| `no_rekening`| varchar(80)    |                                   |
| `saldo_buku` | decimal(18,2)  |                                   |
| `saldo_bank` | decimal(18,2)  |                                   |
| `selisih`    | decimal(18,2)  | = saldo_bank - saldo_buku         |
| `tgl_periksa`| date           |                                   |
| `auditee`    | varchar(150)   |                                   |
| `keterangan` | text           |                                   |
| `detail_json`| json           |                                   |

---

### `pemeriksaan_materai`

| Kolom           | Tipe         | Keterangan                              |
|-----------------|--------------|-----------------------------------------|
| `jenis_materai` | varchar(100) | "Rp 10.000", "Rp 6.000", dll           |
| `saldo_awal`    | integer      |                                         |
| `total_debet`   | integer      |                                         |
| `total_kredit`  | integer      |                                         |
| `saldo_akhir`   | integer      | = saldo_awal + debet - kredit           |
| `fisik`         | integer      | Jumlah fisik saat pemeriksaan           |
| `selisih`       | integer      | = fisik - saldo_akhir                   |
| `uang_10000`    | integer      | Kolom tambahan untuk materai Rp 10.000  |
| `transaksi_json`| json         | Array transaksi dari upload HTML        |

---

### `pemeriksaan_cek_fisik`

| Kolom       | Tipe | Keterangan                                                 |
|-------------|------|------------------------------------------------------------|
| `data_json` | json | Struktur: `{selisih:{cf,stuj,fstnk}, saldoAkhir:{...}, fisik:{...}}` |

**Keterangan singkatan:**
- `cf` = Cek Fisik (unit motor)
- `stuj` = STUJ (Surat Tanda Uji Jalan)
- `fstnk` = Foto copy STNK

---

### `pemeriksaan_perlengkapan`

| Kolom              | Tipe          | Keterangan                     |
|--------------------|---------------|--------------------------------|
| `nama_unit_usaha`  | varchar       |                                |
| `nama_pemeriksa`   | varchar       |                                |
| `tgl_periksa`      | date          |                                |
| `jenis_perlengkapan`| varchar      |                                |
| `saldo`            | decimal(15,2) |                                |
| `fisik`            | integer       |                                |
| `selisih`          | decimal(15,2) |                                |
| `penjelasan`       | text          |                                |

---

### `pemeriksaan_smh` + `smh_onhand_items`

`pemeriksaan_smh` — header per plan.
`smh_onhand_items` — detail per unit motor.

| Kolom (items)          | Tipe    | Keterangan                          |
|------------------------|---------|-------------------------------------|
| `no_mesin`             | varchar |                                     |
| `no_rangka`            | varchar |                                     |
| `kode_model`           | varchar |                                     |
| `status_fisik`         | varchar | `null` = belum, `ada`, `tidak_ada`  |
| `keterangan_kondisi`   | varchar | `ready_for_sale`, `rusak`, dll      |
| `perlengkapan_json`    | json    | `[{nama, ada: true/false}]`         |

---

### `bpkb_onhand_items`

| Kolom         | Tipe         | Keterangan                   |
|---------------|--------------|------------------------------|
| `no_bpkb`     | varchar(100) |                              |
| `no_polisi`   | varchar(50)  |                              |
| `jenis`       | varchar(20)  | `REG` / `KDS`                |
| `umur`        | integer      | Usia BPKB dalam hari         |
| `sudah_scan`  | boolean      | Sudah discan saat pemeriksaan|

---

### `pemeriksaan_kwitansi`

| Kolom          | Tipe | Keterangan                        |
|----------------|------|-----------------------------------|
| `kwitansi_json`| json | Array data kwitansi dari upload   |

---

### `pemeriksaan_piutang_reguler` + `pemeriksaan_piutang_cdn`

| Kolom         | Tipe | Keterangan                        |
|---------------|------|-----------------------------------|
| `piutang_json`| json | Array data piutang dari upload    |

---

### `pemeriksaan_ttp_gantung`

| Kolom       | Tipe | Keterangan                    |
|-------------|------|-------------------------------|
| `data_json` | json | Array TTP Gantung dari upload |

---

### `pemeriksaan_hgp`

| Kolom       | Tipe | Keterangan                                                                       |
|-------------|------|----------------------------------------------------------------------------------|
| `items_json`| json | `[{sparepart, saldoAwal, fisik, akhir, selisih, keterangan, tgl}]`              |

---

### `pemeriksaan_mt`

| Kolom       | Tipe | Keterangan                                             |
|-------------|------|--------------------------------------------------------|
| `data_json` | json | Array peralatan MT dengan status ketersediaan          |

---

### `pemeriksaan_hga`

| Kolom       | Tipe | Keterangan                     |
|-------------|------|--------------------------------|
| `items_json`| json | Array accessories dari upload  |

---

### `pemeriksaan_smh_tarikan`

| Kolom       | Tipe | Keterangan                       |
|-------------|------|----------------------------------|
| `items_json`| json | Array motor tarikan              |

---

### `pemeriksaan_lampiran`

| Kolom        | Tipe    | Keterangan                        |
|--------------|---------|-----------------------------------|
| `files_json` | json    | Array path file lampiran          |
| `merged_pdf` | varchar | Path PDF gabungan (setelah merge) |

---

### `pemeriksaan_bpkb_inproses`

Menyimpan data BPKB yang masih dalam proses per blok (blok reguler, CDN, dll).

---

## Tabel Pendukung

### `audit_gradings`

| Kolom            | Tipe          | Keterangan                                 |
|------------------|---------------|--------------------------------------------|
| `plan_audit_id`  | bigint        |                                            |
| `id_grading`     | varchar       | ID dari master db_grading                  |
| `jenis`          | varchar       | Cabang, Bengkel, WHS PART, WHS UNIT, dll   |
| `area`           | varchar       | RRI, dll                                   |
| `bbnkb`          | varchar       | `N` / `Y`                                  |
| `fraud`          | varchar       | `N` / `Y`                                  |
| `jenis_fraud`    | json          | Array jenis fraud                          |
| `details`        | json          | Array item pemeriksaan grading             |
| `total_nilai`    | decimal(8,2)  |                                            |

---

### `bu_performances`

| Kolom        | Tipe    | Keterangan                              |
|--------------|---------|-----------------------------------------|
| `bulan`      | varchar | Contoh: "Januari 2026"                  |
| `unit_usaha` | varchar |                                         |
| `auditor`    | varchar |                                         |
| `penilaian`  | json    | `[{pic, jabatan, uraian}]`              |

---

### `pinjaman_cabang`

| Kolom              | Tipe          | Keterangan                                                            |
|--------------------|---------------|-----------------------------------------------------------------------|
| `audit_task_id`    | bigint        | → audit_tasks                                                         |
| `jenis`            | enum          | `BPK`, `BPB`                                                          |
| `cabang_realisasi` | json          | Array cabang terpilih                                                 |
| `no_spd`           | varchar       |                                                                       |
| `nominal`          | decimal(18,2) |                                                                       |
| `terbilang`        | varchar       |                                                                       |
| `bukti_file`       | varchar       |                                                                       |
| `status`           | varchar       | `draft`, `pending_koordinator`, `pending_manajer`, `pending_coo`, dll |
| `approvals`        | json          | `[{role, user, action, note, at}]`                                    |

---

## Tabel Database Master

| Tabel              | Keterangan                              |
|--------------------|-----------------------------------------|
| `db_harga_smh`     | Master harga SMH per kode model         |
| `db_plafon`        | Master plafon per kode                  |
| `db_perlengkapan`  | Master perlengkapan per wilayah         |
| `db_unit_usaha`    | Master unit usaha (cabang)              |
| `db_grading`       | Master item pemeriksaan grading         |
| `db_mt`            | Master peralatan MT                     |
| `db_het`           | Master Harga Eceran Tertinggi (HET)     |

---

## Tabel Sistem

| Tabel                    | Keterangan                                  |
|--------------------------|---------------------------------------------|
| `app_data`               | Key-value store (pengaturan aplikasi)       |
| `activity_log`           | Log aktivitas semua user                    |
| `menus`                  | Konfigurasi menu dinamis                    |
| `menu_roles`             | Mapping menu ↔ role                         |
| `roles`                  | Master role (selain default Sanctum)        |
| `personal_access_tokens` | Token Sanctum                               |
| `sessions`               | Session Laravel                             |
| `cache`                  | Cache Laravel                               |
| `jobs`                   | Queue jobs                                  |

---

## Relasi Utama

```
users
  └─ (buat/assign) plan_audits
        ├─ audit_tasks
        │     └─ pinjaman_cabang
        ├─ audit_recommendations
        │     ├─ picas
        │     └─ steps[] (JSON birokrasi)
        ├─ surat_keputusan
        ├─ audit_gradings
        ├─ pemeriksaan_kas
        ├─ pemeriksaan_bank
        ├─ pemeriksaan_smh
        │     └─ smh_onhand_items
        ├─ pemeriksaan_materai
        ├─ pemeriksaan_perlengkapan
        ├─ bpkb_onhand_items
        ├─ pemeriksaan_bpkb_inproses
        ├─ pemeriksaan_kwitansi
        ├─ pemeriksaan_piutang_reguler
        ├─ pemeriksaan_piutang_cdn
        ├─ pemeriksaan_cek_fisik
        ├─ pemeriksaan_ttp_gantung
        ├─ pemeriksaan_hgp
        ├─ pemeriksaan_mt
        ├─ pemeriksaan_hga
        ├─ pemeriksaan_smh_tarikan
        └─ pemeriksaan_lampiran
```
