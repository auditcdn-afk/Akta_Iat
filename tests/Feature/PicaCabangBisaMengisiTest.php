<?php

namespace Tests\Feature;

use App\Models\Pica;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PICA diisi bergiliran: auditor menulis Current Condition, unit usaha mengisi
 * tindak lanjutnya, pihak Relation Ship menuliskan tanggapan, lalu unit usaha
 * melakukan Re-Chek.
 *
 * Dilaporkan dari SO UJT: bagian yang jelas-jelas berlabel "(diisi cabang)"
 * tidak bisa diisi sama sekali. Dan begitu tanggapan Relation Ship masuk,
 * isian unit usaha ikut hilang -- keduanya menumpang di satu kolom yang sama.
 */
class PicaCabangBisaMengisiTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = PlanAudit::query()->create([
            'no_spt'      => '0461/01/09/2026/SPT-IAT',
            'cabang'      => 'SO UJT',
            'jenis_audit' => 'Audit Full SO',
            'status'      => 'running',
        ]);
    }

    private function picaDariAuditor(array $tambahan = []): Pica
    {
        return Pica::query()->create([
            'plan_audit_id'     => $this->plan->id,
            'pica_no'           => 'PICA-20260919-0001',
            'title'             => 'Penyerahan BPKB',
            'current_condition' => 'Penyerahan BPKB dari tanggal BO ke Konsumen > 120 hari.',
            'unit_usaha'        => 'SO UJT',
            'source_type'       => 'grading',
            'status'            => 'open',
            'priority'          => 'sedang',
            'created_by'        => 'auditor1',
        ] + $tambahan);
    }

    private function cabang(string $unit = 'SO UJT'): User
    {
        return User::factory()->create(['role' => 'h1', 'unit_usaha' => $unit]);
    }

    private function isianCabang(array $tambahan = []): array
    {
        return array_merge([
            'title'                  => 'Penyerahan BPKB',
            'current_condition'      => 'Penyerahan BPKB dari tanggal BO ke Konsumen > 120 hari.',
            'problem_identification' => 'BPKB belum diambil konsumen walau sudah dihubungi.',
            'corrective_action'      => 'Kirim surat panggilan dan telepon ulang tiap minggu.',
            'pic'                    => 'Kepala Administrasi',
            'relation_ship'          => 'Budi (CSC UJT)',
            'target_date'            => '2026-10-31',
        ], $tambahan);
    }

    public function test_unit_usaha_bisa_mengisi_bagian_yang_berlabel_diisi_cabang(): void
    {
        $pica = $this->picaDariAuditor();
        Sanctum::actingAs($this->cabang());

        $this->putJson("/api/picas/{$pica->id}", $this->isianCabang())
            ->assertOk();

        $pica->refresh();

        $this->assertSame('BPKB belum diambil konsumen walau sudah dihubungi.', $pica->problem_identification);
        $this->assertSame('Kirim surat panggilan dan telepon ulang tiap minggu.', $pica->corrective_action);
        $this->assertSame('Kepala Administrasi', $pica->pic);
        $this->assertSame('Budi (CSC UJT)', $pica->relation_ship);
        $this->assertSame('2026-10-31', $pica->target_date->toDateString());
    }

    public function test_pica_diteruskan_ke_unit_pihak_relation_ship(): void
    {
        $pica = $this->picaDariAuditor();
        Sanctum::actingAs($this->cabang());

        $this->putJson("/api/picas/{$pica->id}", $this->isianCabang())
            ->assertOk()
            ->assertJsonPath('forwarded_to', 'Budi (CSC UJT)');

        $pica->refresh();

        $this->assertSame('CSC UJT', $pica->forwarded_to_unit);
        $this->assertSame('progress', $pica->status);
    }

    public function test_cabang_tidak_bisa_mengubah_tulisan_auditor(): void
    {
        $pica = $this->picaDariAuditor();
        Sanctum::actingAs($this->cabang());

        $this->putJson("/api/picas/{$pica->id}", $this->isianCabang([
            'title'             => 'Judul diubah cabang',
            'current_condition' => 'Dihapus cabang',
        ]))->assertOk();

        $pica->refresh();

        $this->assertSame('Penyerahan BPKB', $pica->title);
        $this->assertSame(
            'Penyerahan BPKB dari tanggal BO ke Konsumen > 120 hari.',
            $pica->current_condition
        );
    }

    public function test_tanggapan_relation_ship_tidak_menimpa_isian_unit_usaha(): void
    {
        $pica = $this->picaDariAuditor();

        Sanctum::actingAs($this->cabang());
        $this->putJson("/api/picas/{$pica->id}", $this->isianCabang())->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'h2', 'unit_usaha' => 'CSC UJT']));
        $this->putJson("/api/picas/{$pica->id}", [
            'tanggapan_pica'         => 'Unit sudah menyiapkan BPKB, tinggal serah terima.',
            'problem_identification' => 'Unit sudah menyiapkan BPKB, tinggal serah terima.',
            'corrective_action'      => '',
        ])->assertOk();

        $pica->refresh();

        $this->assertSame('Unit sudah menyiapkan BPKB, tinggal serah terima.', $pica->tanggapan_pica);
        $this->assertSame('BPKB belum diambil konsumen walau sudah dihubungi.', $pica->problem_identification);
        $this->assertSame('Kirim surat panggilan dan telepon ulang tiap minggu.', $pica->corrective_action);
        $this->assertNotNull($pica->forwarded_filled_at);
    }

    public function test_pihak_relation_ship_melihat_pica_yang_diteruskan_padanya(): void
    {
        $pica = $this->picaDariAuditor();

        Sanctum::actingAs($this->cabang());
        $this->putJson("/api/picas/{$pica->id}", $this->isianCabang())->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'h2', 'unit_usaha' => 'CSC UJT']));

        $this->getJson('/api/picas')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $pica->id);
    }

    public function test_unit_usaha_lain_tidak_melihat_pica_cabang_ini(): void
    {
        $this->picaDariAuditor();

        Sanctum::actingAs(User::factory()->create(['role' => 'h1', 'unit_usaha' => 'SO PJD']));

        $this->getJson('/api/picas')->assertOk()->assertJsonCount(0);
    }

    public function test_pemilik_pica_tetap_berperan_cabang_walau_namanya_ada_di_relation_ship(): void
    {
        $pica = $this->picaDariAuditor([
            'relation_ship'    => 'Andi (SO UJT)',
            'forwarded_to_unit' => 'SO UJT',
        ]);

        Sanctum::actingAs($this->cabang());

        $this->putJson("/api/picas/{$pica->id}", $this->isianCabang([
            'relation_ship' => 'Andi (SO UJT)',
        ]))->assertOk();

        $pica->refresh();

        $this->assertSame('BPKB belum diambil konsumen walau sudah dihubungi.', $pica->problem_identification);
        $this->assertNull($pica->forwarded_filled_at);
    }

    public function test_re_chek_unit_usaha_tersimpan(): void
    {
        $pica = $this->picaDariAuditor();
        Sanctum::actingAs($this->cabang());

        $this->postJson("/api/picas/{$pica->id}/upload-recheck", [
            'recheck_note'     => 'Sudah diserahkan ke konsumen 20 Oktober.',
            'recheck_deadline' => '2026-11-15',
        ])->assertOk();

        $pica->refresh();

        $this->assertSame('Sudah diserahkan ke konsumen 20 Oktober.', $pica->recheck_note);
        $this->assertSame('2026-11-15', $pica->recheck_deadline->toDateString());
        $this->assertNotNull($pica->recheck_at);
    }

    public function test_simpan_tetap_jalan_saat_hosting_belum_punya_kolom_baru(): void
    {
        $pica = $this->picaDariAuditor();

        // Hosting mengunggah kode lebih dulu dan menjalankan migration belakangan.
        Schema::table('picas', fn($t) => $t->dropColumn('tanggapan_pica'));

        Sanctum::actingAs($this->cabang());

        $this->putJson("/api/picas/{$pica->id}", $this->isianCabang([
            'tanggapan_pica' => 'Belum ada kolomnya di hosting.',
        ]))->assertOk();

        $this->assertSame(
            'BPKB belum diambil konsumen walau sudah dihubungi.',
            $pica->refresh()->problem_identification
        );
    }

    public function test_formulir_pica_tidak_mengunci_kolom_cabang_di_markup(): void
    {
        $markup = file_get_contents(resource_path('views/akta/pages/pica.blade.php'));

        // Inilah yang dilihat auditor SO UJT: kolomnya terkunci sejak dari
        // markup, sebelum peran pengisinya sempat diperiksa.
        foreach (['problemIdentification', 'correctiveAction', 'pic', 'relationShip', 'relationShip2'] as $kolom) {
            preg_match('/id="' . $kolom . '"[^>]*>/', $markup, $m);

            $this->assertNotEmpty($m, "Kolom {$kolom} tidak ada di formulir PICA.");

            // Tanpa kelas Tailwind disabled:*, yang tersisa hanya atribut aslinya.
            $tanpaKelas = preg_replace('/class="[^"]*"/', '', $m[0]);

            $this->assertDoesNotMatchRegularExpression(
                '/\sdisabled[\s>=]/',
                $tanpaKelas,
                "Kolom {$kolom} dikunci sejak dari markup, jadi cabang tidak akan pernah bisa mengisinya."
            );
        }
    }
}
