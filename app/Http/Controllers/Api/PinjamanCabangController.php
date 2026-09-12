<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditTask;
use App\Models\PinjamanCabang;
use App\Services\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PinjamanCabangController extends Controller
{
    /**
     * Daftar pinjaman satu PLAN (bukan satu task).
     *
     * Pinjaman tercatat pada audit_task_id, sementara satu plan punya satu task
     * per petugas. Kalau daftarnya dibatasi ke satu task, pengajuan yang dibuat
     * dari task Kepala Tim tidak terlihat dari task anggota tim — dan tidak
     * terlihat lagi begitu task-nya selesai lalu disembunyikan dari daftar.
     * Auditor jadi tidak tahu pinjamannya sudah diajukan atau belum, dan
     * berisiko mengajukan dua kali. Jadi audit_task_id diperluas ke seluruh
     * task pada plan yang sama.
     */
    public function index(Request $request): JsonResponse
    {
        $taskId = $request->query('audit_task_id');
        $planId = $request->query('plan_audit_id');

        if (! $planId && $taskId) {
            $planId = AuditTask::whereKey($taskId)->value('plan_audit_id');
        }

        $query = PinjamanCabang::query();

        if ($planId) {
            $query->whereIn('audit_task_id', AuditTask::where('plan_audit_id', $planId)->select('id'));
        } elseif ($taskId) {
            // Task lepas tanpa plan: tetap per task.
            $query->where('audit_task_id', $taskId);
        } else {
            return response()->json(['data' => []]);
        }

        $rows = $query->orderByDesc('created_at')->get()->map(fn($p) => $p->toAktaArray());

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
            'cabang_realisasi'=> $this->normalkanCabangRealisasi($request->input('cabang_realisasi', [])),
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

    /**
     * Cabang realisasi dikirim form lewat FormData sebagai teks JSON (mis.
     * '["SO ARK"]'), sementara kolomnya di-cast 'array'. Tanpa diurai dulu,
     * yang tersimpan adalah teks JSON yang ter-encode dua kali — bukan daftar —
     * dan setiap pemakainya (daftar riwayat di form, memo PDF) pecah saat
     * mencoba menggabungkannya.
     *
     * @return array<int,string>
     */
    private function normalkanCabangRealisasi(mixed $nilai): array
    {
        if (is_string($nilai)) {
            $decoded = json_decode($nilai, true);
            $nilai = is_array($decoded) ? $decoded : ($nilai !== '' ? [$nilai] : []);
        }

        return array_values(array_filter(
            array_map(fn($v) => is_string($v) ? trim($v) : $v, (array) ($nilai ?? [])),
            fn($v) => is_string($v) && $v !== ''
        ));
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

        if ($newStatus === 'rejected') {
            // Pengajuan yang ditolak harus KEMBALI ke meja pengajunya, bukan
            // berhenti diam-diam: task-nya dibuka lagi kalau sudah ditandai
            // selesai (task selesai disembunyikan dari daftar auditor, jadi
            // pengajuannya tidak akan pernah bisa dibuka untuk diperbaiki),
            // lalu pengajunya diberi tahu.
            $this->bukaKembaliTask($pinjaman, $who);
            NotificationDispatcher::notifyPinjamanRejected($pinjaman->fresh(), trim((string) $note) ?: null, $role);
        }

        return response()->json(['message' => 'Status pinjaman diperbarui ke: ' . $newStatus, 'data' => $pinjaman->fresh()->toAktaArray()]);
    }

    /**
     * Perbaiki pengajuan yang DITOLAK lalu ajukan ulang dari tahap awal.
     *
     * Sebelum ini 'rejected' adalah jalan buntu: PinjamanCabang::nextStatus()
     * mengembalikan null untuk status itu, tidak ada endpoint edit sama sekali,
     * dan satu-satunya jalan keluar adalah reset manual oleh admin. Auditor yang
     * pengajuannya ditolak hanya bisa mengajukan berkas baru yang isinya sama.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pinjaman = PinjamanCabang::findOrFail($id);
        $user     = $request->user();
        $who      = $user?->username ?? $user?->email;

        if ($pinjaman->status !== 'rejected') {
            return response()->json([
                'message' => 'Hanya pengajuan yang ditolak yang bisa diperbaiki. Pengajuan yang sedang berjalan harus ditolak dulu oleh pemegang tahapnya.',
            ], 422);
        }

        // Admin boleh memperbaiki pengajuan siapa pun (jalur koreksi). Selain
        // admin, hanya pengajunya sendiri.
        $milikSendiri = $who !== null && in_array($pinjaman->created_by, array_filter([$user?->username, $user?->email]), true);

        if (! $user?->isAdmin() && ! $milikSendiri) {
            return response()->json(['message' => 'Hanya pengaju pinjaman ini atau admin yang bisa memperbaikinya.'], 403);
        }

        $isi = [
            'no_spd'     => $request->input('no_spd', $pinjaman->no_spd),
            'catatan'    => $request->input('catatan', $pinjaman->catatan),
            'nominal'    => $request->input('nominal', $pinjaman->nominal),
            'terbilang'  => $request->input('terbilang', $pinjaman->terbilang),
            'departemen' => $request->input('departemen', $pinjaman->departemen),
        ];

        // Jenis (BPK/BPB) menentukan alur persetujuannya, jadi tidak ikut
        // berubah — kalau salah jenis, ajukan berkas baru.
        if ($request->has('cabang_realisasi')) {
            $isi['cabang_realisasi'] = $this->normalkanCabangRealisasi($request->input('cabang_realisasi', []));
        }

        if ($request->hasFile('bukti_file')) {
            $isi['bukti_file'] = $request->file('bukti_file')->store('pinjaman/bukti', 'public');
        }

        $flow      = $pinjaman->jenis === 'BPK' ? PinjamanCabang::FLOW_BPK : PinjamanCabang::FLOW_BPB;
        $approvals = $pinjaman->approvals ?? [];
        $approvals[] = [
            'role'   => $user?->role ?? 'auditor',
            'user'   => $who,
            'action' => 'resubmit',
            'note'   => trim((string) $request->input('note', '')) ?: 'Diperbaiki lalu diajukan ulang',
            'at'     => now()->toDateTimeString(),
        ];

        $pinjaman->update([
            ...$isi,
            'status'     => $flow[0],
            'approvals'  => $approvals,
            'updated_by' => $who,
        ]);

        NotificationDispatcher::resolveRejection(PinjamanCabang::class, $pinjaman->id);

        return response()->json([
            'message' => 'Pengajuan ' . $pinjaman->jenis . ' diperbaiki dan diajukan ulang.',
            'data'    => $pinjaman->fresh()->toAktaArray(),
        ]);
    }

    /**
     * Task yang sudah ditandai selesai disembunyikan dari daftar auditor
     * (lihat AuditTaskController::index). Kalau pengajuan pinjamannya ditolak
     * setelah itu, tidak ada lagi pintu untuk membukanya — jadi task-nya
     * dikembalikan ke "sedang berjalan".
     */
    private function bukaKembaliTask(PinjamanCabang $pinjaman, ?string $who): void
    {
        AuditTask::whereKey($pinjaman->audit_task_id)
            ->where('status', 'done')
            ->update(['status' => 'in_progress', 'updated_by' => $who ?: 'system']);
    }

    public function show(int $id): JsonResponse
    {
        $pinjaman = PinjamanCabang::findOrFail($id);
        return response()->json(['data' => $pinjaman->toAktaArray()]);
    }

    // Admin: reset status pinjaman ke tahap tertentu
    public function adminResetStatus(Request $request, int $id): JsonResponse
    {
        $pinjaman = PinjamanCabang::findOrFail($id);

        $validStatuses = $pinjaman->jenis === 'BPK'
            ? PinjamanCabang::FLOW_BPK
            : PinjamanCabang::FLOW_BPB;

        $request->validate([
            'status' => ['required', 'in:' . implode(',', $validStatuses)],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        $oldStatus = $pinjaman->status;
        $newStatus = $request->input('status');
        $alasan    = trim((string) $request->input('alasan', '')) ?: 'Koreksi admin';
        $who       = $request->user()?->username ?? 'admin';

        $approvals   = $pinjaman->approvals ?? [];
        $approvals[] = [
            'role'   => 'admin',
            'user'   => $who,
            'action' => 'admin_reset',
            'note'   => "Koreksi: {$oldStatus} → {$newStatus}. {$alasan}",
            'at'     => now()->toDateTimeString(),
        ];

        $pinjaman->update([
            'status'     => $newStatus,
            'approvals'  => $approvals,
            'updated_by' => $who,
        ]);

        return response()->json([
            'message' => "Status pinjaman diubah dari [{$oldStatus}] ke [{$newStatus}].",
            'data'    => $pinjaman->fresh()->toAktaArray(),
        ]);
    }
}
