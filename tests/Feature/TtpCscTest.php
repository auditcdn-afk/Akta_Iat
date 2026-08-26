<?php

namespace Tests\Feature;

use App\Models\PemeriksaanTtpCsc;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

/**
 * Tool "TTP CSC": import "LAPORAN TTP PANJAR" (.xls), ambil HANYA bagian
 * "II. TTP Sesuai Periode Filter" — file ini juga punya bagian "I. TTP Yang
 * Belum Selesai" dengan format identik tepat di atasnya, yang harus DIABAIKAN.
 * Tanggal Portal diisi manual per baris; Selisih Tgl & Keterangan default
 * dihitung di server saat Tanggal Portal disimpan.
 */
class TtpCscTest extends TestCase
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

    private function buildLapTtpFile(): UploadedFile
    {
        $sheet = new Spreadsheet();
        $ws = $sheet->getActiveSheet();

        // Bagian I — HARUS diabaikan oleh parser (formatnya identik dengan bagian II).
        $ws->setCellValue('A9', 'I. TTP YANG BELUM SELESAI');
        $ws->fromArray(['', '', 'TTP', '', '', 'Penagihan', '', '', '', 'Penyelesaian', '', '', 'Pengembalian'], null, 'A11');
        $ws->fromArray(['No', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Sisa'], null, 'A12');
        $ws->fromArray(['No. Reg', '', 'Tanggal', 'Nama', 'Jenis', 'No WO / PRF', 'Status', 'Nilai', 'Tgl Selesai', 'No. Nota', 'No. NSC', 'Nilai', 'No. BPK', 'Tgl. BPK', ''], null, 'A13');
        $ws->fromArray([1, 'XX000-SALAH', 45000, 'JANGAN TERBACA', '2', 'PRQ/SALAH', 'P', 999999, '', '', '', '', '', '', 999999], null, 'A14');
        $ws->fromArray(['', '', '', '', '', '', '', 'T O T A L', 999999, '', '', '', '', '', 999999], null, 'A16');

        // Bagian II — yang harus diambil.
        $ws->setCellValue('A26', 'II. TTP SESUAI PERIODE FILTER');
        $ws->fromArray(['', '', 'TTP', '', '', 'Penagihan', '', '', '', 'Penyelesaian', '', '', 'Pengembalian'], null, 'A28');
        $ws->fromArray(['No', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Sisa'], null, 'A29');
        $ws->fromArray(['No. Reg', '', 'Tanggal', 'Nama', 'Jenis', 'No WO / PRF', 'Status', 'Nilai', 'Tgl Selesai', 'No. Nota', 'No. NSC', 'Nilai', 'No. BPK', 'Tgl. BPK', ''], null, 'A30');
        $ws->fromArray([1, 'CO006603', 46131, 'IRDAYANA', '1', 'JOS/26/04/002350', 'F', 500000, 46137, 'BB177476', 'SOP/26/04/02135', 500000, '0172/CND/IV/2026', 46137, 0], null, 'A31');
        $ws->fromArray([2, 'CO006608', 46240, 'RAHMAT HIDAYAT', '1', 'JOS/26/08/004886', 'P', 2000000, '', '', '', '', '', '', 2000000], null, 'A33');
        $ws->fromArray(['', '', '', '', '', '', '', 'T O T A L', 2500000, '', '', 500000, '', '', 2000000], null, 'A35');

        $path = sys_get_temp_dir() . '/' . uniqid('ttp_csc_test_') . '.xls';
        (new Xls($sheet))->save($path);
        return new UploadedFile($path, 'lap_ttp.xls', null, null, true);
    }

    public function test_parse_hanya_ambil_bagian_ii_bukan_bagian_i(): void
    {
        $res = $this->postJson('/api/audit-detail/ttp-csc/parse-excel', [
            'file' => $this->buildLapTtpFile(),
        ])->assertOk();

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('CO006603', $data[0]['ttp']);
        $this->assertSame('IRDAYANA', $data[0]['nama']);
        $this->assertSame('2026-04-19', $data[0]['tanggal']);
        $this->assertSame(500000, $data[0]['nilai']);
        $this->assertSame('CO006608', $data[1]['ttp']);
        // Baris bagian I ("XX000-SALAH") tidak boleh ikut terbaca.
        $this->assertStringNotContainsString('XX000-SALAH', json_encode($data));
        // Tanggal Portal awalnya kosong — belum diisi auditor.
        $this->assertSame('', $data[0]['tanggalPortal']);
        $this->assertNull($data[0]['selisihTgl']);
    }

    public function test_update_tanggal_portal_selisih_0_jadi_data_sesuai(): void
    {
        PemeriksaanTtpCsc::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['no' => 1, 'ttp' => 'CO006603', 'tanggal' => '2026-04-19', 'nama' => 'IRDAYANA', 'nilai' => 500000, 'tanggalPortal' => '', 'selisihTgl' => null, 'keterangan' => ''],
            ],
        ]);

        $res = $this->patchJson('/api/audit-detail/ttp-csc/tanggal-portal', [
            'planAuditId' => $this->plan->id, 'index' => 0, 'tanggalPortal' => '2026-04-19',
        ])->assertOk();

        $this->assertSame(0, $res->json('item.selisihTgl'));
        $this->assertSame('Data Sesuai', $res->json('item.keterangan'));
    }

    public function test_update_tanggal_portal_beda_jadi_selisih(): void
    {
        PemeriksaanTtpCsc::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['no' => 1, 'ttp' => 'CO006603', 'tanggal' => '2026-04-19', 'nama' => 'IRDAYANA', 'nilai' => 500000, 'tanggalPortal' => '', 'selisihTgl' => null, 'keterangan' => ''],
            ],
        ]);

        $res = $this->patchJson('/api/audit-detail/ttp-csc/tanggal-portal', [
            'planAuditId' => $this->plan->id, 'index' => 0, 'tanggalPortal' => '2026-04-22',
        ])->assertOk();

        $this->assertSame(3, $res->json('item.selisihTgl'));
        $this->assertSame('Selisih', $res->json('item.keterangan'));
    }

    public function test_keterangan_bisa_ditimpa_manual_setelah_dihitung_otomatis(): void
    {
        PemeriksaanTtpCsc::create([
            'plan_audit_id' => $this->plan->id,
            'items_json' => [
                ['no' => 1, 'ttp' => 'CO006603', 'tanggal' => '2026-04-19', 'nama' => 'IRDAYANA', 'nilai' => 500000, 'tanggalPortal' => '2026-04-22', 'selisihTgl' => 3, 'keterangan' => 'Selisih'],
            ],
        ]);

        $this->patchJson('/api/audit-detail/ttp-csc/keterangan', [
            'planAuditId' => $this->plan->id, 'index' => 0, 'keterangan' => 'Selisih 3 hari, sudah dikonfirmasi ke portal',
        ])->assertOk();

        $rec = PemeriksaanTtpCsc::where('plan_audit_id', $this->plan->id)->first();
        $this->assertSame('Selisih 3 hari, sudah dikonfirmasi ke portal', $rec->items_json[0]['keterangan']);
        // Selisih Tgl tidak ikut berubah — hanya keterangan yang ditimpa.
        $this->assertSame(3, $rec->items_json[0]['selisihTgl']);
    }

    public function test_save_ditolak_sebelum_auditor_terisi(): void
    {
        $this->postJson('/api/audit-detail/ttp-csc', [
            'planAuditId' => $this->plan->id,
            'items' => [['ttp' => 'CO006603']],
        ])->assertStatus(422);
    }
}
