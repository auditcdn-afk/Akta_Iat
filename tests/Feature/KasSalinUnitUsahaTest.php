<?php

namespace Tests\Feature;

use App\Models\PemeriksaanAuditor;
use App\Models\PemeriksaanKas;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SO dan CSC di lokasi yang sama memakai kas yang sama dan dihitung sekali,
 * jadi hasilnya boleh disalin — TAPI hanya antar unit usaha yang sama, yang
 * dikenali dari kata terakhir namanya: "SO UJT" dengan "CSC UJT".
 */
class KasSalinUnitUsahaTest extends TestCase
{
    use RefreshDatabase;

    private PlanAudit $so;
    private PlanAudit $csc;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['role' => 'auditor', 'username' => 'auditor1']));

        $this->so  = $this->plan('0459/01/09/2026/SPT-IAT', 'SO UJT', 'Audit Full SO');
        $this->csc = $this->plan('0460/01/09/2026/SPT-IAT', 'CSC UJT', 'Audit Full CSC');

        $this->auditorTerisi($this->so);

        PemeriksaanKas::query()->create([
            'plan_audit_id' => $this->so->id,
            'no_spt'        => $this->so->no_spt,
            'cabang'        => $this->so->cabang,
            'jenis_audit'   => $this->so->jenis_audit,
            'nama_pos'      => 'Pemeriksaan Kas',
            'saldo_fisik'   => 86_021_832,
            'saldo_buku'    => 86_021_832,
            'selisih'       => 0,
            'detail_json'   => $this->isiKas(),
            'created_by'    => 'salim',
        ]);
    }

    // ── Daftar sumber ────────────────────────────────────────────────────────

    public function test_daftar_sumber_hanya_unit_usaha_dengan_kata_terakhir_sama(): void
    {
        // Unit usaha lain yang kasnya juga terisi — tidak boleh muncul.
        $lain = $this->plan('0461/01/09/2026/SPT-IAT', 'SO PRW', 'Audit Full SO');
        PemeriksaanKas::query()->create([
            'plan_audit_id' => $lain->id, 'cabang' => $lain->cabang,
            'nama_pos' => 'Pemeriksaan Kas', 'detail_json' => $this->isiKas(),
        ]);

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}")->assertOk();

        $this->assertSame('UJT', $res->json('kunci'));
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('SO UJT', $res->json('data.0.cabang'));
        $this->assertContains('2 penerimaan', $res->json('data.0.ringkas'));
        $this->assertContains('1 register blanko H1', $res->json('data.0.ringkas'));
    }

    public function test_nama_unit_usaha_tiga_kata_ikut_cocok(): void
    {
        $gudang = $this->plan('0470/SPT-IAT', 'WHS Part KIM', 'Audit Full SO');
        $kantor = $this->plan('0471/SPT-IAT', 'SO KIM', 'Audit Full SO');
        PemeriksaanKas::query()->create([
            'plan_audit_id' => $gudang->id, 'cabang' => $gudang->cabang,
            'nama_pos' => 'Pemeriksaan Kas', 'detail_json' => $this->isiKas(),
        ]);

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$kantor->id}")->assertOk();

        $this->assertSame('KIM', $res->json('kunci'));
        $this->assertSame('WHS Part KIM', $res->json('data.0.cabang'));
    }

    public function test_nama_mirip_tapi_bukan_kata_terakhir_tidak_ikut(): void
    {
        // "SO SUJT" berakhiran UJT kalau dicocokkan sebagai potongan huruf,
        // tapi kata terakhirnya SUJT — bukan unit usaha yang sama.
        $jebakan = $this->plan('0480/SPT-IAT', 'SO SUJT', 'Audit Full SO');
        PemeriksaanKas::query()->create([
            'plan_audit_id' => $jebakan->id, 'cabang' => $jebakan->cabang,
            'nama_pos' => 'Pemeriksaan Kas', 'detail_json' => $this->isiKas(),
        ]);

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}")->assertOk();

        $this->assertSame(['SO UJT'], array_column($res->json('data'), 'cabang'));
    }

    public function test_daftar_sumber_tidak_menarik_seluruh_tabel(): void
    {
        // Unit usaha lain yang datanya banyak: tidak boleh ikut terbaca hanya
        // untuk menampilkan daftar pilihan.
        foreach (range(1, 12) as $i) {
            $lain = $this->plan("05{$i}/SPT-IAT", 'SO PRW', 'Audit Full SO');
            PemeriksaanKas::query()->create([
                'plan_audit_id' => $lain->id, 'cabang' => $lain->cabang,
                'nama_pos' => 'Pemeriksaan Kas', 'detail_json' => $this->isiKas(),
            ]);
        }

        $terbaca = 0;
        \Illuminate\Support\Facades\DB::listen(function ($q) use (&$terbaca) {
            if (str_contains($q->sql, 'pemeriksaan_kas') && str_starts_with(strtolower(trim($q->sql)), 'select')) {
                $terbaca++;
            }
        });

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}")->assertOk();

        $this->assertCount(1, $res->json('data'), 'Hanya unit usaha UJT yang boleh muncul.');
        $this->assertLessThan(3, $terbaca, 'Daftar sumber harus disaring di database, bukan menarik seluruh tabel kas.');
    }

    public function test_periode_mencakup_bulan_plan_dan_dua_bulan_ke_belakang(): void
    {
        // Yang di dalam jendela: bulan plan sendiri, 1 bulan lalu, 2 bulan lalu.
        // Yang di luar: 3 bulan lalu, bulan depan, dan tahun lalu.
        foreach ([
            '2026-08-28' => true,   // 1 bulan sebelum
            '2026-07-01' => true,   // 2 bulan sebelum
            '2026-06-30' => false,  // 3 bulan sebelum
            '2026-10-02' => false,  // bulan berikutnya
            '2025-09-04' => false,  // tahun lalu
        ] as $tgl => $masuk) {
            $lain = $this->plan("SPT-{$tgl}", 'SO UJT', 'Audit Full SO', $tgl);
            PemeriksaanKas::query()->create([
                'plan_audit_id' => $lain->id, 'cabang' => $lain->cabang,
                'nama_pos' => 'Pemeriksaan Kas', 'detail_json' => $this->isiKas(),
            ]);
        }

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}")->assertOk();

        $this->assertSame('Juli–September 2026', $res->json('periode'));
        $noSpt = array_column($res->json('data'), 'noSpt');
        sort($noSpt);
        $this->assertSame(['0459/01/09/2026/SPT-IAT', 'SPT-2026-07-01', 'SPT-2026-08-28'], $noSpt);
    }

    public function test_pemeriksaan_bulan_lalu_tetap_muncul_untuk_plan_bulan_ini(): void
    {
        // Yang dikhawatirkan: SO diperiksa bulan 9, plan CSC-nya bulan 10.
        $this->csc->update(['tgl_plan' => '2026-10-05']);

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}")->assertOk();

        $this->assertSame('Agustus–Oktober 2026', $res->json('periode'));
        $this->assertCount(1, $res->json('data'), 'Pemeriksaan bulan 9 harus tetap bisa disalin ke plan bulan 10.');
        $this->assertSame($this->so->no_spt, $res->json('data.0.noSpt'));
    }

    public function test_periode_lain_bisa_ditampilkan_kalau_periodenya_kosong(): void
    {
        // Arahnya cuma mundur: sumber yang plannya JAUH lebih baru tidak ikut.
        $this->so->update(['tgl_plan' => '2027-01-04']);

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}")->assertOk();
        $this->assertSame([], $res->json('data'), 'Periode lain tidak dicampur begitu saja.');
        $this->assertSame(1, $res->json('diPeriodeLain'), 'Tapi auditor diberi tahu ada di periode lain.');

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}&periode=semua")->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertNull($res->json('periode'));
    }

    public function test_plan_tanpa_tanggal_menampilkan_semua_periode(): void
    {
        $this->csc->update(['tgl_plan' => null]);

        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->csc->id}")->assertOk();

        $this->assertNull($res->json('periode'));
        $this->assertCount(1, $res->json('data'), 'Tanpa tanggal plan, jangan sampai daftarnya jadi kosong.');
    }

    public function test_plan_sendiri_tidak_ikut_jadi_pilihan(): void
    {
        $res = $this->getJson("/api/audit-detail/kas/sumber-salin?plan_audit_id={$this->so->id}")->assertOk();

        $this->assertSame([], $res->json('data'));
    }

    // ── Menyalin ─────────────────────────────────────────────────────────────

    public function test_salin_memindahkan_seluruh_isi_termasuk_register_blanko(): void
    {
        $this->auditorTerisi($this->csc);

        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertOk();

        $kas = PemeriksaanKas::query()->where('plan_audit_id', $this->csc->id)->firstOrFail();
        $d   = $kas->detail_json;

        $this->assertCount(2, $d['kas_besar']['penerimaan']);
        $this->assertSame(85_878_832, $d['kas_besar']['saldo_awal']);
        $this->assertCount(1, $d['kas_kecil']['bon']);
        $this->assertCount(1, $d['blanko_h1'], 'Register blanko ikut tersalin.');
        $this->assertSame(86_021_832.0, (float) $kas->saldo_fisik);

        // Identitasnya tetap milik plan tujuan, bukan ikut sumbernya.
        $this->assertSame('CSC UJT', $kas->cabang);
        $this->assertSame($this->csc->no_spt, $kas->no_spt);
        $this->assertSame('Audit Full CSC', $kas->jenis_audit);
    }

    public function test_hasil_salinan_membawa_jejak_asalnya(): void
    {
        $this->auditorTerisi($this->csc);

        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertOk();

        $jejak = PemeriksaanKas::query()->where('plan_audit_id', $this->csc->id)->firstOrFail()->detail_json['disalin_dari'];

        $this->assertSame($this->so->id, $jejak['plan_audit_id']);
        $this->assertSame('SO UJT', $jejak['cabang']);
        $this->assertSame('auditor1', $jejak['oleh']);
        $this->assertNotEmpty($jejak['pada']);
    }

    public function test_nama_auditor_dan_auditee_ikut_tersalin(): void
    {
        // Tujuannya belum mengisi nama sama sekali — justru itu yang mau disalin.
        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertOk();

        $this->assertDatabaseHas('pemeriksaan_auditors', [
            'plan_audit_id' => $this->csc->id,
            'tool'          => 'kas',
            'nama_auditor'  => 'Auditor Uji',
            'nama_auditee'  => 'Auditee Uji',
        ]);
    }

    public function test_nama_yang_sudah_diketik_di_tujuan_ikut_dikonfirmasi_sebelum_tertimpa(): void
    {
        PemeriksaanAuditor::query()->create([
            'plan_audit_id' => $this->csc->id, 'tool' => 'kas',
            'nama_auditor'  => 'Heri Syahputra', 'nama_auditee' => 'SARI I',
        ]);

        $res = $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertStatus(409);

        $this->assertStringContainsString('Heri Syahputra', implode(' ', $res->json('akanHilang')));

        // Namanya masih utuh sebelum disetujui.
        $this->assertDatabaseHas('pemeriksaan_auditors', [
            'plan_audit_id' => $this->csc->id, 'nama_auditor' => 'Heri Syahputra',
        ]);
    }

    public function test_sumber_yang_belum_mengisi_nama_auditee_ditolak(): void
    {
        PemeriksaanAuditor::query()->where('plan_audit_id', $this->so->id)->delete();

        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertStatus(422);
    }

    public function test_unit_usaha_berbeda_ditolak_server_walau_dipaksa(): void
    {
        $lain = $this->plan('0461/01/09/2026/SPT-IAT', 'SO PRW', 'Audit Full SO');
        $this->auditorTerisi($lain);

        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $lain->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('pemeriksaan_kas', ['plan_audit_id' => $lain->id]);
    }

    public function test_isi_yang_sudah_ada_tidak_ditimpa_diam_diam(): void
    {
        $this->auditorTerisi($this->csc);
        PemeriksaanKas::query()->create([
            'plan_audit_id' => $this->csc->id,
            'cabang'        => $this->csc->cabang,
            'nama_pos'      => 'Pemeriksaan Kas',
            'detail_json'   => ['kas_besar' => ['penerimaan' => [['tanggal' => '2026-09-17', 'keterangan' => 'punya CSC', 'jumlah' => 500]]]],
        ]);

        $res = $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertStatus(409);

        $this->assertTrue($res->json('perluTimpa'));
        $this->assertContains('1 penerimaan', $res->json('akanHilang'));

        // Isinya masih utuh — belum ada yang tertimpa.
        $d = PemeriksaanKas::query()->where('plan_audit_id', $this->csc->id)->firstOrFail()->detail_json;
        $this->assertSame('punya CSC', $d['kas_besar']['penerimaan'][0]['keterangan']);

        // Dengan persetujuan auditor, baru ditimpa.
        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
            'timpa'                => true,
        ])->assertOk();

        $d = PemeriksaanKas::query()->where('plan_audit_id', $this->csc->id)->firstOrFail()->detail_json;
        $this->assertCount(2, $d['kas_besar']['penerimaan']);
        $this->assertSame(1, PemeriksaanKas::query()->where('plan_audit_id', $this->csc->id)->count());
    }

    public function test_sumber_yang_kasnya_kosong_ditolak(): void
    {
        $kosong = $this->plan('0462/01/09/2026/SPT-IAT', 'CSC UJT', 'Audit Full CSC');
        $this->auditorTerisi($this->csc);

        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $kosong->id,
        ])->assertStatus(422);
    }

    public function test_role_yang_hanya_boleh_melihat_tidak_bisa_menyalin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'koordinator']));
        $this->auditorTerisi($this->csc);

        $this->postJson('/api/audit-detail/kas/salin', [
            'plan_audit_id'        => $this->csc->id,
            'sumber_plan_audit_id' => $this->so->id,
        ])->assertStatus(403);
    }

    // ── Bantuan ──────────────────────────────────────────────────────────────

    private function plan(string $noSpt, string $cabang, string $jenis, ?string $tglPlan = '2026-09-04'): PlanAudit
    {
        return PlanAudit::query()->create([
            'no_spt' => $noSpt, 'cabang' => $cabang, 'jenis_audit' => $jenis,
            'status' => 'running', 'tgl_plan' => $tglPlan,
        ]);
    }

    private function auditorTerisi(PlanAudit $plan): void
    {
        PemeriksaanAuditor::query()->firstOrCreate(
            ['plan_audit_id' => $plan->id, 'tool' => 'kas'],
            ['nama_auditor' => 'Auditor Uji', 'nama_auditee' => 'Auditee Uji']
        );
    }

    private function isiKas(): array
    {
        return [
            'kas_besar' => [
                'saldo_awal_tgl' => '2026-09-04',
                'saldo_awal'     => 85_878_832,
                'penerimaan'     => [
                    ['tanggal' => '2026-09-17', 'keterangan' => 'TTS H2 No. CE 005266', 'jumlah' => 143_000],
                    ['tanggal' => '2026-09-17', 'keterangan' => 'TTS H2 No. CE 005267', 'jumlah' => 200_000],
                ],
                'pengeluaran'    => [],
                'keterangan'     => '',
            ],
            'kas_kecil' => [
                'cadangan'   => 1_000_000,
                'bon'        => [['tanggal' => '2026-09-16', 'keterangan' => 'Bon sementara', 'jumlah' => 50_000]],
                'keterangan' => '',
            ],
            'pecahan'   => [['nominal' => 100_000, 'lembar_besar' => 5, 'lembar_kecil' => 0]],
            'blanko_h1' => [['jenis' => 'Faktur Penjualan', 'nomor' => 'CN 072682-072700']],
            'blanko_h2' => [],
        ];
    }
}
