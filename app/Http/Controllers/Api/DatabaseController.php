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

        // Remove header row — any row where the first non-empty cell is non-numeric text
        if (!empty($rows)) {
            $first = trim((string) ($rows[0][0] ?? ''));
            if ($first !== '' && !is_numeric($first)) {
                array_shift($rows);
            }
        }

        // Also remove any subsequent rows that look like repeated headers (all-text, no numeric ID)
        $rows = array_values(array_filter($rows, function ($row) {
            $cells = array_filter(array_map('trim', $row));
            if (empty($cells)) return false;
            $firstVal = reset($cells);
            // Skip rows where the first cell is purely alphabetic text (header labels)
            return is_numeric($firstVal) || preg_match('/^\d/', $firstVal);
        }));

        // Flatten multi-group horizontal layout:
        // Some Excel files place multiple groups of columns side-by-side, possibly with
        // separator columns between them. We scan all possible starting offsets to find
        // every block of $colCount consecutive columns that contains data, then deduplicate.
        $colCount  = count($cols);
        $flatRows  = [];
        $sampleLen = !empty($rows) ? max(array_map('count', array_slice($rows, 0, 10))) : 0;

        if ($sampleLen <= $colCount) {
            // Single-group or exact-fit file — use rows directly
            foreach ($rows as $row) {
                if (!empty(array_filter(array_map('trim', array_slice($row, 0, $colCount))))) {
                    $flatRows[] = array_slice($row, 0, $colCount);
                }
            }
        } else {
            // Multi-column file: find all offsets where a $colCount-wide block has data
            // across the majority of data rows (> 30% of non-empty rows).
            $dataRows = array_filter($rows, fn($r) => !empty(array_filter(array_map('trim', $r))));
            $dataCount = count($dataRows);

            $goodOffsets = [];
            for ($offset = 0; $offset + $colCount <= $sampleLen; $offset++) {
                $hits = 0;
                foreach ($dataRows as $row) {
                    $slice = array_slice($row, $offset, $colCount);
                    if (!empty(array_filter(array_map('trim', $slice)))) {
                        $hits++;
                    }
                }
                if ($dataCount > 0 && ($hits / $dataCount) >= 0.3) {
                    $goodOffsets[] = $offset;
                }
            }

            // Merge overlapping offsets: keep only non-overlapping starts separated by >= $colCount
            $usedOffsets = [];
            foreach ($goodOffsets as $off) {
                $overlaps = false;
                foreach ($usedOffsets as $used) {
                    if (abs($off - $used) < $colCount) {
                        $overlaps = true;
                        break;
                    }
                }
                if (!$overlaps) {
                    $usedOffsets[] = $off;
                }
            }

            if (empty($usedOffsets)) {
                $usedOffsets = [0]; // fallback
            }

            foreach ($rows as $row) {
                foreach ($usedOffsets as $offset) {
                    $slice = array_slice($row, $offset, $colCount);
                    if (!empty(array_filter(array_map('trim', $slice)))) {
                        $flatRows[] = $slice;
                    }
                }
            }
        }

        $imported = 0;

        DB::transaction(function () use ($flatRows, $model, $cols, &$imported) {
            foreach ($flatRows as $row) {
                if (empty(array_filter(array_map('trim', $row)))) {
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
