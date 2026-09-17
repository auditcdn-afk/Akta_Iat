<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Mengunggah file onhand SMH dua kali tidak boleh menggandakan daftar unitnya.
 *
 * Dulu bisa: langkahnya "hapus semua item lalu masukkan seluruh isi file", jadi
 * dua unggahan yang datang beriringan sama-sama menghapus isi lama yang belum
 * sempat ditulis siapa pun, lalu sama-sama memasukkan 193 baris — daftarnya
 * jadi 386 sementara Total Unit di header tetap 193. Plafon dan Report Audit
 * menghitung nilai stok dari baris-baris itu, jadi nilainya ikut dobel.
 */
class SmhOnhandUnggahUlangTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0424/28/07/2026/SPT-IAT',
            'cabang'      => 'CSC TBH',
            'jenis_audit' => 'Audit',
            'status'      => 'running',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id,
            'tool'          => 'smh',
            'nama_auditor'  => 'Auditor Uji',
            'nama_auditee'  => 'Auditee Uji',
        ]);
    }

    // ── Perilaku unggah ulang ────────────────────────────────────────────────

    public function test_unggah_file_yang_sama_dua_kali_tidak_menggandakan_daftar_unit(): void
    {
        $unit = $this->tigaUnit();

        $this->unggah($unit)->assertCreated();
        $this->unggah($unit)->assertCreated();

        $pmx = PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->firstOrFail();

        $this->assertSame(3, $pmx->items()->count(), 'File 3 unit yang diunggah dua kali harus tetap 3 baris, bukan 6.');
        $this->assertSame(3, $pmx->total_unit);
        $this->assertSame(1, PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->count());
    }

    public function test_hasil_pemeriksaan_fisik_tidak_hilang_saat_onhand_diunggah_ulang(): void
    {
        $unit = $this->tigaUnit();
        $this->unggah($unit)->assertCreated();

        $item = SmhOnhandItem::query()->where('no_mesin', 'KC04E 1073891')->firstOrFail();
        $this->putJson("/api/audit-detail/smh/items/{$item->id}", [
            'status_fisik'     => 'ada',
            'keterangan_fisik' => 'Unit ada di showroom',
        ])->assertOk();

        $this->unggah($unit)->assertCreated();

        $item->refresh();
        $this->assertSame('ada', $item->status_fisik, 'Unit yang sudah diperiksa tidak boleh direset oleh unggahan ulang.');
        $this->assertSame('Unit ada di showroom', $item->keterangan_fisik);
        $this->assertNotNull($item->checked_at);

        $pmx = $item->pemeriksaan;
        $this->assertSame(3, $pmx->total_unit);
        $this->assertSame(1, $pmx->total_ditemukan);
    }

    public function test_unit_yang_ditambahkan_manual_tetap_ada_setelah_unggah_ulang(): void
    {
        $unit = $this->tigaUnit();
        $this->unggah($unit)->assertCreated();

        $this->postJson('/api/audit-detail/smh/manual', [
            'plan_audit_id' => $this->plan->id,
            'no_mesin'      => 'KC04E9999999',
            'no_rangka'     => 'KC0419TK099999',
            'gudang'        => 'SO',
        ])->assertCreated();

        $this->unggah($unit)->assertCreated();

        $this->assertDatabaseHas('smh_onhand_items', ['no_mesin' => 'KC04E9999999']);
        $pmx = PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->firstOrFail();
        $this->assertSame(4, $pmx->total_unit, 'Unit temuan manual ikut dihitung di Total Unit.');
    }

    public function test_unit_yang_hilang_dari_file_dan_belum_diperiksa_ikut_dibuang(): void
    {
        $this->unggah($this->tigaUnit())->assertCreated();

        $lebihSedikit = array_slice($this->tigaUnit(), 0, 2);
        $this->unggah($lebihSedikit)->assertCreated();

        $pmx = PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->firstOrFail();
        $this->assertSame(2, $pmx->items()->count());
        $this->assertDatabaseMissing('smh_onhand_items', ['no_mesin' => 'KC04E 1073754']);
    }

    public function test_baris_kembar_di_dalam_satu_file_hanya_tersimpan_sekali(): void
    {
        $unit = $this->tigaUnit();
        $kembar = array_merge($unit, [$unit[0]]);

        $res = $this->unggah($kembar)->assertCreated();

        $pmx = PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->firstOrFail();
        $this->assertSame(3, $pmx->items()->count());
        $this->assertStringContainsString('1 baris kembar di file diabaikan', $res->json('message'));
    }

    public function test_nomor_dengan_dan_tanpa_spasi_dianggap_unit_yang_sama(): void
    {
        // Input manual menyimpan nomor tanpa spasi; file onhand memakai spasi.
        $this->postJson('/api/audit-detail/smh/manual', [
            'plan_audit_id' => $this->plan->id,
            'no_mesin'      => 'KC04E1073891',
            'no_rangka'     => 'KC0411TK073769',
        ])->assertCreated();

        $this->unggah($this->tigaUnit())->assertCreated();

        $pmx = PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->firstOrFail();
        $this->assertSame(3, $pmx->items()->count(), 'Unit yang sama tidak boleh tercatat dua kali hanya karena beda spasi.');
    }

    public function test_tambah_manual_unit_yang_sudah_ada_menandainya_ditemukan_bukan_menambah_baris(): void
    {
        $this->unggah($this->tigaUnit())->assertCreated();

        // Nomor yang sama, ditulis tanpa spasi — persis yang terjadi kalau
        // auditor mengetik ulang nomor yang tidak ketemu saat discan.
        $res = $this->postJson('/api/audit-detail/smh/manual', [
            'plan_audit_id' => $this->plan->id,
            'no_mesin'      => 'KC04E1073891',
            'no_rangka'     => 'KC0411TK073769',
        ])->assertCreated();

        $pmx = PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->firstOrFail();
        $this->assertSame(3, $pmx->items()->count(), 'Tidak boleh ada baris kedua untuk unit yang sama.');
        $this->assertSame(1, $pmx->total_ditemukan);
        $this->assertStringContainsString('sudah ada di daftar onhand', $res->json('message'));

        $item = SmhOnhandItem::query()->where('no_mesin', 'KC04E 1073891')->firstOrFail();
        $this->assertSame('ada', $item->status_fisik);
    }

    // ── Penjaga di sisi database ─────────────────────────────────────────────

    public function test_database_menolak_dua_baris_unit_yang_sama_persis(): void
    {
        $pmx = PemeriksaanSmh::query()->create(['plan_audit_id' => $this->plan->id]);
        SmhOnhandItem::create(['pemeriksaan_smh_id' => $pmx->id, 'no_mesin' => 'KC03E 1009289', 'no_rangka' => 'KC0316TK009244']);

        $this->expectException(QueryException::class);
        SmhOnhandItem::create(['pemeriksaan_smh_id' => $pmx->id, 'no_mesin' => 'KC03E 1009289', 'no_rangka' => 'KC0316TK009244']);
    }

    public function test_database_menolak_dua_daftar_onhand_untuk_satu_plan(): void
    {
        PemeriksaanSmh::query()->create(['plan_audit_id' => $this->plan->id]);

        $this->expectException(QueryException::class);
        PemeriksaanSmh::query()->create(['plan_audit_id' => $this->plan->id]);
    }

    // ── Pembersihan data yang terlanjur dobel ────────────────────────────────

    public function test_migrasi_merapikan_data_lama_yang_sudah_terlanjur_dobel(): void
    {
        Schema::table('smh_onhand_items', fn ($t) => $t->dropUnique('smh_onhand_items_unit_unik'));
        Schema::table('pemeriksaan_smh', fn ($t) => $t->dropUnique('pemeriksaan_smh_plan_unik'));

        // Dua header untuk satu plan, dan daftar unit yang tergandakan —
        // persis bentuk data yang ditinggalkan bug lamanya.
        $pmxA = PemeriksaanSmh::query()->create(['plan_audit_id' => $this->plan->id, 'total_unit' => 2]);
        $pmxB = PemeriksaanSmh::query()->create(['plan_audit_id' => $this->plan->id, 'total_unit' => 2]);

        foreach ([$pmxA, $pmxB] as $pmx) {
            foreach ([['KC03E 1009289', 'KC0316TK009244'], ['KC04E 1073891', 'KC0411TK073769']] as [$mesin, $rangka]) {
                SmhOnhandItem::create(['pemeriksaan_smh_id' => $pmx->id, 'no_mesin' => $mesin, 'no_rangka' => $rangka]);
            }
        }
        // Satu di antaranya sudah diperiksa: baris inilah yang harus bertahan.
        SmhOnhandItem::query()
            ->where('pemeriksaan_smh_id', $pmxB->id)
            ->where('no_mesin', 'KC03E 1009289')
            ->update(['status_fisik' => 'ada', 'keterangan_fisik' => 'Sudah dicek', 'checked_at' => now()]);

        $this->assertSame(4, SmhOnhandItem::query()->count());

        (require database_path('migrations/2026_09_17_000001_rapikan_duplikat_onhand_smh.php'))->up();

        $this->assertSame(1, PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->count());
        $this->assertSame(2, SmhOnhandItem::query()->count(), 'Empat baris untuk dua unit harus tinggal dua.');

        $bertahan = SmhOnhandItem::query()->where('no_mesin', 'KC03E 1009289')->firstOrFail();
        $this->assertSame('ada', $bertahan->status_fisik, 'Baris yang sudah diperiksa yang harus dipertahankan.');

        $pmx = PemeriksaanSmh::query()->where('plan_audit_id', $this->plan->id)->firstOrFail();
        $this->assertSame(2, $pmx->total_unit);
        $this->assertSame(1, $pmx->total_ditemukan);
    }

    // ── Bantuan ──────────────────────────────────────────────────────────────

    /** @return array<int,array{0:string,1:string}> */
    private function tigaUnit(): array
    {
        return [
            ['KC03E 1009289', 'KC0316TK009244'],
            ['KC04E 1073891', 'KC0411TK073769'],
            ['KC04E 1073754', 'KC0412TK073845'],
        ];
    }

    /** @param array<int,array{0:string,1:string}> $unit */
    private function unggah(array $unit): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/audit-detail/smh/upload', [
            'file'          => $this->fileOnhand($unit),
            'plan_audit_id' => $this->plan->id,
        ]);
    }

    /** Bentuk filenya menyalin file onhand asli: baris tanggal, header kode model, lalu barisnya. */
    private function fileOnhand(array $unit): UploadedFile
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setCellValue('A1', 'LAPORAN STOK ONHAND');
        $sheet->setCellValue('A2', '14 September 2026');
        $sheet->setCellValue('A3', 'Kode Model Intern :');
        $sheet->setCellValue('C3', 'KF0');
        $sheet->setCellValue('F3', 'BK');

        $baris = 4;
        foreach ($unit as $i => [$mesin, $rangka]) {
            $sheet->setCellValue('A' . $baris, $i + 1);
            $sheet->setCellValue('B' . $baris, $mesin);
            $sheet->setCellValue('C' . $baris, $rangka);
            $sheet->setCellValue('D' . $baris, '0034/SPB-SMH/I/26');
            $sheet->setCellValue('G' . $baris, 'OK');
            $sheet->setCellValue('H' . $baris, 30);
            $sheet->setCellValue('J' . $baris, 'KF0');
            $sheet->setCellValue('K' . $baris, 'BK');
            $sheet->setCellValue('L' . $baris, 'SO');
            $baris++;
        }

        $path = tempnam(sys_get_temp_dir(), 'onhand') . '.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, 'onhand.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
