<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\HgaController;
use App\Http\Controllers\Api\HgpController;
use App\Http\Controllers\Api\RsaHgpController;
use App\Http\Controllers\Concerns\MengunciDataPemeriksaan;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Satu SPT, dua auditor, satu daftar HGP & AHM Oils.
 *
 * Tiap scan membuat server membaca SELURUH items_json, mengubah satu item, lalu
 * menulisnya kembali. Tanpa penguncian, dua permintaan yang datang berbarengan
 * sama-sama membaca isi yang sama dan yang menyimpan belakangan menulis dari
 * salinan lama — hasil scan auditor satunya hilang tanpa jejak, itemnya tetap
 * tercatat "belum discan", lalu muncul sebagai selisih palsu di laporan.
 *
 * Penjaganya: pembacaan dan penulisan berada di dalam SATU transaksi dengan
 * baris pemeriksaannya dikunci (lockForUpdate), jadi permintaan kedua menunggu
 * giliran dan membaca hasil yang sudah lengkap.
 */
class ScanDuaAuditorTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0424/28/07/2026/SPT-IAT',
            'cabang'      => 'CSC TBH',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);
        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'hgp',
            'nama_auditor'  => 'Auditor Uji',
            'nama_auditee'  => 'Auditee Uji',
        ]);
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [[
                'noPart' => '061B1KEH002', 'sparepart' => 'GASKET KIT B',
                'saldoAkhir' => 7, 'fisik' => 0, 'wo' => 0,
                'akhir' => 7, 'selisih' => -7, 'logScan' => [],
            ]],
        ]);
    }

    public function test_scan_dua_auditor_dijumlahkan_jadi_satu_hasil(): void
    {
        // Auditor 1 menghitung 3 batang.
        $this->sebagai('auditor1');
        $this->scan('061B1KEH002', 3, 'a1')->assertOk();

        $it = $this->item();
        $this->assertSame(3.0, (float) $it['fisik'], 'Setelah auditor 1: fisik 3.');
        $this->assertSame(4.0, (float) $it['akhir'], 'Sisanya 4.');

        // Auditor 2, perangkat lain, menghitung 4 batang sisanya.
        $this->sebagai('auditor2');
        $this->scan('061B1KEH002', 4, 'a2')->assertOk();

        $it = $this->item();
        $this->assertSame(7.0, (float) $it['fisik'], 'Hasil kedua auditor dijumlahkan: 3 + 4 = 7.');
        $this->assertSame(0.0, (float) $it['selisih'], 'Cocok dengan saldo 7, jadi tidak ada selisih.');
        $this->assertCount(2, $it['logScan'], 'Riwayat kedua auditor tersimpan dua-duanya.');
    }

    public function test_penulisan_scan_berada_di_dalam_transaksi(): void
    {
        $this->sebagai('auditor1');

        $dasar = DB::transactionLevel();
        $saatMenulis = null;
        DB::listen(function ($q) use (&$saatMenulis) {
            if ($saatMenulis === null && preg_match('/update\s+["`]?pemeriksaan_hgp/i', $q->sql)) {
                $saatMenulis = DB::transactionLevel();
            }
        });

        $this->scan('061B1KEH002', 1, 'x1')->assertOk();

        $this->assertNotNull($saatMenulis, 'Tidak ada penulisan ke pemeriksaan_hgp yang terpantau.');
        $this->assertSame($dasar + 1, $saatMenulis,
            'Baca-ubah-tulis hasil scan harus berada di dalam satu transaksi, kalau tidak '
            . 'penguncian barisnya tidak berlaku dan scan auditor lain bisa tertimpa.');
    }

    /**
     * Ketiga tool scan menulis dengan pola baca-ubah-tulis yang sama, jadi
     * ketiganya harus memakai penguncian yang sama.
     */
    #[DataProvider('controllerScan')]
    public function test_tool_scan_memakai_penguncian(string $controller): void
    {
        $this->assertContains(
            MengunciDataPemeriksaan::class,
            class_uses_recursive($controller),
            "{$controller} menulis items_json tanpa penguncian — scan dua auditor bisa saling menimpa."
        );
    }

    public static function controllerScan(): array
    {
        return [
            'HGP & AHM Oils'     => [HgpController::class],
            'RSA HGP & AHM Oils' => [RsaHgpController::class],
            'HGA (Accessories)'  => [HgaController::class],
        ];
    }

    // ── Bantuan ──────────────────────────────────────────────────────────────

    private function sebagai(string $nama): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'username' => $nama]));
    }

    private function scan(string $noPart, float $qty, string $idScan)
    {
        return $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id,
            'noPart'      => $noPart,
            'qty'         => $qty,
            'entries'     => [['id' => $idScan, 'qty' => $qty]],
        ]);
    }

    private function item(): array
    {
        return PemeriksaanHgp::query()
            ->where('plan_audit_id', $this->plan->id)
            ->firstOrFail()
            ->items_json[0];
    }
}
