<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Tools Rusak & Hilang – {{ $plan->no_spt ?? '-' }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 10px; color: #111; background: #e5e7eb; }
  .page-wrap { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 14mm; box-shadow: 0 2px 16px rgba(0,0,0,.18); }
  .empty { color: #9ca3af; font-style: italic; }

  .print-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 999;
    background: #1e40af; color: #fff; padding: 7px 16px;
    display: flex; align-items: center; justify-content: space-between; }
  .print-bar button { background: #fff; color: #1e3a8a; border: none;
    font-size: 11px; font-weight: 700; padding: 4px 14px; border-radius: 6px; cursor: pointer; }
  .print-bar .close-btn { background: transparent; color: #fff; border: 1px solid #fff; margin-left: 8px; }
  .print-spacer { height: 40px; }

  @media print {
    body { background: #fff; }
    .print-bar, .print-spacer { display: none !important; }
    .page-wrap { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    @page { size: A4 portrait; margin: 14mm 13mm; }
  }
</style>
</head>
<body>

<div class="print-bar">
  <span style="font-weight:700;font-size:13px;">🖨️ Rekap Tools Rusak &amp; Hilang – {{ $plan->no_spt ?? '-' }}</span>
  <div>
    <button onclick="window.print()">🖨️ Cetak / Save PDF</button>
    <button class="close-btn" onclick="window.close()">✕ Tutup</button>
  </div>
</div>
@if(!empty($autoprint))
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    document.querySelector('.print-bar') && (document.querySelector('.print-bar').style.display = 'none');
    document.querySelector('.print-spacer') && (document.querySelector('.print-spacer').style.display = 'none');
    window.print();
  }, 500);
});
</script>
@endif
<div class="print-spacer"></div>

<div class="page-wrap">
  @include('akta.pdf.partials.mt-rekap-header', ['plan' => $plan, 'auditor' => $auditor, 'judulRekap' => 'RUSAK'])
  @include('akta.pdf.partials.mt-rekap-tables', ['rekap' => $mtRekap['rusak'] ?? [], 'kategori' => 'rusak'])
</div>

<div class="page-wrap" style="page-break-before:always;">
  @include('akta.pdf.partials.mt-rekap-header', ['plan' => $plan, 'auditor' => $auditor, 'judulRekap' => 'HILANG'])
  @include('akta.pdf.partials.mt-rekap-tables', ['rekap' => $mtRekap['hilang'] ?? [], 'kategori' => 'hilang'])
</div>

</body>
</html>
