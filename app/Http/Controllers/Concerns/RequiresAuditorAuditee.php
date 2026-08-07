<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PemeriksaanAuditor;

/**
 * Dipakai controller tool pemeriksaan (Kas, SMH, Bank, dst) untuk menolak
 * penyimpanan data tool itu sebelum Nama Auditor & Nama Auditee untuk
 * (plan_audit_id, tool) tersebut terisi lewat widget di atas tab — lihat
 * PemeriksaanAuditorController dan resources/js/audit-editor.js
 * (loadAuditorWidget).
 *
 * Hanya diterapkan di action simpan/impor UTAMA tiap tool, bukan di action
 * sekunder seperti parse-excel/parse-html/scan-increment — itu aksi
 * preview/staging, bukan titik commit data.
 */
trait RequiresAuditorAuditee
{
    protected function ensureAuditorFilled(int $planAuditId, string $tool): void
    {
        $ok = PemeriksaanAuditor::query()
            ->where('plan_audit_id', $planAuditId)
            ->where('tool', $tool)
            ->whereNotNull('nama_auditee')
            ->where('nama_auditee', '!=', '')
            ->exists();

        abort_unless($ok, 422, 'Isi Nama Auditor & Nama Auditee di atas sebelum menyimpan.');
    }
}
