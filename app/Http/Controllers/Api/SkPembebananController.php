<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditTask;
use App\Models\PlanAudit;
use App\Models\SkPembebanan;
use App\Models\SuratKeputusan;
use App\Services\BirokrasiResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkPembebananController extends Controller
{
    // Rincian pembebanan per jenis unit usaha.
    private const KATEGORI_H1 = [
        'Baterai', 'Helm', 'Buser', 'Kaca Spion', 'Toolset', 'License Plate',
        'Safety Tools', 'Matprom', 'MT', 'HGA', 'Cek Fisik', 'Buser', 'Lain-lain',
    ];

    private const KATEGORI_H2_WHS = [
        'Sparepart', 'MT', 'TP Oli', 'RSA', 'Lain-lain',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = SkPembebanan::query()->with(['suratKeputusan', 'planAudit'])->latest('id');

        if ($request->filled('surat_keputusan_id')) {
            $query->where('surat_keputusan_id', $request->query('surat_keputusan_id'));
        }

        if ($request->filled('plan_audit_id')) {
            $query->where('plan_audit_id', $request->query('plan_audit_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    // GET /api/sk-pembebanan/rekap?tahun[]=&bulan[]=&jenis_unit[]=&status[]=
    // Rekap beban SK untuk grafik: total per bulan, per unit usaha, dan per status.
    public function rekap(Request $request): JsonResponse
    {
        $tahunInput = $request->query('tahun');
        $tahunList = collect(is_array($tahunInput) ? $tahunInput : array_filter([$tahunInput]))
            ->map(fn($t) => (int) $t)
            ->filter(fn($t) => $t >= 2000 && $t <= 2100)
            ->unique()->sort()->values();

        $bulanInput = $request->query('bulan');
        $bulanList = collect(is_array($bulanInput) ? $bulanInput : array_filter([$bulanInput]))
            ->map(fn($b) => (int) $b)
            ->filter(fn($b) => $b >= 1 && $b <= 12)
            ->unique()->sort()->values();

        $jenisUnitInput = $request->query('jenis_unit');
        $jenisUnitList = collect(is_array($jenisUnitInput) ? $jenisUnitInput : array_filter([$jenisUnitInput]))
            ->filter()->values();

        $statusInput = $request->query('status');
        $statusList = collect(is_array($statusInput) ? $statusInput : array_filter([$statusInput]))
            ->filter()->values();

        $unitUsahaInput = $request->query('unit_usaha');
        $unitUsahaList = collect(is_array($unitUsahaInput) ? $unitUsahaInput : array_filter([$unitUsahaInput]))
            ->filter()->values();

        $query = SkPembebanan::query()->whereNotNull('tgl_audit');

        if ($tahunList->isNotEmpty()) {
            $query->where(function ($q) use ($tahunList) {
                foreach ($tahunList as $t) {
                    $q->orWhereYear('tgl_audit', $t);
                }
            });
        }
        if ($bulanList->isNotEmpty()) {
            $query->where(function ($q) use ($bulanList) {
                foreach ($bulanList as $b) {
                    $q->orWhereMonth('tgl_audit', $b);
                }
            });
        }
        if ($jenisUnitList->isNotEmpty()) {
            $query->whereIn('jenis_unit', $jenisUnitList);
        }
        if ($statusList->isNotEmpty()) {
            $query->whereIn('status', $statusList);
        }
        if ($unitUsahaList->isNotEmpty()) {
            $query->whereIn('unit_usaha', $unitUsahaList);
        }

        $rows = $query->get(['unit_usaha', 'jenis_unit', 'status', 'total_pembebanan', 'tgl_audit', 'personil']);

        $tahunOptions = SkPembebanan::query()
            ->whereNotNull('tgl_audit')
            ->pluck('tgl_audit')
            ->map(fn($d) => (int) $d->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();
        if ($tahunOptions->isEmpty()) {
            $tahunOptions = collect([now()->year]);
        }

        $unitUsahaOptions = SkPembebanan::query()
            ->whereNotNull('unit_usaha')
            ->distinct()
            ->orderBy('unit_usaha')
            ->pluck('unit_usaha')
            ->filter()
            ->values();

        $byBulan = $rows
            ->groupBy(fn($r) => optional($r->tgl_audit)->format('Y-m'))
            ->map(fn($group, $key) => [
                'bulan' => $key,
                'total' => (float) $group->sum('total_pembebanan'),
                'jumlahSk' => $group->count(),
            ])
            ->sortBy('bulan')
            ->values();

        $byUnit = $rows
            ->groupBy('unit_usaha')
            ->map(fn($group, $key) => [
                'unitUsaha' => $key ?: '(Tanpa Unit)',
                'jenisUnit' => $group->first()->jenis_unit,
                'total' => (float) $group->sum('total_pembebanan'),
                'jumlahSk' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $byJenisUnit = $rows
            ->groupBy(fn($r) => $r->jenis_unit ?: 'lainnya')
            ->map(fn($group, $key) => [
                'jenisUnit' => $key,
                'total' => (float) $group->sum('total_pembebanan'),
                'jumlahSk' => $group->count(),
            ])
            ->values();

        $byTahun = $rows
            ->groupBy(fn($r) => optional($r->tgl_audit)->format('Y'))
            ->map(fn($group, $key) => [
                'tahun' => $key,
                'total' => (float) $group->sum('total_pembebanan'),
                'jumlahSk' => $group->count(),
            ])
            ->sortBy('tahun')
            ->values();

        // Ratakan semua rincian personil (nama, jabatan, kategori item) dari
        // seluruh SK yang lolos filter, untuk agregasi per item/personil/jabatan.
        $allRincian = collect();
        $allPersonil = collect();
        foreach ($rows as $row) {
            foreach (($row->personil ?? []) as $p) {
                $allPersonil->push([
                    'nama' => $p['nama'] ?? '(Tanpa Nama)',
                    'jabatan' => $p['jabatan'] ?? '-',
                    'subtotal' => (float) ($p['subtotal'] ?? 0),
                ]);
                foreach (($p['rincian'] ?? []) as $r) {
                    $allRincian->push([
                        'kategori' => $r['kategori'] ?? '(Lainnya)',
                        'nilai' => (float) ($r['nilai'] ?? 0),
                    ]);
                }
            }
        }

        $byItemPembebanan = $allRincian
            ->groupBy('kategori')
            ->map(fn($group, $key) => [
                'kategori' => $key,
                'total' => (float) $group->sum('nilai'),
                'jumlah' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $byPersonil = $allPersonil
            ->groupBy(fn($p) => $p['nama'] . '|' . $p['jabatan'])
            ->map(fn($group) => [
                'nama' => $group->first()['nama'],
                'jabatan' => $group->first()['jabatan'],
                'total' => (float) $group->sum('subtotal'),
                'jumlahKasus' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $byJabatan = $allPersonil
            ->groupBy(fn($p) => $p['jabatan'] ?: '-')
            ->map(fn($group, $key) => [
                'jabatan' => $key,
                'total' => (float) $group->sum('subtotal'),
                'jumlahKasus' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'ok' => true,
            'tahunOptions' => $tahunOptions,
            'unitUsahaOptions' => $unitUsahaOptions,
            'stats' => [
                'totalBeban' => (float) $rows->sum('total_pembebanan'),
                'totalFinal' => (float) $rows->where('status', 'final')->sum('total_pembebanan'),
                'totalDraft' => (float) $rows->where('status', 'draft')->sum('total_pembebanan'),
                'jumlahFinal' => $rows->where('status', 'final')->count(),
                'jumlahDraft' => $rows->where('status', 'draft')->count(),
            ],
            'byBulan' => $byBulan,
            'byUnit' => $byUnit,
            'byJenisUnit' => $byJenisUnit,
            'byTahun' => $byTahun,
            'byItemPembebanan' => $byItemPembebanan,
            'byPersonil' => $byPersonil,
            'byJabatan' => $byJabatan,
        ]);
    }

    public function show(SkPembebanan $skPembebanan): JsonResponse
    {
        return response()->json(['data' => $skPembebanan->load(['suratKeputusan', 'planAudit'])]);
    }

    // Mengembalikan jenis unit (h1 / h2_whs) dan daftar kategori rincian pembebanan
    // berdasarkan unit_usaha, agar form frontend bisa menampilkan pilihan yang sesuai.
    public function kategori(Request $request): JsonResponse
    {
        $unitUsaha = trim((string) $request->query('unit_usaha', ''));
        $jenis = $this->classifyUnit($unitUsaha);

        return response()->json([
            'jenis' => $jenis,
            'kategori' => $jenis === 'h1' ? self::KATEGORI_H1 : self::KATEGORI_H2_WHS,
            'tgl_audit_suggestion' => $this->suggestTglAudit($request->query('plan_audit_id')),
        ]);
    }

    // Tanggal mulai auditor melaksanakan audit (dari AuditTask::started_at),
    // fallback ke tgl_mulai plan bila belum ada task yang dimulai.
    private function suggestTglAudit($planAuditId): ?string
    {
        if (empty($planAuditId)) {
            return null;
        }

        $startedAt = AuditTask::query()
            ->where('plan_audit_id', $planAuditId)
            ->whereNotNull('started_at')
            ->min('started_at');

        if ($startedAt) {
            return substr($startedAt, 0, 10);
        }

        $plan = PlanAudit::query()->find($planAuditId);

        return $plan?->tgl_mulai ? $plan->tgl_mulai->format('Y-m-d') : null;
    }

    // Simpan satu personil sekaligus (append ke SkPembebanan milik SK yang sama).
    // Header (tgl_audit, no_sk, unit_usaha, pimpinan_so, pimpinan_csc) diperbarui setiap kali,
    // sehingga auditor bisa menyimpan personil satu per satu tanpa kehilangan data header.
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && in_array($user->role, ['admin', 'auditor'], true),
            403,
            'Hanya admin/auditor yang boleh membuat pembebanan SK.'
        );

        $data = $request->validate([
            'surat_keputusan_id' => ['required', 'integer', 'exists:surat_keputusan,id'],
            'plan_audit_id' => ['nullable', 'integer', 'exists:plan_audits,id'],
            'tgl_audit' => ['nullable', 'date'],
            'no_sk' => ['nullable', 'string', 'max:120'],
            'unit_usaha' => ['nullable', 'string', 'max:150'],
            'pimpinan_so' => ['nullable', 'string', 'max:150'],
            'pimpinan_csc' => ['nullable', 'string', 'max:150'],
            'personil' => ['required', 'array'],
            'personil.nama' => ['required', 'string', 'max:150'],
            'personil.jabatan' => ['nullable', 'string', 'max:150'],
            'personil.rincian' => ['required', 'array', 'min:1'],
            'personil.rincian.*.kategori' => ['required', 'string', 'max:100'],
            'personil.rincian.*.nilai' => ['required', 'numeric', 'min:0'],
        ]);

        $sk = SuratKeputusan::query()->findOrFail($data['surat_keputusan_id']);
        $jenis = $this->classifyUnit($data['unit_usaha'] ?? $sk->unit_usaha ?? '');

        $existing = SkPembebanan::query()->where('surat_keputusan_id', $sk->id)->first();
        abort_if($existing?->status === 'final', 422, 'Pembebanan SK sudah final, tidak bisa ditambah personil lagi.');

        $p = $data['personil'];
        $subtotal = array_reduce($p['rincian'], fn($carry, $r) => $carry + (float) $r['nilai'], 0);
        $entry = [
            'nama' => $p['nama'],
            'jabatan' => $p['jabatan'] ?? null,
            'rincian' => array_map(fn($r) => [
                'kategori' => $r['kategori'],
                'nilai' => (float) $r['nilai'],
            ], $p['rincian']),
            'subtotal' => $subtotal,
        ];

        $pembebanan = SkPembebanan::query()->firstOrNew(['surat_keputusan_id' => $sk->id]);
        $personilList = $pembebanan->personil ?? [];
        $personilList[] = $entry;

        $pembebanan->fill([
            'plan_audit_id' => $data['plan_audit_id'] ?? $sk->plan_audit_id,
            'tgl_audit' => $data['tgl_audit'] ?? $pembebanan->tgl_audit,
            'no_sk' => $data['no_sk'] ?? $sk->no_sk,
            'unit_usaha' => $data['unit_usaha'] ?? $sk->unit_usaha,
            'jenis_unit' => $jenis,
            'pimpinan_so' => $data['pimpinan_so'] ?? $pembebanan->pimpinan_so,
            'pimpinan_csc' => $data['pimpinan_csc'] ?? $pembebanan->pimpinan_csc,
            'personil' => $personilList,
            'total_pembebanan' => array_sum(array_column($personilList, 'subtotal')),
            'created_by' => $pembebanan->created_by ?? $user->username,
            'created_by_name' => $pembebanan->created_by_name ?? ($user->display_name ?? $user->name ?? $user->username),
        ]);
        $pembebanan->save();

        return response()->json([
            'message' => 'Personil berhasil ditambahkan ke pembebanan SK.',
            'data' => $pembebanan->load(['suratKeputusan', 'planAudit']),
        ], 201);
    }

    // Kunci pembebanan agar tidak bisa ditambah/diubah lagi.
    public function finalize(Request $request, SkPembebanan $skPembebanan): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && in_array($user->role, ['admin', 'auditor'], true),
            403,
            'Hanya admin/auditor yang boleh menyelesaikan pembebanan SK.'
        );

        abort_if($skPembebanan->status === 'final', 422, 'Pembebanan SK sudah final.');
        abort_if(empty($skPembebanan->personil), 422, 'Belum ada personil yang diisi.');

        $skPembebanan->status = 'final';
        $skPembebanan->finalized_by = $user->username;
        $skPembebanan->finalized_by_name = $user->display_name ?? $user->name ?? $user->username;
        $skPembebanan->finalized_at = now();
        $skPembebanan->save();

        return response()->json([
            'message' => 'Pembebanan SK berhasil diselesaikan dan dikunci.',
            'data' => $skPembebanan->load(['suratKeputusan', 'planAudit']),
        ]);
    }

    public function destroy(Request $request, SkPembebanan $skPembebanan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403, 'Hanya admin yang boleh menghapus pembebanan SK.');

        $skPembebanan->delete();

        return response()->json(['ok' => true, 'message' => 'Pembebanan SK berhasil dihapus.']);
    }

    private function classifyUnit(string $unitUsaha): string
    {
        $group = BirokrasiResolver::groupFor($unitUsaha) ?? '';

        return str_starts_with($group, 'SO / H1') ? 'h1' : 'h2_whs';
    }
}
