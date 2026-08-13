{{--
    Satu tabel "AHM OIL'S" atau "SPAREPART" pada cetakan Rekap Selisih.
    Dipakai lewat @include('akta.pdf.partials.rekap-selisih-table', ['items' => $oilItems]).

    Nomor baris (NO) sengaja mulai dari 1 di tabel ini sendiri — bukan
    memakai posisi item di daftar HGP/RSA HGP lengkap di atasnya.
--}}
@php
  $fmt = fn($v) => number_format((float) $v, 0, ',', '.');
  $totalSistem = 0; $totalFisik = 0; $totalSelisih = 0; $totalNilai = 0;
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
      @endphp
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $it['noPart'] ?? '-' }}</td>
        <td>{{ $nama }}</td>
        <td class="num">{{ $fmt($sistem) }}</td>
        <td class="num">{{ $fmt($fisik) }}</td>
        <td class="num {{ $selisih < 0 ? 'neg' : '' }}">{{ $fmt($selisih) }}</td>
        <td class="num {{ $nilai < 0 ? 'neg' : '' }}">{{ $fmt($nilai) }}</td>
        <td>{{ $it['keterangan'] ?? '' }}</td>
      </tr>
    @endforeach
    <tr style="background:#f3f4f6;font-weight:700;">
      <td colspan="3" style="text-align:right;">TOTAL</td>
      <td class="num">{{ $fmt($totalSistem) }}</td>
      <td class="num">{{ $fmt($totalFisik) }}</td>
      <td class="num {{ $totalSelisih < 0 ? 'neg' : '' }}">{{ $fmt($totalSelisih) }}</td>
      <td class="num {{ $totalNilai < 0 ? 'neg' : '' }}">{{ $fmt($totalNilai) }}</td>
      <td></td>
    </tr>
  </tbody>
</table>
@endif
