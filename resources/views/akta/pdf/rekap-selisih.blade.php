<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Selisih – {{ $plan->no_spt ?? '-' }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 10px; color: #111; background: #e5e7eb; }
  .page-wrap {
    width: 210mm; min-height: 297mm;
    margin: 12px auto; background: #fff;
    padding: 16mm 16mm; box-shadow: 0 2px 16px rgba(0,0,0,.18);
  }

  h1 { text-align: center; font-size: 14px; font-weight: 700; letter-spacing: .3px; margin-bottom: 14px; }

  .info { display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 10px; }
  .info .row { display: flex; gap: 8px; margin-bottom: 3px; }
  .info .label { font-weight: 700; min-width: 78px; }

  .group-title { font-weight: 700; font-size: 11px; margin: 16px 0 6px; }

  table { width: 100%; border-collapse: collapse; font-size: 9.5px; margin-bottom: 4px; }
  th { background: #f3f4f6; text-align: left; padding: 5px 7px; border: 1px solid #9ca3af; font-weight: 700; }
  td { padding: 4px 7px; border: 1px solid #d1d5db; vertical-align: top; }
  .num { text-align: right; }
  .neg { color: #dc2626; }

  .empty { color: #6b7280; font-style: italic; padding: 6px 0; }

  .sign { margin-top: 40px; display: flex; justify-content: space-between; font-size: 10px; }
  .sign .box { width: 45%; text-align: center; }
  .sign .space { height: 60px; }

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
    /* min-height:297mm ada supaya .page-wrap terlihat seukuran kertas A4 saat
       dilihat di layar sebelum dicetak. Tapi @page di bawah SUDAH memberi
       margin halaman sendiri (14mm atas+bawah) — kalau min-height itu ikut
       terbawa ke cetakan, tinggi kotaknya (297mm) jadi lebih besar dari area
       cetak yang tersisa per halaman (297mm - 28mm margin = 269mm), sehingga
       SELALU meluber ke halaman kedua yang nyaris kosong walau isinya cuma
       muat setengah halaman. min-height dilepas di sini supaya tinggi
       dokumen murni mengikuti isinya. */
    .page-wrap { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    @page { size: A4 portrait; margin: 14mm 13mm; }
  }
</style>
</head>
<body>

<div class="print-bar">
  <span style="font-weight:700;font-size:13px;">📄 Rekap Selisih – {{ $plan->no_spt ?? '-' }}</span>
  <div>
    <button onclick="window.print()">🖨️ Cetak / Save PDF</button>
    <button class="close-btn" onclick="window.close()">✕ Tutup</button>
  </div>
</div>
<div class="print-spacer"></div>

<div class="page-wrap">

  <h1>REKAP SELISIH PART &amp; AHM OIL'S</h1>

  <div class="info">
    <div>
      <div class="row"><span class="label">idplan</span><span>: {{ $plan->no_spt ?? '-' }}</span></div>
      <div class="row"><span class="label">UNIT USAHA</span><span>: {{ $plan->cabang ?? '-' }}</span></div>
    </div>
    <div>
      <div class="row"><span class="label">PEMBUAT</span><span>: {{ $auditor->nama_auditor ?? $plan->kepala_tim ?? '-' }}</span></div>
      <div class="row"><span class="label">TIM</span><span>: {{ is_array($plan->tim) && count($plan->tim) ? implode(', ', $plan->tim) : ($plan->kepala_tim ?? '-') }}</span></div>
    </div>
  </div>

  @if(empty($oilItems) && empty($sparepartItems))
    <p class="empty">Tidak ada selisih pada pemeriksaan {{ $toolLabel }} untuk plan ini.</p>
  @else
    <div class="group-title">AHM OIL'S</div>
    @include('akta.pdf.partials.rekap-selisih-table', ['items' => $oilItems])

    <div class="group-title">SPAREPART</div>
    @include('akta.pdf.partials.rekap-selisih-table', ['items' => $sparepartItems])
  @endif

  <div class="sign">
    <div class="box">
      <div class="space"></div>
      Diperiksa :
    </div>
    <div class="box">
      <div class="space"></div>
      Diketahui :
    </div>
  </div>

</div>
</body>
</html>
