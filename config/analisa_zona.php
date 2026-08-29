<?php

// Ambang nominal tetap untuk skor ABSOLUT tiap indikator zona risiko (lihat
// App\Services\AnalisaZona\ZonaRiskScoreService). Skor final tiap indikator
// adalah rata-rata skor RELATIF (dibanding zona lain di periode yang sama,
// min-max 0-100) dan skor ABSOLUT (nilai riil dibanding ambang di bawah ini,
// dibatasi maksimal 100).
//
// Kenapa perlu dua-duanya: skor relatif SENDIRIAN runtuh jadi rata (50 untuk
// semua zona) begitu cuma ada 1 zona yang punya data di suatu periode —
// wajar terjadi selama adopsi upload rutin belum merata ke semua unit
// usaha. Skor absolut tetap mencerminkan besar-kecilnya angka meski cuma
// ada 1 zona untuk dibandingkan.
//
// Angka di bawah ini TEBAKAN AWAL berdasar sampel data nyata (lihat riwayat
// konsultasi) — sengaja dibuat gampang disesuaikan di sini (bukan hard-code
// di service) kalau ternyata terlalu longgar/ketat setelah dilihat pola data
// riil dari lebih banyak unit usaha & periode.
return [
    'ambang' => [
        // Total nominal voucher kas kecil (RKK) per zona per bulan.
        'kas_kecil_nominal_max' => 100_000_000,

        // Piutang belum lunas (ACC tipe F) per zona per bulan.
        'piutang_nominal_max' => 1_000_000_000,

        // Jumlah baris terindikasi duplikat (konsumen/piutang) per zona per bulan.
        'anomali_jumlah_max' => 30,

        // Saldo akhir kas (LHPBK) — snapshot HARI TERAKHIR yang ada datanya
        // dalam periode, bukan dijumlah (ini posisi/stock, bukan arus kas).
        // Kas yang masih tertahan di atas ambang ini di akhir hari dianggap
        // risiko tinggi (belum disetor ke bank).
        'posisi_kas_saldo_max' => 50_000_000,
    ],

    // Ambang untuk aturan pemeriksaan otomatis yang menghasilkan TEMUAN
    // (lihat App\Services\AnalisaZona\Temuan\*). Beda dari 'ambang' di atas
    // yang cuma menskala angka 0-100: yang di sini menentukan sebuah baris
    // dilaporkan sebagai temuan atau tidak, jadi pengaruhnya langsung ke
    // daftar tindakan yang muncul di layar auditor.
    'temuan' => [
        // Umur piutang (hari, dihitung dari tanggal transaksi ke tanggal
        // laporan terakhir) yang dianggap sudah terlalu lama belum cair.
        // Pada sampel nyata SOTDB, sebaran umurnya 3-22 hari dengan jeda
        // jelas antara kelompok <=12 hari dan 2 piutang berumur 21-22 hari —
        // 14 dipakai supaya jeda itu tertangkap. Sesuaikan setelah terlihat
        // pola dari lebih banyak cabang.
        'piutang_umur_hari' => 14,

        // Piutang di bawah nominal ini tidak dilaporkan satu per satu walau
        // sudah lewat umur di atas — supaya daftar temuan tidak penuh oleh
        // sisa-sisa kecil yang tidak sepadan dengan biaya menagihnya.
        'piutang_nominal_min' => 1_000_000,

        // Selisih rekonsiliasi (LPK vs LHPBK, RKK vs LHPBK) yang masih
        // dianggap wajar. Hubungan angkanya seharusnya eksak, jadi ambang
        // ini kecil — hanya untuk menyerap pembulatan, bukan memaafkan
        // selisih sungguhan.
        'selisih_rekonsiliasi_toleransi' => 1_000,

        // Saldo akhir kas yang masih dianggap wajar ditahan di cabang pada
        // akhir hari. Di atas ini dilaporkan sebagai kas belum disetor.
        'saldo_kas_wajar_max' => 50_000_000,

        // Rasio DP terhadap harga OTR di bawah ini dianggap tipis.
        'dp_ratio_min' => 0.15,
    ],
];
