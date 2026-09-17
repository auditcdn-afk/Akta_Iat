<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PemeriksaanSmhController extends Controller
{
    use RequiresAuditorAuditee;

    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    /**
     * Batas skor {@see skorCocokScan()} yang dianggap "cocok penuh": nomor
     * persis sama, atau nomor tersimpan berakhiran persis hasil scan. Di bawah
     * itu kecocokannya cuma sepotong, jadi unitnya tidak boleh dipilih otomatis
     * kalau kandidatnya lebih dari satu.
     */
    private const SKOR_COCOK_PENUH = 70;

    /** Skor {@see skorCocokScan()} untuk nomor yang sama persis. */
    private const SKOR_NOMOR_PERSIS = 90;

    /**
     * Panjang minimal nomor hasil scan (tanpa spasi) supaya kecocokan
     * "berakhiran" boleh dianggap pasti. Potongan pendek seperti 4 angka
     * terakhir terlalu sering nyangkut ke unit lain, jadi kalau kandidatnya
     * lebih dari satu auditor yang memilih.
     */
    private const MIN_PANJANG_YAKIN = 8;

    // ── GET /api/audit-detail/smh ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id') ?? $request->query('plan_id');

        $query = PemeriksaanSmh::query()->with(['items'])->latest('id');
        if ($planId) $query->where('plan_audit_id', $planId);

        return response()->json(['data' => $query->get()->map(fn($r) => $this->format($r))]);
    }

    // ── POST /api/audit-detail/smh/upload ────────────────────────────────────

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'         => 'required|file',
            'plan_audit_id' => 'required|integer|exists:plan_audits,id',
        ]);

        $this->ensureCanWrite($request, (int) $request->input('plan_audit_id'));
        $this->ensureAuditorFilled((int) $request->input('plan_audit_id'), 'smh');

        $file = $request->file('file');
        // setReadDataOnly: kita cuma butuh nilai selnya, bukan style/formatnya —
        // membaca style .xls/.xlsx bisa berkali-kali lipat lebih lambat dari
        // sekadar membaca nilainya, terutama untuk file besar dengan banyak
        // formatting warisan template lama.
        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        // ── Parse header tanggal (row index 3) ──
        $tglOnhand = null;
        foreach ($rows as $idx => $row) {
            $first = trim((string) ($row[0] ?? ''));
            if (preg_match('/\d{1,2}\s+\w+\s+\d{4}/', $first)) {
                try { $tglOnhand = Carbon::parse($first)->toDateString(); } catch (\Throwable) {}
                break;
            }
            // Sometimes date is in col index 5
            $sixth = trim((string) ($row[5] ?? ''));
            if (preg_match('/\d{1,2}\s+\w+\s+\d{4}/', $sixth)) {
                try { $tglOnhand = Carbon::parse($sixth)->toDateString(); } catch (\Throwable) {}
                break;
            }
        }

        // ── Parse units ──
        $units = [];
        $currentKodeModelIntern = null;
        $currentKodeWarnaIntern = null;

        foreach ($rows as $row) {
            $col0 = trim((string) ($row[0] ?? ''));
            $col1 = trim((string) ($row[1] ?? ''));
            $col2 = trim((string) ($row[2] ?? ''));

            // Group header: "Kode Model Intern :"
            if (str_contains(strtolower($col0), 'kode model intern')) {
                $currentKodeModelIntern = trim((string) ($row[2] ?? ''));
                $currentKodeWarnaIntern = trim((string) ($row[5] ?? ''));
                continue;
            }

            // Data row: first col is a number
            if (is_numeric($col0) && $col0 > 0 && $col1 !== '') {
                $tglSpb = null;
                $rawTgl = $row[5] ?? null;
                if (is_numeric($rawTgl)) {
                    try { $tglSpb = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$rawTgl))->toDateString(); } catch (\Throwable) {}
                } elseif ($rawTgl) {
                    try { $tglSpb = Carbon::parse($rawTgl)->toDateString(); } catch (\Throwable) {}
                }

                $units[] = [
                    'no_mesin'           => trim((string)($row[1] ?? '')),
                    'no_rangka'          => trim((string)($row[2] ?? '')),
                    'no_spb'             => trim((string)($row[3] ?? '')),
                    'tgl_spb'            => $tglSpb,
                    'status_spb'         => trim((string)($row[6] ?? '')),
                    'umur'               => is_numeric($row[7] ?? null) ? (int)$row[7] : null,
                    'no_do'              => trim((string)($row[8] ?? '')),
                    'kode_model'         => trim((string)($row[9] ?? '')),
                    'kode_model_intern'  => $currentKodeModelIntern,
                    'warna'              => trim((string)($row[10] ?? '')),
                    'kode_warna_intern'  => $currentKodeWarnaIntern,
                    'gudang'             => trim((string)($row[11] ?? '')),
                    'book'               => trim((string)($row[12] ?? '')),
                ];
            }
        }

        // ── Simpan ──
        $planAuditId = (int) $request->input('plan_audit_id');
        $plan = PlanAudit::find($planAuditId);

        $hasil = $this->simpanOnhand($planAuditId, $plan, $units, $tglOnhand, $this->who($request));

        return response()->json([
            'message' => $this->pesanHasilUnggah($hasil),
            'data'    => $this->format($hasil['pmx']->load('items')),
        ], 201);
    }

    /**
     * Tulis daftar onhand ke database SATU KALI saja, walau filenya diunggah
     * dua kali beriringan.
     *
     * Dulu langkahnya: ambil/buat header, HAPUS semua item lama, lalu masukkan
     * seluruh isi file. Dua unggahan yang datang hampir bersamaan (auditor
     * mengklik "Upload & Proses" dua kali karena filenya lama diproses)
     * sama-sama menghapus isi lama yang belum sempat ditulis siapa pun, lalu
     * sama-sama memasukkan seluruh isi file — daftar unitnya jadi dobel,
     * sementara angka Total Unit di header tetap sejumlah baris file. Itu
     * bukan cuma salah di layar: Plafon dan Report Audit menghitung nilai stok
     * dari baris-baris ini, jadi nilainya ikut jadi dua kali lipat.
     *
     * Sekarang penulisannya dikunci per plan, dan isinya DICOCOKKAN, bukan
     * dihapus lalu ditulis ulang: unit yang sudah ada diperbarui data
     * masternya, unit baru ditambahkan, unit yang tidak ada lagi di file
     * dibuang HANYA kalau belum pernah disentuh auditor.
     *
     * @param  array<int,array<string,mixed>>  $units
     * @return array{pmx:PemeriksaanSmh,total:int,baru:int,diperbarui:int,dipertahankan:int,dibuang:int,kembarDiFile:int}
     */
    private function simpanOnhand(int $planAuditId, ?PlanAudit $plan, array $units, ?string $tglOnhand, ?string $oleh): array
    {
        return DB::transaction(function () use ($planAuditId, $plan, $units, $tglOnhand, $oleh) {
            $pmx = PemeriksaanSmh::firstOrCreate(
                ['plan_audit_id' => $planAuditId],
                ['no_spt' => $plan?->no_spt, 'cabang' => $plan?->cabang, 'created_by' => $oleh],
            );

            // Gerbangnya: unggahan kedua yang datang berbarengan menunggu di
            // baris ini sampai yang pertama selesai, jadi ia melihat isi yang
            // sudah lengkap dan tidak menuliskannya lagi.
            PemeriksaanSmh::query()->whereKey($pmx->id)->lockForUpdate()->first();

            // File onhand sendiri kadang memuat baris kembar; satu unit tetap
            // cuma boleh sekali.
            $dariFile = [];
            $kembarDiFile = 0;
            foreach ($units as $u) {
                $k = $this->kunciUnit($u['no_mesin'] ?? null, $u['no_rangka'] ?? null);
                if (isset($dariFile[$k])) { $kembarDiFile++; continue; }
                $dariFile[$k] = $u;
            }

            $tersimpan = [];
            $buang     = [];
            foreach ($pmx->items()->get() as $it) {
                $k = $this->kunciUnit($it->no_mesin, $it->no_rangka);
                // Sisa penggandaan lama — sebelum penguncian ini ada.
                if (isset($tersimpan[$k])) { $buang[] = $it->id; continue; }
                $tersimpan[$k] = $it;
            }

            $masuk = [];
            $diperbarui = 0;
            $now = now();
            foreach ($dariFile as $k => $u) {
                $it = $tersimpan[$k] ?? null;
                if (! $it) {
                    $masuk[] = array_merge($u, [
                        'pemeriksaan_smh_id' => $pmx->id,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ]);
                    continue;
                }

                // Hasil pemeriksaan fisiknya tidak disentuh sama sekali; yang
                // disegarkan cuma data master dari file.
                $it->fill($u);
                if ($it->isDirty()) { $it->save(); $diperbarui++; }
                unset($tersimpan[$k]);
            }

            // Sisanya sudah tidak ada di file onhand. Yang belum pernah
            // disentuh auditor dibuang; yang sudah diperiksa atau ditambahkan
            // manual tetap disimpan — pekerjaan auditor tidak boleh hilang
            // gara-gara file onhand diunggah ulang.
            $dipertahankan = 0;
            foreach ($tersimpan as $it) {
                if ($this->belumDisentuh($it)) { $buang[] = $it->id; continue; }
                $dipertahankan++;
            }

            if ($buang) {
                foreach (array_chunk($buang, 500) as $sebagian) {
                    SmhOnhandItem::query()->whereIn('id', $sebagian)->delete();
                }
            }
            foreach (array_chunk($masuk, 200) as $sebagian) {
                SmhOnhandItem::insert($sebagian);
            }

            $pmx->fill([
                'no_spt'     => $plan?->no_spt ?? $pmx->no_spt,
                'cabang'     => $plan?->cabang ?? $pmx->cabang,
                'tgl_onhand' => $tglOnhand ?? $pmx->tgl_onhand,
                'updated_by' => $oleh,
            ]);
            $this->hitungUlangTotal($pmx);

            return [
                'pmx'           => $pmx,
                'total'         => count($dariFile),
                'baru'          => count($masuk),
                'diperbarui'    => $diperbarui,
                'dipertahankan' => $dipertahankan,
                'dibuang'       => count($buang),
                'kembarDiFile'  => $kembarDiFile,
            ];
        });
    }

    /**
     * Kunci identitas satu unit: no mesin + no rangka, tanpa spasi dan huruf
     * besar semua. File onhand menulisnya "KC03E 1009289" sementara input
     * manual menyimpannya tanpa spasi — dua-duanya unit yang sama.
     */
    private function kunciUnit(?string $noMesin, ?string $noRangka): string
    {
        $rapikan = fn(?string $v) => strtoupper(preg_replace('/\s+/', '', (string) $v));

        return $rapikan($noMesin) . '|' . $rapikan($noRangka);
    }

    /** Unit yang belum pernah dipegang auditor — aman dibuang saat onhand diperbarui. */
    private function belumDisentuh(SmhOnhandItem $it): bool
    {
        return $it->status_fisik === null
            && $it->checked_at === null
            && ($it->keterangan_fisik === null || $it->keterangan_fisik === '')
            && empty($it->perlengkapan_json);
    }

    /** Hitung ulang angka ringkasan dari isi tabelnya, bukan dari asumsi. */
    private function hitungUlangTotal(PemeriksaanSmh $pmx): void
    {
        $hitung = $pmx->items()->selectRaw(
            'COUNT(*) as total, ' .
            "SUM(CASE WHEN status_fisik = 'ada' THEN 1 ELSE 0 END) as ditemukan, " .
            "SUM(CASE WHEN status_fisik = 'tidak_ada' THEN 1 ELSE 0 END) as tidak_ditemukan"
        )->first();

        $pmx->total_unit            = (int) $hitung->total;
        $pmx->total_ditemukan       = (int) $hitung->ditemukan;
        $pmx->total_tidak_ditemukan = (int) $hitung->tidak_ditemukan;
        $pmx->save();
    }

    /** @param array<string,mixed> $h */
    private function pesanHasilUnggah(array $h): string
    {
        $bagian = ["{$h['total']} unit di file"];
        $bagian[] = "{$h['baru']} baru";
        if ($h['diperbarui'])    $bagian[] = "{$h['diperbarui']} diperbarui";
        if ($h['dipertahankan']) $bagian[] = "{$h['dipertahankan']} unit di luar file tetap disimpan karena sudah diperiksa";
        if ($h['dibuang'])       $bagian[] = "{$h['dibuang']} baris lama dibuang";
        if ($h['kembarDiFile'])  $bagian[] = "{$h['kembarDiFile']} baris kembar di file diabaikan";

        return 'File onhand berhasil diproses: ' . implode(', ', $bagian) . '.';
    }

    // ── PUT /api/audit-detail/smh/items/{item} ───────────────────────────────

    public function checkItem(Request $request, SmhOnhandItem $item): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $item->pemeriksaan?->plan_audit_id);

        $data = $request->validate([
            'status_fisik'       => 'required|in:ada,tidak_ada',
            'keterangan_fisik'   => 'nullable|string|max:500',
            'tgl_periksa'        => 'nullable|date',
            'keterangan_kondisi' => 'nullable|string|max:100',
            'perlengkapan_json'  => 'nullable|array',
        ]);

        $item->update(array_merge($data, ['checked_at' => now()]));

        $pmx = $item->pemeriksaan;
        $pmx->updated_by = $this->who($request);
        $this->hitungUlangTotal($pmx);

        return response()->json(['message' => 'Status fisik diperbarui.', 'data' => $this->formatItem($item->fresh())]);
    }

    // ── GET /api/audit-detail/smh/perlengkapan?kode=JBK1E ────────────────────
    // Ambil daftar perlengkapan berdasarkan 5 huruf prefix no_mesin

    public function perlengkapan(Request $request): JsonResponse
    {
        $kode    = strtoupper(trim((string) $request->query('kode', '')));
        if (!$kode) return response()->json(['data' => null, 'items' => []]);

        $planId  = $request->query('plan_audit_id');
        $wilayah = $this->wilayahFromPlan($planId);
        $row     = $this->findPerlengkapan($kode, $wilayah);

        return response()->json([
            'data'  => $row,
            'items' => $row ? $row->itemList() : [],
            'nama'  => $row?->nama,
            'tipe'  => $row?->satuan,
        ]);
    }

    // ── GET /api/audit-detail/smh/scan ───────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        $q      = trim((string) $request->query('q', ''));
        $planId = $request->query('plan_audit_id');
        $itemId = (int) $request->query('item_id', 0);

        // Unit dipilih eksplisit dari daftar saran / daftar kandidat: ambil
        // baris itu apa adanya, TANPA pencarian lagi. Ini yang bikin dua unit
        // berbeda yang kebetulan punya 4-5 angka ekor sama tetap bisa dipilih
        // satu per satu — dulu klik saran cuma mengirim teks nomornya, lalu
        // dicari ulang dan yang ketemu duluan (unit lain) yang kepilih.
        if ($itemId > 0) {
            $query = SmhOnhandItem::query()->whereKey($itemId);
            if ($planId) {
                $query->whereHas('pemeriksaan', fn($q2) => $q2->where('plan_audit_id', $planId));
            }
            $item = $query->first();

            return response()->json([
                'data'         => $item ? $this->formatItem($item) : null,
                'perlengkapan' => $item ? $this->perlengkapanForItem($item, $planId) : [],
                'matches'      => [],
                'ambiguous'    => false,
                'message'      => $item ? 'Unit ditemukan.' : 'Unit tidak ditemukan dalam daftar onhand.',
            ]);
        }

        if (strlen($q) < 2) {
            return response()->json(['data' => null, 'matches' => [], 'ambiguous' => false, 'message' => 'Minimal 2 karakter.']);
        }

        // No mesin/rangka hasil import Excel kadang ada spasi (mis. "JMK2E
        // 1003815"), tapi barcode fisik di unit biasanya tidak ada spasi sama
        // sekali. Bandingkan juga versi tanpa-spasi di kedua sisi supaya scan
        // barcode tetap menemukan data yang tersimpan dengan spasi.
        $qNoSpace = str_replace(' ', '', $q);

        // Barcode fisik No. Rangka kadang berupa nomor rangka LENGKAP (mis.
        // "MH1KFG112TK001838"), sedangkan yang tersimpan di data onhand hasil
        // import SPB kadang sudah berupa versi ringkas/internal (mis.
        // "KD1112TK722826") — beda total, bukan cuma beda spasi, jadi
        // pencocokan substring di atas tidak akan pernah ketemu. Sebagai
        // fallback, cocokkan juga 5 karakter TERAKHIR hasil scan terhadap 5
        // karakter terakhir no_mesin/no_rangka yang tersimpan — bagian ekor
        // nomor seri ini yang biasanya tetap sama di kedua format.
        $qLast5 = strlen($qNoSpace) >= 5 ? substr($qNoSpace, -5) : null;

        $query = SmhOnhandItem::query()
            ->where(function ($q2) use ($q, $qNoSpace, $qLast5) {
                $q2->where('no_mesin', 'like', "%{$q}%")
                    ->orWhere('no_rangka', 'like', "%{$q}%")
                    ->orWhereRaw("REPLACE(no_mesin, ' ', '') LIKE ?", ["%{$qNoSpace}%"])
                    ->orWhereRaw("REPLACE(no_rangka, ' ', '') LIKE ?", ["%{$qNoSpace}%"]);
                if ($qLast5 !== null) {
                    $q2->orWhereRaw("SUBSTR(REPLACE(no_mesin, ' ', ''), -5) = ?", [$qLast5])
                        ->orWhereRaw("SUBSTR(REPLACE(no_rangka, ' ', ''), -5) = ?", [$qLast5]);
                }
            });

        if ($planId) {
            $query->whereHas('pemeriksaan', fn($q2) => $q2->where('plan_audit_id', $planId));
        }

        // Dulu langsung ->first(): unit mana pun yang duluan tersimpan yang
        // kepilih, walau ada unit lain yang nomornya PERSIS sama dengan hasil
        // scan. Sekarang semua kandidat diambil lalu diperingkat; kecocokan
        // penuh selalu menang atas kecocokan ekor 4-5 angka.
        $candidates = $query->limit(50)->get();

        if ($candidates->isEmpty()) {
            return response()->json([
                'data'         => null,
                'perlengkapan' => [],
                'matches'      => [],
                'ambiguous'    => false,
                'message'      => 'Unit tidak ditemukan dalam daftar onhand.',
            ]);
        }

        $scored = $candidates
            ->map(fn(SmhOnhandItem $it) => ['item' => $it, 'score' => $this->skorCocokScan($it, $qNoSpace, $qLast5)])
            ->sortBy([['score', 'desc'], ['item.id', 'asc']])
            ->values();

        $skorTerbaik = $scored->first()['score'];
        $terbaik     = $scored->where('score', $skorTerbaik)->values();

        // Boleh langsung dipilihkan HANYA kalau satu unit menang telak lewat
        // kecocokan penuh (nomor persis / berakhiran nomor hasil scan). Kalau
        // cuma cocok sepotong di tengah atau lewat fallback ekor 5 karakter,
        // dan kandidatnya lebih dari satu, jangan tebak — dua unit berbeda
        // memang bisa punya 4-5 angka yang sama di nomor yang berbeda.
        $yakin = $terbaik->count() === 1 && (
            $skorTerbaik >= self::SKOR_NOMOR_PERSIS
            || ($skorTerbaik >= self::SKOR_COCOK_PENUH && strlen($qNoSpace) >= self::MIN_PANJANG_YAKIN)
        );

        if (!$yakin && $scored->count() > 1) {
            // Tampilkan semua kandidat (paling mirip di urutan atas), bukan
            // cuma yang skornya sama, supaya unit yang dicari pasti ada di
            // daftar walau kemiripannya lewat kolom yang berbeda.
            $kandidat = $scored->take(10);

            return response()->json([
                'data'         => null,
                'perlengkapan' => [],
                'matches'      => $kandidat->map(fn($row) => $this->formatItem($row['item']))->values(),
                'ambiguous'    => true,
                'message'      => 'Ada ' . $kandidat->count() . ' unit dengan nomor mirip — pilih unit yang sedang diperiksa.',
            ]);
        }

        $item = $scored->first()['item'];

        return response()->json([
            'data'         => $this->formatItem($item),
            'perlengkapan' => $this->perlengkapanForItem($item, $planId),
            'matches'      => [],
            'ambiguous'    => false,
            'message'      => 'Unit ditemukan.',
        ]);
    }

    /**
     * Peringkat kecocokan hasil scan terhadap satu unit onhand. Makin tinggi
     * makin yakin, dan kecocokan pada NO MESIN diutamakan karena itu yang
     * biasanya discan/diketik auditor di kolom Fisik Scan.
     */
    private function skorCocokScan(SmhOnhandItem $it, string $qNoSpace, ?string $qLast5): int
    {
        $mesin  = strtoupper(str_replace(' ', '', (string) $it->no_mesin));
        $rangka = strtoupper(str_replace(' ', '', (string) $it->no_rangka));
        $needle = strtoupper($qNoSpace);

        if ($needle !== '' && $mesin === $needle)  return 100;   // no mesin persis
        if ($needle !== '' && $rangka === $needle) return 90;    // no rangka persis
        if ($needle !== '' && $mesin !== '' && str_ends_with($mesin, $needle))   return 80;
        if ($needle !== '' && $rangka !== '' && str_ends_with($rangka, $needle)) return 70;
        if ($needle !== '' && $mesin !== '' && str_contains($mesin, $needle))    return 60;
        if ($needle !== '' && $rangka !== '' && str_contains($rangka, $needle))  return 50;

        // Sisanya cuma nyangkut lewat fallback ekor 5 karakter — paling lemah.
        if ($qLast5 !== null) {
            $tail = strtoupper($qLast5);
            if ($mesin !== '' && str_ends_with($mesin, $tail))   return 20;
            if ($rangka !== '' && str_ends_with($rangka, $tail)) return 10;
        }

        return 0;
    }

    /** Perlengkapan SMH untuk satu unit, berdasarkan prefix no mesin + wilayah plan. */
    private function perlengkapanForItem(SmhOnhandItem $item, ?string $planId): array
    {
        $prefix  = strtoupper(substr(str_replace(' ', '', $item->no_mesin ?? ''), 0, 5));
        $wilayah = $this->wilayahFromPlan($planId);
        $plRow   = $this->findPerlengkapan($prefix, $wilayah);

        return $plRow ? $plRow->itemList() : [];
    }

    // ── GET /api/audit-detail/smh/{pmx}/sync-perlengkapan ────────────────────

    public function syncPerlengkapan(PemeriksaanSmh $pemeriksaanSmh): JsonResponse
    {
        $items   = $pemeriksaanSmh->items()->get();
        $wilayah = $this->wilayahFromPlan((string) $pemeriksaanSmh->plan_audit_id);

        $grouped = [];
        foreach ($items as $it) {
            $prefix = strtoupper(substr(str_replace(' ', '', $it->no_mesin ?? ''), 0, 5));
            if (!isset($grouped[$prefix])) {
                $plRow = $this->findPerlengkapan($prefix, $wilayah);
                $grouped[$prefix] = [
                    'kode'         => $prefix,
                    'nama'         => $plRow?->nama,
                    'items_db'     => $plRow ? $plRow->itemList() : [],
                    'matched'      => $plRow !== null,
                    'total_unit'   => 0,
                    'total_lengkap'=> 0,
                ];
            }
            $grouped[$prefix]['total_unit']++;
            if ($it->status_fisik === 'ada' && $it->perlengkapan_json) {
                $allAda = collect($it->perlengkapan_json)->every(fn($p) => $p['ada'] ?? false);
                if ($allAda) $grouped[$prefix]['total_lengkap']++;
            }
        }

        return response()->json(['data' => array_values($grouped)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function format(PemeriksaanSmh $pmx): array
    {
        return [
            'id'                   => $pmx->id,
            'planAuditId'          => $pmx->plan_audit_id,
            'noSpt'                => $pmx->no_spt,
            'cabang'               => $pmx->cabang,
            'tglOnhand'            => $pmx->tgl_onhand?->toDateString(),
            'totalUnit'            => $pmx->total_unit,
            'totalDitemukan'       => $pmx->total_ditemukan,
            'totalTidakDitemukan'  => $pmx->total_tidak_ditemukan,
            'totalBelumDiperiksa'  => $pmx->total_unit - $pmx->total_ditemukan - $pmx->total_tidak_ditemukan,
            'keterangan'           => $pmx->keterangan,
            'items'                => $pmx->relationLoaded('items')
                ? $pmx->items->map(fn($i) => $this->formatItem($i))->values()
                : null,
        ];
    }

    private function formatItem(SmhOnhandItem $i): array
    {
        return [
            'id'              => $i->id,
            'noMesin'         => $i->no_mesin,
            'noRangka'        => $i->no_rangka,
            'noSpb'           => $i->no_spb,
            'tglSpb'          => $i->tgl_spb?->toDateString(),
            'statusSpb'       => $i->status_spb,
            'umur'            => $i->umur,
            'noDo'            => $i->no_do,
            'kodeModel'       => $i->kode_model,
            'kodeModelIntern' => $i->kode_model_intern,
            'warna'           => $i->warna,
            'kodeWarnaIntern' => $i->kode_warna_intern,
            'gudang'          => $i->gudang,
            'book'            => $i->book,
            'statusFisik'        => $i->status_fisik,
            'keteranganFisik'    => $i->keterangan_fisik,
            'checkedAt'          => $i->checked_at?->toDateTimeString(),
            'tglPeriksa'         => $i->tgl_periksa?->toDateString(),
            'keteranganKondisi'  => $i->keterangan_kondisi,
            'perlengkapanJson'   => $i->perlengkapan_json ?? [],
        ];
    }

    /** Resolve wilayah string from plan's unit usaha. */
    private function wilayahFromPlan(?string $planId): ?string
    {
        if (!$planId) return null;
        $plan = \App\Models\PlanAudit::find($planId);
        if (!$plan?->cabang) return null;
        $uu = \App\Models\DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();
        return $uu ? strtolower(trim($uu->wilayah ?? '')) : null;
    }

    /** Find DbPerlengkapan by kode + wilayah, fallback to any wilayah for same kode. */
    private function findPerlengkapan(string $kode, ?string $wilayah = null): ?DbPerlengkapan
    {
        try {
            $q = DbPerlengkapan::where('kode', $kode);
            if ($wilayah) {
                // Try exact wilayah first, then fallback to null wilayah
                $row = (clone $q)->where('wilayah', $wilayah)->first()
                    ?? (clone $q)->whereNull('wilayah')->first()
                    ?? $q->first();
                return $row;
            }
            return $q->first();
        } catch (\Illuminate\Database\QueryException $e) {
            return null;
        }
    }

    // ── POST /api/audit-detail/smh/manual ────────────────────────────────────

    public function storeManual(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $request->input('plan_audit_id'));
        $this->ensureAuditorFilled((int) $request->input('plan_audit_id'), 'smh');

        $data = $request->validate([
            'plan_audit_id' => ['required', 'integer', 'exists:plan_audits,id'],
            'no_mesin'      => ['required', 'string', 'max:80'],
            'no_rangka'     => ['required', 'string', 'max:80'],
            'gudang'        => ['nullable', 'string', 'max:80'],
        ]);

        $planId = (int) $data['plan_audit_id'];
        $plan   = PlanAudit::findOrFail($planId);

        // Ambil atau buat record PemeriksaanSmh untuk plan ini
        $smh = PemeriksaanSmh::firstOrCreate(
            ['plan_audit_id' => $planId],
            [
                'no_spt'     => $plan->no_spt,
                'cabang'     => $plan->cabang,
                'created_by' => $this->who($request),
            ]
        );

        // Unit yang diketik manual bisa saja sebenarnya ADA di daftar onhand —
        // cuma nomornya ditulis beda spasi/huruf sehingga tidak ketemu saat
        // discan. Kalau begitu barisnya ditandai ada, bukan ditambah baris
        // kedua untuk unit yang sama.
        $sudahAda = SmhOnhandItem::query()
            ->where('pemeriksaan_smh_id', $smh->id)
            ->whereRaw("UPPER(REPLACE(no_mesin, ' ', '')) = ?", [strtoupper(preg_replace('/\s+/', '', $data['no_mesin']))])
            ->whereRaw("UPPER(REPLACE(no_rangka, ' ', '')) = ?", [strtoupper(preg_replace('/\s+/', '', $data['no_rangka']))])
            ->first();

        if ($sudahAda) {
            $item = $sudahAda;
            $item->status_fisik     = 'ada';
            $item->keterangan_fisik = $item->keterangan_fisik ?: 'Input Manual';
            $item->checked_at       = now();
            if (($data['gudang'] ?? null) !== null) $item->gudang = $data['gudang'];
            $item->save();
        } else {
            $item = SmhOnhandItem::create([
                'pemeriksaan_smh_id' => $smh->id,
                'no_mesin'           => strtoupper(trim($data['no_mesin'])),
                'no_rangka'          => strtoupper(trim($data['no_rangka'])),
                'gudang'             => $data['gudang'] ?? null,
                'status_fisik'       => 'ada',
                'keterangan_fisik'   => 'Input Manual',
            ]);
        }

        // Update angka ringkasan di SMH header
        $smh->updated_by = $this->who($request);
        $this->hitungUlangTotal($smh);

        // Auto-sync perlengkapan untuk item ini
        $prefix  = strtoupper(substr(str_replace(' ', '', $item->no_mesin), 0, 5));
        $wilayah = $this->wilayahFromPlan((string) $planId);
        $plRow   = $this->findPerlengkapan($prefix, $wilayah);
        $perlengkapan = $plRow ? $plRow->itemList() : [];

        return response()->json([
            'message'      => $sudahAda
                ? 'Unit sudah ada di daftar onhand — ditandai ditemukan.'
                : 'Unit berhasil ditambahkan secara manual.',
            'item'         => $this->formatItem($item),
            'perlengkapan' => $perlengkapan,
            'smh'          => $this->format($smh->load('items')),
        ], 201);
    }

    private function ensureCanWrite(Request $request, int $planAuditId = 0): void
    {
        if ($planAuditId && PlanAudit::query()->where('id', $planAuditId)->where('is_mandiri', true)->exists()) {
            return;
        }

        abort_unless(in_array($this->role($request), $this->writeRoles, true), 403, 'Role tidak diizinkan.');
    }

    private function role(Request $request): string
    {
        return strtolower((string) ($request->user()?->role ?? ''));
    }

    private function who(Request $request): ?string
    {
        $u = $request->user();
        return $u?->username ?? $u?->email ?? null;
    }
}
