<?php

namespace App\Http\Controllers;

use App\Models\PlanAudit;
use App\Models\PlanAuditLog;
use App\Models\User;
use Illuminate\View\View;

// Cetak "Surat Perintah Tugas" (SPT) untuk satu plan audit — dokumen lepas dari
// Report Audit PDF (yang isinya hasil pemeriksaan), ini murni surat tugas +
// jejak waktu birokrasi (diajukan/disetujui/mulai/selesai). Kalimat tugasnya
// disesuaikan per jenis audit (lihat DESKRIPSI_TUGAS) supaya tidak generik
// "melakukan pemeriksaan" untuk semua jenis, dan waktu di setiap tahap diambil
// langsung dari plan_audit_logs — bukan diketik manual — jadi selalu real-time
// mengikuti status plan yang sesungguhnya, termasuk kalau baru sebagian tahap
// yang terjadi (tahap yang belum terjadi tampil "Belum terjadi").
class PlanAuditPdfController extends Controller
{
    // Kalimat tugas per jenis audit — daftar jenis diambil dari dropdown
    // #jenisAudit di resources/views/akta/pages/plan-audit.blade.php. Kalau
    // jenis_audit di data tidak ada di sini (mis. data lama / diketik manual
    // lewat API), fallback ke kalimat generik yang menyebut nama jenisnya
    // langsung — lihat deskripsiTugas().
    private const DESKRIPSI_TUGAS = [
        'Audit Full SO' => 'pemeriksaan menyeluruh terhadap seluruh area operasional Sales Office (kas, stock, piutang, dan administrasi terkait)',
        'Audit Full CSC' => 'pemeriksaan menyeluruh terhadap seluruh area operasional CSC (kas, bengkel, spare part, dan administrasi terkait)',
        'Audit Online Kas + Unit SMH' => 'pemeriksaan kas dan unit Stock Motor Honda (SMH) secara online',
        'Audit Online Kas + HGP & AHM Oils' => 'pemeriksaan kas dan HGP & AHM Oils secara online',
        'Audit Verifikasi HO' => 'verifikasi data dan dokumen terkait Head Office',
        'Audit Verifikasi Lapangan' => 'verifikasi kondisi dan data di lapangan',
        'Audit Serah Terima Sales Office Head' => 'pemeriksaan serah terima jabatan Sales Office Head',
        'Audit Serah Terima Warehouse' => 'pemeriksaan serah terima Warehouse',
        'Audit Kas + HGP & AHM Oils' => 'pemeriksaan kas dan HGP & AHM Oils',
        'Audit Kas + Unit SMH' => 'pemeriksaan kas dan unit Stock Motor Honda (SMH)',
        'Audit Kas + BPKB' => 'pemeriksaan kas dan BPKB',
        'Audit Serah Terima Partkeeper' => 'pemeriksaan serah terima jabatan Partkeeper',
        'Audit Serah Terima Workshop Head' => 'pemeriksaan serah terima jabatan Workshop Head',
        'Audit PJS SO HEAD' => 'pemeriksaan dalam rangka penunjukan Pejabat Sementara (PJS) SO Head',
        'Audit PJS SO ADH' => 'pemeriksaan dalam rangka penunjukan Pejabat Sementara (PJS) SO ADH',
        'Audit CHTC' => 'pemeriksaan CHTC (Capella Honda Training Center)',
        'Audit PAV/HC3/HOHO MDN' => 'pemeriksaan PAV / HC3 / HOHO Medan',
        'Faktur' => 'pemeriksaan faktur',
        'Audit Serah Terima Kasir' => 'pemeriksaan serah terima jabatan Kasir',
        'Audit Serah Terima Pos Head' => 'pemeriksaan serah terima jabatan Pos Head',
    ];

    // Sinkron dengan STATUS_LABELS di resources/js/akta-plan-audit.js.
    private const STATUS_LABEL = [
        'draft'               => 'Draft',
        'pending_koordinator' => 'Menunggu Koordinator',
        'pending_manajer'     => 'Menunggu Manajer',
        'pending_coo'         => 'Menunggu COO',
        'scheduled'           => 'Disetujui',
        'running'             => 'Audit Berjalan',
        'cabang_active'       => 'Cabang Aktif',
        'revisi'              => 'Perlu Perbaikan',
        'done'                => 'Selesai',
        'cancelled'           => 'Dibatalkan',
    ];

    private const JABATAN_LABEL = [
        'admin'       => 'Administrator',
        'auditor'     => 'Internal Auditor',
        'manajer'     => 'Manajer',
        'koordinator' => 'Koordinator',
        'coo'         => 'Chief Operating Officer',
        'viewer'      => 'Viewer',
    ];

    public function spt(PlanAudit $plan): View
    {
        $logs = PlanAuditLog::where('plan_audit_id', $plan->id)
            ->orderBy('created_at')
            ->get();

        $tahapan = $this->susunTahapan($logs);

        return view('akta.pdf.plan-audit-spt', [
            'plan'          => $plan,
            'deskripsiTugas' => $this->deskripsiTugas($plan->jenis_audit),
            'jabatanKepalaTim' => $this->jabatanUntukNama($plan->kepala_tim),
            'tahapan'       => $tahapan,
            'statusLabel'   => self::STATUS_LABEL[$plan->status] ?? $plan->status,
            'autoprint'     => (bool) request()->query('autoprint'),
        ]);
    }

    private function deskripsiTugas(?string $jenisAudit): string
    {
        if ($jenisAudit && isset(self::DESKRIPSI_TUGAS[$jenisAudit])) {
            return self::DESKRIPSI_TUGAS[$jenisAudit];
        }

        return 'pemeriksaan sesuai ruang lingkup "' . ($jenisAudit ?: 'audit') . '"';
    }

    private function jabatanUntukNama(?string $namaTampilan): ?string
    {
        if (!$namaTampilan) return null;

        $user = User::where('display_name', $namaTampilan)->orWhere('name', $namaTampilan)->first();

        return $user ? (self::JABATAN_LABEL[$user->role] ?? $user->role) : null;
    }

    /**
     * Susun tahapan birokrasi plan ini jadi daftar berurutan siap-tampil, satu
     * baris per tahap (diajukan/disetujui koordinator/manajer/COO/mulai
     * audit/mulai cabang/selesai), diambil dari log transisi status —
     * to_status tiap log itulah yang menandai tahap mana yang baru terjadi
     * (recordLog dipanggil dengan action generik 'advance' untuk semua
     * transisi maju, jadi to_status yang jadi penanda, bukan action).
     * Tahap yang belum punya log (belum terjadi) tetap ditampilkan dengan
     * waktu & aktor kosong, bukan dihilangkan, supaya progres yang tersisa
     * tetap terlihat di suratnya.
     */
    private function susunTahapan($logs): array
    {
        $cariAksi = fn(string $toStatus) => $logs->firstWhere('to_status', $toStatus);
        $diajukan = $logs->firstWhere('action', 'created');

        $baris = fn(string $label, $log) => [
            'label' => $label,
            'waktu' => $log?->created_at,
            'aktor' => $log ? $this->namaAktor($log->actor) : null,
        ];

        return [
            $baris('Diajukan', $diajukan),
            $baris('Disetujui Koordinator', $cariAksi('pending_manajer')),
            $baris('Disetujui Manajer', $cariAksi('pending_coo')),
            $baris('Disetujui COO', $cariAksi('scheduled')),
            $baris('Mulai Audit (berangkat)', $cariAksi('running')),
            $baris('Tiba di Unit Usaha', $cariAksi('cabang_active')),
            $baris('Selesai', $cariAksi('done')),
        ];
    }

    private function namaAktor(?string $username): ?string
    {
        if (!$username) return null;

        return User::where('username', $username)->value('display_name') ?: $username;
    }
}
