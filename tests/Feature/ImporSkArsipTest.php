<?php

namespace Tests\Feature;

use App\Models\SuratKeputusan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

/**
 * SK tahun-tahun sebelumnya masih tersimpan di aplikasi AppSheet. Supaya
 * perpindahan ke aplikasi ini tidak memutus rujukan ke keputusan lama, arsip
 * itu dipindahkan sebagai BERKAS RIWAYAT: berstatus selesai, tidak masuk alur
 * persetujuan, tidak didistribusikan, dan tidak menagih pembebanan.
 */
class ImporSkArsipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'username' => 'admin']));
    }

    /** PDF sungguhan berisi teks, supaya pembacaan poin Memutuskan benar-benar diuji. */
    private function pdf(string $memutuskan): string
    {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 11);
        $pdf->MultiCell(0, 6, "SURAT KEPUTUSAN\nMenimbang:\nHasil pemeriksaan internal auditor.\nMemutuskan:\n" . $memutuskan . "\nDitetapkan di: Medan");

        return $pdf->Output('S');
    }

    private function zip(array $berkas): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-sk-') . '.zip';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($berkas as $nama => $isi) {
            $zip->addFromString($nama, $isi);
        }

        $zip->close();

        return new UploadedFile($path, 'SK 2025.zip', 'application/zip', null, true);
    }

    private function zipContoh(): UploadedFile
    {
        return $this->zip([
            'SK 2025/CDN.SK.2025.005.IAT CSC TDB.pdf' => $this->pdf('Stock AHM Oil yang berselisih disesuaikan dengan fisik.'),
            'SK 2025/CDN.SK.2025.008.IAT SO TPP.pdf'  => $this->pdf('Kerugian perlengkapan SMH dibebankan ke unit usaha.'),
            '__MACOSX/._CDN.SK.2025.005.IAT CSC TDB.pdf' => 'sampah',
            'SK 2025/catatan.txt' => 'bukan pdf',
        ]);
    }

    public function test_arsip_masuk_sebagai_berkas_riwayat_yang_sudah_selesai(): void
    {
        $this->post('/api/sk/impor-arsip', ['file' => $this->zipContoh()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ringkasan.disimpan', 2);

        $sk = SuratKeputusan::query()->where('no_sk', 'CDN.SK.2025.005.IAT')->first();

        $this->assertNotNull($sk, 'SK dari nama berkas tidak tersimpan.');
        $this->assertSame('CSC TDB', $sk->unit_usaha);
        $this->assertSame('selesai', $sk->status);
        $this->assertFalse($sk->perlu_pembebanan);
        $this->assertNull($sk->plan_audit_id);
        $this->assertStringContainsString('Stock AHM Oil yang berselisih', $sk->memutuskan);

        Storage::disk('public')->assertExists($sk->file_sk['path']);
    }

    public function test_hanya_berkas_pdf_yang_diambil(): void
    {
        $this->post('/api/sk/impor-arsip', ['file' => $this->zipContoh()], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(2, SuratKeputusan::query()->count());
    }

    public function test_pratinjau_tidak_menulis_apa_pun(): void
    {
        $this->post('/api/sk/impor-arsip', [
            'file'      => $this->zipContoh(),
            'pratinjau' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('pratinjau', true)
            ->assertJsonPath('ringkasan.baru', 2)
            ->assertJsonPath('baris.0.unit_usaha', 'CSC TDB');

        $this->assertSame(0, SuratKeputusan::query()->count());
    }

    public function test_impor_ulang_tidak_menggandakan_arsip(): void
    {
        $this->post('/api/sk/impor-arsip', ['file' => $this->zipContoh()], ['Accept' => 'application/json'])->assertOk();

        $this->post('/api/sk/impor-arsip', ['file' => $this->zipContoh()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ringkasan.disimpan', 0)
            ->assertJsonPath('ringkasan.dilewati', 2);

        $this->assertSame(2, SuratKeputusan::query()->count());
    }

    public function test_sk_berjalan_dengan_nomor_sama_tidak_tertimpa(): void
    {
        $berjalan = SuratKeputusan::query()->create([
            'no_sk'  => 'CDN-SK/2025.005/IAT',   // penulisan berbeda, nomor sama
            'status' => 'pending_manajer',
            'memutuskan' => 'Masih diproses manajer.',
        ]);

        $this->post('/api/sk/impor-arsip', ['file' => $this->zipContoh()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ringkasan.disimpan', 1);

        $berjalan->refresh();

        $this->assertSame('pending_manajer', $berjalan->status);
        $this->assertSame('Masih diproses manajer.', $berjalan->memutuskan);
    }

    public function test_ekspor_tabel_melengkapi_no_spt_dan_poin_memutuskan(): void
    {
        $csv = "IDSK,Keputusanid,No SK,Keputusan,Relation\n"
            . "79AB33C0,0052/08/08/2024/SPT-IAT,CDN-SK/2025.005/IAT,\"1. Stock disesuaikan dengan fisik.\",CSC TDB\n"
            . "B574E202,0052/08/08/2024/SPT-IAT,CDN-SK/2025.005/IAT,\"2. Kerugian dibebankan ke unit usaha.\",HRD Dept\n";

        $meta = UploadedFile::fake()->createWithContent('SK.csv', $csv);

        $this->post('/api/sk/impor-arsip', [
            'file' => $this->zipContoh(),
            'meta' => $meta,
        ], ['Accept' => 'application/json'])->assertOk();

        $sk = SuratKeputusan::query()->where('no_sk', 'CDN.SK.2025.005.IAT')->firstOrFail();

        $this->assertSame('0052/08/08/2024/SPT-IAT', $sk->no_spt);
        $this->assertStringContainsString('1. Stock disesuaikan dengan fisik.', $sk->memutuskan);
        $this->assertStringContainsString('2. Kerugian dibebankan ke unit usaha.', $sk->memutuskan);
        $this->assertSame(['CSC TDB', 'HRD Dept'], $sk->steps['migrasi']['relation']);
    }

    public function test_nama_unit_usaha_dari_master_dipakai_apa_adanya(): void
    {
        DB::table('db_unit_usaha')->insert([
            'unit_usaha' => 'WHS Part KIM', 'wilayah' => 'RAC', 'jenis' => 'WHS PART',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $zip = $this->zip([
            'CDN.SK.2025.020.IAT WHS Part KIM.pdf' => $this->pdf('Stock sparepart disesuaikan.'),
        ]);

        $this->post('/api/sk/impor-arsip', ['file' => $zip], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(
            'WHS Part KIM',
            SuratKeputusan::query()->where('no_sk', 'CDN.SK.2025.020.IAT')->value('unit_usaha')
        );
    }

    public function test_pdf_hasil_pindai_tetap_masuk_dengan_catatan(): void
    {
        $zip = $this->zip([
            'CDN.SK.2025.030.IAT CSC LGS.pdf' => "%PDF-1.4\n(tanpa lapisan teks)\n%%EOF",
        ]);

        $this->post('/api/sk/impor-arsip', ['file' => $zip], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ringkasan.disimpan', 1);

        $sk = SuratKeputusan::query()->where('no_sk', 'CDN.SK.2025.030.IAT')->firstOrFail();

        $this->assertNull($sk->memutuskan);
        $this->assertSame('CSC LGS', $sk->unit_usaha);
    }

    public function test_cabang_melihat_arsip_unit_usahanya_sendiri(): void
    {
        $this->post('/api/sk/impor-arsip', ['file' => $this->zipContoh()], ['Accept' => 'application/json'])->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'h2', 'unit_usaha' => 'CSC TDB']));

        $this->getJson('/api/sk')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.no_sk', 'CDN.SK.2025.005.IAT');
    }

    public function test_selain_admin_tidak_boleh_memindahkan_arsip(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->post('/api/sk/impor-arsip', ['file' => $this->zipContoh()], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertSame(0, SuratKeputusan::query()->count());
    }

    public function test_zip_tanpa_pdf_ditolak_dengan_pesan_yang_terbaca(): void
    {
        $zip = $this->zip(['catatan.txt' => 'tidak ada SK di sini']);

        $this->post('/api/sk/impor-arsip', ['file' => $zip], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tidak ada berkas PDF di dalam ZIP ini.');
    }
}
