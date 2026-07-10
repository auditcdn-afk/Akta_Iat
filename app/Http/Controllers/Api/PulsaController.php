<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PulsaPeriode;
use App\Models\PulsaRealisasi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PulsaController extends Controller
{
    private const OPERATORS = ['Telkomsel', 'Indosat', 'XL', 'Axis', 'Tri', 'Smartfren', 'By.U'];

    private const ROLE_LABEL = [
        'admin' => 'Admin',
        'manajer' => 'Manajer Audit',
        'auditor' => 'Auditor',
        'koordinator' => 'Koordinator',
        'coo' => 'Chief Operating Officer',
        'afd' => 'AFD',
    ];

    public function userOptions(): JsonResponse
    {
        $users = User::query()
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get()
            ->map(fn(User $u) => [
                'username' => $u->username,
                'nama' => $u->display_name ?: $u->name,
                'jabatan' => self::ROLE_LABEL[$u->role] ?? ucfirst((string) $u->role),
            ]);

        return response()->json(['data' => $users]);
    }

    public function operatorOptions(): JsonResponse
    {
        return response()->json(['data' => self::OPERATORS]);
    }

    public function index(Request $request): JsonResponse
    {
        $tahun = (int) $request->query('tahun', now()->year);
        $bulan = (int) $request->query('bulan', now()->month);

        $rows = PulsaRealisasi::query()
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->orderBy('tanggal')
            ->get()
            ->map(fn(PulsaRealisasi $r) => $r->toAktaArray());

        $periode = PulsaPeriode::query()->where('tahun', $tahun)->where('bulan', $bulan)->first();
        $isCurrentPeriode = $tahun === now()->year && $bulan === now()->month;

        return response()->json([
            'data' => $rows,
            'periode' => [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'status' => $periode?->status ?? ($isCurrentPeriode ? 'terbuka' : 'tertutup'),
                'isDefaultOpen' => !$periode && $isCurrentPeriode,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['nullable', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['required', 'date'],
            'nomor_hp' => ['required', 'string', 'max:30'],
            'operator' => ['nullable', 'string', Rule::in(self::OPERATORS)],
            'nominal' => ['required', 'numeric', 'min:0'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $tanggal = \Carbon\Carbon::parse($data['tanggal']);
        $tahun = (int) $tanggal->format('Y');
        $bulan = (int) $tanggal->format('n');

        $this->ensurePeriodeTerbuka($request, $tahun, $bulan);

        $file = $request->file('file');
        $path = $file->store('pulsa', 'public');
        $bonFile = [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => Storage::url($path),
        ];

        $realisasi = PulsaRealisasi::query()->create([
            'username' => $data['username'] ?? null,
            'nama' => $data['nama'],
            'jabatan' => $data['jabatan'] ?? null,
            'tanggal' => $data['tanggal'],
            'nomor_hp' => $data['nomor_hp'],
            'operator' => $data['operator'] ?? null,
            'nominal' => $data['nominal'],
            'bon_file' => $bonFile,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'status' => 'diajukan',
            'created_by' => $user?->username,
        ]);

        return response()->json([
            'message' => 'Realisasi pulsa berhasil disimpan.',
            'data' => $realisasi->toAktaArray(),
        ], 201);
    }

    public function destroy(Request $request, PulsaRealisasi $pulsaRealisasi): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->role === 'admin' || $user->username === $pulsaRealisasi->created_by),
            403,
            'Anda tidak berwenang menghapus data ini.'
        );

        $this->ensurePeriodeTerbuka($request, $pulsaRealisasi->tahun, $pulsaRealisasi->bulan);

        $pulsaRealisasi->delete();

        return response()->json(['message' => 'Realisasi pulsa berhasil dihapus.']);
    }

    // Admin membuka/menutup periode input realisasi pulsa untuk tahun+bulan tertentu.
    public function togglePeriode(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->role === 'admin', 403, 'Hanya admin yang boleh mengubah status periode.');

        $data = $request->validate([
            'tahun' => ['required', 'integer'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'status' => ['required', 'string', Rule::in(['terbuka', 'tertutup'])],
        ]);

        $periode = PulsaPeriode::query()->updateOrCreate(
            ['tahun' => $data['tahun'], 'bulan' => $data['bulan']],
            [
                'status' => $data['status'],
                'closed_by' => $data['status'] === 'tertutup' ? $user->username : null,
                'closed_at' => $data['status'] === 'tertutup' ? now() : null,
            ]
        );

        return response()->json([
            'message' => $data['status'] === 'tertutup' ? 'Periode ditutup.' : 'Periode dibuka kembali.',
            'data' => $periode->toAktaArray(),
        ]);
    }

    private function ensurePeriodeTerbuka(Request $request, int $tahun, int $bulan): void
    {
        $user = $request->user();
        if ($user?->role === 'admin') {
            return;
        }

        $periode = PulsaPeriode::query()->where('tahun', $tahun)->where('bulan', $bulan)->first();
        $isCurrentPeriode = $tahun === now()->year && $bulan === now()->month;
        $status = $periode?->status ?? ($isCurrentPeriode ? 'terbuka' : 'tertutup');

        abort_if($status === 'tertutup', 422, 'Periode ini sudah ditutup, tidak bisa menambah/mengubah data.');
    }
}
