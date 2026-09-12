<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\PlanAudit;
use App\Models\User;
use App\Services\NotificationDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isi notifikasi plan audit.
 *
 * Notifikasi dulu berbunyi: 'Plan audit "0501/..." (CSC LANGSA) berstatus
 * "Menunggu Koordinator".' Kalimat itu menjelaskan KEADAAN, bukan TINDAKAN —
 * penerimanya harus menyimpulkan sendiri bahwa dirinyalah yang perlu
 * menyetujui. Di layar kunci HP yang terbaca cuma "ada sesuatu", tanpa
 * petunjuk apa pun soal apa yang harus dikerjakan.
 *
 * Tes ini mengunci agar tiap status menghasilkan kalimat yang menyebut
 * tindakannya, dan tetap menyebut nomor SPT serta cabangnya supaya penerima
 * tahu plan yang mana tanpa harus membuka aplikasi.
 */
class NotifikasiTindakanTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $status): PlanAudit
    {
        return PlanAudit::query()->create([
            'no_spt' => '0501/12/09/2026/SPT-IAT',
            'cabang' => 'CSC LANGSA',
            'cabang_area' => 'ACEH',
            'jenis_audit' => 'Audit Full CSC',
            'kepala_tim' => 'Abdul Aziz',
            'tim' => ['Abdul Aziz'],
            'status' => $status,
        ]);
    }

    private function kirim(string $status, string $role): AppNotification
    {
        User::factory()->create(['role' => $role, 'is_disabled' => false]);
        NotificationDispatcher::notifyPlanAuditStep($this->plan($status));

        $notif = AppNotification::query()->latest('id')->first();
        $this->assertNotNull($notif, "Tidak ada notifikasi yang dibuat untuk status {$status}.");

        return $notif;
    }

    public static function statusTindakan(): array
    {
        return [
            'menunggu koordinator' => ['pending_koordinator', 'koordinator', 'persetujuan Anda sebagai Koordinator', 'Perlu persetujuan Anda'],
            'menunggu manajer'     => ['pending_manajer', 'manajer', 'persetujuan Anda sebagai Manajer', 'Perlu persetujuan Anda'],
            'menunggu coo'         => ['pending_coo', 'coo', 'persetujuan Anda sebagai COO', 'Perlu persetujuan Anda'],
            'sudah disetujui'      => ['scheduled', 'auditor', 'Mulai Audit', 'Plan siap dikerjakan'],
            'masih draft'          => ['draft', 'auditor', 'Ajukan', 'Plan audit belum diajukan'],
            'perlu perbaikan'      => ['revisi', 'auditor', 'ajukan ulang', 'Plan perlu diperbaiki'],
        ];
    }

    #[DataProvider('statusTindakan')]
    public function test_notifikasi_menyebut_tindakan_bukan_sekadar_status(
        string $status, string $role, string $tindakan, string $judul
    ): void {
        $notif = $this->kirim($status, $role);

        $this->assertSame($judul, $notif->title);
        $this->assertStringContainsString($tindakan, $notif->message,
            "Notifikasi status {$status} tidak memberi tahu apa yang harus dilakukan.");
        $this->assertStringNotContainsString('berstatus "', $notif->message,
            'Notifikasi tidak boleh sekadar mengumumkan status.');
    }

    public function test_notifikasi_tetap_menyebut_nomor_spt_dan_cabang(): void
    {
        $notif = $this->kirim('pending_koordinator', 'koordinator');

        $this->assertStringContainsString('0501/12/09/2026/SPT-IAT', $notif->message);
        $this->assertStringContainsString('CSC LANGSA', $notif->message);
    }

    public function test_tautan_notifikasi_menunjuk_plan_yang_dimaksud(): void
    {
        $notif = $this->kirim('pending_koordinator', 'koordinator');

        // Halaman tujuan membaca parameter ini untuk menyorot barisnya
        // (lihat resources/js/akta-sorot.js).
        $this->assertSame('/akta/plan-audit?id='.$notif->notifiable_id, $notif->url);
    }

    public function test_status_tak_terduga_tetap_menghasilkan_notifikasi_yang_masuk_akal(): void
    {
        $notif = $this->kirim('cabang_active', 'auditor');

        $this->assertNotSame('', trim($notif->title));
        $this->assertStringContainsString('CSC LANGSA', $notif->message);
    }
}
