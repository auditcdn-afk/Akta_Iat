<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\PemeriksaanKas;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PemeriksaanKasController extends Controller
{
    use RequiresAuditorAuditee;

    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    public function index(Request $request): JsonResponse
    {
        $query = PemeriksaanKas::query()
            ->with('planAudit')
            ->latest('id');

        $planAuditId = $request->query('plan_audit_id')
            ?? $request->query('plan_id')
            ?? $request->query('planId')
            ?? $request->query('planAuditId');

        if ($planAuditId) {
            $query->where('plan_audit_id', $planAuditId);
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('no_spt', 'like', "%{$keyword}%")
                    ->orWhere('cabang', 'like', "%{$keyword}%")
                    ->orWhere('jenis_audit', 'like', "%{$keyword}%")
                    ->orWhere('nama_pos', 'like', "%{$keyword}%")
                    ->orWhere('keterangan', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('has_selisih')) {
            $hasSelisih = filter_var($request->query('has_selisih'), FILTER_VALIDATE_BOOLEAN);

            if ($hasSelisih) {
                $query->where('selisih', '!=', 0);
            }
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(PemeriksaanKas $pemeriksaanKas): JsonResponse
    {
        return response()->json([
            'data' => $pemeriksaanKas->load('planAudit'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, true);

        $this->ensureCanWrite($request, (int) ($data['plan_audit_id'] ?? 0));
        $this->ensureAuditorFilled((int) $data['plan_audit_id'], 'kas');

        $this->fillFromPlan($data, (int) $data['plan_audit_id']);
        $this->calculateSelisih($data);

        $data['created_by'] = $this->userIdentifier($request);

        // updateOrCreate (bukan create) supaya hanya ada 1 pemeriksaan kas
        // per plan — mencegah baris dobel kalau tombol simpan sempat
        // terpicu dua kali sebelum sisi klien tahu record-nya sudah ada
        // (mis. klik ganda), yang sebelumnya membuat laporan PDF mencetak
        // section "Pemeriksaan Kas" berulang.
        $kas = PemeriksaanKas::query()->updateOrCreate(
            ['plan_audit_id' => $data['plan_audit_id']],
            $data
        );

        return response()->json([
            'message' => $kas->wasRecentlyCreated
                ? 'Pemeriksaan kas berhasil dibuat.'
                : 'Pemeriksaan kas berhasil diperbarui.',
            'data' => $kas->load('planAudit'),
        ], $kas->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, PemeriksaanKas $pemeriksaanKas): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $pemeriksaanKas->plan_audit_id);
        $this->ensureAuditorFilled((int) $pemeriksaanKas->plan_audit_id, 'kas');

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, false);

        if (array_key_exists('plan_audit_id', $data) && $data['plan_audit_id']) {
            $this->fillFromPlan($data, (int) $data['plan_audit_id']);
        }

        $base = array_merge($pemeriksaanKas->toArray(), $data);

        $data['saldo_fisik'] = $base['saldo_fisik'] ?? 0;
        $data['saldo_buku'] = $base['saldo_buku'] ?? 0;

        $this->calculateSelisih($data);

        $data['updated_by'] = $this->userIdentifier($request);

        $pemeriksaanKas->fill($data);
        $pemeriksaanKas->save();

        return response()->json([
            'message' => 'Pemeriksaan kas berhasil diperbarui.',
            'data' => $pemeriksaanKas->load('planAudit'),
        ]);
    }

    public function destroy(Request $request, PemeriksaanKas $pemeriksaanKas): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $pemeriksaanKas->plan_audit_id);

        $pemeriksaanKas->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Pemeriksaan kas berhasil dihapus.',
        ]);
    }

    // ── Salin hasil pemeriksaan kas dari unit usaha sejenis ──────────────────
    //
    // SO dan CSC di lokasi yang sama (SO UJT / CSC UJT, SO PRW / CSC PRW)
    // memakai kas yang sama dan dihitung sekali. Sebelumnya auditor mengetik
    // ulang seluruh isinya di plan yang kedua — pekerjaan dobel yang juga jadi
    // sumber salah ketik.
    //
    // Kunci kecocokannya kata TERAKHIR nama unit usaha ("SO UJT" & "CSC UJT"
    // sama-sama UJT). Nama tiga kata ikut terlayani: "WHS Part KIM" -> KIM.

    /** Kata terakhir nama unit usaha, huruf besar semua. Kosong kalau namanya kosong. */
    private function kunciUnitUsaha(?string $cabang): string
    {
        $bersih = trim(preg_replace('/\s+/', ' ', (string) $cabang));
        if ($bersih === '') {
            return '';
        }
        $potong = explode(' ', $bersih);

        return mb_strtoupper(end($potong));
    }

    /** GET /api/audit-detail/kas/sumber-salin?plan_audit_id= */
    public function sumberSalin(Request $request): JsonResponse
    {
        $planId = (int) ($request->query('plan_audit_id') ?? $request->query('planAuditId') ?? 0);
        $plan   = PlanAudit::query()->find($planId);

        if (! $plan) {
            return response()->json(['message' => 'Plan audit tidak ditemukan.'], 404);
        }

        $kunci = $this->kunciUnitUsaha($plan->cabang);
        if ($kunci === '') {
            return response()->json(['data' => [], 'kunci' => null, 'cabang' => $plan->cabang]);
        }

        // Disaring di database dulu, bukan menarik seluruh tabel lalu memilah di
        // PHP: tiap baris kas membawa detail_json yang bisa puluhan KB, dan
        // setelah bertahun-tahun audit jumlahnya ribuan. Yang diambil hanya yang
        // nama unit usahanya BERAKHIR dengan kata kunci — persis aturannya —
        // lalu dicocokkan ulang di PHP supaya "SO SUJT" tidak ikut lolos gara-
        // gara LIKE.
        $kandidat = PemeriksaanKas::query()
            ->with('planAudit:id,no_spt,cabang,jenis_audit,tgl_plan')
            ->where('plan_audit_id', '!=', $planId)
            ->whereHas('planAudit', fn ($q) => $q
                ->where('cabang', $kunci)
                ->orWhere('cabang', 'like', '% ' . $kunci))
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->filter(fn (PemeriksaanKas $kas) => $this->kunciUnitUsaha($kas->planAudit?->cabang ?? $kas->cabang) === $kunci)
            ->map(fn (PemeriksaanKas $kas) => [
                'planAuditId' => $kas->plan_audit_id,
                'noSpt'       => $kas->planAudit?->no_spt ?? $kas->no_spt,
                'cabang'      => $kas->planAudit?->cabang ?? $kas->cabang,
                'jenisAudit'  => $kas->planAudit?->jenis_audit ?? $kas->jenis_audit,
                'tglPlan'     => $this->tanggalRingkas($kas->planAudit?->tgl_plan),
                'saldoFisik'  => (float) $kas->saldo_fisik,
                'saldoBuku'   => (float) $kas->saldo_buku,
                'selisih'     => (float) $kas->selisih,
                'ringkas'     => $this->ringkasIsi($kas->detail_json ?? []),
                'diperbarui'  => $kas->updated_at?->toDateTimeString(),
                'olehSiapa'   => $kas->updated_by ?? $kas->created_by,
            ])
            ->values();

        return response()->json(['data' => $kandidat, 'kunci' => $kunci, 'cabang' => $plan->cabang]);
    }

    /** POST /api/audit-detail/kas/salin */
    public function salin(Request $request): JsonResponse
    {
        $data = Validator::make($this->normalizePayload($request), [
            'plan_audit_id'        => ['required', 'integer', 'exists:plan_audits,id'],
            'sumber_plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id', 'different:plan_audit_id'],
            'timpa'                => ['nullable', 'boolean'],
        ])->validate();

        $planId   = (int) $data['plan_audit_id'];
        $sumberId = (int) $data['sumber_plan_audit_id'];

        $this->ensureCanWrite($request, $planId);
        $this->ensureAuditorFilled($planId, 'kas');

        $tujuan = PlanAudit::query()->findOrFail($planId);
        $sumber = PlanAudit::query()->findOrFail($sumberId);

        // Dijaga di server, bukan cuma di tampilan: menyalin lintas unit usaha
        // yang berbeda tetap ditolak walau permintaannya dibuat di luar layar.
        $kunciTujuan = $this->kunciUnitUsaha($tujuan->cabang);
        $kunciSumber = $this->kunciUnitUsaha($sumber->cabang);
        if ($kunciTujuan === '' || $kunciTujuan !== $kunciSumber) {
            return response()->json([
                'message' => "Tidak bisa menyalin: \"{$sumber->cabang}\" dan \"{$tujuan->cabang}\" bukan unit usaha yang sama. "
                    . 'Hanya unit usaha dengan kata terakhir yang sama yang boleh saling menyalin.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $planId, $sumberId, $tujuan, $sumber, $data) {
            $asal = PemeriksaanKas::query()->where('plan_audit_id', $sumberId)->lockForUpdate()->first();
            if (! $asal) {
                return response()->json([
                    'message' => "Pemeriksaan kas {$sumber->no_spt} belum ada isinya, tidak ada yang bisa disalin.",
                ], 422);
            }

            $adaSekarang = PemeriksaanKas::query()->where('plan_audit_id', $planId)->lockForUpdate()->first();
            $isiSekarang = $this->ringkasIsi($adaSekarang?->detail_json ?? []);

            // Isi yang sudah ada tidak pernah ditimpa diam-diam: auditor harus
            // menyetujui dulu, dan yang akan hilang disebut satu per satu.
            if ($adaSekarang && $isiSekarang !== [] && ! ($data['timpa'] ?? false)) {
                return response()->json([
                    'message'     => 'Pemeriksaan kas di plan ini sudah ada isinya dan akan tertimpa.',
                    'perluTimpa'  => true,
                    'akanHilang'  => $isiSekarang,
                ], 409);
            }

            $detail = $asal->detail_json ?? [];
            // Jejak asal-usul: hasil salinan tidak boleh disangka hitungan fisik
            // yang berdiri sendiri waktu direview.
            $detail['disalin_dari'] = [
                'plan_audit_id' => $sumberId,
                'no_spt'        => $sumber->no_spt,
                'cabang'        => $sumber->cabang,
                'oleh'          => $this->userIdentifier($request),
                'pada'          => now()->toDateTimeString(),
            ];

            $kas = PemeriksaanKas::query()->updateOrCreate(
                ['plan_audit_id' => $planId],
                [
                    // Identitas tetap milik plan tujuan; yang disalin isinya saja.
                    'no_spt'      => $tujuan->no_spt,
                    'cabang'      => $tujuan->cabang,
                    'jenis_audit' => $tujuan->jenis_audit,
                    'nama_pos'    => $asal->nama_pos ?: 'Pemeriksaan Kas',
                    'saldo_fisik' => $asal->saldo_fisik,
                    'saldo_buku'  => $asal->saldo_buku,
                    'selisih'     => $asal->selisih,
                    'keterangan'  => $asal->keterangan,
                    'detail_json' => $detail,
                    'updated_by'  => $this->userIdentifier($request),
                ]
            );
            if (! $kas->created_by) {
                $kas->update(['created_by' => $this->userIdentifier($request)]);
            }

            return response()->json([
                'message'  => "Hasil pemeriksaan kas {$sumber->no_spt} • {$sumber->cabang} berhasil disalin ke sini.",
                'disalin'  => $this->ringkasIsi($detail),
                'data'     => $kas->fresh()->load('planAudit'),
            ]);
        });
    }

    /** Tanggal apa adanya untuk ditampilkan — tanpa jam, apa pun bentuk simpanannya. */
    private function tanggalRingkas(mixed $tgl): ?string
    {
        if (! $tgl) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($tgl)->toDateString();
        } catch (\Throwable) {
            return (string) $tgl;
        }
    }

    /**
     * Ringkasan isi detail_json dalam kalimat pendek — dipakai untuk memilih
     * sumber salinan dan untuk menyebut apa yang akan hilang saat menimpa.
     *
     * @return array<int,string>
     */
    private function ringkasIsi(array $detail): array
    {
        $hitung = [
            'penerimaan'     => count($detail['kas_besar']['penerimaan'] ?? []),
            'pengeluaran'    => count($detail['kas_besar']['pengeluaran'] ?? []),
            'bon kas kecil'  => count($detail['kas_kecil']['bon'] ?? []),
            'baris pecahan'  => count(array_filter(
                $detail['pecahan'] ?? [],
                fn ($p) => ((int) ($p['lembar_besar'] ?? 0)) > 0 || ((int) ($p['lembar_kecil'] ?? 0)) > 0
            )),
            'register blanko H1' => count($detail['blanko_h1'] ?? []),
            'register blanko H2' => count($detail['blanko_h2'] ?? []),
        ];

        $ringkas = [];
        foreach ($hitung as $nama => $n) {
            if ($n > 0) {
                $ringkas[] = "{$n} {$nama}";
            }
        }

        // Saldo yang terisi tanpa baris apa pun tetap "ada isinya".
        if ($ringkas === []) {
            $saldo = (float) ($detail['kas_besar']['saldo_awal'] ?? 0) + (float) ($detail['kas_kecil']['cadangan'] ?? 0);
            if ($saldo != 0.0) {
                $ringkas[] = 'saldo awal / cadangan yang sudah diisi';
            }
        }

        return $ringkas;
    }

    public function summary(Request $request): JsonResponse
    {
        $query = PemeriksaanKas::query();

        $planAuditId = $request->query('plan_audit_id')
            ?? $request->query('plan_id')
            ?? $request->query('planId')
            ?? $request->query('planAuditId');

        if ($planAuditId) {
            $query->where('plan_audit_id', $planAuditId);
        }

        $items = $query->get();

        return response()->json([
            'data' => [
                'total_pos' => $items->count(),
                'total_saldo_fisik' => round((float) $items->sum('saldo_fisik'), 2),
                'total_saldo_buku' => round((float) $items->sum('saldo_buku'), 2),
                'total_selisih' => round((float) $items->sum('selisih'), 2),
                'pos_selisih' => $items->filter(fn($item) => (float) $item->selisih !== 0.0)->count(),
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    private function validatePayload(array $payload, bool $isCreate): array
    {
        return Validator::make($payload, [
            'plan_audit_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:plan_audits,id'],
            'no_spt' => ['nullable', 'string', 'max:80'],
            'cabang' => ['nullable', 'string', 'max:150'],
            'jenis_audit' => ['nullable', 'string', 'max:80'],
            'nama_pos' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:200'],
            'saldo_fisik' => ['nullable', 'numeric'],
            'saldo_buku' => ['nullable', 'numeric'],
            'keterangan' => ['nullable', 'string'],
            'detail_json' => ['nullable', 'array'],
        ])->validate();
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->all();

        $aliases = [
            'planId' => 'plan_audit_id',
            'plan_id' => 'plan_audit_id',
            'planAuditId' => 'plan_audit_id',
            'noSpt' => 'no_spt',
            'jenisAudit' => 'jenis_audit',
            'namaPos' => 'nama_pos',
            'saldoFisik' => 'saldo_fisik',
            'saldoBuku' => 'saldo_buku',
            'detailJson' => 'detail_json',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && !array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function fillFromPlan(array &$data, int $planAuditId): void
    {
        $plan = PlanAudit::query()->find($planAuditId);

        if (!$plan) {
            return;
        }

        $data['no_spt'] = $data['no_spt']
            ?? $plan->getAttribute('no_spt')
            ?? $plan->getAttribute('noSpt')
            ?? null;

        $data['cabang'] = $data['cabang']
            ?? $plan->getAttribute('cabang')
            ?? null;

        $data['jenis_audit'] = $data['jenis_audit']
            ?? $plan->getAttribute('jenis_audit')
            ?? $plan->getAttribute('jenisAudit')
            ?? null;
    }

    private function calculateSelisih(array &$data): void
    {
        $saldoFisik = (float) ($data['saldo_fisik'] ?? 0);
        $saldoBuku = (float) ($data['saldo_buku'] ?? 0);

        $data['saldo_fisik'] = $saldoFisik;
        $data['saldo_buku'] = $saldoBuku;
        $data['selisih'] = round($saldoFisik - $saldoBuku, 2);
    }

    private function ensureCanWrite(Request $request, int $planAuditId = 0): void
    {
        // Plan Audit Mandiri/Sertijab: unit usaha yang bersangkutan sendiri yang mengisi.
        if ($planAuditId && PlanAudit::query()->where('id', $planAuditId)->where('is_mandiri', true)->exists()) {
            return;
        }

        abort_unless(
            in_array($this->role($request), $this->writeRoles, true),
            403,
            'Role tidak diizinkan mengubah pemeriksaan kas.'
        );
    }

    private function role(Request $request): string
    {
        return strtolower((string) ($request->user()?->role ?? ''));
    }

    private function userIdentifier(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return $user->username
            ?? $user->email
            ?? $user->id
            ?? null;
    }
}
