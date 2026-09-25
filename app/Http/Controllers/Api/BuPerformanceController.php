<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuPerformance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuPerformanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bulan     = $request->query('bulan', '');
        $unitUsaha = $request->query('unit_usaha', '');
        $q         = $request->query('q', '');

        $rows = BuPerformance::query()
            ->when($bulan,     fn($qb) => $qb->where('bulan', $bulan))
            ->when($unitUsaha, fn($qb) => $qb->where('unit_usaha', $unitUsaha))
            ->when($q,         fn($qb) => $qb->where(function ($qb2) use ($q) {
                $qb2->where('unit_usaha', 'like', "%$q%")
                    ->orWhere('auditor', 'like', "%$q%");
            }))
            ->get()
            // Bulan disimpan sebagai TEKS, jadi orderBy('bulan') mengurutkannya
            // menurut abjad -- "April 2026" jatuh sebelum "Januari 2026".
            // Diurutkan di sini memakai kunci kronologis, terbaru lebih dulu.
            ->sortBy(fn($r) => BuPerformance::kunciUrutBulan($r->bulan) . '|' . $r->unit_usaha)
            ->reverse()
            ->values()
            ->map(fn($r) => $r->toAktaArray());

        return response()->json(['data' => $rows, 'total' => $rows->count()]);
    }

    public function bulanOptions(): JsonResponse
    {
        // Terbaru lebih dulu, supaya pilihan pertama = bulan terakhir. Urutannya
        // kronologis, bukan abjad (lihat BuPerformance::kunciUrutBulan).
        $bulans = BuPerformance::select('bulan')->distinct()->pluck('bulan')
            ->sortByDesc(fn($b) => BuPerformance::kunciUrutBulan($b))
            ->values();

        return response()->json(['data' => $bulans]);
    }

    public function save(Request $request): JsonResponse
    {
        $who  = $request->user()?->username ?? $request->user()?->email;
        $rows = $request->input('rows', []);

        if (empty($rows)) {
            return response()->json(['message' => 'Tidak ada data untuk disimpan.'], 422);
        }

        $bulan = $request->input('bulan');
        if (!$bulan) {
            return response()->json(['message' => 'Bulan wajib diisi.'], 422);
        }

        $saved = 0;
        foreach ($rows as $row) {
            $unitUsaha = trim($row['unitUsaha'] ?? $row['unit_usaha'] ?? '');
            if (!$unitUsaha) continue;

            BuPerformance::updateOrCreate(
                ['bulan' => $bulan, 'unit_usaha' => $unitUsaha],
                [
                    'auditor'    => $row['auditor'] ?? null,
                    'penilaian'  => $row['penilaian'] ?? [],
                    'updated_by' => $who,
                    'created_by' => $who,
                ]
            );
            $saved++;
        }

        return response()->json(['message' => "$saved data BU Performance tersimpan.", 'bulan' => $bulan]);
    }

    public function destroy(int $id): JsonResponse
    {
        BuPerformance::findOrFail($id)->delete();
        return response()->json(['message' => 'Data dihapus.']);
    }
}
