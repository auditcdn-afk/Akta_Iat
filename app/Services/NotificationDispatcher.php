<?php

namespace App\Services;

use App\Jobs\KirimPushNotification;
use App\Models\AppNotification;
use App\Models\AuditRecommendation;
use App\Models\PinjamanCabang;
use App\Models\PlanAudit;
use App\Models\SuratKeputusan;
use App\Services\WebPush\WebPushSender;
use Throwable;

class NotificationDispatcher
{
    /** Jangan kirim notifikasi baru untuk (user, step) yang sama sebelum jeda ini lewat. */
    private const REMINDER_INTERVAL_HOURS = 20;

    /**
     * Role yang harus memproses Plan Audit di tiap status, sama dengan
     * PlanAuditController::TRANSITIONS[status]['roles'] (minus 'admin',
     * yang di sana cuma override, bukan pemilik gerbang). Status tanpa
     * entry di sini (mis. "done") berarti tidak ada yang perlu diberi tahu.
     */
    private const PLAN_STATUS_ROLES = [
        'draft'               => ['auditor'],
        'pending_koordinator' => ['koordinator'],
        'pending_manajer'     => ['manajer'],
        'pending_coo'         => ['coo'],
        'scheduled'           => ['auditor'],
        'running'             => ['__branch__'],
        'cabang_active'       => ['auditor', 'manajer', '__branch__'],
        'revisi'              => ['auditor'],
    ];

    /**
     * Apa yang harus DILAKUKAN penerima pada tiap status — bukan sekadar apa
     * statusnya.
     *
     * Sebelumnya notifikasi berbunyi: 'Plan audit "0501/..." (CSC LANGSA)
     * berstatus "Menunggu Koordinator".' Kalimat itu menjelaskan keadaan, dan
     * penerimanya harus menyimpulkan sendiri bahwa dirinyalah yang perlu
     * menyetujui. Di layar kunci HP, yang terbaca cuma "ada sesuatu" tanpa
     * petunjuk apa pun tentang tindakannya.
     *
     * Bentuknya [judul, kalimat tindakan]. Penerima notifikasi status
     * pending_* memang orang yang harus bertindak (lihat PLAN_STATUS_ROLES),
     * jadi kalimatnya boleh menyapa langsung dengan "Anda".
     */
    private const PLAN_TINDAKAN = [
        'draft'               => ['Plan audit belum diajukan', 'masih berstatus Draft — tekan "Ajukan" agar diproses Koordinator.'],
        'pending_koordinator' => ['Perlu persetujuan Anda', 'menunggu persetujuan Anda sebagai Koordinator.'],
        'pending_manajer'     => ['Perlu persetujuan Anda', 'menunggu persetujuan Anda sebagai Manajer.'],
        'pending_coo'         => ['Perlu persetujuan Anda', 'menunggu persetujuan Anda sebagai COO.'],
        'scheduled'           => ['Plan siap dikerjakan', 'sudah disetujui COO — buka menu Audit lalu tekan "Mulai Audit".'],
        'running'             => ['Konfirmasi kedatangan auditor', 'auditnya sedang berjalan — cabang perlu mengonfirmasi kedatangan auditor.'],
        'cabang_active'       => ['Audit menunggu diselesaikan', 'cabang sudah memulai — lengkapi tindak lanjut, lalu nyatakan audit selesai.'],
        'revisi'              => ['Plan perlu diperbaiki', 'dikembalikan dengan catatan — perbaiki isinya lalu ajukan ulang.'],
    ];

    private const PLAN_STATUS_LABELS = [
        'draft'               => 'Draft',
        'pending_koordinator' => 'Menunggu Koordinator',
        'pending_manajer'     => 'Menunggu Manajer',
        'pending_coo'         => 'Menunggu COO',
        'scheduled'           => 'Disetujui',
        'running'             => 'Audit Berjalan',
        'cabang_active'       => 'Mulai Cabang',
        'revisi'              => 'Perlu Perbaikan',
    ];

    /** Notifikasi penerima step pending pertama (yang belum diisi) pada rekomendasi. */
    public static function notifyRecommendationStep(AuditRecommendation $recommendation): void
    {
        // Migrasi database SENGAJA tidak dijalankan otomatis saat deploy (lihat
        // deploy.yml) -- tabel app_notifications bisa saja belum ada di server
        // untuk sementara. Notifikasi cuma pelengkap; gagal di sini TIDAK boleh
        // menggagalkan pembuatan/approval rekomendasi itu sendiri.
        try {
            $steps = $recommendation->steps ?: [];
            $pendingIndex = null;
            foreach ($steps as $i => $step) {
                if (($step['status'] ?? null) === 'pending') {
                    $pendingIndex = $i;
                    break;
                }
            }
            if ($pendingIndex === null) {
                return;
            }

            $stepRole = $steps[$pendingIndex]['role'] ?? $steps[$pendingIndex]['step'] ?? '';
            if (!$stepRole) {
                return;
            }

            $cabang = $recommendation->planAudit?->cabang ?? '';
            $recipients = BirokrasiResolver::recipientsForStep($stepRole, $cabang);

            $title = 'Giliran mengisi rekomendasi';
            $message = sprintf(
                'Rekomendasi "%s" (%s) menunggu diisi oleh %s.',
                $recommendation->judul,
                $cabang ?: '-',
                $stepRole
            );
            $url = '/akta/rekomendasi?id=' . $recommendation->id;

            foreach ($recipients as $user) {
                static::notifyIfDue(
                    $user->id,
                    'birokrasi_step',
                    AuditRecommendation::class,
                    $recommendation->id,
                    (string) $pendingIndex,
                    $title,
                    $message,
                    $url
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Tandai notifikasi step tertentu (semua penerima) sudah terbaca, karena step-nya sudah diisi. */
    public static function resolveRecommendationStep(AuditRecommendation $recommendation, int $stepIndex): void
    {
        try {
            AppNotification::query()
                ->where('notifiable_type', AuditRecommendation::class)
                ->where('notifiable_id', $recommendation->id)
                ->where('step_key', (string) $stepIndex)
                ->unread()
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Notifikasi penerima tahap SK yang sedang pending (manajer/afd). */
    public static function notifySuratKeputusanStep(SuratKeputusan $suratKeputusan): void
    {
        try {
            $stage = match ($suratKeputusan->status) {
                'pending_manajer' => 'manajer',
                'pending_afd' => 'afd',
                default => null,
            };
            if ($stage === null) {
                return;
            }

            $recipients = BirokrasiResolver::recipientsForRoles([$stage]);

            $title = 'Giliran approve SK';
            $message = sprintf(
                'SK "%s" (%s) menunggu approval %s.',
                $suratKeputusan->no_sk ?: '-',
                $suratKeputusan->unit_usaha ?: '-',
                strtoupper($stage)
            );
            $url = '/akta/sk?id=' . $suratKeputusan->id;

            foreach ($recipients as $user) {
                static::notifyIfDue(
                    $user->id,
                    'sk_step',
                    SuratKeputusan::class,
                    $suratKeputusan->id,
                    $stage,
                    $title,
                    $message,
                    $url
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Tandai notifikasi tahap SK tertentu sudah terbaca, karena tahap itu sudah di-approve/ditolak. */
    public static function resolveSuratKeputusanStage(SuratKeputusan $suratKeputusan, string $stage): void
    {
        try {
            AppNotification::query()
                ->where('notifiable_type', SuratKeputusan::class)
                ->where('notifiable_id', $suratKeputusan->id)
                ->where('step_key', $stage)
                ->unread()
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Notifikasi penerima yang berwenang memproses Plan Audit di status sekarang. */
    public static function notifyPlanAuditStep(PlanAudit $plan): void
    {
        try {
            $roles = self::PLAN_STATUS_ROLES[$plan->status] ?? null;
            if ($roles === null) {
                return;
            }

            $recipients = BirokrasiResolver::recipientsForPlanStatus($roles, $plan->cabang);

            [$title, $tindakan] = self::PLAN_TINDAKAN[$plan->status]
                ?? ['Giliran memproses plan audit', 'berstatus "'.(self::PLAN_STATUS_LABELS[$plan->status] ?? $plan->status).'".'];

            $message = sprintf('%s (%s) %s', $plan->no_spt, $plan->cabang ?: '-', $tindakan);
            $url = '/akta/plan-audit?id=' . $plan->id;

            foreach ($recipients as $user) {
                static::notifyIfDue(
                    $user->id,
                    'plan_audit_step',
                    PlanAudit::class,
                    $plan->id,
                    $plan->status,
                    $title,
                    $message,
                    $url
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /**
     * Plan audit DITOLAK — kabari tim plan itu sendiri, bukan seluruh auditor.
     *
     * Penolakan mengembalikan status ke draft, dan notifikasi draft biasa cuma
     * berbunyi "masih berstatus Draft — tekan Ajukan". Kalimat itu menghapus
     * kabar terpentingnya: plannya ditolak, oleh siapa, dengan alasan apa, dan
     * bahwa isinya harus DIPERBAIKI dulu — bukan diajukan ulang apa adanya.
     */
    public static function notifyPlanAuditRejected(PlanAudit $plan, string $dariStatus, ?string $alasan, ?string $olehRole = null): void
    {
        try {
            $penolak = self::PLAN_STATUS_LABELS[$dariStatus] ?? $dariStatus;
            $penolak = str_replace('Menunggu ', '', $penolak);

            $message = sprintf(
                '%s (%s) ditolak %s. %s Buka plannya, perbaiki isinya, lalu ajukan ulang.',
                $plan->no_spt,
                $plan->cabang ?: '-',
                $olehRole ? ucfirst($olehRole) : $penolak,
                $alasan ? 'Alasan: ' . $alasan . '.' : 'Tidak ada alasan yang dituliskan.'
            );

            foreach (BirokrasiResolver::recipientsForPlanTeam($plan) as $user) {
                static::kirim(
                    $user->id,
                    'plan_audit_step',
                    PlanAudit::class,
                    $plan->id,
                    'reject',
                    'Plan audit ditolak — perlu diperbaiki',
                    $message,
                    '/akta/plan-audit?id=' . $plan->id
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /**
     * Pengajuan pinjaman cabang (BPK/BPB) DITOLAK — kabari pengajunya.
     *
     * Sebelumnya alur pinjaman tidak mengirim notifikasi sama sekali: auditor
     * baru tahu pengajuannya ditolak kalau kebetulan membuka lagi task-nya.
     */
    public static function notifyPinjamanRejected(PinjamanCabang $pinjaman, ?string $alasan, ?string $olehRole = null): void
    {
        try {
            $recipients = BirokrasiResolver::recipientsForPengaju($pinjaman->created_by);

            if ($recipients->isEmpty()) {
                return;
            }

            $message = sprintf(
                'Pengajuan %s Rp %s ditolak%s. %s Buka task-nya, perbaiki pengajuannya, lalu ajukan ulang.',
                $pinjaman->jenis ?: 'pinjaman',
                number_format((float) $pinjaman->nominal, 0, ',', '.'),
                $olehRole ? ' oleh ' . ucfirst($olehRole) : '',
                $alasan ? 'Alasan: ' . $alasan . '.' : 'Tidak ada alasan yang dituliskan.'
            );

            foreach ($recipients as $user) {
                static::kirim(
                    $user->id,
                    'pinjaman_cabang_step',
                    PinjamanCabang::class,
                    $pinjaman->id,
                    'reject',
                    'Pengajuan ' . ($pinjaman->jenis ?: 'pinjaman') . ' ditolak — perlu diperbaiki',
                    $message,
                    '/akta/task?pinjaman=' . $pinjaman->id
                );
            }
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Tandai notifikasi penolakan sudah terbaca, karena dokumennya sudah diperbaiki/diajukan ulang. */
    public static function resolveRejection(string $notifiableType, int $notifiableId): void
    {
        try {
            AppNotification::query()
                ->where('notifiable_type', $notifiableType)
                ->where('notifiable_id', $notifiableId)
                ->where('step_key', 'reject')
                ->unread()
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    /** Tandai notifikasi status Plan Audit tertentu sudah terbaca, karena statusnya sudah berubah. */
    public static function resolvePlanAuditStatus(PlanAudit $plan, string $status): void
    {
        try {
            AppNotification::query()
                ->where('notifiable_type', PlanAudit::class)
                ->where('notifiable_id', $plan->id)
                ->where('step_key', $status)
                ->unread()
                ->update(['read_at' => now()]);
        } catch (Throwable) {
            // Notifikasi tidak boleh membuat request utama gagal.
        }
    }

    private static function notifyIfDue(
        int $userId,
        string $type,
        string $notifiableType,
        int $notifiableId,
        string $stepKey,
        string $title,
        string $message,
        ?string $url
    ): void {
        $last = AppNotification::query()
            ->where('user_id', $userId)
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->where('step_key', $stepKey)
            ->latest('id')
            ->first();

        if ($last && $last->created_at && $last->created_at->gt(now()->subHours(self::REMINDER_INTERVAL_HOURS))) {
            return;
        }

        static::kirim($userId, $type, $notifiableType, $notifiableId, $stepKey, $title, $message, $url);
    }

    /**
     * Buat notifikasi TANPA mengecek jeda pengingat.
     *
     * Dipakai untuk kejadian sekali-terjadi yang wajib sampai: penolakan.
     * Kalau ini lewat notifyIfDue(), penolakan kedua atas plan/pinjaman yang
     * sama dalam 20 jam akan hilang diam-diam — padahal justru itu kabar yang
     * harus segera ditindaklanjuti pengajunya.
     */
    private static function kirim(
        int $userId,
        string $type,
        string $notifiableType,
        int $notifiableId,
        string $stepKey,
        string $title,
        string $message,
        ?string $url
    ): void {
        $notifikasi = AppNotification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'step_key' => $stepKey,
            'title' => $title,
            'message' => $message,
            'url' => $url,
        ]);

        static::dorongKePerangkat($notifikasi);
    }

    /**
     * Teruskan notifikasi ke layar HP penerimanya (Web Push), lewat antrean.
     *
     * Yang masuk ke permintaan pengguna hanyalah satu baris ke tabel antrean;
     * pengiriman sesungguhnya — satu permintaan HTTPS per perangkat — dikerjakan
     * di latar belakang, supaya tombol Approve tidak ikut menunggu.
     *
     * Koneksi antreannya disebut eksplisit, bukan default aplikasi: kalau .env
     * produksi kebetulan masih QUEUE_CONNECTION=sync, pengiriman itu akan
     * diam-diam ditarik kembali ke dalam permintaan pengguna dan justru
     * menghadirkan kembali delay yang ingin dihindari.
     */
    private static function dorongKePerangkat(AppNotification $notifikasi): void
    {
        if (! WebPushSender::siap()) {
            return;
        }

        try {
            KirimPushNotification::dispatch($notifikasi->id)
                ->onConnection(config('webpush.queue_connection'))
                ->onQueue(config('webpush.queue'));
        } catch (Throwable) {
            // Antrean belum siap (mis. tabel jobs belum dimigrasi) tidak boleh
            // menggagalkan approval; notifikasi in-app-nya sudah tersimpan.
        }
    }
}
