# Deploy otomatis ke hosting

Setiap perubahan yang masuk ke `master` akan diuji, di-build, dikirim ke hosting,
lalu cache-nya dibersihkan — semuanya otomatis. Tidak ada lagi upload manual.

Alurnya ada di [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml).

## Penyiapan (sekali saja)

Buka repositori di GitHub → **Settings** → **Secrets and variables** → **Actions**.

### 1. Tab "Secrets" → tombol **New repository secret**

Isi empat ini:

| Nama | Isi |
| --- | --- |
| `FTP_SERVER` | Alamat FTP hosting, mis. `ftp.exprosalab.com` — tanpa `ftp://` |
| `FTP_USERNAME` | Nama pengguna FTP |
| `FTP_PASSWORD` | Kata sandi FTP |
| `DEPLOY_SECRET` | Token yang sama dengan `DEPLOY_SECRET` di `.env` server |

Nilai secret tidak bisa dilihat lagi setelah disimpan, dan otomatis disamarkan
di log. Jangan pernah menuliskannya di kode, di deskripsi PR, atau di percakapan.

### 2. Tab "Variables" → **New repository variable** (opsional)

Hanya perlu diisi kalau bawaannya tidak cocok:

| Nama | Bawaan | Kapan perlu diubah |
| --- | --- | --- |
| `FTP_SERVER_DIR` | `/` | Kalau folder aplikasi bukan di akar FTP, mis. `/public_html/` |
| `FTP_PROTOCOL` | `ftps` | Ubah ke `ftp` **hanya** kalau hosting menolak FTPS |
| `APP_URL` | `https://simpas-iat.exprosalab.com` | Kalau domainnya berubah |

> **Soal `FTP_PROTOCOL`:** `ftps` mengenkripsi kata sandi saat dikirim. `ftp`
> biasa mengirimkannya sebagai teks polos yang bisa disadap. Pakai `ftp` hanya
> kalau benar-benar terpaksa, dan sebaiknya minta hosting mengaktifkan FTPS.

## Cara memakainya

Tidak ada yang perlu dilakukan. Begitu ada perubahan masuk ke `master`, buka tab
**Actions** di GitHub untuk melihat prosesnya (biasanya 2–4 menit).

Untuk menjalankan ulang tanpa perubahan kode: **Actions** → **Deploy ke
hosting** → **Run workflow**.

## Yang dikerjakan otomatis

1. Menjalankan seluruh tes — **kalau ada yang gagal, deploy dibatalkan** dan
   server tetap memakai versi lama yang masih berfungsi
2. `npm run build`, lalu memastikan `public/build/manifest.json` benar terbentuk
3. Mengunggah berkas yang berubah lewat FTP
4. Memanggil `/deploy/clear-cache`

## Yang TIDAK disentuh di server

Sengaja dikecualikan, karena menimpanya bisa merusak atau menghilangkan data:

| Tidak diunggah | Alasan |
| --- | --- |
| `.env` | Konfigurasi produksi, termasuk sandi database |
| `vendor/` | Menimpanya dengan isi runner bisa mematikan aplikasi |
| `bootstrap/cache/` | Daftar paket basi di sini membuat **seluruh aplikasi mati**, termasuk endpoint clear-cache — tidak ada jalan pulih selain hapus manual lewat FTP |
| `storage/` | Lampiran yang diunggah auditor, log, view terkompilasi |
| `composer.json` / `composer.lock` | Tidak berguna di server dan berisiko memicu ketidakcocokan dengan `vendor/` |
| `.git/` | Folder ini di server web publik membocorkan seluruh kode & riwayatnya |
| `tests/`, `node_modules/`, konfigurasi build | Hanya dipakai saat membangun |

Berkas asing yang sudah ada di server **tidak dihapus** — deploy hanya menambah
dan menimpa. Aman, tapi artinya sisa berkas lama perlu dibersihkan manual kalau
memang ingin rapi.

## Migrasi database tidak otomatis

Mengubah skema database produksi harus keputusan sadar, bukan efek samping
sebuah merge. Kalau ada migrasi baru, ringkasan di akhir proses Actions
menampilkan peringatan beserta daftar berkasnya.

Jalankan sendiri saat siap:

```
https://<domain>/deploy/migrate?token=<DEPLOY_SECRET>
```

## Kalau deploy gagal

Buka tab **Actions**, klik proses yang merah, dan lihat langkah mana yang
berhenti:

| Langkah gagal | Artinya |
| --- | --- |
| **Jalankan tes** | Ada yang rusak di kode. Server tidak tersentuh — perbaiki dulu |
| **Bangun aset** | `npm run build` gagal. Server tidak tersentuh |
| **Kirim ke hosting** | Kredensial FTP salah, atau hosting menolak FTPS — coba ubah variabel `FTP_PROTOCOL` jadi `ftp` |
| **Bersihkan cache** | Berkas sudah terkirim tapi cache belum bersih. Panggil `/deploy/clear-cache` manual, dan periksa `DEPLOY_SECRET` cocok dengan yang di `.env` server |
