<?php

namespace Tests\Feature;

use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Total Selisih di "C. REKAP GABUNGAN PERLENGKAPAN PER JENIS" harus menjumlahkan
 * SELURUH fisik yang tertanggung — yang ditemukan menempel di unit saat Cek Fisik
 * SMH, DITAMBAH yang ditemukan terpisah di gudang (Perlengkapan di luar SMH) —
 * lalu dibandingkan dengan jumlah unit yang membutuhkannya.
 *
 * Sebelumnya angka Total mengikuti kolom `saldo` yang tersimpan di baris
 * pemeriksaan_perlengkapan. Kolom itu cuma potret saat baris disimpan dan menjadi
 * basi begitu checklist Cek Fisik unit berubah sesudahnya, sehingga perlengkapan
 * yang sebenarnya sudah lengkap tetap terbaca kurang.
 */
class PerlengkapanTotalSelisihTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;
    private PemeriksaanSmh $smh;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'username' => 'auditor1', 'role' => 'admin', 'unit_usaha' => 'SBG MTR',
        ]));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0132/08/07/2026/SPT-IAT', 'cabang' => 'SBG MTR',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        DbUnitUsaha::query()->create(['unit_usaha' => 'SBG MTR', 'wilayah' => 'RIAU', 'jenis' => 'H1']);
        DbPerlengkapan::query()->create([
            'kode' => 'JBK1E', 'wilayah' => 'RIAU', 'nama' => 'BeAT',
            'keterangan' => 'Baterai 3 Ah',
        ]);

        $this->smh = PemeriksaanSmh::query()->create([
            'plan_audit_id' => $this->plan->id, 'no_spt' => $this->plan->no_spt, 'cabang' => 'SBG MTR',
        ]);
    }

    /** Buat $jumlah unit onhand; $adaPerlengkapan unit pertama tercatat punya perlengkapannya. */
    private function unitOnhand(int $jumlah, int $adaPerlengkapan, string $jenis): void
    {
        for ($i = 1; $i <= $jumlah; $i++) {
            SmhOnhandItem::query()->create([
                'pemeriksaan_smh_id' => $this->smh->id,
                'no_mesin'           => sprintf('JBK1E%07d', $i),
                'no_rangka'          => sprintf('MH1JBK1E%07d', $i),
                'status_fisik'       => 'ada',
                'perlengkapan_json'  => [['nama' => $jenis, 'ada' => $i <= $adaPerlengkapan]],
            ]);
        }
    }

    /**
     * Baca satu baris tabel "C. REKAP GABUNGAN" dari HTML yang BENAR-BENAR
     * dirender, bukan dari data mentahnya — supaya yang diuji adalah angka yang
     * dilihat auditor di laporan, termasuk aritmetika di dalam blade-nya.
     *
     * @return array{smhSaldo:int, smhFisik:int, smhSel:int, luarSaldo:int, luarFisik:int, luarSel:int, totalSel:int}
     */
    private function barisRekapGabungan(string $jenis): array
    {
        $html = $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->getContent();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        // Baris yang sel keduanya berisi nama jenis, di dalam tabel yang punya
        // kolom "Total Selisih" (menandai tabel Rekap Gabungan, bukan tabel B).
        $baris = $xpath->query(
            '//table[.//th[contains(normalize-space(.), "Total Selisih")]]'
            . '//tr[td[2][normalize-space(.)="' . $jenis . '"]]'
        )->item(0);

        $this->assertNotNull($baris, "Baris \"{$jenis}\" tidak ditemukan di tabel Rekap Gabungan.");

        $angka = [];
        foreach ($baris->getElementsByTagName('td') as $td) {
            $angka[] = trim($td->textContent);
        }

        // Urutan kolom: #, Jenis, smhSaldo, smhFisik, smhSel, luarSaldo, luarFisik,
        // luarSel, totalSel, Keterangan.
        $ke = fn(int $i) => (int) str_replace(['.', '+'], '', $angka[$i] === '-' ? '0' : $angka[$i]);

        return [
            'smhSaldo'  => $ke(2),
            'smhFisik'  => $ke(3),
            'smhSel'    => $ke(4),
            'luarSaldo' => $ke(5),
            'luarFisik' => $ke(6),
            'luarSel'   => $ke(7),
            'totalSel'  => $ke(8),
        ];
    }

    /**
     * Kasus nyata dari plan SBG: Baterai 3 Ah dibutuhkan 14 unit, 1 ditemukan
     * menempel di unit, 13 ditemukan di gudang. Seluruhnya tertanggung, jadi
     * Total Selisih harus 0 — bukan -1 seperti yang dilaporkan sebelumnya.
     */
    public function test_fisik_dari_kedua_sumber_dijumlahkan_sehingga_pas_menjadi_nol(): void
    {
        $this->unitOnhand(14, 1, 'Baterai 3 Ah');

        // Baris ini sengaja menyimpan saldo basi 14 — potret dari sebelum unit
        // pertama diperiksa. Report tidak boleh mempercayainya.
        PemeriksaanPerlengkapan::query()->create([
            'plan_audit_id' => $this->plan->id, 'jenis_perlengkapan' => 'Baterai 3 Ah',
            'saldo' => 14, 'fisik' => 13, 'selisih' => -1,
        ]);

        $b = $this->barisRekapGabungan('Baterai 3 Ah');

        $this->assertSame(14, $b['smhSaldo'], 'Jumlah unit yang membutuhkan.');
        $this->assertSame(1, $b['smhFisik'], 'Ditemukan menempel di unit.');
        $this->assertSame(13, $b['luarSaldo'], 'Sisa yang belum tertanggung setelah cek fisik: 14 - 1.');
        $this->assertSame(13, $b['luarFisik'], 'Ditemukan di gudang.');
        $this->assertSame(0, $b['totalSel'], '1 di unit + 13 di gudang = 14, pas dengan kebutuhan 14.');
    }

    /**
     * Kasus Safety tools dari plan yang sama: butuh 63, 56 menempel di unit,
     * 8 ditemukan di gudang — berarti kelebihan 1, bukan kurang 9.
     */
    public function test_kelebihan_terbaca_positif_bukan_kekurangan(): void
    {
        DbPerlengkapan::query()->create([
            'kode' => 'JM31E', 'wilayah' => 'RIAU', 'nama' => 'Vario',
            'keterangan' => 'Safety tools',
        ]);

        for ($i = 1; $i <= 63; $i++) {
            SmhOnhandItem::query()->create([
                'pemeriksaan_smh_id' => $this->smh->id,
                'no_mesin'           => sprintf('JM31E%07d', $i),
                'no_rangka'          => sprintf('MH1JM31E%07d', $i),
                'status_fisik'       => 'ada',
                'perlengkapan_json'  => [['nama' => 'Safety tools', 'ada' => $i <= 56]],
            ]);
        }

        PemeriksaanPerlengkapan::query()->create([
            'plan_audit_id' => $this->plan->id, 'jenis_perlengkapan' => 'Safety tools',
            'saldo' => 17, 'fisik' => 8, 'selisih' => -9, // seluruhnya basi
        ]);

        $b = $this->barisRekapGabungan('Safety tools');

        $this->assertSame(63, $b['smhSaldo']);
        $this->assertSame(56, $b['smhFisik']);
        $this->assertSame(7, $b['luarSaldo'], 'Sisa sesungguhnya 63 - 56, bukan 17 yang tersimpan.');
        $this->assertSame(1, $b['totalSel'], '56 + 8 = 64 terhadap kebutuhan 63 = kelebihan 1.');
    }

    /** Tanpa tindak lanjut Luar SMH, kekurangan dari cek fisik unit tetap utuh. */
    public function test_tanpa_tindak_lanjut_luar_smh_kekurangan_tetap_tampil(): void
    {
        $this->unitOnhand(18, 1, 'Baterai 3 Ah');

        $b = $this->barisRekapGabungan('Baterai 3 Ah');

        $this->assertSame(0, $b['luarFisik']);
        $this->assertSame(-17, $b['totalSel'], '1 dari 18 tertanggung, sisanya benar-benar kurang.');
    }
}
