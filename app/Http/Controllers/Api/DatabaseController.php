<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DbGrading;
use App\Models\DbHargaSmh;
use App\Models\DbHet;
use App\Models\DbMt;
use App\Models\DbPerlengkapan;
use App\Models\DbPlafon;
use App\Models\DbUnitUsaha;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class DatabaseController extends Controller
{
    private static array $typeMap = [
        'harga-smh'    => DbHargaSmh::class,
        'plafon'       => DbPlafon::class,
        'perlengkapan' => DbPerlengkapan::class,
        'unit-usaha'   => DbUnitUsaha::class,
        'grading'      => DbGrading::class,
        'mt'           => DbMt::class,
        'het'          => DbHet::class,
    ];

    private static array $colMap = [
        'harga-smh'    => ['kode_model', 'nama_smh', 'harga'],
        'plafon'       => ['kode', 'nama', 'nilai', 'keterangan'],
        'perlengkapan' => ['kode', 'nama', 'satuan', 'qty', 'keterangan'],
        'unit-usaha'   => ['kode', 'nama', 'alamat', 'keterangan'],
        'grading'      => ['kode', 'nama', 'grade', 'nilai_min', 'nilai_max', 'keterangan'],
        'mt'           => ['kode', 'nama', 'jenis', 'periode', 'keterangan'],
        'het'          => ['kode', 'nama', 'harga_het', 'satuan', 'keterangan'],
    ];

    private static array $searchCols = [
        'harga-smh'    => ['kode_model', 'nama_smh'],
        'plafon'       => ['kode', 'nama', 'keterangan'],
        'perlengkapan' => ['kode', 'nama', 'satuan', 'keterangan'],
        'unit-usaha'   => ['kode', 'nama', 'alamat', 'keterangan'],
        'grading'      => ['kode', 'nama', 'grade', 'keterangan'],
        'mt'           => ['kode', 'nama', 'jenis', 'periode', 'keterangan'],
        'het'          => ['kode', 'nama', 'satuan', 'keterangan'],
    ];

    private function resolveModel(string $type): string
    {
        $class = self::$typeMap[$type] ?? null;
        if (!$class) {
            abort(404, "Tipe database '{$type}' tidak ditemukan.");
        }
        return $class;
    }

    public function index(Request $request, string $type): JsonResponse
    {
        $model = $this->resolveModel($type);
        $q = trim((string) $request->query('q', ''));
        $searchCols = self::$searchCols[$type] ?? [];

        $query = $model::query()->orderBy('id');

        if ($q !== '' && !empty($searchCols)) {
            $query->where(function ($sub) use ($q, $searchCols) {
                foreach ($searchCols as $col) {
                    $sub->orWhere($col, 'like', "%{$q}%");
                }
            });
        }

        $page = (int) $request->query('page', 1);
        $perPage = 100;
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'ok'          => true,
            'data'        => collect($paginated->items())->map(fn(Model $m) => $m->toAktaArray()),
            'total'       => $paginated->total(),
            'perPage'     => $paginated->perPage(),
            'currentPage' => $paginated->currentPage(),
            'lastPage'    => $paginated->lastPage(),
        ]);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $model = $this->resolveModel($type);
        $cols  = self::$colMap[$type] ?? [];

        $record = $model::create($request->only($cols));

        return response()->json([
            'ok'      => true,
            'message' => 'Data berhasil ditambahkan.',
            'data'    => $record->toAktaArray(),
        ], 201);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $model  = $this->resolveModel($type);
        $cols   = self::$colMap[$type] ?? [];
        $record = $model::findOrFail($id);
        $record->update($request->only($cols));

        return response()->json([
            'ok'      => true,
            'message' => 'Data berhasil diperbarui.',
            'data'    => $record->fresh()->toAktaArray(),
        ]);
    }

    public function destroy(string $type, int $id): JsonResponse
    {
        $model = $this->resolveModel($type);
        $model::findOrFail($id)->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Data berhasil dihapus.',
        ]);
    }

    public function truncate(Request $request, string $type, ActivityLogger $logger): JsonResponse
    {
        $model = $this->resolveModel($type);
        $model::truncate();

        $logger->write($request, 'DB_TRUNCATE', $type, "Hapus semua data database: {$type}", $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Semua data berhasil dihapus.',
        ]);
    }

    public function import(Request $request, string $type, ActivityLogger $logger): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $file      = $request->file('file');
        $ext       = strtolower($file->getClientOriginalExtension());
        $model     = $this->resolveModel($type);
        $cols      = self::$colMap[$type] ?? [];

        $rows = match ($ext) {
            'csv', 'txt' => $this->parseCsv($file->getRealPath()),
            'xlsx'       => $this->parseXlsx($file->getRealPath()),
            default      => throw new \InvalidArgumentException("Format file '{$ext}' tidak didukung. Gunakan .xlsx atau .csv."),
        };

        // Remove header row (non-numeric first cell)
        if (!empty($rows)) {
            $first = trim((string) ($rows[0][0] ?? ''));
            if ($first !== '' && !is_numeric($first)) {
                array_shift($rows);
            }
        }

        $imported = 0;

        DB::transaction(function () use ($rows, $model, $cols, &$imported) {
            foreach ($rows as $row) {
                if (empty(array_filter(array_map('trim', $row)))) {
                    continue;
                }
                $data = [];
                foreach ($cols as $i => $col) {
                    $val = isset($row[$i]) ? trim((string) $row[$i]) : null;
                    $data[$col] = $val === '' ? null : $val;
                }
                $model::create($data);
                $imported++;
            }
        });

        $logger->write($request, 'DB_IMPORT', $type, "Import {$imported} data ke database: {$type}", $request->user());

        return response()->json([
            'ok'       => true,
            'message'  => "{$imported} data berhasil diimport.",
            'imported' => $imported,
        ]);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Tidak dapat membaca file CSV.');
        }
        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiters = [',', ';', "\t", '|'];
        $counts = array_map(fn($d) => substr_count($firstLine, $d), $delimiters);
        $delimiter = $delimiters[array_search(max($counts), $counts)];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('File Excel tidak valid atau rusak.');
        }

        // Shared strings
        $sharedStrings = [];
        $ssContent = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssContent !== false) {
            $ss = simplexml_load_string($ssContent);
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r ?? [] as $r) {
                        $text .= (string) $r->t;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetContent = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetContent === false) {
            throw new \RuntimeException('Sheet tidak ditemukan di dalam file Excel.');
        }

        $sheet = simplexml_load_string($sheetContent);
        $rows  = [];

        foreach ($sheet->sheetData->row as $row) {
            $rowData  = [];
            $lastIdx  = -1;

            foreach ($row->c as $cell) {
                preg_match('/^([A-Z]+)/', (string) $cell['r'], $m);
                $colStr = $m[1] ?? 'A';
                $colIdx = 0;
                foreach (str_split($colStr) as $ch) {
                    $colIdx = $colIdx * 26 + (ord($ch) - ord('A') + 1);
                }
                $colIdx--;

                while ($lastIdx < $colIdx - 1) {
                    $rowData[] = '';
                    $lastIdx++;
                }

                $cellType = (string) $cell['t'];
                $value    = (string) ($cell->v ?? '');

                if ($cellType === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($cellType === 'b') {
                    $value = $value ? 'TRUE' : 'FALSE';
                }

                $rowData[] = $value;
                $lastIdx   = $colIdx;
            }

            $rows[] = $rowData;
        }

        return $rows;
    }
}
