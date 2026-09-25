<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditRecommendation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BirokrasiResolver;
use App\Services\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuditRecommendationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user          = $request->user();
        $isInternal    = $user && in_array($user->role, ['admin', 'manajer', 'auditor']);
        $unitUsaha     = $user?->unit_usaha;
        $userRoleUpper = strtoupper($user?->role ?? '');

        // Collect all cabang names reachable by this user sebagai APPROVER
        // (bukan hanya anggota unit biasa di grup birokrasi tsb):
        // - unit_usaha matches an approver name exactly (e.g. "Retail Aceh")
        // - role (case-insensitive) matches a generic approver role (e.g. "so", "csc", "whs")
        // Unit biasa (bukan approver) hanya boleh melihat rekomendasi milik unit usahanya sendiri.
        $allowedCabang = [];
        if (!$isInternal) {
            foreach (config('birokrasi', []) as $group) {
                $approversUpper = array_map('strtoupper', $group['approvers']);
                $inApproverUnit = $unitUsaha && in_array(strtoupper($unitUsaha), $approversUpper);
                $inApproverRole = $userRoleUpper && in_array($userRoleUpper, $approversUpper);
                if ($inApproverUnit || $inApproverRole) {
                    $allowedCabang = array_merge($allowedCabang, $group['units']);
                }
            }
            $allowedCabang = array_unique($allowedCabang);
        }

        $recommendations = AuditRecommendation::query()
            ->with(['planAudit', 'auditTask'])
            // Non-internal users only see recs for cabang they are responsible for via birokrasi
            ->when(!$isInternal, function ($query) use ($allowedCabang, $unitUsaha) {
                $query->whereHas('planAudit', function ($q) use ($allowedCabang, $unitUsaha) {
                    $q->whereIn('cabang', $allowedCabang);
                    if ($unitUsaha) {
                        $q->orWhere('cabang', $unitUsaha);
                    }
                });
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = $request->query('q');

                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('judul', 'like', "%{$q}%")
                        ->orWhere('deskripsi', 'like', "%{$q}%")
                        ->orWhere('kategori', 'like', "%{$q}%")
                        ->orWhere('pic', 'like', "%{$q}%")
                        ->orWhereHas('planAudit', function ($planQuery) use ($q) {
                            $planQuery
                                ->where('no_spt', 'like', "%{$q}%")
                                ->orWhere('cabang', 'like', "%{$q}%");
                        })
                        ->orWhereHas('auditTask', function ($taskQuery) use ($q) {
                            $taskQuery->where('judul', 'like', "%{$q}%");
                        });
                });
            })
            ->when($request->filled('plan_audit_id'), function ($query) use ($request) {
                $query->where('plan_audit_id', $request->query('plan_audit_id'));
            })
            ->when($request->filled('audit_task_id'), function ($query) use ($request) {
                $query->where('audit_task_id', $request->query('audit_task_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->query('status'));
            })
            ->when($request->filled('prioritas'), function ($query) use ($request) {
                $query->where('prioritas', $request->query('prioritas'));
            })
            ->latest()
            ->get()
            ->map(fn(AuditRecommendation $recommendation) => $this->untukLayar($recommendation, $user));

        return response()->json([
            'ok' => true,
            'data' => $recommendations,
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): JsonResponse
    {
        $payload = $this->validatedPayload($request);

        if (($payload['status'] ?? 'draft') === 'done' && empty($payload['tgl_selesai'])) {
            $payload['tgl_selesai'] = now()->toDateString();
        }

        $recommendation = AuditRecommendation::query()->create([
            ...$payload,
            'steps' => $payload['steps'] ?? $this->buildBirokrasiSteps((int) ($payload['plan_audit_id'] ?? 0), $request->user()?->username ?? ''),
            'created_by' => $request->user()?->username,
            'updated_by' => $request->user()?->username,
        ]);

        $recommendation->load(['planAudit', 'auditTask']);
        NotificationDispatcher::notifyRecommendationStep($recommendation);

        $logger->write(
            $request,
            'RECOMMENDATION_CREATE',
            'audit_recommendations',
            'Membuat rekomendasi: ' . $recommendation->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil dibuat.',
            'data' => $this->untukLayar($recommendation, $request->user()),
        ], 201);
    }

    public function show(Request $request, AuditRecommendation $recommendation): JsonResponse
    {
        $recommendation->load(['planAudit', 'auditTask']);

        return response()->json([
            'ok' => true,
            'data' => $this->untukLayar($recommendation, $request->user()),
        ]);
    }

    public function update(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        $payload = $this->validatedPayload($request);

        if (($payload['status'] ?? $recommendation->status) === 'done' && empty($payload['tgl_selesai'])) {
            $payload['tgl_selesai'] = now()->toDateString();
        }

        if (($payload['status'] ?? $recommendation->status) !== 'done') {
            $payload['tgl_selesai'] = null;
        }

        $recommendation->fill([
            ...$payload,
            'steps' => $payload['steps'] ?? $recommendation->steps,
            'updated_by' => $request->user()?->username,
        ]);

        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write(
            $request,
            'RECOMMENDATION_UPDATE',
            'audit_recommendations',
            'Update rekomendasi: ' . $recommendation->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil diperbarui.',
            'data' => $this->untukLayar($recommendation, $request->user()),
        ]);
    }

    public function destroy(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        if (!$this->bolehMenghapus($request->user(), $recommendation)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Rekomendasi ini sudah diisi pihak lain, jadi tidak bisa dihapus lagi. '
                    . 'Hubungi admin kalau memang harus dihapus.',
            ], 422);
        }

        $judul = $recommendation->judul;

        $this->buangBerkasLampiran($recommendation);
        $recommendation->delete();

        $logger->write(
            $request,
            'RECOMMENDATION_DELETE',
            'audit_recommendations',
            'Menghapus rekomendasi: ' . $judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil dihapus.',
        ]);
    }

    public function approve(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        $recommendation->fill([
            'status' => 'approved',
            'approved_by' => $request->user()?->username,
            'approved_at' => now(),
            'updated_by' => $request->user()?->username,
        ]);

        $steps = $recommendation->steps ?: [];
        $steps[] = [
            'step' => 'approval',
            'status' => 'approved',
            'user' => $request->user()?->username,
            'role' => $request->user()?->role,
            'time' => now()->toDateTimeString(),
            'note' => 'Rekomendasi disetujui.',
        ];

        $recommendation->steps = $steps;
        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write(
            $request,
            'RECOMMENDATION_APPROVE',
            'audit_recommendations',
            'Approve rekomendasi: ' . $recommendation->judul,
            $request->user()
        );

        return response()->json([
            'ok' => true,
            'message' => 'Rekomendasi berhasil disetujui.',
            'data' => $this->untukLayar($recommendation, $request->user()),
        ]);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'plan_audit_id' => ['nullable', 'integer', 'exists:plan_audits,id'],
            'audit_task_id' => ['nullable', 'integer', 'exists:audit_tasks,id'],
            'judul' => ['required', 'string', 'max:300'],
            'deskripsi' => ['nullable', 'string'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'prioritas' => [
                'required',
                'string',
                Rule::in(['rendah', 'sedang', 'tinggi', 'urgent']),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(['draft', 'open', 'in_progress', 'waiting_approval', 'approved', 'done', 'cancelled']),
            ],
            'pic' => ['nullable', 'string', 'max:150'],
            'deadline' => ['nullable', 'date'],
            'tgl_selesai' => ['nullable', 'date'],
            'steps' => ['nullable', 'array'],
        ]);
    }

    public function isi(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        $request->validate([
            'tgl_isi' => ['required', 'date'],
            'isi'     => ['required', 'string'],
        ]);

        // Isian Unit Usaha adalah tanggapan UNIT USAHA YANG DIAUDIT atas
        // rekomendasi auditor -- sama seperti Keputusan Bertahap, yang mengisi
        // adalah pihaknya sendiri. Dulu admin/manajer/auditor boleh mengisinya
        // untuk unit usaha mana pun, jadi auditor ditawari menuliskan tanggapan
        // atas nama cabang yang baru saja diperiksanya. Admin tetap bisa
        // menimpa, sebagai jalur darurat.
        $user = $request->user();

        if (!$this->bolehMengisiIsianUnitUsaha($user, $recommendation)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Isian ini hanya boleh ditulis oleh unit usaha yang diperiksa.',
            ], 403);
        }

        // Isian yang sudah tersimpan hanya boleh diubah oleh admin
        $sudahDiisi = collect($recommendation->steps ?: [])->contains(fn($s) => ($s['step'] ?? '') === 'isi_rekomendasi');
        if ($sudahDiisi && $user?->role !== 'admin') {
            return response()->json(['ok' => false, 'message' => 'Isian sudah tersimpan. Perubahan hanya dapat dilakukan oleh admin.'], 403);
        }

        $steps   = $recommendation->steps ?: [];
        $steps[] = [
            'step'   => 'isi_rekomendasi',
            'role'   => 'unit_usaha',
            'status' => 'done',
            'user'   => $request->user()?->username,
            'time'   => $request->input('tgl_isi'),
            'note'   => $request->input('isi'),
        ];

        $recommendation->steps      = $steps;
        $recommendation->updated_by = $request->user()?->username;
        if ($recommendation->status === 'open') {
            $recommendation->status = 'in_progress';
        }
        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write($request, 'RECOMMENDATION_ISI', 'audit_recommendations',
            'Isi rekomendasi: ' . $recommendation->judul, $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Isi rekomendasi berhasil disimpan.',
            'data'    => $this->untukLayar($recommendation, $request->user()),
        ]);
    }

    public function approveStep(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        $request->validate([
            'step_index' => ['required', 'integer', 'min:0'],
            'note'       => ['nullable', 'string'],
            'tgl_isi'    => ['nullable', 'date'],
        ]);

        $idx   = (int) $request->input('step_index');
        $steps = $recommendation->steps ?: [];

        if (!isset($steps[$idx])) {
            return response()->json(['ok' => false, 'message' => 'Step tidak ditemukan.'], 404);
        }

        // Tiap pihak menuliskan keputusannya SENDIRI -- itu seluruh gunanya
        // Keputusan Bertahap. Siapa pemilik sebuah step ditentukan di satu
        // tempat saja, BirokrasiResolver::bolehMengisiStep(), yang dipakai juga
        // oleh layar (penanda bisaDiisi) dan oleh pemilihan penerima notifikasi.
        //
        // Dulu di sini ada jalan pintas tersendiri: admin/manajer/auditor boleh
        // mengisi step APA PUN kecuali "AFD". Akibatnya auditor bisa menuliskan
        // keputusan atas nama FIN REG, REG HEAD, atau unit usaha. Sekarang
        // tinggal admin yang boleh menimpa, sebagai jalur darurat.
        $user     = $request->user();
        $stepRole = $steps[$idx]['role'] ?? $steps[$idx]['step'] ?? '';
        $cabang   = $recommendation->planAudit?->cabang ?? '';

        $isAdmin = $user?->role === 'admin';

        if (!$isAdmin && !BirokrasiResolver::bolehMengisiStep($user, $stepRole, $cabang)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Step "' . $stepRole . '" hanya boleh diisi oleh pihak yang bersangkutan.',
            ], 403);
        }

        // Membetulkan isian sendiri boleh, SELAMA bagian berikutnya belum
        // mengisi -- begitu pihak setelahnya menuliskan keputusannya, keputusan
        // ini sudah jadi dasar pertimbangan mereka dan tidak boleh berubah lagi
        // di belakang mereka. Admin tetap bisa membetulkan bagian mana pun.
        $pembetulan = $this->sudahDiisi($steps[$idx]);

        if ($pembetulan && !$isAdmin && !$this->bolehDiubah($recommendation, $steps, $idx)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Keputusan ini sudah terkunci karena bagian berikutnya sudah mengisi. '
                    . 'Hubungi admin kalau memang harus diubah.',
            ], 422);
        }

        $steps[$idx]['status'] = 'done';
        $steps[$idx]['user']   = $request->user()?->username;
        $steps[$idx]['time']   = $request->input('tgl_isi') ?? now()->toDateString();
        $steps[$idx]['note']   = $request->input('note');

        // Auto-approve overall recommendation when last step is approved
        $allApproved = collect($steps)->every(fn($s) => in_array($s['status'], ['done', 'approved']));

        $recommendation->steps = $steps;
        if ($allApproved) {
            $recommendation->status      = 'approved';
            $recommendation->approved_by = $request->user()?->username;
            $recommendation->approved_at = now();
        }
        $recommendation->updated_by = $request->user()?->username;
        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        NotificationDispatcher::resolveRecommendationStep($recommendation, $idx);
        NotificationDispatcher::notifyRecommendationStep($recommendation);

        $logger->write($request, 'RECOMMENDATION_STEP_APPROVE', 'audit_recommendations',
            'Approve step "' . ($steps[$idx]['step'] ?? $idx) . '" pada rekomendasi: ' . $recommendation->judul,
            $request->user());

        return response()->json([
            'ok'      => true,
            'message' => $pembetulan ? 'Keputusan berhasil diperbarui.' : 'Step berhasil disetujui.',
            'data'    => $this->untukLayar($recommendation, $request->user()),
        ]);
    }

    /**
     * Unggah (atau ganti) berkas lampiran rekomendasi.
     *
     * Form Rekomendasi sudah lama punya "Upload File Lampiran", tapi berkasnya
     * tidak pernah dikirim ke mana pun: layar hanya mengambil NAMANYA lalu
     * menempelkannya sebagai teks di akhir deskripsi ("Lampiran: REKAP
     * SELISIH.pdf"). Jadi yang tersimpan cuma tulisan, berkasnya hilang --
     * itulah sebabnya lampirannya tidak pernah bisa dibuka.
     */
    public function unggahLampiran(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        if (!$this->adaKolomLampiran()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Struktur database belum punya kolom lampiran. '
                    . 'Jalankan pembaruan struktur database (/deploy/migrate) lebih dulu.',
            ], 422);
        }

        $request->validate([
            'lampiran' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ], [
            'lampiran.required' => 'Berkas lampiran belum dipilih.',
            'lampiran.max'      => 'Ukuran lampiran melebihi batas 10MB.',
            'lampiran.mimes'    => 'Lampiran harus PDF, gambar, Word, atau Excel.',
        ]);

        $berkas = $request->file('lampiran');

        $this->buangBerkasLampiran($recommendation);

        $recommendation->lampiran_path = $berkas->store('lampiran-rekomendasi', 'public');
        $recommendation->lampiran_nama = $berkas->getClientOriginalName();
        $recommendation->updated_by    = $request->user()?->username;
        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write($request, 'RECOMMENDATION_LAMPIRAN', 'audit_recommendations',
            'Unggah lampiran "' . $recommendation->lampiran_nama . '" pada rekomendasi: ' . $recommendation->judul,
            $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Lampiran berhasil diunggah.',
            'data'    => $this->untukLayar($recommendation, $request->user()),
        ]);
    }

    public function hapusLampiran(
        Request $request,
        AuditRecommendation $recommendation,
        ActivityLogger $logger
    ): JsonResponse {
        if (!$this->adaKolomLampiran() || !$recommendation->lampiran_path) {
            return response()->json(['ok' => false, 'message' => 'Tidak ada lampiran pada rekomendasi ini.'], 404);
        }

        $nama = $recommendation->lampiran_nama;

        $this->buangBerkasLampiran($recommendation);
        $recommendation->lampiran_path = null;
        $recommendation->lampiran_nama = null;
        $recommendation->updated_by    = $request->user()?->username;
        $recommendation->save();
        $recommendation->load(['planAudit', 'auditTask']);

        $logger->write($request, 'RECOMMENDATION_LAMPIRAN_HAPUS', 'audit_recommendations',
            'Hapus lampiran "' . $nama . '" pada rekomendasi: ' . $recommendation->judul,
            $request->user());

        return response()->json([
            'ok'      => true,
            'message' => 'Lampiran berhasil dihapus.',
            'data'    => $this->untukLayar($recommendation, $request->user()),
        ]);
    }

    /** Buang berkas lama dari penyimpanan supaya tidak menumpuk tanpa pemilik. */
    private function buangBerkasLampiran(AuditRecommendation $recommendation): void
    {
        if ($recommendation->lampiran_path && Storage::disk('public')->exists($recommendation->lampiran_path)) {
            Storage::disk('public')->delete($recommendation->lampiran_path);
        }
    }

    /** Hosting bisa saja belum menjalankan migrasinya. */
    private function adaKolomLampiran(): bool
    {
        return Schema::hasColumn('audit_recommendations', 'lampiran_path');
    }

    /**
     * Bolehkah user ini menulis Isian Unit Usaha pada rekomendasi ini?
     *
     * Hanya unit usaha yang diperiksa (cabang milik plan) -- ditambah admin
     * sebagai jalur darurat.
     */
    private function bolehMengisiIsianUnitUsaha(?User $user, AuditRecommendation $recommendation): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        $cabang = strtoupper(trim((string) ($recommendation->planAudit?->cabang ?? '')));
        $unit   = strtoupper(trim((string) $user->unit_usaha));

        return $cabang !== '' && $unit === $cabang;
    }

    /**
     * Bolehkah user ini menghapus rekomendasi ini?
     *
     * Auditor boleh membuang rekomendasi yang salah SELAMA belum ada satu pihak
     * pun yang mengisi -- begitu birokrasinya berjalan, isian pihak lain ikut
     * terbawa kalau rekomendasinya dihapus. Admin tetap bisa kapan saja.
     */
    private function bolehMenghapus(?User $user, AuditRecommendation $recommendation): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'auditor') {
            return false;
        }

        return !$this->adaIsianPihakLain($recommendation);
    }

    /** Sudahkah ada pihak yang mengisi (keputusan bertahap atau isian unit usaha)? */
    private function adaIsianPihakLain(AuditRecommendation $recommendation): bool
    {
        foreach ($recommendation->steps ?: [] as $step) {
            $step = (array) $step;

            if (($step['step'] ?? '') === 'created') {
                continue;
            }

            if ($this->sudahDiisi($step)) {
                return true;
            }
        }

        return false;
    }

    /** Sudahkah sebuah step diisi? */
    private function sudahDiisi(mixed $step): bool
    {
        return in_array(((array) $step)['status'] ?? '', ['done', 'approved'], true);
    }

    /**
     * Bolehkah pemiliknya masih membetulkan isian step ini?
     *
     * Syaratnya: rekomendasinya belum selesai, DAN bagian setelahnya belum
     * mengisi. Yang dihitung "bagian setelahnya" adalah step birokrasi
     * berikutnya -- step teknis 'created' dan 'isi_rekomendasi' dilewati,
     * karena 'isi_rekomendasi' ditambahkan di UJUNG array (lihat isi()), bukan
     * pada urutan gilirannya, jadi memakai indeks berikutnya begitu saja akan
     * salah menilai step terakhir.
     *
     * @param  array<int, mixed>  $steps
     */
    private function bolehDiubah(AuditRecommendation $recommendation, array $steps, int $idx): bool
    {
        if (in_array($recommendation->status, ['approved', 'done', 'cancelled'], true)) {
            return false;
        }

        foreach ($steps as $i => $step) {
            if ($i <= $idx) {
                continue;
            }

            $nama = ((array) $step)['step'] ?? '';
            if ($nama === 'created' || $nama === 'isi_rekomendasi') {
                continue;
            }

            return !$this->sudahDiisi($step);
        }

        // Tidak ada bagian setelahnya: ini keputusan TERAKHIR (AFD). Terkunci
        // begitu diisi -- tidak ada pihak berikutnya yang bisa jadi penanda
        // "sudah jadi dasar pertimbangan orang", dan keputusan penutup memang
        // tidak semestinya bisa diubah sendiri sesudah dijatuhkan. Hanya admin
        // yang bisa membetulkannya.
        return false;
    }

    /**
     * Bentuk rekomendasi untuk layar: sama dengan toAktaArray(), tapi tiap step
     * diberi penanda bisaDiisi -- boleh tidaknya PENGGUNA INI mengisi step itu.
     *
     * Dihitung di server dengan BirokrasiResolver::bolehMengisiStep(), aturan
     * yang sama persis dengan yang dipakai approveStep(). Tanpa ini layar harus
     * menyalin ulang aturannya, dan menyalin aturan ke dua tempat justru yang
     * membuat tombol "Isi Keputusan" muncul untuk pihak yang tidak berhak.
     *
     * @return array<string, mixed>
     */
    private function untukLayar(AuditRecommendation $recommendation, ?User $user): array
    {
        $data    = $recommendation->toAktaArray();
        $cabang  = $recommendation->planAudit?->cabang ?? '';
        $isAdmin = $user?->role === 'admin';

        $semua = array_values($data['steps'] ?? []);

        $data['steps'] = array_map(function ($step, $idx) use ($user, $cabang, $isAdmin, $recommendation, $semua) {
            $step  = (array) $step;
            $peran = $step['role'] ?? $step['step'] ?? '';
            $milik = $isAdmin || BirokrasiResolver::bolehMengisiStep($user, $peran, $cabang);

            $step['bisaDiisi']  = $milik;
            $step['bisaDiubah'] = $milik
                && $this->sudahDiisi($step)
                && ($isAdmin || $this->bolehDiubah($recommendation, $semua, $idx));

            return $step;
        }, $semua, array_keys($semua));

        $data['bisaDihapus']        = $this->bolehMenghapus($user, $recommendation);
        $data['bisaIsiUnitUsaha']   = $this->bolehMengisiIsianUnitUsaha($user, $recommendation);

        return $data;
    }

    private function buildBirokrasiSteps(int $planAuditId, string $username): array
    {
        $cabang = '';
        if ($planAuditId) {
            $plan   = \App\Models\PlanAudit::find($planAuditId);
            $cabang = $plan?->cabang ?? '';
        }
        return BirokrasiResolver::buildSteps($cabang, $username);
    }

    private function defaultSteps(?string $username): array
    {
        return [
            [
                'step'   => 'created',
                'role'   => null,
                'status' => 'done',
                'user'   => $username,
                'time'   => now()->toDateTimeString(),
                'note'   => 'Rekomendasi dibuat.',
            ],
        ];
    }
}
