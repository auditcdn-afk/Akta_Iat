<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanHgp;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * Kolom yang menambah hitungan fisik (judul bawaannya "WO", di lapangan sering
 * disebut Titipan) diisi untuk banyak No. Part sekaligus dari berkas Excel.
 *
 * Sebelumnya angkanya diketik satu per satu: pada audit gudang daftar onhand-nya
 * ribuan item sementara titipannya ratusan — mencari tiap No. Part di tabel lalu
 * mengetik angkanya memakan waktu berjam-jam.
 */
class HgpImporWoTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['username' => 'auditor1', 'role' => 'auditor']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0500/01/01/2026/SPT-IAT', 'cabang' => 'WHS Avian',
            'jenis_audit' => 'Audit Warehouse PART', 'status' => 'running',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'hgp',
            'nama_auditor' => 'Auditor Satu', 'nama_auditee' => 'Kepala Gudang',
        ]);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function isiDaftar(array $items): PemeriksaanHgp
    {
        return PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => $items,
        ]);
    }

    private function item(string $noPart, float $saldo = 10, float $fisik = 0, float $wo = 0): array
    {
        return [
            'noPart' => $noPart, 'sparepart' => 'Part ' . $noPart,
            'saldoAkhir' => $saldo, 'fisik' => $fisik, 'wo' => $wo,
            'akhir' => $saldo - ($fisik + $wo), 'selisih' => ($fisik + $wo) - $saldo,
            'keterangan' => '', 'tgl' => '2026-09-21', 'logScan' => [],
        ];
    }

    /**
     * Berkas titipan seperti yang dikirim cabang: dua kolom, No Part & QTY.
     *
     * @param array<int,array{0:string,1:mixed}> $baris
     */
    private function berkas(array $baris, array $judul = ['No Part', 'QTY']): UploadedFile
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([$judul], null, 'A1', true);
        $sheet->fromArray($baris, null, 'A2', true);

        $path = tempnam(sys_get_temp_dir(), 'titipan') . '.xlsx';
        (new XlsxWriter($ss))->save($path);

        return new UploadedFile($path, 'Titipan.xlsx', null, null, true);
    }

    private function impor(UploadedFile $file, array $extra = [])
    {
        return $this->post('/api/audit-detail/hgp/impor-wo', array_merge([
            'plan_audit_id' => $this->plan->id,
            'file'          => $file,
        ], $extra));
    }

    public function test_qty_berkas_mengisi_kolom_wo_dan_menambah_fisik(): void
    {
        $rec = $this->isiDaftar([
            $this->item('61304K0JA00', saldo: 10, fisik: 2),
            $this->item('64300K2FP00', saldo: 400),
        ]);

        $this->impor($this->berkas([
            ['61304K0JA00', 5],
            ['64300K2FP00', 316],
        ]))->assertOk()->assertJson(['cocok' => 2, 'tidakCocok' => 0]);

        $items = $rec->fresh()->items_json;

        $this->assertEquals(5, $items[0]['wo']);
        // WO ikut menambah fisik: 2 fisik + 5 WO = 7 dari saldo 10.
        $this->assertEquals(3, $items[0]['akhir']);
        $this->assertEquals(-3, $items[0]['selisih']);

        $this->assertEquals(316, $items[1]['wo']);
        $this->assertEquals(84, $items[1]['akhir']);
    }

    public function test_hasil_scan_fisik_tidak_disentuh(): void
    {
        $awal = $this->item('61304K0JA00', saldo: 10, fisik: 4);
        $awal['logScan']    = [['at' => '2026-09-21T08:00:00+07:00', 'qty' => 4]];
        $awal['keterangan'] = 'rak B3';

        $rec = $this->isiDaftar([$awal]);

        $this->impor($this->berkas([['61304K0JA00', 3]]))->assertOk();

        $it = $rec->fresh()->items_json[0];

        $this->assertEquals(4, $it['fisik'], 'fisik hasil scan tidak boleh berubah');
        $this->assertCount(1, $it['logScan'], 'riwayat scan tidak boleh hilang');
        $this->assertSame('rak B3', $it['keterangan']);
        $this->assertEquals(3, $it['wo']);
    }

    public function test_angka_lama_diganti_bukan_ditambah(): void
    {
        $rec = $this->isiDaftar([$this->item('61304K0JA00', saldo: 10, wo: 9)]);

        // Berkas yang sudah dikoreksi diimport ulang: yang berlaku angka di
        // berkas, bukan 9 + 5.
        $this->impor($this->berkas([['61304K0JA00', 5]]))->assertOk();

        $this->assertEquals(5, $rec->fresh()->items_json[0]['wo']);
    }

    public function test_no_part_yang_sama_berkali_kali_dijumlahkan(): void
    {
        // Daftar titipan sering ditulis per surat jalan, belum direkap per part.
        $rec = $this->isiDaftar([$this->item('61304K0JA00', saldo: 50)]);

        $this->impor($this->berkas([
            ['61304K0JA00', 5],
            ['61304K0JA00', 4],
            ['61304K0JA00', 1],
        ]))->assertOk()->assertJson(['barisBerkas' => 3, 'noPartUnik' => 1, 'cocok' => 1]);

        $this->assertEquals(10, $rec->fresh()->items_json[0]['wo']);
    }

    public function test_no_part_berkas_yang_tidak_ada_di_daftar_dilaporkan_bukan_dibuat(): void
    {
        $rec = $this->isiDaftar([$this->item('61304K0JA00')]);

        $res = $this->impor($this->berkas([
            ['61304K0JA00', 5],
            ['SALAH0001', 3],
        ]))->assertOk();

        $res->assertJson(['cocok' => 1, 'tidakCocok' => 1]);
        $this->assertSame(['SALAH0001'], $res->json('contohTidakCocok'));

        // Part asing tidak boleh ikut masuk jadi item pemeriksaan.
        $this->assertCount(1, $rec->fresh()->items_json);
    }

    public function test_beda_besar_kecil_huruf_dan_spasi_tetap_cocok(): void
    {
        $rec = $this->isiDaftar([$this->item('61304K0JA00')]);

        $this->impor($this->berkas([[' 61304k0ja00 ', 7]]))
            ->assertOk()->assertJson(['cocok' => 1]);

        $this->assertEquals(7, $rec->fresh()->items_json[0]['wo']);
    }

    public function test_pratinjau_menghitung_dampak_tanpa_menulis_apa_pun(): void
    {
        $rec = $this->isiDaftar([
            $this->item('61304K0JA00'),
            $this->item('64300K2FP00'),
        ]);

        $res = $this->impor($this->berkas([
            ['61304K0JA00', 5],
            ['TIDAKADA', 2],
        ]), ['pratinjau' => 1])->assertOk();

        $res->assertJson([
            'pratinjau'  => true,
            'noPartUnik' => 2,
            'cocok'      => 1,
            'tidakCocok' => 1,
            'totalQty'   => 5,
        ]);

        foreach ($rec->fresh()->items_json as $it) {
            $this->assertEquals(0, $it['wo'], 'pratinjau tidak boleh menulis apa pun');
        }
    }

    public function test_item_diluar_berkas_dibiarkan_kecuali_diminta_dikosongkan(): void
    {
        $rec = $this->isiDaftar([
            $this->item('61304K0JA00', wo: 0),
            $this->item('64300K2FP00', wo: 8),
        ]);

        // Bawaannya: angka yang sudah diketik auditor lain tidak dihapus.
        $this->impor($this->berkas([['61304K0JA00', 5]]))
            ->assertOk()->assertJson(['dikosongkan' => 0]);
        $this->assertEquals(8, $rec->fresh()->items_json[1]['wo']);

        // Diminta: berkasnya daftar lengkap, sisanya memang tidak ada titipan.
        $this->impor($this->berkas([['61304K0JA00', 5]]), ['kosongkanSisanya' => 1])
            ->assertOk()->assertJson(['dikosongkan' => 1]);

        $items = $rec->fresh()->items_json;
        $this->assertEquals(0, $items[1]['wo']);
        $this->assertEquals(10, $items[1]['akhir'], 'akhir ikut dihitung ulang');
        $this->assertEquals(5, $items[0]['wo'], 'yang cocok tetap terisi');
    }

    public function test_hanya_item_yang_berubah_yang_dikirim_balik(): void
    {
        // Daftar onhand gudang ribuan item; yang dikirim balik ke layar tidak
        // boleh seluruh daftar itu.
        $daftar = [];
        for ($i = 0; $i < 200; $i++) {
            $daftar[] = $this->item(sprintf('PART%05d', $i));
        }
        $this->isiDaftar($daftar);

        $res = $this->impor($this->berkas([
            ['PART00007', 3],
            ['PART00099', 4],
        ]))->assertOk();

        $this->assertCount(2, $res->json('perubahan'));
        $this->assertSame('PART00007', $res->json('perubahan.0.noPart'));
        $this->assertEquals(3, $res->json('perubahan.0.wo'));
    }

    public function test_judul_kolom_berkas_boleh_bukan_kolom_pertama(): void
    {
        $rec = $this->isiDaftar([$this->item('61304K0JA00')]);

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray([['No', 'Nama Part', 'No Part', 'QTY Titipan']], null, 'A1', true);
        $sheet->fromArray([[1, 'STAY RECEIVER', '61304K0JA00', 6]], null, 'A2', true);
        $path = tempnam(sys_get_temp_dir(), 'titipan') . '.xlsx';
        (new XlsxWriter($ss))->save($path);

        $this->impor(new UploadedFile($path, 'Titipan.xlsx', null, null, true))
            ->assertOk()->assertJson(['cocok' => 1]);

        $this->assertEquals(6, $rec->fresh()->items_json[0]['wo']);
    }

    public function test_ditolak_kalau_daftar_pemeriksaan_belum_ada(): void
    {
        $this->impor($this->berkas([['61304K0JA00', 5]]))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Daftar item belum ada. Import data HGP & AHM Oils dulu, baru isi kolom ini.']);
    }

    /**
     * Pertanyaan dari lapangan: setelah angkanya masuk lewat impor, apakah
     * rumus selisihnya berjalan sama seperti waktu diketik satu per satu?
     *
     * Dibuktikan dengan membandingkan dua jalur itu pada data yang sama:
     * hasilnya harus identik, bukan sekadar mirip.
     */
    public function test_hasil_impor_identik_dengan_angka_yang_diketik_manual(): void
    {
        $contoh = [
            // [saldoAkhir, fisik hasil scan, qty dari berkas]
            [13, 0, 10],    // belum discan            -> selisih -3
            [10, 2, 5],     // sudah discan sebagian   -> selisih -3
            [5,  0, 5],     // pas                     -> selisih  0
            [4,  1, 6],     // lebih                   -> selisih +3
            [0,  0, 7],     // tidak ada di sistem     -> selisih +7
            [8,  0, 0],     // titipan nol             -> selisih -8
        ];

        foreach ($contoh as [$saldo, $fisik, $qty]) {
            $noPart = "PART{$saldo}X{$fisik}X{$qty}";

            // Jalur A: diketik manual di tabel (endpoint yang sudah dipakai
            // sejak kolom WO ada).
            $manual = $this->isiDaftar([$this->item($noPart, saldo: $saldo, fisik: $fisik)]);
            $this->postJson('/api/audit-detail/hgp/scan-increment', [
                'plan_audit_id' => $this->plan->id,
                'noPart'        => $noPart,
                'qty'           => 0,
                'wo'            => $qty,
            ])->assertOk();
            $hasilManual = $manual->fresh()->items_json[0];
            $manual->delete();

            // Jalur B: masuk lewat impor berkas.
            $impor = $this->isiDaftar([$this->item($noPart, saldo: $saldo, fisik: $fisik)]);
            $this->impor($this->berkas([[$noPart, $qty]]))->assertOk();
            $hasilImpor = $impor->fresh()->items_json[0];
            $impor->delete();

            $ringkas = fn (array $it) => [
                'wo'      => (float) $it['wo'],
                'akhir'   => (float) $it['akhir'],
                'selisih' => (float) $it['selisih'],
            ];

            $this->assertSame(
                $ringkas($hasilManual),
                $ringkas($hasilImpor),
                "saldo {$saldo}, fisik {$fisik}, titipan {$qty}: impor beda dengan ketik manual"
            );

            // Sekalian pastikan rumusnya memang yang dimaksud, bukan dua jalur
            // yang sama-sama salah.
            $this->assertEquals($saldo - ($fisik + $qty), $hasilImpor['akhir']);
            $this->assertEquals(($fisik + $qty) - $saldo, $hasilImpor['selisih']);
        }
    }

    public function test_selisih_ikut_dihitung_ulang_saat_titipan_dikosongkan(): void
    {
        // Angka yang salah dikoreksi jadi 0: selisihnya harus kembali seperti
        // sebelum titipan diisi, bukan tertinggal di angka lama.
        $rec = $this->isiDaftar([$this->item('61304K0JA00', saldo: 13, fisik: 0, wo: 10)]);

        $this->impor($this->berkas([['61304K0JA00', 0]]))->assertOk();

        $it = $rec->fresh()->items_json[0];
        $this->assertEquals(0, $it['wo']);
        $this->assertEquals(13, $it['akhir']);
        $this->assertEquals(-13, $it['selisih']);
    }

    public function test_data_lama_yang_cuma_punya_saldo_awal_ikut_terhitung(): void
    {
        // Item dari versi aplikasi lama menyimpan saldoAwal, bukan saldoAkhir.
        $rec = $this->isiDaftar([[
            'noPart' => '61304K0JA00', 'sparepart' => 'STAY RECEIVER',
            'saldoAwal' => 13, 'fisik' => 0, 'wo' => 0,
            'keterangan' => '', 'tgl' => '2026-09-21', 'logScan' => [],
        ]]);

        $this->impor($this->berkas([['61304K0JA00', 10]]))->assertOk();

        $it = $rec->fresh()->items_json[0];
        $this->assertEquals(3, $it['akhir'], 'saldoAwal harus ikut dipakai, sama seperti di layar');
        $this->assertEquals(-3, $it['selisih']);
    }

    public function test_titipan_hasil_impor_masuk_ke_export_selisih(): void
    {
        $rec = $this->isiDaftar([
            $this->item('61304K0JA00', saldo: 13),   // jadi selisih -3 setelah impor
            $this->item('64300K2FP00', saldo: 5),    // jadi selisih 0  -> tidak ikut
        ]);
        $rec->update(['label_wo' => 'Titipan']);

        $this->impor($this->berkas([
            ['61304K0JA00', 10],
            ['64300K2FP00', 5],
        ]))->assertOk();

        $res = $this->get('/api/audit-detail/hgp/export-selisih?plan_audit_id=' . $this->plan->id);
        $res->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'selisih') . '.xlsx';
        file_put_contents($path, $res->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getSheetByName('SPAREPART');
        $isi   = $sheet->toArray();

        $rata = [];
        foreach ($isi as $baris) {
            foreach ($baris as $sel) {
                if ($sel !== null && $sel !== '') $rata[] = (string) $sel;
            }
        }

        $this->assertContains('Titipan', $rata, 'judul kolom ikut ke berkas export');
        $this->assertContains('61304K0JA00', $rata, 'item yang selisih ikut terbawa');
        $this->assertNotContains('64300K2FP00', $rata, 'item yang selisihnya nol tidak ikut');
    }

    public function test_simpan_penuh_dari_layar_ketinggalan_tidak_menghapus_hasil_impor(): void
    {
        // Auditor lain masih memegang tabel versi SEBELUM import ini. Kalau
        // layarnya menekan Simpan, angka yang baru masuk tidak boleh lenyap.
        $rec = $this->isiDaftar([$this->item('61304K0JA00', saldo: 10)]);

        $this->impor($this->berkas([['61304K0JA00', 5]]))->assertOk();

        $snapshotLama = [$this->item('61304K0JA00', saldo: 10)];   // wo masih 0

        $this->postJson('/api/audit-detail/hgp', [
            'plan_audit_id' => $this->plan->id,
            'items'         => $snapshotLama,
        ])->assertStatus(409);

        $this->assertEquals(5, $rec->fresh()->items_json[0]['wo']);
    }

    public function test_judul_kolom_yang_dipakai_plan_ikut_dijawab(): void
    {
        $rec = $this->isiDaftar([$this->item('61304K0JA00')]);
        $rec->update(['label_wo' => 'Titipan']);

        $this->impor($this->berkas([['61304K0JA00', 5]]))
            ->assertOk()
            ->assertJson(['labelWo' => 'Titipan'])
            ->assertJsonFragment(['message' => 'Kolom "Titipan" terisi untuk 1 item.']);
    }
}
