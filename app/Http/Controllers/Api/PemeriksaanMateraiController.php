<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanMaterai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PemeriksaanMateraiController extends Controller
{
    // ── GET /api/audit-detail/materai ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rows   = PemeriksaanMaterai::where('plan_audit_id', $planId)
            ->orderBy('jenis_materai')
            ->get();
        return response()->json(['data' => $rows->map->toAktaArray()]);
    }

    // ── POST /api/audit-detail/materai/upload (parse HTML dari MTP SPP) ──────

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'          => 'required|file|mimes:html,htm,xhtml',
            'plan_audit_id' => 'required|integer|exists:plan_audits,id',
        ]);

        $html    = file_get_contents($request->file('file')->getRealPath());
        $planId  = $request->input('plan_audit_id');
        $who     = $request->user()?->username ?? $request->user()?->email ?? null;

        $parsed  = $this->parseHtml($html);
        if (empty($parsed)) {
            return response()->json(['message' => 'Tidak ada data meterai yang berhasil dibaca dari file HTML.'], 422);
        }

        $saved = [];
        foreach ($parsed as $block) {
            $rec = PemeriksaanMaterai::updateOrCreate(
                ['plan_audit_id' => $planId, 'jenis_materai' => $block['jenisMaterai']],
                [
                    'saldo_awal'     => $block['saldoAwal'],
                    'total_debet'    => $block['totalDebet'],
                    'total_kredit'   => $block['totalKredit'],
                    'saldo_akhir'    => $block['saldoAkhir'],
                    'transaksi_json' => $block['transaksi'],
                    // fisik & selisih dipertahankan jika sudah diisi sebelumnya
                    'updated_by'     => $who,
                ]
            );
            if (!$rec->created_by) $rec->update(['created_by' => $who]);
            $saved[] = $rec->fresh()->toAktaArray();
        }

        return response()->json([
            'message' => count($saved) . ' jenis meterai berhasil diimpor.',
            'data'    => $saved,
        ]);
    }

    // ── PUT /api/audit-detail/materai/{rec}/fisik ─────────────────────────────
    // Simpan jumlah fisik & hitung selisih

    public function updateFisik(Request $request, PemeriksaanMaterai $pemeriksaanMaterai): JsonResponse
    {
        $data = $request->validate(['fisik' => 'required|integer|min:0']);
        $fisik   = (int) $data['fisik'];
        $selisih = $fisik - $pemeriksaanMaterai->saldo_akhir;
        $pemeriksaanMaterai->update([
            'fisik'      => $fisik,
            'selisih'    => $selisih,
            'updated_by' => $request->user()?->username ?? $request->user()?->email,
        ]);
        return response()->json(['data' => $pemeriksaanMaterai->fresh()->toAktaArray()]);
    }

    // ── DELETE /api/audit-detail/materai/{rec} ────────────────────────────────

    public function destroy(PemeriksaanMaterai $pemeriksaanMaterai): JsonResponse
    {
        $pemeriksaanMaterai->delete();
        return response()->json(['message' => 'Data dihapus.']);
    }

    // ── HTML Parser ───────────────────────────────────────────────────────────
    // Format MTP SPP: tabel dengan baris per jenis meterai
    // Kolom: NO | TANGGAL | NOMOR | KETERANGAN | DEBET | KREDIT | SALDO

    private function parseHtml(string $html): array
    {
        // Suppress HTML parse warnings
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        $results = [];
        $currentJenis   = null;
        $saldoAwal      = 0;
        $transaksi      = [];
        $totalDebet     = 0;
        $totalKredit    = 0;
        $saldoAkhir     = 0;

        // Cari semua baris tabel
        $rows = $xpath->query('//table//tr');
        foreach ($rows as $row) {
            $cells = $xpath->query('td', $row);
            if ($cells->length === 0) {
                // Cek header section (th) — bisa berisi judul jenis meterai
                $ths = $xpath->query('th', $row);
                if ($ths->length > 0) {
                    $text = strtoupper(trim($ths->item(0)->textContent ?? ''));
                    // Cek apakah ini header jenis meterai baru
                    if (str_contains($text, 'METERAI') || str_contains($text, 'MATERAI')) {
                        // Simpan block sebelumnya
                        if ($currentJenis !== null) {
                            $results[] = $this->makeBlock($currentJenis, $saldoAwal, $totalDebet, $totalKredit, $saldoAkhir, $transaksi);
                        }
                        $currentJenis = $this->extractJenis($text);
                        $saldoAwal = $totalDebet = $totalKredit = $saldoAkhir = 0;
                        $transaksi = [];
                    }
                }
                continue;
            }

            $cols = [];
            foreach ($cells as $cell) {
                $cols[] = trim($cell->textContent ?? '');
            }

            if (count($cols) < 3) continue;

            $col0 = strtoupper($cols[0]);

            // Deteksi baris SALDO AWAL
            if (str_contains($col0, 'SALDO') && str_contains($col0, 'AWAL')) {
                $saldoAwal = $this->toInt(end($cols));
                $saldoAkhir = $saldoAwal;
                continue;
            }

            // Deteksi baris SALDO AKHIR / total
            if (str_contains($col0, 'SALDO') && (str_contains($col0, 'AKHIR') || str_contains($col0, 'TOTAL'))) {
                // Ambil totalDebet, totalKredit, saldoAkhir dari kolom
                if (count($cols) >= 3) {
                    $saldoAkhir  = $this->toInt($cols[count($cols) - 1]);
                    $totalKredit = $this->toInt($cols[count($cols) - 2]);
                    $totalDebet  = $this->toInt($cols[count($cols) - 3]);
                }
                continue;
            }

            // Deteksi header baris jenis baru dalam tabel (colspan cell dengan text METERAI)
            $text0 = strtoupper($cols[0]);
            if ((str_contains($text0, 'METERAI') || str_contains($text0, 'MATERAI')) && count($cols) <= 2) {
                if ($currentJenis !== null) {
                    $results[] = $this->makeBlock($currentJenis, $saldoAwal, $totalDebet, $totalKredit, $saldoAkhir, $transaksi);
                }
                $currentJenis = $this->extractJenis($text0);
                $saldoAwal = $totalDebet = $totalKredit = $saldoAkhir = 0;
                $transaksi = [];
                continue;
            }

            // Baris transaksi normal: NO | TANGGAL | NOMOR | KETERANGAN | DEBET | KREDIT | SALDO
            if (is_numeric($cols[0]) && count($cols) >= 5) {
                $n = count($cols);
                $transaksi[] = [
                    'no'          => (int) $cols[0],
                    'tanggal'     => $cols[1] ?? '',
                    'nomor'       => $cols[2] ?? '',
                    'keterangan'  => $cols[3] ?? '',
                    'debet'       => $this->toInt($cols[$n - 3] ?? ''),
                    'kredit'      => $this->toInt($cols[$n - 2] ?? ''),
                    'saldo'       => $this->toInt($cols[$n - 1] ?? ''),
                ];
            }
        }

        // Simpan block terakhir
        if ($currentJenis !== null) {
            $results[] = $this->makeBlock($currentJenis, $saldoAwal, $totalDebet, $totalKredit, $saldoAkhir, $transaksi);
        }

        // Fallback: jika tidak ada jenis terdeteksi tapi ada transaksi, buat satu block
        if (empty($results) && !empty($transaksi)) {
            $results[] = $this->makeBlock('Meterai', $saldoAwal, $totalDebet, $totalKredit, $saldoAkhir, $transaksi);
        }

        return $results;
    }

    private function makeBlock(string $jenis, int $saldoAwal, int $debet, int $kredit, int $saldoAkhir, array $transaksi): array
    {
        // Hitung ulang total dari transaksi jika nilai header kosong
        if ($debet === 0 && $kredit === 0 && !empty($transaksi)) {
            $debet  = array_sum(array_column($transaksi, 'debet'));
            $kredit = array_sum(array_column($transaksi, 'kredit'));
        }
        if ($saldoAkhir === 0 && !empty($transaksi)) {
            $saldoAkhir = (int) end($transaksi)['saldo'];
        }
        return [
            'jenisMaterai' => $jenis,
            'saldoAwal'    => $saldoAwal,
            'totalDebet'   => $debet,
            'totalKredit'  => $kredit,
            'saldoAkhir'   => $saldoAkhir,
            'transaksi'    => $transaksi,
        ];
    }

    private function extractJenis(string $text): string
    {
        // "METERAI Rp 10.000" → "Rp 10.000"
        $text = preg_replace('/MATE?RAI\s*/i', '', $text);
        $text = trim($text);
        if ($text === '') return 'Meterai';
        return 'Meterai ' . $text;
    }

    private function toInt(string $val): int
    {
        // "1.234" atau "1,234" → 1234 ; strip non-numeric
        $clean = preg_replace('/[^0-9]/', '', $val);
        return $clean !== '' ? (int) $clean : 0;
    }
}
