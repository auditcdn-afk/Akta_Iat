<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PinjamanCabang extends Model
{
    protected $table = 'pinjaman_cabang';

    protected $fillable = [
        'audit_task_id', 'jenis', 'cabang_realisasi', 'no_spd', 'catatan',
        'nominal', 'terbilang', 'bukti_file', 'departemen',
        'status', 'approvals', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'cabang_realisasi' => 'array',
        'approvals'        => 'array',
        'nominal'          => 'float',
    ];

    // Urutan birokrasi per jenis
    public const FLOW_BPK = [
        'pending_koordinator', 'pending_manajer', 'pending_coo', 'pending_unit', 'pending_bpk', 'approved',
    ];
    public const FLOW_BPB = [
        'pending_koordinator', 'pending_manajer', 'pending_bpk', 'approved',
    ];

    public function auditTask(): BelongsTo
    {
        return $this->belongsTo(AuditTask::class, 'audit_task_id');
    }

    public function nextStatus(): ?string
    {
        $flow  = $this->jenis === 'BPK' ? self::FLOW_BPK : self::FLOW_BPB;
        $idx   = array_search($this->status, $flow);
        return $idx !== false && isset($flow[$idx + 1]) ? $flow[$idx + 1] : null;
    }

    /**
     * Cabang realisasi SELALU sebagai daftar.
     *
     * Form mengirim kolom ini sebagai teks JSON (mis. '["SO ARK"]') lewat
     * FormData, dan sebagian baris lama terlanjur tersimpan begitu — teks JSON
     * yang ter-encode sekali lagi oleh cast 'array', bukan daftar. Dibiarkan
     * apa adanya, pemakainya pecah: implode()/join() atas sebuah string
     * melempar error, dan itu membuat daftar riwayat pinjaman gagal tampil
     * sama sekali (auditor jadi tidak tahu sudah mengajukan atau belum) serta
     * memo PDF-nya gagal dicetak.
     *
     * @return array<int,string>
     */
    public function daftarCabangRealisasi(): array
    {
        $nilai = $this->cabang_realisasi;

        if (is_string($nilai)) {
            $decoded = json_decode($nilai, true);
            $nilai = is_array($decoded) ? $decoded : ($nilai !== '' ? [$nilai] : []);
        }

        return array_values(array_filter(
            array_map(fn($v) => is_string($v) ? trim($v) : $v, (array) ($nilai ?? [])),
            fn($v) => is_string($v) && $v !== ''
        ));
    }

    public function toAktaArray(): array
    {
        return [
            'id'               => $this->id,
            'auditTaskId'      => $this->audit_task_id,
            'jenis'            => $this->jenis,
            'cabangRealisasi'  => $this->daftarCabangRealisasi(),
            'noSpd'            => $this->no_spd,
            'catatan'          => $this->catatan,
            'nominal'          => $this->nominal,
            'terbilang'        => $this->terbilang,
            'buktFile'         => $this->bukti_file,
            'departemen'       => $this->departemen,
            'status'           => $this->status,
            'approvals'        => $this->approvals ?? [],
            'nextStatus'       => $this->nextStatus(),
            'createdBy'        => $this->created_by,
            'createdAt'        => optional($this->created_at)->format('Y-m-d H:i'),
            'updatedAt'        => optional($this->updated_at)->format('Y-m-d H:i'),
        ];
    }
}
