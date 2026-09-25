<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditRecommendation;
use App\Models\Pica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PicaController extends Controller
{

    // Tahap 1 -- milik auditor/kantor pusat. Cabang tidak boleh mengubahnya.
    private const KOLOM_PUSAT = ['title', 'current_condition', 'notes', 'unit_usaha'];

    // Tahap 2 -- milik unit usaha pemilik PICA. Pihak Relation Ship tidak boleh
    // menimpanya; dulu isian ini hilang karena tanggapan menumpang di kolom yang sama.
    private const KOLOM_CABANG = [
        'problem_identification', 'corrective_action', 'pic',
        'relation_ship', 'relation_ship2', 'target_date',
    ];

    // Tahap 3 -- milik pihak Relation Ship.
    private const KOLOM_TANGGAPAN = ['tanggapan_pica'];

    // Role kantor pusat yang boleh mengubah PICA. Cabang tidak didaftar di sini
    // -- haknya dihitung oleh isCabang(), lihat keterangannya di bawah. Pihak
    // Relation Ship ikut boleh lewat forwarded_to_unit, apa pun role-nya.
    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    private array $closeRoles = ['admin', 'manajer'];

    // Role kantor pusat (HO) yang boleh melihat semua unit usaha.
    private const HO_ROLES = ['admin', 'manajer', 'auditor', 'koordinator', 'coo'];

    /**
     * Apakah pengguna ini berperan sebagai CABANG -- yang mengisi Problem
     * Identification, Corrective Action, PIC, Relation Ship, dan Target Date?
     *
     * Sengaja BUKAN daftar nama role. Role bisa ditambah sendiri lewat panel
     * Kelola Role, dan daftar tetap ['h1','h2','unit','bpk'] membuat tiap role
     * baru terkunci separuh jalan: akun WHS PART AVIAN (role "whs") bisa
     * MELIHAT PICA unit usahanya -- index() memang sudah memakai aturan "bukan
     * HO" -- tapi begitu mengisi bagian yang jelas berlabel "(diisi cabang)",
     * jawabannya 403 "Role tidak diizinkan mengubah PICA". Jadi aturannya
     * disamakan dengan yang sudah dipakai index(): siapa pun yang bukan kantor
     * pusat dan punya unit usaha berperan sebagai cabang.
     */
    private function isCabang(?string $role, ?string $unitUsaha): bool
    {
        return !in_array($role, self::HO_ROLES, true)
            && trim((string) $unitUsaha) !== '';
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Pica::query()
            ->with(['recommendation', 'plan', 'task'])
            ->latest('id');

        // Role cabang (unit usaha, H1/H2/WHS) hanya boleh melihat PICA unit
        // usahanya sendiri -- ditambah PICA yang diteruskan kepadanya sebagai
        // pihak Relation Ship, yang plan-nya justru milik cabang lain.
        if (!in_array($user?->role, self::HO_ROLES, true)) {
            $unitUsaha = $user?->unit_usaha;
            $adaKolomTerusan = \Illuminate\Support\Facades\Schema::hasColumn('picas', 'forwarded_to_unit');

            $query->where(function ($q) use ($unitUsaha, $adaKolomTerusan) {
                $q->whereHas('plan', fn($p) => $p->where('cabang', $unitUsaha))
                    ->orWhere('unit_usaha', $unitUsaha);

                if ($adaKolomTerusan) {
                    $q->orWhere('forwarded_to_unit', $unitUsaha);
                }
            });
        }

        $recommendationId = $request->query('audit_recommendation_id')
            ?? $request->query('recommendation_id')
            ?? $request->query('recommendationId');

        $planAuditId = $request->query('plan_audit_id')
            ?? $request->query('plan_id')
            ?? $request->query('planId');

        $auditTaskId = $request->query('audit_task_id')
            ?? $request->query('task_id')
            ?? $request->query('taskId');

        if ($recommendationId) {
            $query->where('audit_recommendation_id', $recommendationId);
        }

        if ($planAuditId) {
            $query->where('plan_audit_id', $planAuditId);
        }

        if ($auditTaskId) {
            $query->where('audit_task_id', $auditTaskId);
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('priority') && $request->query('priority') !== 'all') {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->filled('prioritas') && $request->query('prioritas') !== 'all') {
            $query->where('priority', $request->query('prioritas'));
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('pica_no', 'like', "%{$keyword}%")
                    ->orWhere('title', 'like', "%{$keyword}%")
                    ->orWhere('problem', 'like', "%{$keyword}%")
                    ->orWhere('root_cause', 'like', "%{$keyword}%")
                    ->orWhere('corrective_action', 'like', "%{$keyword}%")
                    ->orWhere('preventive_action', 'like', "%{$keyword}%")
                    ->orWhere('pic', 'like', "%{$keyword}%")
                    ->orWhere('notes', 'like', "%{$keyword}%");
            });
        }

        return response()->json($query->get());
    }

    public function show(Pica $pica): JsonResponse
    {
        return response()->json(
            $pica->load(['recommendation', 'plan', 'task'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request);

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, true);

        if (!empty($data['audit_recommendation_id'])) {
            $recommendation = AuditRecommendation::query()
                ->findOrFail($data['audit_recommendation_id']);

            $data['plan_audit_id'] = $recommendation->getAttribute('plan_audit_id')
                ?? $recommendation->getAttribute('plan_id')
                ?? null;

            $data['audit_task_id'] = $recommendation->getAttribute('audit_task_id')
                ?? $recommendation->getAttribute('task_id')
                ?? null;
        }

        $data['created_by'] = $this->userName($request);
        $data['status'] = $data['status'] ?? 'open';
        $data['priority'] = $data['priority'] ?? 'sedang';

        $pica = Pica::query()->create($this->kolomYangAdaDiTabel($data));

        if (!$pica->pica_no) {
            $pica->pica_no = 'PICA-' . now()->format('Ymd') . '-' . str_pad((string) $pica->id, 4, '0', STR_PAD_LEFT);
            $pica->save();
        }

        return response()->json(
            $pica->load(['recommendation', 'plan', 'task']),
            201
        );
    }

    public function update(Request $request, Pica $pica): JsonResponse
    {
        $this->ensureCanWrite($request, $pica);

        if ($pica->status === 'closed' && !$this->canClose($request)) {
            abort(403, 'PICA sudah closed. Hanya admin/manajer yang boleh mengubah.');
        }

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, false);

        if (($data['status'] ?? null) === 'closed') {
            $this->ensureCanClose($request);
        }

        if (array_key_exists('audit_recommendation_id', $data) && !empty($data['audit_recommendation_id'])) {
            $recommendation = AuditRecommendation::query()
                ->findOrFail($data['audit_recommendation_id']);

            $data['plan_audit_id'] = $recommendation->getAttribute('plan_audit_id')
                ?? $recommendation->getAttribute('plan_id')
                ?? null;

            $data['audit_task_id'] = $recommendation->getAttribute('audit_task_id')
                ?? $recommendation->getAttribute('task_id')
                ?? null;
        }

        $data['updated_by'] = $this->userName($request);

        // Jika cabang menyimpan dan relation_ship sudah diisi → cari unit_usaha pihak terkait
        $role = $this->role($request);

        $userUnit = $request->user()?->unit_usaha;

        // Unit usaha pemilik PICA selalu berperan sebagai cabang, tidak pernah
        // sebagai pihak Relation Ship -- meski namanya sendiri yang ditulis di
        // kolom Relation Ship.
        $isPemilik = (bool) ($userUnit && $pica->unit_usaha === $userUnit);

        $isForwarded = !$isPemilik && $userUnit && (
            $pica->forwarded_to_unit === $userUnit ||
            ($pica->relation_ship && str_contains($pica->relation_ship, $userUnit)) ||
            ($pica->relation_ship2 && str_contains($pica->relation_ship2, $userUnit))
        );

        $isCabang = $this->isCabang($role, $userUnit);

        $data = $this->kolomYangBolehDiubah($data, $isCabang, $isForwarded);

        $relationShip = $data['relation_ship'] ?? $pica->relation_ship;

        // Jika pihak Relation Ship yang menyimpan, tandai sudah diisi
        if ($isForwarded) {
            $data['forwarded_filled_at'] = now();
        }

        if (($isCabang || $isForwarded) && !empty($relationShip)) {
            // Parse nama dari format "Nama (unit_usaha)" atau cari user langsung
            $forwardedUnit = null;
            preg_match('/\(([^)]+)\)$/', $relationShip, $m);
            if (!empty($m[1])) {
                $forwardedUnit = trim($m[1]);
            } else {
                // Cari user berdasarkan nama
                $u = \App\Models\User::where('name', $relationShip)
                    ->orWhere('username', $relationShip)
                    ->first();
                $forwardedUnit = $u?->unit_usaha;
            }
            if ($forwardedUnit) $data['forwarded_to_unit'] = $forwardedUnit;

            // Auto progress jika masih open
            if ($pica->status === 'open' || ($data['status'] ?? $pica->status) === 'open') {
                $data['status'] = 'progress';
            }
        }

        // Kolom yang belum ada di database dibuang lebih dulu; kalau dipaksakan,
        // yang sampai ke layar cuma "Server Error".
        $pica->fill($this->kolomYangAdaDiTabel($data));
        $pica->save();

        $forwarded = !empty($relationShip) && $isCabang && !$isForwarded;
        $message   = $forwarded
            ? "PICA berhasil disimpan dan diteruskan ke: {$relationShip}."
            : 'PICA berhasil disimpan.';

        return response()->json([
            'message' => $message,
            'forwarded_to' => $forwarded ? $relationShip : null,
            ...$pica->load(['recommendation', 'plan', 'task'])->toArray(),
        ]);
    }

    public function destroy(Request $request, Pica $pica): JsonResponse
    {
        if ($pica->status === 'closed') {
            $this->ensureCanClose($request);
        } else {
            $this->ensureCanWrite($request);
        }

        $pica->delete();

        return response()->json([
            'ok' => true,
            'message' => 'PICA berhasil dihapus.',
        ]);
    }

    public function close(Request $request, Pica $pica): JsonResponse
    {
        $this->ensureCanClose($request);

        $payload = $this->normalizePayload($request);

        $data = Validator::make($payload, [
            'actual_date' => ['nullable', 'date'],
            'close_note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $pica->status = 'closed';
        $pica->actual_date = $data['actual_date'] ?? now()->toDateString();
        $pica->closed_by = $this->userName($request);
        $pica->closed_at = now();
        $pica->close_note = $data['close_note'] ?? $data['notes'] ?? null;
        $pica->updated_by = $this->userName($request);
        $pica->save();

        return response()->json(
            $pica->load(['recommendation', 'plan', 'task'])
        );
    }

    /**
     * Buang kolom milik tahap lain.
     *
     * PICA diisi bergiliran: auditor, unit usaha, lalu pihak Relation Ship.
     * Tanpa batas ini, isian satu tahap bisa hilang tertimpa tahap berikutnya
     * -- yang persis terjadi pada Problem Identification milik unit usaha.
     */
    private function kolomYangBolehDiubah(array $data, bool $isCabang, bool $isForwarded): array
    {
        if ($isForwarded) {
            return array_diff_key($data, array_flip([...self::KOLOM_PUSAT, ...self::KOLOM_CABANG]));
        }

        if ($isCabang) {
            return array_diff_key($data, array_flip([...self::KOLOM_PUSAT, ...self::KOLOM_TANGGAPAN]));
        }

        return $data;
    }

    /**
     * Sisakan hanya kolom yang benar-benar ada di tabel.
     *
     * Beberapa kolom alur PICA (tanggapan, re-chek) baru dibuat lewat migration
     * terbaru. Kalau hosting belum menjalankannya, menyimpan kolom itu bikin
     * seluruh simpanan gagal dengan "Server Error" -- padahal sisanya baik-baik
     * saja. Lebih baik kolom barunya yang dilewati.
     */
    private function kolomYangAdaDiTabel(array $data): array
    {
        $kolom = \Illuminate\Support\Facades\Schema::getColumnListing('picas');

        return array_intersect_key($data, array_flip($kolom));
    }

    private function validatePayload(array $payload, bool $isCreate): array
    {
        $rules = [
            'audit_recommendation_id' => [
                'nullable',
                'integer',
                'exists:audit_recommendations,id',
            ],
            'pica_no' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:200'],
            'problem' => ['nullable', 'string'],
            'current_condition' => ['nullable', 'string'],
            'problem_identification' => ['nullable', 'string'],
            'tanggapan_pica' => ['nullable', 'string'],
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'preventive_action' => ['nullable', 'string'],
            'pic' => ['nullable', 'string', 'max:150'],
            'relation_ship' => ['nullable', 'string', 'max:150'],
            'relation_ship2' => ['nullable', 'string', 'max:150'],
            'priority' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'target_date' => ['nullable', 'date'],
            'actual_date' => ['nullable', 'date'],
            'evidence' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'unit_usaha' => ['nullable', 'string', 'max:150'],
            'forwarded_to_unit' => ['nullable', 'string', 'max:150'],
            'recheck_note' => ['nullable', 'string'],
            'recheck_deadline' => ['nullable', 'date'],
            'recheck_file' => ['nullable', 'string', 'max:255'],
            'recheck_at' => ['nullable', 'date'],
        ];

        return Validator::make($payload, $rules)->validate();
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->all();

        $aliases = [
            'recommendationId' => 'audit_recommendation_id',
            'recommendation_id' => 'audit_recommendation_id',
            'planId' => 'plan_audit_id',
            'plan_id' => 'plan_audit_id',
            'taskId' => 'audit_task_id',
            'task_id' => 'audit_task_id',
            'picaNo' => 'pica_no',
            'rootCause' => 'root_cause',
            'correctiveAction' => 'corrective_action',
            'tanggapanPica' => 'tanggapan_pica',
            'preventiveAction' => 'preventive_action',
            'targetDate' => 'target_date',
            'actualDate' => 'actual_date',
            'closeNote' => 'close_note',
            'prioritas' => 'priority',
            'unitUsaha' => 'unit_usaha',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && !array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function ensureCanWrite(Request $request, ?Pica $pica = null): void
    {
        $userUnit = $request->user()?->unit_usaha;

        $canWrite = in_array($this->role($request), $this->writeRoles, true)
            || $this->isCabang($this->role($request), $userUnit);

        // Also allow forwarded party (any role) to update the PICA
        if (!$canWrite && $pica) {
            $canWrite = $userUnit && $pica->forwarded_to_unit === $userUnit;
        }

        abort_unless($canWrite, 403, 'Role tidak diizinkan mengubah PICA.');
    }

    private function ensureCanClose(Request $request): void
    {
        abort_unless(
            $this->canClose($request),
            403,
            'Hanya admin/manajer yang boleh close PICA.'
        );
    }

    private function canClose(Request $request): bool
    {
        return in_array($this->role($request), $this->closeRoles, true);
    }

    /**
     * Unggah berkas recheck untuk satu PICA.
     *
     * Sebelumnya ini closure di routes/api.php — dipindahkan ke controller agar
     * route cache tidak perlu menyimpan closure ter-serialisasi.
     */
    public function uploadRecheck(Request $request, Pica $pica): JsonResponse
    {
        $request->validate(['file' => ['nullable', 'file', 'max:10240']]);

        if ($request->hasFile('file')) {
            $pica->recheck_file = $request->file('file')->store('pica-recheck', 'public');
        }

        // Hanya timpa jika nilai baru tidak kosong (proteksi data lama)
        $note = $request->input('recheck_note');
        if (!is_null($note) && $note !== '') {
            $pica->recheck_note = $note;
        }

        $deadline = $request->input('recheck_deadline');
        if (!is_null($deadline) && $deadline !== '') {
            $pica->recheck_deadline = $deadline;
        }

        if (!$pica->recheck_at) {
            $pica->recheck_at = now();
        }

        $pica->save();

        return response()->json([
            'ok'   => true,
            'path' => $pica->recheck_file,
            'url'  => $pica->recheck_file ? asset('storage/' . $pica->recheck_file) : null,
        ]);
    }

    private function role(Request $request): string
    {
        return strtolower((string) ($request->user()?->role ?? ''));
    }

    private function userName(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return $user->username
            ?? $user->display_name
            ?? $user->name
            ?? $user->email
            ?? null;
    }
}
