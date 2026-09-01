<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Audit – {{ $plan->no_spt ?? '-' }}</title>
@vite(['resources/js/akta-report-pdf-lampiran.js'])
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: Arial, sans-serif; font-size: 10px; color: #111; background: #e5e7eb;
  }
  .page-wrap {
    width: 210mm; min-height: 297mm;
    margin: 12px auto; background: #fff;
    padding: 14mm 14mm 14mm 14mm;
    box-shadow: 0 2px 16px rgba(0,0,0,.18);
  }

  /* ── Cover / header ── */
  .cover { text-align: center; padding: 24px 16px 18px; border-bottom: 3px solid #1e40af; margin-bottom: 16px; }
  .cover h1 { font-size: 15px; font-weight: 700; color: #1e3a8a; letter-spacing: .5px; }
  .cover h2 { font-size: 11px; color: #374151; margin-top: 3px; }
  .cover .meta { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin-top: 10px; font-size: 9px; color: #6b7280; }
  .cover .meta span strong { color: #1f2937; }

  /* ── Section header ──
     page-break-inside:avoid TIDAK dipasang di .section: section seperti Kas
     (Kas Besar + Kas Kecil, bisa puluhan baris) sering lebih tinggi dari sisa
     ruang kosong di halaman saat ini. Kalau seluruh section dipaksa "avoid"
     dan tidak muat, browser memindahkan SELURUH section ke halaman
     berikutnya alih-alih memotongnya di titik yang wajar — sisa halaman
     sebelumnya jadi kosong (persis kasus yang dilaporkan: halaman 1 cuma
     berisi judul, section pertama meloncat penuh ke halaman 2). Sebagai
     gantinya, cukup judul section yang dijaga tidak sendirian di ujung
     halaman lewat page-break-after:avoid di bawah — badan section tetap
     boleh terpotong wajar antar baris tabel saat memang panjang. */
  .section { margin-bottom: 14px; }
  .section-title {
    background: #1e40af; color: #fff;
    padding: 4px 9px; font-size: 10px; font-weight: 700; letter-spacing: .4px;
    border-radius: 4px 4px 0 0;
    page-break-after: avoid; break-after: avoid-page;
    page-break-inside: avoid;
  }
  .section-body { border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 4px 4px; padding: 8px; }

  /* ── Tables ── */
  table { width: 100%; border-collapse: collapse; font-size: 9px; }
  th { background: #f3f4f6; text-align: left; padding: 4px 6px; border: 1px solid #d1d5db; font-weight: 700; color: #374151; }
  td { padding: 3px 6px; border: 1px solid #e5e7eb; vertical-align: top; }
  tr:nth-child(even) td { background: #f9fafb; }

  /* ── Key-value pairs ── */
  .kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px; }
  .kv { display: flex; gap: 4px; }
  .kv-label { font-weight: 700; min-width: 100px; color: #374151; }
  .kv-val { color: #111; }

  /* ── Status badges ── */
  .badge { display: inline-block; padding: 1px 5px; border-radius: 99px; font-size: 8px; font-weight: 700; }
  .badge-open { background: #dbeafe; color: #1d4ed8; }
  .badge-progress { background: #fef3c7; color: #92400e; }
  .badge-closed, .badge-selesai, .badge-done { background: #d1fae5; color: #065f46; }

  /* ── Empty state ── */
  .empty { color: #9ca3af; font-style: italic; padding: 6px 0; }

  /* ── Data Karyawan (halaman pertama) ── */
  .karyawan-grid { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
  .karyawan-card { width: 62px; text-align: center; page-break-inside: avoid; }
  .karyawan-card .foto {
    width: 56px; height: 56px; margin: 0 auto 3px; border-radius: 4px;
    border: 1px solid #d1d5db; object-fit: cover; display: block;
  }
  .karyawan-card .foto-placeholder {
    width: 56px; height: 56px; margin: 0 auto 3px; border-radius: 4px;
    background: #e5e7eb; color: #6b7280; display: flex; align-items: center;
    justify-content: center; font-size: 16px; font-weight: 700;
  }
  .karyawan-card .nama { font-size: 8px; font-weight: 700; color: #1f2937; line-height: 1.2; word-break: break-word; }
  .karyawan-card .jabatan { font-size: 7px; color: #6b7280; line-height: 1.2; word-break: break-word; }

  /* ── Rekap Selisih (dipakai lewat partials/rekap-selisih-table di section
     HGP & RSA HGP) ── */
  .group-title { font-weight: 700; font-size: 11px; margin: 12px 0 6px; color: #1e3a8a; }
  .num { text-align: right; }
  .neg { color: #dc2626; }

  /* ── Print controls ── */
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
    /* min-height:297mm ada supaya .page-wrap terlihat seukuran kertas A4 di
       layar. @page di bawah sudah punya margin halaman sendiri (14mm atas+
       bawah) — kalau min-height itu ikut terbawa ke cetakan, tinggi kotaknya
       (297mm) lebih besar dari area cetak yang tersisa per halaman (269mm),
       sehingga SELALU ada satu halaman terakhir yang nyaris kosong meski
       section paling akhir sudah selesai jauh sebelum ujung halaman itu.
       Dilepas di sini supaya tinggi dokumen murni mengikuti isinya. */
    .page-wrap { width: 100%; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    @page { size: A4 portrait; margin: 14mm 13mm; }
    /* Section lebar (Piutang Reguler/CDN, banyak kolom) dicetak landscape agar
       tidak ada kolom yang terpotong / perlu digeser saat dibaca dari PDF. */
    @page landscape-section { size: A4 landscape; margin: 12mm 10mm; }
    .section-landscape {
      page: landscape-section;
      page-break-before: always;
      page-break-after: always;
    }
    /* Kontainer geser-samping hanya berguna di layar. Saat dicetak, bagian yang
       harus digeser tidak ikut tercetak — kolom paling kanan hilang tanpa jejak.
       Section dengan tabel lebar sudah dicetak melintang di atas; ini pengaman
       tambahan supaya isi yang tetap kelebihan lebar terlihat meluber, bukan
       terpotong diam-diam. */
    .tbl-scroll { overflow: visible !important; }

    /* Jaring pengaman terakhir untuk lebar tabel.
       Aturan @page bernama (landscape-section di atas) tidak dijamin dihormati
       semua browser/versi — kalau diabaikan, tabel min-width 800-900px tetap
       dicetak di halaman tegak yang cuma muat ~695px dan kolom kanannya hilang
       lagi. Karena itu lebar minimumnya dilepas khusus saat mencetak: tabel
       menyesuaikan lebar halaman berapa pun orientasinya. Kalau landscape
       berhasil, tabel memakai ruang lebar itu; kalau tidak, kolomnya jadi lebih
       rapat tapi TIDAK ADA yang hilang. Lebar minimum tetap berlaku di layar,
       tempat kontainer geser memang berfungsi. */
    .tbl-scroll > table { min-width: 0 !important; width: 100% !important; }

    /* Nama part, keterangan, dan kolom teks lain boleh dipenggal antar baris
       saat ruangnya sempit — tanpa ini kata panjang memaksa tabel melebar lagi
       dan membatalkan aturan di atas. */
    .tbl-scroll > table td,
    .tbl-scroll > table th { word-break: break-word; overflow-wrap: anywhere; }
  }
</style>
</head>
<body>

<div class="print-bar">
  <span style="font-weight:700;font-size:13px;">📄 Laporan Audit – {{ $plan->no_spt ?? '-' }}</span>
  <div>
    <button onclick="printReport()">🖨️ Cetak / Save PDF</button>
    <button onclick="downloadPdf()" style="background:#22c55e;color:#fff;border:none;font-size:11px;font-weight:700;padding:4px 14px;border-radius:6px;cursor:pointer;margin-left:6px;">⬇ Download PDF</button>
    <button class="close-btn" onclick="window.close()">✕ Tutup</button>
  </div>
</div>
<script>
// Isi lampiran PDF dirender async oleh akta-report-pdf-lampiran.js (pdf.js),
// yang mengisi window.__lampiranPdfReady dengan Promise-nya. Kalau cetak/save
// dipicu sebelum rendering itu selesai, hasil cetak masih menampilkan
// "Memuat isi PDF…" alih-alih isinya — jadi tombol cetak/download menunggu
// promise itu dulu (dengan batas waktu jaga-jaga kalau rendering gagal diam).
function waitForLampiran() {
  var ready = window.__lampiranPdfReady;
  if (ready && typeof ready.then === 'function') {
    return Promise.race([
      ready,
      new Promise(function(resolve) { setTimeout(resolve, 15000); })
    ]);
  }
  return Promise.resolve();
}

function printReport() {
  waitForLampiran().then(function() { window.print(); });
}

function downloadPdf() {
  var bar = document.querySelector('.print-bar');
  var spacer = document.querySelector('.print-spacer');
  if (bar) bar.style.display = 'none';
  if (spacer) spacer.style.display = 'none';
  waitForLampiran().then(function() {
    window.print();
    setTimeout(function() {
      if (bar) bar.style.display = '';
      if (spacer) spacer.style.display = '';
    }, 1000);
  });
}
@if(!empty($autoprint))
window.addEventListener('load', function() {
  setTimeout(function() {
    document.querySelector('.print-bar') && (document.querySelector('.print-bar').style.display = 'none');
    document.querySelector('.print-spacer') && (document.querySelector('.print-spacer').style.display = 'none');
    waitForLampiran().then(function() { window.print(); });
  }, 800);
});
@endif
</script>
<div class="print-spacer"></div>
<div class="page-wrap">

{{-- ── COVER ── --}}
<div class="cover">
  <h1>LAPORAN HASIL AUDIT INTERNAL</h1>
  <h2>{{ $plan->no_spt ?? '-' }} &nbsp;·&nbsp; {{ $plan->cabang ?? '-' }}</h2>
  <div class="meta">
    <span><strong>Jenis Audit:</strong> {{ $plan->jenis_audit ?? '-' }}</span>
    <span><strong>Tgl Plan:</strong> {{ $plan->tgl_plan ? \Carbon\Carbon::parse($plan->tgl_plan)->format('d/m/Y') : '-' }}</span>
    <span><strong>Tgl Mulai:</strong> {{ $plan->tgl_mulai ? \Carbon\Carbon::parse($plan->tgl_mulai)->format('d/m/Y') : '-' }}</span>
    <span><strong>Tgl Selesai:</strong> {{ $plan->tgl_selesai ? \Carbon\Carbon::parse($plan->tgl_selesai)->format('d/m/Y') : '-' }}</span>
    <span><strong>Kepala Tim:</strong> {{ $plan->kepala_tim ?? '-' }}</span>
    <span><strong>Tim:</strong> {{ is_array($plan->tim) ? implode(', ', $plan->tim) : ($plan->tim ?? '-') }}</span>
    <span><strong>Status:</strong> {{ strtoupper($plan->status ?? '-') }}</span>
    <span><strong>Dicetak:</strong> {{ now()->format('d/m/Y H:i') }}</span>
  </div>
</div>

@if(!empty($karyawans) && count($karyawans))
{{-- ── DATA KARYAWAN UNIT USAHA ── --}}
<div class="section">
  <div class="section-title">DATA KARYAWAN {{ $plan->cabang ?? '-' }}</div>
  <div class="section-body">
    <div class="karyawan-grid">
      @foreach($karyawans as $kar)
      <div class="karyawan-card">
        @if($kar->photo_url)
          <img class="foto" src="{{ $kar->photo_url }}" alt="{{ $kar->nama }}">
        @else
          <div class="foto-placeholder">{{ strtoupper(mb_substr($kar->nama, 0, 1)) }}</div>
        @endif
        <div class="nama">{{ $kar->nama }}</div>
        <div class="jabatan">{{ $kar->jabatan }}</div>
      </div>
      @endforeach
    </div>
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     1. PEMERIKSAAN KAS
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['kas'] ?? true))
<div class="section">
  <div class="section-title">1. PEMERIKSAAN KAS</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'kas'])
  <div class="section-body">
    @if(!$kas)
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $k = $kas;
        $d   = $k->detail_json ?? [];
        $kb  = $d['kas_besar'] ?? [];
        $kk  = $d['kas_kecil'] ?? [];
        $pcn = $d['pecahan']   ?? [];

        $kbPenerimaan = $kb['penerimaan'] ?? [];
        $kbPengeluaran = $kb['pengeluaran'] ?? [];
        $kkBon = $kk['bon'] ?? [];

        $kbSaldoAwal    = (float)($kb['saldo_awal'] ?? 0);
        $kbTotalTerima  = array_sum(array_column($kbPenerimaan, 'jumlah'));
        $kbTotalKeluar  = array_sum(array_column($kbPengeluaran, 'jumlah'));
        $kbSaldoBuku    = $kbSaldoAwal + $kbTotalTerima - $kbTotalKeluar;
        $kbSaldoFisik   = array_sum(array_map(fn($p) => ($p['nominal']??0)*($p['lembar_besar']??0), $pcn));
        $kbSelisih      = $kbSaldoFisik - $kbSaldoBuku;

        $kkCadangan  = (float)($kk['cadangan'] ?? 0);
        $kkTotalBon  = array_sum(array_column($kkBon, 'jumlah'));
        $kkSaldoBuku = $kkCadangan - $kkTotalBon;
        $kkSaldoFisik = array_sum(array_map(fn($p) => ($p['nominal']??0)*($p['lembar_kecil']??0), $pcn));
        $kkSelisih   = $kkSaldoFisik - $kkSaldoBuku;

        $totalFisik  = (float)($k->saldo_fisik ?? ($kbSaldoFisik + $kkSaldoFisik));
        $totalBuku   = (float)($k->saldo_buku  ?? ($kbSaldoBuku  + $kkSaldoBuku));
        $totalSelisih = (float)($k->selisih    ?? ($kbSelisih    + $kkSelisih));

        $fmt = fn($v) => 'Rp '.number_format((float)$v, 0, ',', '.');
      @endphp

      {{-- ── Ringkasan ── --}}
      <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:6px;padding:10px 14px;margin-bottom:14px;">
        <div style="font-weight:700;font-size:12px;color:#1e3a8a;margin-bottom:8px;">RINGKASAN PEMERIKSAAN KAS</div>
        <table style="width:100%;font-size:10px;">
          <thead>
            <tr style="background:#e0e7ff;">
              <th style="text-align:left;padding:4px 8px;border:1px solid #c7d2fe;">Pos Kas</th>
              <th style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">Saldo Buku</th>
              <th style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">Saldo Fisik</th>
              <th style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">Selisih</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding:4px 8px;border:1px solid #e5e7eb;">Kas Besar</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ $fmt($kbSaldoBuku) }}</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ $fmt($kbSaldoFisik) }}</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;color:{{ $kbSelisih != 0 ? '#dc2626' : '#059669' }};">
                {{ $fmt($kbSelisih) }}
              </td>
            </tr>
            <tr style="background:#f9fafb;">
              <td style="padding:4px 8px;border:1px solid #e5e7eb;">Kas Kecil</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ $fmt($kkSaldoBuku) }}</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ $fmt($kkSaldoFisik) }}</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;color:{{ $kkSelisih != 0 ? '#dc2626' : '#059669' }};">
                {{ $fmt($kkSelisih) }}
              </td>
            </tr>
            <tr style="background:#e0e7ff;font-weight:700;">
              <td style="padding:4px 8px;border:1px solid #c7d2fe;">TOTAL</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">{{ $fmt($totalBuku) }}</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">{{ $fmt($totalFisik) }}</td>
              <td style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;color:{{ $totalSelisih != 0 ? '#dc2626' : '#059669' }};">
                {{ $fmt($totalSelisih) }}
              </td>
            </tr>
          </tbody>
        </table>
        @if($k->keterangan)
        <div style="margin-top:6px;font-size:10px;"><strong>Keterangan:</strong> {{ $k->keterangan }}</div>
        @endif
      </div>

      {{-- ── Kas Besar ── --}}
      <div style="margin-bottom:16px;">
        <div style="font-weight:700;font-size:11px;color:#1d4ed8;border-bottom:2px solid #1d4ed8;padding-bottom:3px;margin-bottom:8px;">A. KAS BESAR</div>
        <div style="display:flex;gap:20px;margin-bottom:8px;font-size:10px;">
          <span><strong>Tgl H-1:</strong> {{ $kb['saldo_awal_tgl'] ?? '-' }}</span>
          <span><strong>Saldo Awal (H-1):</strong> {{ $fmt($kbSaldoAwal) }}</span>
          @if($kb['keterangan'] ?? null)<span><strong>Keterangan:</strong> {{ $kb['keterangan'] }}</span>@endif
        </div>

        @if(count($kbPenerimaan))
        <div style="margin-bottom:6px;font-size:10px;font-weight:700;color:#374151;">Penerimaan</div>
        <table style="margin-bottom:8px;">
          <thead><tr><th>#</th><th>Tanggal</th><th>Keterangan</th><th style="text-align:right">Jumlah</th></tr></thead>
          <tbody>
            @foreach($kbPenerimaan as $ii => $r)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $r['tanggal'] ?? '-' }}</td>
              <td>{{ $r['keterangan'] ?? '-' }}</td>
              <td style="text-align:right">{{ $fmt($r['jumlah'] ?? 0) }}</td>
            </tr>
            @endforeach
            <tr style="font-weight:700;background:#f0fdf4;">
              <td colspan="3" style="text-align:right">Total Penerimaan</td>
              <td style="text-align:right;color:#059669;">{{ $fmt($kbTotalTerima) }}</td>
            </tr>
          </tbody>
        </table>
        @endif

        @if(count($kbPengeluaran))
        <div style="margin-bottom:6px;font-size:10px;font-weight:700;color:#374151;">Pengeluaran</div>
        <table style="margin-bottom:8px;">
          <thead><tr><th>#</th><th>Tanggal</th><th>Keterangan</th><th style="text-align:right">Jumlah</th></tr></thead>
          <tbody>
            @foreach($kbPengeluaran as $ii => $r)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $r['tanggal'] ?? '-' }}</td>
              <td>{{ $r['keterangan'] ?? '-' }}</td>
              <td style="text-align:right">{{ $fmt($r['jumlah'] ?? 0) }}</td>
            </tr>
            @endforeach
            <tr style="font-weight:700;background:#fff1f2;">
              <td colspan="3" style="text-align:right">Total Pengeluaran</td>
              <td style="text-align:right;color:#dc2626;">{{ $fmt($kbTotalKeluar) }}</td>
            </tr>
          </tbody>
        </table>
        @endif

        <table style="width:200px;margin-left:auto;font-size:10px;border:1px solid #d1d5db;">
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Buku</td><td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">{{ $fmt($kbSaldoBuku) }}</td></tr>
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Fisik</td><td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">{{ $fmt($kbSaldoFisik) }}</td></tr>
          <tr style="background:{{ $kbSelisih!=0 ? '#fee2e2' : '#f0fdf4' }};">
            <td style="padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">Selisih</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700;color:{{ $kbSelisih!=0 ? '#dc2626' : '#059669' }};">{{ $fmt($kbSelisih) }}</td>
          </tr>
        </table>
      </div>

      {{-- ── Kas Kecil ── --}}
      <div style="margin-bottom:16px;">
        <div style="font-weight:700;font-size:11px;color:#7c3aed;border-bottom:2px solid #7c3aed;padding-bottom:3px;margin-bottom:8px;">B. KAS KECIL</div>
        <div style="display:flex;gap:20px;margin-bottom:8px;font-size:10px;">
          <span><strong>Cadangan Kas Kecil:</strong> {{ $fmt($kkCadangan) }}</span>
          @if($kk['keterangan'] ?? null)<span><strong>Keterangan:</strong> {{ $kk['keterangan'] }}</span>@endif
        </div>

        @if(count($kkBon))
        <div style="margin-bottom:6px;font-size:10px;font-weight:700;color:#374151;">Bon / Pengeluaran</div>
        <table style="margin-bottom:8px;">
          <thead><tr><th>#</th><th>Tanggal</th><th>Keterangan</th><th style="text-align:right">Jumlah</th></tr></thead>
          <tbody>
            @foreach($kkBon as $ii => $r)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $r['tanggal'] ?? '-' }}</td>
              <td>{{ $r['keterangan'] ?? '-' }}</td>
              <td style="text-align:right">{{ $fmt($r['jumlah'] ?? 0) }}</td>
            </tr>
            @endforeach
            <tr style="font-weight:700;background:#fff1f2;">
              <td colspan="3" style="text-align:right">Total Bon</td>
              <td style="text-align:right;color:#dc2626;">{{ $fmt($kkTotalBon) }}</td>
            </tr>
          </tbody>
        </table>
        @endif

        <table style="width:200px;margin-left:auto;font-size:10px;border:1px solid #d1d5db;">
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Buku</td><td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">{{ $fmt($kkSaldoBuku) }}</td></tr>
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Fisik</td><td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">{{ $fmt($kkSaldoFisik) }}</td></tr>
          <tr style="background:{{ $kkSelisih!=0 ? '#fee2e2' : '#f0fdf4' }};">
            <td style="padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">Selisih</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700;color:{{ $kkSelisih!=0 ? '#dc2626' : '#059669' }};">{{ $fmt($kkSelisih) }}</td>
          </tr>
        </table>
      </div>

      {{-- ── Tabel Pecahan Uang ── --}}
      @if(count($pcn))
      <div>
        <div style="font-weight:700;font-size:11px;color:#374151;border-bottom:2px solid #d1d5db;padding-bottom:3px;margin-bottom:8px;">C. RINCIAN PECAHAN UANG</div>
        <table>
          <thead>
            <tr>
              <th>Nominal</th>
              <th style="text-align:center">Lembar Besar</th>
              <th style="text-align:right">Jumlah Besar</th>
              <th style="text-align:center">Lembar Kecil</th>
              <th style="text-align:right">Jumlah Kecil</th>
            </tr>
          </thead>
          <tbody>
            @foreach($pcn as $p)
            @php
              $nom = (float)($p['nominal'] ?? 0);
              $lb  = (int)($p['lembar_besar'] ?? 0);
              $lk  = (int)($p['lembar_kecil'] ?? 0);
            @endphp
            @if($lb > 0 || $lk > 0)
            <tr>
              <td>{{ number_format($nom, 0, ',', '.') }}</td>
              <td style="text-align:center">{{ $lb }}</td>
              <td style="text-align:right">{{ $fmt($nom * $lb) }}</td>
              <td style="text-align:center">{{ $lk }}</td>
              <td style="text-align:right">{{ $fmt($nom * $lk) }}</td>
            </tr>
            @endif
            @endforeach
            <tr style="font-weight:700;background:#f3f4f6;">
              <td>TOTAL</td>
              <td></td>
              <td style="text-align:right;color:#1d4ed8;">{{ $fmt($kbSaldoFisik) }}</td>
              <td></td>
              <td style="text-align:right;color:#7c3aed;">{{ $fmt($kkSaldoFisik) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      @endif

      {{-- ── Register Blanko yang Belum Digunakan ── --}}
      @php
        $blankoH1 = $d['blanko_h1'] ?? [];
        $blankoH2 = $d['blanko_h2'] ?? [];
      @endphp
      @if(count($blankoH1) || count($blankoH2))
      <div style="margin-top:16px;">
        <div style="font-weight:700;font-size:11px;color:#374151;border-bottom:2px solid #d1d5db;padding-bottom:3px;margin-bottom:8px;">D. REGISTER BLANKO YANG BELUM DIGUNAKAN</div>
        <div style="display:flex;gap:16px;">
          <div style="flex:1;">
            <div style="margin-bottom:4px;font-size:10px;font-weight:700;color:#374151;">H1</div>
            @if(count($blankoH1))
            <table>
              <thead><tr><th>Jenis</th><th>Nomor Range Blanko</th></tr></thead>
              <tbody>
                @foreach($blankoH1 as $b)
                <tr>
                  <td>{{ $b['jenis'] ?? '-' }}</td>
                  <td>{{ $b['nomor'] ?? '-' }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
            @else
            <p class="empty">Belum ada data.</p>
            @endif
          </div>
          <div style="flex:1;">
            <div style="margin-bottom:4px;font-size:10px;font-weight:700;color:#374151;">H2</div>
            @if(count($blankoH2))
            <table>
              <thead><tr><th>Jenis</th><th>Nomor Range Blanko</th></tr></thead>
              <tbody>
                @foreach($blankoH2 as $b)
                <tr>
                  <td>{{ $b['jenis'] ?? '-' }}</td>
                  <td>{{ $b['nomor'] ?? '-' }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
            @else
            <p class="empty">Belum ada data.</p>
            @endif
          </div>
        </div>
      </div>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     2. ANALISA PLAFON SMH
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['plafon'] ?? true))
<div class="section">
  <div class="section-title">2. ANALISA PLAFON SMH</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'plafon'])
  <div class="section-body">
  @php
    $pl   = $plafon;
    $fmt2 = fn($v) => 'Rp '.number_format((float)$v, 0, ',', '.');
    $hasPl = $pl['totalUnit'] > 0 || $pl['plafonNilai'] !== null;
  @endphp
  @if(!$hasPl)
    <p class="empty">Belum ada data onhand SMH untuk analisa plafon.</p>
  @else

    {{-- ── Ringkasan Plafon ── --}}
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 14px;margin-bottom:16px;">
      <div style="font-weight:700;font-size:12px;color:#14532d;margin-bottom:10px;">RINGKASAN ANALISA PLAFON</div>
      <div class="kv-grid" style="margin-bottom:10px;">
        <div class="kv"><span class="kv-label">Cabang:</span><span class="kv-val" style="font-weight:700">{{ $pl['cabang'] }}</span></div>
        <div class="kv"><span class="kv-label">Nama Unit:</span><span class="kv-val">{{ $pl['namaUnit'] }}</span></div>
        <div class="kv"><span class="kv-label">Wilayah:</span><span class="kv-val">{{ $pl['wilayah'] }}</span></div>
        <div class="kv"><span class="kv-label">Nama Plafon:</span><span class="kv-val">{{ $pl['plafonNama'] ?? '-' }}</span></div>
      </div>
      <table style="font-size:10px;width:100%;">
        <thead>
          <tr style="background:#dcfce7;">
            <th style="text-align:left;padding:5px 8px;border:1px solid #bbf7d0;">Keterangan</th>
            <th style="text-align:right;padding:5px 8px;border:1px solid #bbf7d0;">Nilai</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">Total Unit SMH (Onhand)</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;">{{ number_format($pl['totalUnit'], 0, ',', '.') }} unit</td>
          </tr>
          <tr style="background:#f9fafb;">
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">Total Nilai SMH (Harga Pokok)</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;color:#1d4ed8;">{{ $fmt2($pl['totalNilai']) }}</td>
          </tr>
          <tr>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">Nilai Plafon yang Ditetapkan</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;color:#7c3aed;">{{ $pl['plafonNilai'] !== null ? $fmt2($pl['plafonNilai']) : 'Tidak ada data plafon' }}</td>
          </tr>
          @if($pl['sisaTotal'] !== null)
          <tr style="background:{{ $pl['sisaTotal'] > 0 ? '#f0fdf4' : '#fff1f2' }};font-weight:700;">
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">Sisa Cover (Plafon − Nilai SMH)</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;color:{{ $pl['sisaTotal'] > 0 ? '#059669' : '#dc2626' }};">{{ $fmt2($pl['sisaTotal']) }}</td>
          </tr>
          @endif
          @if($pl['persentase'] !== null)
          <tr style="background:{{ $pl['persentase'] <= 100 ? '#f0fdf4' : '#fff1f2' }};">
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">Persentase Penggunaan Plafon</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;font-size:13px;color:{{ $pl['persentase'] <= 80 ? '#059669' : ($pl['persentase'] <= 100 ? '#d97706' : '#dc2626') }};">
              {{ $pl['persentase'] }}%
            </td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>

    {{-- ── Indikator visual persentase ── --}}
    @if($pl['persentase'] !== null)
    @php
      $pct = min(100, $pl['persentase']);
      $barColor = $pct <= 80 ? '#16a34a' : ($pct <= 100 ? '#d97706' : '#dc2626');
    @endphp
    <div style="margin-bottom:16px;">
      <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:4px;">Tingkat Penggunaan Plafon: {{ $pl['persentase'] }}%</div>
      <div style="background:#e5e7eb;border-radius:99px;height:14px;overflow:hidden;">
        <div style="width:{{ $pct }}%;background:{{ $barColor }};height:14px;border-radius:99px;display:flex;align-items:center;justify-content:center;">
          <span style="color:#fff;font-size:9px;font-weight:700;">{{ $pl['persentase'] }}%</span>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:9px;color:#6b7280;margin-top:2px;">
        <span>0%</span><span style="color:#d97706">80%</span><span style="color:#dc2626">100%</span>
      </div>
    </div>
    @endif

    {{-- ── Per Gudang / Sub-Unit ── --}}
    @if(count($pl['perUnit']))
    <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:6px;">Detail Per Gudang / Sub-Unit</div>
    <table style="font-size:9.5px;">
      <thead>
        <tr>
          <th>#</th>
          <th>Gudang / Sub-Unit</th>
          <th style="text-align:center">Total Unit</th>
          <th style="text-align:center">Ada Harga</th>
          <th style="text-align:center">Tanpa Harga</th>
          <th style="text-align:right">Total Nilai SMH</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pl['perUnit'] as $gi => $gu)
        <tr>
          <td>{{ $gi + 1 }}</td>
          <td style="font-weight:600">{{ $gu['gudang'] }}</td>
          <td style="text-align:center">{{ $gu['totalUnit'] }}</td>
          <td style="text-align:center;color:#059669;font-weight:700">{{ $gu['ditemukan'] }}</td>
          <td style="text-align:center;color:{{ $gu['tidakDitemukan'] > 0 ? '#d97706' : '#059669' }};font-weight:700">{{ $gu['tidakDitemukan'] }}</td>
          <td style="text-align:right;font-weight:700">{{ $fmt2($gu['totalNilai']) }}</td>
        </tr>
        @if(count($gu['detail']))
        <tr style="background:#f8fafc;">
          <td colspan="6" style="padding:4px 8px;">
            <table style="width:100%;font-size:9px;border-collapse:collapse;">
              <thead>
                <tr style="background:#f1f5f9;">
                  <th style="padding:2px 6px;border:1px solid #e2e8f0;">No Rangka</th>
                  <th style="padding:2px 6px;border:1px solid #e2e8f0;">No Mesin</th>
                  <th style="padding:2px 6px;border:1px solid #e2e8f0;">Kode Model</th>
                  <th style="padding:2px 6px;border:1px solid #e2e8f0;">Nama SMH</th>
                  {{-- Harga per unit sengaja tidak ditampilkan di laporan. Nilai
                       agregatnya tetap ada di kolom Total Nilai SMH dan di ringkasan
                       plafon, yang memang jadi inti analisisnya. --}}
                </tr>
              </thead>
              <tbody>
                @foreach($gu['detail'] as $det)
                <tr style="{{ $det['harga'] === null ? 'color:#d97706;' : '' }}">
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;font-family:monospace;">{{ $det['noRangka'] ?? '-' }}</td>
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;font-family:monospace;">{{ $det['noMesin'] ?? '-' }}</td>
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;">{{ $det['kodeModel'] ?? '-' }}</td>
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;">{{ $det['namaSmh'] ?? '— harga tidak ditemukan —' }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </td>
        </tr>
        @endif
        @endforeach
        <tr style="background:#dcfce7;font-weight:700;">
          <td colspan="2" style="text-align:right">TOTAL</td>
          <td style="text-align:center">{{ $pl['totalUnit'] }}</td>
          <td colspan="2"></td>
          <td style="text-align:right">{{ $fmt2($pl['totalNilai']) }}</td>
        </tr>
      </tbody>
    </table>
    @endif

  @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     3. PEMERIKSAAN SMH & PERLENGKAPAN
     ═══════════════════════════════════════════════ --}}
@if((($visibleTabs['smh'] ?? true) || ($visibleTabs['perlengkapan'] ?? true)))
<div class="section">
  <div class="section-title">3. PEMERIKSAAN SMH (Stock Motor Honda) &amp; PERLENGKAPAN</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'smh'])
  @include('akta.pdf.partials.auditor-line', ['tool' => 'perlengkapan'])
  <div class="section-body">

    {{-- ── A. SMH Cek Fisik Per Unit ── --}}
    <div style="font-weight:700;font-size:11px;color:#1d4ed8;border-bottom:2px solid #1d4ed8;padding-bottom:3px;margin-bottom:10px;">A. CEK FISIK UNIT SMH</div>
    @if($smh->isEmpty())
      <p class="empty">Belum ada data cek fisik SMH.</p>
    @else
      @foreach($smh as $s)
      @php
        $allItems = $s->items ?? collect();
        $adaItems = $allItems->where('status_fisik', 'ada');
        $tidakItems = $allItems->where('status_fisik', 'tidak');
      @endphp
      {{-- Ringkasan SMH --}}
      <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:6px;padding:8px 12px;margin-bottom:10px;">
        <div class="kv-grid">
          <div class="kv"><span class="kv-label">No SPT:</span><span class="kv-val">{{ $s->no_spt ?? '-' }}</span></div>
          <div class="kv"><span class="kv-label">Cabang:</span><span class="kv-val">{{ $s->cabang ?? '-' }}</span></div>
          <div class="kv"><span class="kv-label">Tgl Onhand:</span><span class="kv-val">{{ $s->tgl_onhand ? \Carbon\Carbon::parse($s->tgl_onhand)->format('d/m/Y') : '-' }}</span></div>
          <div class="kv"><span class="kv-label">Pemeriksa:</span><span class="kv-val">{{ $s->nama_pemeriksa ?? '-' }}</span></div>
        </div>
        <div style="margin-top:6px;display:flex;gap:20px;font-size:10px;">
          <span style="background:#dbeafe;padding:2px 10px;border-radius:99px;color:#1d4ed8;font-weight:700;">Total Unit: {{ $allItems->count() }}</span>
          <span style="background:#d1fae5;padding:2px 10px;border-radius:99px;color:#065f46;font-weight:700;">Ditemukan: {{ $adaItems->count() }}</span>
          <span style="background:#fee2e2;padding:2px 10px;border-radius:99px;color:#991b1b;font-weight:700;">Tidak Ditemukan: {{ $tidakItems->count() }}</span>
        </div>
      </div>

      @if($allItems->count())
      <table style="margin-bottom:16px;font-size:9.5px;">
        <thead>
          <tr>
            <th style="width:28px">#</th>
            <th>No Rangka</th>
            <th>No Mesin</th>
            <th>Kode Model</th>
            <th>Warna</th>
            <th>Gudang</th>
            <th>No SPB</th>
            <th>Tgl SPB</th>
            <th>Status Fisik</th>
            <th>Perlengkapan</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($allItems as $ii => $item)
          @php
            $plJson = $item->perlengkapan_json ?? [];
            $plAda  = collect($plJson)->where('ada', true)->pluck('nama')->join(', ');
            $plTdk  = collect($plJson)->where('ada', false)->pluck('nama')->join(', ');
            $rowBg  = ($item->status_fisik === 'tidak_ada') ? 'background:#fff1f2;' : '';
          @endphp
          <tr style="{{ $rowBg }}">
            <td>{{ (int)$ii + 1 }}</td>
            <td style="font-family:monospace;font-size:9px;">{{ $item->no_rangka ?? '-' }}</td>
            <td style="font-family:monospace;font-size:9px;">{{ $item->no_mesin ?? '-' }}</td>
            <td>{{ $item->kode_model ?? '-' }}</td>
            <td>{{ $item->warna ?? '-' }}</td>
            <td>{{ $item->gudang ?? '-' }}</td>
            <td>{{ $item->no_spb ?? '-' }}</td>
            <td>{{ $item->tgl_spb ? \Carbon\Carbon::parse($item->tgl_spb)->format('d/m/Y') : '-' }}</td>
            <td style="font-weight:700;color:{{ ($item->status_fisik === 'ada') ? '#059669' : (($item->status_fisik === 'tidak_ada') ? '#dc2626' : '#374151') }}">
              {{ strtoupper($item->status_fisik ?? '-') }}
            </td>
            <td style="font-size:9px;">
              @if(count($plJson))
                @if($plAda)<span style="color:#059669">✓ {{ $plAda }}</span>@endif
                @if($plTdk)<br><span style="color:#dc2626">✗ {{ $plTdk }}</span>@endif
              @else
                -
              @endif
            </td>
            <td style="font-size:9px;">{{ $item->keterangan_fisik ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>

      {{-- Rekap Perlengkapan SMH per Jenis --}}
      @php
        $plSummary = [];
        foreach($allItems as $item) {
            foreach(($item->perlengkapan_json ?? []) as $pl) {
                $nm = trim($pl['nama'] ?? '');
                if($nm === '') continue;
                if(!isset($plSummary[$nm])) $plSummary[$nm] = ['ada'=>0,'tidak'=>0];
                if($pl['ada'] ?? false) $plSummary[$nm]['ada']++;
                else $plSummary[$nm]['tidak']++;
            }
        }
      @endphp
      @if(count($plSummary))
      <div style="margin-bottom:16px;">
        <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:4px;">Rekap Perlengkapan Per Jenis (dari Cek Fisik Unit)</div>
        <table style="font-size:9.5px;">
          <thead>
            <tr>
              <th>#</th>
              <th>Jenis Perlengkapan</th>
              <th style="text-align:center">Ada</th>
              <th style="text-align:center">Tidak Ada</th>
              <th style="text-align:center">Total Diperiksa</th>
              <th style="text-align:center">%Ada</th>
            </tr>
          </thead>
          <tbody>
            @foreach($plSummary as $plNm => $plCnt)
            @php $plTotal = $plCnt['ada'] + $plCnt['tidak']; $plPct = $plTotal > 0 ? round($plCnt['ada']/$plTotal*100) : 0; @endphp
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>{{ $plNm }}</td>
              <td style="text-align:center;color:#059669;font-weight:700">{{ $plCnt['ada'] }}</td>
              <td style="text-align:center;color:#dc2626;font-weight:700">{{ $plCnt['tidak'] }}</td>
              <td style="text-align:center">{{ $plTotal }}</td>
              <td style="text-align:center;color:{{ $plPct>=100 ? '#059669' : ($plPct>=80 ? '#d97706' : '#dc2626') }}">{{ $plPct }}%</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @endif

      @endif
      @endforeach
    @endif

    {{-- ── B. Perlengkapan di Luar SMH ── --}}
    <div style="font-weight:700;font-size:11px;color:#7c3aed;border-bottom:2px solid #7c3aed;padding-bottom:3px;margin-bottom:10px;margin-top:16px;">B. PERLENGKAPAN DI LUAR SMH</div>
    @if($perlengkapan->isEmpty())
      <p class="empty">Belum ada data perlengkapan di luar SMH.</p>
    @else
      @php
        // Saldo (Buku) dihitung ulang dari data onhand + hasil cek fisik unit, bukan
        // dibaca dari kolom `saldo` yang tersimpan. Kolom itu hanya potret saat baris
        // disimpan dan langsung basi begitu checklist Cek Fisik unit berubah sesudahnya
        // — form-nya sendiri menandai field ini "Otomatis dari data onhand", jadi ia
        // memang nilai turunan, bukan isian auditor. Menghitungnya di sini membuat
        // bagian B dan bagian C selalu menunjukkan angka yang sama.
        $saldoLive = function ($jenis) use ($perlengkapanOnhand) {
            $row = $perlengkapanOnhand[trim((string) $jenis)] ?? null;
            return $row ? max(0, $row['totalOnhand'] - $row['ada']) : 0;
        };

        $totalSaldo = $totalFisik = $totalSelisih = 0;
        foreach ($perlengkapan as $p) {
            $totalSaldo   += $saldoLive($p->jenis_perlengkapan);
            $totalFisik   += (int) ($p->fisik ?? 0);
            $totalSelisih += (int) ($p->fisik ?? 0) - $saldoLive($p->jenis_perlengkapan);
        }
      @endphp
      <table style="font-size:9.5px;">
        <thead>
          <tr>
            <th>#</th>
            <th>Jenis Perlengkapan</th>
            <th>Tgl Periksa</th>
            <th>Pemeriksa</th>
            <th>Unit Usaha</th>
            <th style="text-align:right">Saldo (Buku)</th>
            <th style="text-align:right">Fisik</th>
            <th style="text-align:right">Selisih</th>
            <th>Penjelasan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($perlengkapan as $i => $p)
          @php
            $saldoP = $saldoLive($p->jenis_perlengkapan);
            $sel    = (int)($p->fisik ?? 0) - $saldoP;
          @endphp
          <tr>
            <td>{{ (int)$i + 1 }}</td>
            <td style="font-weight:600">{{ $p->jenis_perlengkapan ?? '-' }}</td>
            <td>{{ $p->tgl_periksa ? \Carbon\Carbon::parse($p->tgl_periksa)->format('d/m/Y') : '-' }}</td>
            <td>{{ $p->nama_pemeriksa ?? '-' }}</td>
            <td>{{ $p->nama_unit_usaha ?? '-' }}</td>
            <td style="text-align:right">{{ number_format((float)$saldoP, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format((int)($p->fisik ?? 0), 0, ',', '.') }}</td>
            <td style="text-align:right;font-weight:700;color:{{ $sel != 0 ? '#dc2626' : '#059669' }}">
              {{ number_format($sel, 0, ',', '.') }}
            </td>
            <td style="font-size:9px">{{ $p->penjelasan ?? '-' }}</td>
          </tr>
          @endforeach
          <tr style="background:#f3f4f6;font-weight:700;">
            <td colspan="5" style="text-align:right">TOTAL</td>
            <td style="text-align:right">{{ number_format((float)$totalSaldo, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format((float)$totalFisik, 0, ',', '.') }}</td>
            <td style="text-align:right;color:{{ $totalSelisih != 0 ? '#dc2626' : '#059669' }}">{{ number_format((float)$totalSelisih, 0, ',', '.') }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
    @endif

    {{-- ── C. Rekap Gabungan Perlengkapan ── --}}
    @php
      // Map SMH: nama → {smhSaldo (unit yang MEMBUTUHKAN), smhFisik (yang ditemukan)}.
      //
      // Penyebutnya (smhSaldo) diambil dari App\Services\PerlengkapanOnhand —
      // sumber yang sama dengan Saldo buku "Perlengkapan di luar SMH" di tab
      // Perlengkapan. Dulu bagian ini menghitung sendiri dengan hanya menjumlah
      // unit yang status fisiknya 'ada' DAN checklist perlengkapannya sudah
      // tersinkron. Unit yang tidak ditemukan fisik — atau yang checklist-nya
      // belum diisi — tetap membutuhkan perlengkapannya, jadi mengeluarkannya
      // dari penyebut membuat kekurangan pada sisi SMH lebih kecil daripada
      // Saldo Luar SMH, dan kedua sisi tabel tidak pernah bisa direkonsiliasi.
      $smhPlMap = [];
      foreach($perlengkapanOnhand as $nm => $row) {
          $smhPlMap[$nm] = ['smhSaldo' => $row['totalOnhand'], 'smhFisik' => $row['ada']];
      }
      // Bangun map dari perlengkapan luar SMH: jenis → {luarSaldo, luarFisik, luarSelisih, penjelasan[]}
      $luarPlMap = [];
      foreach($perlengkapan as $p) {
          $nm = trim($p->jenis_perlengkapan ?? '');
          if($nm === '') continue;
          if(!isset($luarPlMap[$nm])) $luarPlMap[$nm] = ['luarSaldo'=>0,'luarFisik'=>0,'luarSelisih'=>0,'penjelasan'=>[]];
          $luarPlMap[$nm]['luarSaldo']  += (float)($p->saldo ?? 0);
          $luarPlMap[$nm]['luarFisik']  += (int)($p->fisik ?? 0);
          $luarPlMap[$nm]['luarSelisih']+= (float)($p->selisih ?? 0);
          if($p->penjelasan) $luarPlMap[$nm]['penjelasan'][] = $p->penjelasan;
      }
      // Gabungkan semua kunci
      $allJenis = array_unique(array_merge(array_keys($smhPlMap), array_keys($luarPlMap)));
      sort($allJenis);
    @endphp

    @if(count($allJenis))
    <div style="font-weight:700;font-size:11px;color:#0f766e;border-bottom:2px solid #0f766e;padding-bottom:3px;margin-bottom:10px;margin-top:20px;">C. REKAP GABUNGAN PERLENGKAPAN PER JENIS</div>
    @php
      $grandSmhSaldo=$grandSmhFisik=$grandSmhSel=0;
      $grandLuarSaldo=$grandLuarFisik=$grandLuarSel=0;
      $grandTotalSel=0;
    @endphp
    <table style="font-size:9.5px;">
      <thead>
        <tr style="background:#ccfbf1;">
          <th rowspan="2" style="vertical-align:middle">#</th>
          <th rowspan="2" style="vertical-align:middle">Jenis Perlengkapan</th>
          <th colspan="3" style="text-align:center;background:#dbeafe;color:#1d4ed8;">SMH Cek Fisik</th>
          <th colspan="3" style="text-align:center;background:#ede9fe;color:#7c3aed;">Perlengkapan Luar SMH</th>
          <th rowspan="2" style="vertical-align:middle;text-align:center;background:#fef3c7;color:#92400e;">Total Selisih</th>
          <th rowspan="2" style="vertical-align:middle">Keterangan</th>
        </tr>
        <tr style="background:#ccfbf1;">
          <th style="text-align:right;background:#dbeafe;color:#1d4ed8;">Saldo (unit)</th>
          <th style="text-align:right;background:#dbeafe;color:#1d4ed8;">Fisik (ada)</th>
          <th style="text-align:right;background:#dbeafe;color:#1d4ed8;">Selisih</th>
          <th style="text-align:right;background:#ede9fe;color:#7c3aed;">Saldo (buku)</th>
          <th style="text-align:right;background:#ede9fe;color:#7c3aed;">Fisik</th>
          <th style="text-align:right;background:#ede9fe;color:#7c3aed;">Selisih</th>
        </tr>
      </thead>
      <tbody>
        @foreach($allJenis as $idx => $jns)
        @php
          $smhD  = $smhPlMap[$jns]  ?? ['smhSaldo'=>0,'smhFisik'=>0];
          $hasLuar = isset($luarPlMap[$jns]);
          $luarD = $luarPlMap[$jns] ?? ['luarSaldo'=>0,'luarFisik'=>0,'luarSelisih'=>0,'penjelasan'=>[]];
          $smhSel  = $smhD['smhFisik'] - $smhD['smhSaldo'];

          // Saldo (buku) Luar SMH = sisa yang BELUM tertanggung setelah cek fisik unit.
          // Dihitung ulang di sini, bukan dibaca dari kolom `saldo` yang tersimpan:
          // nilai tersimpan itu hanya potret saat baris disimpan, dan menjadi basi
          // begitu checklist Cek Fisik unit berubah sesudahnya. Contoh nyata dari
          // lapangan: Baterai 3 Ah butuh 14 unit, 1 ketemu di unit, 13 ketemu di
          // gudang — mestinya pas (0), tapi kolom `saldo` masih menyimpan 14 (bukan
          // 13) sehingga selisihnya terbaca -1 padahal tidak ada yang kurang.
          $luarSaldo = max(0, $smhD['smhSaldo'] - $smhD['smhFisik']);
          $luarSel   = $luarD['luarFisik'] - $luarSaldo;

          // Total Selisih = SELURUH fisik yang tertanggung (ditemukan menempel di unit
          // saat cek fisik + ditemukan terpisah di gudang) dikurangi jumlah unit yang
          // membutuhkannya. Ditulis eksplisit begini supaya tidak bergantung pada
          // angka tersimpan mana pun, dan supaya jelas bahwa kedua sumber fisik
          // memang dijumlahkan — bukan salah satunya saja.
          $totalSel = ($smhD['smhFisik'] + $luarD['luarFisik']) - $smhD['smhSaldo'];

          $grandSmhSaldo  += $smhD['smhSaldo'];
          $grandSmhFisik  += $smhD['smhFisik'];
          $grandSmhSel    += $smhSel;
          $grandLuarSaldo += $luarSaldo;
          $grandLuarFisik += $luarD['luarFisik'];
          $grandLuarSel   += $luarSel;
          $grandTotalSel  += $totalSel;
          $ket = implode('; ', $luarD['penjelasan']);
        @endphp
        <tr>
          <td>{{ $idx + 1 }}</td>
          <td style="font-weight:600">{{ $jns }}</td>
          {{-- SMH --}}
          <td style="text-align:right">{{ $smhD['smhSaldo'] ?: '-' }}</td>
          <td style="text-align:right">{{ $smhD['smhFisik'] ?: '-' }}</td>
          <td style="text-align:right;font-weight:700;color:{{ $smhSel < 0 ? '#dc2626' : ($smhSel > 0 ? '#d97706' : '#059669') }}">
            {{ $smhD['smhSaldo'] ? ($smhSel > 0 ? '+'.$smhSel : $smhSel) : '-' }}
          </td>
          {{-- Luar SMH --}}
          <td style="text-align:right">{{ $luarSaldo ? number_format($luarSaldo,0,',','.') : '-' }}</td>
          <td style="text-align:right">{{ $luarD['luarFisik'] ? number_format($luarD['luarFisik'],0,',','.') : '-' }}</td>
          <td style="text-align:right;font-weight:700;color:{{ $luarSel != 0 ? '#dc2626' : '#059669' }}">
            {{ ($luarSaldo || $hasLuar) ? number_format($luarSel,0,',','.') : '-' }}
          </td>
          {{-- Total Selisih --}}
          <td style="text-align:center;font-weight:700;background:#fef9c3;color:{{ $totalSel < 0 ? '#dc2626' : ($totalSel > 0 ? '#d97706' : '#059669') }}">
            @if($smhD['smhSaldo'] || $hasLuar)
              {{ $totalSel > 0 ? '+'.$totalSel : $totalSel }}
            @else -
            @endif
          </td>
          <td style="font-size:9px">{{ $ket ?: '-' }}</td>
        </tr>
        @endforeach
        <tr style="background:#e6fffa;font-weight:700;border-top:2px solid #0f766e;">
          <td colspan="2" style="text-align:right">TOTAL</td>
          <td style="text-align:right">{{ $grandSmhSaldo }}</td>
          <td style="text-align:right">{{ $grandSmhFisik }}</td>
          <td style="text-align:right;color:{{ $grandSmhSel < 0 ? '#dc2626' : '#059669' }}">{{ $grandSmhSel > 0 ? '+'.$grandSmhSel : $grandSmhSel }}</td>
          <td style="text-align:right">{{ number_format($grandLuarSaldo,0,',','.') }}</td>
          <td style="text-align:right">{{ number_format($grandLuarFisik,0,',','.') }}</td>
          <td style="text-align:right;color:{{ $grandLuarSel != 0 ? '#dc2626' : '#059669' }}">{{ number_format($grandLuarSel,0,',','.') }}</td>
          <td style="text-align:center;background:#fef3c7;color:{{ $grandTotalSel != 0 ? '#dc2626' : '#059669' }}">{{ $grandTotalSel > 0 ? '+'.$grandTotalSel : $grandTotalSel }}</td>
          <td></td>
        </tr>
      </tbody>
    </table>
    @endif

    {{-- ── D. Register Blanko yang Belum Digunakan (SMH) ── --}}
    @php
      $smhBlankoRec = $blankos['smh'] ?? null;
      $smhBlankoH1 = $smhBlankoRec->blanko_h1 ?? [];
    @endphp
    @if(count($smhBlankoH1))
    <div style="margin-top:16px;">
      <div style="font-weight:700;font-size:11px;color:#374151;border-bottom:2px solid #d1d5db;padding-bottom:3px;margin-bottom:8px;">D. REGISTER BLANKO YANG BELUM DIGUNAKAN</div>
      <table>
        <thead><tr><th>Jenis</th><th>Nomor Range Blanko</th></tr></thead>
        <tbody>
          @foreach($smhBlankoH1 as $b)
          <tr>
            <td>{{ $b['jenis'] ?? '-' }}</td>
            <td>{{ $b['nomor'] ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif

  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     4. PEMERIKSAAN BANK
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['bank'] ?? true))
<div class="section">
  <div class="section-title">4. PEMERIKSAAN BANK</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'bank'])
  <div class="section-body">
    @if($bank->isEmpty())
      <p class="empty">Belum ada data.</p>
    @else

    {{-- ── Ringkasan semua rekening ── --}}
    @php
      $bankTotalBuku   = $bank->sum('saldo_buku');
      $bankTotalRK     = $bank->sum('saldo_bank');
      $bankTotalSelisih= $bank->sum('selisih');
      $fmt = fn($v) => 'Rp '.number_format((float)$v, 0, ',', '.');
    @endphp
    <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:6px;padding:10px 14px;margin-bottom:16px;">
      <div style="font-weight:700;font-size:12px;color:#1e3a8a;margin-bottom:8px;">RINGKASAN PEMERIKSAAN BANK</div>
      <table style="font-size:10px;">
        <thead>
          <tr style="background:#e0e7ff;">
            <th style="text-align:left;padding:4px 8px;border:1px solid #c7d2fe;">#</th>
            <th style="text-align:left;padding:4px 8px;border:1px solid #c7d2fe;">Nama Bank</th>
            <th style="text-align:left;padding:4px 8px;border:1px solid #c7d2fe;">No Rekening</th>
            <th style="text-align:left;padding:4px 8px;border:1px solid #c7d2fe;">Tgl Periksa</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">Saldo Buku</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">Saldo Rekening Koran</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">Selisih</th>
          </tr>
        </thead>
        <tbody>
          @foreach($bank as $i => $b)
          @php $sel = (float)($b->selisih ?? 0); @endphp
          <tr>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">{{ (int)$i+1 }}</td>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;">{{ $b->nama_bank ?? '-' }}</td>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;font-family:monospace;">{{ $b->no_rekening ?? '-' }}</td>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">{{ $b->tgl_periksa ? \Carbon\Carbon::parse($b->tgl_periksa)->format('d/m/Y') : '-' }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ $fmt($b->saldo_buku ?? 0) }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ $fmt($b->saldo_bank ?? 0) }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;color:{{ $sel != 0 ? '#dc2626' : '#059669' }};">{{ $fmt($sel) }}</td>
          </tr>
          @endforeach
          <tr style="background:#e0e7ff;font-weight:700;">
            <td colspan="5" style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">TOTAL</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">{{ $fmt($bankTotalBuku) }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;">{{ $fmt($bankTotalRK) }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #c7d2fe;color:{{ $bankTotalSelisih != 0 ? '#dc2626' : '#059669' }};">{{ $fmt($bankTotalSelisih) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    {{-- ── Detail per rekening ── --}}
    @foreach($bank as $bi => $b)
    @php
      $d   = $b->detail_json ?? [];
      $pen = $d['penerimaan']  ?? [];
      $peng= $d['pengeluaran'] ?? [];
      $saldoAwal    = (float)($d['saldo_awal'] ?? 0);
      $totalPen     = array_sum(array_column($pen, 'jumlah'));
      $totalPeng    = array_sum(array_column($peng, 'jumlah'));
      $saldoBuku    = $saldoAwal + $totalPen - $totalPeng;
      $saldoRK      = (float)($b->saldo_bank ?? $d['saldo_rk'] ?? 0);
      $selisih      = (float)($b->selisih ?? ($saldoRK - $saldoBuku));
    @endphp
    <div style="margin-bottom:20px;border:1px solid #d1d5db;border-radius:6px;overflow:hidden;">
      {{-- Header rekening --}}
      <div style="background:#1d4ed8;color:#fff;padding:7px 12px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:700;font-size:11px;">🏦 {{ (int)$bi+1 }}. {{ $b->nama_bank ?? '-' }}</span>
        <span style="font-size:10px;opacity:.85;">No. Rek: {{ $b->no_rekening ?? '-' }}</span>
      </div>
      <div style="padding:10px 12px;">

        {{-- Info rekening --}}
        <div class="kv-grid" style="margin-bottom:10px;">
          <div class="kv"><span class="kv-label">Tgl Periksa:</span><span class="kv-val">{{ $b->tgl_periksa ? \Carbon\Carbon::parse($b->tgl_periksa)->format('d/m/Y') : '-' }}</span></div>
          <div class="kv"><span class="kv-label">Tgl Saldo Awal:</span><span class="kv-val">{{ $d['saldo_awal_tgl'] ?? '-' }}</span></div>
          <div class="kv"><span class="kv-label">Saldo Awal:</span><span class="kv-val" style="font-weight:700;">{{ $fmt($saldoAwal) }}</span></div>
        </div>

        {{-- Penerimaan --}}
        @if(count($pen))
        <div style="margin-bottom:8px;">
          <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:4px;">Penerimaan / Kredit</div>
          <table style="font-size:9.5px;">
            <thead><tr><th>#</th><th>Tanggal</th><th>Keterangan</th><th style="text-align:right">Jumlah</th></tr></thead>
            <tbody>
              @foreach($pen as $ii => $r)
              <tr>
                <td>{{ $ii+1 }}</td>
                <td>{{ $r['tanggal'] ?? '-' }}</td>
                <td>{{ $r['keterangan'] ?? '-' }}</td>
                <td style="text-align:right">{{ $fmt($r['jumlah'] ?? 0) }}</td>
              </tr>
              @endforeach
              <tr style="font-weight:700;background:#f0fdf4;">
                <td colspan="3" style="text-align:right">Total Penerimaan</td>
                <td style="text-align:right;color:#059669;">{{ $fmt($totalPen) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        @endif

        {{-- Pengeluaran --}}
        @if(count($peng))
        <div style="margin-bottom:8px;">
          <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:4px;">Pengeluaran / Debet</div>
          <table style="font-size:9.5px;">
            <thead><tr><th>#</th><th>Tanggal</th><th>Keterangan</th><th style="text-align:right">Jumlah</th></tr></thead>
            <tbody>
              @foreach($peng as $ii => $r)
              <tr>
                <td>{{ $ii+1 }}</td>
                <td>{{ $r['tanggal'] ?? '-' }}</td>
                <td>{{ $r['keterangan'] ?? '-' }}</td>
                <td style="text-align:right">{{ $fmt($r['jumlah'] ?? 0) }}</td>
              </tr>
              @endforeach
              <tr style="font-weight:700;background:#fff1f2;">
                <td colspan="3" style="text-align:right">Total Pengeluaran</td>
                <td style="text-align:right;color:#dc2626;">{{ $fmt($totalPeng) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        @endif

        {{-- Rekonsiliasi --}}
        <table style="width:280px;margin-left:auto;font-size:10px;border:1px solid #d1d5db;">
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Awal</td>
              <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;">{{ $fmt($saldoAwal) }}</td></tr>
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;color:#059669;">+ Penerimaan</td>
              <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:#059669;">{{ $fmt($totalPen) }}</td></tr>
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;color:#dc2626;">− Pengeluaran</td>
              <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:#dc2626;">{{ $fmt($totalPeng) }}</td></tr>
          <tr style="background:#f0f4ff;font-weight:700;">
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Buku (Sistem)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;">{{ $fmt($saldoBuku) }}</td></tr>
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Rekening Koran</td>
              <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;">{{ $fmt($saldoRK) }}</td></tr>
          <tr style="background:{{ $selisih != 0 ? '#fee2e2' : '#f0fdf4' }};font-weight:700;">
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Selisih</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:{{ $selisih != 0 ? '#dc2626' : '#059669' }};">{{ $fmt($selisih) }}</td></tr>
        </table>

        @if($b->keterangan || ($d['keterangan_selisih'] ?? null))
        <div style="margin-top:8px;font-size:10px;color:#374151;">
          <strong>Keterangan Selisih:</strong> {{ $b->keterangan ?? $d['keterangan_selisih'] ?? '-' }}
        </div>
        @endif

        @if($d['saldo_rk_tgl'] ?? null)
        <div style="margin-top:4px;font-size:10px;color:#6b7280;">
          <strong>Tgl Rekening Koran:</strong> {{ $d['saldo_rk_tgl'] }}
        </div>
        @endif

      </div>
    </div>
    @endforeach

    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     5. PEMERIKSAAN MATERAI
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['materai'] ?? true))
<div class="section">
  <div class="section-title">5. PEMERIKSAAN MATERAI</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'materai'])
  <div class="section-body">
    @if($materai->isEmpty())
      <p class="empty">Belum ada data.</p>
    @else

    {{-- ── Ringkasan semua jenis materai ── --}}
    <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;padding:10px 14px;margin-bottom:16px;">
      <div style="font-weight:700;font-size:12px;color:#4c1d95;margin-bottom:8px;">RINGKASAN PEMERIKSAAN MATERAI</div>
      <table style="font-size:10px;">
        <thead>
          <tr style="background:#ede9fe;">
            <th style="text-align:left;padding:4px 8px;border:1px solid #ddd6fe;">#</th>
            <th style="text-align:left;padding:4px 8px;border:1px solid #ddd6fe;">Jenis Materai</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd6fe;">Saldo Awal</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd6fe;">Total Debet (+)</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd6fe;">Total Kredit (−)</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd6fe;">Saldo Buku</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd6fe;">Fisik</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd6fe;">Uang (Rp 10rb)</th>
            <th style="text-align:right;padding:4px 8px;border:1px solid #ddd6fe;">Selisih</th>
          </tr>
        </thead>
        <tbody>
          @foreach($materai as $i => $m)
          @php $sel = (int)($m->selisih ?? 0); @endphp
          <tr>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">{{ (int)$i+1 }}</td>
            <td style="padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;">{{ $m->jenis_materai ?? '-' }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ number_format($m->saldo_awal ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;color:#059669;">{{ number_format($m->total_debet ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;color:#dc2626;">{{ number_format($m->total_kredit ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;">{{ number_format($m->saldo_akhir ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;">{{ number_format($m->fisik ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;">{{ $m->uang_10000 ? 'Rp '.number_format($m->uang_10000 * 10000, 0, ',', '.') : '-' }}</td>
            <td style="text-align:right;padding:4px 8px;border:1px solid #e5e7eb;font-weight:700;color:{{ $sel != 0 ? '#dc2626' : '#059669' }};">{{ number_format($sel, 0, ',', '.') }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- ── Detail per jenis materai ── --}}
    @foreach($materai as $mi => $m)
    @php
      $trx     = $m->transaksi_json ?? [];
      $trxDebet  = array_filter($trx, fn($t) => ($t['debet'] ?? 0) > 0);
      $trxKredit = array_filter($trx, fn($t) => ($t['kredit'] ?? 0) > 0);
      $sel       = (int)($m->selisih ?? 0);
    @endphp
    <div style="margin-bottom:16px;border:1px solid #d1d5db;border-radius:6px;overflow:hidden;">
      <div style="background:#7c3aed;color:#fff;padding:7px 12px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:700;font-size:11px;">🏷️ {{ (int)$mi+1 }}. {{ $m->jenis_materai ?? '-' }}</span>
        <span style="font-size:10px;opacity:.85;">Selisih: <strong style="color:{{ $sel != 0 ? '#fca5a5' : '#6ee7b7' }}">{{ number_format($sel, 0, ',', '.') }}</strong></span>
      </div>
      <div style="padding:10px 12px;">

        {{-- Rekap saldo ── --}}
        <table style="width:260px;margin-bottom:12px;font-size:10px;border:1px solid #d1d5db;">
          <tr style="background:#f5f3ff;">
            <td style="padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">Saldo Awal (Buku)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;">{{ number_format($m->saldo_awal ?? 0, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td style="padding:3px 8px;border:1px solid #d1d5db;color:#059669;">+ Total Debet (masuk)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:#059669;">{{ number_format($m->total_debet ?? 0, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td style="padding:3px 8px;border:1px solid #d1d5db;color:#dc2626;">− Total Kredit (keluar)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:#dc2626;">{{ number_format($m->total_kredit ?? 0, 0, ',', '.') }}</td>
          </tr>
          <tr style="background:#ede9fe;font-weight:700;">
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Buku (Akhir)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;">{{ number_format($m->saldo_akhir ?? 0, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Fisik (lembar)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700;">{{ number_format($m->fisik ?? 0, 0, ',', '.') }}</td>
          </tr>
          @if($m->uang_10000)
          <tr>
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Uang Rp 10.000 (pengganti)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;">Rp {{ number_format($m->uang_10000 * 10000, 0, ',', '.') }}</td>
          </tr>
          @endif
          <tr style="background:{{ $sel != 0 ? '#fee2e2' : '#f0fdf4' }};font-weight:700;">
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Selisih (Fisik − Buku)</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:{{ $sel != 0 ? '#dc2626' : '#059669' }};">{{ number_format($sel, 0, ',', '.') }}</td>
          </tr>
        </table>

        {{-- Transaksi ── --}}
        @if(count($trx))
        <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:6px;">Riwayat Transaksi</div>
        <table style="font-size:9.5px;">
          <thead>
            <tr>
              <th>#</th>
              <th>Tanggal</th>
              <th>Keterangan</th>
              <th style="text-align:right;color:#059669">Debet (+)</th>
              <th style="text-align:right;color:#dc2626">Kredit (−)</th>
              <th style="text-align:right">Saldo</th>
            </tr>
          </thead>
          <tbody>
            @php $runSaldo = (int)($m->saldo_awal ?? 0); @endphp
            <tr style="background:#f5f3ff;font-weight:700;">
              <td colspan="2">Saldo Awal</td>
              <td colspan="3"></td>
              <td style="text-align:right">{{ number_format($runSaldo, 0, ',', '.') }}</td>
            </tr>
            @foreach($trx as $ti => $t)
            @php
              $dbt = (int)($t['debet'] ?? 0);
              $krd = (int)($t['kredit'] ?? 0);
              $runSaldo += $dbt - $krd;
            @endphp
            <tr>
              <td>{{ $ti + 1 }}</td>
              <td>{{ $t['tanggal'] ?? '-' }}</td>
              <td>{{ $t['keterangan'] ?? '-' }}</td>
              <td style="text-align:right;color:#059669;font-weight:{{ $dbt ? '700' : '400' }}">{{ $dbt ? number_format($dbt, 0, ',', '.') : '-' }}</td>
              <td style="text-align:right;color:#dc2626;font-weight:{{ $krd ? '700' : '400' }}">{{ $krd ? number_format($krd, 0, ',', '.') : '-' }}</td>
              <td style="text-align:right;font-weight:700">{{ number_format($runSaldo, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr style="background:#ede9fe;font-weight:700;">
              <td colspan="3" style="text-align:right">Total</td>
              <td style="text-align:right;color:#059669">{{ number_format($m->total_debet ?? 0, 0, ',', '.') }}</td>
              <td style="text-align:right;color:#dc2626">{{ number_format($m->total_kredit ?? 0, 0, ',', '.') }}</td>
              <td style="text-align:right">{{ number_format($m->saldo_akhir ?? 0, 0, ',', '.') }}</td>
            </tr>
          </tbody>
        </table>
        @else
          <p style="color:#9ca3af;font-size:10px;font-style:italic;">Tidak ada riwayat transaksi.</p>
        @endif

      </div>
    </div>
    @endforeach

    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     6. ONHAND BPKB
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['bpkb'] ?? true))
<div class="section">
  <div class="section-title">6. ONHAND BPKB</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'bpkb'])
  <div class="section-body">
    @if($bpkbOnhand->isEmpty())
      <p class="empty">Belum ada data.</p>
    @else
    @php
      $bpkbReg90 = $bpkbOnhand->filter(fn($b) => strtoupper($b->jenis ?? '') === 'REG' && ($b->umur ?? 0) > 90)->sortByDesc('umur');
      $bpkbByJenis = $bpkbOnhand->groupBy(fn($b) => strtoupper($b->jenis ?? '-'));
    @endphp

    {{-- ── Ringkasan per Jenis ── --}}
    <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:6px;padding:10px 14px;margin-bottom:14px;">
      <div style="font-weight:700;font-size:12px;color:#1e3a8a;margin-bottom:8px;">RINGKASAN ONHAND BPKB</div>
      <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
        @foreach($bpkbByJenis as $jenis => $items)
        @php $sudahScan = $items->where('sudah_scan', true)->count(); @endphp
        <div style="background:#fff;border:1px solid #e0e7ff;border-radius:6px;padding:8px 14px;min-width:130px;">
          <div style="font-weight:700;font-size:11px;color:#1d4ed8;margin-bottom:4px;">{{ $jenis }}</div>
          <div style="font-size:10px;color:#374151;">Total: <strong>{{ $items->count() }}</strong></div>
          <div style="font-size:10px;color:#059669;">Sudah Scan: <strong>{{ $sudahScan }}</strong></div>
          <div style="font-size:10px;color:#dc2626;">Belum Scan: <strong>{{ $items->count() - $sudahScan }}</strong></div>
          @php $avg = $items->avg('umur'); @endphp
          @if($avg)<div style="font-size:10px;color:#6b7280;">Rata-rata umur: {{ round($avg) }} hari</div>@endif
        </div>
        @endforeach
        <div style="background:#1e40af;border-radius:6px;padding:8px 14px;min-width:130px;">
          <div style="font-weight:700;font-size:11px;color:#fff;margin-bottom:4px;">TOTAL</div>
          <div style="font-size:10px;color:#bfdbfe;">Semua BPKB: <strong style="color:#fff">{{ $bpkbOnhand->count() }}</strong></div>
          <div style="font-size:10px;color:#fca5a5;">REG &gt; 90 hari: <strong style="color:#fca5a5">{{ $bpkbReg90->count() }}</strong></div>
        </div>
      </div>
    </div>

    {{-- ── ALERT: REG > 90 hari ── --}}
    @if($bpkbReg90->count())
    <div style="background:#fff7ed;border:2px solid #fed7aa;border-radius:6px;padding:10px 14px;margin-bottom:16px;">
      <div style="font-weight:700;font-size:11px;color:#c2410c;margin-bottom:8px;">
        ⚠️ BPKB REG UMUR &gt; 90 HARI — {{ $bpkbReg90->count() }} item
      </div>
      <table style="font-size:9.5px;">
        <thead>
          <tr style="background:#ffedd5;">
            <th>#</th>
            <th>No BPKB</th>
            <th>No Polisi</th>
            <th>Nama Pemilik</th>
            <th>No Mesin</th>
            <th>Tgl Terima</th>
            <th style="text-align:center">Umur (hari)</th>
            <th style="text-align:center">Scan</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($bpkbReg90 as $ri => $b)
          @php $umur = (int)($b->umur ?? 0); @endphp
          <tr style="{{ $umur > 180 ? 'background:#fee2e2;' : ($umur > 90 ? 'background:#fff7ed;' : '') }}">
            <td>{{ $ri + 1 }}</td>
            <td style="font-weight:700">{{ $b->no_bpkb ?? '-' }}</td>
            <td>{{ $b->no_polisi ?? '-' }}</td>
            <td>{{ $b->nama_pemilik ?? '-' }}</td>
            <td style="font-size:9px">{{ $b->no_mesin ?? '-' }}</td>
            <td>{{ $b->tgl_terima ? \Carbon\Carbon::parse($b->tgl_terima)->format('d/m/Y') : '-' }}</td>
            <td style="text-align:center;font-weight:700;color:{{ $umur > 180 ? '#dc2626' : '#d97706' }}">{{ $umur }}</td>
            <td style="text-align:center">{{ $b->sudah_scan ? '✓' : '✗' }}</td>
            <td style="font-size:9px">{{ $b->keterangan ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif

    {{-- ── Daftar Lengkap Onhand BPKB ── --}}
    <div style="font-size:10px;font-weight:700;color:#374151;margin-bottom:6px;">Daftar Lengkap Onhand BPKB</div>
    <table style="font-size:9.5px;">
      <thead>
        <tr>
          <th>#</th>
          <th>No BPKB</th>
          <th>No Polisi</th>
          <th>Nama Pemilik</th>
          <th>No Mesin</th>
          <th style="text-align:center">Jenis</th>
          <th>Tgl Terima</th>
          <th style="text-align:center">Umur (hari)</th>
          <th style="text-align:center">Scan</th>
          <th>Keterangan</th>
        </tr>
      </thead>
      <tbody>
        @foreach($bpkbOnhand as $i => $b)
        @php $umur = (int)($b->umur ?? 0); $isReg90 = strtoupper($b->jenis ?? '') === 'REG' && $umur > 90; @endphp
        <tr style="{{ $isReg90 ? 'background:#fff7ed;' : '' }}">
          <td>{{ (int)$i+1 }}</td>
          <td style="font-weight:{{ $isReg90 ? '700' : '400' }}">{{ $b->no_bpkb ?? '-' }}</td>
          <td>{{ $b->no_polisi ?? '-' }}</td>
          <td>{{ $b->nama_pemilik ?? '-' }}</td>
          <td style="font-size:9px">{{ $b->no_mesin ?? '-' }}</td>
          <td style="text-align:center;font-weight:700">{{ strtoupper($b->jenis ?? '-') }}</td>
          <td>{{ $b->tgl_terima ? \Carbon\Carbon::parse($b->tgl_terima)->format('d/m/Y') : '-' }}</td>
          <td style="text-align:center;font-weight:700;color:{{ $isReg90 ? ($umur > 180 ? '#dc2626' : '#d97706') : '#374151' }}">
            {{ $umur ?: '-' }}
          </td>
          <td style="text-align:center;color:{{ $b->sudah_scan ? '#059669' : '#9ca3af' }}">{{ $b->sudah_scan ? '✓' : '✗' }}</td>
          <td style="font-size:9px">{{ $b->keterangan ?? '-' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    @endif

    {{-- ── Register Blanko yang Belum Digunakan (Onhand BPKB) ── --}}
    @php
      $bpkbBlankoRec = $blankos['bpkb'] ?? null;
      $bpkbBlankoH1 = $bpkbBlankoRec->blanko_h1 ?? [];
    @endphp
    @if(count($bpkbBlankoH1))
    <div style="margin-top:16px;">
      <div style="font-weight:700;font-size:11px;color:#374151;border-bottom:2px solid #d1d5db;padding-bottom:3px;margin-bottom:8px;">REGISTER BLANKO YANG BELUM DIGUNAKAN</div>
      <table>
        <thead><tr><th>Jenis</th><th>Nomor Range Blanko</th></tr></thead>
        <tbody>
          @foreach($bpkbBlankoH1 as $b)
          <tr>
            <td>{{ $b['jenis'] ?? '-' }}</td>
            <td>{{ $b['nomor'] ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     7. BPKB INPROSES
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['bpkb-inproses'] ?? true))
<div class="section">
  <div class="section-title">7. BPKB INPROSES</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'bpkb-inproses'])
  <div class="section-body">
    @if($bpkbInproses->isEmpty())
      <p class="empty">Belum ada data.</p>
    @else
    @foreach($bpkbInproses as $b)
    @php
      // Semua field angka disimpan sebagai 'qty', bukan 'jumlah'
      $penFisik  = $b->penerimaan_fisik_json    ?? [];
      $kelBpkb   = $b->pengeluaran_bpkb_json    ?? [];
      $blocks    = $b->inproses_blocks_json      ?? [];

      $saldoAwalFisik  = (int)($b->saldo_awal_fisik ?? 0);
      $totalPenFisik   = array_sum(array_column($penFisik, 'qty'));
      $totalKelBpkb    = array_sum(array_column($kelBpkb, 'qty'));
      $fisikBpkbHitung = (int)($b->fisik_bpkb_hitung ?? ($saldoAwalFisik + $totalPenFisik - $totalKelBpkb));
      $onhandBpkb      = (int)($b->onhand_bpkb ?? 0);
      $selisihBpkb     = $fisikBpkbHitung - $onhandBpkb;
      $fmt = fn($v) => number_format((int)$v, 0, ',', '.');
    @endphp

    {{-- ── RINGKASAN ── --}}
    <div style="background:#f0f4ff;border:1px solid #c7d2fe;border-radius:6px;padding:10px 14px;margin-bottom:16px;">
      <div style="font-weight:700;font-size:12px;color:#1e3a8a;margin-bottom:8px;">RINGKASAN PEMERIKSAAN BPKB INPROSES</div>
      <div class="kv-grid" style="margin-bottom:10px;">
        <div class="kv"><span class="kv-label">Tgl Pemeriksaan:</span><span class="kv-val">{{ $b->tgl_awal ? \Carbon\Carbon::parse($b->tgl_awal)->format('d/m/Y') : '-' }}</span></div>
        <div class="kv"><span class="kv-label">Onhand BPKB (Sistem):</span><span class="kv-val" style="font-weight:700">{{ $fmt($onhandBpkb) }}</span></div>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div style="background:#fff;border:1px solid #e0e7ff;border-radius:6px;padding:8px 14px;flex:1;min-width:180px;">
          <div style="font-size:10px;font-weight:700;color:#1d4ed8;margin-bottom:6px;border-bottom:1px solid #e0e7ff;padding-bottom:3px;">FISIK BPKB</div>
          <div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;"><span>Saldo Awal Fisik</span><strong>{{ $fmt($saldoAwalFisik) }}</strong></div>
          @if($totalPenFisik)<div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;color:#059669;"><span>+ Penerimaan</span><strong>{{ $fmt($totalPenFisik) }}</strong></div>@endif
          @if($totalKelBpkb)<div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;color:#dc2626;"><span>− Pengeluaran</span><strong>{{ $fmt($totalKelBpkb) }}</strong></div>@endif
          <div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;border-top:1px solid #e0e7ff;padding-top:3px;font-weight:700;"><span>Fisik Buku</span><span>{{ $fmt($fisikBpkbHitung) }}</span></div>
          <div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;"><span>Onhand (Sistem)</span><strong>{{ $fmt($onhandBpkb) }}</strong></div>
          <div style="font-size:10px;display:flex;justify-content:space-between;font-weight:700;color:{{ $selisihBpkb != 0 ? '#dc2626' : '#059669' }};background:{{ $selisihBpkb != 0 ? '#fee2e2' : '#f0fdf4' }};padding:3px 4px;border-radius:4px;">
            <span>Selisih</span><span>{{ $selisihBpkb > 0 ? '+' : '' }}{{ $fmt($selisihBpkb) }}</span>
          </div>
          @if($b->keterangan_selisih)
          <div style="margin-top:4px;font-size:9px;color:#6b7280;"><em>{{ $b->keterangan_selisih }}</em></div>
          @endif
        </div>
        @if(count($blocks))
        @foreach($blocks as $blk)
        @php
          $saldoBlk   = (int)($blk['saldoAwalInproses'] ?? 0);
          $pendBlk    = $blk['pendaftaranBpkb']      ?? [];
          $penyelBlk  = $blk['penyelesaianInproses'] ?? [];
          $totalPendBlk  = array_sum(array_column($pendBlk,   'qty'));
          $totalPenyelBlk= array_sum(array_column($penyelBlk, 'qty'));
          $fisikBlk   = (int)($blk['fisikInprosesHitung'] ?? ($saldoBlk + $totalPendBlk - $totalPenyelBlk));
        @endphp
        <div style="background:#fff;border:1px solid #e0e7ff;border-radius:6px;padding:8px 14px;flex:1;min-width:180px;">
          <div style="font-size:10px;font-weight:700;color:#7c3aed;margin-bottom:6px;border-bottom:1px solid #ede9fe;padding-bottom:3px;">
            📋 {{ $blk['filterInproses'] ?? 'INPROSES' }}
          </div>
          <div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;"><span>Saldo Awal</span><strong>{{ $fmt($saldoBlk) }}</strong></div>
          @if($totalPendBlk)<div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;color:#1d4ed8;"><span>+ Pendaftaran</span><strong>{{ $fmt($totalPendBlk) }}</strong></div>@endif
          @if($totalPenyelBlk)<div style="font-size:10px;display:flex;justify-content:space-between;margin-bottom:3px;color:#dc2626;"><span>− Penyelesaian</span><strong>{{ $fmt($totalPenyelBlk) }}</strong></div>@endif
          <div style="font-size:10px;display:flex;justify-content:space-between;border-top:1px solid #ede9fe;padding-top:3px;font-weight:700;"><span>Fisik Inproses</span><span>{{ $fmt($fisikBlk) }}</span></div>
        </div>
        @endforeach
        @endif
      </div>
    </div>

    {{-- ── A. FISIK BPKB: Penerimaan ── --}}
    @if(count($penFisik))
    <div style="margin-bottom:14px;">
      <div style="font-weight:700;font-size:11px;color:#1d4ed8;border-bottom:2px solid #1d4ed8;padding-bottom:3px;margin-bottom:8px;">A. PENERIMAAN FISIK BPKB</div>
      <table style="font-size:9.5px;">
        <thead><tr><th>#</th><th>Keterangan</th><th style="text-align:right">Qty (unit)</th></tr></thead>
        <tbody>
          @foreach($penFisik as $ii => $r)
          <tr>
            <td>{{ $ii+1 }}</td>
            <td>{{ $r['keterangan'] ?? '-' }}</td>
            <td style="text-align:right;color:#059669;font-weight:700">{{ $fmt($r['qty'] ?? 0) }}</td>
          </tr>
          @endforeach
          <tr style="background:#f0fdf4;font-weight:700;">
            <td colspan="2" style="text-align:right">Total Penerimaan</td>
            <td style="text-align:right;color:#059669">{{ $fmt($totalPenFisik) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    @endif

    {{-- ── B. FISIK BPKB: Pengeluaran ── --}}
    @if(count($kelBpkb))
    <div style="margin-bottom:14px;">
      <div style="font-weight:700;font-size:11px;color:#dc2626;border-bottom:2px solid #dc2626;padding-bottom:3px;margin-bottom:8px;">B. PENGELUARAN / PENGIRIMAN BPKB</div>
      <table style="font-size:9.5px;">
        <thead><tr><th>#</th><th>Keterangan</th><th style="text-align:right">Qty (unit)</th></tr></thead>
        <tbody>
          @foreach($kelBpkb as $ii => $r)
          <tr>
            <td>{{ $ii+1 }}</td>
            <td>{{ $r['keterangan'] ?? '-' }}</td>
            <td style="text-align:right;color:#dc2626;font-weight:700">{{ $fmt($r['qty'] ?? 0) }}</td>
          </tr>
          @endforeach
          <tr style="background:#fff1f2;font-weight:700;">
            <td colspan="2" style="text-align:right">Total Pengeluaran</td>
            <td style="text-align:right;color:#dc2626">{{ $fmt($totalKelBpkb) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    @endif

    {{-- ── C. Detail Inproses Blocks ── --}}
    @foreach($blocks as $bi => $blk)
    @php
      $pendBlk    = $blk['pendaftaranBpkb']      ?? [];
      $penyelBlk  = $blk['penyelesaianInproses'] ?? [];
      $ketBlk     = $blk['ketSelisihInproses']   ?? [];
      $rincBlk    = $blk['rincianInproses']       ?? [];
      $saldoBlk   = (int)($blk['saldoAwalInproses'] ?? 0);
      $totalPendBlk   = array_sum(array_column($pendBlk,   'qty'));
      $totalPenyelBlk = array_sum(array_column($penyelBlk, 'qty'));
      $fisikBlk   = (int)($blk['fisikInprosesHitung'] ?? ($saldoBlk + $totalPendBlk - $totalPenyelBlk));
      $selisihBlk = $fisikBlk - ($saldoBlk + $totalPendBlk - $totalPenyelBlk);
    @endphp
    <div style="margin-bottom:14px;border:1px solid #ede9fe;border-radius:6px;overflow:hidden;">
      <div style="background:#7c3aed;color:#fff;padding:6px 12px;font-weight:700;font-size:10px;display:flex;justify-content:space-between;">
        <span>📋 {{ (int)$bi+1 }}. INPROSES: {{ $blk['filterInproses'] ?? '-' }}</span>
        <span>Saldo Awal: {{ $fmt($saldoBlk) }} &nbsp;|&nbsp; Fisik: {{ $fmt($fisikBlk) }}</span>
      </div>
      <div style="padding:8px 12px;">

        {{-- Rekap mini ── --}}
        <table style="width:260px;margin-bottom:10px;font-size:10px;border:1px solid #d1d5db;">
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Awal Inproses</td>
              <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;font-weight:700">{{ $fmt($saldoBlk) }}</td></tr>
          @if($totalPendBlk)
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;color:#1d4ed8;">+ Pendaftaran BPKB</td>
              <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:#1d4ed8;font-weight:700">{{ $fmt($totalPendBlk) }}</td></tr>
          @endif
          @if($totalPenyelBlk)
          <tr><td style="padding:3px 8px;border:1px solid #d1d5db;color:#dc2626;">− Penyelesaian Inproses</td>
              <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:#dc2626;font-weight:700">{{ $fmt($totalPenyelBlk) }}</td></tr>
          @endif
          <tr style="background:#ede9fe;font-weight:700;">
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Saldo Buku</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;">{{ $fmt($saldoBlk + $totalPendBlk - $totalPenyelBlk) }}</td></tr>
          <tr style="background:{{ $selisihBlk != 0 ? '#fee2e2' : '#f0fdf4' }};font-weight:700;">
            <td style="padding:3px 8px;border:1px solid #d1d5db;">Selisih</td>
            <td style="text-align:right;padding:3px 8px;border:1px solid #d1d5db;color:{{ $selisihBlk != 0 ? '#dc2626' : '#059669' }};">
              {{ $selisihBlk != 0 ? $fmt($selisihBlk) : 'Nihil' }}</td></tr>
        </table>

        {{-- Pendaftaran BPKB ── --}}
        @if(count($pendBlk))
        <div style="font-size:10px;font-weight:700;margin-bottom:4px;color:#1d4ed8;">+ Pendaftaran BPKB</div>
        <table style="font-size:9.5px;margin-bottom:8px;">
          <thead><tr><th>#</th><th>Keterangan</th><th style="text-align:right">Qty</th></tr></thead>
          <tbody>
            @foreach($pendBlk as $ii => $r)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $r['keterangan'] ?? '-' }}</td>
              <td style="text-align:right;color:#1d4ed8;font-weight:700">{{ $fmt($r['qty'] ?? 0) }}</td>
            </tr>
            @endforeach
            <tr style="background:#dbeafe;font-weight:700;">
              <td colspan="2" style="text-align:right">Total Pendaftaran</td>
              <td style="text-align:right;color:#1d4ed8">{{ $fmt($totalPendBlk) }}</td>
            </tr>
          </tbody>
        </table>
        @endif

        {{-- Penyelesaian Inproses ── --}}
        @if(count($penyelBlk))
        <div style="font-size:10px;font-weight:700;margin-bottom:4px;color:#dc2626;">− Penyelesaian Inproses</div>
        <table style="font-size:9.5px;margin-bottom:8px;">
          <thead><tr><th>#</th><th>Keterangan</th><th style="text-align:right">Qty</th></tr></thead>
          <tbody>
            @foreach($penyelBlk as $ii => $r)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $r['keterangan'] ?? '-' }}</td>
              <td style="text-align:right;color:#dc2626;font-weight:700">{{ $fmt($r['qty'] ?? 0) }}</td>
            </tr>
            @endforeach
            <tr style="background:#fff1f2;font-weight:700;">
              <td colspan="2" style="text-align:right">Total Penyelesaian</td>
              <td style="text-align:right;color:#dc2626">{{ $fmt($totalPenyelBlk) }}</td>
            </tr>
          </tbody>
        </table>
        @endif

        {{-- Rincian Inproses ── --}}
        @if(count($rincBlk))
        <div style="font-size:10px;font-weight:700;margin-bottom:4px;color:#374151;">Rincian Inproses</div>
        <table style="font-size:9.5px;margin-bottom:8px;">
          <thead><tr><th>#</th><th>Bulan / Periode</th><th style="text-align:right">Qty</th></tr></thead>
          <tbody>
            @foreach($rincBlk as $ii => $r)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $r['bulan'] ?? $r['keterangan'] ?? '-' }}</td>
              <td style="text-align:right;font-weight:700">{{ $fmt($r['qty'] ?? 0) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @endif

        {{-- Keterangan Selisih ── --}}
        @if(count($ketBlk))
        <div style="font-size:10px;font-weight:700;margin-bottom:4px;color:#374151;">Keterangan Selisih</div>
        <table style="font-size:9.5px;">
          <thead><tr><th>#</th><th>Keterangan</th><th style="text-align:right">Qty</th></tr></thead>
          <tbody>
            @foreach($ketBlk as $ii => $r)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $r['keterangan'] ?? '-' }}</td>
              <td style="text-align:right">{{ $fmt($r['qty'] ?? 0) }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @endif
      </div>
    </div>
    @endforeach

    {{-- Keterangan selisih onhand ── --}}
    @if($b->keterangan_selisih_onhand)
    <div style="margin-top:8px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;font-size:10px;">
      <strong>Keterangan Selisih Onhand:</strong> {{ $b->keterangan_selisih_onhand }}
    </div>
    @endif

    @endforeach
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     8. KWITANSI GANTUNG
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['kwitansi'] ?? true))
<div class="section">
  <div class="section-title">8. KWITANSI GANTUNG</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'kwitansi'])
  <div class="section-body">
    @if(!$kwitansi)
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $kwItems = $kwitansi->kwitansi_json ?? [];
        $kwTotalNilai   = array_sum(array_column($kwItems, 'nilaiKwitansi'));
        $kwLeasing      = collect($kwItems)->pluck('leasing')->filter()->unique()->sort()->values();
        $kwCustomerCnt  = collect($kwItems)->pluck('namaCustomer')->filter()->unique()->count();
        $kwDiffs        = array_filter(array_column($kwItems, 'diff'), fn($d) => $d !== null && $d !== '');
        $kwAvgDiff      = count($kwDiffs) ? round(array_sum($kwDiffs) / count($kwDiffs)) : null;
        $kwByLeasing    = collect($kwItems)->groupBy('leasing');
        $tglAuditTs     = $kwitansi->tgl_audit ? strtotime($kwitansi->tgl_audit->format('Y-m-d')) : null;
      @endphp

      {{-- Summary cards --}}
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val">{{ count($kwItems) }}</div>
          <div class="cs-lbl">Total Kwitansi</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val">{{ $kwCustomerCnt }}</div>
          <div class="cs-lbl">Customer</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val">{{ $kwLeasing->count() }}</div>
          <div class="cs-lbl">Leasing</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:140px;">
          <div class="cs-val" style="font-size:13px;">Rp {{ number_format($kwTotalNilai,0,',','.') }}</div>
          <div class="cs-lbl">Total Nilai</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val">{{ $kwAvgDiff !== null ? $kwAvgDiff.' hari' : '-' }}</div>
          <div class="cs-lbl">Rata-rata Gantung</div>
        </div>
      </div>

      <div class="kv" style="margin-bottom:10px;">
        <span class="kv-label">Tgl Audit:</span>
        <span class="kv-val">{{ $kwitansi->tgl_audit ? $kwitansi->tgl_audit->format('d/m/Y') : '-' }}</span>
      </div>

      @if(count($kwItems))
        @foreach($kwByLeasing as $leasingName => $lsItems)
          @php
            $lsTotal   = $lsItems->sum('nilaiKwitansi');
            $lsDiffs   = $lsItems->pluck('diff')->filter(fn($d) => $d !== null && $d !== '');
            $lsAvgDiff = $lsDiffs->count() ? round($lsDiffs->avg()) : null;
          @endphp
          <div style="margin-bottom:14px;">
            <div style="font-weight:600;font-size:12px;margin-bottom:4px;padding:4px 8px;background:#1e293b;border-left:3px solid #3b82f6;">
              {{ $leasingName ?: '-' }}
              <span style="font-weight:400;color:#94a3b8;margin-left:8px;">{{ $lsItems->count() }} kwitansi</span>
              @if($lsAvgDiff !== null)
                <span style="font-weight:400;color:#94a3b8;margin-left:8px;">avg gantung: {{ $lsAvgDiff }} hari</span>
              @endif
            </div>
            <table>
              <thead>
                <tr>
                  <th style="width:30px;">#</th>
                  <th>No Kwitansi</th>
                  <th>Tgl Kwitansi</th>
                  <th>Nama Customer</th>
                  <th>No AR</th>
                  <th>No Faktur</th>
                  <th style="text-align:right;">Nilai Kwitansi</th>
                  <th style="text-align:center;">Diff (hari)</th>
                  <th>Keterangan</th>
                  <th style="text-align:center;">Fisik</th>
                </tr>
              </thead>
              <tbody>
                @foreach($lsItems as $idx => $kw)
                <tr>
                  <td>{{ (int)$idx+1 }}</td>
                  <td style="font-family:monospace;">{{ $kw['noKwitansi'] ?? '-' }}</td>
                  <td>{{ isset($kw['tglKwitansi']) && $kw['tglKwitansi'] ? \Carbon\Carbon::parse($kw['tglKwitansi'])->format('d/m/Y') : '-' }}</td>
                  <td>{{ $kw['namaCustomer'] ?? '-' }}</td>
                  <td style="font-family:monospace;font-size:10px;">{{ $kw['noAr'] ?? '-' }}</td>
                  <td style="font-family:monospace;font-size:10px;">{{ $kw['noFaktur'] ?? '-' }}</td>
                  <td style="text-align:right;">Rp {{ isset($kw['nilaiKwitansi']) ? number_format($kw['nilaiKwitansi'],0,',','.') : '-' }}</td>
                  <td style="text-align:center;">
                    @php $d = $kw['diff'] ?? null; @endphp
                    @if($d !== null && $d !== '')
                      <span style="font-weight:600;color:{{ $d <= 30 ? '#10b981' : ($d <= 90 ? '#f59e0b' : '#ef4444') }};">{{ $d }}</span>
                    @else
                      -
                    @endif
                  </td>
                  <td>{{ $kw['keterangan'] ?? '-' }}</td>
                  <td style="text-align:center;">
                    @if(!empty($kw['fisik'])) <span style="color:#10b981;font-weight:600;">✓</span>
                    @else <span style="color:#ef4444;">✗</span>
                    @endif
                  </td>
                </tr>
                @endforeach
                <tr style="background:#1e293b;font-weight:600;">
                  <td colspan="6" style="text-align:right;">Sub Total {{ $leasingName }}:</td>
                  <td style="text-align:right;">Rp {{ number_format($lsTotal,0,',','.') }}</td>
                  <td colspan="3"></td>
                </tr>
              </tbody>
            </table>
          </div>
        @endforeach

        {{-- Grand total --}}
        <div style="margin-top:8px;padding:8px 12px;background:#1e3a5f;border-radius:6px;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-weight:600;font-size:12px;">Total Kwitansi Gantung ({{ count($kwItems) }} item)</span>
          <span style="font-weight:700;font-size:13px;">Rp {{ number_format($kwTotalNilai,0,',','.') }}</span>
        </div>
      @else
        <p class="empty">Tidak ada item kwitansi.</p>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     9. PIUTANG REGULER
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['piutang-reguler'] ?? true))
<div class="section section-landscape">
  <div class="section-title">9. PIUTANG REGULER</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'piutang-reguler'])
  <div class="section-body">
    @if(!$piutangReguler)
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $prItems       = $piutangReguler->piutang_json ?? [];
        $prCust        = collect($prItems)->pluck('customer')->filter()->unique()->count();
        $prBelumJto    = array_sum(array_column($prItems, 'belumJto'));
        $prTung15      = array_sum(array_column($prItems, 'tung15'));
        $prTung630     = array_sum(array_column($prItems, 'tung630'));
        $prTung3160    = array_sum(array_column($prItems, 'tung3160'));
        $prTung60      = array_sum(array_column($prItems, 'tung60'));
        $prSaldoAkhir  = array_sum(array_column($prItems, 'saldoAkhir'));
        $fmtPr = fn($v) => $v ? 'Rp '.number_format($v,0,',','.') : '-';
      @endphp

      {{-- Summary cards --}}
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="card-stat" style="flex:1;min-width:80px;">
          <div class="cs-val">{{ $prCust }}</div>
          <div class="cs-lbl">Total Customer</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;">{{ $fmtPr($prBelumJto) }}</div>
          <div class="cs-lbl">Belum Jatuh Tempo</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#f59e0b;">{{ $fmtPr($prTung15) }}</div>
          <div class="cs-lbl">Tunggakan 1–5</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#f97316;">{{ $fmtPr($prTung630) }}</div>
          <div class="cs-lbl">Tunggakan 6–30</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#ef4444;">{{ $fmtPr($prTung3160) }}</div>
          <div class="cs-lbl">Tunggakan 31–60</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#dc2626;">{{ $fmtPr($prTung60) }}</div>
          <div class="cs-lbl">Tunggakan &gt;60</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:110px;">
          <div class="cs-val" style="font-size:11px;">{{ $fmtPr($prSaldoAkhir) }}</div>
          <div class="cs-lbl">Total Saldo Akhir</div>
        </div>
      </div>

      @if(count($prItems))
      <div class="tbl-scroll" style="overflow-x:auto;">
      <table style="font-size:9.5px;">
        <thead>
          <tr>
            <th rowspan="2" style="vertical-align:middle;">#</th>
            <th rowspan="2" style="vertical-align:middle;">Customer</th>
            <th rowspan="2" style="vertical-align:middle;">No Faktur</th>
            <th rowspan="2" style="vertical-align:middle;">Tanggal</th>
            <th rowspan="2" style="vertical-align:middle;">Type</th>
            <th rowspan="2" style="text-align:right;vertical-align:middle;">Saldo Awal</th>
            <th colspan="3" style="text-align:center;">Debet</th>
            <th colspan="3" style="text-align:center;">Kredit</th>
            <th rowspan="2" style="text-align:right;vertical-align:middle;">Saldo Akhir</th>
            <th rowspan="2" style="text-align:right;vertical-align:middle;">Belum JTO</th>
            <th colspan="4" style="text-align:center;">Tunggakan</th>
            <th rowspan="2" style="vertical-align:middle;">Keterangan</th>
          </tr>
          <tr>
            <th style="text-align:right;">Pokok</th>
            <th style="text-align:right;">PPN</th>
            <th style="text-align:right;">Lain2</th>
            <th style="text-align:right;">No Kwit</th>
            <th style="text-align:right;">Tgl Kredit</th>
            <th style="text-align:right;">Pembayaran</th>
            <th style="text-align:right;">1–5</th>
            <th style="text-align:right;">6–30</th>
            <th style="text-align:right;">31–60</th>
            <th style="text-align:right;">&gt;60</th>
          </tr>
        </thead>
        <tbody>
          @foreach($prItems as $i => $pr)
          @php
            $sa = $pr['saldoAkhir'] ?? 0;
            $saCls = $sa > 0 ? 'color:#94a3b8;text-decoration:line-through;' : 'font-weight:600;';
          @endphp
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td style="font-weight:500;color:#93c5fd;">{{ $pr['customer'] ?? '-' }}</td>
            <td style="font-family:monospace;">{{ $pr['noFaktur'] ?? '-' }}</td>
            <td>{{ $pr['tanggal'] ?? '-' }}</td>
            <td style="font-weight:600;">{{ $pr['type'] ?? '-' }}</td>
            <td style="text-align:right;">{{ $pr['saldoAwal'] ? number_format($pr['saldoAwal'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ $pr['pokok'] ? number_format($pr['pokok'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ $pr['ppn'] ? number_format($pr['ppn'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ $pr['lain2'] ? number_format($pr['lain2'],0,',','.') : '-' }}</td>
            <td style="font-family:monospace;">{{ $pr['noKwit'] ?? '-' }}</td>
            <td>{{ $pr['tglKredit'] ?? '-' }}</td>
            <td style="text-align:right;color:#4ade80;">{{ $pr['pembayaran'] ? number_format($pr['pembayaran'],0,',','.') : '-' }}</td>
            <td style="text-align:right;{{ $saCls }}">{{ $sa ? number_format($sa,0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($pr['belumJto'] ?? 0) ? number_format($pr['belumJto'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($pr['tung15'] ?? 0) > 0 ? '#fbbf24' : '#6b7280' }};">{{ ($pr['tung15'] ?? 0) ? number_format($pr['tung15'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($pr['tung630'] ?? 0) > 0 ? '#fb923c' : '#6b7280' }};">{{ ($pr['tung630'] ?? 0) ? number_format($pr['tung630'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($pr['tung3160'] ?? 0) > 0 ? '#f87171' : '#6b7280' }};">{{ ($pr['tung3160'] ?? 0) ? number_format($pr['tung3160'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($pr['tung60'] ?? 0) > 0 ? '#ef4444' : '#6b7280' }};font-weight:{{ ($pr['tung60'] ?? 0) > 0 ? '700' : '400' }};">{{ ($pr['tung60'] ?? 0) ? number_format($pr['tung60'],0,',','.') : '-' }}</td>
            <td>{{ $pr['keterangan'] ?? '-' }}</td>
          </tr>
          @endforeach
          {{-- Total row --}}
          <tr style="background:#1e293b;font-weight:700;font-size:9px;">
            <td colspan="5" style="text-align:right;">TOTAL ({{ count($prItems) }} customer)</td>
            <td style="text-align:right;">{{ $prSaldoAkhir ? number_format(array_sum(array_column($prItems,'saldoAwal')),0,',','.') : '-' }}</td>
            <td colspan="6"></td>
            <td style="text-align:right;">{{ $prSaldoAkhir ? number_format($prSaldoAkhir,0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ $prBelumJto ? number_format($prBelumJto,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#fbbf24;">{{ $prTung15 ? number_format($prTung15,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#fb923c;">{{ $prTung630 ? number_format($prTung630,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#f87171;">{{ $prTung3160 ? number_format($prTung3160,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#ef4444;">{{ $prTung60 ? number_format($prTung60,0,',','.') : '-' }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
      </div>
      @else
        <p class="empty">Tidak ada item.</p>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     10. PIUTANG CDN
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['piutang-cdn'] ?? true))
<div class="section section-landscape">
  <div class="section-title">10. PIUTANG CDN</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'piutang-cdn'])
  <div class="section-body">
    @if(!$piutangCdn)
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $cdnItems    = $piutangCdn->piutang_json ?? [];
        $cdnCust     = collect($cdnItems)->pluck('customer')->filter()->unique()->count();
        $cdnSaldo    = array_sum(array_column($cdnItems, 'saldoPiutang'));
        $cdnBelumJto = array_sum(array_column($cdnItems, 'belumJto'));
        $cdnTung15   = array_sum(array_column($cdnItems, 'tung15'));
        $cdnTung630  = array_sum(array_column($cdnItems, 'tung630'));
        $cdnTung3160 = array_sum(array_column($cdnItems, 'tung3160'));
        $cdnTung60   = array_sum(array_column($cdnItems, 'tung60'));
        $fmtCdn = fn($v) => $v ? 'Rp '.number_format($v,0,',','.') : '-';
      @endphp

      {{-- Summary cards --}}
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="card-stat" style="flex:1;min-width:80px;">
          <div class="cs-val">{{ $cdnCust }}</div>
          <div class="cs-lbl">Total Customer</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:110px;">
          <div class="cs-val" style="font-size:11px;">{{ $fmtCdn($cdnSaldo) }}</div>
          <div class="cs-lbl">Total Saldo Piutang</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;">{{ $fmtCdn($cdnBelumJto) }}</div>
          <div class="cs-lbl">Belum Jatuh Tempo</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#f59e0b;">{{ $fmtCdn($cdnTung15) }}</div>
          <div class="cs-lbl">Tunggakan 1–5</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#f97316;">{{ $fmtCdn($cdnTung630) }}</div>
          <div class="cs-lbl">Tunggakan 6–30</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#ef4444;">{{ $fmtCdn($cdnTung3160) }}</div>
          <div class="cs-lbl">Tunggakan 31–60</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="font-size:11px;color:#dc2626;">{{ $fmtCdn($cdnTung60) }}</div>
          <div class="cs-lbl">Tunggakan &gt;60</div>
        </div>
      </div>

      @if(count($cdnItems))
      <div class="tbl-scroll" style="overflow-x:auto;">
      <table style="font-size:9.5px;">
        <thead>
          <tr>
            <th>#</th>
            <th>No Kontrak</th>
            <th>Customer</th>
            <th style="text-align:right;">Saldo Piutang</th>
            <th style="text-align:right;">Belum JTO</th>
            <th style="text-align:right;">Tung 1–5</th>
            <th style="text-align:right;">Tung 6–30</th>
            <th style="text-align:right;">Tung 31–60</th>
            <th style="text-align:right;">Tung &gt;60</th>
            <th style="text-align:right;">Analisa 0</th>
            <th style="text-align:right;">Analisa 1</th>
            <th style="text-align:right;">Analisa 2</th>
            <th style="text-align:right;">Analisa 3</th>
            <th style="text-align:right;">Analisa 4</th>
            <th style="text-align:right;">Analisa 5</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($cdnItems as $i => $cdn)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td style="font-family:monospace;">{{ $cdn['noKontrak'] ?? '-' }}</td>
            <td style="font-weight:500;">{{ $cdn['customer'] ?? '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['saldoPiutang'] ?? 0) ? number_format($cdn['saldoPiutang'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['belumJto'] ?? 0) ? number_format($cdn['belumJto'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($cdn['tung15'] ?? 0) > 0 ? '#fbbf24' : '#6b7280' }};">{{ ($cdn['tung15'] ?? 0) ? number_format($cdn['tung15'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($cdn['tung630'] ?? 0) > 0 ? '#fb923c' : '#6b7280' }};">{{ ($cdn['tung630'] ?? 0) ? number_format($cdn['tung630'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($cdn['tung3160'] ?? 0) > 0 ? '#f87171' : '#6b7280' }};">{{ ($cdn['tung3160'] ?? 0) ? number_format($cdn['tung3160'],0,',','.') : '-' }}</td>
            <td style="text-align:right;color:{{ ($cdn['tung60'] ?? 0) > 0 ? '#ef4444' : '#6b7280' }};font-weight:{{ ($cdn['tung60'] ?? 0) > 0 ? '700' : '400' }};">{{ ($cdn['tung60'] ?? 0) ? number_format($cdn['tung60'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['analisa0'] ?? 0) ? number_format($cdn['analisa0'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['analisa1'] ?? 0) ? number_format($cdn['analisa1'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['analisa2'] ?? 0) ? number_format($cdn['analisa2'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['analisa3'] ?? 0) ? number_format($cdn['analisa3'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['analisa4'] ?? 0) ? number_format($cdn['analisa4'],0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ ($cdn['analisa5'] ?? 0) ? number_format($cdn['analisa5'],0,',','.') : '-' }}</td>
            <td>{{ $cdn['keterangan'] ?? '-' }}</td>
          </tr>
          @endforeach
          {{-- Total row --}}
          <tr style="background:#1e293b;font-weight:700;font-size:9px;">
            <td colspan="3" style="text-align:right;">TOTAL ({{ count($cdnItems) }} customer)</td>
            <td style="text-align:right;">{{ $cdnSaldo ? number_format($cdnSaldo,0,',','.') : '-' }}</td>
            <td style="text-align:right;">{{ $cdnBelumJto ? number_format($cdnBelumJto,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#fbbf24;">{{ $cdnTung15 ? number_format($cdnTung15,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#fb923c;">{{ $cdnTung630 ? number_format($cdnTung630,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#f87171;">{{ $cdnTung3160 ? number_format($cdnTung3160,0,',','.') : '-' }}</td>
            <td style="text-align:right;color:#ef4444;">{{ $cdnTung60 ? number_format($cdnTung60,0,',','.') : '-' }}</td>
            <td colspan="7"></td>
          </tr>
        </tbody>
      </table>
      </div>
      @else
        <p class="empty">Tidak ada item.</p>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     11. TTP GANTUNG
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['ttp-gantung'] ?? true))
<div class="section">
  <div class="section-title">11. TTP GANTUNG</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'ttp-gantung'])
  <div class="section-body">
    @if(!$ttpGantung)
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $ttpItems    = $ttpGantung->ttp_json ?? [];
        $tglAuditStr = $ttpGantung->tgl_audit ?? null;
        $tglAuditTs  = $tglAuditStr ? strtotime($tglAuditStr) : time();
        $ttpTotBelum = array_sum(array_column($ttpItems, 'belumCair'));
        $ttpTotNilai = array_sum(array_column($ttpItems, 'nilai'));
        $ttpByLeasing = collect($ttpItems)->groupBy('leasing');
        // Compute max diff
        $ttpDiffs = collect($ttpItems)->map(function($r) use ($tglAuditTs) {
            if (!($r['tglTtp'] ?? null)) return null;
            $ts = strtotime($r['tglTtp']);
            return $ts ? (int)(($tglAuditTs - $ts) / 86400) : null;
        })->filter(fn($d) => $d !== null && $d >= 0);
        $ttpMaxDiff = $ttpDiffs->count() ? $ttpDiffs->max() : null;
        $fmtTtp = fn($v) => $v ? number_format($v, 0, ',', '.') : '-';
      @endphp

      {{-- Summary cards --}}
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="card-stat" style="flex:1;min-width:80px;">
          <div class="cs-val">{{ count($ttpItems) }}</div>
          <div class="cs-lbl">Total Data</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:120px;">
          <div class="cs-val" style="font-size:11px;">Rp {{ number_format($ttpTotNilai,0,',','.') }}</div>
          <div class="cs-lbl">Total Nilai TTP</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:120px;">
          <div class="cs-val" style="font-size:11px;color:#f97316;">Rp {{ number_format($ttpTotBelum,0,',','.') }}</div>
          <div class="cs-lbl">Total Belum Cair</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="color:#ef4444;">{{ $ttpMaxDiff !== null ? $ttpMaxDiff.' hari' : '-' }}</div>
          <div class="cs-lbl">Diff Terlama</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val">{{ $ttpByLeasing->count() }}</div>
          <div class="cs-lbl">Kelompok Leasing</div>
        </div>
      </div>

      <div class="kv" style="margin-bottom:10px;">
        <span class="kv-label">Tgl Audit:</span>
        <span class="kv-val">{{ $tglAuditStr ? \Carbon\Carbon::parse($tglAuditStr)->format('d/m/Y') : '-' }}</span>
      </div>

      @if(count($ttpItems))
        @php $no = 0; @endphp
        @foreach($ttpByLeasing as $leasingName => $lsItems)
          @php
            $lsTotNilai = $lsItems->sum('nilai');
            $lsTotBelum = $lsItems->sum('belumCair');
          @endphp
          <div style="margin-bottom:14px;">
            <div style="font-weight:600;font-size:11px;margin-bottom:4px;padding:4px 8px;background:#1e293b;border-left:3px solid #f59e0b;text-transform:uppercase;letter-spacing:.05em;">
              {{ $leasingName ?: '-' }}
              <span style="font-weight:400;color:#94a3b8;margin-left:8px;">{{ $lsItems->count() }} tagihan</span>
            </div>
            <table style="font-size:9.5px;">
              <thead>
                <tr>
                  <th rowspan="2" style="vertical-align:middle;">#</th>
                  <th colspan="2" style="text-align:center;">TTP</th>
                  <th colspan="3" style="text-align:center;">Faktur</th>
                  <th colspan="2" style="text-align:center;">Pencairan</th>
                  <th rowspan="2" style="text-align:right;vertical-align:middle;">Tagihan Belum Cair</th>
                  <th rowspan="2" style="vertical-align:middle;">Keterangan</th>
                  <th rowspan="2" style="text-align:center;vertical-align:middle;">Diff (hari)</th>
                  <th rowspan="2" style="text-align:center;vertical-align:middle;">Fisik</th>
                </tr>
                <tr>
                  <th>No TTP</th>
                  <th>Tgl TTP</th>
                  <th>No Faktur</th>
                  <th>Nama</th>
                  <th style="text-align:right;">Nilai</th>
                  <th>Tanggal</th>
                  <th style="text-align:right;">Nilai</th>
                </tr>
              </thead>
              <tbody>
                @foreach($lsItems as $t)
                  @php
                    $no++;
                    $diff = null;
                    if (!empty($t['tglTtp'])) {
                        $ts = strtotime($t['tglTtp']);
                        if ($ts) $diff = (int)(($tglAuditTs - $ts) / 86400);
                    }
                    $diffColor = $diff === null ? '#6b7280'
                               : ($diff > 60 ? '#ef4444' : ($diff > 30 ? '#f97316' : '#94a3b8'));
                    $diffWeight = ($diff !== null && $diff > 60) ? '700' : '400';
                  @endphp
                  <tr>
                    <td>{{ $no }}</td>
                    <td style="font-family:monospace;color:#93c5fd;">{{ $t['noTtp'] ?? '-' }}</td>
                    <td>{{ $t['tglTtp'] ?? '-' }}</td>
                    <td style="font-family:monospace;font-size:9px;">{{ $t['noFaktur'] ?? '-' }}</td>
                    <td>{{ $t['nama'] ?? '-' }}</td>
                    <td style="text-align:right;">{{ $fmtTtp($t['nilai'] ?? 0) }}</td>
                    <td>{{ $t['pencTgl'] ?? '-' }}</td>
                    <td style="text-align:right;color:{{ ($t['pencNilai'] ?? 0) > 0 ? '#4ade80' : '#6b7280' }};">{{ $fmtTtp($t['pencNilai'] ?? 0) }}</td>
                    <td style="text-align:right;font-weight:{{ ($t['belumCair'] ?? 0) > 0 ? '600' : '400' }};color:{{ ($t['belumCair'] ?? 0) > 0 ? '#fb923c' : '#6b7280' }};">{{ $fmtTtp($t['belumCair'] ?? 0) }}</td>
                    <td style="font-size:9px;max-width:180px;">{{ $t['keterangan'] ?? '-' }}</td>
                    <td style="text-align:center;color:{{ $diffColor }};font-weight:{{ $diffWeight }};">{{ $diff !== null ? $diff.' hr' : '-' }}</td>
                    <td style="text-align:center;">
                      @if(!empty($t['fisik'])) <span style="color:#10b981;font-weight:700;">✓</span>
                      @else <span style="color:#ef4444;">✗</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
                <tr style="background:#1e293b;font-weight:700;font-size:9px;">
                  <td colspan="5" style="text-align:right;">Sub Total {{ $leasingName }}:</td>
                  <td style="text-align:right;">{{ number_format($lsTotNilai,0,',','.') }}</td>
                  <td colspan="2"></td>
                  <td style="text-align:right;color:#fb923c;">{{ number_format($lsTotBelum,0,',','.') }}</td>
                  <td colspan="3"></td>
                </tr>
              </tbody>
            </table>
          </div>
        @endforeach

        {{-- Grand total --}}
        <div style="margin-top:8px;padding:8px 12px;background:#1e3a5f;border-radius:6px;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-weight:600;font-size:12px;">Total TTP Gantung ({{ count($ttpItems) }} tagihan)</span>
          <span style="font-weight:700;font-size:13px;color:#fb923c;">Rp {{ number_format($ttpTotBelum,0,',','.') }} belum cair</span>
        </div>
      @else
        <p class="empty">Tidak ada item.</p>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     12. CEK FISIK
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['cek-fisik'] ?? true))
<div class="section">
  <div class="section-title">12. CEK FISIK (Blangko Cek Fisik &amp; STUJ)</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'cek-fisik'])
  <div class="section-body">
    @if(!$cekFisik)
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $cf     = $cekFisik->data_json ?? [];
        $cfSa   = $cf['saldoAwal']  ?? ['tanggal'=>'', 'cf'=>0, 'stuj'=>0, 'fstnk'=>0];
        $cfPen  = $cf['penerimaan'] ?? [];
        $cfKel  = $cf['pengeluaran']?? [];
        $cfFis  = $cf['fisik']      ?? ['cf'=>0,'stuj'=>0,'fstnk'=>0];
        // Compute saldo akhir
        $cfAkhirCf    = ($cfSa['cf']    ?? 0);
        $cfAkhirStuj  = ($cfSa['stuj']  ?? 0);
        $cfAkhirFstnk = ($cfSa['fstnk'] ?? 0);
        foreach ($cfPen as $r) { $cfAkhirCf += ($r['cf']??0); $cfAkhirStuj += ($r['stuj']??0); $cfAkhirFstnk += ($r['fstnk']??0); }
        foreach ($cfKel as $r) { $cfAkhirCf -= ($r['cf']??0); $cfAkhirStuj -= ($r['stuj']??0); $cfAkhirFstnk -= ($r['fstnk']??0); }
        $cfSelCf    = $cfAkhirCf    - ($cfFis['cf']    ?? 0);
        $cfSelStuj  = $cfAkhirStuj  - ($cfFis['stuj']  ?? 0);
        $cfSelFstnk = $cfAkhirFstnk - ($cfFis['fstnk'] ?? 0);
        $selColor = fn($v) => $v == 0 ? '#10b981' : '#ef4444';
      @endphp

      {{-- Summary stat cards --}}
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        {{-- CEK FISIK --}}
        <div style="flex:1;min-width:150px;background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:10px 14px;">
          <div style="font-size:10px;font-weight:600;color:#60a5fa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">CEK FISIK (CF)</div>
          <div style="display:flex;gap:12px;">
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;">{{ $cfSa['cf'] ?? 0 }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Awal</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;color:#60a5fa;">{{ $cfAkhirCf }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Akhir</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;color:{{ $selColor($cfSelCf) }};">{{ $cfSelCf }}</div><div style="font-size:9px;color:#94a3b8;">Selisih</div></div>
          </div>
        </div>
        {{-- STUJ --}}
        <div style="flex:1;min-width:150px;background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:10px 14px;">
          <div style="font-size:10px;font-weight:600;color:#a78bfa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">STUJ</div>
          <div style="display:flex;gap:12px;">
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;">{{ $cfSa['stuj'] ?? 0 }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Awal</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;color:#a78bfa;">{{ $cfAkhirStuj }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Akhir</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;color:{{ $selColor($cfSelStuj) }};">{{ $cfSelStuj }}</div><div style="font-size:9px;color:#94a3b8;">Selisih</div></div>
          </div>
        </div>
        {{-- F.STNK --}}
        <div style="flex:1;min-width:150px;background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:10px 14px;">
          <div style="font-size:10px;font-weight:600;color:#34d399;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">F. STNK</div>
          <div style="display:flex;gap:12px;">
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;">{{ $cfSa['fstnk'] ?? 0 }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Awal</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;color:#34d399;">{{ $cfAkhirFstnk }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Akhir</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:13px;font-weight:700;color:{{ $selColor($cfSelFstnk) }};">{{ $cfSelFstnk }}</div><div style="font-size:9px;color:#94a3b8;">Selisih</div></div>
          </div>
        </div>
      </div>

      {{-- Rekap tabel 4 baris --}}
      <table style="margin-bottom:14px;">
        <thead>
          <tr>
            <th style="width:180px;">Keterangan</th>
            <th style="text-align:center;">Cek Fisik</th>
            <th style="text-align:center;">STUJ</th>
            <th style="text-align:center;">F. STNK</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Saldo Awal ({{ $cfSa['tanggal'] ?? '-' }})</td>
            <td style="text-align:center;">{{ $cfSa['cf'] ?? 0 }}</td>
            <td style="text-align:center;">{{ $cfSa['stuj'] ?? 0 }}</td>
            <td style="text-align:center;">{{ $cfSa['fstnk'] ?? 0 }}</td>
          </tr>
          @if(count($cfPen))
            @foreach($cfPen as $r)
            <tr style="color:#4ade80;">
              <td>+ Penerimaan{{ ($r['tanggal']??'') ? ' ('.$r['tanggal'].')' : '' }}{{ ($r['noDokumen']??'') ? ' – '.$r['noDokumen'] : '' }}</td>
              <td style="text-align:center;">{{ $r['cf'] ?? 0 }}</td>
              <td style="text-align:center;">{{ $r['stuj'] ?? 0 }}</td>
              <td style="text-align:center;">{{ $r['fstnk'] ?? 0 }}</td>
            </tr>
            @endforeach
          @endif
          @if(count($cfKel))
            @foreach($cfKel as $r)
            <tr style="color:#f87171;">
              <td>– Pengeluaran{{ ($r['noDokumen']??'') ? ' ('.$r['noDokumen'].')' : '' }}</td>
              <td style="text-align:center;">{{ $r['cf'] ?? 0 }}</td>
              <td style="text-align:center;">{{ $r['stuj'] ?? 0 }}</td>
              <td style="text-align:center;">{{ $r['fstnk'] ?? 0 }}</td>
            </tr>
            @endforeach
          @endif
          <tr style="background:#1e293b;font-weight:700;">
            <td>Saldo Akhir (Sistem)</td>
            <td style="text-align:center;color:#60a5fa;">{{ $cfAkhirCf }}</td>
            <td style="text-align:center;color:#a78bfa;">{{ $cfAkhirStuj }}</td>
            <td style="text-align:center;color:#34d399;">{{ $cfAkhirFstnk }}</td>
          </tr>
          <tr>
            <td>Fisik (Hasil Pemeriksaan)</td>
            <td style="text-align:center;">{{ $cfFis['cf'] ?? 0 }}</td>
            <td style="text-align:center;">{{ $cfFis['stuj'] ?? 0 }}</td>
            <td style="text-align:center;">{{ $cfFis['fstnk'] ?? 0 }}</td>
          </tr>
          <tr style="font-weight:700;">
            <td>Selisih</td>
            <td style="text-align:center;color:{{ $selColor($cfSelCf) }};">{{ $cfSelCf }}</td>
            <td style="text-align:center;color:{{ $selColor($cfSelStuj) }};">{{ $cfSelStuj }}</td>
            <td style="text-align:center;color:{{ $selColor($cfSelFstnk) }};">{{ $cfSelFstnk }}</td>
          </tr>
        </tbody>
      </table>

      @php $hasSelisih = $cfSelCf != 0 || $cfSelStuj != 0 || $cfSelFstnk != 0; @endphp
      @if($hasSelisih)
      <div style="padding:8px 12px;background:#450a0a;border:1px solid #ef4444;border-radius:6px;color:#fca5a5;font-size:11px;font-weight:600;">
        ⚠ Terdapat selisih pada pemeriksaan blangko:
        @if($cfSelCf != 0) CF: {{ $cfSelCf > 0 ? '+'.$cfSelCf : $cfSelCf }}; @endif
        @if($cfSelStuj != 0) STUJ: {{ $cfSelStuj > 0 ? '+'.$cfSelStuj : $cfSelStuj }}; @endif
        @if($cfSelFstnk != 0) F.STNK: {{ $cfSelFstnk > 0 ? '+'.$cfSelFstnk : $cfSelFstnk }}; @endif
      </div>
      @else
      <div style="padding:8px 12px;background:#052e16;border:1px solid #10b981;border-radius:6px;color:#6ee7b7;font-size:11px;font-weight:600;">
        ✓ Tidak ada selisih — saldo sistem sesuai dengan fisik.
      </div>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     13. MT (Mechanic Truster Tools)
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['mt'] ?? true))
<div class="section">
  <div class="section-title">13. MT (Mechanic Truster Tools)</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'mt'])
  <div class="section-body" style="padding:0;">
    @if(!$mt)
      <p class="empty" style="padding:12px;">Belum ada data.</p>
    @else
      @php
        $mtRaw           = $mt->data_json ?? [];
        $mtEntries       = $mtRaw['entries'] ?? [];
        $mtSelectedJenis = $mtRaw['mekanikSelectedJenis'] ?? [];
        $mtEntriesFiltered = collect($mtEntries)->filter(function($e) use ($mtSelectedJenis) {
            $mekanik  = $e['mekanik'] ?? '';
            $selected = $mtSelectedJenis[$mekanik] ?? 'baru';
            return ($e['jenis'] ?? '') === $selected;
        });
        $mtByMekanik  = $mtEntriesFiltered->groupBy('mekanik');
        $mtJenisLabel = ['baru' => 'Baru', 'lama' => 'Lama', 'fi' => 'FI'];
        $mtKatLabel   = ['bagus' => 'Bagus', 'rusak' => 'Rusak', 'skAudit' => 'SK Audit', 'hilang' => 'Hilang'];
        $mtKatBg      = ['bagus' => '#d1fae5', 'rusak' => '#fee2e2', 'skAudit' => '#dbeafe', 'hilang' => '#ffedd5'];
        $mtKatText    = ['bagus' => '#065f46', 'rusak' => '#991b1b', 'skAudit' => '#1e40af', 'hilang' => '#9a3412'];
        $mtKatBorder  = ['bagus' => '#6ee7b7', 'rusak' => '#fca5a5', 'skAudit' => '#93c5fd', 'hilang' => '#fdba74'];
        $mtKatIcon    = ['bagus' => '✔', 'rusak' => '✘', 'skAudit' => '⚑', 'hilang' => '!'];
      @endphp

      @if($mtByMekanik->isEmpty())
        <p class="empty" style="padding:12px;">Tidak ada data MT.</p>
      @else
        @foreach($mtByMekanik as $mekanik => $entries)
          @php
            $mekanikIdx = $loop->index + 1;
          @endphp
          {{-- ── Mechanic card ── --}}
          <div style="border-bottom:{{ $loop->last ? 'none' : '2px solid #e5e7eb' }};padding:16px 16px 20px;">

            {{-- Mechanic header bar --}}
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
              <div style="width:32px;height:32px;border-radius:50%;background:#1e40af;color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                {{ $mekanikIdx }}
              </div>
              <div>
                <div style="font-size:11px;font-weight:700;color:#111827;line-height:1.2;">{{ $mekanik }}</div>
                <div style="font-size:10px;color:#6b7280;">Mechanic Truster Tools – Pemeriksaan Alat</div>
              </div>
              @foreach($entries as $entry)
                @php
                  $jenisKey = $entry['jenis'] ?? 'baru';
                  $jenisLbl = $mtJenisLabel[$jenisKey] ?? strtoupper($jenisKey);
                  $jenisBg  = $jenisKey === 'fi' ? '#7c3aed' : ($jenisKey === 'lama' ? '#0369a1' : '#1e40af');
                  $totalAll = collect(['bagus','rusak','skAudit','hilang'])->sum(fn($k) => count($entry[$k] ?? []));
                @endphp
                <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                  <span style="background:{{ $jenisBg }};color:#fff;font-size:11px;font-weight:700;padding:3px 14px;border-radius:999px;letter-spacing:.5px;">
                    Jenis: {{ $jenisLbl }}
                  </span>
                  <span style="background:#f3f4f6;color:#374151;font-size:10px;font-weight:600;padding:3px 10px;border-radius:999px;border:1px solid #d1d5db;">
                    {{ $totalAll }} Tools
                  </span>
                </div>
              @endforeach
            </div>

            @foreach($entries as $entry)
              @php
                $bagus   = $entry['bagus']   ?? [];
                $rusak   = $entry['rusak']   ?? [];
                $skAudit = $entry['skAudit'] ?? [];
                $hilang  = $entry['hilang']  ?? [];
              @endphp

              {{-- Summary stat row --}}
              <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px;">
                @foreach(['bagus','rusak','skAudit','hilang'] as $kat)
                  @php $cnt = count($entry[$kat] ?? []); @endphp
                  <div style="border:1px solid {{ $mtKatBorder[$kat] }};border-radius:8px;padding:8px 10px;background:{{ $mtKatBg[$kat] }};text-align:center;">
                    <div style="font-size:15px;font-weight:800;color:{{ $mtKatText[$kat] }};line-height:1;">{{ $cnt }}</div>
                    <div style="font-size:9.5px;font-weight:600;color:{{ $mtKatText[$kat] }};margin-top:2px;opacity:.85;">{{ $mtKatIcon[$kat] }} {{ $mtKatLabel[$kat] }}</div>
                  </div>
                @endforeach
              </div>

              {{-- Detail table per kategori --}}
              @foreach(['bagus','rusak','skAudit','hilang'] as $kat)
                @php $tools = $entry[$kat] ?? []; @endphp
                @if(count($tools))
                <div style="margin-bottom:10px;">
                  {{-- Kategori header --}}
                  <div style="background:{{ $mtKatBg[$kat] }};border:1px solid {{ $mtKatBorder[$kat] }};border-bottom:none;padding:5px 10px;border-radius:6px 6px 0 0;display:flex;align-items:center;gap:6px;">
                    <span style="font-size:11px;font-weight:700;color:{{ $mtKatText[$kat] }};">{{ $mtKatIcon[$kat] }} {{ $mtKatLabel[$kat] }}</span>
                    <span style="font-size:10px;color:{{ $mtKatText[$kat] }};opacity:.7;">({{ count($tools) }} item)</span>
                  </div>
                  {{-- Tool grid --}}
                  <div style="border:1px solid {{ $mtKatBorder[$kat] }};border-radius:0 0 6px 6px;padding:8px 10px;background:#fff;">
                    <div style="display:flex;flex-wrap:wrap;gap:5px;">
                      @foreach($tools as $tool)
                      <span style="font-size:9.5px;font-weight:500;padding:3px 9px;border-radius:4px;background:{{ $mtKatBg[$kat] }};color:{{ $mtKatText[$kat] }};border:1px solid {{ $mtKatBorder[$kat] }};">
                        {{ $tool }}
                      </span>
                      @endforeach
                    </div>
                  </div>
                </div>
                @endif
              @endforeach

              @if(!empty($entry['keterangan']))
              <div style="margin-top:4px;padding:8px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
                <span style="font-size:9.5px;font-weight:700;color:#374151;">Keterangan: </span>
                <span style="font-size:9.5px;color:#4b5563;">{{ $entry['keterangan'] }}</span>
              </div>
              @endif

            @endforeach
          </div>
        @endforeach
      @endif
    @endif
  </div>
</div>

{{-- ── REKAP TOOLS RUSAK & HILANG — replika format laporan lama, dicetak
     terpisah dari kartu/tabel MT di atas. Dilewati kalau memang tidak ada
     tool rusak/hilang sama sekali (auditor lain tidak perlu ganti halaman
     percuma), tapi tetap tampil dengan pesan kosong kalau tab MT-nya
     sendiri terisi tapi kebetulan semua tool statusnya bagus. Lihat
     MtRekapBuilder untuk cara kode & harga tool didapat. ── --}}
@if($mt)
<div class="section" style="page-break-before:always;">
  <div class="section-title">13a. REKAP TOOLS RUSAK (MT)</div>
  <div class="section-body">
    @include('akta.pdf.partials.mt-rekap-header', ['plan' => $plan, 'auditor' => $auditors['mt'] ?? null, 'judulRekap' => 'RUSAK'])
    @include('akta.pdf.partials.mt-rekap-tables', ['rekap' => $mtRekap['rusak'] ?? [], 'kategori' => 'rusak'])
  </div>
</div>

<div class="section" style="page-break-before:always;">
  <div class="section-title">13b. REKAP TOOLS HILANG (MT)</div>
  <div class="section-body">
    @include('akta.pdf.partials.mt-rekap-header', ['plan' => $plan, 'auditor' => $auditors['mt'] ?? null, 'judulRekap' => 'HILANG'])
    @include('akta.pdf.partials.mt-rekap-tables', ['rekap' => $mtRekap['hilang'] ?? [], 'kategori' => 'hilang'])
  </div>
</div>
@endif
@endif

{{-- ═══════════════════════════════════════════════
     14. HGP & AHM OILS
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['hgp'] ?? true))
{{-- Cetak melintang HANYA kalau ada isinya. Tabel section ini punya 12 kolom
     (lebar minimum 800-900px) sehingga terpotong di A4 tegak — kolom paling
     kanan hilang sama sekali dari PDF, bukan sekadar tak terlihat. Tapi
     .section-landscape juga memaksa ganti halaman sebelum & sesudahnya, jadi
     kalau datanya kosong ia hanya akan memboroskan satu halaman melintang
     berisi tulisan "Belum ada data". --}}
@php
    $lebar = filled($hgp?->items_json);
@endphp
<div class="section {{ $lebar ? 'section-landscape' : '' }}">
  <div class="section-title">14. HGP &amp; AHM OILS</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'hgp'])
  <div class="section-body" style="padding:0;">
    @if(!$hgp)
      <p class="empty" style="padding:12px;">Belum ada data.</p>
    @else
      @php
        $hgpItems = $hgp->items_json ?? [];
        $hN = fn($v) => (float)($v ?? 0);
        $hgpTotalSaldo   = array_sum(array_map(fn($it) => $hN($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0), $hgpItems));
        $hgpTotalFisikOnly = array_sum(array_map(fn($it) => $hN($it['fisik'] ?? 0), $hgpItems));
        $hgpTotalWo      = array_sum(array_map(fn($it) => $hN($it['wo'] ?? 0), $hgpItems));
        $hgpTotalFisik   = $hgpTotalFisikOnly + $hgpTotalWo;
        $hgpTotalSelisih = array_sum(array_map(fn($it) => $hN($it['selisih'] ?? 0), $hgpItems));
        $hgpTotalJumlah  = array_sum(array_map(fn($it) => $hN($it['hargaHet'] ?? 0) * $hN($it['selisih'] ?? 0), $hgpItems));
        $hgpSelCount     = count(array_filter($hgpItems, fn($it) => ($it['selisih'] ?? 0) != 0));
        $fmtN = fn($v) => number_format((float)$v, 0, ',', '.');
        $fmtSign = fn($v) => ($v > 0 ? '+' : '') . number_format((float)$v, 0, ',', '.');
      @endphp

      {{-- Summary cards --}}
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:#1e40af;">{{ count($hgpItems) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total Item</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:#059669;">{{ $fmtN($hgpTotalFisik) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total Fisik + WO</div>
        </div>
        <div style="background:#fff;border:1px solid {{ $hgpTotalSelisih < 0 ? '#fee2e2' : '#e5e7eb' }};border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:{{ $hgpTotalSelisih < 0 ? '#dc2626' : ($hgpTotalSelisih > 0 ? '#d97706' : '#059669') }};">{{ $fmtSign($hgpTotalSelisih) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total Selisih Qty</div>
        </div>
        <div style="background:#fff;border:1px solid {{ $hgpTotalJumlah < 0 ? '#fee2e2' : '#e5e7eb' }};border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:{{ $hgpTotalJumlah < 0 ? '#dc2626' : ($hgpTotalJumlah > 0 ? '#d97706' : '#059669') }};">{{ $fmtSign($hgpTotalJumlah) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Nilai Selisih (Rp)</div>
        </div>
      </div>

      @if(count($hgpItems))
      <div class="tbl-scroll" style="overflow-x:auto;">
      <table style="font-size:9.5px;min-width:800px;">
        <thead>
          <tr>
            <th style="width:28px;">#</th>
            <th style="width:80px;">No. Part</th>
            <th>Nama Part</th>
            <th style="width:70px;text-align:center;">Tgl Periksa</th>
            <th style="width:50px;text-align:right;">Saldo Akhir</th>
            <th style="width:40px;text-align:right;">Fisik</th>
            <th style="width:36px;text-align:right;color:#92400e;background:#fffbeb;">WO</th>
            <th style="width:50px;text-align:right;">Akhir</th>
            <th style="width:46px;text-align:right;">Selisih</th>
            <th style="width:70px;text-align:right;">Harga HET</th>
            <th style="width:80px;text-align:right;">Jumlah (Rp)</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @php $hgpGrandJumlah = 0; @endphp
          @foreach($hgpItems as $i => $it)
            @php
              $saldo   = $hN($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0);
              $fisik   = $hN($it['fisik'] ?? 0);
              $wo      = $hN($it['wo'] ?? 0);
              $totalFisik = $fisik + $wo;
              $akhir   = $hN($it['akhir'] ?? ($saldo - $totalFisik));
              $selisih = $hN($it['selisih'] ?? ($totalFisik - $saldo));
              $harga   = $hN($it['hargaHet'] ?? 0);
              $jumlah  = $harga * $selisih;
              $hgpGrandJumlah += $jumlah;
              $selColor = $selisih < 0 ? '#dc2626' : ($selisih > 0 ? '#d97706' : '#374151');
              $jmlColor = $jumlah < 0 ? '#dc2626' : ($jumlah > 0 ? '#d97706' : '#374151');
            @endphp
            <tr>
              <td>{{ (int)$i + 1 }}</td>
              <td style="font-size:8.5px;color:#6b7280;">{{ $it['noPart'] ?? '-' }}</td>
              <td style="font-weight:600;">{{ $it['sparepart'] ?? $it['nama'] ?? '-' }}</td>
              <td style="text-align:center;color:#6b7280;">{{ $it['tgl'] ?? '-' }}</td>
              <td style="text-align:right;">{{ $saldo > 0 ? $fmtN($saldo) : '0' }}</td>
              <td style="text-align:right;font-weight:700;">{{ $fmtN($fisik) }}</td>
              <td style="text-align:right;background:#fffbeb;color:#92400e;font-weight:{{ $wo > 0 ? '700' : '400' }};">{{ $wo > 0 ? $fmtN($wo) : '—' }}</td>
              <td style="text-align:right;">{{ $fmtN($akhir) }}</td>
              <td style="text-align:right;font-weight:700;color:{{ $selColor }};">{{ $selisih >= 0 ? '+' : '' }}{{ $fmtN($selisih) }}</td>
              <td style="text-align:right;color:#6b7280;">{{ $harga > 0 ? $fmtN($harga) : '—' }}</td>
              <td style="text-align:right;font-weight:700;color:{{ $jmlColor }};">{{ $jumlah != 0 ? ($jumlah >= 0 ? '+' : '') . $fmtN($jumlah) : '—' }}</td>
              <td style="font-size:9px;color:#6b7280;">{{ $it['keterangan'] ?? '' }}</td>
            </tr>
          @endforeach
          {{-- Total row --}}
          <tr style="background:#f3f4f6;font-weight:700;border-top:2px solid #d1d5db;">
            <td colspan="4" style="text-align:right;">TOTAL</td>
            <td style="text-align:right;">{{ $fmtN($hgpTotalSaldo) }}</td>
            <td style="text-align:right;">{{ $fmtN($hgpTotalFisikOnly) }}</td>
            <td style="text-align:right;background:#fffbeb;color:#92400e;">{{ $hgpTotalWo > 0 ? $fmtN($hgpTotalWo) : '—' }}</td>
            <td></td>
            <td style="text-align:right;color:{{ $hgpTotalSelisih < 0 ? '#dc2626' : ($hgpTotalSelisih > 0 ? '#d97706' : '#374151') }};">{{ $fmtSign($hgpTotalSelisih) }}</td>
            <td></td>
            <td style="text-align:right;color:{{ $hgpGrandJumlah < 0 ? '#dc2626' : ($hgpGrandJumlah > 0 ? '#d97706' : '#374151') }};">{{ $hgpGrandJumlah != 0 ? $fmtSign($hgpGrandJumlah) : '—' }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
      </div>
      @else
        <p class="empty" style="padding:12px;">Tidak ada item.</p>
      @endif

      {{-- Rekap Selisih Part & AHM Oil's: hanya item yang selisihnya tidak
           nol dari tabel di atas, dipecah AHM OIL'S (kode part terdaftar di
           Database AHM Oil) vs SPAREPART (sisanya). Nomor barisnya sama
           dengan nomor baris di tabel lengkap di atas. --}}
      @if($hgpSelCount > 0)
      <div style="padding:4px 14px 14px;">
        <div class="group-title" style="font-size:12px;">REKAP SELISIH PART &amp; AHM OIL'S</div>
        <div class="group-title">AHM OIL'S</div>
        @include('akta.pdf.partials.rekap-selisih-table', ['items' => $hgpOilItems])
        <div class="group-title">SPAREPART</div>
        @include('akta.pdf.partials.rekap-selisih-table', ['items' => $hgpSparepartItems])
      </div>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     14B. RSA HGP & AHM OILS (SAMPLING)
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['rsa-hgp'] ?? true))
{{-- Cetak melintang HANYA kalau ada isinya. Tabel section ini punya 12 kolom
     (lebar minimum 800-900px) sehingga terpotong di A4 tegak — kolom paling
     kanan hilang sama sekali dari PDF, bukan sekadar tak terlihat. Tapi
     .section-landscape juga memaksa ganti halaman sebelum & sesudahnya, jadi
     kalau datanya kosong ia hanya akan memboroskan satu halaman melintang
     berisi tulisan "Belum ada data". --}}
@php
    $lebar = filled($rsaHgp?->items_json);
@endphp
<div class="section {{ $lebar ? 'section-landscape' : '' }}">
  <div class="section-title">14B. RSA HGP &amp; AHM OILS (SAMPLING)</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'rsa-hgp'])
  <div class="section-body" style="padding:0;">
    @if(!$rsaHgp)
      <p class="empty" style="padding:12px;">Belum ada data.</p>
    @else
      @php
        $rsaHgpItems = $rsaHgp->items_json ?? [];
        $hN = fn($v) => (float)($v ?? 0);
        $rsaHgpTotalSaldo   = array_sum(array_map(fn($it) => $hN($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0), $rsaHgpItems));
        $rsaHgpTotalFisikOnly = array_sum(array_map(fn($it) => $hN($it['fisik'] ?? 0), $rsaHgpItems));
        $rsaHgpTotalWo      = array_sum(array_map(fn($it) => $hN($it['wo'] ?? 0), $rsaHgpItems));
        $rsaHgpTotalFisik   = $rsaHgpTotalFisikOnly + $rsaHgpTotalWo;
        $rsaHgpTotalSelisih = array_sum(array_map(fn($it) => $hN($it['selisih'] ?? 0), $rsaHgpItems));
        $rsaHgpTotalJumlah  = array_sum(array_map(fn($it) => $hN($it['hargaHet'] ?? 0) * $hN($it['selisih'] ?? 0), $rsaHgpItems));
        $rsaHgpSelCount     = count(array_filter($rsaHgpItems, fn($it) => ($it['selisih'] ?? 0) != 0));
        $fmtN = fn($v) => number_format((float)$v, 0, ',', '.');
        $fmtSign = fn($v) => ($v > 0 ? '+' : '') . number_format((float)$v, 0, ',', '.');
      @endphp

      {{-- Summary cards --}}
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:#1e40af;">{{ count($rsaHgpItems) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total Item</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:#059669;">{{ $fmtN($rsaHgpTotalFisik) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total Fisik + WO</div>
        </div>
        <div style="background:#fff;border:1px solid {{ $rsaHgpTotalSelisih < 0 ? '#fee2e2' : '#e5e7eb' }};border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:{{ $rsaHgpTotalSelisih < 0 ? '#dc2626' : ($rsaHgpTotalSelisih > 0 ? '#d97706' : '#059669') }};">{{ $fmtSign($rsaHgpTotalSelisih) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total Selisih Qty</div>
        </div>
        <div style="background:#fff;border:1px solid {{ $rsaHgpTotalJumlah < 0 ? '#fee2e2' : '#e5e7eb' }};border-radius:8px;padding:10px 12px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:{{ $rsaHgpTotalJumlah < 0 ? '#dc2626' : ($rsaHgpTotalJumlah > 0 ? '#d97706' : '#059669') }};">{{ $fmtSign($rsaHgpTotalJumlah) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Nilai Selisih (Rp)</div>
        </div>
      </div>

      @if($rsaHgp->total_ditemukan && $rsaHgp->sample_size && $rsaHgp->total_ditemukan > $rsaHgp->sample_size)
      <p style="padding:8px 14px;margin:0;font-size:9px;color:#92400e;background:#fffbeb;border-bottom:1px solid #e5e7eb;">
        ℹ️ Random Sampling Audit — tabel di bawah adalah {{ $rsaHgp->sample_size }} item hasil sampling otomatis
        dari total {{ $rsaHgp->total_ditemukan }} item yang ditemukan saat import, bukan seluruh populasi.
      </p>
      @endif

      @if(count($rsaHgpItems))
      <div class="tbl-scroll" style="overflow-x:auto;">
      <table style="font-size:9.5px;min-width:800px;">
        <thead>
          <tr>
            <th style="width:28px;">#</th>
            <th style="width:80px;">No. Part</th>
            <th>Nama Part</th>
            <th style="width:70px;text-align:center;">Tgl Periksa</th>
            <th style="width:50px;text-align:right;">Saldo Akhir</th>
            <th style="width:40px;text-align:right;">Fisik</th>
            <th style="width:36px;text-align:right;color:#92400e;background:#fffbeb;">WO</th>
            <th style="width:50px;text-align:right;">Akhir</th>
            <th style="width:46px;text-align:right;">Selisih</th>
            <th style="width:70px;text-align:right;">Harga HET</th>
            <th style="width:80px;text-align:right;">Jumlah (Rp)</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @php $rsaHgpGrandJumlah = 0; @endphp
          @foreach($rsaHgpItems as $i => $it)
            @php
              $saldo   = $hN($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0);
              $fisik   = $hN($it['fisik'] ?? 0);
              $wo      = $hN($it['wo'] ?? 0);
              $totalFisik = $fisik + $wo;
              $akhir   = $hN($it['akhir'] ?? ($saldo - $totalFisik));
              $selisih = $hN($it['selisih'] ?? ($totalFisik - $saldo));
              $harga   = $hN($it['hargaHet'] ?? 0);
              $jumlah  = $harga * $selisih;
              $rsaHgpGrandJumlah += $jumlah;
              $selColor = $selisih < 0 ? '#dc2626' : ($selisih > 0 ? '#d97706' : '#374151');
              $jmlColor = $jumlah < 0 ? '#dc2626' : ($jumlah > 0 ? '#d97706' : '#374151');
            @endphp
            <tr>
              <td>{{ (int)$i + 1 }}</td>
              <td style="font-size:8.5px;color:#6b7280;">{{ $it['noPart'] ?? '-' }}</td>
              <td style="font-weight:600;">{{ $it['sparepart'] ?? $it['nama'] ?? '-' }}</td>
              <td style="text-align:center;color:#6b7280;">{{ $it['tgl'] ?? '-' }}</td>
              <td style="text-align:right;">{{ $saldo > 0 ? $fmtN($saldo) : '0' }}</td>
              <td style="text-align:right;font-weight:700;">{{ $fmtN($fisik) }}</td>
              <td style="text-align:right;background:#fffbeb;color:#92400e;font-weight:{{ $wo > 0 ? '700' : '400' }};">{{ $wo > 0 ? $fmtN($wo) : '—' }}</td>
              <td style="text-align:right;">{{ $fmtN($akhir) }}</td>
              <td style="text-align:right;font-weight:700;color:{{ $selColor }};">{{ $selisih >= 0 ? '+' : '' }}{{ $fmtN($selisih) }}</td>
              <td style="text-align:right;color:#6b7280;">{{ $harga > 0 ? $fmtN($harga) : '—' }}</td>
              <td style="text-align:right;font-weight:700;color:{{ $jmlColor }};">{{ $jumlah != 0 ? ($jumlah >= 0 ? '+' : '') . $fmtN($jumlah) : '—' }}</td>
              <td style="font-size:9px;color:#6b7280;">{{ $it['keterangan'] ?? '' }}</td>
            </tr>
          @endforeach
          {{-- Total row --}}
          <tr style="background:#f3f4f6;font-weight:700;border-top:2px solid #d1d5db;">
            <td colspan="4" style="text-align:right;">TOTAL</td>
            <td style="text-align:right;">{{ $fmtN($rsaHgpTotalSaldo) }}</td>
            <td style="text-align:right;">{{ $fmtN($rsaHgpTotalFisikOnly) }}</td>
            <td style="text-align:right;background:#fffbeb;color:#92400e;">{{ $rsaHgpTotalWo > 0 ? $fmtN($rsaHgpTotalWo) : '—' }}</td>
            <td></td>
            <td style="text-align:right;color:{{ $rsaHgpTotalSelisih < 0 ? '#dc2626' : ($rsaHgpTotalSelisih > 0 ? '#d97706' : '#374151') }};">{{ $fmtSign($rsaHgpTotalSelisih) }}</td>
            <td></td>
            <td style="text-align:right;color:{{ $rsaHgpGrandJumlah < 0 ? '#dc2626' : ($rsaHgpGrandJumlah > 0 ? '#d97706' : '#374151') }};">{{ $rsaHgpGrandJumlah != 0 ? $fmtSign($rsaHgpGrandJumlah) : '—' }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
      </div>
      @else
        <p class="empty" style="padding:12px;">Tidak ada item.</p>
      @endif

      {{-- Rekap Selisih Part & AHM Oil's — lihat catatan yang sama di section 14. --}}
      @if($rsaHgpSelCount > 0)
      <div style="padding:4px 14px 14px;">
        <div class="group-title" style="font-size:12px;">REKAP SELISIH PART &amp; AHM OIL'S</div>
        <div class="group-title">AHM OIL'S</div>
        @include('akta.pdf.partials.rekap-selisih-table', ['items' => $rsaHgpOilItems])
        <div class="group-title">SPAREPART</div>
        @include('akta.pdf.partials.rekap-selisih-table', ['items' => $rsaHgpSparepartItems])
      </div>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     15. HGA (ACCESSORIES)
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['hga'] ?? true))
{{-- Cetak melintang HANYA kalau ada isinya. Tabel section ini punya 12 kolom
     (lebar minimum 800-900px) sehingga terpotong di A4 tegak — kolom paling
     kanan hilang sama sekali dari PDF, bukan sekadar tak terlihat. Tapi
     .section-landscape juga memaksa ganti halaman sebelum & sesudahnya, jadi
     kalau datanya kosong ia hanya akan memboroskan satu halaman melintang
     berisi tulisan "Belum ada data". --}}
@php
    $lebar = filled($hga?->items_json);
@endphp
<div class="section {{ $lebar ? 'section-landscape' : '' }}">
  <div class="section-title">15. HGA (Accessories)</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'hga'])
  <div class="section-body" style="padding:0;">
    @if(!$hga)
      <p class="empty" style="padding:12px;">Belum ada data.</p>
    @else
      @php
        $hgaItems = $hga->items_json ?? [];
        $hgaN2 = fn($v) => (float)($v ?? 0);
        $hgaTotalSaldo   = array_sum(array_map(fn($it) => $hgaN2($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0), $hgaItems));
        $hgaTotalPts     = array_sum(array_map(fn($it) => isset($it['saldoPts']) ? $hgaN2($it['saldoPts']) : $hgaN2($it['saldoAkhir'] ?? 0), $hgaItems));
        $hgaTotalScan    = array_sum(array_map(fn($it) => $hgaN2($it['fisik'] ?? 0), $hgaItems));
        $hgaTotalTtp     = array_sum(array_map(fn($it) => $hgaN2($it['fisikTtp'] ?? 0), $hgaItems));
        $hgaTotalFisik   = $hgaTotalScan + $hgaTotalTtp;
        $hgaTotalSelisih = array_sum(array_map(fn($it) => $hgaN2($it['selisih'] ?? 0), $hgaItems));
        $hgaTotalJumlah  = array_sum(array_map(fn($it) => $hgaN2($it['hargaHet'] ?? 0) * $hgaN2($it['selisih'] ?? 0), $hgaItems));
        $hgaSelCount     = count(array_filter($hgaItems, fn($it) => ($it['selisih'] ?? 0) != 0));
        $fmtHga  = fn($v) => number_format((float)$v, 0, ',', '.');
        $signHga = fn($v) => ($v > 0 ? '+' : '') . number_format((float)$v, 0, ',', '.');
      @endphp

      {{-- Summary cards --}}
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:15px;font-weight:800;color:#1e40af;">{{ count($hgaItems) }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Total Item</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:15px;font-weight:800;color:#059669;">{{ $fmtHga($hgaTotalScan) }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Total Fisik Scan</div>
        </div>
        <div style="background:#fff;border:1px solid #fef3c7;border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:15px;font-weight:800;color:#b45309;">{{ $fmtHga($hgaTotalTtp) }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Total Fisik TTP</div>
        </div>
        <div style="background:#fff;border:1px solid {{ $hgaSelCount > 0 ? '#fee2e2' : '#e5e7eb' }};border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:15px;font-weight:800;color:{{ $hgaSelCount > 0 ? '#dc2626' : '#059669' }};">{{ $hgaSelCount }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Item Selisih</div>
        </div>
        <div style="background:#fff;border:1px solid {{ $hgaTotalJumlah < 0 ? '#fee2e2' : '#e5e7eb' }};border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:13px;font-weight:800;color:{{ $hgaTotalJumlah < 0 ? '#dc2626' : ($hgaTotalJumlah > 0 ? '#d97706' : '#059669') }};">{{ $hgaTotalJumlah != 0 ? $signHga($hgaTotalJumlah) : '—' }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Nilai Selisih (Rp)</div>
        </div>
      </div>

      @if(count($hgaItems))
      <div class="tbl-scroll" style="overflow-x:auto;">
      <table style="font-size:9.5px;min-width:900px;">
        <thead>
          <tr>
            <th style="width:24px;">#</th>
            <th style="width:85px;">No. HGA</th>
            <th>Nama HGA</th>
            <th style="width:68px;text-align:center;">Tgl Periksa</th>
            <th style="width:46px;text-align:right;">Saldo Akhir</th>
            <th style="width:42px;text-align:right;color:#7c3aed;">Akhir PTS</th>
            <th style="width:38px;text-align:right;color:#059669;">Fisik Scan</th>
            <th style="width:38px;text-align:right;color:#b45309;background:#fffbeb;">Fisik TTP</th>
            <th style="width:36px;text-align:right;">Akhir</th>
            <th style="width:44px;text-align:right;">Selisih</th>
            <th style="width:70px;text-align:right;">Harga HET</th>
            <th style="width:80px;text-align:right;">Jumlah (Rp)</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @php $hgaGrandJumlah = 0; @endphp
          @foreach($hgaItems as $i => $it)
            @php
              $saldo    = $hgaN2($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0);
              $saldoPts = isset($it['saldoPts']) && $it['saldoPts'] !== null ? $hgaN2($it['saldoPts']) : null;
              $refSaldo = $saldoPts !== null ? $saldoPts : $saldo;
              $fisikScan= $hgaN2($it['fisik'] ?? 0);
              $fisikTtp = $hgaN2($it['fisikTtp'] ?? 0);
              $totalFisik = $fisikScan + $fisikTtp;
              $akhir    = $hgaN2($it['akhir'] ?? ($refSaldo - $totalFisik));
              $selisih  = $hgaN2($it['selisih'] ?? ($totalFisik - $refSaldo));
              $harga    = $hgaN2($it['hargaHet'] ?? 0);
              $jumlah   = $harga * $selisih;
              $hgaGrandJumlah += $jumlah;
              $selColor = $selisih < 0 ? '#dc2626' : ($selisih > 0 ? '#d97706' : '#374151');
              $jmlColor = $jumlah < 0 ? '#dc2626' : ($jumlah > 0 ? '#d97706' : '#374151');
              $isPtsOnly = !empty($it['_ptsOnly']);
              // Baris diberi warna latar kalau ada selisih, supaya di antara
              // ratusan item langsung terlihat mana yang perlu ditindaklanjuti
              // tanpa harus membaca kolom Selisih satu per satu.
              $rowBg = $selisih < 0 ? '#fef2f2' : ($selisih > 0 ? '#fffbeb' : '');
            @endphp
            <tr @if($rowBg) style="background:{{ $rowBg }};" @endif>
              <td>{{ (int)$i + 1 }}</td>
              <td style="font-size:8.5px;color:#6b7280;">{{ $it['noPart'] ?? '-' }}</td>
              <td style="font-weight:600;">{{ $it['sparepart'] ?? $it['nama'] ?? '-' }}</td>
              <td style="text-align:center;color:#6b7280;">{{ $it['tgl'] ?? '—' }}</td>
              <td style="text-align:right;">{{ $isPtsOnly ? '—' : $fmtHga($saldo) }}</td>
              <td style="text-align:right;color:#7c3aed;font-weight:{{ $saldoPts !== null ? '700' : '400' }};">{{ $saldoPts !== null ? $fmtHga($saldoPts) : '—' }}</td>
              <td style="text-align:right;font-weight:700;color:#059669;">{{ $fmtHga($fisikScan) }}</td>
              <td style="text-align:right;background:#fffbeb;font-weight:{{ $fisikTtp > 0 ? '700' : '400' }};color:#b45309;">{{ $fisikTtp > 0 ? $fmtHga($fisikTtp) : '—' }}</td>
              <td style="text-align:right;">{{ $fmtHga($akhir) }}</td>
              <td style="text-align:right;font-weight:700;color:{{ $selColor }};">{{ $selisih >= 0 ? '+' : '' }}{{ $fmtHga($selisih) }}</td>
              <td style="text-align:right;color:#6b7280;">{{ $harga > 0 ? $fmtHga($harga) : '—' }}</td>
              <td style="text-align:right;font-weight:700;color:{{ $jmlColor }};">{{ $jumlah != 0 ? ($jumlah >= 0 ? '+' : '') . $fmtHga($jumlah) : '—' }}</td>
              <td style="font-size:9px;">
                @if(!empty($it['keterangan']))<div>Scan: {{ $it['keterangan'] }}</div>@endif
                @if(!empty($it['keteranganTtp']))<div style="color:#b45309;">TTP: {{ $it['keteranganTtp'] }}</div>@endif
              </td>
            </tr>
          @endforeach
          {{-- Total row --}}
          <tr style="background:#f3f4f6;font-weight:700;border-top:2px solid #d1d5db;">
            <td colspan="4" style="text-align:right;">TOTAL</td>
            <td style="text-align:right;">{{ $fmtHga($hgaTotalSaldo) }}</td>
            <td style="text-align:right;color:#7c3aed;">{{ $fmtHga($hgaTotalPts) }}</td>
            <td style="text-align:right;color:#059669;">{{ $fmtHga($hgaTotalScan) }}</td>
            <td style="text-align:right;background:#fffbeb;color:#b45309;">{{ $hgaTotalTtp > 0 ? $fmtHga($hgaTotalTtp) : '—' }}</td>
            <td></td>
            <td style="text-align:right;color:{{ $hgaTotalSelisih < 0 ? '#dc2626' : ($hgaTotalSelisih > 0 ? '#d97706' : '#374151') }};">{{ $signHga($hgaTotalSelisih) }}</td>
            <td></td>
            <td style="text-align:right;color:{{ $hgaGrandJumlah < 0 ? '#dc2626' : ($hgaGrandJumlah > 0 ? '#d97706' : '#374151') }};">{{ $hgaGrandJumlah != 0 ? $signHga($hgaGrandJumlah) : '—' }}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
      </div>
      @else
        <p class="empty" style="padding:12px;">Tidak ada item.</p>
      @endif

      {{-- Rekap Selisih HGA: hanya item yang selisihnya tidak nol dari tabel
           di atas, dipisah supaya item yang perlu ditindaklanjuti langsung
           terlihat tanpa harus menyisir tabel lengkap yang bisa berisi
           ratusan item (mengikuti pola REKAP SELISIH PART & AHM OIL'S di
           section HGP/RSA HGP). --}}
      @if($hgaSelCount > 0)
      <div style="padding:4px 14px 14px;">
        <div class="group-title" style="font-size:12px;">REKAP SELISIH HGA</div>
        <div class="tbl-scroll" style="overflow-x:auto;">
        <table style="font-size:9.5px;min-width:700px;">
          <thead>
            <tr>
              <th style="width:24px;">#</th>
              <th style="width:85px;">No. HGA</th>
              <th>Nama HGA</th>
              <th style="width:44px;text-align:right;">Selisih</th>
              <th style="width:70px;text-align:right;">Harga HET</th>
              <th style="width:80px;text-align:right;">Jumlah (Rp)</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            @php $hgaRekapNo = 0; @endphp
            @foreach($hgaItems as $it)
              @php
                $rekSaldo    = $hgaN2($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0);
                $rekSaldoPts = isset($it['saldoPts']) && $it['saldoPts'] !== null ? $hgaN2($it['saldoPts']) : null;
                $rekRefSaldo = $rekSaldoPts !== null ? $rekSaldoPts : $rekSaldo;
                $rekTotalFisik = $hgaN2($it['fisik'] ?? 0) + $hgaN2($it['fisikTtp'] ?? 0);
                $rekSelisih  = $hgaN2($it['selisih'] ?? ($rekTotalFisik - $rekRefSaldo));
              @endphp
              @continue($rekSelisih == 0)
              @php
                $hgaRekapNo++;
                $rekHarga  = $hgaN2($it['hargaHet'] ?? 0);
                $rekJumlah = $rekHarga * $rekSelisih;
                $rekSelColor = $rekSelisih < 0 ? '#dc2626' : '#d97706';
                $rekJmlColor = $rekJumlah < 0 ? '#dc2626' : ($rekJumlah > 0 ? '#d97706' : '#374151');
                $rekRowBg    = $rekSelisih < 0 ? '#fef2f2' : '#fffbeb';
              @endphp
              <tr style="background:{{ $rekRowBg }};">
                <td>{{ $hgaRekapNo }}</td>
                <td style="font-size:8.5px;color:#6b7280;">{{ $it['noPart'] ?? '-' }}</td>
                <td style="font-weight:600;">{{ $it['sparepart'] ?? $it['nama'] ?? '-' }}</td>
                <td style="text-align:right;font-weight:700;color:{{ $rekSelColor }};">{{ $rekSelisih >= 0 ? '+' : '' }}{{ $fmtHga($rekSelisih) }}</td>
                <td style="text-align:right;color:#6b7280;">{{ $rekHarga > 0 ? $fmtHga($rekHarga) : '—' }}</td>
                <td style="text-align:right;font-weight:700;color:{{ $rekJmlColor }};">{{ $rekJumlah != 0 ? ($rekJumlah >= 0 ? '+' : '') . $fmtHga($rekJumlah) : '—' }}</td>
                <td style="font-size:9px;">
                  @if(!empty($it['keterangan']))<div>Scan: {{ $it['keterangan'] }}</div>@endif
                  @if(!empty($it['keteranganTtp']))<div style="color:#b45309;">TTP: {{ $it['keteranganTtp'] }}</div>@endif
                </td>
              </tr>
            @endforeach
            <tr style="background:#f3f4f6;font-weight:700;border-top:2px solid #d1d5db;">
              <td colspan="3" style="text-align:right;">TOTAL</td>
              <td style="text-align:right;color:{{ $hgaTotalSelisih < 0 ? '#dc2626' : ($hgaTotalSelisih > 0 ? '#d97706' : '#374151') }};">{{ $signHga($hgaTotalSelisih) }}</td>
              <td></td>
              <td style="text-align:right;color:{{ $hgaTotalJumlah < 0 ? '#dc2626' : ($hgaTotalJumlah > 0 ? '#d97706' : '#374151') }};">{{ $hgaTotalJumlah != 0 ? $signHga($hgaTotalJumlah) : '—' }}</td>
              <td></td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     16. SMH TARIKAN
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['smh-tarikan'] ?? true))
{{-- Cetak melintang HANYA kalau ada isinya. Tabel section ini punya 12 kolom
     (lebar minimum 800-900px) sehingga terpotong di A4 tegak — kolom paling
     kanan hilang sama sekali dari PDF, bukan sekadar tak terlihat. Tapi
     .section-landscape juga memaksa ganti halaman sebelum & sesudahnya, jadi
     kalau datanya kosong ia hanya akan memboroskan satu halaman melintang
     berisi tulisan "Belum ada data". --}}
@php
    $lebar = filled($smhTarikan?->items_json);
@endphp
<div class="section {{ $lebar ? 'section-landscape' : '' }}">
  <div class="section-title">16. SMH TARIKAN</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'smh-tarikan'])
  <div class="section-body" style="padding:0;">
    @if(!$smhTarikan)
      <p class="empty" style="padding:12px;">Belum ada data.</p>
    @else
      @php
        $tarItems   = $smhTarikan->items_json ?? [];
        $tarTotal   = count($tarItems);
        $tarLengkap = collect($tarItems)->filter(fn($it) => !empty($it['nama']) && !empty($it['noBast']))->count();
        $tarPiutang = array_sum(array_map(fn($it) => (float)($it['sisaPiutang'] ?? 0), $tarItems));
        $tarSudah   = collect($tarItems)->filter(fn($it) => !empty($it['sudahAjukan']))->count();
        $fmtRp = fn($v) => 'Rp '.number_format((float)$v, 0, ',', '.');
      @endphp

      {{-- Summary cards --}}
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:15px;font-weight:800;color:#1e40af;">{{ $tarTotal }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Total Unit</div>
        </div>
        <div style="background:#fff;border:1px solid #d1fae5;border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:15px;font-weight:800;color:#059669;">{{ $tarLengkap }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Data Lengkap</div>
        </div>
        <div style="background:#fff;border:1px solid {{ $tarSudah < $tarTotal ? '#fef3c7' : '#d1fae5' }};border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:15px;font-weight:800;color:{{ $tarSudah < $tarTotal ? '#b45309' : '#059669' }};">{{ $tarSudah }}/{{ $tarTotal }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Sudah Ajukan</div>
        </div>
        <div style="background:#fff;border:1px solid #fee2e2;border-radius:8px;padding:8px 10px;text-align:center;">
          <div style="font-size:13px;font-weight:800;color:#dc2626;">{{ $tarPiutang > 0 ? $fmtRp($tarPiutang) : '—' }}</div>
          <div style="font-size:9px;color:#6b7280;margin-top:2px;">Total Sisa Piutang</div>
        </div>
      </div>

      @if($tarTotal > 0)
      <div class="tbl-scroll" style="overflow-x:auto;">
      <table style="font-size:9.5px;min-width:900px;">
        <thead>
          <tr>
            <th style="width:24px;">#</th>
            <th>Nama Konsumen</th>
            <th style="width:80px;">No. BAST</th>
            <th style="width:60px;">Merk/Type</th>
            <th style="width:36px;text-align:center;">Tahun</th>
            <th style="width:90px;">No. Mesin</th>
            <th style="width:90px;">No. Rangka</th>
            <th style="width:55px;">No. Polisi</th>
            <th style="width:70px;">No. Kontrak</th>
            <th style="width:70px;text-align:right;">Sisa Piutang</th>
            <th style="width:80px;">Perlengkapan</th>
            <th style="width:60px;text-align:center;">Tgl Pengajuan</th>
            <th>Kondisi SMH</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tarItems as $i => $it)
            @php
              $sudah    = !empty($it['sudahAjukan']);
              $piutang  = (float)($it['sisaPiutang'] ?? 0);
              $isLengkap = !empty($it['nama']) && !empty($it['noBast']);
            @endphp
            <tr style="{{ !$isLengkap ? 'background:#fffbeb;' : '' }}">
              <td>{{ (int)$i + 1 }}</td>
              <td style="font-weight:700;">{{ $it['nama'] ?? '—' }}</td>
              <td style="font-size:8.5px;color:#374151;">{{ $it['noBast'] ?? '—' }}</td>
              <td>{{ $it['merk'] ?? '—' }}</td>
              <td style="text-align:center;">{{ $it['tahun'] ?? '—' }}</td>
              <td style="font-family:monospace;font-size:8.5px;">{{ $it['noMesin'] ?? '—' }}</td>
              <td style="font-family:monospace;font-size:8.5px;">{{ $it['noRangka'] ?? '—' }}</td>
              <td style="font-weight:600;">{{ $it['nopol'] ?? '—' }}</td>
              <td style="font-size:8.5px;color:#6b7280;">{{ $it['noKontrak'] ?? '—' }}</td>
              <td style="text-align:right;font-weight:700;color:{{ $piutang > 0 ? '#dc2626' : '#374151' }};">
                {{ $piutang > 0 ? $fmtRp($piutang) : '—' }}
              </td>
              <td style="font-size:8.5px;color:#374151;">{{ $it['perlengkapan'] ?? '—' }}</td>
              <td style="text-align:center;">
                @if($sudah)
                  <span style="color:#059669;font-weight:700;font-size:8.5px;">{{ $it['tglPengajuan'] ?? '✔' }}</span>
                @else
                  <span style="color:#d97706;font-size:8.5px;">Belum Ajukan</span>
                @endif
              </td>
              <td style="font-size:8.5px;color:#6b7280;">{{ $it['kondisi'] ?? '—' }}</td>
            </tr>
          @endforeach
          {{-- Total row --}}
          @if($tarPiutang > 0)
          <tr style="background:#f3f4f6;font-weight:700;border-top:2px solid #d1d5db;">
            <td colspan="9" style="text-align:right;">TOTAL SISA PIUTANG</td>
            <td style="text-align:right;color:#dc2626;">{{ $fmtRp($tarPiutang) }}</td>
            <td colspan="3"></td>
          </tr>
          @endif
        </tbody>
      </table>
      </div>
      @else
        <p class="empty" style="padding:12px;">Tidak ada data unit.</p>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     17. LAMPIRAN
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['lampiran'] ?? true))
<div class="section">
  <div class="section-title">17. LAMPIRAN AUDIT</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'lampiran'])
  <div class="section-body" style="padding:0;">
    @if(!$lampiran)
      <p class="empty" style="padding:12px;">Belum ada lampiran.</p>
    @else
      @php
        $files    = $lampiran->files_json ?? [];
        $totFiles = count($files);
        $totSize  = array_sum(array_map(fn($f) => (int)($f['size'] ?? 0), $files));
        $extGroups = collect($files)->groupBy(fn($f) => strtoupper($f['ext'] ?? 'OTHER'));
        $fmtSize  = function($bytes) {
            if ($bytes >= 1048576) return number_format($bytes/1048576, 1).' MB';
            if ($bytes >= 1024)    return number_format($bytes/1024, 1).' KB';
            return $bytes.' B';
        };
        $extColor = [
            'PDF'  => ['bg' => '#fee2e2', 'text' => '#991b1b', 'border' => '#fca5a5'],
            'JPG'  => ['bg' => '#dbeafe', 'text' => '#1e40af', 'border' => '#93c5fd'],
            'JPEG' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'border' => '#93c5fd'],
            'PNG'  => ['bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7'],
            'DOC'  => ['bg' => '#ede9fe', 'text' => '#5b21b6', 'border' => '#c4b5fd'],
            'DOCX' => ['bg' => '#ede9fe', 'text' => '#5b21b6', 'border' => '#c4b5fd'],
            'XLS'  => ['bg' => '#dcfce7', 'text' => '#166534', 'border' => '#86efac'],
            'XLSX' => ['bg' => '#dcfce7', 'text' => '#166534', 'border' => '#86efac'],
        ];
        $getExtStyle = fn($ext) => $extColor[strtoupper($ext)] ?? ['bg' => '#f3f4f6', 'text' => '#374151', 'border' => '#d1d5db'];
      @endphp

      {{-- Summary cards --}}
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;text-align:center;">
          <div style="font-size:28px;font-weight:800;color:#1e40af;">{{ $totFiles }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total File Lampiran</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;text-align:center;">
          <div style="font-size:14px;font-weight:800;color:#059669;">{{ $fmtSize($totSize) }}</div>
          <div style="font-size:9.5px;color:#6b7280;margin-top:2px;">Total Ukuran</div>
        </div>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:center;">
          @foreach($extGroups as $ext => $group)
            @php $es = $getExtStyle($ext); @endphp
            <span style="background:{{ $es['bg'] }};color:{{ $es['text'] }};border:1px solid {{ $es['border'] }};font-size:9px;font-weight:700;padding:3px 8px;border-radius:4px;">
              {{ $ext }} ({{ $group->count() }})
            </span>
          @endforeach
          <div style="width:100%;font-size:9px;color:#6b7280;text-align:center;margin-top:2px;">Jenis File</div>
        </div>
      </div>

      @if($totFiles > 0)
      {{-- Embedded file contents --}}
      <div style="padding:14px;">
        @foreach($lampiranEmbeds as $i => $embed)
          @php $f = $embed['file']; $ext = strtoupper($f['ext'] ?? 'FILE'); $es = $getExtStyle($ext); @endphp
          {{-- page-break-inside:avoid TIDAK dipasang di sini: lampiran PDF bisa
               berisi puluhan halaman (jauh lebih tinggi dari 1 halaman kertas),
               sama seperti section Kas dkk di atas yang juga sengaja tidak diberi
               "avoid" pada isinya — kalau dipaksa, kondisinya mustahil dipenuhi
               browser (isi selalu lebih tinggi dari ruang 1 halaman) dan
               pagination bisa memberi hasil yang tidak konsisten.

               page-break-after:avoid pada baris header JUGA TIDAK dipasang
               (sempat dicoba, lalu ditemukan jadi masalah baru): isi PDF
               dirender sebagai <canvas> — satu gambar utuh yang tidak bisa
               dipecah antar halaman kertas. Kalau header "dipaksa" tidak boleh
               diikuti page break, sementara <canvas> di bawahnya (gambar utuh,
               hampir pasti lebih tinggi dari sisa ruang halaman) juga tidak
               bisa dipecah, browser mendorong SELURUH header+canvas ke halaman
               berikutnya — menyisakan halaman sebelumnya kosong melompong.
               Tanpa "avoid" ini, browser bebas menaruh header di ujung bawah
               halaman berjalan (baris tunggal, wajar) dan mulai gambar
               <canvas> di halaman berikutnya, tanpa membuang seluruh sisa
               halaman jadi kosong. --}}
          <div style="margin-bottom:20px;">
            {{-- File header bar --}}
            <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f3f4f6;border:1px solid #e5e7eb;border-bottom:none;border-radius:8px 8px 0 0;">
              <span style="background:{{ $es['bg'] }};color:{{ $es['text'] }};border:1px solid {{ $es['border'] }};font-size:9px;font-weight:700;padding:2px 7px;border-radius:4px;">{{ $ext }}</span>
              <span style="font-size:10px;font-weight:700;color:#111827;flex:1;">{{ (int)$i+1 }}. {{ $f['name'] ?? 'Unnamed' }}</span>
              <span style="font-size:8.5px;color:#6b7280;">{{ $fmtSize((int)($f['size'] ?? 0)) }}</span>
              @if(!empty($f['uploadedAt']))<span style="font-size:8.5px;color:#9ca3af;">{{ $f['uploadedAt'] }}</span>@endif
            </div>
            {{-- Content --}}
            <div style="border:1px solid #e5e7eb;border-radius:0 0 8px 8px;background:#fff;overflow:hidden;">
              @if($embed['type'] === 'image' && $embed['data'])
                <img src="{{ $embed['data'] }}" alt="{{ $f['name'] ?? '' }}"
                     style="display:block;width:100%;max-width:100%;height:auto;object-fit:contain;">
              @elseif($embed['type'] === 'pdf' && $embed['data'])
                {{-- Bukan <embed>: browser membatasi jumlah plugin PDF viewer yang
                     bisa aktif render bersamaan dalam satu halaman, jadi begitu
                     lampiran PDF lebih dari beberapa buah, entry berikutnya
                     tampil kosong tanpa error. Isinya dirender lewat pdf.js ke
                     <canvas> (lihat akta-report-pdf-lampiran.js) — bukan plugin
                     native — supaya tidak kena batas itu dan tetap tampil
                     sebagai gambar halaman, bukan cuma tautan file. --}}
                <div class="lampiran-pdf-pages" data-pdf-src="{{ $embed['data'] }}" data-pdf-name="{{ $f['name'] ?? 'file' }}">
                  <div style="padding:24px 20px;text-align:center;background:#f9fafb;font-size:10px;color:#6b7280;">Memuat isi PDF…</div>
                </div>
              @else
                <div style="padding:20px;text-align:center;background:#f9fafb;">
                  <div style="font-size:32px;margin-bottom:8px;">📎</div>
                  <div style="font-size:11px;color:#374151;font-weight:600;">{{ $f['name'] ?? 'file' }}</div>
                  <div style="font-size:9px;color:#9ca3af;margin-top:4px;">Format {{ $ext }} tidak dapat ditampilkan inline.</div>
                </div>
              @endif
            </div>
          </div>
        @endforeach
      </div>
      @else
        <p class="empty" style="padding:12px;">Tidak ada file lampiran.</p>
      @endif
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     18. MUTASI PEMBELIAN
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['mutasi-pembelian'] ?? true))
<div class="section">
  <div class="section-title">18. MUTASI PEMBELIAN</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'mutasi-pembelian'])
  <div class="section-body">
    @if(!$mutasiPembelian || empty($mutasiPembelian->items_json))
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $mpItems = $mutasiPembelian->items_json ?? [];
        $mpMatch = collect($mpItems)->where('matched', true)->count();
      @endphp

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val">{{ count($mpItems) }}</div>
          <div class="cs-lbl">Total Baris Gudang</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="color:#4ade80;">{{ $mpMatch }}</div>
          <div class="cs-lbl">Sudah Diterima</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="color:#f87171;">{{ count($mpItems) - $mpMatch }}</div>
          <div class="cs-lbl">Belum Terima</div>
        </div>
      </div>

      <div class="tbl-scroll" style="overflow-x:auto;">
      <table style="font-size:9.5px;">
        <thead>
          <tr>
            <th>#</th>
            <th>Kode Part</th>
            <th>Nama Part</th>
            <th style="text-align:right;">Qty</th>
            <th>Nomor Faktur</th>
            <th>Tanggal Faktur</th>
            <th>Lokasi</th>
            <th>Kode</th>
            <th>Unit Usaha</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($mpItems as $i => $mp)
          <tr @if(!($mp['matched'] ?? false)) style="background:#7f1d1d1a;" @endif>
            <td>{{ (int)$i+1 }}</td>
            <td style="font-family:monospace;">{{ $mp['kodePart'] ?? '-' }}</td>
            <td>{{ $mp['namaPart'] ?? '-' }}</td>
            <td style="text-align:right;">{{ ($mp['qty'] ?? 0) ? number_format($mp['qty'],0,',','.') : '-' }}</td>
            <td style="font-family:monospace;">{{ $mp['nomorFaktur'] ?? '-' }}</td>
            <td>{{ $mp['tanggalFaktur'] ?? '-' }}</td>
            <td>{{ $mp['lokasi'] ?: '-' }}</td>
            <td>{{ $mp['kode'] ?? '-' }}</td>
            <td>{{ $mp['unitUsaha'] ?? '-' }}</td>
            <td style="color:{{ ($mp['matched'] ?? false) ? '#4ade80' : '#f87171' }};font-weight:600;">{{ $mp['keterangan'] ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      </div>
    @endif
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     19. TTP CSC
     ═══════════════════════════════════════════════ --}}
@if(($visibleTabs['ttp-csc'] ?? true))
<div class="section">
  <div class="section-title">19. TTP CSC</div>
  @include('akta.pdf.partials.auditor-line', ['tool' => 'ttp-csc'])
  <div class="section-body">
    @if(!$ttpCsc || empty($ttpCsc->items_json))
      <p class="empty">Belum ada data.</p>
    @else
      @php
        $tcItems  = $ttpCsc->items_json ?? [];
        $tcSesuai = collect($tcItems)->where('keterangan', 'Data Sesuai')->count();
      @endphp

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val">{{ count($tcItems) }}</div>
          <div class="cs-lbl">Total TTP</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="color:#4ade80;">{{ $tcSesuai }}</div>
          <div class="cs-lbl">Data Sesuai</div>
        </div>
        <div class="card-stat" style="flex:1;min-width:100px;">
          <div class="cs-val" style="color:#f87171;">{{ count($tcItems) - $tcSesuai }}</div>
          <div class="cs-lbl">Selisih / Belum Dicek</div>
        </div>
      </div>

      <table style="font-size:9.5px;">
        <thead>
          <tr>
            <th>No</th>
            <th>TTP</th>
            <th>Tanggal</th>
            <th>Nama</th>
            <th style="text-align:right;">Nilai</th>
            <th>Tanggal Portal</th>
            <th style="text-align:right;">Selisih Tgl</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          @foreach($tcItems as $tc)
          <tr>
            <td>{{ $tc['no'] ?? '-' }}</td>
            <td style="font-family:monospace;">{{ $tc['ttp'] ?? '-' }}</td>
            <td>{{ $tc['tanggal'] ?? '-' }}</td>
            <td>{{ $tc['nama'] ?? '-' }}</td>
            <td style="text-align:right;">{{ ($tc['nilai'] ?? 0) ? 'Rp '.number_format($tc['nilai'],0,',','.') : '-' }}</td>
            <td>{{ $tc['tanggalPortal'] ?: '-' }}</td>
            <td style="text-align:right;">{{ $tc['tanggalPortal'] ? ($tc['selisihTgl'] ?? 0) : '-' }}</td>
            <td style="color:{{ $tc['keterangan'] === 'Data Sesuai' ? '#4ade80' : (($tc['keterangan'] ?? '') !== '' ? '#f87171' : '#6b7280') }};font-weight:600;">{{ $tc['keterangan'] ?: '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endif

<div style="text-align:center;color:#9ca3af;font-size:8px;margin-top:16px;border-top:1px solid #e5e7eb;padding-top:8px;">
  Laporan ini digenerate secara otomatis oleh sistem SIMPAS-IAT pada {{ now()->format('d/m/Y H:i:s') }}.
</div>

</div>{{-- end page-wrap --}}
</body>
</html>
