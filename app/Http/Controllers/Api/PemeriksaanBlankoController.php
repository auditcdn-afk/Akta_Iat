<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanBlanko;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Register Blanko yang Belum Digunakan (H1/H2) — satu pasang per
 * (plan_audit_id, tool), sama seperti yang sudah ada di Pemeriksaan Kas,
 * tapi disimpan di tabel bersama supaya bisa dipakai tool lain (SMH, Onhand
 * BPKB) tanpa perlu menambah kolom ke tabel masing-masing tool tersebut.
 */
class PemeriksaanBlankoController extends Controller
{
    private const ALLOWED_TOOLS = ['smh', 'bpkb'];

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'tool'          => ['required', 'string', Rule::in(self::ALLOWED_TOOLS)],
        ]);

        $rec = PemeriksaanBlanko::query()
            ->where('plan_audit_id', $data['plan_audit_id'])
            ->where('tool', $data['tool'])
            ->first();

        if ($rec) {
            return response()->json(['ok' => true, 'data' => $rec->toAktaArray()]);
        }

        return response()->json([
            'ok'   => true,
            'data' => [
                'id'          => null,
                'planAuditId' => (int) $data['plan_audit_id'],
                'tool'        => $data['tool'],
                'blankoH1'    => [],
                'blankoH2'    => [],
                'updatedAt'   => null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_audit_id'      => ['required', 'integer', 'exists:plan_audits,id'],
            'tool'                => ['required', 'string', Rule::in(self::ALLOWED_TOOLS)],
            'blanko_h1'           => ['nullable', 'array'],
            'blanko_h1.*.jenis'   => ['nullable', 'string', 'max:100'],
            'blanko_h1.*.nomor'   => ['nullable', 'string', 'max:100'],
            'blanko_h2'           => ['nullable', 'array'],
            'blanko_h2.*.jenis'   => ['nullable', 'string', 'max:100'],
            'blanko_h2.*.nomor'   => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $rec = PemeriksaanBlanko::query()->updateOrCreate(
            ['plan_audit_id' => $data['plan_audit_id'], 'tool' => $data['tool']],
            [
                'blanko_h1'  => $data['blanko_h1'] ?? [],
                'blanko_h2'  => $data['blanko_h2'] ?? [],
                'updated_by' => $user?->username,
                'created_by' => $user?->username,
            ]
        );

        return response()->json([
            'ok'      => true,
            'message' => 'Register Blanko tersimpan.',
            'data'    => $rec->toAktaArray(),
        ]);
    }
}
