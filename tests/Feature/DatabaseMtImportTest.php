<?php

namespace Tests\Feature;

use App\Models\DbMt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import Database MT sempat kehilangan kolom Harga sama sekali — bukan cuma
 * salah baca, tapi TERPOTONG sebelum sempat dibaca: pemotongan baris generik
 * (flatten multi-group) memangkas tiap baris ke $colCount tetap (5 kolom),
 * jadi kolom Harga di posisi 6 tidak pernah sampai ke tahap ekstraksi.
 *
 * Dua berkas nyata yang diunggah auditor untuk "berkas yang sama" (Database
 * MT) ternyata berbeda bentuk:
 *   No. | Nama Singkat | (kosong) | Nama Peralatan | Kode Peralatan | Gambar | Harga
 * — kolom "Gambar" ada di antara Kode Peralatan dan Harga, sehingga Harga
 * bergeser ke indeks 6, bukan 5 seperti yang sempat diasumsikan. Posisi
 * kolomnya sekarang dibaca dari ISI header (detectMtColumns), bukan indeks
 * tetap, supaya kolom tambahan apa pun di antaranya tidak lagi masalah.
 */
class DatabaseMtImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function saveTemp(Spreadsheet $sheet, string $name): UploadedFile
    {
        $path = sys_get_temp_dir() . '/' . uniqid('mt_import_') . '_' . $name;
        (new Xlsx($sheet))->save($path);
        return new UploadedFile($path, $name, null, null, true);
    }

    /** Bentuk berkas nyata yang diunggah auditor: kolom Gambar di antara Kode Peralatan & Harga. */
    private function buildFileDenganGambar(): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray(['No.', 'Nama Singkat', '', 'Nama Peralatan (IND)', 'Kode Peralatan', 'Gambar', 'Harga'], null, 'A1');
        $ws->fromArray(['', '', '', '', '', '', ''], null, 'A2'); // baris kosong, seperti berkas asli
        $ws->fromArray([1, 'UPPER TRAY', '', 'Alas Kunci dan Obeng', '076000060000', '', 313020], null, 'A3');
        $ws->fromArray([2, 'LOWER TRAY', '', 'Alas Tang dan Palu', '076000070000', '', 313020], null, 'A4');

        return $this->saveTemp($sheet, 'mt_dengan_gambar.xlsx');
    }

    /** Bentuk lain: Harga langsung setelah Kode Peralatan, tanpa kolom Gambar. */
    private function buildFileTanpaGambar(): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray(['No.', 'Nama Singkat', 'Nama Peralatan (IND)', 'Kode Peralatan', 'Harga'], null, 'A1');
        $ws->fromArray([1, 'SPANNER 8X9', 'Kunci Pas 8 x 9', '07600KLH1240', 179820], null, 'A2');

        return $this->saveTemp($sheet, 'mt_tanpa_gambar.xlsx');
    }

    /**
     * Bentuk berkas MT Lama nyata: ada baris kosong pemisah SEBELUM baris
     * header (dan satu lagi sesudahnya), serta dua kolom yang sama-sama
     * mengandung kata "harga" (kolom "HARGA" kosong lalu kolom "Harga" yang
     * benar-benar berisi angka) — kolom terakhir yang cocok yang harus dipakai.
     */
    private function buildFileMtLamaDenganBarisKosong(): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray(['', '', '', '', '', '', '', '', ''], null, 'A1');
        $ws->fromArray(['No.', 'Nama Singkat', '', 'Nama Peralatan (IND)', 'Kode Peralatan', 'Gambar', 'Jumlah Alat', 'HARGA', 'Harga'], null, 'A2');
        $ws->fromArray(['', '', '', '', '', '', '', '', ''], null, 'A3');
        $ws->fromArray([1, 'SPANNER 6X7', '', 'Kunci Pas 6 x 7', '07600-KLH-1210', '', 1, '', 179820], null, 'A4');
        $ws->fromArray([2, 'SPANNER 8X9', '', 'Kunci Pas 8 x 9', '07600-KLH-1240', '', 1, '', 179820], null, 'A5');

        return $this->saveTemp($sheet, 'mt_lama_baris_kosong.xlsx');
    }

    /** Bentuk lama sebelum Harga ada sama sekali: TANPA baris header. */
    private function buildFileLegacyTanpaHeader(): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray([1, 'KT10', '', 'Kunci T 10 mm', 'KODE-KT10'], null, 'A1');
        $ws->fromArray([2, 'KT12', '', 'Kunci T 12 mm', 'KODE-KT12'], null, 'A2');

        return $this->saveTemp($sheet, 'mt_legacy.xlsx');
    }

    public function test_import_membaca_harga_walau_ada_kolom_gambar_di_antaranya(): void
    {
        Storage::fake('local');

        $this->post('/api/database/mt/import', [
            'file' => $this->buildFileDenganGambar(),
            'mt_jenis' => 'MT Baru',
        ])->assertOk()->assertJsonPath('imported', 2);

        $upper = DbMt::where('kode_peralatan', '076000060000')->firstOrFail();
        $this->assertSame('UPPER TRAY', $upper->nama_singkat);
        $this->assertSame('Alas Kunci dan Obeng', $upper->nama_peralatan);
        $this->assertEquals(313020.0, $upper->harga);
        $this->assertSame('MT Baru', $upper->jenis);

        $lower = DbMt::where('kode_peralatan', '076000070000')->firstOrFail();
        $this->assertEquals(313020.0, $lower->harga);
    }

    public function test_import_tetap_benar_kalau_harga_tidak_didahului_kolom_gambar(): void
    {
        Storage::fake('local');

        $this->post('/api/database/mt/import', [
            'file' => $this->buildFileTanpaGambar(),
            'mt_jenis' => 'MT Baru',
        ])->assertOk()->assertJsonPath('imported', 1);

        $row = DbMt::where('kode_peralatan', '07600KLH1240')->firstOrFail();
        $this->assertSame('SPANNER 8X9', $row->nama_singkat);
        $this->assertEquals(179820.0, $row->harga);
    }

    public function test_import_mt_lama_dengan_baris_kosong_sebelum_header_tetap_dapat_harga(): void
    {
        Storage::fake('local');

        $this->post('/api/database/mt/import', [
            'file' => $this->buildFileMtLamaDenganBarisKosong(),
            'mt_jenis' => 'MT Lama',
        ])->assertOk()->assertJsonPath('imported', 2);

        $row = DbMt::where('kode_peralatan', '07600-KLH-1210')->firstOrFail();
        $this->assertSame('SPANNER 6X7', $row->nama_singkat);
        $this->assertSame('Kunci Pas 6 x 7', $row->nama_peralatan);
        $this->assertEquals(179820.0, $row->harga);
        $this->assertSame('MT Lama', $row->jenis);
    }

    public function test_import_berkas_lama_tanpa_header_dan_tanpa_harga_tetap_terbaca(): void
    {
        Storage::fake('local');

        $this->post('/api/database/mt/import', [
            'file' => $this->buildFileLegacyTanpaHeader(),
            'mt_jenis' => 'MT Lama',
        ])->assertOk()->assertJsonPath('imported', 2);

        $row = DbMt::where('kode_peralatan', 'KODE-KT10')->firstOrFail();
        $this->assertSame('KT10', $row->nama_singkat);
        $this->assertSame('Kunci T 10 mm', $row->nama_peralatan);
        $this->assertNull($row->harga);
        $this->assertSame('MT Lama', $row->jenis);
    }

    /** Import ulang (kode_peralatan+jenis sama) memperbarui harga, bukan menduplikasi baris. */
    public function test_import_ulang_kode_dan_jenis_sama_memperbarui_harga(): void
    {
        Storage::fake('local');

        $this->post('/api/database/mt/import', [
            'file' => $this->buildFileDenganGambar(),
            'mt_jenis' => 'MT Baru',
        ])->assertOk();

        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();
        $ws->fromArray(['No.', 'Nama Singkat', '', 'Nama Peralatan (IND)', 'Kode Peralatan', 'Gambar', 'Harga'], null, 'A1');
        $ws->fromArray([1, 'UPPER TRAY', '', 'Alas Kunci dan Obeng', '076000060000', '', 999000], null, 'A2');
        $ulang = $this->saveTemp($sheet, 'mt_update.xlsx');

        $this->post('/api/database/mt/import', [
            'file' => $ulang,
            'mt_jenis' => 'MT Baru',
        ])->assertOk();

        $this->assertSame(1, DbMt::where('kode_peralatan', '076000060000')->count());
        $this->assertEquals(999000.0, DbMt::where('kode_peralatan', '076000060000')->first()->harga);
    }
}
