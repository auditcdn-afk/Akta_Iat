<?php

namespace Tests\Feature;

use App\Models\PemeriksaanLampiran;
use App\Models\PlanAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Lampiran PDF sebelumnya dirender lewat <embed> (plugin viewer native
 * browser), yang dibatasi browser sampai beberapa instance saja — lampiran
 * ke-5/6 dst tampil kosong. Sempat diganti jadi tautan "buka di tab baru",
 * tapi isinya harus langsung terlihat di laporan, bukan cuma nama file dan
 * tombol. Sekarang isinya dirender ke <canvas> lewat pdf.js di sisi klien
 * (akta-report-pdf-lampiran.js) — server hanya perlu menyediakan data PDF-nya
 * lewat data URI, sama seperti sebelumnya.
 */
class ReportPdfLampiranTest extends TestCase
{
    use RefreshDatabase;

    public function test_lampiran_pdf_dirender_via_placeholder_pdfjs_bukan_tautan_saja(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $plan = PlanAudit::query()->create([
            'no_spt' => '0001/01/01/2026/SPT-IAT', 'cabang' => 'SBG MTR',
            'jenis_audit' => 'Audit Full SO', 'status' => 'running',
        ]);

        // ReportPdfController membaca file lewat storage_path('app/public/...')
        // langsung (bukan lewat Storage facade), jadi Storage::fake() (yang
        // mengarah ke disk terpisah) tidak akan terlihat oleh controller —
        // file ditulis ke disk public sungguhan lalu dibersihkan di akhir tes.
        $relPath = 'lampiran/dummy-test-'.uniqid().'.pdf';
        Storage::disk('public')->put($relPath, "%PDF-1.4\n%dummy content for test\n");

        try {
            PemeriksaanLampiran::query()->create([
                'plan_audit_id' => $plan->id,
                'files_json' => [[
                    'name' => 'Berkas Uji.pdf',
                    'ext' => 'pdf',
                    'path' => $relPath,
                    'size' => Storage::disk('public')->size($relPath),
                    'uploadedAt' => now()->toDateTimeString(),
                ]],
            ]);

            $html = $this->get(route('akta.report-audit.pdf', $plan))->assertOk()->getContent();

            $this->assertStringContainsString('lampiran-pdf-pages', $html);
            $this->assertStringContainsString('data-pdf-name="Berkas Uji.pdf"', $html);
            $this->assertStringContainsString('data:application/pdf;base64,', $html);
            $this->assertStringNotContainsString('Buka Pratinjau PDF ↗', $html);
        } finally {
            Storage::disk('public')->delete($relPath);
        }
    }
}
