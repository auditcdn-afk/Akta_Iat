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
        'unit-usaha'   => ['unit_usaha', 'wilayah', 'jenis'],
        'grading'      => ['id_grading', 'jenis', 'wilayah', 'nama_pemeriksaan', 'hasil_pemeriksaan', 'nilai', 'bknf', 'pknf', 'bkf', 'pkf', 'bnknf', 'pnknf', 'bnkf', 'pnkf'],
        'mt'           => ['nomor', 'nama_singkat', '_x', 'nama_peralatan', 'kode_peralatan'],
        'het'          => ['kode', 'nama', 'harga_het'],
    ];

    private static array $searchCols = [
        'harga-smh'    => ['kode_model', 'nama_smh'],
        'plafon'       => ['kode', 'nama', 'keterangan'],
        'perlengkapan' => ['kode', 'nama', 'keterangan'],
        'unit-usaha'   => ['unit_usaha', 'wilayah', 'jenis'],
        'grading'      => ['id_grading', 'jenis', 'wilayah', 'nama_pemeriksaan', 'hasil_pemeriksaan'],
        'mt'           => ['nama_singkat', 'nama_peralatan', 'kode_peralatan', 'jenis'],
        'het'          => ['kode', 'nama'],
    ];

    // Unique key(s) per type — used for upsert during import
    private static array $uniqueKeys = [
        'harga-smh'    => ['kode_model'],
        'plafon'       => ['kode'],
        'perlengkapan' => ['kode'],
        'unit-usaha'   => ['unit_usaha', 'wilayah'],
        'grading'      => ['id_grading'],
        'mt'           => ['kode_peralatan', 'jenis'],
        'het'          => ['kode'],
    ];

    private function resolveModel(string $type): string
    {
        $class = self::$typeMap[$type] ?? null;
        if (!$class) {
            abort(404, "Tipe database '{$type}' tidak ditemukan.");
        }
        return $class;
    }

    /**
     * Daftar lengkap unit usaha (tanpa paginasi) untuk dropdown.
     * Dipakai di form Pengguna agar wilayah terisi otomatis saat unit usaha dipilih.
     */
    public function unitUsahaOptions(): JsonResponse
    {
        $rows = DbUnitUsaha::query()
            ->orderBy('unit_usaha')
            ->get(['id', 'unit_usaha', 'wilayah', 'jenis']);

        return response()->json([
            'ok'   => true,
            'data' => $rows->map(fn(DbUnitUsaha $r) => [
                'id'        => $r->id,
                'unitUsaha' => $r->unit_usaha,
                'wilayah'   => $r->wilayah,
                'jenis'     => $r->jenis,
            ]),
        ]);
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

        // Flatten multi-group horizontal layout:
        // If the file has more columns than expected, check if it's a uniform repeat
        // (e.g. 10 cols / 5 colCount = 2 groups). Otherwise just take first colCount cols.
        $colCount  = count($cols);
        $flatRows  = [];
        $sampleLen = !empty($rows) ? max(array_map('count', array_slice($rows, 0, 5))) : 0;
        $groups    = 1;

        // Perlengkapan uses variable-width rows (item per column), skip group expansion
        if ($type !== 'perlengkapan' && $sampleLen > $colCount && $sampleLen % $colCount === 0) {
            $groups = intdiv($sampleLen, $colCount);
        }

        foreach ($rows as $row) {
            for ($g = 0; $g < $groups; $g++) {
                $slice = array_slice($row, $g * $colCount, $colCount);
                $trimmed = array_map('trim', $slice);
                // Skip slice if first two cells are both empty (likely padding/separator group)
                if (($trimmed[0] ?? '') === '' && ($trimmed[1] ?? '') === '') {
                    continue;
                }
                if (!empty(array_filter($trimmed))) {
                    $flatRows[] = $slice;
                }
            }
        }

        $imported  = 0;
        $mtJenis   = ($type === 'mt') ? trim((string) $request->input('mt_jenis', '')) : null;

        DB::transaction(function () use ($flatRows, $model, $cols, $type, $mtJenis, &$imported) {
            foreach ($flatRows as $row) {
                if (empty(array_filter(array_map('trim', $row)))) {
                    continue;
                }

                // Special handling for perlengkapan: old XLS format is
                // TIPE(col0) | NOSIN(col1) | Item1 | Item2 | ... (many columns)
                // Map to: kode=NOSIN, nama=TIPE, keterangan=joined items
                if ($type === 'perlengkapan') {
                    $tipe  = trim((string) ($row[0] ?? ''));
                    $nosin = trim((string) ($row[1] ?? ''));
                    if ($nosin === '') continue;
                    // cols 2+ are perlengkapan items
                    $items = array_filter(array_map('trim', array_slice($row, 2)), fn($v) => $v !== '');
                    $model::updateOrCreate(
                        ['kode' => strtoupper($nosin)],
                        ['nama' => $tipe ?: null, 'keterangan' => implode(', ', $items) ?: null]
                    );
                    $imported++;
                    continue;
                }

                $data = [];
                $instance = new $model();
                $casts = $instance->getCasts();
                foreach ($cols as $i => $col) {
                    $val = isset($row[$i]) ? trim((string) $row[$i]) : null;
                    if ($val === '') {
                        $val = null;
                    } elseif (isset($casts[$col]) && in_array($casts[$col], ['float', 'double', 'decimal', 'integer', 'int'])) {
                        $val = is_numeric($val) ? $val : null;
                    }
                    $data[$col] = $val;
                }
                // Override jenis for MT import
                if ($mtJenis !== null && $mtJenis !== '') {
                    $data['jenis'] = $mtJenis;
                }
                $uniqueKeys = self::$uniqueKeys[$type] ?? [];
                $keyData    = array_intersect_key($data, array_flip($uniqueKeys));
                $valData    = array_diff_key($data, array_flip($uniqueKeys));
                if (!empty($keyData)) {
                    $model::updateOrCreate($keyData, $valData);
                } else {
                    $model::create($data);
                }
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
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException('Tidak dapat membaca file Excel.');
        }

        // Find End of Central Directory record
        $eocdPos = strrpos($data, "\x50\x4b\x05\x06");
        if ($eocdPos === false) {
            throw new \RuntimeException('File Excel tidak valid atau rusak.');
        }

        $cdOffset = unpack('V', substr($data, $eocdPos + 16, 4))[1];
        $cdSize   = unpack('V', substr($data, $eocdPos + 12, 4))[1];

        // Parse Central Directory to build file index
        $files = [];
        $pos   = $cdOffset;
        while ($pos < $cdOffset + $cdSize) {
            if (substr($data, $pos, 4) !== "\x50\x4b\x01\x02") break;
            $compMethod  = unpack('v', substr($data, $pos + 10, 2))[1];
            $compSize    = unpack('V', substr($data, $pos + 20, 4))[1];
            $uncompSize  = unpack('V', substr($data, $pos + 24, 4))[1];
            $fnLen       = unpack('v', substr($data, $pos + 28, 2))[1];
            $extraLen    = unpack('v', substr($data, $pos + 30, 2))[1];
            $commentLen  = unpack('v', substr($data, $pos + 32, 2))[1];
            $localOffset = unpack('V', substr($data, $pos + 42, 4))[1];
            $fileName    = substr($data, $pos + 46, $fnLen);
            $files[$fileName] = compact('compMethod', 'compSize', 'uncompSize', 'localOffset');
            $pos += 46 + $fnLen + $extraLen + $commentLen;
        }

        $extract = function (string $name) use ($data, $files): ?string {
            if (!isset($files[$name])) return null;
            $info  = $files[$name];
            $lPos  = $info['localOffset'];
            if (substr($data, $lPos, 4) !== "\x50\x4b\x03\x04") return null;
            $fnLen    = unpack('v', substr($data, $lPos + 26, 2))[1];
            $extraLen = unpack('v', substr($data, $lPos + 28, 2))[1];
            $raw = substr($data, $lPos + 30 + $fnLen + $extraLen, $info['compSize']);
            return $info['compMethod'] === 0 ? $raw : gzinflate($raw);
        };

        // Parse shared strings
        $sharedStrings = [];
        $ssContent = $extract('xl/sharedStrings.xml');
        if ($ssContent !== null) {
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

        $sheetContent = $extract('xl/worksheets/sheet1.xml');
        if ($sheetContent === null) {
            throw new \RuntimeException('Sheet tidak ditemukan di dalam file Excel.');
        }

        $sheet = simplexml_load_string($sheetContent);
        $rows  = [];

        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            $lastIdx = -1;

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
