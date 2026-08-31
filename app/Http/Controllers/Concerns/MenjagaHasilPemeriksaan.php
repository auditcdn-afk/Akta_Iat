<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Carbon;

/**
 * Penjaga hasil pemeriksaan fisik (HGP & AHM Oils dan kembarannya RSA HGP)
 * terhadap simpan-penuh dari snapshot lama.
 *
 * Latar masalahnya: items_json satu tool disimpan sebagai SATU dokumen. Selama
 * simpan-penuh (save()) menerima apa pun yang dikirim browser, array lama yang
 * masih dipegang satu perangkat akan menimpa hasil scan perangkat lain yang
 * lebih baru — dan hasilnya persis seperti yang dilaporkan auditor: item yang
 * sudah discan kembali tampil belum discan, riwayat logScan-nya ikut hilang.
 * Simpan-penuh itu tidak cuma dipicu tombol Simpan: import ulang, "Hapus Semua
 * Data", sampai fallback ketika request delta gagal semuanya lewat jalur sama.
 *
 * Aturan yang dijaga di sini: JEJAK PEMERIKSAAN TIDAK BOLEH MENYUSUT. Koreksi
 * qty minus tetap boleh (koreksi selalu menambah 1 entri logScan), tapi payload
 * yang membuat sebuah item kehilangan entri logScan-nya ditolak — kecuali
 * penghapusan itu memang diminta secara sadar (mode "replace").
 */
trait MenjagaHasilPemeriksaan
{
    /** Angka yang toleran terhadap "1.234", "" dan null — sama seperti n() di controller. */
    private function angka(mixed $val): float
    {
        if ($val === null || $val === '') return 0.0;
        if (is_numeric($val)) return (float) $val;
        $bersih = preg_replace('/[^0-9.\-]/', '', (string) $val);
        return ($bersih === '' || $bersih === '-') ? 0.0 : (float) $bersih;
    }

    private function kunciNoPart(mixed $noPart): string
    {
        return strtolower(trim((string) $noPart));
    }

    /** Peta noPart (huruf kecil) => item, untuk mencocokkan dua versi daftar. */
    private function petaPerNoPart(array $items): array
    {
        $peta = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $kunci = $this->kunciNoPart($item['noPart'] ?? '');
            if ($kunci !== '') $peta[$kunci] = $item;
        }
        return $peta;
    }

    /**
     * Berapa banyak jejak pemeriksaan yang sudah menempel di satu item. Selain
     * fisik hasil scan, kolom qty yang diisi manual ikut dihitung: "wo" (HGP &
     * RSA HGP) dan "fisikTtp" (HGA) — dua-duanya hasil kerja auditor yang tidak
     * boleh hilang, dan tiap tool cuma punya salah satunya.
     */
    private function jejakPemeriksaan(array $item): array
    {
        return [
            'fisik'  => $this->angka($item['fisik'] ?? 0),
            'manual' => $this->angka($item['wo'] ?? 0) + $this->angka($item['fisikTtp'] ?? 0),
            'log'    => is_array($item['logScan'] ?? null) ? count($item['logScan']) : 0,
        ];
    }

    private function belumTersentuh(array $jejak): bool
    {
        return $jejak['log'] === 0 && $jejak['fisik'] == 0.0 && $jejak['manual'] == 0.0;
    }

    /**
     * Daftar No. Part yang jejak pemeriksaannya akan HILANG kalau $baru
     * disimpan menimpa $tersimpan. Kosong berarti payload-nya aman.
     */
    private function pemeriksaanYangHilang(array $baru, array $tersimpan): array
    {
        $peta   = $this->petaPerNoPart($baru);
        $hilang = [];

        foreach ($tersimpan as $lama) {
            if (!is_array($lama)) continue;
            $kunci = $this->kunciNoPart($lama['noPart'] ?? '');
            if ($kunci === '') continue;

            $jejakLama = $this->jejakPemeriksaan($lama);
            if ($this->belumTersentuh($jejakLama)) continue;

            $itemBaru = $peta[$kunci] ?? null;
            if ($itemBaru === null) {                 // itemnya lenyap sama sekali
                $hilang[] = (string) ($lama['noPart'] ?? '');
                continue;
            }

            $jejakBaru = $this->jejakPemeriksaan($itemBaru);
            // Riwayat scan hanya boleh bertambah. Koreksi qty minus menurunkan
            // fisik TAPI menambah entri log, jadi tetap lolos di sini.
            $riwayatMenyusut = $jejakBaru['log'] < $jejakLama['log'];
            // Tanpa entri log baru, turunnya angka hanya bisa berarti payload-nya
            // ketinggalan. Kolom manual (WO / Fisik TTP) ikut dijaga: isinya sama
            // saja hasil kerja auditor walau tidak lewat scan.
            $angkaTurunTanpaJejak = $jejakBaru['log'] === $jejakLama['log']
                && ($jejakBaru['fisik'] < $jejakLama['fisik'] || $jejakBaru['manual'] < $jejakLama['manual']);

            if ($riwayatMenyusut || $angkaTurunTanpaJejak) {
                $hilang[] = (string) ($lama['noPart'] ?? '');
            }
        }

        return $hilang;
    }

    /**
     * Import ulang = daftar item & saldo baru dari file Excel, TAPI hasil
     * pemeriksaan yang sudah ada di server ikut dibawa (bukan diambil dari
     * snapshot browser yang bisa saja ketinggalan). Item lama yang sudah
     * terlanjur discan namun tidak ada di file baru tetap dipertahankan di
     * ekor daftar supaya hasil kerjanya tidak menguap.
     */
    private function bawaHasilPemeriksaan(array $baru, array $tersimpan): array
    {
        $peta    = $this->petaPerNoPart($tersimpan);
        $terpakai = [];

        foreach ($baru as $i => $item) {
            if (!is_array($item)) continue;
            $kunci = $this->kunciNoPart($item['noPart'] ?? '');
            $lama  = $kunci !== '' ? ($peta[$kunci] ?? null) : null;
            if (!$lama) continue;

            $terpakai[$kunci] = true;
            foreach (['fisik', 'wo', 'fisikTtp', 'logScan', 'keterangan', 'keteranganTtp', 'tgl'] as $field) {
                if (array_key_exists($field, $lama)) $item[$field] = $lama[$field];
            }
            // Saldo baseline-nya dari file baru, jadi akhir & selisih dihitung ulang.
            $saldo = $this->angka($item['saldoAkhir'] ?? 0);
            $total = $this->angka($item['fisik'] ?? 0) + $this->angka($item['wo'] ?? 0);
            $item['akhir']   = $saldo - $total;
            $item['selisih'] = $total - $saldo;
            $baru[$i] = $item;
        }

        foreach ($tersimpan as $lama) {
            if (!is_array($lama)) continue;
            $kunci = $this->kunciNoPart($lama['noPart'] ?? '');
            if ($kunci === '' || isset($terpakai[$kunci])) continue;
            if ($this->belumTersentuh($this->jejakPemeriksaan($lama))) continue;
            $baru[] = $lama;
        }

        return array_values($baru);
    }

    /**
     * Terapkan entri scan ke satu item. Tiap entri membawa id dari browser
     * supaya request yang diulang (karena jaringan gudang putus di tengah
     * jalan) tidak menambah fisik dua kali — entri dengan id yang sudah
     * tercatat dilewati.
     */
    private function terapkanEntriScan(array $item, array $entries): array
    {
        $item['logScan'] = is_array($item['logScan'] ?? null) ? $item['logScan'] : [];

        $sudahAda = [];
        foreach ($item['logScan'] as $tercatat) {
            $id = is_array($tercatat) ? ($tercatat['id'] ?? null) : null;
            if (is_string($id) && $id !== '') $sudahAda[$id] = true;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) continue;
            $qty = $this->angka($entry['qty'] ?? 0);
            if ($qty === 0.0) continue;

            $id = $entry['id'] ?? null;
            $id = is_string($id) && $id !== '' ? $id : null;
            if ($id !== null && isset($sudahAda[$id])) continue;   // duplikat kiriman ulang

            $item['fisik'] = $this->angka($item['fisik'] ?? 0) + $qty;
            $catatan = ['at' => $this->waktuScan($entry['at'] ?? null), 'qty' => $qty];
            if ($id !== null) {
                $catatan['id'] = $id;
                $sudahAda[$id] = true;
            }
            $item['logScan'][] = $catatan;
        }

        return $item;
    }

    /**
     * Waktu scan dari alat auditor dipakai apa adanya kalau bisa dibaca, supaya
     * riwayat menunjukkan kapan barang benar-benar discan — bukan kapan request
     * gabungannya sampai di server. Format asing jatuh ke waktu server.
     */
    private function waktuScan(mixed $at): string
    {
        if (is_string($at) && trim($at) !== '') {
            try {
                return Carbon::parse($at)->toIso8601String();
            } catch (\Throwable) {
                // biarkan jatuh ke waktu server di bawah
            }
        }

        return now()->toIso8601String();
    }
}
