<?php

namespace App\Services\AnalisaZona;

/** Hasil parse 1 file (RKK/ACC/LPK) — siap dipakai import service untuk bulk insert. */
class ParsedFile
{
    /**
     * @param string $jenis 'rkk' | 'acc' | 'lpk'
     * @param array<int, array<string, mixed>> $rows Baris siap insert per tabel tujuan,
     *        dikelompokkan per nama tabel tujuan (mis. ['analisa_rkk_transactions' => [...]]).
     */
    public function __construct(
        public readonly string $jenis,
        public readonly string $unitUsahaCode,
        public readonly ?string $tanggal,
        public readonly string $sourceHash,
        public readonly array $rows,
    ) {
    }

    public function rowCount(): int
    {
        return array_sum(array_map('count', $this->rows));
    }
}
