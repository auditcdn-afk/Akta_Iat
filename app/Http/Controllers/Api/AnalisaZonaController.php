<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalisaAccConsumer;
use App\Models\AnalisaAccContract;
use App\Models\AnalisaAccReceivable;
use App\Models\AnalisaLpkPenjualan;
use App\Models\AnalisaPosisiKas;
use App\Models\AnalisaRkkTransaction;
use App\Models\AnalisaTemuan;
use App\Models\AnalisaUpload;
use App\Models\AnalisaZonaScore;
use App\Services\AnalisaZona\AnalisaZonaImportService;
use App\Services\AnalisaZona\ZonaRiskScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data di sini berisi PII konsumen (NIK/HP/alamat) — akses seluruh endpoint
 * di controller ini SUDAH digerbang oleh middleware `akta.analisa-zona`
 * (lihat routes/api.php), bukan hanya dicek di sini.
 */
class AnalisaZonaController extends Controller
{
    public function import(Request $request, AnalisaZonaImportService $service): JsonResponse
    {
        $request->validate([
            // Boleh .zip (isinya campur RKK/ACC/LPK/LHPBK) ATAU langsung satu
            // file .pdf LHPBK (dicetak satu-satu per hari, tidak wajib dizip
            // lebih dulu seperti RKK/ACC/LPK).
            'file' => ['required', 'file', 'mimetypes:application/zip,application/x-zip-compressed,application/pdf', 'max:51200'],
        ]);

        $summary = $service->importZip($request->file('file'), $request->user()?->username);

        return response()->json([
            'message' => sprintf(
                '%d file diproses, %d duplikat dilewati, %d file tidak dikenali.',
                count($summary['processed']),
                count($summary['skipped_duplicate']),
                count($summary['unsupported'])
            ),
            'data' => $summary,
        ]);
    }

    /**
     * Upload harian oleh unit usaha sendiri — SEMUA user login boleh panggil
     * ini (tidak digerbang middleware akta.analisa-zona), tapi cuma untuk
     * kode unit usaha yang cocok dengan field unit_usaha akun mereka. Endpoint
     * ini SENGAJA tidak mengembalikan data hasil parse (tidak ada NIK/HP di
     * response) — cuma ringkasan jumlah file/baris.
     */
    public function uploadSelf(Request $request, AnalisaZonaImportService $service): JsonResponse
    {
        $request->validate([
            // Boleh .zip (isinya campur RKK/ACC/LPK/LHPBK) ATAU langsung satu
            // file .pdf LHPBK (dicetak satu-satu per hari, tidak wajib dizip
            // lebih dulu seperti RKK/ACC/LPK).
            'file' => ['required', 'file', 'mimetypes:application/zip,application/x-zip-compressed,application/pdf', 'max:51200'],
        ]);

        $user = $request->user();
        if (!$user?->unit_usaha) {
            return response()->json([
                'message' => 'Akun Anda belum punya Unit Usaha terdaftar — hubungi admin untuk melengkapi data akun sebelum bisa upload.',
            ], 422);
        }

        $summary = $service->importZip($request->file('file'), $user->username, $user->unit_usaha);

        return response()->json([
            'message' => sprintf(
                '%d file diproses, %d duplikat dilewati, %d ditolak (unit usaha tidak cocok), %d file tidak dikenali.',
                count($summary['processed']),
                count($summary['skipped_duplicate']),
                count($summary['rejected_unit_usaha']),
                count($summary['unsupported'])
            ),
            'data' => [
                'processed'           => $summary['processed'],
                'skipped_duplicate'   => $summary['skipped_duplicate'],
                'rejected_unit_usaha' => $summary['rejected_unit_usaha'],
                'unsupported'         => $summary['unsupported'],
            ],
        ]);
    }

    /**
     * Riwayat upload milik unit usaha akun yang login sendiri — supaya
     * mereka bisa lihat file apa saja yang sudah pernah masuk (dan kapan)
     * tanpa perlu coba upload ulang cuma untuk tahu itu sudah pernah
     * diupload atau belum. Terbuka untuk SEMUA user login, sama seperti
     * uploadSelf() — tidak ada data pribadi konsumen di tabel ini, cuma
     * nama file/jenis/tanggal/jumlah baris.
     */
    public function myUploads(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user?->unit_usaha) {
            return response()->json(['data' => []]);
        }

        $expected = AnalisaZonaImportService::normalizeUnitUsahaCode($user->unit_usaha);

        $uploads = AnalisaUpload::query()
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->filter(fn(AnalisaUpload $u) => AnalisaZonaImportService::normalizeUnitUsahaCode($u->unit_usaha_code) === $expected)
            ->values()
            ->take(50);

        return response()->json(['data' => $uploads]);
    }

    public function uploads(Request $request): JsonResponse
    {
        $uploads = AnalisaUpload::query()
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $uploads]);
    }

    public function scores(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        $query = AnalisaZonaScore::query()->orderByDesc('skor_total');
        if ($periode) {
            $query->where('periode', $periode);
        } else {
            $latest = AnalisaZonaScore::max('periode');
            if ($latest) {
                $query->where('periode', $latest);
            }
        }

        return response()->json([
            'data' => $query->get()->map(fn(AnalisaZonaScore $s) => $s->toAktaArray()),
        ]);
    }

    public function recompute(Request $request, ZonaRiskScoreService $service): JsonResponse
    {
        $periode = $request->input('periode') ?: now()->format('Y-m');
        $count   = $service->recompute($periode);
        $temuan  = AnalisaTemuan::where('periode', $periode)->count();

        return response()->json([
            'message' => "Skor {$count} unit usaha untuk periode {$periode} berhasil dihitung ulang — {$temuan} temuan.",
        ]);
    }

    /**
     * Daftar temuan hasil pemeriksaan otomatis. Ini sisi "apa yang harus
     * diperiksa" dari modul ini — skor menentukan cabang mana yang didatangi,
     * temuan menyiapkan agendanya begitu sampai.
     */
    public function temuan(Request $request): JsonResponse
    {
        $request->validate([
            'periode'         => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'unit_usaha_code' => ['nullable', 'string'],
        ]);

        $periode = $request->query('periode') ?: AnalisaTemuan::max('periode');

        $query = AnalisaTemuan::query();
        if ($periode) {
            $query->where('periode', $periode);
        }
        if ($kode = $request->query('unit_usaha_code')) {
            $query->where('unit_usaha_code', $kode);
        }

        // Diurutkan di PHP, bukan SQL: urutan severity-nya menurut tingkat
        // kegentingan (tinggi -> sedang -> rendah), bukan abjad, dan tidak
        // semua database yang mungkin dipakai punya cara yang sama untuk
        // mengurutkan berdasar daftar nilai.
        $items = $query->get()
            ->sortBy([
                fn($a, $b) => (AnalisaTemuan::URUTAN_SEVERITY[$a->severity] ?? 9) <=> (AnalisaTemuan::URUTAN_SEVERITY[$b->severity] ?? 9),
                fn($a, $b) => (float) $b->nominal <=> (float) $a->nominal,
            ])
            ->values();

        return response()->json([
            'data' => $items->map(fn(AnalisaTemuan $t) => $t->toAktaArray()),
            'meta' => [
                'periode'  => $periode,
                'total'    => $items->count(),
                'per_severity' => $items->groupBy('severity')->map->count(),
            ],
        ]);
    }

    /** Drill-down data mentah per unit usaha — tempat NIK/alamat/HP asli ditampilkan. */
    public function drillDown(Request $request): JsonResponse
    {
        $request->validate([
            'unit_usaha_code' => ['required', 'string'],
            'jenis'           => ['required', 'in:rkk,acc-consumers,acc-contracts,acc-receivables,lpk,posisi-kas'],
        ]);

        $unitUsahaCode = $request->query('unit_usaha_code');
        $jenis         = $request->query('jenis');

        $query = match ($jenis) {
            'rkk'             => AnalisaRkkTransaction::query()->where('unit_usaha_code', $unitUsahaCode)->orderByDesc('tanggal'),
            'acc-consumers'   => AnalisaAccConsumer::query()->where('unit_usaha_code', $unitUsahaCode)->orderByDesc('tanggal'),
            'acc-contracts'   => AnalisaAccContract::query()->where('unit_usaha_code', $unitUsahaCode)->orderByDesc('tanggal'),
            'acc-receivables' => AnalisaAccReceivable::query()->where('unit_usaha_code', $unitUsahaCode)->orderByDesc('tanggal_laporan'),
            'lpk'             => AnalisaLpkPenjualan::query()->where('unit_usaha_code', $unitUsahaCode)->orderByDesc('tanggal'),
            'posisi-kas'      => AnalisaPosisiKas::query()->where('unit_usaha_code', $unitUsahaCode)->orderByDesc('tanggal'),
        };

        $items = $query->paginate(50);

        // NIK/no HP konsumen disamarkan di list — tidak perlu ditampilkan
        // penuh hanya untuk melihat daftar, hanya angka/indikator agregatnya.
        if ($jenis === 'acc-consumers') {
            $items->getCollection()->transform(function (AnalisaAccConsumer $c) {
                $arr = $c->toArray();
                $arr['nik'] = $c->nik_masked;
                $arr['no_hp'] = $c->no_hp_masked;
                return $arr;
            });
        }

        return response()->json($items);
    }
}
