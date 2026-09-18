<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MengunciDataPemeriksaan;
use App\Http\Controllers\Concerns\RequiresAuditorAuditee;
use App\Http\Controllers\Controller;
use App\Models\DbMt;
use App\Models\PemeriksaanMt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MtController extends Controller
{
    use RequiresAuditorAuditee;
    use MengunciDataPemeriksaan;

    public function show(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $rec    = PemeriksaanMt::where('plan_audit_id', $planId)->first();
        return response()->json(['data' => $rec ? $rec->toAktaArray() : null]);
    }

    /**
     * Simpan pemeriksaan MT.
     *
     * Dulu isi kiriman browser menimpa seluruh data_json. Satu SPT dikerjakan
     * dua auditor yang membagi mekanik, dan tiap perubahan kecil (menambah
     * mekanik, mencabut tool, ganti jenis) langsung mengirim SELURUH data dari
     * layarnya masing-masing. Akibatnya yang menyimpan belakangan menulis dari
     * salinan yang belum memuat pekerjaan rekannya: dari 6 mekanik yang
     * diperiksa berdua, cuma 3 yang tersisa.
     *
     * Sekarang isinya DIGABUNG per (mekanik, jenis), di dalam transaksi dengan
     * barisnya dikunci. Yang tidak dikenal layar pengirim dibiarkan utuh --
     * itu punya rekannya.
     *
     * Menghapus tetap bisa: browser ikut mengirim "dikenal", yaitu daftar entri
     * yang ADA di layarnya waktu memuat. Entri yang dikenal tapi tidak ikut
     * dikirim berarti memang dihapus auditor itu, bukan pekerjaan rekan yang
     * belum terlihat. Kiriman lama tanpa "dikenal" (tab yang belum dimuat ulang
     * setelah pembaruan ini) tidak pernah menghapus apa pun -- kehilangan data
     * jauh lebih mahal daripada penghapusan yang tertunda sampai tab dimuat ulang.
     */
    public function save(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $this->ensureAuditorFilled((int) $planId, 'mt');
        $who     = $request->user()?->username ?? $request->user()?->email;
        $masuk   = (array) $request->input('data', []);
        $dikenal = $request->has('dikenal') ? (array) $request->input('dikenal') : null;

        return $this->denganKunciPemeriksaan(PemeriksaanMt::class, $planId,
            function (?PemeriksaanMt $rec) use ($planId, $who, $masuk, $dikenal) {
                $gabung = $this->gabungDataMt($rec?->data_json ?? [], $masuk, $dikenal);

                $rec = PemeriksaanMt::updateOrCreate(
                    ['plan_audit_id' => $planId],
                    ['data_json' => $gabung, 'updated_by' => $who]
                );
                if (! $rec->created_by) $rec->update(['created_by' => $who]);

                return response()->json(['message' => 'Data MT tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
            });
    }

    /** Satu entri MT dikenali dari nama mekanik + jenisnya. */
    private function kunciEntriMt(array $entri): string
    {
        return mb_strtoupper(trim((string) ($entri['mekanik'] ?? '')))
            . '|' . mb_strtolower(trim((string) ($entri['jenis'] ?? '')));
    }

    /**
     * @param  array<string,mixed>  $tersimpan  isi data_json di server
     * @param  array<string,mixed>  $masuk      isi dari layar pengirim
     * @param  array<int,string>|null  $dikenal  entri yang ada di layar itu waktu memuat
     * @return array<string,mixed>
     */
    private function gabungDataMt(array $tersimpan, array $masuk, ?array $dikenal): array
    {
        $entriMasuk = array_filter((array) ($masuk['entries'] ?? []), 'is_array');
        $entriLama  = array_filter((array) ($tersimpan['entries'] ?? []), 'is_array');

        $peta = [];
        foreach ($entriMasuk as $e) {
            $peta[$this->kunciEntriMt($e)] = $e;
        }

        $dihapus = [];
        foreach (($dikenal ?? []) as $k) {
            $k = (string) $k;
            if (! isset($peta[$k])) {
                $dihapus[$k] = true;
            }
        }

        $hasil = [];
        foreach ($entriLama as $e) {
            $k = $this->kunciEntriMt($e);
            if (isset($peta[$k]) || isset($dihapus[$k])) {
                continue;   // ditimpa kiriman, atau memang dihapus auditor ini
            }
            $hasil[$k] = $e;    // punya auditor lain — dipertahankan apa adanya
        }
        foreach ($peta as $k => $e) {
            $hasil[$k] = $e;
        }

        // Pilihan jenis per mekanik ikut digabung, lalu yang mekaniknya sudah
        // tidak ada dibuang supaya tidak menumpuk jadi sampah.
        $mekanikAda = [];
        foreach ($hasil as $e) {
            $mekanikAda[mb_strtoupper(trim((string) ($e['mekanik'] ?? '')))] = true;
        }
        $jenis = array_merge(
            (array) ($tersimpan['mekanikSelectedJenis'] ?? []),
            (array) ($masuk['mekanikSelectedJenis'] ?? [])
        );
        foreach (array_keys($jenis) as $nama) {
            if (! isset($mekanikAda[mb_strtoupper(trim((string) $nama))])) {
                unset($jenis[$nama]);
            }
        }

        return array_merge($masuk, [
            'entries'              => array_values($hasil),
            'mekanikSelectedJenis' => $jenis,
        ]);
    }

    // Ambil daftar tools dari db_mt, dikelompokkan per jenis
    public function tools(Request $request): JsonResponse
    {
        $jenis = $request->query('jenis'); // 'baru' | 'lama' | 'fi'

        $jenisMap = [
            'baru' => 'MT Baru',
            'lama' => 'MT Lama',
            'fi'   => 'MT FI',
        ];

        $query = DbMt::orderBy('nomor');

        if ($jenis && isset($jenisMap[$jenis])) {
            $query->where('jenis', $jenisMap[$jenis]);
        }

        $rows = $query->get()->map(fn($r) => [
            'nama'          => $r->nama_peralatan ?: $r->nama_singkat,
            'namaSingkat'   => $r->nama_singkat,
            'kode'          => $r->kode_peralatan,
            'harga'         => $r->harga !== null ? (float) $r->harga : null,
            'jenis'         => $r->jenis,
        ]);

        // Dedupe by nama (trim+lowercase) — db_mt kadang punya baris ganda untuk
        // nama tool yang sama (mis. hasil import Excel dengan baris terduplikasi).
        // Tanpa ini, tiap tool yang ganda ikut ke-auto-isi 2x ke kategori Bagus
        // saat mekanik baru pertama kali dibuka, dan salah satu duplikatnya tetap
        // "available" untuk dipilih di kategori lain walau kelihatan sudah ada.
        $rows = $rows->unique(fn($r) => strtolower(trim($r['nama'])))->values();

        return response()->json(['data' => $rows]);
    }
}
