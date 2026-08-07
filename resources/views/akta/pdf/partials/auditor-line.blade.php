{{--
    Baris kecil "Diperiksa oleh / Auditee" di bawah judul tiap section.
    Dipakai lewat @include('akta.pdf.partials.auditor-line', ['tool' => 'kas']).
    $auditors dikirim dari ReportPdfController::buildViewData() —
    PemeriksaanAuditor::where('plan_audit_id', $id)->get()->keyBy('tool').
--}}
@php($aud = $auditors[$tool] ?? null)
@if($aud)
<div style="font-size:9px;color:#6b7280;margin-bottom:6px;">
    Diperiksa oleh: <strong>{{ $aud->nama_auditor }}</strong> · Auditee: <strong>{{ $aud->nama_auditee }}</strong>
</div>
@endif
