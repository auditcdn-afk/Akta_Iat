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
     2. PEMERIKSAAN SMH
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">2. PEMERIKSAAN SMH (Stock Motor Honda)</div>
  <div class="section-body">
    @if($smh->isEmpty())
      <p class="empty">Belum ada data.</p>
    @else
      @foreach($smh as $s)
      <div style="margin-bottom:12px;">
        <div class="kv-grid" style="margin-bottom:6px;">
          <div class="kv"><span class="kv-label">Tgl Onhand:</span><span class="kv-val">{{ $s->tgl_onhand ? \Carbon\Carbon::parse($s->tgl_onhand)->format('d/m/Y') : '-' }}</span></div>
          <div class="kv"><span class="kv-label">Total Unit:</span><span class="kv-val">{{ $s->total_unit ?? 0 }}</span></div>
          <div class="kv"><span class="kv-label">Ditemukan:</span><span class="kv-val">{{ $s->total_ditemukan ?? 0 }}</span></div>
          <div class="kv"><span class="kv-label">Tidak Ditemukan:</span><span class="kv-val">{{ $s->total_tidak_ditemukan ?? 0 }}</span></div>
        </div>
        @if($s->items && $s->items->count())
        <table>
          <thead><tr><th>#</th><th>No Rangka</th><th>No Mesin</th><th>Kode Model</th><th>Warna</th><th>Status Fisik</th></tr></thead>
          <tbody>
            @foreach($s->items->take(50) as $ii => $item)
            <tr>
              <td>{{ $ii+1 }}</td>
              <td>{{ $item->no_rangka ?? '-' }}</td>
              <td>{{ $item->no_mesin ?? '-' }}</td>
              <td>{{ $item->kode_model ?? '-' }}</td>
              <td>{{ $item->warna ?? '-' }}</td>
              <td>{{ $item->status_fisik ?? '-' }}</td>
            </tr>
            @endforeach
            @if($s->items->count() > 50)
            <tr><td colspan="6" style="font-style:italic;color:#6b7280">... dan {{ $s->items->count()-50 }} unit lainnya.</td></tr>
            @endif
          </tbody>
        </table>
        @endif
      </div>
      @endforeach
    @endif
  </div>
</div>

{{-- ═══════════════════════════════════════════════
     3. PERLENGKAPAN DI LUAR SMH
     ═══════════════════════════════════════════════ --}}
<div class="section">
  <div class="section-title">3. PERLENGKAPAN DI LUAR SMH</div>
  <div class="section-body">
    @if($perlengkapan->isEmpty())
      <p class="empty">Belum ada data.</p>
    @else
      <table>
        <thead><tr><th>#</th><th>Nama</th><th>Jumlah</th><th>Satuan</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach($perlengkapan as $i => $p)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $p->nama ?? $p->nama_item ?? '-' }}</td>
            <td style="text-align:right">{{ $p->jumlah ?? '-' }}</td>
            <td>{{ $p->satuan ?? '-' }}</td>
            <td>{{ $p->keterangan ?? '-' }}</td>
          </tr>
          @endforeach
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
      <table>
        <thead><tr><th>#</th><th>Nama Bank</th><th>No Rekening</th><th>Saldo Buku</th><th>Saldo Bank</th><th>Selisih</th><th>Tgl Periksa</th><th>Keterangan</th></tr></thead>
        <tbody>
          @foreach($bank as $i => $b)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $b->nama_bank ?? '-' }}</td>
            <td>{{ $b->no_rekening ?? '-' }}</td>
            <td style="text-align:right">{{ number_format($b->saldo_buku ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format($b->saldo_bank ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format($b->selisih ?? 0, 0, ',', '.') }}</td>
            <td>{{ $b->tgl_periksa ? \Carbon\Carbon::parse($b->tgl_periksa)->format('d/m/Y') : '-' }}</td>
            <td>{{ $b->keterangan ?? '-' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
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
      <table>
        <thead><tr><th>#</th><th>Jenis Materai</th><th>Saldo Awal</th><th>Total Debet</th><th>Total Kredit</th><th>Saldo Akhir</th><th>Fisik</th><th>Selisih</th></tr></thead>
        <tbody>
          @foreach($materai as $i => $m)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $m->jenis_materai ?? '-' }}</td>
            <td style="text-align:right">{{ number_format($m->saldo_awal ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format($m->total_debet ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format($m->total_kredit ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format($m->saldo_akhir ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format($m->fisik ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right">{{ number_format($m->selisih ?? 0, 0, ',', '.') }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
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
      <table>
        <thead><tr><th>#</th><th>No BPKB</th><th>No Polisi</th><th>Nama Pemilik</th><th>No Mesin</th><th>Jenis</th><th>Tgl Terima</th><th>Scan</th></tr></thead>
        <tbody>
          @foreach($bpkbOnhand->take(100) as $i => $b)
          <tr>
            <td>{{ (int)$i+1 }}</td>
            <td>{{ $b->no_bpkb ?? '-' }}</td>
            <td>{{ $b->no_polisi ?? '-' }}</td>
            <td>{{ $b->nama_pemilik ?? '-' }}</td>
            <td>{{ $b->no_mesin ?? '-' }}</td>
            <td>{{ $b->jenis ?? '-' }}</td>
            <td>{{ $b->tgl_terima ? \Carbon\Carbon::parse($b->tgl_terima)->format('d/m/Y') : '-' }}</td>
            <td>{{ $b->sudah_scan ? '✓' : '-' }}</td>
          </tr>
          @endforeach
          @if($bpkbOnhand->count() > 100)
          <tr><td colspan="8" style="font-style:italic;color:#6b7280">... dan {{ $bpkbOnhand->count()-100 }} item lainnya.</td></tr>
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
