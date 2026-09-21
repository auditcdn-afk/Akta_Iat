<?php

namespace Tests\Feature;

use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Unit usaha WHS memakai laporan stok gudang, bukan berkas onhand cabang.
 * Susunan kolomnya sama sekali berbeda:
 *
 *   NO | NO PART | NAMA PART | AWAL | MASUK | KELUAR | ADJ | MM1 | MK1 |
 *   MM2 | MK2 | Faktur Belum Kutip | Claim | AKHIR
 *
 * Aturan lama membaca kolom dari JARAK terhadap kolom AWAL (nomor part empat
 * kolom di kirinya). Pada berkas WHS, AWAL ada di kolom keempat -- empat kolom
 * ke kirinya jatuh di luar tabel, jadi importnya tidak menghasilkan apa pun.
 */
class HgpImportWhsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'auditor']));
    }

    private const JUDUL_WHS = [
        'NO', 'NO PART', 'NAMA PART', 'AWAL', 'MASUK', 'KELUAR', 'ADJ',
        'MM1', 'MK1', 'MM2', 'MK2', 'Faktur Belum Kutip', 'Claim', 'AKHIR',
    ];

    private function berkas(array $judul, array $baris): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws    = $sheet->getActiveSheet();

        // strictNullComparison = true: tanpa itu PhpSpreadsheet melewati sel
        // bernilai 0 (0 == null), sehingga kolom yang memang nol tidak pernah
        // sampai ke berkas ujinya.
        $ws->fromArray($judul, null, 'A1', true);

        foreach ($baris as $i => $row) {
            $ws->fromArray($row, null, 'A' . ($i + 2), true);
        }

        $path = tempnam(sys_get_temp_dir(), 'stok') . '.xlsx';
        (new Xlsx($sheet))->save($path);

        return new UploadedFile($path, 'laporan_stok_WHS.xlsx', null, null, true);
    }

    private function impor(UploadedFile $berkas, ?PlanAudit $plan = null)
    {
        return $this->post('/api/audit-detail/hgp/parse-excel', array_filter([
            'plan_audit_id' => $plan?->id,
            'file'          => $berkas,
        ]), ['Accept' => 'application/json']);
    }

    public function test_laporan_stok_whs_terbaca(): void
    {
        $berkas = $this->berkas(self::JUDUL_WHS, [
            [1, '60180K16A20', 'STAY, RECEIVER', 5, 2, 1, 0, 0, 0, 0, 0, 0, '', 6],
            [2, '44711KWW010', 'TIRE FR TT',     28, 0, 0, 0, 0, 0, 0, 0, 0, '', 28],
        ]);

        $res = $this->impor($berkas)->assertOk();

        $this->assertSame(2, $res->json('total'));
        $this->assertSame('60180K16A20', $res->json('data.0.noPart'));
        $this->assertSame('STAY, RECEIVER', $res->json('data.0.sparepart'));

        // Saldo pembanding fisik diambil dari AKHIR, bukan AWAL.
        $this->assertEquals(6, $res->json('data.0.saldoAkhir'));
        $this->assertEquals(28, $res->json('data.1.saldoAkhir'));
    }

    public function test_seluruh_kolom_laporan_stok_ikut_terbawa(): void
    {
        $berkas = $this->berkas(self::JUDUL_WHS, [
            [1, '44711KYE901', 'TIRE FR TL', 0, 10, 1, 3, 4, 2, 6, 7, 1, '', 7],
        ]);

        $stok = $this->impor($berkas)->assertOk()->json('data.0.stok');

        $this->assertEquals([
            'awal' => 0, 'masuk' => 10, 'keluar' => 1, 'adj' => 3,
            'mm1' => 4, 'mk1' => 2, 'mm2' => 6, 'mk2' => 7,
            'fakturBelumKutip' => 1,
        ], $stok);
    }

    public function test_kolom_yang_kosong_di_berkas_tidak_dijadikan_nol(): void
    {
        $berkas = $this->berkas(self::JUDUL_WHS, [
            [1, '44712KZT901', 'TUBE, TIRE', 38, 0, 0, 0, 0, 0, 0, 0, 0, '', 38],
        ]);

        // Kolom Claim memang kosong di laporan aslinya -- "belum diisi" tidak
        // sama dengan "nol", jadi tidak boleh dikarang menjadi 0.
        $this->assertArrayNotHasKey('claim', $this->impor($berkas)->assertOk()->json('data.0.stok'));
    }

    public function test_baris_tanpa_nomor_urut_tetap_ikut(): void
    {
        // Di laporan aslinya 74 baris terakhir tidak punya nomor urut. Aturan
        // lama membuang baris yang kolom pertamanya kosong.
        $berkas = $this->berkas(self::JUDUL_WHS, [
            [1,  '60180K16A20', 'STAY, RECEIVER', 5, 0, 0, 0, 0, 0, 0, 0, 0, '', 5],
            ['', '44712K84901', 'TUBE, TIRE',     2, 0, 0, 0, 0, 0, 0, 0, 0, '', 2],
        ]);

        $res = $this->impor($berkas)->assertOk();

        $this->assertSame(2, $res->json('total'));
        $this->assertSame('44712K84901', $res->json('data.1.noPart'));
    }

    public function test_baris_total_di_kaki_tabel_dilewati(): void
    {
        $berkas = $this->berkas(self::JUDUL_WHS, [
            [1, '60180K16A20', 'STAY, RECEIVER', 5, 0, 0, 0, 0, 0, 0, 0, 0, '', 5],
            ['', 'TOTAL', '', 5, 0, 0, 0, 0, 0, 0, 0, 0, '', 5],
        ]);

        $res = $this->impor($berkas)->assertOk();

        $this->assertSame(1, $res->json('total'));
    }

    public function test_berkas_onhand_cabang_tetap_terbaca_seperti_semula(): void
    {
        // Judulnya memakai kolom gabungan: posisi judul bergeser dari posisi
        // datanya, jadi kolomnya TIDAK boleh dibaca dari nama judulnya.
        $judul = ['NO', '', 'NO PART', '', 'NAMA PART', 'AWAL', 'MASUK', 'KELUAR', 'ADJUST', 'AKHIR', 'KETERANGAN'];
        $baris = [
            [1, 'PART-1', 'Sparepart Satu', '', '', 10, 0, 0, 0, 7, 'Gudang A'],
            [2, 'PART-2', 'Sparepart Dua',  '', '', 4,  0, 0, 0, 4, 'Gudang B'],
        ];

        $res = $this->impor($this->berkas($judul, $baris))->assertOk();

        $this->assertSame(2, $res->json('total'));
        $this->assertSame('PART-1', $res->json('data.0.noPart'));
        $this->assertSame('Sparepart Satu', $res->json('data.0.sparepart'));
        $this->assertEquals(7, $res->json('data.0.saldoAkhir'));
        $this->assertSame('Gudang A', $res->json('data.0.keterangan'));

        // Berkas cabang tidak punya kolom laporan stok, jadi tabelnya tidak
        // perlu menampilkan kolom-kolom itu.
        $this->assertNull($res->json('data.0.stok'));
    }

    public function test_sampling_tetap_berlaku_untuk_laporan_stok_whs(): void
    {
        $plan = PlanAudit::query()->create([
            'no_spt' => '0002/01/01/2026/SPT-IAT', 'cabang' => 'WHS Part KIM',
            'jenis_audit' => 'Audit Online Kas + HGP & AHM Oils', 'status' => 'running',
        ]);

        $baris = [];
        for ($i = 1; $i <= 80; $i++) {
            $baris[] = [$i, "PART-{$i}", "Sparepart {$i}", 1, 0, 0, 0, 0, 0, 0, 0, 0, '', 1];
        }

        $res = $this->impor($this->berkas(self::JUDUL_WHS, $baris), $plan)->assertOk();

        $this->assertCount(30, $res->json('data'));
        $this->assertSame(80, $res->json('totalFound'));
        $this->assertTrue($res->json('sampled'));
    }

    public function test_kolom_laporan_stok_bertahan_setelah_disimpan(): void
    {
        $plan = PlanAudit::query()->create([
            'no_spt' => '0003/01/01/2026/SPT-IAT', 'cabang' => 'WHS Part KIM',
            'jenis_audit' => 'Audit Warehouse PART', 'status' => 'running',
        ]);

        \App\Models\PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $plan->id, 'tool' => 'hgp',
            'nama_auditor' => 'Auditor Satu', 'nama_auditee' => 'Kepala Gudang',
        ]);

        $items = $this->impor($this->berkas(self::JUDUL_WHS, [
            [1, '60180K16A20', 'STAY, RECEIVER', 5, 2, 1, 0, 0, 0, 0, 0, 0, '', 6],
        ]))->assertOk()->json('data');

        $this->postJson('/api/audit-detail/hgp', [
            'plan_audit_id' => $plan->id,
            'mode'          => 'import',
            'items'         => $items,
        ])->assertOk();

        $tersimpan = $this->getJson('/api/audit-detail/hgp?plan_audit_id=' . $plan->id)
            ->assertOk()
            ->json('data.items.0.stok');

        $this->assertEquals(2, $tersimpan['masuk']);
        $this->assertEquals(1, $tersimpan['keluar']);
    }
}
