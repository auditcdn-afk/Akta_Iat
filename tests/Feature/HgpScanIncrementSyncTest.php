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

    // Edit inline kolom WO di tabel (bukan lewat form "Input Pemeriksaan Fisik")
    // sebelumnya juga memicu simpan penuh array — celah yang sama, jalur berbeda.
    // qty=0 dipakai supaya update ini tidak ikut mencatat logScan/menambah fisik.
    public function test_update_wo_qty_nol_tidak_menambah_fisik_atau_logscan(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'PART-1', 'sparepart' => 'Sparepart 1', 'saldoAkhir' => 10, 'fisik' => 3, 'wo' => 0, 'logScan' => [['at' => now(), 'qty' => 3]]],
            ],
        ]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 0, 'wo' => 2,
        ])->assertOk()
            ->assertJsonPath('item.fisik', 3)
            ->assertJsonPath('item.wo', 2)
            // akhir = saldo(10) - (fisik(3) + wo(2)) = 5; selisih = 5 - 10 = -5
            ->assertJsonPath('item.selisih', -5);

        $item = PemeriksaanHgp::first()->items_json[0];
        $this->assertCount(1, $item['logScan']);
    }

    // Dua auditor menambah No. Part manual yang BERBEDA secara berurutan — sebelum
    // fix, "Tambah Part Manual" juga push ke array lokal lalu kirim ulang seluruh
    // array (endpoint save() lama), sehingga part yang ditambahkan auditor pertama
    // bisa hilang kalau auditor kedua (snapshot-nya belum ter-refresh) menyusul
    // menambah part lain. add-item sekarang baca-ubah-simpan di server.
    public function test_dua_akun_tambah_part_manual_berbeda_tidak_saling_hilang(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => []]);

        $this->postJson('/api/audit-detail/hgp/add-item', [
            'planAuditId' => $this->plan->id, 'noPart' => 'MAN-A', 'sparepart' => 'Manual A',
        ])->assertOk();

        $this->postJson('/api/audit-detail/hgp/add-item', [
            'planAuditId' => $this->plan->id, 'noPart' => 'MAN-B', 'sparepart' => 'Manual B',
        ])->assertOk();

        $items = PemeriksaanHgp::first()->items_json;
        $this->assertCount(2, $items);
        $this->assertSame('MAN-A', $items[0]['noPart']);
        $this->assertSame('MAN-B', $items[1]['noPart']);
    }

    public function test_tambah_part_manual_duplikat_ditolak(): void
    {
        PemeriksaanHgp::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [['noPart' => 'PART-1', 'sparepart' => 'Sparepart 1', 'saldoAkhir' => 10, 'fisik' => 0, 'logScan' => []]],
        ]);

        $this->postJson('/api/audit-detail/hgp/add-item', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'sparepart' => 'Duplikat',
        ])->assertStatus(422);

        $this->assertCount(1, PemeriksaanHgp::first()->items_json);
    }

    public function test_hga_update_fisik_ttp_qty_nol_tidak_menambah_fisik_scan(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool' => 'hga', 'nama_auditee' => 'Auditee Test',
        ])->assertOk();

        PemeriksaanHga::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['noPart' => 'PART-1', 'sparepart' => 'Sparepart 1', 'saldoAkhir' => 10, 'fisik' => 1, 'fisikTtp' => 0, 'logScan' => [['at' => now(), 'qty' => 1]]],
            ],
        ]);

        $this->postJson('/api/audit-detail/hga/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 0, 'fisikTtp' => 4,
        ])->assertOk()
            ->assertJsonPath('item.fisik', 1)
            ->assertJsonPath('item.fisikTtp', 4)
            // akhir = saldo(10) - (fisik(1)+fisikTtp(4)) = 5; selisih = 5-10 = -5
            ->assertJsonPath('item.selisih', -5);

        $item = PemeriksaanHga::first()->items_json[0];
        $this->assertCount(1, $item['logScan']);
    }
}
