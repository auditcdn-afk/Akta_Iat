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

    /**
     * Role pemegang tiap tahap persetujuan — sumber kebenaran tunggal untuk
     * "giliran siapa sekarang".
     *
     * Sebelumnya peta ini hanya ada di browser (PINJAMAN_STAGE di
     * akta-task.js), sehingga server tidak pernah memeriksa giliran sama
     * sekali: siapa pun yang lolos middleware rute approve bisa menyetujui
     * pengajuan pada tahap mana pun. Tombolnya memang disembunyikan, tapi
     * itu hanya tampilan, bukan kewenangan.
     */
    public const TAHAP_ROLE = [
        'pending_koordinator' => 'koordinator',
        'pending_manajer'     => 'manajer',
        'pending_coo'         => 'coo',
        'pending_unit'        => 'unit',
        'pending_bpk'         => 'bpk',
    ];

    /** Role yang berhak memproses pengajuan ini sekarang, atau null kalau tidak ada. */
    public function rolePemegangTahap(): ?string
    {
        return self::TAHAP_ROLE[$this->status] ?? null;
    }

    /** Tahap yang menjadi giliran $role, atau null kalau role itu bukan pemegang tahap mana pun. */
    public static function tahapUntukRole(?string $role): ?string
    {
        $peta = array_flip(self::TAHAP_ROLE);

        return $peta[$role] ?? null;
    }

    /** Apakah $user pernah menyetujui/menolak pengajuan ini? */
    public function pernahDiprosesOleh(?string $username, ?string $email = null): bool
    {
        $identitas = array_values(array_filter([$username, $email]));

        if (! $identitas) {
            return false;
        }

        foreach ($this->approvals ?? [] as $jejak) {
            if (! in_array($jejak['action'] ?? '', ['approve', 'reject'], true)) {
                continue;
            }

            if (in_array($jejak['user'] ?? null, $identitas, true)) {
                return true;
            }
        }

        return false;
    }

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
