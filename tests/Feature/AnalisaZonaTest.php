<?php

namespace Tests\Feature;

use App\Models\AnalisaAccReceivable;
use App\Models\AnalisaRkkTransaction;
use App\Models\AnalisaZonaScore;
use App\Models\User;
use App\Services\AnalisaZona\ZonaRiskScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

/**
 * Data RKK/ACC/LPK di test ini SINTETIS (bukan data nyata konsumen) tapi
 * meniru struktur baris asli — lihat catatan yang sama di AnalisaZonaParserTest.
 */
class AnalisaZonaTest extends TestCase
{
    use RefreshDatabase;

    private function buildSampleZip(): string
    {
        $rkk = implode("\r\n", [
            'hash-rkk-001',
            'SOSGL',
            '1;2;0072/SGL/VIII/2026;-;2026-08-18;SJA;SEULAWAH JAYA;ZFS;ZULFAHMI SARAGI;BIAYA TES;500000;20957;',
            '2;2;0072/SGL/VIII/2026;200.09;D;200.09-1;HUTANG DAGANG CSC;-;500000;-;0;0;0;20957;18643;CSC.SGL;',
        ]);

        $acc = implode("\r\n", [
            'SOSGL;2026-08-18;2026-08-18;',
            '0;SOSGL;BUDT010190;CASH;UMUM;BUDI TESTING;DUSUN TES;KEC TES;Kab. Tes;DS. TES;24183;081200000001;1;1990-01-01;-;081200000001;-;1107010101900001;JMH2E 0000000;BUDT010190;110905;Kec Tes;11090534;Ds Tes;2026-08-18;',
            '1;SOSGL;H00999-26;2026-08-18;0999/SGL/VIII/2026;;CJ000999;BUDT010190;BUDT010190;REG;18337838;2017162;25000000;2000000;0;0;0;0;0;0;0;0;2026-08-18;0.5;ABC;P;LANCAR;CASH;NA;NA;;-;0;',
            'F;SOSGL;2026-08-18;TESK010190;GAMPONG TES;081200000099;TESK010190;F00099-26;2026-06-04;MT1;3901351;0;0;0;;0000-00-00;0;3901351;0;0;0;0;3901351;2026-06-04;-;',
        ]);

        $lpk = implode("\r\n", [
            'hash-lpk-001',
            '0;SOTDB;2026-08-25;',
            '1;YK000001;280;BUDT010190;BUDI TESTING;IMFI;;0999/TDB/VIII/2026;H00151-26;0;3667000;;;0000-00-00;0;3667000;PBBO;1. Penjualan Unit Baru;P;;;',
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'analisa-zona-test-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('SOSGL-260818-260818RKK.RKK', $rkk);
        $zip->addFromString('SOSGL-20260818-20260818.ACC', $acc);
        $zip->addFromString('SOTDB-260825LPK.LPK', $lpk);
        $zip->close();

        return $zipPath;
    }

    private function actingAsAnalisaZonaUser(): User
    {
        $user = User::factory()->create(['role' => 'auditor', 'analisa_zona_access' => true]);
        Sanctum::actingAs($user);
        return $user;
    }

    public function test_user_biasa_tanpa_analisa_zona_access_bisa_upload_sendiri_untuk_unit_usahanya(): void
    {
        // "SO SGL" (field unit_usaha akun) harus cocok dengan "SOSGL" (kode di
        // dalam file) walau beda spasi — inti dari normalisasi kode unit usaha.
        $user = User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'SO SGL', 'analisa_zona_access' => false]);
        Sanctum::actingAs($user);

        $zipPath = $this->buildSampleZip();
        $upload = new UploadedFile($zipPath, 'RKK.zip', 'application/zip', null, true);

        $res = $this->postJson('/api/analisa-zona/upload-self', ['file' => $upload])->assertOk();
        $data = $res->json('data');

        $this->assertCount(2, $data['processed']); // RKK & ACC unitnya SOSGL
        $this->assertCount(1, $data['rejected_unit_usaha']); // LPK unitnya SOTDB, harus ditolak
        $this->assertStringContainsString('SOTDB', $data['rejected_unit_usaha'][0]);

        $this->assertDatabaseCount('analisa_rkk_transactions', 1);
        $this->assertDatabaseCount('analisa_lpk_penjualan', 0);
    }

    public function test_my_uploads_hanya_menampilkan_riwayat_unit_usaha_sendiri(): void
    {
        $userSosgl = User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'SO SGL', 'analisa_zona_access' => false]);
        Sanctum::actingAs($userSosgl);
        $this->postJson('/api/analisa-zona/upload-self', [
            'file' => new UploadedFile($this->buildSampleZip(), 'RKK.zip', 'application/zip', null, true),
        ])->assertOk();

        $res = $this->getJson('/api/analisa-zona/my-uploads')->assertOk();
        $filenames = collect($res->json('data'))->pluck('source_filename')->all();

        // Cuma file kode SOSGL yang masuk riwayat SO SGL (RKK & ACC), LPK
        // (kode SOTDB) tidak ikut karena ditolak validasi unit usaha.
        $this->assertCount(2, $filenames);
        $this->assertContains('SOSGL-260818-260818RKK.RKK', $filenames);
        $this->assertContains('SOSGL-20260818-20260818.ACC', $filenames);

        // User unit usaha lain tidak melihat riwayat SO SGL.
        $userLain = User::factory()->create(['role' => 'auditor', 'unit_usaha' => 'SO TDB', 'analisa_zona_access' => false]);
        Sanctum::actingAs($userLain);
        $res2 = $this->getJson('/api/analisa-zona/my-uploads')->assertOk();
        $this->assertCount(0, $res2->json('data'));
    }

    public function test_upload_self_ditolak_kalau_akun_belum_punya_unit_usaha(): void
    {
        $user = User::factory()->create(['role' => 'auditor', 'unit_usaha' => null, 'analisa_zona_access' => false]);
        Sanctum::actingAs($user);

        $upload = new UploadedFile($this->buildSampleZip(), 'RKK.zip', 'application/zip', null, true);
        $this->postJson('/api/analisa-zona/upload-self', ['file' => $upload])->assertStatus(422);
    }

    public function test_user_tanpa_akses_ditolak_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'analisa_zona_access' => false]));

        $this->getJson('/api/analisa-zona/scores')->assertStatus(403);
    }

    public function test_user_dengan_akses_boleh_lihat_scores(): void
    {
        $this->actingAsAnalisaZonaUser();

        $this->getJson('/api/analisa-zona/scores')->assertOk();
    }

    public function test_import_zip_menyimpan_baris_ke_3_jenis_tabel_dan_dedup_saat_upload_ulang(): void
    {
        $this->actingAsAnalisaZonaUser();
        $zipPath = $this->buildSampleZip();

        $upload = new UploadedFile($zipPath, 'RKK.zip', 'application/zip', null, true);
        $res = $this->postJson('/api/analisa-zona/import', ['file' => $upload])->assertOk();

        $data = $res->json('data');
        $this->assertCount(3, $data['processed']);
        $this->assertCount(0, $data['skipped_duplicate']);

        $this->assertDatabaseCount('analisa_rkk_transactions', 1);
        $this->assertDatabaseCount('analisa_acc_consumers', 1);
        $this->assertDatabaseCount('analisa_acc_contracts', 1);
        $this->assertDatabaseCount('analisa_acc_receivables', 1);
        $this->assertDatabaseCount('analisa_lpk_penjualan', 1);

        // Upload ulang file yang sama → harus dikenali sebagai duplikat, tidak dobel insert.
        $upload2 = new UploadedFile($this->buildSampleZip(), 'RKK.zip', 'application/zip', null, true);
        $res2 = $this->postJson('/api/analisa-zona/import', ['file' => $upload2])->assertOk();
        $data2 = $res2->json('data');
        $this->assertCount(0, $data2['processed']);
        $this->assertCount(3, $data2['skipped_duplicate']);

        $this->assertDatabaseCount('analisa_rkk_transactions', 1);
    }

    public function test_drill_down_menyamarkan_nik_dan_no_hp_konsumen(): void
    {
        $this->actingAsAnalisaZonaUser();
        $zipPath = $this->buildSampleZip();
        $upload = new UploadedFile($zipPath, 'RKK.zip', 'application/zip', null, true);
        $this->postJson('/api/analisa-zona/import', ['file' => $upload])->assertOk();

        $res = $this->getJson('/api/analisa-zona/drill-down?unit_usaha_code=SOSGL&jenis=acc-consumers')->assertOk();
        $items = $res->json('data');
        $this->assertNotEmpty($items);
        $this->assertStringNotContainsString('1107010101900001', $items[0]['nik']);
        $this->assertStringContainsString('1107', $items[0]['nik']); // 4 digit depan tetap terlihat
    }

    public function test_recompute_score_memberi_bobot_terbesar_pada_piutang(): void
    {
        AnalisaRkkTransaction::create([
            'upload_id' => \App\Models\AnalisaUpload::create([
                'jenis' => 'rkk', 'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-05',
                'source_hash' => 'h1', 'source_filename' => 'a.rkk', 'row_count' => 1,
            ])->id,
            'unit_usaha_code' => 'AAA', 'tanggal' => '2026-08-05', 'nominal' => 100000,
        ]);

        $uploadB = \App\Models\AnalisaUpload::create([
            'jenis' => 'acc', 'unit_usaha_code' => 'BBB', 'tanggal' => '2026-08-05',
            'source_hash' => 'h2', 'source_filename' => 'b.acc', 'row_count' => 1,
        ]);
        AnalisaAccReceivable::create([
            'upload_id' => $uploadB->id, 'unit_usaha_code' => 'BBB',
            'tanggal_laporan' => '2026-08-05', 'nominal' => 50000000, 'raw_line' => 'F;BBB;...',
        ]);

        $count = app(ZonaRiskScoreService::class)->recompute('2026-08');
        $this->assertSame(2, $count);

        $scoreAaa = AnalisaZonaScore::where('unit_usaha_code', 'AAA')->where('periode', '2026-08')->first();
        $scoreBbb = AnalisaZonaScore::where('unit_usaha_code', 'BBB')->where('periode', '2026-08')->first();

        // BBB (piutang besar) harus jauh lebih tinggi skor totalnya daripada
        // AAA (cuma kas kecil kecil, tanpa piutang) karena bobot piutang terbesar.
        $this->assertGreaterThan((float) $scoreAaa->skor_total, (float) $scoreBbb->skor_total);
        $this->assertSame(100.0, (float) $scoreBbb->skor_penjualan_piutang);
    }

    public function test_purge_command_hanya_hapus_data_lebih_tua_dari_retensi_dan_skor_tidak_ikut_terhapus(): void
    {
        $uploadLama = \App\Models\AnalisaUpload::create([
            'jenis' => 'rkk', 'unit_usaha_code' => 'AAA', 'tanggal' => now()->subDays(90)->toDateString(),
            'source_hash' => 'lama', 'source_filename' => 'lama.rkk', 'row_count' => 1,
        ]);
        AnalisaRkkTransaction::create([
            'upload_id' => $uploadLama->id, 'unit_usaha_code' => 'AAA',
            'tanggal' => now()->subDays(90)->toDateString(), 'nominal' => 1000,
        ]);

        $uploadBaru = \App\Models\AnalisaUpload::create([
            'jenis' => 'rkk', 'unit_usaha_code' => 'AAA', 'tanggal' => now()->subDays(5)->toDateString(),
            'source_hash' => 'baru', 'source_filename' => 'baru.rkk', 'row_count' => 1,
        ]);
        AnalisaRkkTransaction::create([
            'upload_id' => $uploadBaru->id, 'unit_usaha_code' => 'AAA',
            'tanggal' => now()->subDays(5)->toDateString(), 'nominal' => 2000,
        ]);

        AnalisaZonaScore::create([
            'unit_usaha_code' => 'AAA', 'periode' => now()->subMonths(3)->format('Y-m'), 'skor_total' => 42,
        ]);

        $this->artisan('analisa-zona:purge-old-data', ['--days' => 60])->assertSuccessful();

        $this->assertDatabaseCount('analisa_rkk_transactions', 1);
        $this->assertDatabaseHas('analisa_rkk_transactions', ['nominal' => 2000]);
        $this->assertDatabaseMissing('analisa_rkk_transactions', ['nominal' => 1000]);
        // Tabel skor ringkasan TIDAK ikut kena purge walau periodenya lama.
        $this->assertDatabaseCount('analisa_zona_scores', 1);
    }
}
