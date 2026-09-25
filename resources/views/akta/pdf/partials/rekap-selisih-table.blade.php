{{--
    Satu tabel "AHM OIL'S" atau "SPAREPART" pada cetakan Rekap Selisih.
    Dipakai lewat @include('akta.pdf.partials.rekap-selisih-table', ['items' => $oilItems]).

    Parameter 'kolomStok' opsional: kalau true, kolom FKT Blm Kutip & Claim
    ikut dicetak. Hanya HGP yang mengirimkannya, dan hanya untuk data hasil
    impor laporan stok WHS — tool lain (RSA HGP) datanya tidak pernah membawa
    kolom itu, jadi tabelnya tidak ikut melebar.

    Nomor baris (NO) sengaja mulai dari 1 di tabel ini sendiri — bukan
    memakai posisi item di daftar HGP/RSA HGP lengkap di atasnya.
--}}
@php
  $fmt = fn($v) => number_format((float) $v, 0, ',', '.');
  $totalSistem = 0; $totalFisik = 0; $totalSelisih = 0; $totalNilai = 0;
  $kolomStok = $kolomStok ?? false;
  $totalFkt = 0; $totalClaim = 0;
@endphp
@if(empty($items))
  <p class="empty">Tidak ada selisih.</p>
@else
<table>
  <thead>
    <tr>
      <th style="width:28px;">NO</th>
      <th style="width:100px;">KODE PART</th>
      <th>NAMA PART</th>
      @if($kolomStok)
        <th class="num" style="width:52px;background:#eef2ff;">FKT BLM KUTIP</th>
        <th class="num" style="width:44px;background:#eef2ff;">CLAIM</th>
      @endif
      <th style="width:56px;" class="num">SISTEM</th>
      <th style="width:50px;" class="num">FISIK</th>
      <th style="width:56px;" class="num">SELISIH</th>
      <th style="width:80px;" class="num">HET</th>
      <th style="width:120px;">KETERANGAN</th>
    </tr>
  </thead>
  <tbody>
    @foreach($items as $it)
      @php
        $sistem   = (float) ($it['saldoAkhir'] ?? $it['saldoAwal'] ?? 0);
        $fisik    = (float) ($it['fisik'] ?? 0);
        $selisih  = (float) ($it['selisih'] ?? 0);
        $hargaHet = (float) ($it['hargaHet'] ?? 0);
        $nilai    = $hargaHet * $selisih;
        $nama     = $it['sparepart'] ?? $it['nama'] ?? '-';
        $totalSistem  += $sistem;
        $totalFisik   += $fisik;
        $totalSelisih += $selisih;
        $totalNilai   += $nilai;
        $fktKutip  = (float) ($it['stok']['fakturBelumKutip'] ?? 0);
        $claimItem = (float) ($it['stok']['claim'] ?? 0);
        $totalFkt   += $fktKutip;
        $totalClaim += $claimItem;
      @endphp
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $it['noPart'] ?? '-' }}</td>
        <td>{{ $nama }}</td>
        @if($kolomStok)
          <td class="num" style="background:#eef2ff;color:#3730a3;">{{ $fktKutip != 0 ? $fmt($fktKutip) : '—' }}</td>
          <td class="num" style="background:#eef2ff;color:#3730a3;">{{ $claimItem != 0 ? $fmt($claimItem) : '—' }}</td>
        @endif
        <td class="num">{{ $fmt($sistem) }}</td>
        <td class="num">{{ $fmt($fisik) }}</td>
        <td class="num {{ $selisih < 0 ? 'neg' : '' }}">{{ $fmt($selisih) }}</td>
        <td class="num {{ $nilai < 0 ? 'neg' : '' }}">{{ $fmt($nilai) }}</td>
        <td>{{ $it['keterangan'] ?? '' }}</td>
      </tr>
    @endforeach
    <tr style="background:#f3f4f6;font-weight:700;">
      <td colspan="3" style="text-align:right;">TOTAL</td>
      @if($kolomStok)
        <td class="num" style="background:#eef2ff;color:#3730a3;">{{ $totalFkt != 0 ? $fmt($totalFkt) : '—' }}</td>
        <td class="num" style="background:#eef2ff;color:#3730a3;">{{ $totalClaim != 0 ? $fmt($totalClaim) : '—' }}</td>
      @endif
      <td class="num">{{ $fmt($totalSistem) }}</td>
      <td class="num">{{ $fmt($totalFisik) }}</td>
      <td class="num {{ $totalSelisih < 0 ? 'neg' : '' }}">{{ $fmt($totalSelisih) }}</td>
      <td class="num {{ $totalNilai < 0 ? 'neg' : '' }}">{{ $fmt($totalNilai) }}</td>
      <td></td>
    </tr>
  </tbody>
</table>
@endif
