{{--
    Satu tabel "AHM OIL'S" atau "SPAREPART" pada cetakan Rekap Selisih.
    Dipakai lewat @include('akta.pdf.partials.rekap-selisih-table', ['items' => $oilItems]).
--}}
@php
  $fmt = fn($v) => number_format((float) $v, 0, ',', '.');
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
      @endphp
      <tr>
        <td>{{ (int) $it['no'] }}</td>
        <td>{{ $it['noPart'] ?? '-' }}</td>
        <td>{{ $nama }}</td>
        <td class="num">{{ $fmt($sistem) }}</td>
        <td class="num">{{ $fmt($fisik) }}</td>
        <td class="num {{ $selisih < 0 ? 'neg' : '' }}">{{ $fmt($selisih) }}</td>
        <td class="num {{ $nilai < 0 ? 'neg' : '' }}">{{ $fmt($nilai) }}</td>
        <td>{{ $it['keterangan'] ?? '' }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
@endif
