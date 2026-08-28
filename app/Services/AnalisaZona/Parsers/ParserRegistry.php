<?php

namespace App\Services\AnalisaZona\Parsers;

/** Daftar parser yang dikenal — tambah format baru cukup daftarkan di sini. */
class ParserRegistry
{
    /** @var AnalisaFileParserInterface[] */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new RkkParser(),
            new AccParser(),
            new LpkParser(),
        ];
    }

    public function find(string $filename): ?AnalisaFileParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($filename)) {
                return $parser;
            }
        }
        return null;
    }
}
