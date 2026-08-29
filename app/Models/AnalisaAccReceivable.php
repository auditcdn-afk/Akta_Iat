<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaAccReceivable extends Model
{
    protected $table = 'analisa_acc_receivables';

    protected $fillable = [
        'upload_id', 'unit_usaha_code', 'tanggal_laporan', 'kode_konsumen',
        'no_bukti', 'tanggal_transaksi', 'kode_sales', 'nominal', 'raw_line',
    ];

    protected $casts = [
        'tanggal_laporan' => 'date',
        'tanggal_transaksi' => 'date',
        'nominal' => 'decimal:2',
    ];

    /**
     * Tanggal laporan terakhir yang ada datanya dalam rentang, sebagai string
     * 'Y-m-d' yang aman dipakai di whereDate().
     *
     * Perlu dinormalisasi karena kolom ini bisa tersimpan dalam dua bentuk:
     * jalur impor menulisnya lewat DB::table()->insert() (bulk insert, tanpa
     * cast Eloquent) sehingga tersimpan apa adanya 'Y-m-d', sedangkan baris
     * yang dibuat lewat model kena cast 'date' dan tersimpan sebagai
     * 'Y-m-d H:i:s'. max() mengembalikan apa pun bentuk yang tersimpan, dan
     * whereDate() TIDAK cocok kalau nilai pembandingnya masih membawa jam —
     * hasilnya query balik kosong tanpa error, jadi kekeliruannya tidak
     * kelihatan sampai angkanya dicek satu per satu.
     */
    public static function snapshotTerakhir(string $unitUsahaCode, string $start, string $end): ?string
    {
        $max = static::where('unit_usaha_code', $unitUsahaCode)
            ->whereBetween('tanggal_laporan', [$start, $end])
            ->max('tanggal_laporan');

        return $max ? \Illuminate\Support\Carbon::parse($max)->toDateString() : null;
    }

    /** Umur piutang (hari) sejak tanggal transaksi sampai tanggal laporan. */
    public function getUmurHariAttribute(): ?int
    {
        if (!$this->tanggal_transaksi || !$this->tanggal_laporan) {
            return null;
        }
        return $this->tanggal_transaksi->diffInDays($this->tanggal_laporan);
    }
}
