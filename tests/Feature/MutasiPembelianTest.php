<?php

namespace Tests\Feature;

use App\Models\PemeriksaanMutasiPembelian;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Tool "Mutasi Pembelian": bandingkan file Gudang (patokan — laporan
 * pembelian dari sistem gudang, merged-cell seperti report Piutang Reguler)
 * terhadap file Unit Usaha (dipakai memverifikasi tiap baris Gudang lewat
 * Kode Part + Qty + Nomor Faktur). Baris Gudang yang kombinasinya ketemu di
 * file Unit Usaha ditandai "Sudah di terima dan di input" (bawa Lokasi dari
 * Unit Usaha); yang tidak ketemu ditandai "Belum Terima".
 */
class MutasiPembelianTest extends TestCase
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
    }

    private function buildGudangFile(): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        // Header merged-cell seperti report "LAPORAN PEMBELIAN (Psch)" asli:
        // label QTY di kolom index 9 (kolom J), data offset relatif terhadapnya.
        $ws->fromArray(
            ['TGL', '', 'NAMA SUPPLIER', '', 'NO.BUKTI', '', '', 'NAMA BARANG', '', 'QTY', 'HARGA BELI', 'DISCOUNT', 'NETTO', 'TGL.JTO'],
            null, 'A1'
        );
        // Baris 1: cocok di Unit Usaha (qty & no faktur sama persis).
        $ws->fromArray([46176, 'SUPPLIER A', '', 'INV-001', '', 'PART-AAA', 'NAMA PART AAA', '', '', 10, 1000, 0, 1000, 46180], null, 'A2');
        // Baris 2: TIDAK cocok (qty beda dari yang ada di Unit Usaha untuk faktur ini).
        $ws->fromArray([46176, 'SUPPLIER B', '', 'INV-002', '', 'PART-BBB', 'NAMA PART BBB', '', '', 5, 2000, 0, 2000, 46180], null, 'A3');
        // Baris 3: kode part sama sekali tidak ada di Unit Usaha.
        $ws->fromArray([46176, 'SUPPLIER C', '', 'INV-003', '', 'PART-CCC', 'NAMA PART CCC', '', '', 3, 3000, 0, 3000, 46180], null, 'A4');
        // Baris footer tanda tangan (harus DIABAIKAN — kolom TGL tidak numerik).
        $ws->fromArray(['Workshop Head', '', '', '', '', '', '', '', '', '', '', '', '', ''], null, 'A5');

        return $this->saveTemp($sheet, 'gudang.xlsx');
    }

    private function buildUnitUsahaFile(): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray(['Kode Part', 'Nama Part', 'Qty', 'Nomor Faktur', 'Tanggal Faktur', 'Lokasi', 'Kode', 'Unit Usaha'], null, 'A1');
        // Cocok dengan baris 1 Gudang (PART-AAA, qty 10, INV-001).
        $ws->fromArray(['PART-AAA', 'NAMA PART AAA', 10, 'INV-001', '2026-06-01', 'A1.01.1.1', 'MMDHH000', 'PT. TEST UNIT USAHA'], null, 'A2');
        // Qty beda (15 bukan 5) untuk INV-002 → tidak boleh cocok dengan baris 2 Gudang.
        $ws->fromArray(['PART-BBB', 'NAMA PART BBB', 15, 'INV-002', '2026-06-01', 'A1.01.1.2', 'MMDHH000', 'PT. TEST UNIT USAHA'], null, 'A3');

        return $this->saveTemp($sheet, 'unit_usaha.xlsx');
    }

    private function saveTemp(Spreadsheet $sheet, string $name): UploadedFile
    {
        $path = sys_get_temp_dir() . '/' . uniqid('mp_test_') . '_' . $name;
        (new Xlsx($sheet))->save($path);
        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_compare_mencocokkan_kode_part_qty_dan_nomor_faktur(): void
    {
        $res = $this->postJson('/api/audit-detail/mutasi-pembelian/compare', [
            'fileGudang'    => $this->buildGudangFile(),
            'fileUnitUsaha' => $this->buildUnitUsahaFile(),
        ])->assertOk();

        $data = $res->json('data');
        $this->assertCount(3, $data);

        $this->assertSame('PART-AAA', $data[0]['kodePart']);
        $this->assertTrue($data[0]['matched']);
        $this->assertSame('Sudah di terima dan di input', $data[0]['keterangan']);
        $this->assertSame('A1.01.1.1', $data[0]['lokasi']);
        $this->assertSame('MMDHH000', $data[0]['kode']);
        $this->assertSame('PT. TEST UNIT USAHA', $data[0]['unitUsaha']);

        // Qty tidak sama persis (5 vs 15 di Unit Usaha) → tidak boleh cocok.
        $this->assertSame('PART-BBB', $data[1]['kodePart']);
        $this->assertFalse($data[1]['matched']);
        $this->assertSame('Belum Terima', $data[1]['keterangan']);
        $this->assertSame('', $data[1]['lokasi']);
        // Kode & Unit Usaha tetap terisi (konstan per plan/file), walau baris ini "Belum Terima".
        $this->assertSame('MMDHH000', $data[1]['kode']);
        $this->assertSame('PT. TEST UNIT USAHA', $data[1]['unitUsaha']);

        // Kode part sama sekali tidak ada di Unit Usaha.
        $this->assertSame('PART-CCC', $data[2]['kodePart']);
        $this->assertFalse($data[2]['matched']);

        $this->assertSame(1, $res->json('totalMatch'));
    }

    public function test_save_ditolak_sebelum_nama_auditor_auditee_terisi(): void
    {
        $this->postJson('/api/audit-detail/mutasi-pembelian', [
            'planAuditId' => $this->plan->id,
            'items' => [['kodePart' => 'X', 'matched' => false, 'keterangan' => 'Belum Terima']],
        ])->assertStatus(422);
    }

    public function test_save_berhasil_setelah_auditor_terisi(): void
    {
        $this->postJson('/api/audit-detail/auditor', [
            'plan_audit_id' => $this->plan->id,
            'tool' => 'mutasi-pembelian', 'nama_auditee' => 'Auditee Test',
        ])->assertOk();

        $this->postJson('/api/audit-detail/mutasi-pembelian', [
            'planAuditId' => $this->plan->id,
            'items' => [
                ['kodePart' => 'PART-AAA', 'matched' => true, 'keterangan' => 'Sudah di terima dan di input'],
                ['kodePart' => 'PART-BBB', 'matched' => false, 'keterangan' => 'Belum Terima'],
            ],
        ])->assertOk();

        $rec = PemeriksaanMutasiPembelian::where('plan_audit_id', $this->plan->id)->first();
        $this->assertNotNull($rec);
        $this->assertCount(2, $rec->items_json);
    }

    public function test_update_keterangan_1_baris_tidak_menimpa_baris_lain(): void
    {
        PemeriksaanMutasiPembelian::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['kodePart' => 'PART-AAA', 'keterangan' => 'Sudah di terima dan di input'],
                ['kodePart' => 'PART-BBB', 'keterangan' => 'Belum Terima'],
            ],
        ]);

        // Simulasikan 2 auditor: satu edit baris 0, yang lain (snapshot lama)
        // tetap kirim PATCH untuk baris 1 — tidak boleh saling menimpa karena
        // masing-masing hanya mengubah 1 index di server, bukan kirim ulang
        // seluruh array dari memori browser.
        $this->patchJson('/api/audit-detail/mutasi-pembelian/keterangan', [
            'planAuditId' => $this->plan->id, 'index' => 0, 'keterangan' => 'Sudah diterima oleh Kabeng',
        ])->assertOk();
        $this->patchJson('/api/audit-detail/mutasi-pembelian/keterangan', [
            'planAuditId' => $this->plan->id, 'index' => 1, 'keterangan' => 'Belum Terima - dikonfirmasi ulang',
        ])->assertOk();

        $rec = PemeriksaanMutasiPembelian::where('plan_audit_id', $this->plan->id)->first();
        $this->assertSame('Sudah diterima oleh Kabeng', $rec->items_json[0]['keterangan']);
        $this->assertSame('Belum Terima - dikonfirmasi ulang', $rec->items_json[1]['keterangan']);
        // Kode Part tidak ikut hilang/berubah dari edit keterangan.
        $this->assertSame('PART-AAA', $rec->items_json[0]['kodePart']);
        $this->assertSame('PART-BBB', $rec->items_json[1]['kodePart']);
    }

    public function test_delete_item_hapus_1_baris_dan_merapatkan_indeks(): void
    {
        PemeriksaanMutasiPembelian::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['kodePart' => 'PART-AAA', 'keterangan' => 'Sudah di terima dan di input'],
                ['kodePart' => 'PART-BBB', 'keterangan' => 'Belum Terima'],
                ['kodePart' => 'PART-CCC', 'keterangan' => 'Belum Terima'],
            ],
        ]);

        $res = $this->deleteJson('/api/audit-detail/mutasi-pembelian/item', [
            'planAuditId' => $this->plan->id, 'index' => 1,
        ])->assertOk();

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('PART-AAA', $data[0]['kodePart']);
        // Baris ke-3 lama (index 2) bergeser jadi index 1 setelah baris tengah dihapus.
        $this->assertSame('PART-CCC', $data[1]['kodePart']);

        $rec = PemeriksaanMutasiPembelian::where('plan_audit_id', $this->plan->id)->first();
        $this->assertCount(2, $rec->items_json);
        $this->assertSame('PART-CCC', $rec->items_json[1]['kodePart']);
    }

    public function test_delete_item_index_tidak_ada_dikembalikan_404(): void
    {
        PemeriksaanMutasiPembelian::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [['kodePart' => 'PART-AAA', 'keterangan' => '']],
        ]);

        $this->deleteJson('/api/audit-detail/mutasi-pembelian/item', [
            'planAuditId' => $this->plan->id, 'index' => 5,
        ])->assertStatus(404);
    }
}
