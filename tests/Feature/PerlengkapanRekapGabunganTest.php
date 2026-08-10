<?php

namespace Tests\Feature;

use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Rekap Gabungan Perlengkapan (bagian C Report Audit) menggabungkan dua sumber:
 *
 *  - "SMH Cek Fisik"        : checklist perlengkapan per unit di tab SMH.
 *  - "Perlengkapan Luar SMH": tab Perlengkapan, yang Saldo bukunya diturunkan
 *                             server dari data onhand (lihat
 *                             App\\Services\\PerlengkapanOnhand::saldoFor()).
 *
 * Supaya kedua sisi bisa direkonsiliasi, keduanya harus memakai penyebut yang
 * sama: jumlah unit onhand yang MEMBUTUHKAN jenis perlengkapan itu.
 */
class PerlengkapanRekapGabunganTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'username' => 'auditor1', 'role' => 'admin', 'unit_usaha' => 'CVSK H1',
        ]));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0424/28/07/2026/SPT-IAT', 'cabang' => 'CVSK H1',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        DbUnitUsaha::query()->create(['unit_usaha' => 'CVSK H1', 'wilayah' => 'RIAU', 'jenis' => 'H1']);

        DbPerlengkapan::query()->create([
            'kode' => 'JBK1E', 'wilayah' => 'RIAU', 'nama' => 'BeAT',
            'keterangan' => 'Helm Open Face, Toolset Matic',
        ]);

        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->plan->id, 'tool' => 'perlengkapan',
            'nama_auditor' => 'Auditor Satu', 'nama_auditee' => 'Kepala Cabang',
        ]);
    }

    /** Buat satu unit onhand beserta checklist perlengkapannya. */
    private function unit(string $noMesin, ?string $statusFisik, ?array $checklist): SmhOnhandItem
    {
        $smh = PemeriksaanSmh::query()->firstOrCreate(
            ['plan_audit_id' => $this->plan->id],
            ['no_spt' => $this->plan->no_spt, 'cabang' => 'CVSK H1']
        );

        return SmhOnhandItem::query()->create([
            'pemeriksaan_smh_id' => $smh->id,
            'no_mesin'           => $noMesin,
            'no_rangka'          => 'MH1' . $noMesin,
            'status_fisik'       => $statusFisik,
            'perlengkapan_json'  => $checklist,
        ]);
    }

    /**
     * Kolom "SMH Cek Fisik" di bagian C report-audit.blade.php, dibaca lewat
     * halaman report yang sebenarnya supaya tes ini ikut gagal kalau blade-nya
     * kembali menghitung penyebutnya sendiri.
     */
    private function smhPlMapDariReport(): array
    {
        $data = $this->get(route('akta.report-audit.pdf', $this->plan))
            ->assertOk()
            ->original
            ->getData();

        $map = [];

        foreach ($data['perlengkapanOnhand'] as $nama => $row) {
            $map[$nama] = ['smhSaldo' => $row['totalOnhand'], 'smhFisik' => $row['ada']];
        }

        return $map;
    }

    /** Saldo buku Luar SMH menurut server (yang tersimpan ke tabel perlengkapan). */
    private function saldoLuarSmh(string $jenis): float
    {
        $response = $this->postJson('/api/audit-detail/perlengkapan', [
            'plan_audit_id'      => $this->plan->id,
            'jenis_perlengkapan' => $jenis,
            'fisik'              => 0,
        ]);

        $response->assertSuccessful();

        return (float) $response->json('data.saldo');
    }

    /**
     * Kasus inti: ada unit onhand yang TIDAK ditemukan saat cek fisik.
     *
     * Unit yang tidak ditemukan tetap membutuhkan perlengkapan, jadi kekurangannya
     * harus tetap terhitung. Dulu bagian C hanya menjumlah unit dengan status_fisik
     * 'ada' sementara Saldo Luar SMH menjumlah SELURUH unit onhand — dua penyebut
     * berbeda, sehingga kedua sisi tabel tidak pernah bertemu.
     */
    public function test_unit_yang_tidak_ditemukan_tetap_terhitung_dan_kedua_sisi_sinkron(): void
    {
        // 3 unit BeAT: 2 ditemukan (1 punya helm, 1 tidak), 1 tidak ditemukan.
        $this->unit('JBK1E1000001', 'ada', [
            ['nama' => 'Helm Open Face', 'ada' => true],
            ['nama' => 'Toolset Matic', 'ada' => true],
        ]);
        $this->unit('JBK1E1000002', 'ada', [
            ['nama' => 'Helm Open Face', 'ada' => false],
            ['nama' => 'Toolset Matic', 'ada' => true],
        ]);
        $this->unit('JBK1E1000003', 'tidak', [
            ['nama' => 'Helm Open Face', 'ada' => false],
            ['nama' => 'Toolset Matic', 'ada' => false],
        ]);

        $smh = $this->smhPlMapDariReport()['Helm Open Face'];

        // Ketiga unit membutuhkan helm — termasuk yang tidak ditemukan fisik,
        // karena kewajiban perlengkapannya tidak hilang hanya karena unitnya
        // tidak ketemu. Satu unit terbukti punya helm.
        $this->assertSame(3, $smh['smhSaldo']);
        $this->assertSame(1, $smh['smhFisik']);
        $kekuranganMenurutBagianC = $smh['smhSaldo'] - $smh['smhFisik']; // = 2

        // Saldo Luar SMH: seluruh 3 unit onhand butuh helm, 1 sudah ada => 2.
        $saldoLuar = $this->saldoLuarSmh('Helm Open Face');

        $this->assertSame(
            (float) $kekuranganMenurutBagianC,
            $saldoLuar,
            'Saldo buku Luar SMH harus sama dengan kekurangan yang ditampilkan kolom SMH Cek Fisik, '
            . "tapi bagian C bilang {$kekuranganMenurutBagianC} sementara Saldo Luar SMH {$saldoLuar}."
        );
    }

    /**
     * Kasus kedua: unit onhand yang checklist perlengkapannya belum tersinkron
     * (perlengkapan_json kosong). Dulu unit itu sama sekali tidak muncul di
     * bagian C, padahal ia tetap membutuhkan perlengkapan dan tetap dihitung
     * oleh Saldo Luar SMH.
     */
    public function test_unit_tanpa_checklist_tersinkron_tetap_terhitung_dan_kedua_sisi_sinkron(): void
    {
        $this->unit('JBK1E2000001', 'ada', [
            ['nama' => 'Helm Open Face', 'ada' => true],
            ['nama' => 'Toolset Matic', 'ada' => true],
        ]);
        // Unit ini ditemukan fisik tapi checklist perlengkapannya belum diisi.
        $this->unit('JBK1E2000002', 'ada', null);

        $smh = $this->smhPlMapDariReport()['Helm Open Face'];
        $kekuranganMenurutBagianC = $smh['smhSaldo'] - $smh['smhFisik']; // 2 - 1 = 1

        $saldoLuar = $this->saldoLuarSmh('Helm Open Face'); // 2 onhand - 1 ada = 1

        $this->assertSame(
            (float) $kekuranganMenurutBagianC,
            $saldoLuar,
            "Bagian C bilang kekurangan {$kekuranganMenurutBagianC}, Saldo Luar SMH bilang {$saldoLuar}."
        );
    }

    /** Kalau semua unit ditemukan dan checklistnya lengkap, keduanya memang sudah cocok. */
    public function test_saat_semua_unit_ditemukan_kedua_sisi_sudah_cocok(): void
    {
        $this->unit('JBK1E3000001', 'ada', [['nama' => 'Helm Open Face', 'ada' => true]]);
        $this->unit('JBK1E3000002', 'ada', [['nama' => 'Helm Open Face', 'ada' => false]]);

        $smh = $this->smhPlMapDariReport()['Helm Open Face'];

        $this->assertSame(
            (float) ($smh['smhSaldo'] - $smh['smhFisik']),
            $this->saldoLuarSmh('Helm Open Face')
        );
    }
}
