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
      <div class="kv-grid" style="margin-bottom:8px;">
        <div class="kv"><span class="kv-label">Tgl Awal:</span><span class="kv-val">{{ $b->tgl_awal ? \Carbon\Carbon::parse($b->tgl_awal)->format('d/m/Y') : '-' }}</span></div>
        <div class="kv"><span class="kv-label">Fisik BPKB:</span><span class="kv-val">{{ $b->fisik_bpkb_hitung ?? '-' }}</span></div>
        <div class="kv"><span class="kv-label">Fisik Inproses:</span><span class="kv-val">{{ $b->fisik_inproses_hitung ?? '-' }}</span></div>
        <div class="kv"><span class="kv-label">Onhand BPKB:</span><span class="kv-val">{{ $b->onhand_bpkb ?? '-' }}</span></div>
        <div class="kv"><span class="kv-label">Ket. Selisih:</span><span class="kv-val">{{ $b->keterangan_selisih ?? '-' }}</span></div>
      </div>
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
      @php $kwItems = $kwitansi->kwitansi_json ?? []; @endphp
      <div class="kv" style="margin-bottom:8px;">
        <span class="kv-label">Tgl Audit:</span>
        <span class="kv-val">{{ $kwitansi->tgl_audit ? \Carbon\Carbon::parse($kwitansi->tgl_audit)->format('d/m/Y') : '-' }}</span>
      </div>
      @if(count($kwItems))
      <table>
        <thead><tr><th>#</th><th>Nama</th><th>No Kwitansi</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach($kwItems as $i => $kw)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $kw['nama'] ?? $kw['name'] ?? '-' }}</td>
            <td>{{ $kw['no_kwitansi'] ?? $kw['no'] ?? '-' }}</td>
            <td style="text-align:right">{{ isset($kw['jumlah']) ? number_format($kw['jumlah'], 0, ',', '.') : '-' }}</td>
            <td>{{ $kw['keterangan'] ?? $kw['ket'] ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
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
      @php $prItems = $piutangReguler->piutang_json ?? []; @endphp
      @if(count($prItems))
      <table>
        <thead><tr><th>#</th><th>Nama</th><th>No Kontrak</th><th>Tunggakan</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach(array_slice($prItems, 0, 100) as $i => $pr)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $pr['nama'] ?? $pr['name'] ?? '-' }}</td>
            <td>{{ $pr['no_kontrak'] ?? $pr['no'] ?? '-' }}</td>
            <td style="text-align:right">{{ isset($pr['tunggakan']) ? number_format($pr['tunggakan'], 0, ',', '.') : '-' }}</td>
            <td>{{ $pr['keterangan'] ?? '-' }}</td>
          </tr>
          @endforeach
          @if(count($prItems) > 100)
          <tr><td colspan="5" style="font-style:italic;color:#6b7280">... dan {{ count($prItems)-100 }} item lainnya.</td></tr>
          @endif
        </tbody>
      </table>
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
      @php $cdnItems = $piutangCdn->piutang_json ?? []; @endphp
      @if(count($cdnItems))
      <table>
        <thead><tr><th>#</th><th>Nama</th><th>No Kontrak</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach(array_slice($cdnItems, 0, 100) as $i => $cdn)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $cdn['nama'] ?? $cdn['name'] ?? '-' }}</td>
            <td>{{ $cdn['no_kontrak'] ?? $cdn['no'] ?? '-' }}</td>
            <td style="text-align:right">{{ isset($cdn['jumlah']) ? number_format($cdn['jumlah'], 0, ',', '.') : (isset($cdn['tunggakan']) ? number_format($cdn['tunggakan'], 0, ',', '.') : '-') }}</td>
            <td>{{ $cdn['keterangan'] ?? '-' }}</td>
          </tr>
          @endforeach
          @if(count($cdnItems) > 100)
          <tr><td colspan="5" style="font-style:italic;color:#6b7280">... dan {{ count($cdnItems)-100 }} item lainnya.</td></tr>
          @endif
        </tbody>
      </table>
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
      @php $ttpItems = $ttpGantung->ttp_json ?? []; @endphp
      <div class="kv" style="margin-bottom:8px;">
        <span class="kv-label">Tgl Audit:</span>
        <span class="kv-val">{{ $ttpGantung->tgl_audit ? \Carbon\Carbon::parse($ttpGantung->tgl_audit)->format('d/m/Y') : '-' }}</span>
      </div>
      @if(count($ttpItems))
      <table>
        <thead><tr><th>#</th><th>Nama</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach($ttpItems as $i => $t)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $t['nama'] ?? $t['name'] ?? '-' }}</td>
            <td style="text-align:right">{{ isset($t['jumlah']) ? number_format($t['jumlah'], 0, ',', '.') : '-' }}</td>
            <td>{{ $t['keterangan'] ?? $t['ket'] ?? '-' }}</td>
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
     12. CEK FISIK
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">12. CEK FISIK</div>
  <div class="section-body">
    @if(!$cekFisik)
      <p class="empty">Belum ada data.</p>
    @else
      @php $cfData = $cekFisik->data_json ?? []; @endphp
      @if(is_array($cfData) && count($cfData))
      <table>
        <thead><tr><th>#</th><th>No Rangka / ID</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach(array_slice($cfData, 0, 100) as $i => $cf)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ is_array($cf) ? ($cf['no_rangka'] ?? $cf['id'] ?? json_encode($cf)) : $cf }}</td>
            <td>{{ is_array($cf) ? ($cf['keterangan'] ?? $cf['ket'] ?? '') : '' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
        <p class="empty">Tidak ada data.</p>
      @endif
    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     13. MT (Motor Tarikan)
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">13. MT (Motor Tarikan)</div>
  <div class="section-body">
    @if(!$mt)
      <p class="empty">Belum ada data.</p>
    @else
      @php $mtData = $mt->data_json ?? []; @endphp
      @if(is_array($mtData) && count($mtData))
      <table>
        <thead><tr><th>#</th><th>Detail</th></tr></thead>
        <tbody>
          @foreach(array_slice($mtData, 0, 50) as $i => $row)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ is_array($row) ? implode(' | ', array_filter(array_map(fn($v) => is_scalar($v) ? $v : null, $row))) : $row }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @else
        <p class="empty">Tidak ada data.</p>
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
