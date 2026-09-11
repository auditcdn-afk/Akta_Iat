<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use App\Models\PemeriksaanAuditor;
use App\Services\PerlengkapanOnhand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PemeriksaanPerlengkapanController extends Controller
{
    use RequiresAuditorAuditee;

    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    /**
     * Perhitungan "berapa unit onhand yang membutuhkan jenis perlengkapan ini"
     * dipakai bersama Report Audit (bagian C Rekap Gabungan), jadi tinggal di
     * satu service supaya kedua sisi tidak bisa lagi memakai penyebut berbeda.
     */
    public function __construct(private readonly PerlengkapanOnhand $onhand)
    {
    }

    // ── GET /api/audit-detail/perlengkapan/export-selisih ────────────────────

    /**
     * Unduh rekap gabungan perlengkapan per jenis sebagai Excel.
     *
     * Isinya sama persis dengan bagian "C. REKAP GABUNGAN PERLENGKAPAN PER
     * JENIS" di Report Audit — angkanya dari service yang sama
     * (PerlengkapanOnhand::rekapGabungan), bukan dihitung ulang di sini, supaya
     * tidak mungkin berbeda dengan laporannya.
     *
     * Bawaannya hanya jenis yang SELISIH-nya tidak nol, karena itu yang
     * ditindaklanjuti auditor. Tambahkan semua=1 untuk mengunduh seluruh jenis.
     */
    public function exportSelisih(Request $request): StreamedResponse
    {
        $planId = $request->query('plan_audit_id');
        abort_unless($planId, 422, 'plan_audit_id wajib diisi.');

        $plan    = PlanAudit::find($planId);
        $auditor = PemeriksaanAuditor::where('plan_audit_id', $planId)
            ->where('tool', 'perlengkapan')->first();

        // Seluruh jenis — sama persis dengan yang dipakai Report Audit bagian C.
        $semuaBaris = $this->onhand->rekapGabungan(
            (string) $planId,
            PemeriksaanPerlengkapan::where('plan_audit_id', $planId)->get()
        );

        $semua = $request->boolean('semua');
        $baris = $semua
            ? $semuaBaris
            : array_values(array_filter($semuaBaris, fn($r) => $r['totalSelisih'] != 0));

        $spreadsheet = new Spreadsheet();
        $this->tulisSheetRekap($spreadsheet->getActiveSheet(), [
            'No SPT: ' . ($plan->no_spt ?? '-'),
            'Cabang/Area: ' . ($plan->cabang_area ?? $plan->cabang ?? '-'),
            'Auditor: ' . ($auditor->nama_auditor ?? '-') . '   Auditee: ' . ($auditor->nama_auditee ?? '-'),
            'Diunduh: ' . now()->format('d/m/Y H:i'),
            'Angka tiap jenis sama persis dengan Report Audit bagian C. Rekap Gabungan Perlengkapan per Jenis.',
        ], $baris, $semuaBaris, $semua);

        $filename = 'perlengkapan-selisih-' . ($plan->no_spt ?? $planId) . '-' . now()->format('Y-m-d_H-i') . '.xlsx';
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new XlsxWriter($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Susun satu sheet rekap gabungan, mengikuti bentuk tabel di Report Audit. */
    private function tulisSheetRekap(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $infoLines,
        array $baris,
        array $semuaBaris,
        bool $semua
    ): void {
        $sheet->setTitle('Rekap Perlengkapan');

        foreach ($infoLines as $i => $line) {
            $sheet->setCellValue([1, $i + 1], $line);
        }
        $sheet->getStyle('A1:A' . count($infoLines))->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $judulRow = count($infoLines) + 2;
        $sheet->setCellValue([1, $judulRow], 'REKAP GABUNGAN PERLENGKAPAN PER JENIS'
            . ($semua ? ' — semua jenis' : ' — hanya yang ada selisih')
            . ' (' . count($baris) . ' jenis)');
        $sheet->getStyle('A' . $judulRow)->getFont()->setBold(true)->setSize(12);

        $headers = [
            'No', 'Jenis Perlengkapan',
            'SMH Saldo (unit)', 'SMH Fisik (ada)', 'SMH Selisih',
            'Luar SMH Saldo (buku)', 'Luar SMH Fisik', 'Luar SMH Selisih',
            'Total Selisih', 'Keterangan',
        ];
        $headerRow = $judulRow + 1;
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, $headerRow], $header);
        }

        $lastCol = count($headers);
        $kolomTerakhir = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);
        $headerRange = "A{$headerRow}:{$kolomTerakhir}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

        $rowIndex = $headerRow + 1;
        $totalSmhSaldo = $totalSmhFisik = $totalLuarSaldo = $totalLuarFisik = $totalSelisih = 0;

        foreach ($baris as $i => $r) {
            $values = [
                $i + 1, $r['jenis'],
                $r['smhSaldo'], $r['smhFisik'], $r['smhSelisih'],
                $r['luarSaldo'], $r['luarFisik'], $r['luarSelisih'],
                $r['totalSelisih'], $r['keterangan'] ?: '-',
            ];
            foreach ($values as $ci => $value) {
                $sheet->setCellValue([$ci + 1, $rowIndex], $value);
            }

            $totalSmhSaldo  += $r['smhSaldo'];
            $totalSmhFisik  += $r['smhFisik'];
            $totalLuarSaldo += $r['luarSaldo'];
            $totalLuarFisik += $r['luarFisik'];
            $totalSelisih   += $r['totalSelisih'];
            $rowIndex++;
        }

        if ($baris) {
            $barisTotal = [[
                'label' => $semua ? 'TOTAL' : 'TOTAL (baris yang ditampilkan)',
                'smhSaldo' => $totalSmhSaldo, 'smhFisik' => $totalSmhFisik,
                'luarSaldo' => $totalLuarSaldo, 'luarFisik' => $totalLuarFisik,
                'totalSelisih' => $totalSelisih,
            ]];

            // Saat hanya selisih yang diunduh, baris TOTAL di atas hanya
            // menjumlah baris yang tampil — jadi kolom Saldo/Fisik-nya lebih
            // kecil daripada TOTAL di Report Audit yang menjumlah SELURUH jenis.
            // Supaya tidak ada angka yang terlihat berbeda dari laporan, total
            // seluruh jenis ikut ditulis dan diberi nama yang jelas.
            if (! $semua) {
                $barisTotal[] = [
                    'label'        => 'TOTAL SELURUH JENIS (sama dengan Report Audit)',
                    'smhSaldo'     => array_sum(array_column($semuaBaris, 'smhSaldo')),
                    'smhFisik'     => array_sum(array_column($semuaBaris, 'smhFisik')),
                    'luarSaldo'    => array_sum(array_column($semuaBaris, 'luarSaldo')),
                    'luarFisik'    => array_sum(array_column($semuaBaris, 'luarFisik')),
                    'totalSelisih' => array_sum(array_column($semuaBaris, 'totalSelisih')),
                ];
            }

            foreach ($barisTotal as $t) {
                // Indeksnya harus sejajar dengan $values pada baris data di atas:
                // 1=Jenis, 2=SMH Saldo, 3=SMH Fisik, 4=SMH Selisih, 5=Luar Saldo,
                // 6=Luar Fisik, 7=Luar Selisih, 8=Total Selisih.
                foreach ([1 => $t['label'], 2 => $t['smhSaldo'], 3 => $t['smhFisik'],
                          4 => $t['smhFisik'] - $t['smhSaldo'], 5 => $t['luarSaldo'],
                          6 => $t['luarFisik'], 7 => $t['luarFisik'] - $t['luarSaldo'],
                          8 => $t['totalSelisih']] as $col => $value) {
                    $sheet->setCellValue([$col + 1, $rowIndex], $value);
                }
                $sheet->getStyle("A{$rowIndex}:{$kolomTerakhir}{$rowIndex}")->getFont()->setBold(true);
                $rowIndex++;
            }

            $sheet->getStyle("A{$headerRow}:{$kolomTerakhir}" . ($rowIndex - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        foreach (range(1, $lastCol) as $colIdx) {
            $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
        }
    }

    // ── GET /api/audit-detail/perlengkapan ───────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $q = PemeriksaanPerlengkapan::query()->latest('id');
        if ($planId) $q->where('plan_audit_id', $planId);

        return response()->json(['data' => $q->get()->map->toAktaArray()]);
    }

    // ── GET /api/audit-detail/perlengkapan/jenis ─────────────────────────────
    // Ambil daftar jenis perlengkapan dari onhand yang sudah diperiksa (perlengkapan_json)
    // disinkronkan dengan db_perlengkapan, dikelompokkan per item perlengkapan.

    public function jenis(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        // Dasar daftar jenis = perlengkapan yang MEMANG dibutuhkan unit-unit onhand
        // plan ini (tipe motor onhand disilangkan ke db_perlengkapan). Sebelumnya
        // daftar ini hanya dibangun dari perlengkapan_json — yang baru terisi
        // setelah unit diperiksa fisik satu per satu — sehingga tepat setelah
        // impor onhand daftarnya jatuh ke fallback yang menampilkan seluruh
        // katalog wilayah, termasuk tipe motor yang tidak ada di cabang itu.
        $expected = $this->onhand->expectedPerJenis($planId);
        arsort($expected);
        $result = array_keys($expected);

        // Ambil semua item onhand dari plan ini
        $itemsQuery = SmhOnhandItem::query()
            ->whereNotNull('perlengkapan_json');

        if ($planId) {
            $itemsQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        // Nama yang tercatat saat periksa fisik tapi tidak ada di db_perlengkapan
        // (mis. ditambahkan manual auditor) tetap ikut, supaya tidak hilang.
        $extra = [];
        foreach ($itemsQuery->get() as $item) {
            foreach ($item->perlengkapan_json ?? [] as $pl) {
                $nama = trim($pl['nama'] ?? '');
                if ($nama !== '' && !isset($expected[$nama])) {
                    $extra[$nama] = ($extra[$nama] ?? 0) + 1;
                }
            }
        }

        arsort($extra);
        $result = array_merge($result, array_keys($extra));

        // Belum ada onhand sama sekali: tampilkan katalog wilayahnya supaya
        // auditor masih bisa mencatat perlengkapan secara manual.
        if (empty($result) && $planId) {
            $wilayah = $this->onhand->wilayahFromPlan($planId);
            $dbRows  = DbPerlengkapan::all()
                ->filter(fn($r) => !$wilayah || strtolower(trim($r->wilayah ?? '')) === $wilayah || blank($r->wilayah));

            foreach ($dbRows as $row) {
                foreach ($row->itemList() as $nama) {
                    if (!in_array($nama, $result, true)) $result[] = $nama;
                }
            }
        }

        return response()->json(['data' => $result]);
    }

    // ── GET /api/audit-detail/perlengkapan/smh-summary ───────────────────────
    // Hitung jumlah per jenis perlengkapan dari onhand (untuk qty referensi)

    public function smhSummary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => array_values($this->onhand->summaryPerJenis($request->query('plan_audit_id'))),
        ]);
    }





    // ── POST /api/audit-detail/perlengkapan ──────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $request->input('plan_audit_id'));
        $this->ensureAuditorFilled((int) $request->input('plan_audit_id'), 'perlengkapan');

        $data = $request->validate([
            'plan_audit_id'     => 'required|integer|exists:plan_audits,id',
            'no_plan'           => 'nullable|string|max:100',
            'nama_unit_usaha'   => 'nullable|string|max:200',
            'nama_pemeriksa'    => 'nullable|string|max:200',
            'tgl_periksa'       => 'nullable|date',
            'jenis_perlengkapan'=> 'required|string|max:200',
            'saldo'             => 'nullable|numeric',
            'fisik'             => 'nullable|integer',
            'penjelasan'        => 'nullable|string|max:1000',
        ]);

        // Saldo diturunkan dari data onhand terkini, bukan dari request.
        $saldo = $this->onhand->saldoFor((string) $data['plan_audit_id'], $data['jenis_perlengkapan']);
        $fisik = (int) ($data['fisik'] ?? 0);

        $data['saldo'] = $saldo;
        // selisih = fisik - saldo (kelebihan/kekurangan fisik vs saldo buku)
        $data['selisih']    = $fisik - $saldo;
        $data['created_by'] = $this->who($request);
        $data['updated_by'] = $this->who($request);

        // updateOrCreate (bukan create) supaya jenis perlengkapan yang sama untuk
        // plan yang sama hanya punya 1 baris. Kalau tidak, jenis yang sama diinput
        // ulang (mis. auditor scan ulang item yang sama) akan bikin baris baru, dan
        // total Saldo di tabel jadi ikut dobel, bukan tersinkron ke fisik terbaru.
        $rec = PemeriksaanPerlengkapan::updateOrCreate(
            ['plan_audit_id' => $data['plan_audit_id'], 'jenis_perlengkapan' => $data['jenis_perlengkapan']],
            $data
        );

        return response()->json(['message' => 'Data berhasil disimpan.', 'data' => $rec->toAktaArray()], 201);
    }

    // ── PUT /api/audit-detail/perlengkapan/{rec} ─────────────────────────────

    public function update(Request $request, PemeriksaanPerlengkapan $pemeriksaanPerlengkapan): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $pemeriksaanPerlengkapan->plan_audit_id);
        $this->ensureAuditorFilled((int) $pemeriksaanPerlengkapan->plan_audit_id, 'perlengkapan');

        $data = $request->validate([
            'no_plan'           => 'nullable|string|max:100',
            'nama_unit_usaha'   => 'nullable|string|max:200',
            'nama_pemeriksa'    => 'nullable|string|max:200',
            'tgl_periksa'       => 'nullable|date',
            'jenis_perlengkapan'=> 'nullable|string|max:200',
            'saldo'             => 'nullable|numeric',
            'fisik'             => 'nullable|integer',
            'penjelasan'        => 'nullable|string|max:1000',
        ]);

        // Sama seperti store(): Saldo diturunkan dari data onhand terkini, jadi
        // baris lama yang tersimpan dengan Saldo salah ikut terkoreksi saat
        // di-update — tanpa perlu menghapus dan membuat ulang barisnya.
        $jenis = $data['jenis_perlengkapan'] ?? $pemeriksaanPerlengkapan->jenis_perlengkapan;
        $saldo = $this->onhand->saldoFor((string) $pemeriksaanPerlengkapan->plan_audit_id, (string) $jenis);
        $fisik = (int) ($data['fisik'] ?? $pemeriksaanPerlengkapan->fisik);

        $data['saldo']      = $saldo;
        $data['selisih']    = $fisik - $saldo;
        $data['updated_by'] = $this->who($request);

        $pemeriksaanPerlengkapan->update($data);

        return response()->json(['message' => 'Data berhasil diperbarui.', 'data' => $pemeriksaanPerlengkapan->fresh()->toAktaArray()]);
    }

    // ── DELETE /api/audit-detail/perlengkapan/{rec} ──────────────────────────

    public function destroy(PemeriksaanPerlengkapan $pemeriksaanPerlengkapan): JsonResponse
    {
        $pemeriksaanPerlengkapan->delete();
        return response()->json(['message' => 'Data dihapus.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────


    private function ensureCanWrite(Request $request, int $planAuditId = 0): void
    {
        if ($planAuditId && PlanAudit::query()->where('id', $planAuditId)->where('is_mandiri', true)->exists()) {
            return;
        }

        abort_unless(in_array(strtolower($request->user()?->role ?? ''), $this->writeRoles, true), 403, 'Role tidak diizinkan.');
    }

    private function who(Request $request): ?string
    {
        $u = $request->user();
        return $u?->username ?? $u?->email ?? null;
    }
}
