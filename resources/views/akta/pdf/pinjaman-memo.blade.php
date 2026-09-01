<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Memo Pinjaman – {{ $pinjaman->jenis }} #{{ $pinjaman->id }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #e5e7eb; }
  .page-wrap { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 16mm; box-shadow: 0 2px 16px rgba(0,0,0,.18); }

  .print-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 999;
    background: #1e40af; color: #fff; padding: 7px 16px;
    display: flex; align-items: center; justify-content: space-between; }
  .print-bar button { background: #fff; color: #1e3a8a; border: none;
    font-size: 11px; font-weight: 700; padding: 4px 14px; border-radius: 6px; cursor: pointer; }
  .print-bar .close-btn { background: transparent; color: #fff; border: 1px solid #fff; margin-left: 8px; }
  .print-spacer { height: 40px; }

  .kop { text-align: center; border-bottom: 3px double #1e293b; padding-bottom: 10px; margin-bottom: 18px; }
  .kop .app-name { font-size: 17px; font-weight: 800; color: #1e293b; letter-spacing: .5px; }
  .kop .app-sub { font-size: 9.5px; color: #64748b; margin-top: 2px; }

  .judul { text-align: center; margin-bottom: 20px; }
  .judul h1 { font-size: 15px; font-weight: 800; text-decoration: underline; letter-spacing: .5px; }
  .judul .nomor { font-size: 11px; margin-top: 3px; color: #334155; }
  .judul .jenis-sub { font-size: 10px; margin-top: 2px; color: #475569; }

  .status-chip { display: inline-block; font-size: 9.5px; font-weight: 700; padding: 2px 10px;
    border-radius: 999px; }
  .status-chip.pending { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
  .status-chip.approved { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
  .status-chip.rejected { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

  p.intro { margin-bottom: 12px; text-align: justify; }
  .field-row { display: flex; margin-bottom: 5px; }
  .field-row .lbl { width: 130px; flex-shrink: 0; color: #334155; }
  .field-row .val { font-weight: 700; color: #0f172a; }
  .field-row .colon { width: 12px; flex-shrink: 0; }

  .nilai-box { display: flex; gap: 12px; margin: 14px 0; }
  .nilai-box .card { flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; text-align: center; }
  .nilai-box .card .cap { font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; color: #64748b; }
  .nilai-box .card .num { font-size: 17px; font-weight: 800; color: #1e3a8a; margin-top: 3px; }
  .nilai-box .card .sub { font-size: 9.5px; color: #475569; margin-top: 2px; font-style: italic; }

  .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 14px; margin-bottom: 16px; }
  .box-title { font-size: 9.5px; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 8px; letter-spacing: .4px; }

  table.tahapan { width: 100%; border-collapse: collapse; font-size: 10px; }
  table.tahapan th { background: #1e3a8a; color: #fff; text-align: left; padding: 6px 8px; font-size: 9.5px; }
  table.tahapan td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
  table.tahapan tr:last-child td { border-bottom: none; }
  .belum { color: #94a3b8; font-style: italic; }
  .sudah-waktu { color: #0f172a; font-weight: 600; }
  .sudah-aktor { color: #475569; }
  .ditolak-label { color: #b91c1c; font-weight: 700; }

  .ttd-wrap { display: flex; justify-content: flex-end; margin-top: 26px; }
  .ttd { width: 220px; text-align: center; font-size: 10.5px; }
  .ttd .kota-tgl { margin-bottom: 46px; }
  .ttd .nama { font-weight: 700; text-decoration: underline; }
  .ttd .jabatan { color: #475569; margin-top: 1px; }

  .footer-note { margin-top: 22px; font-size: 8.5px; color: #94a3b8; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }

  @media print {
    body { background: #fff; }
    .print-bar, .print-spacer { display: none !important; }
    .page-wrap { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    @page { size: A4 portrait; margin: 14mm 15mm; }
  }
</style>
</head>
<body>

<div class="print-bar">
  <span style="font-weight:700;font-size:13px;">🖨️ Memo Pinjaman – {{ $pinjaman->jenis }} #{{ $pinjaman->id }}</span>
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

  <div class="kop">
    <div class="app-name">SIMPAS-IAT</div>
    <div class="app-sub">Sistem Informasi Manajemen Pemeriksaan Audit — Aplikasi Audit Honda Dealer</div>
  </div>

  <div class="judul">
    <h1>MEMO PINJAMAN</h1>
    <div class="nomor">
      No. {{ $plan->no_spt ?? '-' }}@if($pinjaman->jenis)/{{ $pinjaman->jenis }}@endif/{{ $pinjaman->id }}
    </div>
    <div class="jenis-sub">{{ $jenisLabel }}</div>
    <div style="margin-top:6px;">
      <span class="status-chip {{ $pinjaman->status === 'approved' ? 'approved' : ($pinjaman->status === 'rejected' ? 'rejected' : 'pending') }}">
        {{ $statusLabel }}
      </span>
    </div>
  </div>

  <p class="intro">
    Kepada Yth. <strong>{{ $tujuan }}</strong><br>
    Dengan ini harap diberikan pinjaman uang tunai kepada:
  </p>

  <div class="field-row">
    <div class="lbl">Nama</div><div class="colon">:</div>
    <div class="val">{{ $pengaju ?: '-' }}</div>
  </div>
  @if($plan)
  <div class="field-row">
    <div class="lbl">Plan Audit</div><div class="colon">:</div>
    <div class="val">{{ $plan->no_spt ?: '-' }} — {{ $plan->cabang ?: '-' }}</div>
  </div>
  @endif

  <div class="nilai-box">
    <div class="card">
      <div class="cap">Nilai Pinjaman</div>
      <div class="num">Rp {{ number_format((float) $pinjaman->nominal, 0, ',', '.') }}</div>
    </div>
    <div class="card">
      <div class="cap">Terbilang</div>
      <div class="sub">{{ $pinjaman->terbilang ?: '-' }}</div>
    </div>
  </div>

  <p class="intro">
    Untuk {{ $keperluan }}.
  </p>
  <p class="intro">
    {{ $diperhitungkan }}
  </p>
  @if($plan?->tgl_selesai)
  <p class="intro">
    Memo ini berlaku sejak diajukan sampai dengan tanggal <strong>{{ $plan->tgl_selesai->format('d/m/Y') }}</strong>
    (mengikuti periode audit terkait).
  </p>
  @else
  <p class="intro">
    Memo ini berlaku sejak diajukan sampai dengan pinjaman ini dipertanggungjawabkan sepenuhnya.
  </p>
  @endif

  <div class="box">
    <div class="box-title">Tahapan Approval (Real-time)</div>
    <table class="tahapan">
      <thead>
        <tr><th style="width:34%;">Tahap</th><th style="width:30%;">Waktu</th><th>Oleh</th></tr>
      </thead>
      <tbody>
        @foreach($tahapan as $t)
        <tr>
          <td class="{{ !empty($t['ditolak']) ? 'ditolak-label' : '' }}">{{ $t['label'] }}</td>
          @if($t['waktu'])
            <td class="sudah-waktu">{{ \Illuminate\Support\Carbon::parse($t['waktu'])->format('d/m/Y H:i') }}</td>
            <td class="sudah-aktor">{{ $t['aktor'] ?: '-' }}</td>
          @else
            <td class="belum" colspan="2">Belum terjadi</td>
          @endif
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($pinjaman->catatan)
  <div class="field-row" style="margin-top:-6px;margin-bottom:16px;">
    <div class="lbl">Catatan</div><div class="colon">:</div>
    <div class="val" style="font-weight:400;">{{ $pinjaman->catatan }}</div>
  </div>
  @endif

  <div class="ttd-wrap">
    <div class="ttd">
      <div class="kota-tgl">Diterbitkan, {{ now()->format('d/m/Y') }}</div>
      <div class="nama">&nbsp;</div>
      <div class="jabatan">Chief Operating Officer</div>
    </div>
  </div>

  <div class="footer-note">
    Dokumen ini digenerate otomatis oleh sistem SIMPAS-IAT pada {{ now()->format('d/m/Y H:i:s') }}
    dan mencerminkan status pinjaman terkini saat dicetak.
  </div>

</div>

</body>
</html>
