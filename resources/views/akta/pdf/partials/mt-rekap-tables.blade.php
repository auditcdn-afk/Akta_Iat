{{--
    Tabel per mekanik untuk satu kategori (rusak/hilang) — replika format
    laporan lama: KODE TOOL | NAMA TOOL | BAGUS | SK AUDIT | RUSAK | HILANG |
    HARGA, dengan tanda centang hanya di kolom kategori yang sedang dicetak
    (kolom lain memang selalu kosong di sini karena tabel ini sudah disaring
    per kategori — bukan berarti tool itu tidak pernah berstatus lain).

    Variabel: $rekap = [mekanik => ['keterangan'=>string, 'rows'=>[
        ['kode'=>string, 'nama'=>string, 'harga'=>float|null], ...
    ]]], $kategori = 'rusak' | 'hilang'.
--}}
@if(empty($rekap))
<p class="empty" style="padding:12px 0;">Tidak ada tools {{ $kategori === 'rusak' ? 'rusak' : 'hilang' }}.</p>
@else
    @foreach($rekap as $mekanik => $data)
    <div style="margin-bottom:16px;{{ !$loop->last ? 'page-break-inside:avoid;' : '' }}">
        <div style="font-weight:700;font-size:10.5px;color:#111827;margin-bottom:3px;">{{ $mekanik }}</div>
        <table style="width:100%;border-collapse:collapse;font-size:9px;color:#111827;">
            <thead>
                <tr>
                    <th style="border:1px solid #6b7280;background:#f3f4f6;padding:3px 6px;text-align:left;">KODE TOOL</th>
                    <th style="border:1px solid #6b7280;background:#f3f4f6;padding:3px 6px;text-align:left;">NAMA TOOL</th>
                    <th style="border:1px solid #6b7280;background:#f3f4f6;padding:3px 6px;text-align:center;width:52px;">BAGUS</th>
                    <th style="border:1px solid #6b7280;background:#f3f4f6;padding:3px 6px;text-align:center;width:56px;">SK AUDIT</th>
                    <th style="border:1px solid #6b7280;background:#f3f4f6;padding:3px 6px;text-align:center;width:52px;">RUSAK</th>
                    <th style="border:1px solid #6b7280;background:#f3f4f6;padding:3px 6px;text-align:center;width:52px;">HILANG</th>
                    <th style="border:1px solid #6b7280;background:#f3f4f6;padding:3px 6px;text-align:right;width:70px;">HARGA</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['rows'] as $row)
                <tr>
                    <td style="border:1px solid #d1d5db;padding:3px 6px;">{{ $row['kode'] !== '' ? $row['kode'] : '-' }}</td>
                    <td style="border:1px solid #d1d5db;padding:3px 6px;">{{ $row['nama'] }}</td>
                    <td style="border:1px solid #d1d5db;padding:3px 6px;text-align:center;"></td>
                    <td style="border:1px solid #d1d5db;padding:3px 6px;text-align:center;"></td>
                    <td style="border:1px solid #d1d5db;padding:3px 6px;text-align:center;">{{ $kategori === 'rusak' ? '✔' : '' }}</td>
                    <td style="border:1px solid #d1d5db;padding:3px 6px;text-align:center;">{{ $kategori === 'hilang' ? '✔' : '' }}</td>
                    <td style="border:1px solid #d1d5db;padding:3px 6px;text-align:right;">{{ $row['harga'] !== null ? number_format($row['harga'], 0, ',', '.') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($data['keterangan'] !== '')
        <div style="font-size:9px;color:#111827;margin-top:3px;"><strong>Keterangan :</strong>{{ $data['keterangan'] }}</div>
        @endif
    </div>
    @endforeach
@endif
