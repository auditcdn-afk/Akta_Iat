<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanKas;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PemeriksaanKasController extends Controller
{
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
        $this->ensureCanWrite($request);

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, true);

        $this->fillFromPlan($data, (int) $data['plan_audit_id']);
        $this->calculateSelisih($data);

        $data['created_by'] = $this->userIdentifier($request);

        $kas = PemeriksaanKas::query()->create($data);

        return response()->json([
            'message' => 'Pemeriksaan kas berhasil dibuat.',
            'data' => $kas->load('planAudit'),
        ], 201);
    }

    public function update(Request $request, PemeriksaanKas $pemeriksaanKas): JsonResponse
    {
        $this->ensureCanWrite($request);

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
        $this->ensureCanWrite($request);

        $pemeriksaanKas->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Pemeriksaan kas berhasil dihapus.',
        ]);
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

    private function ensureCanWrite(Request $request): void
    {
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
