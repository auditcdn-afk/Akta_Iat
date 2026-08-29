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
];
