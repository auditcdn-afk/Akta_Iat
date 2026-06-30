<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PinjamanCabang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PinjamanCabangController extends Controller
{
    // Daftar pinjaman per task
    public function index(Request $request): JsonResponse
    {
        $taskId = $request->query('audit_task_id');
        $rows   = PinjamanCabang::where('audit_task_id', $taskId)
            ->orderByDesc('created_at')->get()
            ->map(fn($p) => $p->toAktaArray());
        return response()->json(['data' => $rows]);
    }

    // Buat pinjaman baru
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'audit_task_id' => ['required', 'integer'],
            'jenis'         => ['required', 'in:BPK,BPB'],
        ]);

        $who = $request->user()?->username ?? $request->user()?->email;

        $buktiPath = null;
        if ($request->hasFile('bukti_file')) {
            $buktiPath = $request->file('bukti_file')->store('pinjaman/bukti', 'public');
        }

        $pinjaman = PinjamanCabang::create([
            'audit_task_id'   => $request->input('audit_task_id'),
            'jenis'           => $request->input('jenis'),
            'cabang_realisasi'=> $request->input('cabang_realisasi', []),
            'no_spd'          => $request->input('no_spd'),
            'catatan'         => $request->input('catatan'),
            'nominal'         => $request->input('nominal', 0),
            'terbilang'       => $request->input('terbilang'),
            'bukti_file'      => $buktiPath,
            'departemen'      => $request->input('departemen', 'Finance'),
            'status'          => 'pending_koordinator',
            'approvals'       => [[
                'role'   => 'auditor',
                'user'   => $who,
                'action' => 'submit',
                'note'   => 'Pengajuan pinjaman ' . $request->input('jenis'),
                'at'     => now()->toDateTimeString(),
            ]],
            'created_by'      => $who,
            'updated_by'      => $who,
        ]);

        return response()->json(['message' => 'Pinjaman ' . $pinjaman->jenis . ' diajukan.', 'data' => $pinjaman->toAktaArray()], 201);
    }

    // Approve / reject oleh role yang berwenang
    public function approve(Request $request, int $id): JsonResponse
    {
        $pinjaman = PinjamanCabang::findOrFail($id);
        $action   = $request->input('action', 'approve'); // approve | reject
        $note     = $request->input('note', '');
        $who      = $request->user()?->username ?? $request->user()?->email;
        $role     = $request->user()?->role ?? 'auditor';

        $approvals   = $pinjaman->approvals ?? [];
        $approvals[] = [
            'role'   => $role,
            'user'   => $who,
            'action' => $action,
            'note'   => $note,
            'at'     => now()->toDateTimeString(),
        ];

        $newStatus = $action === 'reject' ? 'rejected' : ($pinjaman->nextStatus() ?? 'approved');

        $pinjaman->update([
            'status'     => $newStatus,
            'approvals'  => $approvals,
            'updated_by' => $who,
        ]);

        return response()->json(['message' => 'Status pinjaman diperbarui ke: ' . $newStatus, 'data' => $pinjaman->fresh()->toAktaArray()]);
    }

    public function show(int $id): JsonResponse
    {
        $pinjaman = PinjamanCabang::findOrFail($id);
        return response()->json(['data' => $pinjaman->toAktaArray()]);
    }
}
