<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Audit – {{ $plan->no_spt ?? '-' }}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #111; background: #fff; }

  /* ── Cover / header ── */
  .cover { text-align: center; padding: 32px 24px 24px; border-bottom: 3px solid #1e40af; margin-bottom: 20px; }
  .cover h1 { font-size: 18px; font-weight: 700; color: #1e3a8a; letter-spacing: .5px; }
  .cover h2 { font-size: 13px; color: #374151; margin-top: 4px; }
  .cover .meta { display: flex; justify-content: center; flex-wrap: wrap; gap: 16px; margin-top: 14px; font-size: 10px; color: #6b7280; }
  .cover .meta span strong { color: #1f2937; }

  /* ── Section header ── */
  .section { page-break-inside: avoid; margin-bottom: 20px; }
  .section-title {
    background: #1e40af; color: #fff;
    padding: 5px 10px; font-size: 11px; font-weight: 700; letter-spacing: .4px;
    border-radius: 4px 4px 0 0;
  }
  .section-body { border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 4px 4px; padding: 10px; }

  /* ── Tables ── */
  table { width: 100%; border-collapse: collapse; font-size: 10px; }
  th { background: #f3f4f6; text-align: left; padding: 5px 7px; border: 1px solid #d1d5db; font-weight: 700; color: #374151; }
  td { padding: 4px 7px; border: 1px solid #e5e7eb; vertical-align: top; }
  tr:nth-child(even) td { background: #f9fafb; }

  /* ── Key-value pairs ── */
  .kv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
  .kv { display: flex; gap: 6px; }
  .kv-label { font-weight: 700; min-width: 110px; color: #374151; }
  .kv-val { color: #111; }

  /* ── Status badges ── */
  .badge { display: inline-block; padding: 1px 6px; border-radius: 99px; font-size: 9px; font-weight: 700; }
  .badge-open { background: #dbeafe; color: #1d4ed8; }
  .badge-progress { background: #fef3c7; color: #92400e; }
  .badge-closed, .badge-selesai, .badge-done { background: #d1fae5; color: #065f46; }

  /* ── Empty state ── */
  .empty { color: #9ca3af; font-style: italic; padding: 8px 0; }

  /* ── Print controls ── */
  .print-bar { position: fixed; top: 0; left: 0; right: 0; z-index: 999;
    background: #1e40af; color: #fff; padding: 8px 16px;
    display: flex; align-items: center; justify-content: space-between; }
  .print-bar button { background: #fff; color: #1e3a8a; border: none;
    font-size: 12px; font-weight: 700; padding: 5px 16px; border-radius: 6px; cursor: pointer; }
  .print-bar .close-btn { background: transparent; color: #fff; border: 1px solid #fff; margin-left: 8px; }
  .print-spacer { height: 44px; }

  @media print {
    .print-bar, .print-spacer { display: none !important; }
    body { font-size: 10px; }
    .section { page-break-inside: avoid; }
    @page { margin: 16mm 14mm; }
  }
</style>
</head>
<body>

<div class="print-bar">
  <span style="font-weight:700;font-size:13px;">📄 Laporan Audit – {{ $plan->no_spt ?? '-' }}</span>
  <div>
    <button onclick="window.print()">🖨️ Cetak / Save PDF</button>
    <button class="close-btn" onclick="window.close()">✕ Tutup</button>
  </div>
</div>
<div class="print-spacer"></div>

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

{{-- ═══════════════════════════════════════════════
     1. PEMERIKSAAN KAS
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">1. PEMERIKSAAN KAS</div>
  <div class="section-body">
    @if($kas->isEmpty())
      <p class="empty">Belum ada data.</p>
    @else
      @foreach($kas as $k)
      @php
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

      @endforeach
    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     2. ANALISA PLAFON SMH
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">2. ANALISA PLAFON SMH</div>
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
                  <th style="text-align:right;padding:2px 6px;border:1px solid #e2e8f0;">Harga</th>
                </tr>
              </thead>
              <tbody>
                @foreach($gu['detail'] as $det)
                <tr style="{{ $det['harga'] === null ? 'color:#d97706;' : '' }}">
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;font-family:monospace;">{{ $det['noRangka'] ?? '-' }}</td>
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;font-family:monospace;">{{ $det['noMesin'] ?? '-' }}</td>
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;">{{ $det['kodeModel'] ?? '-' }}</td>
                  <td style="padding:2px 6px;border:1px solid #e2e8f0;">{{ $det['namaSmh'] ?? '— harga tidak ditemukan —' }}</td>
                  <td style="text-align:right;padding:2px 6px;border:1px solid #e2e8f0;">{{ $det['harga'] !== null ? $fmt2($det['harga']) : '-' }}</td>
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

{{-- ═══════════════════════════════════════════════
     3. PEMERIKSAAN SMH & PERLENGKAPAN
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">3. PEMERIKSAAN SMH (Stock Motor Honda) &amp; PERLENGKAPAN</div>
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
            $rowBg  = ($item->status_fisik === 'tidak') ? 'background:#fff1f2;' : '';
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
            <td style="font-weight:700;color:{{ ($item->status_fisik === 'ada') ? '#059669' : (($item->status_fisik === 'tidak') ? '#dc2626' : '#374151') }}">
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
        $totalSaldo = $perlengkapan->sum('saldo');
        $totalFisik = $perlengkapan->sum('fisik');
        $totalSelisih = $perlengkapan->sum('selisih');
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
          @php $sel = (float)($p->selisih ?? 0); @endphp
          <tr>
            <td>{{ (int)$i + 1 }}</td>
            <td style="font-weight:600">{{ $p->jenis_perlengkapan ?? '-' }}</td>
            <td>{{ $p->tgl_periksa ? \Carbon\Carbon::parse($p->tgl_periksa)->format('d/m/Y') : '-' }}</td>
            <td>{{ $p->nama_pemeriksa ?? '-' }}</td>
            <td>{{ $p->nama_unit_usaha ?? '-' }}</td>
            <td style="text-align:right">{{ number_format((float)($p->saldo ?? 0), 0, ',', '.') }}</td>
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
      // Bangun map dari SMH fisik: nama → {smhSaldo (total diperiksa), smhFisik (ada)}
      $smhPlMap = [];
      foreach($smh as $s) {
          foreach(($s->items ?? collect()) as $item) {
              foreach(($item->perlengkapan_json ?? []) as $pl) {
                  $nm = trim($pl['nama'] ?? '');
                  if($nm === '') continue;
                  if(!isset($smhPlMap[$nm])) $smhPlMap[$nm] = ['smhSaldo'=>0, 'smhFisik'=>0];
                  $smhPlMap[$nm]['smhSaldo']++;
                  if($pl['ada'] ?? false) $smhPlMap[$nm]['smhFisik']++;
              }
          }
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
          $luarD = $luarPlMap[$jns] ?? ['luarSaldo'=>0,'luarFisik'=>0,'luarSelisih'=>0,'penjelasan'=>[]];
          $smhSel  = $smhD['smhFisik'] - $smhD['smhSaldo'];
          $luarSel = $luarD['luarSelisih'];
          $totalSel = $smhSel + $luarSel;
          $grandSmhSaldo  += $smhD['smhSaldo'];
          $grandSmhFisik  += $smhD['smhFisik'];
          $grandSmhSel    += $smhSel;
          $grandLuarSaldo += $luarD['luarSaldo'];
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
          <td style="text-align:right">{{ $luarD['luarSaldo'] ? number_format($luarD['luarSaldo'],0,',','.') : '-' }}</td>
          <td style="text-align:right">{{ $luarD['luarFisik'] ? number_format($luarD['luarFisik'],0,',','.') : '-' }}</td>
          <td style="text-align:right;font-weight:700;color:{{ $luarSel != 0 ? '#dc2626' : '#059669' }}">
            {{ $luarD['luarSaldo'] ? number_format($luarSel,0,',','.') : '-' }}
          </td>
          {{-- Total Selisih --}}
          <td style="text-align:center;font-weight:700;background:#fef9c3;color:{{ $totalSel < 0 ? '#dc2626' : ($totalSel > 0 ? '#d97706' : '#059669') }}">
            @if($smhD['smhSaldo'] || $luarD['luarSaldo'])
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

  </div>
</div>

{{-- ═══════════════════════════════════════════════
     4. PEMERIKSAAN BANK
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">4. PEMERIKSAAN BANK</div>
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
            <th style="text-align:left;padding:4px 8px;border:1px solid #c7d2fe;">Auditee</th>
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
            <td style="padding:4px 8px;border:1px solid #e5e7eb;">{{ $b->auditee ?? '-' }}</td>
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
          <div class="kv"><span class="kv-label">Auditee:</span><span class="kv-val">{{ $b->auditee ?? '-' }}</span></div>
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

{{-- ═══════════════════════════════════════════════
     5. PEMERIKSAAN MATERAI
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">5. PEMERIKSAAN MATERAI</div>
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

{{-- ═══════════════════════════════════════════════
     6. ONHAND BPKB
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">6. ONHAND BPKB</div>
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
        @foreach($bpkbOnhand->take(200) as $i => $b)
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
        @if($bpkbOnhand->count() > 200)
        <tr><td colspan="10" style="font-style:italic;color:#6b7280;text-align:center">... dan {{ $bpkbOnhand->count()-200 }} item lainnya.</td></tr>
        @endif
      </tbody>
    </table>

    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     7. BPKB INPROSES
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">7. BPKB INPROSES</div>
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

{{-- ═══════════════════════════════════════════════
     8. KWITANSI GANTUNG
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">8. KWITANSI GANTUNG</div>
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

{{-- ═══════════════════════════════════════════════
     9. PIUTANG REGULER
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">9. PIUTANG REGULER</div>
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
      <div style="overflow-x:auto;">
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

{{-- ═══════════════════════════════════════════════
     10. PIUTANG CDN
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">10. PIUTANG CDN</div>
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
      <div style="overflow-x:auto;">
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

{{-- ═══════════════════════════════════════════════
     11. TTP GANTUNG
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">11. TTP GANTUNG</div>
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

{{-- ═══════════════════════════════════════════════
     12. CEK FISIK
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">12. CEK FISIK (Blangko Cek Fisik &amp; STUJ)</div>
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
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;">{{ $cfSa['cf'] ?? 0 }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Awal</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;color:#60a5fa;">{{ $cfAkhirCf }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Akhir</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;color:{{ $selColor($cfSelCf) }};">{{ $cfSelCf }}</div><div style="font-size:9px;color:#94a3b8;">Selisih</div></div>
          </div>
        </div>
        {{-- STUJ --}}
        <div style="flex:1;min-width:150px;background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:10px 14px;">
          <div style="font-size:10px;font-weight:600;color:#a78bfa;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">STUJ</div>
          <div style="display:flex;gap:12px;">
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;">{{ $cfSa['stuj'] ?? 0 }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Awal</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;color:#a78bfa;">{{ $cfAkhirStuj }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Akhir</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;color:{{ $selColor($cfSelStuj) }};">{{ $cfSelStuj }}</div><div style="font-size:9px;color:#94a3b8;">Selisih</div></div>
          </div>
        </div>
        {{-- F.STNK --}}
        <div style="flex:1;min-width:150px;background:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:10px 14px;">
          <div style="font-size:10px;font-weight:600;color:#34d399;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">F. STNK</div>
          <div style="display:flex;gap:12px;">
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;">{{ $cfSa['fstnk'] ?? 0 }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Awal</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;color:#34d399;">{{ $cfAkhirFstnk }}</div><div style="font-size:9px;color:#94a3b8;">Saldo Akhir</div></div>
            <div style="text-align:center;flex:1;"><div style="font-size:16px;font-weight:700;color:{{ $selColor($cfSelFstnk) }};">{{ $cfSelFstnk }}</div><div style="font-size:9px;color:#94a3b8;">Selisih</div></div>
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

{{-- ═══════════════════════════════════════════════
     13. MT (Mechanic Truster Tools)
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">13. MT (Mechanic Truster Tools)</div>
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
                <div style="font-size:14px;font-weight:700;color:#111827;line-height:1.2;">{{ $mekanik }}</div>
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
                    <div style="font-size:20px;font-weight:800;color:{{ $mtKatText[$kat] }};line-height:1;">{{ $cnt }}</div>
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

            @endforeach
          </div>
        @endforeach
      @endif
    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     14. HGP & AHM OILS
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">14. HGP &amp; AHM OILS</div>
  <div class="section-body">
    @if(!$hgp)
      <p class="empty">Belum ada data.</p>
    @else
      @php $hgpItems = $hgp->items_json ?? []; @endphp
      @if(count($hgpItems))
      <table>
        <thead><tr><th>#</th><th>Nama / Kode</th><th>Qty</th><th>Satuan</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach(array_slice($hgpItems, 0, 100) as $i => $item)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ is_array($item) ? ($item['nama'] ?? $item['kode'] ?? $item['name'] ?? '') : $item }}</td>
            <td style="text-align:right">{{ is_array($item) ? ($item['qty'] ?? $item['jumlah'] ?? '') : '' }}</td>
            <td>{{ is_array($item) ? ($item['satuan'] ?? '') : '' }}</td>
            <td>{{ is_array($item) ? ($item['keterangan'] ?? $item['ket'] ?? '') : '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
        <p class="empty">Tidak ada item.</p>
      @endif
    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     15. HGA (ACCESSORIES)
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">15. HGA (Accessories)</div>
  <div class="section-body">
    @if(!$hga)
      <p class="empty">Belum ada data.</p>
    @else
      @php $hgaItems = $hga->items_json ?? []; @endphp
      @if(count($hgaItems))
      <table>
        <thead><tr><th>#</th><th>Nama / Kode</th><th>Qty</th><th>Satuan</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach(array_slice($hgaItems, 0, 100) as $i => $item)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ is_array($item) ? ($item['nama'] ?? $item['kode'] ?? $item['name'] ?? '') : $item }}</td>
            <td style="text-align:right">{{ is_array($item) ? ($item['qty'] ?? $item['jumlah'] ?? '') : '' }}</td>
            <td>{{ is_array($item) ? ($item['satuan'] ?? '') : '' }}</td>
            <td>{{ is_array($item) ? ($item['keterangan'] ?? $item['ket'] ?? '') : '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
        <p class="empty">Tidak ada item.</p>
      @endif
    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     16. SMH TARIKAN
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">16. SMH TARIKAN</div>
  <div class="section-body">
    @if(!$smhTarikan)
      <p class="empty">Belum ada data.</p>
    @else
      @php $tarItems = $smhTarikan->items_json ?? []; @endphp
      @if(count($tarItems))
      <table>
        <thead><tr><th>#</th><th>No Rangka</th><th>No Mesin</th><th>Jenis</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach(array_slice($tarItems, 0, 100) as $i => $t)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ is_array($t) ? ($t['no_rangka'] ?? '') : $t }}</td>
            <td>{{ is_array($t) ? ($t['no_mesin'] ?? '') : '' }}</td>
            <td>{{ is_array($t) ? ($t['jenis'] ?? '') : '' }}</td>
            <td>{{ is_array($t) ? ($t['keterangan'] ?? $t['ket'] ?? '') : '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
        <p class="empty">Tidak ada item.</p>
      @endif
    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     17. LAMPIRAN
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">17. LAMPIRAN</div>
  <div class="section-body">
    @if(!$lampiran)
      <p class="empty">Belum ada lampiran.</p>
    @else
      @php $files = $lampiran->files_json ?? []; @endphp
      @if(count($files))
      <table>
        <thead><tr><th>#</th><th>Nama File</th><th>Tipe</th><th>Ukuran</th><th>Diupload</th></tr></thead>
        <tbody>
          @foreach($files as $i => $f)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $f['name'] ?? '-' }}</td>
            <td>{{ strtoupper($f['ext'] ?? '-') }}</td>
            <td>{{ isset($f['size']) ? number_format($f['size']/1024, 1).' KB' : '-' }}</td>
            <td>{{ $f['uploadedAt'] ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
        <p class="empty">Tidak ada file lampiran.</p>
      @endif
    @endif
  </div>
</div>

<div style="text-align:center;color:#9ca3af;font-size:9px;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:10px;">
  Laporan ini digenerate secara otomatis oleh sistem AKTA IAT pada {{ now()->format('d/m/Y H:i:s') }}.
</div>

</body>
</html>
