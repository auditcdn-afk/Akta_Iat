<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditGrading;
use App\Models\DbGrading;
use App\Models\DbUnitUsaha;
use App\Models\Pica;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradingController extends Controller
{
    // Konversi nilai dengan format koma Indonesia ke float
    private static function toFloat($val): float
    {
        if ($val === null || $val === '') return 0.0;
        // Ganti koma desimal → titik, hapus karakter non-numerik kecuali titik & minus
        $clean = str_replace(',', '.', (string)$val);
        $clean = preg_replace('/[^0-9.\-]/', '', $clean);
        return (float)$clean;
    }

    // Jenis audit → jenis grading.
    //
    // Versi sebelumnya memetakan kunci 'H1'/'H2', padahal jenis_audit di plan
    // tidak pernah berisi itu: isinya "Audit Full SO", "Audit Full CSC",
    // "Audit Warehouse PART", dan seterusnya. Akibatnya pemetaannya TIDAK
    // PERNAH cocok, tombol Jenis tidak pernah terpilih otomatis, dan daftar
    // item grading jatuh ke pilihan yang salah.
    //
    // Dicocokkan lewat kata kunci, bukan daftar tetap, karena jenis audit ada
    // dua puluh lebih dan masih bertambah — sementara jenis grading cuma empat.
    // Urutannya penting: gudang diperiksa lebih dulu (paling khas), lalu
    // bengkel, baru kantor cabang. Yang tidak jelas sengaja dibiarkan kosong
    // supaya auditor memilih sendiri, bukan ditebak-tebak.
    // Satu "keluarga" jenis unit usaha, dengan semua sebutan yang mungkin
    // dipakai untuknya. Ini perlu karena SEBUTANNYA BERBEDA-BEDA antar tempat:
    // master grading memakai "CSC" dan "SO", tombol di layar dulu bertuliskan
    // "Bengkel" dan "Cabang", dan pemetaan lama bahkan memakai "H1"/"H2".
    // Ketiganya menunjuk hal yang sama, jadi yang dicocokkan keluarganya —
    // bukan tulisannya.
    //
    // Urutannya penting: gudang diperiksa lebih dulu karena paling khas, lalu
    // bengkel, baru kantor cabang.
    private const KELUARGA_JENIS = [
        'WHS PART' => ['WHS PART', 'WAREHOUSE PART', 'GUDANG PART', 'PARTKEEPER'],
        'WHS UNIT' => ['WHS UNIT', 'WAREHOUSE UNIT', 'GUDANG UNIT'],
        'CSC'      => ['CSC', 'BENGKEL', 'WORKSHOP', 'H2'],
        'SO'       => ['SO', 'SALES OFFICE', 'CABANG', 'H1'],
    ];

    /** Keluarga jenis dari sepotong teks bebas (jenis audit, nama unit usaha, label jenis). */
    private static function keluargaJenis(?string $teks): ?string
    {
        $t = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $teks)));
        if ($t === '') return null;

        foreach (self::KELUARGA_JENIS as $keluarga => $sebutan) {
            foreach ($sebutan as $k) {
                // Dicocokkan sebagai KATA UTUH: "SO" boleh menandai SO UJT dan
                // Audit PJS SO HEAD, tapi tidak boleh ikut tertarik oleh kata
                // lain yang kebetulan memuat huruf itu.
                if (preg_match('/\b' . preg_quote($k, '/') . '\b/', $t)) return $keluarga;
            }
        }
        return null;
    }

    /**
     * Jenis grading untuk sebuah plan, DALAM SEBUTAN YANG DIPAKAI MASTER.
     *
     * Jenis audit didahulukan karena di situlah SO dan CSC benar-benar
     * dibedakan; nama unit usaha jadi cadangan (mis. "Audit Kas + BPKB" di
     * CSC UJT tetap ketahuan bengkel). Master unit usaha dipakai paling akhir,
     * sebab di sana CSC dan SO sama-sama tercatat "Cabang" — kalau dipakai
     * lebih dulu justru mengembalikan kekeliruan yang sedang diperbaiki.
     *
     * Hasilnya lalu diterjemahkan ke sebutan yang benar-benar ada di master
     * grading, supaya penyaringan pasti ketemu: keluarga CSC mengembalikan
     * "CSC" kalau master menulisnya begitu, atau "Bengkel" kalau master
     * memakai sebutan itu.
     */
    private static function tebakJenisGrading(?string $jenisAudit, ?string $cabang, ?string $jenisUnitUsaha): ?string
    {
        $keluarga = self::keluargaJenis($jenisAudit)
            ?? self::keluargaJenis($cabang)
            ?? self::keluargaJenis($jenisUnitUsaha);
        if (!$keluarga) return null;

        foreach (self::jenisDiMaster() as $jenisMaster) {
            if (self::keluargaJenis($jenisMaster) === $keluarga) return $jenisMaster;
        }

        // Master belum punya jenis itu sama sekali — kembalikan sebutan bakunya
        // supaya tombolnya tetap terpilih dan layar bisa menerangkan keadaannya.
        return $keluarga;
    }

    private static function jenisDiMaster(): array
    {
        return DbGrading::query()->whereNotNull('jenis')->where('jenis', '!=', '')
            ->distinct()->orderBy('jenis')->pluck('jenis')->all();
    }

    public function show(Request $request): JsonResponse
    {
        try {
            $planId = $request->query('plan_audit_id');
            $rec    = AuditGrading::where('plan_audit_id', $planId)->first();
            if (!$rec) return response()->json(['data' => null]);

            $data = $rec->toAktaArray();
            $data['jenis'] = self::sebutanJenisDiMaster($data['jenis']);

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            // Tabel belum dibuat — kembalikan null agar UI tidak crash
            return response()->json(['data' => null]);
        }
    }

    /**
     * Terjemahkan jenis yang TERSIMPAN ke sebutan yang dipakai master sekarang.
     *
     * Grading yang disimpan sebelum sebutannya diseragamkan bisa menyimpan
     * "Bengkel" atau "Cabang", sementara master menulisnya "CSC" dan "SO".
     * Tanpa penerjemahan ini, membuka grading lama memilih jenis yang tidak ada
     * isinya di master — daftar itemnya kosong, dan tombol bersebutan lama itu
     * ikut nongol di layar seolah-olah pilihan yang sah.
     *
     * Yang tidak dikenali sama sekali dibiarkan apa adanya: lebih baik auditor
     * melihat pilihannya yang dulu daripada diam-diam digeser ke jenis lain.
     */
    private static function sebutanJenisDiMaster(?string $tersimpan): ?string
    {
        $tersimpan = trim((string) $tersimpan);
        if ($tersimpan === '') return $tersimpan;

        $diMaster = self::jenisDiMaster();
        foreach ($diMaster as $j) {
            if (strcasecmp($j, $tersimpan) === 0) return $j;   // sudah sesuai
        }

        $keluarga = self::keluargaJenis($tersimpan);
        if (!$keluarga) return $tersimpan;

        foreach ($diMaster as $j) {
            if (self::keluargaJenis($j) === $keluarga) return $j;
        }

        return $tersimpan;
    }

    public function save(Request $request): JsonResponse
    {
        try {
            $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
            $who    = $request->user()?->username ?? $request->user()?->email;

            $rec = AuditGrading::updateOrCreate(
                ['plan_audit_id' => $planId],
                [
                    'id_grading'       => $request->input('idGrading'),
                    'jenis'            => $request->input('jenis'),
                    'area'             => $request->input('area'),
                    'bbnkb'            => $request->input('bbnkb', 'N'),
                    'fraud'            => $request->input('fraud', 'N'),
                    'jenis_fraud'      => $request->input('jenisFraud', []),
                    'keterangan_fraud' => $request->input('keteranganFraud'),
                    'details'          => $request->input('details', []),
                    'total_nilai'      => $request->input('totalNilai'),
                    'updated_by'       => $who,
                ]
            );
            if (!$rec->created_by) $rec->update(['created_by' => $who]);

            // Auto-sync PICA untuk setiap item grading yang punya currentCondition
            $this->syncPicaFromGrading($rec, $planId, $who);

            return response()->json(['message' => 'Grading tersimpan.', 'data' => $rec->fresh()->toAktaArray()]);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), '42S02')) {
                return response()->json([
                    'message' => 'Tabel audit_gradings belum ada. Jalankan: php artisan migrate',
                    'migrate' => true,
                ], 500);
            }
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // Master: daftar pemeriksaan + pilihan hasil berdasarkan jenis & wilayah
    public function master(Request $request): JsonResponse
    {
        $jenis   = $request->query('jenis');   // Cabang, Bengkel, WHS PART, WHS UNIT
        $wilayah = $request->query('wilayah'); // RRI, dll

        // Dicocokkan tanpa memandang besar-kecil huruf: master menulis wilayah
        // "Aceh"/"Riau", sementara master unit usaha menulisnya "ACEH"/"RIAU".
        $query = DbGrading::query();
        if ($jenis)   $query->whereRaw('UPPER(jenis) = ?',   [mb_strtoupper(trim($jenis))]);
        if ($wilayah) $query->whereRaw('UPPER(wilayah) = ?', [mb_strtoupper(trim($wilayah))]);

        $rows = $query->orderBy('nama_pemeriksaan')->get();

        // Kelompokkan per nama_pemeriksaan → array hasil yang mungkin (deduplikasi by label)
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r->nama_pemeriksaan;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'namaPemeriksaan' => $key,
                    'idGrading'       => $r->id_grading,
                    'jenis'           => $r->jenis,
                    'wilayah'         => $r->wilayah,
                    'hasilOptions'    => [],
                    '_hasilLabels'    => [],   // tracking deduplikasi, dihapus sebelum return
                ];
            }
            if ($r->hasil_pemeriksaan) {
                $labelIdx = array_search($r->hasil_pemeriksaan, $grouped[$key]['_hasilLabels']);
                if ($labelIdx === false) {
                    // Belum ada — tambahkan
                    $grouped[$key]['_hasilLabels'][] = $r->hasil_pemeriksaan;
                    $grouped[$key]['hasilOptions'][] = [
                        'label' => $r->hasil_pemeriksaan,
                        'nilai' => self::toFloat($r->nilai),
                        'bknf'  => $r->bknf,
                        'pknf'  => self::toFloat($r->pknf),
                        'bkf'   => $r->bkf,
                        'pkf'   => self::toFloat($r->pkf),
                        'bnknf' => $r->bnknf,
                        'pnknf' => self::toFloat($r->pnknf),
                        'bnkf'  => $r->bnkf,
                        'pnkf'  => self::toFloat($r->pnkf),
                    ];
                } else {
                    // Sudah ada — update hanya kolom yang masih 0 dengan nilai non-zero dari baris ini
                    $existing = &$grouped[$key]['hasilOptions'][$labelIdx];
                    foreach (['nilai','pknf','pkf','pnknf','pnkf'] as $col) {
                        if (($existing[$col] ?? 0) == 0 && self::toFloat($r->$col) != 0) {
                            $existing[$col] = self::toFloat($r->$col);
                        }
                    }
                }
            }
        }

        // Hapus field tracking sebelum di-return
        foreach ($grouped as &$g) unset($g['_hasilLabels']);

        // Daftar kosong bukan jawaban yang cukup: layar harus bisa menjelaskan
        // KENAPA kosong, supaya auditor tahu ini soal data master yang belum
        // ada untuk jenis/wilayah itu -- bukan aplikasi yang rusak. Dulu layar
        // menutupi keadaan ini dengan menampilkan SELURUH master, sehingga
        // audit CSC ikut menampilkan item milik SO.
        $tersedia = [];
        if (!$grouped) {
            $tersedia = [
                'jenis'   => DbGrading::query()->whereNotNull('jenis')
                    ->distinct()->orderBy('jenis')->pluck('jenis')->values(),
                'wilayah' => DbGrading::query()->whereNotNull('wilayah')
                    ->when($jenis, fn($q) => $q->whereRaw('UPPER(jenis) = ?', [mb_strtoupper(trim($jenis))]))
                    ->distinct()->orderBy('wilayah')->pluck('wilayah')->values(),
                'total'   => DbGrading::query()->count(),
            ];
        }

        return response()->json([
            'data'     => array_values($grouped),
            'total'    => count($grouped),
            'diminta'  => ['jenis' => $jenis ?: '', 'wilayah' => $wilayah ?: ''],
            'tersedia' => $tersedia,
        ]);
    }

    // Daftar jenis unik dari db_grading
    public function jenisOptions(): JsonResponse
    {
        $jenis = DbGrading::select('jenis')->distinct()->whereNotNull('jenis')
            ->orderBy('jenis')->pluck('jenis');
        return response()->json(['data' => $jenis]);
    }

    // Daftar wilayah unik dari db_grading
    public function wilayahOptions(): JsonResponse
    {
        $wilayah = DbGrading::select('wilayah')->distinct()->whereNotNull('wilayah')
            ->orderBy('wilayah')->pluck('wilayah');
        return response()->json(['data' => $wilayah]);
    }

    // Info plan audit untuk auto-fill jenis & area
    public function planInfo(Request $request): JsonResponse
    {
        $planId = $request->query('plan_audit_id');
        $plan   = PlanAudit::find($planId);
        if (!$plan) return response()->json(['data' => null]);

        // Ambil wilayah dari db_unit_usaha berdasarkan cabang
        $unitUsaha = DbUnitUsaha::where('unit_usaha', $plan->cabang)->first();
        $wilayah   = $unitUsaha?->wilayah ?? $plan->cabang_area ?? '';

        $jenisGrading = self::tebakJenisGrading($plan->jenis_audit, $plan->cabang, $unitUsaha?->jenis);

        return response()->json(['data' => [
            'cabang'       => $plan->cabang,
            'area'         => $wilayah,
            'jenisAudit'   => $plan->jenis_audit,
            'jenisGrading' => $jenisGrading,
        ]]);
    }

    // Daftar semua grading yang sudah tersimpan (untuk menu utama Grading)
    public function index(Request $request): JsonResponse
    {
        try {
            $q        = $request->query('q', '');
            $wilayah  = $request->query('wilayah', '');
            $jenis    = $request->query('jenis', '');

            $query = AuditGrading::with('planAudit')
                ->whereNotNull('id')
                ->orderByDesc('updated_at');

            $rows = $query->get()->map(function ($g) {
                $plan = $g->planAudit;
                return [
                    'id'           => $g->id,
                    'planAuditId'  => $g->plan_audit_id,
                    'noSpt'        => $plan?->no_spt       ?? '-',
                    'cabang'       => $plan?->cabang       ?? '-',
                    'cabangArea'   => $plan?->cabang_area  ?? $g->area ?? '-',
                    'jenisAudit'   => $plan?->jenis_audit  ?? '-',
                    'tglMulai'     => optional($plan?->tgl_mulai)->format('Y-m-d'),
                    'tglSelesai'   => optional($plan?->tgl_selesai)->format('Y-m-d'),
                    'idGrading'    => $g->id_grading,
                    'jenis'        => $g->jenis,
                    'area'         => $g->area,
                    'bbnkb'        => $g->bbnkb,
                    'fraud'        => $g->fraud,
                    'totalNilai'   => $g->total_nilai,
                    'itemCount'    => count($g->details ?? []),
                    'updatedAt'    => optional($g->updated_at)->format('Y-m-d'),
                ];
            });

            // Filter client-side fields
            if ($q) {
                $q = strtolower($q);
                $rows = $rows->filter(fn($r) =>
                    str_contains(strtolower($r['cabang']), $q) ||
                    str_contains(strtolower($r['noSpt']),  $q) ||
                    str_contains(strtolower($r['area']),   $q)
                );
            }
            if ($wilayah) $rows = $rows->filter(fn($r) => strtolower($r['area'])  === strtolower($wilayah));
            if ($jenis)   $rows = $rows->filter(fn($r) => strtolower($r['jenis']) === strtolower($jenis));

            return response()->json(['data' => array_values($rows->toArray()), 'total' => $rows->count()]);
        } catch (\Exception $e) {
            return response()->json(['data' => [], 'total' => 0]);
        }
    }

    // Detail grading lengkap (untuk analisa)
    public function detail(int $id): JsonResponse
    {
        try {
            $g    = AuditGrading::findOrFail($id);
            $plan = $g->planAudit;
            return response()->json(['data' => [
                ...$g->toAktaArray(),
                'noSpt'      => $plan?->no_spt      ?? '-',
                'cabang'     => $plan?->cabang      ?? '-',
                'cabangArea' => $plan?->cabang_area ?? $g->area ?? '-',
                'jenisAudit' => $plan?->jenis_audit ?? '-',
                'tglMulai'   => optional($plan?->tgl_mulai)->format('Y-m-d'),
                'tglSelesai' => optional($plan?->tgl_selesai)->format('Y-m-d'),
            ]]);
        } catch (\Exception $e) {
            return response()->json(['data' => null], 404);
        }
    }

    // Endpoint: trigger sync PICA untuk plan tertentu (data grading yang sudah ada)
    public function syncPica(Request $request): JsonResponse
    {
        $planId = $request->input('planAuditId') ?? $request->input('plan_audit_id');
        $who    = $request->user()?->username ?? $request->user()?->email;

        $grading = AuditGrading::where('plan_audit_id', $planId)->first();
        if (!$grading) {
            return response()->json(['message' => 'Data grading tidak ditemukan.'], 404);
        }

        $this->syncPicaFromGrading($grading, $planId, $who);

        return response()->json(['message' => 'PICA berhasil disinkronisasi dari data grading.']);
    }

    /**
     * Terbitkan / segarkan baris PICA dari item grading yang sudah diisi
     * Current Condition-nya.
     *
     * Dicocokkan lewat NAMA PEMERIKSAAN, bukan nomor barisnya. Nomor baris
     * bergeser begitu auditor menghapus atau menyisipkan item di tengah, dan
     * baris PICA yang ikut bergeser akan menempel pada temuan yang salah --
     * lengkap dengan akar masalah dan rencana perbaikan milik temuan lain.
     * Nama pemeriksaan tidak pernah kembar dalam satu grading (daftar pilihan
     * memang membuang yang sudah terpakai), jadi ia kunci yang aman.
     */
    private function syncPicaFromGrading(AuditGrading $grading, mixed $planId, ?string $who): void
    {
        try {
            $plan    = PlanAudit::find($planId);
            $details = $grading->details ?? [];
            $dipakai = [];

            foreach ($details as $idx => $item) {
                $condition = trim((string) ($item['currentCondition'] ?? ''));
                if ($condition === '') {
                    continue;
                }

                $nama = $item['namaPemeriksaan'] ?? ('Item ' . ($idx + 1));

                $pica = Pica::firstOrNew([
                    'source_type' => 'grading',
                    'source_id'   => $grading->id,
                    'title'       => $nama,
                ]);

                // Status & prioritas HANYA diisi saat baris PICA baru dibuat.
                // Grading yang disimpan ulang tidak boleh menarik kembali PICA
                // yang sudah ditutup auditor menjadi "open".
                if (!$pica->exists) {
                    $pica->plan_audit_id = $planId;
                    $pica->status        = 'open';
                    $pica->priority      = 'sedang';
                    $pica->created_by    = $grading->created_by ?? $who;
                }

                $pica->current_condition = $condition;
                $pica->unit_usaha        = $plan?->cabang;
                $pica->source_item_idx   = $idx;
                $pica->updated_by        = $who;
                $pica->save();

                $dipakai[] = $pica->id;
            }

            // Item yang dicabut auditor dari grading tidak boleh meninggalkan
            // temuan gantung di daftar PICA. Tapi yang tindak lanjutnya sudah
            // dikerjakan TIDAK dihapus -- itu pekerjaan orang, bukan sisa data;
            // dibiarkan berdiri sendiri supaya bisa ditutup sebagaimana mestinya.
            Pica::query()
                ->where('source_type', 'grading')
                ->where('source_id', $grading->id)
                ->when($dipakai, fn($q) => $q->whereNotIn('id', $dipakai))
                ->get()
                ->each(function (Pica $p) {
                    if (self::picaBelumDikerjakan($p)) $p->delete();
                });

            // Auto-generate pica_no jika belum ada
            Pica::where('source_type', 'grading')
                ->where('source_id', $grading->id)
                ->whereNull('pica_no')
                ->each(function (Pica $p) {
                    $p->pica_no = 'PICA-' . now()->format('Ymd') . '-' . str_pad((string) $p->id, 4, '0', STR_PAD_LEFT);
                    $p->save();
                });
        } catch (\Throwable) {
            // Jangan gagalkan save grading hanya karena PICA sync error
        }
    }

    /** Baris PICA yang belum disentuh siapa pun selain grading yang menerbitkannya. */
    private static function picaBelumDikerjakan(Pica $p): bool
    {
        foreach (['problem', 'problem_identification', 'root_cause', 'corrective_action',
                  'preventive_action', 'pic', 'evidence', 'notes', 'close_note',
                  'target_date', 'actual_date', 'closed_at', 'audit_task_id'] as $kolom) {
            if (trim((string) ($p->$kolom ?? '')) !== '') return false;
        }

        return in_array((string) $p->status, ['', 'open'], true);
    }
}
