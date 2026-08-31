<?php

namespace Tests\Feature;

use App\Models\PemeriksaanHgp;
use App\Models\PemeriksaanRsaHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Auditor melaporkan item yang SUDAH discan tiba-tiba kembali tampil belum
 * discan. Penyebabnya: items_json satu tool disimpan sebagai satu dokumen, dan
 * simpan-penuh (save()) menerima apa pun yang dikirim browser. Array lama yang
 * masih dipegang satu perangkat karena itu bisa menimpa hasil scan perangkat
 * lain yang lebih baru — lengkap dengan riwayat logScan-nya. Simpan-penuh itu
 * tidak cuma dipicu tombol Simpan: import ulang dan (dulu) fallback ketika
 * request delta gagal juga lewat jalur yang sama.
 *
 * Tes ini mengunci aturan penjaganya: jejak pemeriksaan tidak boleh menyusut.
 */
class HgpTidakKehilanganScanTest extends TestCase
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

        foreach (['hgp', 'rsa-hgp'] as $tool) {
            $this->postJson('/api/audit-detail/auditor', [
                'plan_audit_id' => $this->plan->id, 'tool' => $tool, 'nama_auditee' => 'Auditee Test',
            ])->assertOk();
        }
    }

    /** Daftar 2 item yang belum tersentuh — snapshot yang dipegang browser lama. */
    private function snapshotAwal(): array
    {
        return [
            ['noPart' => 'PART-1', 'sparepart' => 'A', 'saldoAkhir' => 10, 'fisik' => 0, 'logScan' => []],
            ['noPart' => 'PART-2', 'sparepart' => 'B', 'saldoAkhir' => 10, 'fisik' => 0, 'logScan' => []],
        ];
    }

    public function test_snapshot_lama_tidak_boleh_menimpa_hasil_scan_yang_lebih_baru(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        // Auditor A menembak PART-1 tujuh kali lewat endpoint delta.
        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 7,
        ])->assertOk()->assertJsonPath('item.fisik', 7);

        // Browser B masih memegang snapshot lama lalu menyimpan penuh.
        $this->postJson('/api/audit-detail/hgp', [
            'planAuditId' => $this->plan->id, 'items' => $this->snapshotAwal(),
        ])->assertStatus(409)->assertJsonPath('stale', true);

        // Hasil scan auditor A harus tetap utuh.
        $item = PemeriksaanHgp::first()->items_json[0];
        $this->assertSame(7, $item['fisik']);
        $this->assertCount(1, $item['logScan']);
    }

    public function test_simpan_penuh_yang_membawa_hasil_scan_tetap_diterima(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 2,
        ])->assertOk();

        // Browser yang datanya sudah segar: bawa hasil scan + tambahan keterangan.
        $items = PemeriksaanHgp::first()->items_json;
        $items[1]['keterangan'] = 'Diperiksa bersama';

        $this->postJson('/api/audit-detail/hgp', [
            'planAuditId' => $this->plan->id, 'items' => $items,
        ])->assertOk();

        $tersimpan = PemeriksaanHgp::first()->items_json;
        $this->assertSame(2, $tersimpan[0]['fisik']);
        $this->assertSame('Diperiksa bersama', $tersimpan[1]['keterangan']);
    }

    /** Koreksi qty minus menurunkan fisik TAPI menambah entri log — harus tetap lolos. */
    public function test_koreksi_qty_minus_tidak_ikut_dianggap_kehilangan_data(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 5,
        ])->assertOk();

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => -2,
        ])->assertOk()->assertJsonPath('item.fisik', 3);

        $items = PemeriksaanHgp::first()->items_json;
        $this->postJson('/api/audit-detail/hgp', [
            'planAuditId' => $this->plan->id, 'items' => $items,
        ])->assertOk();

        $this->assertSame(3, PemeriksaanHgp::first()->items_json[0]['fisik']);
    }

    /** Item yang lenyap sama sekali dari payload juga terhitung kehilangan. */
    public function test_item_yang_hilang_dari_payload_ikut_ditolak(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-2', 'qty' => 4,
        ])->assertOk();

        $this->postJson('/api/audit-detail/hgp', [
            'planAuditId' => $this->plan->id,
            'items' => [$this->snapshotAwal()[0]],   // PART-2 dibuang
        ])->assertStatus(409);

        $this->assertCount(2, PemeriksaanHgp::first()->items_json);
        $this->assertSame(4, PemeriksaanHgp::first()->items_json[1]['fisik']);
    }

    /** Import ulang: saldo baru dari file, hasil scan dibawa server (bukan dari layar). */
    public function test_import_ulang_membawa_hasil_scan_yang_sudah_ada_di_server(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 6,
        ])->assertOk();

        // Browser mengirim hasil parse Excel: fisik 0 semua, saldo berubah, plus 1 part baru.
        $this->postJson('/api/audit-detail/hgp', [
            'planAuditId' => $this->plan->id,
            'mode'  => 'import',
            'items' => [
                ['noPart' => 'PART-1', 'sparepart' => 'A', 'saldoAkhir' => 20, 'fisik' => 0, 'logScan' => []],
                ['noPart' => 'PART-3', 'sparepart' => 'C', 'saldoAkhir' => 5,  'fisik' => 0, 'logScan' => []],
            ],
        ])->assertOk();

        $items = collect(PemeriksaanHgp::first()->items_json)->keyBy('noPart');

        // Hasil scan tetap, saldo ikut yang baru, akhir & selisih dihitung ulang.
        $this->assertSame(6, $items['PART-1']['fisik']);
        $this->assertCount(1, $items['PART-1']['logScan']);
        $this->assertEquals(20, $items['PART-1']['saldoAkhir']);
        $this->assertEquals(14, $items['PART-1']['akhir']);
        $this->assertEquals(-14, $items['PART-1']['selisih']);
        // Part baru dari file ikut masuk.
        $this->assertTrue($items->has('PART-3'));
        // PART-2 belum tersentuh & tidak ada di file baru — memang gugur.
        $this->assertFalse($items->has('PART-2'));
    }

    /** Part yang sudah discan tapi tidak ada di file import tetap dipertahankan. */
    public function test_import_ulang_tidak_membuang_part_yang_sudah_discan(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-2', 'qty' => 3,
        ])->assertOk();

        $this->postJson('/api/audit-detail/hgp', [
            'planAuditId' => $this->plan->id, 'mode' => 'import',
            'items' => [['noPart' => 'PART-1', 'sparepart' => 'A', 'saldoAkhir' => 10, 'fisik' => 0, 'logScan' => []]],
        ])->assertOk();

        $items = collect(PemeriksaanHgp::first()->items_json)->keyBy('noPart');
        $this->assertTrue($items->has('PART-2'), 'Part yang sudah discan tidak boleh hilang saat import ulang.');
        $this->assertSame(3, $items['PART-2']['fisik']);
    }

    /** "Hapus Semua Data" tetap harus bisa mengosongkan — itu memang disengaja. */
    public function test_mode_replace_tetap_bisa_mengosongkan_data(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 9,
        ])->assertOk();

        $this->postJson('/api/audit-detail/hgp', [
            'planAuditId' => $this->plan->id, 'mode' => 'replace', 'items' => [],
        ])->assertOk();

        $this->assertSame([], PemeriksaanHgp::first()->items_json);
    }

    /** Kiriman ulang scan yang sama (jaringan putus lalu dicoba lagi) tidak dobel. */
    public function test_entri_scan_dengan_id_sama_tidak_dihitung_dua_kali(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $payload = [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 2,
            'entries' => [
                ['id' => 'scan-a', 'at' => '2026-08-31T09:00:01+07:00', 'qty' => 1],
                ['id' => 'scan-b', 'at' => '2026-08-31T09:00:02+07:00', 'qty' => 1],
            ],
        ];

        $this->postJson('/api/audit-detail/hgp/scan-increment', $payload)->assertOk()->assertJsonPath('item.fisik', 2);
        // Dikirim ulang persis sama — server harus melewatinya.
        $this->postJson('/api/audit-detail/hgp/scan-increment', $payload)->assertOk()->assertJsonPath('item.fisik', 2);

        $item = PemeriksaanHgp::first()->items_json[0];
        $this->assertSame(2, $item['fisik']);
        $this->assertCount(2, $item['logScan']);
    }

    /** Kiriman ulang yang membawa 1 entri baru: hanya yang baru yang ditambahkan. */
    public function test_kiriman_ulang_hanya_menambahkan_entri_yang_belum_tercatat(): void
    {
        PemeriksaanHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 1,
            'entries' => [['id' => 'scan-a', 'qty' => 1]],
        ])->assertOk();

        $this->postJson('/api/audit-detail/hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 2,
            'entries' => [['id' => 'scan-a', 'qty' => 1], ['id' => 'scan-c', 'qty' => 1]],
        ])->assertOk()->assertJsonPath('item.fisik', 2);

        $this->assertCount(2, PemeriksaanHgp::first()->items_json[0]['logScan']);
    }

    public function test_rsa_hgp_ikut_aturan_yang_sama(): void
    {
        PemeriksaanRsaHgp::create(['plan_audit_id' => $this->plan->id, 'items_json' => $this->snapshotAwal()]);

        $this->postJson('/api/audit-detail/rsa-hgp/scan-increment', [
            'planAuditId' => $this->plan->id, 'noPart' => 'PART-1', 'qty' => 5,
        ])->assertOk();

        $this->postJson('/api/audit-detail/rsa-hgp', [
            'planAuditId' => $this->plan->id, 'items' => $this->snapshotAwal(),
        ])->assertStatus(409);

        $this->assertSame(5, PemeriksaanRsaHgp::first()->items_json[0]['fisik']);
    }
}
