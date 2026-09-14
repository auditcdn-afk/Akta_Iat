<?php

namespace App\Http\Controllers;

use App\Models\PinjamanCabang;
use App\Models\User;
use Illuminate\View\View;

// Cetak "Memo Pinjaman" untuk satu pengajuan pinjaman cabang (BPK/BPB) — pola
// sama dengan Surat Perintah Tugas (PlanAuditPdfController): dibangun langsung
// dari data yang sudah tersimpan, bukan diketik ulang manual, dan tahapan
// approval-nya real-time dari kolom `approvals` (bukan tanda tangan kosong
// seperti contoh memo lama). Kalimat "untuk keperluan ..." disesuaikan per
// jenis (BPK = pinjaman kendaraan/operasional realisasi di unit usaha, BPB =
// pinjaman ke Finance) karena field & alur birokrasi keduanya berbeda (lihat
// PinjamanCabang::FLOW_BPK / FLOW_BPB).
class PinjamanPdfController extends Controller
{
    // Role yang approve di tiap tahap flow, berurutan sesuai FLOW_BPK/FLOW_BPB
    // (status 'approved' di akhir bukan tahap approval, jadi tidak disertakan
    // di sini — ia adalah HASIL dari approval terakhir, bukan tahap tersendiri).
    private const ROLE_PER_STAGE = [
        'pending_koordinator' => 'koordinator',
        'pending_manajer'     => 'manajer',
        'pending_coo'         => 'coo',
        'pending_unit'        => 'unit',
        'pending_bpk'         => 'bpk',
    ];

    private const STAGE_LABEL = [
        'koordinator' => 'Disetujui Koordinator',
        'manajer'     => 'Disetujui Manajer Audit',
        'coo'         => 'Disetujui COO',
        'unit'        => 'Disetujui Unit Usaha',
        'bpk'         => 'Disetujui Role BPK',
    ];

    /**
     * Tahap yang MENERBITKAN memo untuk tiap jenis.
     *
     * Sebelumnya memo selalu tertanggal hari pencetakan dan ditandatangani COO —
     * padahal COO bahkan tidak ada dalam alur BPB (FLOW_BPB: koordinator →
     * manajer → bpk). Memo yang dicetak ulang bulan depan karena itu berubah
     * tanggalnya, seolah terbit dua kali.
     *
     * Sekarang tanggal terbitnya diambil dari persetujuan yang menjadi dasar
     * terbitnya memo: COO untuk BPK, Manajer Audit untuk BPB.
     */
    private const ROLE_PENERBIT = [
        'BPK' => 'coo',
        'BPB' => 'manajer',
    ];

    private const JABATAN_LABEL = [
        'admin'       => 'Administrator',
        'auditor'     => 'Internal Auditor',
        'manajer'     => 'Manajer Audit',
        'koordinator' => 'Koordinator',
        'coo'         => 'Chief Operating Officer',
        'unit'        => 'Unit Usaha',
        'bpk'         => 'Role BPK',
        'viewer'      => 'Viewer',
    ];

    public function memo(PinjamanCabang $pinjaman): View
    {
        $pinjaman->load('auditTask.planAudit');
        $plan = $pinjaman->auditTask?->planAudit;

        return view('akta.pdf.pinjaman-memo', [
            'pinjaman'   => $pinjaman,
            'plan'       => $plan,
            'jenisLabel' => $pinjaman->jenis === 'BPK'
                ? 'Bon Pinjaman Kendaraan / Operasional (BPK)'
                : 'Bon Pinjaman ke Finance (BPB)',
            'tujuan'     => $this->tujuan($pinjaman),
            'keperluan'  => $this->keperluan($pinjaman, $plan),
            'diperhitungkan' => $pinjaman->jenis === 'BPK'
                ? 'Uang tersebut akan diperhitungkan di kantor pusat setelah realisasi perjalanan dinas selesai dipertanggungjawabkan.'
                : 'Uang tersebut akan diperhitungkan langsung dengan Departemen ' . ($pinjaman->departemen ?: 'Finance') . '.',
            'pengaju'    => $this->namaAktor($pinjaman->created_by),
            'tahapan'    => $this->susunTahapan($pinjaman),
            'statusLabel' => $this->statusLabel($pinjaman->status),
            'diterbitkan' => $this->diterbitkan($pinjaman),
            'cabangPinjaman' => $pinjaman->jenis === 'BPK'
                ? (implode(', ', $pinjaman->daftarCabangRealisasi()) ?: null)
                : null,
            'autoprint'  => (bool) request()->query('autoprint'),
        ]);
    }

    /**
     * Kapan memo ini terbit, oleh siapa, dan dalam jabatan apa.
     *
     * Selama persetujuan penerbitnya belum terjadi, memo memang belum terbit —
     * jadi tanggalnya dikosongkan, bukan diisi tanggal hari ini. Mengisinya
     * dengan tanggal cetak membuat dokumen yang belum sah terlihat sudah sah.
     *
     * @return array{tanggal:?string, oleh:?string, jabatan:string, terbit:bool}
     */
    private function diterbitkan(PinjamanCabang $p): array
    {
        $role = self::ROLE_PENERBIT[$p->jenis] ?? 'coo';

        $entri = collect($p->approvals ?? [])
            ->first(fn ($a) => ($a['role'] ?? null) === $role && ($a['action'] ?? null) === 'approve');

        return [
            'tanggal' => $entri['at'] ?? null,
            'oleh'    => $entri ? $this->namaAktor($entri['user'] ?? null) : null,
            'jabatan' => self::JABATAN_LABEL[$role] ?? $role,
            'terbit'  => (bool) $entri,
        ];
    }

    private function tujuan(PinjamanCabang $p): string
    {
        if ($p->jenis === 'BPK') {
            $cabang = $p->daftarCabangRealisasi();
            return $cabang ? implode(', ', $cabang) : '-';
        }

        return $p->departemen ?: 'Finance';
    }

    private function keperluan(PinjamanCabang $p, $plan): string
    {
        if ($p->jenis === 'BPK') {
            $bagian = [];
            $bagian[] = 'keperluan pinjaman kendaraan / operasional dalam rangka pelaksanaan tugas audit'
                . ($plan?->no_spt ? " (SPT No. {$plan->no_spt})" : '')
                . ($p->no_spd ? ", No. SPD {$p->no_spd}" : '');
            if ($p->catatan) {
                $bagian[] = $p->catatan;
            }
            return implode('. ', $bagian);
        }

        return $p->catatan ?: ('keperluan operasional Departemen ' . ($p->departemen ?: 'Finance'));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending_koordinator' => 'Menunggu Koordinator',
            'pending_manajer'     => 'Menunggu Manajer Audit',
            'pending_coo'         => 'Menunggu COO',
            'pending_unit'        => 'Menunggu Unit Usaha',
            'pending_bpk'         => 'Menunggu Role BPK',
            'approved'            => 'Disetujui',
            'rejected'            => 'Ditolak',
            default               => $status,
        };
    }

    /**
     * Susun tahapan approval siap-tampil untuk jenis pinjaman ini — daftar
     * tahap yang DIHARUSKAN oleh alurnya (FLOW_BPK 5 tahap, FLOW_BPB 3 tahap),
     * dipasangkan dengan entri nyata di kolom `approvals` lewat field `role`
     * (BUKAN posisi array) supaya tetap benar walau ada entri koreksi admin
     * di antaranya. Begitu ada 'reject', tahap itu ditandai ditolak dan semua
     * tahap sesudahnya tetap "Belum terjadi" (proses berhenti di situ).
     */
    private function susunTahapan(PinjamanCabang $p): array
    {
        $approvals = $p->approvals ?? [];
        $submit    = collect($approvals)->firstWhere('action', 'submit');
        $ditolak   = collect($approvals)->firstWhere('action', 'reject');

        $baris = [[
            'label' => 'Diajukan',
            'waktu' => $submit['at'] ?? null,
            'aktor' => $this->namaAktor($submit['user'] ?? null),
        ]];

        $flow = $p->jenis === 'BPK'
            ? \App\Models\PinjamanCabang::FLOW_BPK
            : \App\Models\PinjamanCabang::FLOW_BPB;

        $sudahDitolak = false;
        foreach ($flow as $status) {
            $role = self::ROLE_PER_STAGE[$status] ?? null;
            if (!$role) continue; // 'approved' bukan tahap tersendiri

            $entri = collect($approvals)->first(fn($a) => ($a['role'] ?? null) === $role && ($a['action'] ?? null) === 'approve');

            if ($sudahDitolak) {
                $baris[] = ['label' => self::STAGE_LABEL[$role], 'waktu' => null, 'aktor' => null];
                continue;
            }

            if (!$entri && $ditolak && ($ditolak['role'] ?? null) === $role) {
                $baris[] = [
                    'label' => self::STAGE_LABEL[$role] . ' — DITOLAK',
                    'waktu' => $ditolak['at'] ?? null,
                    'aktor' => $this->namaAktor($ditolak['user'] ?? null),
                    'ditolak' => true,
                ];
                $sudahDitolak = true;
                continue;
            }

            $baris[] = [
                'label' => self::STAGE_LABEL[$role],
                'waktu' => $entri['at'] ?? null,
                'aktor' => $entri ? $this->namaAktor($entri['user'] ?? null) : null,
            ];
        }

        return $baris;
    }

    private function namaAktor(?string $username): ?string
    {
        if (!$username) return null;

        return User::where('username', $username)->value('display_name') ?: $username;
    }
}
