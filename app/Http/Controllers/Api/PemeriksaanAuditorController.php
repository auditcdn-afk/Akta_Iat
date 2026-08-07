<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanAuditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Nama Auditor & Nama Auditee — satu pasang per (plan_audit_id, tool).
 *
 * Nama Auditor selalu diambil dari user yang sedang login, tidak pernah dari
 * request klien — konsisten dengan cara Saldo di Perlengkapan diturunkan di
 * server, bukan dipercaya dari kiriman klien.
 */
class PemeriksaanAuditorController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'tool'          => ['required', 'string', Rule::in(array_keys(config('audit_tabs', [])))],
        ]);

        $rec = PemeriksaanAuditor::query()
            ->where('plan_audit_id', $data['plan_audit_id'])
            ->where('tool', $data['tool'])
            ->first();

        if ($rec) {
            return response()->json(['ok' => true, 'data' => $rec->toAktaArray()]);
        }

        // Belum pernah disimpan — tampilkan Nama Auditor dari user login
        // sebagai default supaya widget langsung terisi tanpa perlu klik apa
        // pun, tapi nama_auditee tetap kosong sampai auditor mengisinya.
        return response()->json([
            'ok'   => true,
            'data' => [
                'id'          => null,
                'planAuditId' => (int) $data['plan_audit_id'],
                'tool'        => $data['tool'],
                'namaAuditor' => $request->user()?->display_name,
                'namaAuditee' => null,
                'updatedAt'   => null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'tool'          => ['required', 'string', Rule::in(array_keys(config('audit_tabs', [])))],
            'nama_auditee'  => ['required', 'string', 'max:150'],
        ]);

        $user = $request->user();

        $rec = PemeriksaanAuditor::query()->updateOrCreate(
            ['plan_audit_id' => $data['plan_audit_id'], 'tool' => $data['tool']],
            [
                // Nama Auditor tidak pernah diambil dari request — selalu dari
                // akun yang sedang login, apa pun yang dikirim klien.
                'nama_auditor' => $user?->display_name,
                'nama_auditee' => $data['nama_auditee'],
                'updated_by'   => $user?->username,
                'created_by'   => $user?->username,
            ]
        );

        return response()->json([
            'ok'      => true,
            'message' => 'Nama Auditor & Auditee tersimpan.',
            'data'    => $rec->toAktaArray(),
        ]);
    }
}
