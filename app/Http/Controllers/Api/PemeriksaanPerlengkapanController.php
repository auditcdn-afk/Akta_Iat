<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbPerlengkapan;
use App\Models\DbUnitUsaha;
use App\Models\PemeriksaanPerlengkapan;
use App\Models\PemeriksaanSmh;
use App\Models\PlanAudit;
use App\Models\SmhOnhandItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PemeriksaanPerlengkapanController extends Controller
{
    use RequiresAuditorAuditee;

    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    // ── GET /api/audit-detail/perlengkapan ───────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $q = PemeriksaanPerlengkapan::query()->latest('id');
        if ($planId) $q->where('plan_audit_id', $planId);

        return response()->json(['data' => $q->get()->map->toAktaArray()]);
    }

    // ── GET /api/audit-detail/perlengkapan/jenis ─────────────────────────────
    // Ambil daftar jenis perlengkapan dari onhand yang sudah diperiksa (perlengkapan_json)
    // disinkronkan dengan db_perlengkapan, dikelompokkan per item perlengkapan.

    public function jenis(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');

        // Dasar daftar jenis = perlengkapan yang MEMANG dibutuhkan unit-unit onhand
        // plan ini (tipe motor onhand disilangkan ke db_perlengkapan). Sebelumnya
        // daftar ini hanya dibangun dari perlengkapan_json — yang baru terisi
        // setelah unit diperiksa fisik satu per satu — sehingga tepat setelah
        // impor onhand daftarnya jatuh ke fallback yang menampilkan seluruh
        // katalog wilayah, termasuk tipe motor yang tidak ada di cabang itu.
        $expected = $this->expectedPerJenis($planId);
        arsort($expected);
        $result = array_keys($expected);

        // Ambil semua item onhand dari plan ini
        $itemsQuery = SmhOnhandItem::query()
            ->whereNotNull('perlengkapan_json');

        if ($planId) {
            $itemsQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        // Nama yang tercatat saat periksa fisik tapi tidak ada di db_perlengkapan
        // (mis. ditambahkan manual auditor) tetap ikut, supaya tidak hilang.
        $extra = [];
        foreach ($itemsQuery->get() as $item) {
            foreach ($item->perlengkapan_json ?? [] as $pl) {
                $nama = trim($pl['nama'] ?? '');
                if ($nama !== '' && !isset($expected[$nama])) {
                    $extra[$nama] = ($extra[$nama] ?? 0) + 1;
                }
            }
        }

        arsort($extra);
        $result = array_merge($result, array_keys($extra));

        // Belum ada onhand sama sekali: tampilkan katalog wilayahnya supaya
        // auditor masih bisa mencatat perlengkapan secara manual.
        if (empty($result) && $planId) {
            $wilayah = $this->wilayahFromPlan($planId);
            $dbRows  = DbPerlengkapan::all()
                ->filter(fn($r) => !$wilayah || strtolower(trim($r->wilayah ?? '')) === $wilayah || blank($r->wilayah));

            foreach ($dbRows as $row) {
                foreach ($row->itemList() as $nama) {
                    if (!in_array($nama, $result, true)) $result[] = $nama;
                }
            }
        }

        return response()->json(['data' => $result]);
    }

    // ── GET /api/audit-detail/perlengkapan/smh-summary ───────────────────────
    // Hitung jumlah per jenis perlengkapan dari onhand (untuk qty referensi)

    public function smhSummary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => array_values($this->summaryPerJenis($request->query('plan_audit_id'))),
        ]);
    }

    /**
     * Rekap per jenis perlengkapan: berapa unit yang membutuhkannya (totalOnhand),
     * berapa yang sudah dicek (total), dan berapa yang perlengkapannya ada (ada).
     *
     * Seed-nya dari SELURUH unit onhand, bukan hanya yang sudah diperiksa fisik.
     * Ini inti perbaikannya: sebelumnya rekap ini hanya dibangun dari unit
     * ber-status_fisik "ada", sehingga tepat setelah impor onhand (belum ada satu
     * pun unit diperiksa) hasilnya array kosong dan form "Perlengkapan di luar
     * SMH" menampilkan Saldo 0 untuk semua jenis — seolah data onhand tidak
     * tersinkron dengan db_perlengkapan.
     *
     * @return array<string, array{nama: string, ada: int, total: int, totalOnhand: int}>
     */
    private function summaryPerJenis(?string $planId): array
    {
        $totalOnhandPerJenis = $this->expectedPerJenis($planId);

        $summary = [];
        foreach ($totalOnhandPerJenis as $nama => $totalOnhand) {
            $summary[$nama] = [
                'nama'        => $nama,
                'ada'         => 0,
                'total'       => 0,
                'totalOnhand' => $totalOnhand,
            ];
        }

        $itemsQuery = SmhOnhandItem::query()
            ->whereNotNull('perlengkapan_json')
            ->where('status_fisik', 'ada');

        if ($planId) {
            $itemsQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        // Timpa dengan hasil pemeriksaan fisik: ada vs total di setiap unit.
        foreach ($itemsQuery->get() as $item) {
            foreach ($item->perlengkapan_json ?? [] as $pl) {
                $nama = trim($pl['nama'] ?? '');
                if ($nama === '') continue;

                $summary[$nama] ??= [
                    'nama'        => $nama,
                    'ada'         => 0,
                    'total'       => 0,
                    'totalOnhand' => $totalOnhandPerJenis[$nama] ?? 0,
                ];

                $summary[$nama]['total']++;
                if ($pl['ada'] ?? false) $summary[$nama]['ada']++;
            }
        }

        return $summary;
    }

    /**
     * Saldo buku satu jenis perlengkapan = unit yang membutuhkannya dikurangi
     * unit yang perlengkapannya sudah ditemukan saat periksa fisik.
     *
     * Diturunkan di server, bukan diambil dari request: field Saldo di form
     * memang berlabel "Otomatis dari data onhand" dan tidak diisi manual, dan
     * ReportPdfController membaca nilai tersimpan ini apa adanya. Kalau angka
     * kiriman klien yang dipercaya, baris yang tersimpan saat rekap masih kosong
     * akan mengendap dengan Saldo 0 dan Selisih yang ikut salah.
     */
    private function saldoFor(?string $planId, string $jenis): float
    {
        $row = $this->summaryPerJenis($planId)[trim($jenis)] ?? null;

        if (!$row) {
            return 0.0;
        }

        return (float) max(0, $row['totalOnhand'] - $row['ada']);
    }

    /**
     * Jumlah unit onhand yang membutuhkan tiap jenis perlengkapan.
     *
     * Tiap jenis perlengkapan (mis. "Kaca Spion PCX160") hanya relevan untuk tipe
     * motor tertentu, jadi hitungannya bukan total seluruh unit onhand digabung.
     * Tipe motor diambil dari 5 huruf pertama no mesin dan dicocokkan ke
     * db_perlengkapan.kode — konvensi yang sama dipakai
     * PemeriksaanSmhController::syncPerlengkapan().
     *
     * @return array<string, int> nama perlengkapan => jumlah unit
     */
    private function expectedPerJenis(?string $planId): array
    {
        $kodeItemMap = $this->kodeItemMap($this->wilayahFromPlan($planId));

        $onhandQuery = SmhOnhandItem::query();

        if ($planId) {
            $onhandQuery->whereHas('pemeriksaan', fn($q) => $q->where('plan_audit_id', $planId));
        }

        $expected = [];

        foreach ($onhandQuery->get(['no_mesin']) as $unit) {
            $kode = strtoupper(substr(str_replace(' ', '', $unit->no_mesin ?? ''), 0, 5));

            foreach ($kodeItemMap[$kode] ?? [] as $nama) {
                $expected[$nama] = ($expected[$nama] ?? 0) + 1;
            }
        }

        return $expected;
    }

    /**
     * Peta kode tipe motor => daftar nama perlengkapan yang wajib ada.
     *
     * Satu kode bisa punya beberapa baris (per wilayah); dipilih yang cocok
     * wilayahnya, lalu baris tanpa wilayah, lalu apa pun yang ada.
     *
     * @return array<string, array<int, string>>
     */
    private function kodeItemMap(?string $wilayah): array
    {
        $map = [];

        foreach (DbPerlengkapan::all()->groupBy('kode') as $kode => $rows) {
            $match = $rows->first(fn($r) => $wilayah && strtolower(trim($r->wilayah ?? '')) === $wilayah)
                ?? $rows->first(fn($r) => blank($r->wilayah))
                ?? $rows->first();

            $map[$kode] = $match?->itemList() ?? [];
        }

        return $map;
    }

    // ── POST /api/audit-detail/perlengkapan ──────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $request->input('plan_audit_id'));
        $this->ensureAuditorFilled((int) $request->input('plan_audit_id'), 'perlengkapan');

        $data = $request->validate([
            'plan_audit_id'     => 'required|integer|exists:plan_audits,id',
            'no_plan'           => 'nullable|string|max:100',
            'nama_unit_usaha'   => 'nullable|string|max:200',
            'nama_pemeriksa'    => 'nullable|string|max:200',
            'tgl_periksa'       => 'nullable|date',
            'jenis_perlengkapan'=> 'required|string|max:200',
            'saldo'             => 'nullable|numeric',
            'fisik'             => 'nullable|integer',
            'penjelasan'        => 'nullable|string|max:1000',
        ]);

        // Saldo diturunkan dari data onhand terkini, bukan dari request.
        $saldo = $this->saldoFor((string) $data['plan_audit_id'], $data['jenis_perlengkapan']);
        $fisik = (int) ($data['fisik'] ?? 0);

        $data['saldo'] = $saldo;
        // selisih = fisik - saldo (kelebihan/kekurangan fisik vs saldo buku)
        $data['selisih']    = $fisik - $saldo;
        $data['created_by'] = $this->who($request);
        $data['updated_by'] = $this->who($request);

        // updateOrCreate (bukan create) supaya jenis perlengkapan yang sama untuk
        // plan yang sama hanya punya 1 baris. Kalau tidak, jenis yang sama diinput
        // ulang (mis. auditor scan ulang item yang sama) akan bikin baris baru, dan
        // total Saldo di tabel jadi ikut dobel, bukan tersinkron ke fisik terbaru.
        $rec = PemeriksaanPerlengkapan::updateOrCreate(
            ['plan_audit_id' => $data['plan_audit_id'], 'jenis_perlengkapan' => $data['jenis_perlengkapan']],
            $data
        );

        return response()->json(['message' => 'Data berhasil disimpan.', 'data' => $rec->toAktaArray()], 201);
    }

    // ── PUT /api/audit-detail/perlengkapan/{rec} ─────────────────────────────

    public function update(Request $request, PemeriksaanPerlengkapan $pemeriksaanPerlengkapan): JsonResponse
    {
        $this->ensureCanWrite($request, (int) $pemeriksaanPerlengkapan->plan_audit_id);
        $this->ensureAuditorFilled((int) $pemeriksaanPerlengkapan->plan_audit_id, 'perlengkapan');

        $data = $request->validate([
            'no_plan'           => 'nullable|string|max:100',
            'nama_unit_usaha'   => 'nullable|string|max:200',
            'nama_pemeriksa'    => 'nullable|string|max:200',
            'tgl_periksa'       => 'nullable|date',
            'jenis_perlengkapan'=> 'nullable|string|max:200',
            'saldo'             => 'nullable|numeric',
            'fisik'             => 'nullable|integer',
            'penjelasan'        => 'nullable|string|max:1000',
        ]);

        // Sama seperti store(): Saldo diturunkan dari data onhand terkini, jadi
        // baris lama yang tersimpan dengan Saldo salah ikut terkoreksi saat
        // di-update — tanpa perlu menghapus dan membuat ulang barisnya.
        $jenis = $data['jenis_perlengkapan'] ?? $pemeriksaanPerlengkapan->jenis_perlengkapan;
        $saldo = $this->saldoFor((string) $pemeriksaanPerlengkapan->plan_audit_id, (string) $jenis);
        $fisik = (int) ($data['fisik'] ?? $pemeriksaanPerlengkapan->fisik);

        $data['saldo']      = $saldo;
        $data['selisih']    = $fisik - $saldo;
        $data['updated_by'] = $this->who($request);

        $pemeriksaanPerlengkapan->update($data);

        return response()->json(['message' => 'Data berhasil diperbarui.', 'data' => $pemeriksaanPerlengkapan->fresh()->toAktaArray()]);
    }

    // ── DELETE /api/audit-detail/perlengkapan/{rec} ──────────────────────────

    public function destroy(PemeriksaanPerlengkapan $pemeriksaanPerlengkapan): JsonResponse
    {
        $pemeriksaanPerlengkapan->delete();
        return response()->json(['message' => 'Data dihapus.']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function wilayahFromPlan(?string $planId): ?string
    {
        if (!$planId) return null;
        $plan = PlanAudit::find($planId);
        if (!$plan?->cabang) return null;
        $uu = DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();
        return $uu ? strtolower(trim($uu->wilayah ?? '')) : null;
    }

    private function ensureCanWrite(Request $request, int $planAuditId = 0): void
    {
        if ($planAuditId && PlanAudit::query()->where('id', $planAuditId)->where('is_mandiri', true)->exists()) {
            return;
        }

        abort_unless(in_array(strtolower($request->user()?->role ?? ''), $this->writeRoles, true), 403, 'Role tidak diizinkan.');
    }

    private function who(Request $request): ?string
    {
        $u = $request->user();
        return $u?->username ?? $u?->email ?? null;
    }
}
