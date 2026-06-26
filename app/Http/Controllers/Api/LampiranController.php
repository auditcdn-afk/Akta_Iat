<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PemeriksaanLampiran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class LampiranController extends Controller
{
    private const ALLOWED = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanLampiran::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'          => 'required|file|max:20480',
            'plan_audit_id' => 'required|integer',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, self::ALLOWED, true)) {
            return response()->json(['message' => 'Format tidak didukung. Gunakan PDF, JPG, PNG, DOC, atau DOCX.'], 422);
        }

        $planId   = $request->input('plan_audit_id');
        $who      = $request->user()?->username ?? $request->user()?->email;
        $dir      = "lampiran/{$planId}";
        $filename = Str::uuid() . '.' . $ext;
        $path     = $file->storeAs($dir, $filename, 'public');

        $rec = PemeriksaanLampiran::firstOrCreate(
            ['plan_audit_id' => $planId],
            ['created_by' => $who]
        );

        $files = $rec->files_json ?? [];
        $files[] = [
            'name'      => $file->getClientOriginalName(),
            'path'      => $path,
            'ext'       => $ext,
            'size'      => $file->getSize(),
            'uploadedAt'=> now()->toDateTimeString(),
        ];

        $rec->update(['files_json' => $files, 'updated_by' => $who, 'merged_pdf' => null]);

        return response()->json([
            'message' => 'File berhasil diupload.',
            'data'    => $rec->fresh()->toAktaArray(),
        ]);
    }

    public function deleteFile(Request $request): JsonResponse
    {
        $planId = $request->input('plan_audit_id');
        $idx    = (int) $request->input('index');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanLampiran::where('plan_audit_id', $planId)->firstOrFail();
        $files = $rec->files_json ?? [];

        if (!isset($files[$idx])) {
            return response()->json(['message' => 'File tidak ditemukan.'], 404);
        }

        Storage::disk('public')->delete($files[$idx]['path']);
        array_splice($files, $idx, 1);

        $rec->update(['files_json' => $files, 'updated_by' => $who, 'merged_pdf' => null]);

        return response()->json(['message' => 'File dihapus.', 'data' => $rec->fresh()->toAktaArray()]);
    }

    public function mergePdf(Request $request): JsonResponse
    {
        $planId = $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $rec = PemeriksaanLampiran::where('plan_audit_id', $planId)->firstOrFail();
        $files = array_filter($rec->files_json ?? [], fn($f) => in_array($f['ext'], ['pdf', 'jpg', 'jpeg', 'png']));
        $files = array_values($files);

        if (empty($files)) {
            return response()->json(['message' => 'Tidak ada file PDF/gambar untuk digabung. File Word tidak bisa digabung otomatis.'], 422);
        }

        $pdf = new Fpdi();
        $pdf->SetAutoPageBreak(false);

        foreach ($files as $f) {
            $absPath = Storage::disk('public')->path($f['path']);
            if (!file_exists($absPath)) continue;

            if ($f['ext'] === 'pdf') {
                try {
                    $count = $pdf->setSourceFile($absPath);
                    for ($p = 1; $p <= $count; $p++) {
                        $tpl = $pdf->importPage($p);
                        $size = $pdf->getTemplateSize($tpl);
                        $pdf->AddPage($size['width'] > $size['height'] ? 'L' : 'P', [$size['width'], $size['height']]);
                        $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
                    }
                } catch (\Exception $e) {
                    // Skip PDF yang tidak bisa dibaca
                }
            } elseif (in_array($f['ext'], ['jpg', 'jpeg', 'png'])) {
                // Konversi gambar ke halaman PDF menggunakan GD
                $img = $f['ext'] === 'png' ? @imagecreatefrompng($absPath) : @imagecreatefromjpeg($absPath);
                if (!$img) $img = @imagecreatefromstring(file_get_contents($absPath));
                if (!$img) continue;

                $w = imagesx($img);
                $h = imagesy($img);
                // Scale ke A4 (210x297mm) dengan DPI 96 → pixel per mm ≈ 3.78
                $mmW = round($w / 3.78, 2);
                $mmH = round($h / 3.78, 2);
                // Fit ke A4 jika terlalu besar
                $maxW = 210; $maxH = 297;
                if ($mmW > $maxW || $mmH > $maxH) {
                    $scale = min($maxW / $mmW, $maxH / $mmH);
                    $mmW   = round($mmW * $scale, 2);
                    $mmH   = round($mmH * $scale, 2);
                }
                $orientation = $mmW > $mmH ? 'L' : 'P';
                $pdf->AddPage($orientation, [$mmW, $mmH]);

                // Simpan gambar ke temp untuk FPDF
                $isJpeg  = in_array($f['ext'], ['jpg', 'jpeg']);
                $tmpBase = tempnam(sys_get_temp_dir(), 'lmp_');
                $tmpImg  = $tmpBase . ($isJpeg ? '.jpg' : '.png');
                if ($isJpeg) imagejpeg($img, $tmpImg, 90);
                else         imagepng($img, $tmpImg);
                imagedestroy($img);
                @unlink($tmpBase); // hapus file base tanpa ekstensi

                $type = $isJpeg ? 'JPEG' : 'PNG';
                $pdf->Image($tmpImg, 0, 0, $mmW, $mmH, $type);
                @unlink($tmpImg);
            }
        }

        if ($pdf->getNumPages() === 0) {
            return response()->json(['message' => 'Tidak ada halaman yang berhasil diproses.'], 422);
        }

        $dir     = "lampiran/{$planId}";
        $outName = 'merged_' . now()->format('YmdHis') . '.pdf';
        $outPath = $dir . '/' . $outName;

        Storage::disk('public')->makeDirectory($dir);
        $pdf->Output('F', Storage::disk('public')->path($outPath));

        // Hapus merged PDF lama
        if ($rec->merged_pdf && $rec->merged_pdf !== $outPath) {
            Storage::disk('public')->delete($rec->merged_pdf);
        }

        $rec->update(['merged_pdf' => $outPath, 'updated_by' => $who]);

        return response()->json([
            'message'   => 'PDF berhasil digabung.',
            'mergedPdf' => $outPath,
            'url'       => Storage::disk('public')->url($outPath),
        ]);
    }

    public function download(Request $request)
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanLampiran::where('plan_audit_id', $planId)->first();

        if (!$rec || !$rec->merged_pdf) {
            abort(404, 'PDF gabungan belum dibuat.');
        }

        $path = Storage::disk('public')->path($rec->merged_pdf);
        if (!file_exists($path)) abort(404, 'File tidak ditemukan.');

        return response()->download($path, 'Lampiran_Audit_' . $planId . '.pdf');
    }
}
