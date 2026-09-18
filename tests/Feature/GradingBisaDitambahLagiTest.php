<?php

namespace Tests\Feature;

use App\Models\AuditGrading;
use App\Models\Pica;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Satu grading diisi bertahap: item demi item, kadang lintas hari dan lintas
 * auditor. Karena itu menyimpan grading tidak boleh menguncinya.
 *
 * Dilaporkan: setelah memilih satu jenis penilaian dan menyimpan, auditor tidak
 * bisa menambah jenis penilaian berikutnya sama sekali.
 *
 * Baris PICA yang diterbitkan grading dicocokkan lewat NAMA pemeriksaan, bukan
 * nomor barisnya -- nomor baris bergeser begitu ada item yang dihapus, dan PICA
 * yang ikut bergeser akan menempel pada temuan yang salah.
 */
class GradingBisaDitambahLagiTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->plan = PlanAudit::query()->create([
            'no_spt' => '0460/01/09/2026/SPT-IAT', 'cabang' => 'CSC UJT',
            'jenis_audit' => 'Audit Full CSC', 'status' => 'running',
        ]);
    }

    private function item(string $nama, float $nilai = 0.15, string $kondisi = ''): array
    {
        return [
            'namaPemeriksaan'  => $nama,
            'hasilPemeriksaan' => '5. ' . $nama . ' sesuai',
            'nilai'            => $nilai,
            'currentCondition' => $kondisi,
        ];
    }

    private function simpan(array $details)
    {
        return $this->postJson('/api/audit-detail/grading', [
            'planAuditId' => $this->plan->id,
            'idGrading'   => '2026-09-18',
            'jenis'       => 'CSC',
            'area'        => 'RIAU',
            'bbnkb'       => 'N',
            'fraud'       => 'N',
            'details'     => $details,
            'totalNilai'  => array_sum(array_column($details, 'nilai')),
        ])->assertOk();
    }

    /** Grading yang sudah tersimpan tetap bisa ditambah itemnya. */
    public function test_item_masih_bisa_ditambah_setelah_grading_tersimpan(): void
    {
        $this->simpan([$this->item('Area Gedung')]);

        $this->simpan([$this->item('Area Gedung'), $this->item('Pemeriksaan Kas Kecil', 0.25)]);

        $rec = AuditGrading::where('plan_audit_id', $this->plan->id)->first();
        $this->assertCount(2, $rec->details);
        $this->assertSame(['Area Gedung', 'Pemeriksaan Kas Kecil'], array_column($rec->details, 'namaPemeriksaan'));
        $this->assertEqualsWithDelta(0.40, (float) $rec->total_nilai, 0.001);
    }

    /** PICA yang diisi sesudah grading tersimpan harus sampai ke server. */
    public function test_pica_yang_diisi_setelah_tersimpan_ikut_terbit(): void
    {
        $this->simpan([$this->item('Area Gedung')]);
        $this->assertSame(0, Pica::where('source_type', 'grading')->count());

        $this->simpan([$this->item('Area Gedung', 0.15, 'Lantai H2 retak dan berlubang')]);

        $pica = Pica::where('source_type', 'grading')->get();
        $this->assertCount(1, $pica);
        $this->assertSame('Area Gedung', $pica[0]->title);
        $this->assertSame('Lantai H2 retak dan berlubang', $pica[0]->current_condition);
        $this->assertNotNull($pica[0]->pica_no);
    }

    /**
     * Menghapus item di ATAS sebuah temuan menggeser nomor barisnya. PICA-nya
     * harus tetap menempel pada temuan yang benar, bukan ikut bergeser.
     */
    public function test_pica_tidak_tertukar_saat_item_di_atasnya_dihapus(): void
    {
        $this->simpan([
            $this->item('Area Gedung'),
            $this->item('Pemeriksaan Kas Kecil', 0.25, 'Selisih kas Rp 50.000'),
        ]);

        $picaAwal = Pica::where('title', 'Pemeriksaan Kas Kecil')->firstOrFail();
        $this->assertSame(1, $picaAwal->source_item_idx);

        // Auditor menghapus item pertama; Kas Kecil naik ke baris 0.
        $this->simpan([$this->item('Pemeriksaan Kas Kecil', 0.25, 'Selisih kas Rp 50.000')]);

        $picaAkhir = Pica::where('source_type', 'grading')->get();
        $this->assertCount(1, $picaAkhir, 'Tidak boleh terbit baris PICA baru hanya karena nomornya bergeser.');
        $this->assertSame($picaAwal->id, $picaAkhir[0]->id, 'Baris PICA-nya harus yang sama, bukan yang baru.');
        $this->assertSame('Pemeriksaan Kas Kecil', $picaAkhir[0]->title);
        $this->assertSame(0, $picaAkhir[0]->source_item_idx);
        $this->assertSame('Selisih kas Rp 50.000', $picaAkhir[0]->current_condition);
    }

    /** Menyimpan ulang tidak boleh membuka kembali PICA yang sudah ditutup. */
    public function test_pica_yang_sudah_ditutup_tidak_dibuka_lagi(): void
    {
        $this->simpan([$this->item('Area Gedung', 0.15, 'Lantai H2 retak')]);

        $pica = Pica::where('source_type', 'grading')->firstOrFail();
        $pica->update(['status' => 'closed', 'root_cause' => 'Beton lama', 'corrective_action' => 'Cor ulang']);

        $this->simpan([$this->item('Area Gedung', 0.15, 'Lantai H2 retak'), $this->item('Material Promosi', 0.1)]);

        $pica->refresh();
        $this->assertSame('closed', $pica->status);
        $this->assertSame('Beton lama', $pica->root_cause);
        $this->assertSame('Cor ulang', $pica->corrective_action);
    }

    /** Temuan yang dicabut dari grading tidak boleh menggantung di daftar PICA. */
    public function test_pica_dari_item_yang_dicabut_ikut_hilang_selama_belum_dikerjakan(): void
    {
        $this->simpan([
            $this->item('Area Gedung', 0.15, 'Lantai H2 retak'),
            $this->item('Material Promosi', 0.10, 'Spanduk sobek'),
        ]);
        $this->assertSame(2, Pica::where('source_type', 'grading')->count());

        $this->simpan([$this->item('Area Gedung', 0.15, 'Lantai H2 retak')]);

        $sisa = Pica::where('source_type', 'grading')->get();
        $this->assertCount(1, $sisa);
        $this->assertSame('Area Gedung', $sisa[0]->title);
    }

    /** ...tapi yang tindak lanjutnya sudah dikerjakan TIDAK boleh ikut terhapus. */
    public function test_pica_yang_sudah_dikerjakan_tidak_terhapus_walau_itemnya_dicabut(): void
    {
        $this->simpan([
            $this->item('Area Gedung', 0.15, 'Lantai H2 retak'),
            $this->item('Material Promosi', 0.10, 'Spanduk sobek'),
        ]);

        $picaPromosi = Pica::where('title', 'Material Promosi')->firstOrFail();
        $picaPromosi->update(['root_cause' => 'Dipasang sejak 2024', 'pic' => 'Kepala Bengkel']);

        $this->simpan([$this->item('Area Gedung', 0.15, 'Lantai H2 retak')]);

        $this->assertNotNull(Pica::find($picaPromosi->id),
            'PICA yang sudah diisi akar masalah & PIC-nya adalah hasil kerja orang — tidak boleh hilang diam-diam.');
    }
}
