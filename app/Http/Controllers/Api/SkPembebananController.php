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
            'personil' => ['required', 'array', 'min:1'],
            'personil.*.nama' => ['required', 'string', 'max:150'],
            'personil.*.jabatan' => ['nullable', 'string', 'max:150'],
            'personil.*.rincian' => ['required', 'array', 'min:1'],
            'personil.*.rincian.*.kategori' => ['required', 'string', 'max:100'],
            'personil.*.rincian.*.nilai' => ['required', 'numeric', 'min:0'],
        ]);

        $sk = SuratKeputusan::query()->findOrFail($data['surat_keputusan_id']);

        $jenis = $this->classifyUnit($data['unit_usaha'] ?? $sk->unit_usaha ?? '');

        $totalKeseluruhan = 0;
        $personil = array_map(function ($p) use (&$totalKeseluruhan) {
            $subtotal = array_reduce($p['rincian'], fn($carry, $r) => $carry + (float) $r['nilai'], 0);
            $totalKeseluruhan += $subtotal;

            return [
                'nama' => $p['nama'],
                'jabatan' => $p['jabatan'] ?? null,
                'rincian' => array_map(fn($r) => [
                    'kategori' => $r['kategori'],
                    'nilai' => (float) $r['nilai'],
                ], $p['rincian']),
                'subtotal' => $subtotal,
            ];
        }, $data['personil']);

        $pembebanan = SkPembebanan::query()->create([
            'surat_keputusan_id' => $sk->id,
            'plan_audit_id' => $data['plan_audit_id'] ?? $sk->plan_audit_id,
            'tgl_audit' => $data['tgl_audit'] ?? null,
            'no_sk' => $data['no_sk'] ?? $sk->no_sk,
            'unit_usaha' => $data['unit_usaha'] ?? $sk->unit_usaha,
            'jenis_unit' => $jenis,
            'pimpinan_so' => $data['pimpinan_so'] ?? null,
            'pimpinan_csc' => $data['pimpinan_csc'] ?? null,
            'personil' => $personil,
            'total_pembebanan' => $totalKeseluruhan,
            'created_by' => $user->username,
            'created_by_name' => $user->display_name ?? $user->name ?? $user->username,
        ]);

        return response()->json([
            'message' => 'Pembebanan SK berhasil disimpan.',
            'data' => $pembebanan->load(['suratKeputusan', 'planAudit']),
        ], 201);
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
