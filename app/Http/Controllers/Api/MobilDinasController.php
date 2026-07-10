<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobilDinasPengajuan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MobilDinasController extends Controller
{
    private const CREATE_ROLES = ['admin', 'auditor'];
    private const DECIDE_ROLES = ['admin', 'manajer'];
    private const COMPLETE_ROLES = ['admin', 'mrr'];

    public function picOptions(): JsonResponse
    {
        $users = User::query()
            ->where('is_disabled', false)
            ->where('role', 'auditor')
            ->orderBy('name')
            ->get()
            ->map(fn(User $u) => [
                'username' => $u->username,
                'nama' => $u->display_name ?: $u->name,
            ]);

        return response()->json(['data' => $users]);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = MobilDinasPengajuan::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(MobilDinasPengajuan $m) => $m->toAktaArray());

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, self::CREATE_ROLES, true), 403, 'Anda tidak berwenang mengajukan mobil dinas.');

        $data = $request->validate([
            'supir_request' => ['required', 'string', 'max:150'],
            'tanggal_berangkat' => ['required', 'date'],
            'tanggal_pulang' => ['required', 'date', 'after_or_equal:tanggal_berangkat'],
            'pic_mobil' => ['required', 'array', 'min:1'],
            'pic_mobil.*' => ['string', 'max:150'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $file->store('mobil-dinas', 'public');
        $spdFile = [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => Storage::url($path),
        ];

        $pengajuan = MobilDinasPengajuan::query()->create([
            'supir_request' => $data['supir_request'],
            'tanggal_berangkat' => $data['tanggal_berangkat'],
            'tanggal_pulang' => $data['tanggal_pulang'],
            'pic_mobil' => $data['pic_mobil'],
            'spd_file' => $spdFile,
            'status' => 'diajukan',
            'created_by' => $user->username,
        ]);

        return response()->json([
            'message' => 'Pengajuan mobil dinas berhasil dikirim.',
            'data' => $pengajuan->toAktaArray(),
        ], 201);
    }

    public function decide(Request $request, MobilDinasPengajuan $mobilDinasPengajuan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, self::DECIDE_ROLES, true), 403, 'Anda tidak berwenang menyetujui/menolak pengajuan ini.');
        abort_unless($mobilDinasPengajuan->status === 'diajukan', 422, 'Pengajuan ini sudah diproses sebelumnya.');

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['disetujui', 'ditolak'])],
            'catatan_manajer' => ['nullable', 'string', 'max:1000'],
        ]);

        $mobilDinasPengajuan->status = $data['status'];
        $mobilDinasPengajuan->catatan_manajer = $data['catatan_manajer'] ?? null;
        $mobilDinasPengajuan->approved_by = $user->username;
        $mobilDinasPengajuan->approved_at = now();
        $mobilDinasPengajuan->save();

        return response()->json([
            'message' => $data['status'] === 'disetujui' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.',
            'data' => $mobilDinasPengajuan->toAktaArray(),
        ]);
    }

    public function complete(Request $request, MobilDinasPengajuan $mobilDinasPengajuan): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($user->role, self::COMPLETE_ROLES, true), 403, 'Hanya MRR yang boleh melengkapi form ini.');
        abort_unless($mobilDinasPengajuan->status === 'disetujui', 422, 'Pengajuan ini belum disetujui manajer audit.');

        $data = $request->validate([
            'nama_supir' => ['required', 'string', 'max:150'],
            'plat_mobil' => ['required', 'string', 'max:30'],
            'jenis_mobil' => ['required', 'string', 'max:100'],
        ]);

        $mobilDinasPengajuan->nama_supir = $data['nama_supir'];
        $mobilDinasPengajuan->plat_mobil = $data['plat_mobil'];
        $mobilDinasPengajuan->jenis_mobil = $data['jenis_mobil'];
        $mobilDinasPengajuan->status = 'selesai';
        $mobilDinasPengajuan->completed_by = $user->username;
        $mobilDinasPengajuan->completed_at = now();
        $mobilDinasPengajuan->save();

        return response()->json([
            'message' => 'Form mobil dinas berhasil dikirim.',
            'data' => $mobilDinasPengajuan->toAktaArray(),
        ]);
    }

    public function destroy(Request $request, MobilDinasPengajuan $mobilDinasPengajuan): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->role === 'admin' || $user->username === $mobilDinasPengajuan->created_by),
            403,
            'Anda tidak berwenang menghapus pengajuan ini.'
        );
        abort_unless($mobilDinasPengajuan->status === 'diajukan', 422, 'Pengajuan yang sudah diproses tidak bisa dihapus.');

        $mobilDinasPengajuan->delete();

        return response()->json(['message' => 'Pengajuan berhasil dihapus.']);
    }
}
