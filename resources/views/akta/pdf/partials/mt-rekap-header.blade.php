{{--
    Header "AUDIT MT" replika laporan lama (idplan/unit usaha/pembuat/tim),
    dipakai baik di section Report Audit maupun halaman cetak mandiri.
    Variabel: $plan, $auditor (PemeriksaanAuditor tool 'mt', bisa null),
    $judulRekap ("RUSAK" | "HILANG").
--}}
<div style="text-align:center;font-weight:700;font-size:12px;color:#111827;margin-bottom:10px;">AUDIT MT</div>
<table style="width:100%;font-size:9.5px;color:#111827;margin-bottom:10px;">
    <tr>
        <td style="width:55%;padding:1px 0;"><strong>idplan</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {{ $plan->no_spt ?? '-' }}</td>
        <td style="padding:1px 0;"><strong>PEMBUAT</strong>&nbsp;&nbsp;&nbsp;{{ $auditor->nama_auditor ?? '-' }}</td>
    </tr>
    <tr>
        <td style="padding:1px 0;"><strong>UNIT USAHA</strong>&nbsp;: {{ $plan->cabang ?? '-' }}</td>
        <td style="padding:1px 0;"><strong>TIM</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ is_array($plan->tim ?? null) ? implode(', ', $plan->tim) : ($plan->tim ?? $plan->kepala_tim ?? '-') }}</td>
    </tr>
</table>
<div style="font-weight:700;font-size:10.5px;color:#111827;margin-bottom:6px;">*REKAP TOOLS {{ $judulRekap }}</div>
