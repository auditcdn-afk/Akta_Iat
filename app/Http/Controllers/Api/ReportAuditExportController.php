<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReportAuditChecklistItem;
use App\Models\ReportAuditSummary;
use App\Services\ReportAuditFlattener;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Export Excel dari tabel ringkasan report_audit_summaries + report_audit_checklist_items
// (bukan dari tabel transaksional langsung) — supaya export tetap ringan berapa pun
// sering dipakai, karena hanya membaca hasil precompute dari ReportAuditFlattener.
// Kolom checklist H1/H2/WU/WP di-pivot balik ke bentuk lebar di sini, on-demand,
// mendekati struktur file rekap lama.
class ReportAuditExportController extends Controller
{
    private const HO_ROLES = ['admin', 'manajer', 'auditor', 'koordinator', 'coo', 'viewer'];

    // Kelompok pemeriksaan (jenis di audit_gradings) → kode singkat gaya rekap lama.
    private const JENIS_ABBR = [
        'Cabang' => 'H1',
        'Bengkel' => 'H2',
        'WHS UNIT' => 'WU',
        'WHS PART' => 'WP',
    ];

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $role = $user?->role;

        $query = ReportAuditSummary::query()->orderBy('tgl_mulai');

        if (!in_array($role, self::HO_ROLES, true)) {
            $query->where('unit_usaha', $user?->unit_usaha);
        }

        if ($request->filled('dari')) {
            $query->whereDate('tgl_mulai', '>=', $request->query('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('tgl_mulai', '<=', $request->query('sampai'));
        }
        if ($request->filled('unit_usaha')) {
            $query->where('unit_usaha', $request->query('unit_usaha'));
        }

        $rows = $query->get();
        $planIds = $rows->pluck('plan_audit_id');

        // Kumpulkan checklist per plan, dikelompokkan per "H1 Nama Item" dst,
        // supaya bisa langsung dipetakan ke satu kolom saat tulis baris.
        $checklistByPlan = ReportAuditChecklistItem::whereIn('plan_audit_id', $planIds)
            ->orderBy('urutan')
            ->get()
            ->groupBy('plan_audit_id');

        // Bangun daftar kolom checklist dinamis dari data yang benar-benar ada,
        // urut berdasar kelompok (H1, H2, WU, WP) lalu urutan aslinya.
        $checklistColumns = [];
        $seen = [];
        foreach ($checklistByPlan as $items) {
            foreach ($items as $item) {
                $abbr = self::JENIS_ABBR[$item->jenis] ?? ($item->jenis ?: 'LAIN');
                $label = trim($abbr . ' ' . $item->nama_pemeriksaan);
                if (!isset($seen[$label])) {
                    $seen[$label] = true;
                    $checklistColumns[] = $label;
                }
            }
        }

        $baseHeaders = [
            'No SPT', 'Unit Usaha', 'Cabang Area', 'Jenis Audit', 'Kepala Tim', 'Anggota Tim', 'Status Plan',
            'Tgl Plan', 'Tgl Mulai', 'Tgl Selesai', 'Jumlah Hari',
            'Akomodasi', 'Transportasi Darat', 'Transportasi Laut', 'Transportasi Udara',
            'Konsumsi', 'Laundry', 'Pramenu', 'Perobatan', 'Komunikasi', 'Lain-lain', 'TOTAL',
            'Fraud', 'Jenis Fraud', 'Keterangan Fraud',
            'Nilai Grading', 'Jumlah Item Grading',
        ];
        $tailHeaders = [
            'No SK', 'Status SK', 'Tgl SK Dibuat', 'Tgl SK Selesai',
            'Jumlah Rekomendasi', 'Rekomendasi Selesai', 'Ringkasan Rekomendasi',
            'Jumlah PICA', 'PICA Closed', 'Ringkasan PICA',
        ];

        $headers = [...$baseHeaders, ...$checklistColumns, ...$tailHeaders];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report Audit');
        $lastCol = count($headers);

        $refreshedAt = $rows->max('refreshed_at');
        $sheet->setCellValue([1, 1], 'Data disegarkan per: '
            . ($refreshedAt ? $refreshedAt->format('d/m/Y H:i') : '-')
            . ' (dijadwalkan tiap 2 jam, bukan real-time saat file ini dibuat)');
        $sheet->getStyle('A1')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 2], $header);
        }
        $headerRange = Coordinate::stringFromColumnIndex(1) . '2:'
            . Coordinate::stringFromColumnIndex($lastCol) . '2';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowIndex = 3;
        foreach ($rows as $row) {
            $itemsByLabel = [];
            foreach ($checklistByPlan->get($row->plan_audit_id, []) as $item) {
                $abbr = self::JENIS_ABBR[$item->jenis] ?? ($item->jenis ?: 'LAIN');
                $label = trim($abbr . ' ' . $item->nama_pemeriksaan);
                $itemsByLabel[$label] = $item->current_condition;
            }

            $values = [
                $row->no_spt, $row->unit_usaha, $row->cabang_area, $row->jenis_audit,
                $row->kepala_tim, $row->anggota_tim, $row->status_plan,
                optional($row->tgl_plan)->format('Y-m-d'),
                optional($row->tgl_mulai)->format('Y-m-d'),
                optional($row->tgl_selesai)->format('Y-m-d'),
                $row->jumlah_hari,
                $row->biaya_akomodasi, $row->biaya_transportasi_darat, $row->biaya_transportasi_laut,
                $row->biaya_transportasi_udara, $row->biaya_konsumsi, $row->biaya_laundry,
                $row->biaya_pramenu, $row->biaya_perobatan, $row->biaya_komunikasi,
                $row->biaya_lain_lain, $row->biaya_total,
                $row->fraud, $row->jenis_fraud, $row->keterangan_fraud,
                $row->nilai_grading, $row->jumlah_item_grading,
            ];

            foreach ($checklistColumns as $label) {
                $values[] = $itemsByLabel[$label] ?? '';
            }

            $values = [
                ...$values,
                $row->no_sk, $row->status_sk,
                optional($row->tgl_sk_dibuat)->format('Y-m-d'),
                optional($row->tgl_sk_selesai)->format('Y-m-d'),
                $row->jumlah_rekomendasi, $row->rekomendasi_selesai, $row->ringkasan_rekomendasi,
                $row->jumlah_pica, $row->pica_closed, $row->ringkasan_pica,
            ];

            foreach ($values as $i => $value) {
                $sheet->setCellValue([$i + 1, $rowIndex], $value);
            }
            $rowIndex++;
        }

        $dataRange = 'A2:' . Coordinate::stringFromColumnIndex($lastCol) . ($rowIndex - 1);
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range(1, min($lastCol, 40)) as $colIdx) {
            $sheet->getColumnDimensionByColumn($colIdx)->setAutoSize(true);
        }

        $filename = 'report-audit-' . ($refreshedAt ? $refreshedAt->format('Y-m-d_H-i') : now()->format('Y-m-d_H-i')) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // Trigger manual (admin) untuk refresh segera, di luar jadwal — misalnya
    // sesaat sebelum export kalau butuh data yang benar-benar terbaru.
    public function refreshNow(Request $request, ReportAuditFlattener $flattener): JsonResponse
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya admin yang boleh memicu refresh manual.');

        $count = $flattener->refreshAll();

        return response()->json([
            'message' => "Data ringkasan report audit diperbarui ({$count} plan).",
        ]);
    }
}
