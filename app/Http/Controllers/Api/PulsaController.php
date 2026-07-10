<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PulsaPeriode;
use App\Models\PulsaRealisasi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PulsaController extends Controller
{
    private const OPERATORS = ['Telkomsel', 'Indosat', 'XL', 'Axis', 'Tri', 'Smartfren', 'By.U'];

    private const ROLE_LABEL = [
        'admin' => 'Admin',
        'manajer' => 'Manajer Audit',
        'auditor' => 'Auditor',
        'koordinator' => 'Koordinator',
        'coo' => 'Chief Operating Officer',
        'afd' => 'AFD',
    ];

    public function userOptions(): JsonResponse
    {
        $users = User::query()
            ->where('is_disabled', false)
            ->whereIn('role', ['auditor', 'manajer'])
            ->orderBy('name')
            ->get()
            ->map(fn(User $u) => [
                'username' => $u->username,
                'nama' => $u->display_name ?: $u->name,
                'jabatan' => self::ROLE_LABEL[$u->role] ?? ucfirst((string) $u->role),
            ]);

        return response()->json(['data' => $users]);
    }

    public function operatorOptions(): JsonResponse
    {
        return response()->json(['data' => self::OPERATORS]);
    }

    public function index(Request $request): JsonResponse
    {
        $tahun = (int) $request->query('tahun', now()->year);
        $bulan = (int) $request->query('bulan', now()->month);

        $rows = PulsaRealisasi::query()
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->orderBy('tanggal')
            ->get()
            ->map(fn(PulsaRealisasi $r) => $r->toAktaArray());

        $periode = PulsaPeriode::query()->where('tahun', $tahun)->where('bulan', $bulan)->first();
        $isCurrentPeriode = $tahun === now()->year && $bulan === now()->month;

        return response()->json([
            'data' => $rows,
            'periode' => [
                'tahun' => $tahun,
                'bulan' => $bulan,
                'status' => $periode?->status ?? ($isCurrentPeriode ? 'terbuka' : 'tertutup'),
                'isDefaultOpen' => !$periode && $isCurrentPeriode,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['nullable', 'string', 'max:100'],
            'nama' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['required', 'date'],
            'nomor_hp' => ['required', 'string', 'max:30'],
            'operator' => ['nullable', 'string', Rule::in(self::OPERATORS)],
            'nominal' => ['required', 'numeric', 'min:0'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $tanggal = \Carbon\Carbon::parse($data['tanggal']);
        $tahun = (int) $tanggal->format('Y');
        $bulan = (int) $tanggal->format('n');

        $this->ensurePeriodeTerbuka($request, $tahun, $bulan);

        $file = $request->file('file');
        $path = $file->store('pulsa', 'public');
        $bonFile = [
            'name' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => Storage::url($path),
        ];

        $realisasi = PulsaRealisasi::query()->create([
            'username' => $data['username'] ?? null,
            'nama' => $data['nama'],
            'jabatan' => $data['jabatan'] ?? null,
            'tanggal' => $data['tanggal'],
            'nomor_hp' => $data['nomor_hp'],
            'operator' => $data['operator'] ?? null,
            'nominal' => $data['nominal'],
            'bon_file' => $bonFile,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'status' => 'diajukan',
            'created_by' => $user?->username,
        ]);

        return response()->json([
            'message' => 'Realisasi pulsa berhasil disimpan.',
            'data' => $realisasi->toAktaArray(),
        ], 201);
    }

    public function destroy(Request $request, PulsaRealisasi $pulsaRealisasi): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->role === 'admin' || $user->username === $pulsaRealisasi->created_by),
            403,
            'Anda tidak berwenang menghapus data ini.'
        );

        $this->ensurePeriodeTerbuka($request, $pulsaRealisasi->tahun, $pulsaRealisasi->bulan);

        $pulsaRealisasi->delete();

        return response()->json(['message' => 'Realisasi pulsa berhasil dihapus.']);
    }

    // Admin membuka/menutup periode input realisasi pulsa untuk tahun+bulan tertentu.
    public function togglePeriode(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->role === 'admin', 403, 'Hanya admin yang boleh mengubah status periode.');

        $data = $request->validate([
            'tahun' => ['required', 'integer'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'status' => ['required', 'string', Rule::in(['terbuka', 'tertutup'])],
        ]);

        $periode = PulsaPeriode::query()->updateOrCreate(
            ['tahun' => $data['tahun'], 'bulan' => $data['bulan']],
            [
                'status' => $data['status'],
                'closed_by' => $data['status'] === 'tertutup' ? $user->username : null,
                'closed_at' => $data['status'] === 'tertutup' ? now() : null,
            ]
        );

        return response()->json([
            'message' => $data['status'] === 'tertutup' ? 'Periode ditutup.' : 'Periode dibuka kembali.',
            'data' => $periode->toAktaArray(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tahun = (int) $request->query('tahun', now()->year);
        $bulan = (int) $request->query('bulan', now()->month);

        $rows = PulsaRealisasi::query()
            ->where('tahun', $tahun)
            ->where('bulan', $bulan)
            ->orderBy('tanggal')
            ->get();

        $statusLabel = [
            'diajukan' => 'Diajukan',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
        ];

        $headers = ['No', 'Tanggal', 'Nama', 'Jabatan', 'Operator', 'No HP', 'Nominal', 'Bon', 'Status'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Realisasi Pulsa');

        $sheet->setCellValue('A1', 'REALISASI PULSA - ' . mb_strtoupper(self::bulanLabel($bulan)) . ' ' . $tahun);
        $sheet->mergeCells('A1:' . chr(64 + count($headers)) . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        foreach ($headers as $i => $header) {
            $col = chr(65 + $i);
            $sheet->setCellValue($col . '3', $header);
        }
        $headerRange = 'A3:' . chr(64 + count($headers)) . '3';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $rowIndex = 4;
        $totalNominal = 0;

        foreach ($rows as $i => $row) {
            $sheet->setCellValue('A' . $rowIndex, $i + 1);
            $sheet->setCellValue('B' . $rowIndex, optional($row->tanggal)->format('d/m/Y'));
            $sheet->setCellValue('C' . $rowIndex, $row->nama);
            $sheet->setCellValue('D' . $rowIndex, $row->jabatan);
            $sheet->setCellValue('E' . $rowIndex, $row->operator);
            $sheet->setCellValue('F' . $rowIndex, $row->nomor_hp);
            $sheet->setCellValue('G' . $rowIndex, (float) $row->nominal);
            $sheet->setCellValue('I' . $rowIndex, $statusLabel[$row->status] ?? $row->status);

            $bonPath = $row->bon_file['path'] ?? null;
            $absolutePath = $bonPath ? Storage::disk('public')->path($bonPath) : null;
            $isImage = $absolutePath && file_exists($absolutePath)
                && in_array(strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']);

            if ($isImage) {
                $drawing = new Drawing();
                $drawing->setName('Bon');
                $drawing->setPath($absolutePath);
                $drawing->setHeight(70);
                $drawing->setCoordinates('H' . $rowIndex);
                $drawing->setOffsetX(4);
                $drawing->setOffsetY(4);
                $drawing->setWorksheet($sheet);
                $sheet->getRowDimension($rowIndex)->setRowHeight(56);
            } else {
                $sheet->setCellValue('H' . $rowIndex, $bonPath ? 'File PDF (lihat lampiran)' : '-');
            }

            $totalNominal += (float) $row->nominal;
            $rowIndex++;
        }

        $sheet->setCellValue('F' . $rowIndex, 'TOTAL');
        $sheet->getStyle('F' . $rowIndex)->getFont()->setBold(true);
        $sheet->setCellValue('G' . $rowIndex, $totalNominal);
        $sheet->getStyle('G' . $rowIndex)->getFont()->setBold(true);

        $sheet->getStyle('G4:G' . $rowIndex)->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize($col !== 'H');
        }
        $sheet->getColumnDimension('H')->setWidth(14);

        $dataRange = 'A3:' . chr(64 + count($headers)) . $rowIndex;
        $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $filename = 'realisasi-pulsa-' . $tahun . '-' . str_pad((string) $bulan, 2, '0', STR_PAD_LEFT) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function bulanLabel(int $bulan): string
    {
        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $labels[$bulan] ?? (string) $bulan;
    }

    private function ensurePeriodeTerbuka(Request $request, int $tahun, int $bulan): void
    {
        $user = $request->user();
        if ($user?->role === 'admin') {
            return;
        }

        $periode = PulsaPeriode::query()->where('tahun', $tahun)->where('bulan', $bulan)->first();
        $isCurrentPeriode = $tahun === now()->year && $bulan === now()->month;
        $status = $periode?->status ?? ($isCurrentPeriode ? 'terbuka' : 'tertutup');

        abort_if($status === 'tertutup', 422, 'Periode ini sudah ditutup, tidak bisa menambah/mengubah data.');
    }
}
