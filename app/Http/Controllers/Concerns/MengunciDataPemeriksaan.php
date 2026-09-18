<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Dipakai tool pemeriksaan yang datanya SATU baris per plan audit berisi
 * seluruh daftar item (items_json) — HGP & AHM Oils, RSA HGP, HGA.
 *
 * Satu SPT dikerjakan dua auditor sekaligus, dan tiap kali salah satu menembak
 * barcode, server membaca SELURUH items_json, mengubah satu item, lalu menulis
 * ulang semuanya. Tanpa penguncian, dua permintaan yang datang berbarengan
 * sama-sama membaca isi yang sama; yang menyimpan belakangan menulis dari
 * salinan lama, dan hasil scan auditor satunya HILANG tanpa jejak — itemnya
 * tetap tercatat "belum discan" padahal barangnya sudah dihitung, lalu muncul
 * sebagai selisih palsu di laporan.
 *
 * Antrean di sisi browser (createScanIncrementQueue) hanya mengurutkan scan
 * dari SATU perangkat. Antar perangkat, di sinilah urutannya dijaga: permintaan
 * kedua menunggu di baris kunci sampai yang pertama selesai menyimpan, lalu
 * membaca hasil yang sudah lengkap.
 */
trait MengunciDataPemeriksaan
{
    /**
     * Jalankan $aksi sambil mengunci baris pemeriksaan milik plan ini.
     *
     * $aksi menerima barisnya (null kalau memang belum ada) dan HARUS memakai
     * baris itu — bukan membacanya lagi dari luar, karena pembacaan di luar
     * transaksi tidak ikut terkunci.
     *
     * @param  class-string<Model>  $model
     * @param  callable(?Model): mixed  $aksi
     */
    protected function denganKunciPemeriksaan(string $model, mixed $planId, callable $aksi): mixed
    {
        return DB::transaction(function () use ($model, $planId, $aksi) {
            $rec = $model::query()->where('plan_audit_id', $planId)->lockForUpdate()->first();

            return $aksi($rec);
        });
    }

    /**
     * Sama, tapi untuk baris yang sudah dipegang controller-nya (route model
     * binding). Barisnya DIBACA ULANG di dalam transaksi sambil dikunci —
     * salinan yang terlanjur dipegang di luar transaksi sudah bisa basi.
     *
     * @param  callable(Model): mixed  $aksi
     */
    protected function denganKunciBaris(Model $baris, callable $aksi): mixed
    {
        return DB::transaction(function () use ($baris, $aksi) {
            $segar = $baris->newQuery()->whereKey($baris->getKey())->lockForUpdate()->first() ?? $baris;

            return $aksi($segar);
        });
    }
}
