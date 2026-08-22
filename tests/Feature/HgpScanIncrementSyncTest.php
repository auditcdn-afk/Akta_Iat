<?php

namespace Tests\Feature;

use App\Models\PemeriksaanHga;
use App\Models\PemeriksaanHgp;
use App\Models\PemeriksaanRsaHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Form "Input Pemeriksaan Fisik" manual (Qty Fisik Scan) sebelumnya memutakhirkan
 * array items_json di memori BROWSER lalu mengirim ULANG SELURUH array itu ke
 * server (lihat komentar lama _doSaveHgp()). Kalau 2 akun memeriksa plan yang
 * sama, array di memori masing-masing browser adalah snapshot saat tab dibuka —
 * begitu akun A menyimpan, array stale milik akun B (yang belum di-refresh) ikut
 * tersimpan berikutnya dan MENIMPA BALIK hasil scan akun A. Endpoint scan-increment
 * (dipakai jalur scan barcode) sudah aman karena baca-ubah-simpan 1 item langsung
 * di server; fix-nya membuat form manual ikut lewat endpoint yang sama.
 *
 * Test ini memverifikasi endpoint scan-increment itu sendiri tetap benar dipakai
 * berturut-turut dari "device"/akun berbeda (baca ulang dari DB tiap panggilan,
 * bukan dari state lama) — dan bahwa keterangan/tgl (field baru yang dibawa form
 * manual) tidak ikut menghilang saat scan barcode berikutnya (yang tidak membawa
 * field itu) dipanggil.
 */
class HgpScanIncrementSyncTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'SBG MTR',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool' => 'hgp', 'nama_auditee' => 'Auditee Test',
        ])->assertOk();
    }

    public function test_scan_increment_dua_akun_berturut_turut_tidak_saling_menimpa(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'PART-1', 'sparepart' => 'Sparepart 1', 'saldoAkhir' => 10, 'fisik' => 0, 'logScan' => []],
            ],
        ]);

        // Dua "akun" memeriksa part yang sama secara berurutan — masing-masing
        // panggilan HARUS membaca ulang dari DB (bukan dari array lama di
        // memori), jadi qty keduanya terakumulasi, tidak ada yang hilang.
        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 3,
        ])->assertOk();

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 2,
        ])->assertOk()->assertJsonPath('item.fisik', 5);

        $item = PemeriksaanHgp::first()->items_json[0];
        $this->assertSame(5, $item['fisik']);
        $this->assertCount(2, $item['logScan']);
    }

    public function test_keterangan_dari_form_manual_tersimpan_dan_tidak_hilang_saat_scan_barcode(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'PART-1', 'sparepart' => 'Sparepart 1', 'saldoAkhir' => 10, 'fisik' => 0, 'keterangan' => '', 'logScan' => []],
            ],
        ]);

        // Form manual mengirim keterangan/tgl.
        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 1,
            'keterangan' => 'Sudah dicek fisik', 'tgl' => '2026-08-22',
        ])->assertOk()->assertJsonPath('item.keterangan', 'Sudah dicek fisik');

        // Scan barcode berikutnya TIDAK membawa keterangan/tgl sama sekali —
        // field yang sudah tersimpan harus tetap ada, bukan ikut kosong.
        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 1,
        ])->assertOk()->assertJsonPath('item.keterangan', 'Sudah dicek fisik');

        $item = PemeriksaanHgp::first()->items_json[0];
        $this->assertSame('Sudah dicek fisik', $item['keterangan']);
        $this->assertSame(2, $item['fisik']);
    }

    public function test_rsa_hgp_scan_increment_menerima_keterangan_dan_tgl(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool' => 'rsa-hgp', 'nama_auditee' => 'Auditee Test',
        ])->assertOk();

        PemeriksaanRsaHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'PART-1', 'sparepart' => 'Sparepart 1', 'saldoAkhir' => 10, 'fisik' => 0, 'keterangan' => '', 'logScan' => []],
            ],
        ]);

        $this->postJson('/api/audit-detail/rsa-hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 4,
            'keterangan' => 'Cek ulang', 'tgl' => '2026-08-22',
        ])->assertOk()
            ->assertJsonPath('item.fisik', 4)
            ->assertJsonPath('item.keterangan', 'Cek ulang');
    }

    public function test_hga_scan_increment_menerima_keterangan_dan_tgl(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool' => 'hga', 'nama_auditee' => 'Auditee Test',
        ])->assertOk();

        PemeriksaanHga::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'PART-1', 'sparepart' => 'Sparepart 1', 'saldoAkhir' => 10, 'fisik' => 0, 'fisikTtp' => 0, 'keterangan' => '', 'logScan' => []],
            ],
        ]);

        $this->postJson('/api/audit-detail/hga/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 2,
            'keterangan' => 'Cek ulang HGA', 'tgl' => '2026-08-22',
        ])->assertOk()
            ->assertJsonPath('item.fisik', 2)
            ->assertJsonPath('item.keterangan', 'Cek ulang HGA');
    }
}
