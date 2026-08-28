<?php

namespace App\Services\AnalisaZona;

use App\Models\AnalisaUpload;
use App\Services\AnalisaZona\Parsers\ParserRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class AnalisaZonaImportService
{
    public function __construct(private readonly ParserRegistry $registry)
    {
    }

    /** Normalisasi kode unit usaha untuk dibandingkan (buang spasi/simbol, uppercase) — supaya "SO SGL" (field unit_usaha akun) bisa dicocokkan dengan "SOSGL" (kode di dalam file). */
    public static function normalizeUnitUsahaCode(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value) ?? '');
    }

    /**
     * @param string|null $expectedUnitUsahaCode Kalau diisi, file yang kode unit
     *        usahanya (setelah dinormalisasi) TIDAK cocok akan ditolak (masuk ke
     *        `rejected_unit_usaha`), bukan diproses — dipakai saat unit usaha
     *        upload sendiri, supaya 1 akun tidak bisa (sengaja/tidak sengaja)
     *        upload data milik unit usaha lain.
     * @return array{processed: string[], skipped_duplicate: string[], unsupported: string[], rejected_unit_usaha: string[], row_counts: array<string,int>}
     */
    public function importZip(UploadedFile $zip, ?string $uploadedBy, ?string $expectedUnitUsahaCode = null): array
    {
        $tmpDir = storage_path('app/tmp-analisa-zona-' . uniqid());
        mkdir($tmpDir, 0755, true);

        $expectedNormalized = $expectedUnitUsahaCode !== null ? self::normalizeUnitUsahaCode($expectedUnitUsahaCode) : null;

        $summary = [
            'processed'            => [],
            'skipped_duplicate'    => [],
            'unsupported'          => [],
            'rejected_unit_usaha'  => [],
            'row_counts'           => [],
        ];

        try {
            $archive = new ZipArchive();
            if ($archive->open($zip->getRealPath()) !== true) {
                abort(422, 'File zip tidak bisa dibuka / rusak.');
            }
            $archive->extractTo($tmpDir);
            $archive->close();

            $files = $this->listFilesRecursively($tmpDir);

            foreach ($files as $path) {
                $filename = basename($path);
                $parser   = $this->registry->find($filename);

                if (!$parser) {
                    $summary['unsupported'][] = $filename;
                    continue;
                }

                $content = file_get_contents($path);
                $parsed  = $parser->parse($filename, $content);

                if ($parsed->unitUsahaCode === '' || $parsed->tanggal === null || $parsed->tanggal === '') {
                    $summary['unsupported'][] = $filename . ' (gagal baca unit usaha/tanggal)';
                    continue;
                }

                if ($expectedNormalized !== null && self::normalizeUnitUsahaCode($parsed->unitUsahaCode) !== $expectedNormalized) {
                    $summary['rejected_unit_usaha'][] = "{$filename} (kode di file: {$parsed->unitUsahaCode}, akun Anda: {$expectedUnitUsahaCode})";
                    continue;
                }

                $alreadyExists = AnalisaUpload::where('jenis', $parsed->jenis)
                    ->where('source_hash', $parsed->sourceHash)
                    ->exists();

                if ($alreadyExists) {
                    $summary['skipped_duplicate'][] = $filename;
                    continue;
                }

                DB::transaction(function () use ($parsed, $filename, $uploadedBy, &$summary) {
                    $upload = AnalisaUpload::create([
                        'jenis'            => $parsed->jenis,
                        'unit_usaha_code'  => $parsed->unitUsahaCode,
                        'tanggal'          => $parsed->tanggal,
                        'source_hash'      => $parsed->sourceHash,
                        'source_filename'  => $filename,
                        'row_count'        => $parsed->rowCount(),
                        'uploaded_by'      => $uploadedBy,
                    ]);

                    foreach ($parsed->rows as $table => $rows) {
                        if (empty($rows)) {
                            continue;
                        }
                        $now = now();
                        $rows = array_map(function ($row) use ($upload, $now) {
                            $row['upload_id']  = $upload->id;
                            $row['created_at'] = $now;
                            $row['updated_at'] = $now;
                            return $row;
                        }, $rows);

                        // Insert per potongan supaya tidak melebihi batas parameter
                        // SQLite/MySQL untuk single query pada file dengan ratusan baris.
                        foreach (array_chunk($rows, 500) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }

                        $summary['row_counts'][$table] = ($summary['row_counts'][$table] ?? 0) + count($rows);
                    }
                });

                $summary['processed'][] = $filename;
            }
        } finally {
            $this->deleteDirectory($tmpDir);
        }

        return $summary;
    }

    /** @return string[] */
    private function listFilesRecursively(string $dir): array
    {
        $result = [];
        $items  = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $result = array_merge($result, $this->listFilesRecursively($path));
            } else {
                $result[] = $path;
            }
        }
        return $result;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
