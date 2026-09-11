<?php

namespace Tests\Feature;

use App\Models\DbUnitUsaha;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Import daftar stok ke RSA HGP & AHM Oils (Random Sampling Audit).
 *
 * Export "stock Pagi" dari sistem gudang sama sekali tidak punya baris header:
 * isinya langsung no part;nama;satuan;stok;mutasi;stok;harga;kode gudang. Parser
 * lama menuntut sel "AWAL"/"QTY"/"JUMLAH" untuk menemukan kolom stok, lalu jatuh
 * ke jalur cadangan yang memungut HANYA baris ber-nomor-part angka semua — dan
 * baris itu pun dibaca salah kolom sehingga stoknya 0. Gagal diam-diam: auditor
 * mendapat daftar yang kelihatan wajar padahal isinya keliru.
 *
 * Untuk aplikasi audit, salah kolom lebih berbahaya daripada gagal import.
 */
class RsaHgpImportStokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'auditor']));
    }

    private function buatPlan(array $override = []): PlanAudit
    {
        return PlanAudit::query()->create(array_merge([
            'no_spt' => '0001/TEST/SPT-IAT',
            'cabang' => 'WHS Part KIM',
            'cabang_area' => 'AREA TEST',
            'jenis_audit' => 'Audit Warehouse PART',
            'kepala_tim' => 'Abdul Aziz',
            'tim' => ['Abdul Aziz'],
            'status' => 'running',
        ], $override));
    }

    private function berkasStok(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/stok-tanpa-header.csv'),
            'stock Pagi.csv',
            'text/csv',
            null,
            true
        );
    }

    private function import(?PlanAudit $plan = null): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/audit-detail/rsa-hgp/parse-excel', [
            'file' => $this->berkasStok(),
            'planAuditId' => $plan?->id,
        ]);
    }

    public function test_daftar_stok_tanpa_header_terbaca_seluruhnya(): void
    {
        $hasil = $this->import($this->buatPlan())->assertOk();

        $this->assertSame(62, $hasil->json('totalFound'),
            'Seluruh 61 baris file harus terbaca, bukan hanya yang nomor partnya angka semua.');
    }

    public function test_stok_dibaca_dari_kolom_angka_yang_benar(): void
    {
        // Kolom 3 selalu berisi "1" (satuan) dan kolom 5 selalu "0" — keduanya
        // harus dilewati. Stok sesungguhnya ada di kolom 4.
        $hasil = $this->import($this->buatPlan())->assertOk();

        $this->assertSame(4, $hasil->json('kolomStok'));

        // Dibandingkan langsung dengan isi file, bukan dengan beberapa no part
        // pilihan: yang dikembalikan adalah sample acak, jadi item mana pun bisa
        // saja tidak ikut terambil.
        $stokDiFile = [];
        $namaDiFile = [];
        foreach (file(base_path('tests/Fixtures/stok-tanpa-header.csv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
            $kolom = explode(';', $baris);
            $stokDiFile[$kolom[0]] = (float) $kolom[3];
            $namaDiFile[$kolom[0]] = $kolom[1];
        }

        foreach ($hasil->json('data') as $item) {
            $this->assertSame($stokDiFile[$item['noPart']], (float) $item['saldoAkhir'],
                "Stok {$item['noPart']} tidak sama dengan kolom 4 di file.");
            $this->assertSame(
                $namaDiFile[$item['noPart']] !== '' ? $namaDiFile[$item['noPart']] : $item['noPart'],
                $item['sparepart'],
                'Nama part tidak boleh tergeser jadi nomor part.'
            );
        }
    }

    public function test_tidak_ada_item_yang_stoknya_nol_karena_salah_kolom(): void
    {
        $hasil = $this->import($this->buatPlan())->assertOk();

        $nol = collect($hasil->json('data'))->filter(fn ($it) => (float) $it['saldoAkhir'] === 0.0);

        $this->assertCount(0, $nol,
            'Stok 0 di seluruh baris adalah gejala kolom yang salah dibaca, bukan data gudang yang wajar.');
    }

    public function test_baris_yang_nama_partnya_kosong_tetap_ikut_terhitung(): void
    {
        // Di file gudang sungguhan ada baris seperti "FUEL TANK STAND K;;1;3;..."
        // — nomor partnya ada, namanya kosong, stoknya nyata. Membuangnya berarti
        // diam-diam mengurangi saldo yang harus dipertanggungjawabkan auditor.
        $hasil = $this->post('/api/audit-detail/rsa-hgp/parse-excel', [
            'file' => new UploadedFile(
                base_path('tests/Fixtures/stok-nama-kosong.csv'),
                'stock Pagi.csv',
                'text/csv',
                null,
                true
            ),
            'planAuditId' => $this->buatPlan()->id,
        ])->assertOk();

        $this->assertSame(4, $hasil->json('totalFound'));
        $this->assertFalse($hasil->json('sampled'), 'Di bawah ukuran sample, seluruh isi file dikembalikan.');

        $items = collect($hasil->json('data'))->keyBy('noPart');

        $this->assertSame(3.0, (float) $items['FUEL TANK STAND K']['saldoAkhir']);
        $this->assertSame(4.0, (float) $items['SS-312-SY']['saldoAkhir']);

        $this->assertSame('FUEL TANK STAND K', $items['FUEL TANK STAND K']['sparepart'],
            'Nama kosong diisi nomor partnya, bukan dibiarkan kosong di daftar scan.');

        $this->assertSame(355.0, collect($hasil->json('data'))->sum(fn ($it) => (float) $it['saldoAkhir']),
            'Total saldo harus sama dengan jumlah kolom stok di file, tanpa ada baris yang hilang.');
    }

    public function test_selisih_awal_adalah_minus_saldo_karena_belum_ada_yang_discan(): void
    {
        $hasil = $this->import($this->buatPlan())->assertOk();

        foreach ($hasil->json('data') as $item) {
            $this->assertSame(0.0, (float) $item['fisik']);
            $this->assertSame(-(float) $item['saldoAkhir'], (float) $item['selisih']);
        }
    }

    // ── Ukuran sample ────────────────────────────────────────────────────────

    public function test_gudang_part_mengambil_50_sample(): void
    {
        $hasil = $this->import($this->buatPlan())->assertOk();

        $this->assertSame(50, $hasil->json('sampleSize'));
        $this->assertCount(50, $hasil->json('data'));
        $this->assertTrue($hasil->json('sampled'));
    }

    public function test_nama_unit_usaha_sudah_cukup_walau_belum_ada_di_master_data(): void
    {
        // Sebelumnya kasus ini diam-diam jatuh ke 30: master data kosong, dan
        // jenis audit "Audit Warehouse PART" tidak mengandung kata "WHS".
        $this->assertSame(0, DbUnitUsaha::count());

        $hasil = $this->import($this->buatPlan(['cabang' => 'WHS Part AVIAN']))->assertOk();

        $this->assertSame(50, $hasil->json('sampleSize'));
    }

    public function test_master_data_lebih_didahulukan_daripada_nama(): void
    {
        DbUnitUsaha::create(['unit_usaha' => 'WHS Part KIM', 'wilayah' => 'RAC', 'jenis' => 'WHS PART']);

        $this->assertSame(50, $this->import($this->buatPlan())->assertOk()->json('sampleSize'));
    }

    public function test_cabang_biasa_tetap_30_sample(): void
    {
        $plan = $this->buatPlan([
            'cabang' => 'SO FLB',
            'jenis_audit' => 'Audit Full SO',
        ]);

        $hasil = $this->import($plan)->assertOk();

        $this->assertSame(30, $hasil->json('sampleSize'));
        $this->assertCount(30, $hasil->json('data'));
    }

    public function test_sample_mempertahankan_urutan_asli_file(): void
    {
        $urutanFile = collect(file(base_path('tests/Fixtures/stok-tanpa-header.csv'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(fn ($baris) => explode(';', $baris)[0])
            ->values()
            ->all();

        $sample = collect($this->import($this->buatPlan())->assertOk()->json('data'))
            ->pluck('noPart')
            ->all();

        // Sample harus berupa sub-urutan dari file, bukan sekadar berisi item yang
        // sama: auditor menelusuri daftarnya dari atas ke bawah mengikuti rak.
        $this->assertSame(
            array_values(array_intersect($urutanFile, $sample)),
            $sample,
            'Sample dikembalikan dalam urutan file supaya auditor mudah menelusuri saat scan.'
        );
    }
}
