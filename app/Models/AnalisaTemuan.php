<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalisaTemuan extends Model
{
    protected $table = 'analisa_temuan';

    public const SEVERITY_TINGGI = 'tinggi';
    public const SEVERITY_SEDANG = 'sedang';
    public const SEVERITY_RENDAH = 'rendah';

    /** Untuk mengurutkan temuan: yang paling perlu ditindak muncul lebih dulu. */
    public const URUTAN_SEVERITY = [
        self::SEVERITY_TINGGI => 1,
        self::SEVERITY_SEDANG => 2,
        self::SEVERITY_RENDAH => 3,
    ];

    protected $fillable = [
        'unit_usaha_code', 'periode', 'tanggal', 'kode_rule', 'judul',
        'severity', 'nominal', 'rekomendasi', 'detail_json',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'nominal'     => 'decimal:2',
        'detail_json' => 'array',
    ];

    public function toAktaArray(): array
    {
        return [
            'id'             => $this->id,
            'unitUsahaCode'  => $this->unit_usaha_code,
            'periode'        => $this->periode,
            'tanggal'        => optional($this->tanggal)->toDateString(),
            'kodeRule'       => $this->kode_rule,
            'judul'          => $this->judul,
            'severity'       => $this->severity,
            'nominal'        => $this->nominal !== null ? (float) $this->nominal : null,
            'rekomendasi'    => $this->rekomendasi,
            'detail'         => $this->detail_json ?? [],
        ];
    }
}
