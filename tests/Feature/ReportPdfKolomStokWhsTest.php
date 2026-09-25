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
 * Tabel HGP di Report Audit ikut mencetak Faktur Belum Kutip & Claim.
 *
 * Kedua kolom itu hanya ada pada data hasil impor laporan stok WHS. Berkas
 * onhand cabang tidak punya kolom itu sama sekali, jadi yang dijaga di sini
 * bukan cuma "kolomnya muncul", tapi juga "tidak muncul di tempat yang tidak
 * ada hubungannya" -- tabelnya sudah lebar, dan dua kolom kosong di PDF A4
 * mendorong kolom lain keluar halaman.
 */
class ReportPdfKolomStokWhsTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Sanctum::actingAs(User::factory()->create(['username' => 'admin1', 'role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'WHS Avian',
            'jenis_audit' => 'Audit Warehouse PART', 'status' => 'running',
        ]);
    }

    private function html(): string
    {
        return $this->get(route('akta.report-audit.pdf', $this->plan))->assertOk()->getContent();
    }

    private function item(string $noPart, float $saldo, array $stok = []): array
    {
        return [
            'noPart' => $noPart, 'sparepart' => 'Part ' . $noPart,
            'saldoAkhir' => $saldo, 'fisik' => 0, 'wo' => 0,
            'akhir' => $saldo, 'selisih' => -$saldo,
            'keterangan' => '', 'tgl' => '2026-09-21', 'logScan' => [],
            'stok' => $stok,
        ];
    }

    /** Baris tabel HGP sebagai daftar sel, supaya kesejajarannya bisa diperiksa. */
    private function tabelHgp(string $html): array
    {
        $i = strpos($html, 'FKT Blm Kutip');
        $awal = $i === false ? strpos($html, 'Saldo Akhir') : $i;
        $awal = strrpos(substr($html, 0, $awal), '<table');
        $blok = substr($html, $awal, strpos($html, '</table>', $awal) - $awal);

        preg_match('/<thead>(.*?)<\/thead>/s', $blok, $th);
        $bersih = fn ($t) => trim(preg_replace('/\s+/', ' ', strip_tags($t)));

        $judul = array_map($bersih, preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $th[1] ?? '', $m) ? $m[1] : []);

        $baris = [];
        foreach (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', substr($blok, strpos($blok, '<tbody>')), $r) ? $r[1] : [] as $row) {
            $sel = array_map($bersih, preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $c) ? $c[1] : []);
            if ($sel !== []) $baris[] = $sel;
        }

        return ['judul' => $judul, 'baris' => $baris];
    }

    public function test_data_whs_memunculkan_kolom_fkt_dan_claim(): void
    {
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [
                $this->item('P1', 10, ['awal' => 12, 'fakturBelumKutip' => 3, 'claim' => 2]),
            ],
        ]);

        $tabel = $this->tabelHgp($this->html());

        $this->assertContains('FKT Blm Kutip', $tabel['judul']);
        $this->assertContains('Claim', $tabel['judul']);

        // Letaknya tepat sebelum Saldo Akhir -- keduanya yang menerangkan angka itu.
        $posFkt   = array_search('FKT Blm Kutip', $tabel['judul'], true);
        $posClaim = array_search('Claim', $tabel['judul'], true);
        $posSaldo = array_search('Saldo Akhir', $tabel['judul'], true);
        $this->assertSame($posFkt + 1, $posClaim);
        $this->assertSame($posClaim + 1, $posSaldo);

        // Angkanya benar-benar tercetak, bukan cuma judulnya.
        $this->assertSame('3', $tabel['baris'][0][$posFkt]);
        $this->assertSame('2', $tabel['baris'][0][$posClaim]);
    }

    public function test_berkas_onhand_cabang_tidak_kebagian_kolom_itu(): void
    {
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [$this->item('P1', 10)],   // tanpa 'stok'
        ]);

        $html = $this->html();

        $this->assertStringNotContainsString('FKT Blm Kutip', $html);
        $this->assertSame(['#', 'No. Part', 'Nama Part', 'Tgl Periksa', 'Saldo Akhir'],
            array_slice($this->tabelHgp($html)['judul'], 0, 5));
    }

    public function test_tiap_baris_tetap_sejajar_dengan_judulnya(): void
    {
        // Kolom yang kosong di berkas tidak dijadikan 0, jadi sebagian item
        // tidak punya kunci 'fakturBelumKutip' sama sekali. Selnya tetap harus
        // dicetak, kalau tidak seluruh baris bergeser satu kolom.
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [
                $this->item('P1', 10, ['awal' => 12, 'fakturBelumKutip' => 3, 'claim' => 2]),
                $this->item('P2', 5,  ['awal' => 5]),
                $this->item('P3', 7,  ['awal' => 7, 'claim' => 1]),
            ],
        ]);

        $tabel = $this->tabelHgp($this->html());
        $jumlahJudul = count($tabel['judul']);

        foreach ($tabel['baris'] as $n => $sel) {
            if (($sel[0] ?? '') === 'TOTAL') continue;
            $this->assertCount($jumlahJudul, $sel, "baris ke-" . ($n + 1) . " tidak sejajar");
        }

        $posFkt = array_search('FKT Blm Kutip', $tabel['judul'], true);
        $this->assertSame('—', $tabel['baris'][1][$posFkt], 'yang kosong ditulis tanda pisah, bukan dilewati');
    }

    /** Tabel REKAP SELISIH PART & AHM OIL'S di bawah tabel lengkapnya. */
    private function tabelRekap(string $html): array
    {
        $i = strpos($html, 'REKAP SELISIH');
        $this->assertNotFalse($i, 'section rekap selisih tidak ada');

        $t0 = strpos($html, '<table', $i);
        $blok = substr($html, $t0, strpos($html, '</table>', $t0) - $t0);

        $bersih = fn ($t) => trim(preg_replace('/\s+/', ' ', strip_tags($t)));
        preg_match('/<thead>(.*?)<\/thead>/s', $blok, $th);
        $judul = array_map($bersih, preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $th[1] ?? '', $m) ? $m[1] : []);

        $baris = [];
        foreach (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', substr($blok, strpos($blok, '<tbody>')), $r) ? $r[1] : [] as $row) {
            $sel = array_map($bersih, preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $c) ? $c[1] : []);
            if ($sel !== []) $baris[] = $sel;
        }

        return ['judul' => $judul, 'baris' => $baris];
    }

    public function test_rekap_selisih_ikut_memunculkan_kedua_kolom(): void
    {
        // Rekap selisih dibaca terpisah dari tabel lengkapnya -- sering justru
        // itu yang dilampirkan ke unit usaha. Tanpa kedua kolom ini, selisih
        // yang sebenarnya punya penjelasan terbaca tanpa sebab.
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [
                // fisik 0 vs saldo 32 -> selisih -32, jadi ikut rekap
                array_merge($this->item('P1', 32, ['fakturBelumKutip' => 1, 'claim' => 4]), ['selisih' => -32]),
            ],
        ]);

        $rekap = $this->tabelRekap($this->html());

        $this->assertSame(
            ['NO', 'KODE PART', 'NAMA PART', 'FKT BLM KUTIP', 'CLAIM', 'SISTEM', 'FISIK', 'SELISIH', 'HET', 'KETERANGAN'],
            $rekap['judul']
        );

        $this->assertSame('1', $rekap['baris'][0][3]);
        $this->assertSame('4', $rekap['baris'][0][4]);
    }

    public function test_rekap_selisih_cabang_tetap_seperti_semula(): void
    {
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [array_merge($this->item('P1', 32), ['selisih' => -32])],
        ]);

        $this->assertSame(
            ['NO', 'KODE PART', 'NAMA PART', 'SISTEM', 'FISIK', 'SELISIH', 'HET', 'KETERANGAN'],
            $this->tabelRekap($this->html())['judul']
        );
    }

    public function test_rekap_rsa_hgp_tidak_ikut_melebar(): void
    {
        // Partial rekap dipakai bersama RSA HGP, dan hanya HGP yang
        // mengirimkan 'kolomStok'. Diuji dengan sengaja memberi RSA data yang
        // MEMBAWA kolom stok: tabelnya tetap harus 8 kolom, kalau ikut melebar
        // kolom kanannya terdorong keluar halaman A4.
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [
                array_merge($this->item('P1', 32, ['fakturBelumKutip' => 1]), ['selisih' => -32]),
            ],
        ]);

        PemeriksaanRsaHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [
                array_merge($this->item('R1', 20, ['fakturBelumKutip' => 9]), ['selisih' => -20]),
            ],
        ]);

        $html = $this->html();

        // HGP punya dua tabel rekap (AHM OIL'S & SPAREPART), tapi yang kosong
        // dicetak "Tidak ada selisih." tanpa judul kolom. Satu item di atas
        // jatuh ke SPAREPART, jadi tepat satu tabel yang bertajuk lengkap --
        // dan RSA yang datanya juga membawa stok TIDAK menambahinya.
        $this->assertSame(1, substr_count($html, 'FKT BLM KUTIP'));
        $this->assertStringContainsString('R1', $html, 'rekap RSA memang ikut tercetak');
    }

    public function test_baris_total_menjumlahkan_kedua_kolom(): void
    {
        PemeriksaanHgp::query()->create([
            'plan_audit_id' => $this->plan->id,
            'items_json'    => [
                $this->item('P1', 10, ['fakturBelumKutip' => 3, 'claim' => 2]),
                $this->item('P2', 10, ['fakturBelumKutip' => 4]),
            ],
        ]);

        $tabel = $this->tabelHgp($this->html());
        $total = collect($tabel['baris'])->first(fn ($sel) => ($sel[0] ?? '') === 'TOTAL');

        $this->assertNotNull($total);
        // colspan=4 menutup # / No. Part / Nama Part / Tgl Periksa, jadi sel
        // berikutnya persis FKT lalu Claim.
        $this->assertSame('7', $total[1]);
        $this->assertSame('2', $total[2]);
    }
}
