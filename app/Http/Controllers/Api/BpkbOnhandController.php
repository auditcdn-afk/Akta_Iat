<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\BpkbOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BpkbOnhandController extends Controller
{
    use RequiresAuditorAuditee;

    // ── GET /api/audit-detail/bpkb?plan_audit_id= ────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $items  = BpkbOnhandItem::where('plan_audit_id', $planId)->orderBy('no_bpkb')->get();

        $total      = $items->count();
        $reg        = $items->where('jenis', 'REG')->count();
        $kds        = $items->where('jenis', 'KDS')->count();
        $sudahScan  = $items->where('sudah_scan', true)->count();
        $belumScan  = $items->where('sudah_scan', false)->count();
        $reg120     = $items->where('jenis', 'REG')->where('umur', '>', 120)->count();
        $reg120Pct  = $reg > 0 ? round($reg120 / $reg * 100, 2) : 0;

        return response()->json([
            'summary' => compact('total', 'reg', 'kds', 'sudahScan', 'belumScan', 'reg120', 'reg120Pct'),
            'items'   => $items->map->toAktaArray(),
        ]);
    }

    // ── GET /api/audit-detail/bpkb/search?q=&plan_audit_id= ─────────────────

    public function search(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $q      = trim($request->query('q', ''));
        if (strlen($q) < 3) return response()->json(['data' => []]);

        $rows = BpkbOnhandItem::where('plan_audit_id', $planId)
            ->where('no_bpkb', 'like', "%{$q}%")
            ->limit(10)->get();

        return response()->json(['data' => $rows->map->toAktaArray()]);
    }

    // ── POST /api/audit-detail/bpkb/upload ───────────────────────────────────

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'          => 'required|file',
            'plan_audit_id' => 'required|integer|exists:plan_audits,id',
        ]);

        $planId = $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'bpkb');
        $who    = $request->user()?->username ?? $request->user()?->email ?? null;

        $path = $request->file('file')->getRealPath();
        // setReadDataOnly: kita cuma butuh nilai selnya, bukan style/formatnya —
        // membaca style .xls/.xlsx bisa berkali-kali lipat lebih lambat dari
        // sekadar membaca nilainya, terutama untuk file besar dengan banyak
        // formatting warisan template lama.
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        // Deteksi baris header — strip tanda baca sebelum compare
        $headerRow = null;
        $colMap    = [];
        $normalize = fn($v) => preg_replace('/[^A-Z0-9 ]/', '', strtoupper(trim((string)($v ?? ''))));

        foreach ($rows as $ri => $row) {
            foreach ($row as $ci => $cell) {
                $c = $normalize($cell);
                if (str_contains($c, 'NO BPKB') || str_contains($c, 'NOBPKB') || $c === 'BPKB')
                    $colMap['no_bpkb'] = $ci;
                if (str_contains($c, 'NO POLISI') || str_contains($c, 'NOPOL'))
                    $colMap['no_polisi'] = $ci;
                if (str_contains($c, 'TGL TERIMA') || str_contains($c, 'TANGGAL TERIMA') || str_contains($c, 'TGL  TERIMA'))
                    $colMap['tgl_terima'] = $ci;
                if (str_contains($c, 'NAMA PEMILIK') || $c === 'PEMILIK')
                    $colMap['nama_pemilik'] = $ci;
                if (str_contains($c, 'NO MESIN') || str_contains($c, 'NOMESIN'))
                    $colMap['no_mesin'] = $ci;
                if (str_contains($c, 'NO RANGKA') || str_contains($c, 'NORANGKA'))
                    $colMap['no_rangka'] = $ci;
                if ($c === 'JENIS' || $c === 'TYPE')
                    $colMap['jenis'] = $ci;
                if ($c === 'UMUR' || str_contains($c, 'UMUR'))
                    $colMap['umur'] = $ci;
            }
            if (isset($colMap['no_bpkb'])) {
                $headerRow = $ri;
                // Telepon: cari kolom setelah nama_pemilik yang tidak punya header tapi berisi angka
                if (!isset($colMap['no_telepon']) && isset($colMap['nama_pemilik'])) {
                    $colMap['no_telepon'] = $colMap['nama_pemilik'] + 1;
                }
                break;
            }
        }

        if ($headerRow === null) {
            return response()->json(['message' => 'Kolom NO BPKB tidak ditemukan di file Excel.'], 422);
        }

        $toUpsert = [];
        foreach ($rows as $ri => $row) {
            if ($ri <= $headerRow) continue;
            $noBpkb = trim((string)($row[$colMap['no_bpkb']] ?? ''));
            if ($noBpkb === '') continue;

            // Nama pemilik & telepon
            $namaPemilik = trim((string)($row[$colMap['nama_pemilik'] ?? -1] ?? ''));
            $noTelepon   = trim((string)($row[$colMap['no_telepon']   ?? -1] ?? ''));

            // Parse tanggal — support "dd-mm-yyyy", "dd/mm/yyyy", atau Excel serial
            $tglRaw    = $row[$colMap['tgl_terima'] ?? -1] ?? null;
            $tglTerima = null;
            if ($tglRaw) {
                if (is_numeric($tglRaw)) {
                    $tglTerima = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$tglRaw)->format('Y-m-d');
                } elseif (preg_match('/^(\d{2})[-\/](\d{2})[-\/](\d{4})$/', trim((string)$tglRaw), $m)) {
                    $tglTerima = "{$m[3]}-{$m[2]}-{$m[1]}";
                } else {
                    $parsed = date_create((string)$tglRaw);
                    if ($parsed) $tglTerima = $parsed->format('Y-m-d');
                }
            }

            $jenis    = strtoupper(trim((string)($row[$colMap['jenis'] ?? -1] ?? '')));
            $umurRaw  = trim((string)($row[$colMap['umur'] ?? -1] ?? ''));
            $umur     = (int) preg_replace('/[^0-9]/', '', $umurRaw); // strip "4,647" → 4647

            $toUpsert[$noBpkb] = [
                'plan_audit_id' => $planId,
                'no_bpkb'      => $noBpkb,
                'no_polisi'    => trim((string)($row[$colMap['no_polisi']  ?? -1] ?? '')) ?: null,
                'tgl_terima'   => $tglTerima,
                'nama_pemilik' => $namaPemilik ?: null,
                'no_telepon'   => $noTelepon ?: null,
                'no_mesin'     => trim((string)($row[$colMap['no_mesin']   ?? -1] ?? '')) ?: null,
                'no_rangka'    => trim((string)($row[$colMap['no_rangka']  ?? -1] ?? '')) ?: null,
                'jenis'        => in_array($jenis, ['REG', 'KDS']) ? $jenis : null,
                'umur'         => $umur > 0 ? $umur : null,
                'created_by'   => $who,
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        // updateOrCreate() satu-per-satu di dalam loop berarti tiap baris commit
        // (fsync) sendiri-sendiri di SQLite — ratusan baris jadi ratusan disk
        // write terpisah, itu sumber lambatnya import. upsert() per-chunk
        // menjadikannya sedikit statement INSERT ... ON CONFLICT sekaligus.
        // Dikunci per no_bpkb (bukan array_values biasa) supaya no_bpkb dobel di
        // file Excel yang sama tidak membuat upsert() mengeluh baris duplikat.
        foreach (array_chunk(array_values($toUpsert), 200) as $chunk) {
            BpkbOnhandItem::upsert(
                $chunk,
                ['plan_audit_id', 'no_bpkb'],
                ['no_polisi', 'tgl_terima', 'nama_pemilik', 'no_telepon', 'no_mesin', 'no_rangka', 'jenis', 'umur', 'created_by', 'updated_at']
            );
        }
        $saved = count($toUpsert);

        return response()->json(['message' => "{$saved} data BPKB berhasil diimpor."]);
    }

    // ── POST /api/audit-detail/bpkb/scan ─────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $data   = $request->validate([
            'plan_audit_id' => 'required|integer|exists:plan_audits,id',
            'no_bpkb'       => 'required|string|max:100',
            'force'         => 'nullable|boolean',
        ]);
        $planId = $data['plan_audit_id'];
        $noBpkb = trim($data['no_bpkb']);
        $force  = (bool) ($data['force'] ?? false);

        // Dicari SEPERTI YANG DIKIRIM lebih dulu. Sebagian No. BPKB memang
        // memuat spasi beserta huruf di belakangnya ("I-04308002 D"), dan itu
        // bagian dari nomornya -- bukan embel-embel hasil scan.
        $item = $this->cariOnhand($planId, $noBpkb);

        // Baru kalau tidak ketemu, embel-embel hasil scan barcode dibuang
        // ("W1840506-BPKB POLRI 2025" -> "W1840506") lalu dicari sekali lagi.
        if (!$item) {
            $inti = $this->intiNoBpkb($noBpkb);

            if ($inti !== $noBpkb && $item = $this->cariOnhand($planId, $inti)) {
                $noBpkb = $inti;
            }
        }

        if ($item) {
            // Ada di onhand — tandai fisik ada
            $item->update([
                'sudah_scan' => true,
                'keterangan' => 'fisik ada',
                'scan_at'    => now(),
            ]);
            return response()->json(['status' => 'found', 'data' => $item->fresh()->toAktaArray()]);
        }

        // Tidak ada di onhand — sebelum dicatat sebagai fisik diluar onhand,
        // minta konfirmasi dulu (kecuali sudah dikonfirmasi lewat force=true).
        // Tanpa ini, salah ketik/typo yang tidak sengaja cocok dengan nomor
        // lain langsung membuat baris baru "LUAR" tanpa disadari user.
        if (!$force) {
            return response()->json(['status' => 'confirm', 'data' => null, 'noBpkb' => $noBpkb]);
        }

        // Yang tercatat sebagai baris baru adalah nomornya saja, tanpa tulisan
        // lain yang kebetulan ikut terbaca alat scan.
        $noBpkb = $this->bersihkanNoBpkb($noBpkb);

        $item = BpkbOnhandItem::updateOrCreate(
            ['plan_audit_id' => $planId, 'no_bpkb' => $noBpkb],
            [
                'sudah_scan' => true,
                'keterangan' => 'Fisik diluar onhand',
                'scan_at'    => now(),
                'jenis'       => 'LUAR',
            ]
        );
        return response()->json(['status' => 'outside', 'data' => $item->fresh()->toAktaArray()]);
    }

    /**
     * Cari satu baris onhand yang nomornya sama.
     *
     * No. BPKB di data import biasanya berformat "U-04886028", tapi hasil scan
     * barcode fisiknya kadang tidak membawa tanda "-" ("U04886028") — kalau
     * dicocokkan persis, unit yang sebenarnya sama jadi dianggap "di luar
     * onhand". Strip dan spasi karena itu diabaikan di kedua sisi (sama seperti
     * perbaikan No. Mesin/Rangka SMH & No. Part HGP/HGA).
     */
    private function cariOnhand(mixed $planId, string $noBpkb): ?BpkbOnhandItem
    {
        $norm = strtoupper(preg_replace('/[\s\-]+/', '', $noBpkb) ?? '');

        if ($norm === '') {
            return null;
        }

        return BpkbOnhandItem::where('plan_audit_id', $planId)
            ->where(function ($q) use ($noBpkb, $norm) {
                $q->where('no_bpkb', $noBpkb)
                  ->orWhereRaw("UPPER(REPLACE(REPLACE(no_bpkb, '-', ''), ' ', '')) = ?", [$norm]);
            })
            ->first();
    }

    /** Bagian nomor saja: huruf + angka di depan ("W1840506-BPKB POLRI" -> "W1840506"). */
    private function intiNoBpkb(string $noBpkb): string
    {
        return preg_match('/^([A-Za-z]+-?\d+)/', $noBpkb, $m) ? strtoupper($m[1]) : $noBpkb;
    }

    /**
     * Nomor untuk baris "Fisik Diluar On Hand".
     *
     * Alat scan kadang ikut membaca tulisan lain di lembar BPKB ("W1840506-BPKB
     * POLRI 2025"); itu dibuang. Tapi huruf pendek di belakang nomor
     * ("I-04308002 D") memang bagian dari nomornya dan harus ikut tersimpan --
     * membuangnya membuat dua BPKB berbeda tercatat dengan nomor yang sama.
     */
    private function bersihkanNoBpkb(string $noBpkb): string
    {
        $inti = $this->intiNoBpkb($noBpkb);
        $sisa = trim(mb_substr($noBpkb, mb_strlen($inti)));

        if ($sisa === '') {
            return $noBpkb;
        }

        foreach (preg_split('/[\s\-]+/', $sisa, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $kata) {
            if (mb_strlen($kata) >= 4) {
                return $inti;   // ada kata utuh: itu tulisan lain, bukan bagian nomor
            }
        }

        return $noBpkb;
    }

    // ── DELETE /api/audit-detail/bpkb/scan/{item} ────────────────────────────

    public function unscan(BpkbOnhandItem $bpkbOnhandItem): JsonResponse
    {
        if ($bpkbOnhandItem->jenis === 'LUAR') {
            $bpkbOnhandItem->delete();
        } else {
            $bpkbOnhandItem->update(['sudah_scan' => false, 'scan_at' => null, 'keterangan' => null]);
        }
        return response()->json(['message' => 'Scan dihapus.']);
    }

    // ── DELETE /api/audit-detail/bpkb/reset?plan_audit_id= ───────────────────

    public function reset(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        BpkbOnhandItem::where('plan_audit_id', $planId)->delete();
        return response()->json(['message' => 'Data BPKB dihapus.']);
    }
}
