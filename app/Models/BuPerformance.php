<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuPerformance extends Model
{
    protected $table = 'bu_performances';

    protected $fillable = [
        'bulan', 'unit_usaha', 'auditor', 'penilaian', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'penilaian' => 'array',
    ];

    /** Nama bulan Indonesia -> nomornya, untuk mengurutkan kolom 'bulan' yang berupa teks. */
    private const NOMOR_BULAN = [
        'januari' => '01', 'februari' => '02', 'maret'     => '03', 'april'   => '04',
        'mei'     => '05', 'juni'     => '06', 'juli'      => '07', 'agustus' => '08',
        'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12',
    ];

    /**
     * Kunci urut kronologis untuk kolom 'bulan'.
     *
     * Bulannya disimpan sebagai TEKS ("Januari 2026"), jadi orderBy('bulan')
     * mengurutkannya menurut abjad: "April 2026" jatuh sebelum "Januari 2026",
     * dan "Agustus" sebelum "Desember". Daftar bulan maupun pengelompokannya
     * jadi tidak berurutan waktu. Diubah ke "2026-01" supaya urutannya benar.
     *
     * Teks yang tidak dikenali dikembalikan apa adanya dengan awalan '9999'
     * supaya jatuh di ujung, bukan menyelinap di tengah urutan yang benar.
     */
    public static function kunciUrutBulan(?string $bulan): string
    {
        $bersih = trim((string) $bulan);
        $potong = preg_split('/\s+/', $bersih) ?: [];

        if (count($potong) === 2) {
            $nomor = self::NOMOR_BULAN[mb_strtolower($potong[0])] ?? null;
            if ($nomor !== null && ctype_digit($potong[1])) {
                return $potong[1] . '-' . $nomor;
            }
        }

        return '9999-' . $bersih;
    }

    public function toAktaArray(): array
    {
        return [
            'id'         => $this->id,
            'bulan'      => $this->bulan,
            'unitUsaha'  => $this->unit_usaha,
            'auditor'    => $this->auditor,
            'penilaian'  => $this->penilaian ?? [],
            'createdBy'  => $this->created_by,
            'updatedBy'  => $this->updated_by,
            'updatedAt'  => optional($this->updated_at)->format('Y-m-d'),
        ];
    }
}
