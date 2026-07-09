<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanAudit;
use App\Models\SkDistribusi;
use App\Models\SuratKeputusan;
use App\Models\User;
use App\Services\SkMemutuskanExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SuratKeputusanController extends Controller
{
    private array $writeRoles = ['admin', 'manajer', 'auditor'];

    private array $approveManajerRoles = ['admin', 'manajer'];

    private array $approveAfdRoles = ['admin', 'afd'];

    public function index(Request $request): JsonResponse
    {
        $query = SuratKeputusan::query()
            ->with(['planAudit', 'pembebanan', 'distribusi'])
            ->latest('id');

        $planAuditId = $request->query('plan_audit_id')
            ?? $request->query('plan_id')
            ?? $request->query('planId')
            ?? $request->query('planAuditId');

        if ($planAuditId) {
            $query->where('plan_audit_id', $planAuditId);
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('no_sk', 'like', "%{$keyword}%")
                    ->orWhere('no_spt', 'like', "%{$keyword}%")
                    ->orWhere('unit_usaha', 'like', "%{$keyword}%")
                    ->orWhere('jenis_audit', 'like', "%{$keyword}%")
                    ->orWhere('uploaded_by_name', 'like', "%{$keyword}%");
            });
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(SuratKeputusan $suratKeputusan): JsonResponse
    {
        return response()->json([
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    // Auditor/admin mendistribusikan SK yang sudah selesai ke satu atau lebih pengguna.
    public function distribute(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        abort_unless(
            in_array($this->role($request), ['admin', 'auditor'], true),
            403,
            'Hanya admin/auditor yang boleh mendistribusikan SK.'
        );

        if ($suratKeputusan->status !== 'selesai') {
            abort(422, 'SK belum selesai, belum dapat didistribusikan.');
        }

        $data = $request->validate([
            'usernames' => ['required', 'array', 'min:1'],
            'usernames.*' => ['string'],
            'memutuskan' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($request->filled('memutuskan')) {
            $suratKeputusan->memutuskan = $data['memutuskan'];
            $suratKeputusan->save();
        }

        $users = User::query()->whereIn('username', $data['usernames'])->get(['username', 'name', 'display_name']);
        abort_if($users->isEmpty(), 422, 'Tidak ada pengguna valid yang dipilih.');

        foreach ($users as $user) {
            SkDistribusi::query()->updateOrCreate(
                [
                    'surat_keputusan_id' => $suratKeputusan->id,
                    'user_username' => $user->username,
                ],
                [
                    'user_name' => $user->display_name ?: $user->name ?: $user->username,
                    'status' => 'pending',
                    'tanggapan' => null,
                    'responded_at' => null,
                    'distributed_by' => $this->userIdentifier($request),
                    'distributed_by_name' => $this->userDisplayName($request),
                    'distributed_at' => now(),
                ]
            );
        }

        return response()->json([
            'message' => 'SK berhasil didistribusikan ke ' . $users->count() . ' pengguna.',
            'data' => $suratKeputusan->load('distribusi'),
        ]);
    }

    public function distribusiList(SuratKeputusan $suratKeputusan): JsonResponse
    {
        return response()->json([
            'data' => $suratKeputusan->distribusi()->latest('id')->get(),
        ]);
    }

    // SK yang didistribusikan ke pengguna yang sedang login
    public function myDistribusi(Request $request): JsonResponse
    {
        $username = $this->userIdentifier($request);

        $data = SkDistribusi::query()
            ->with('suratKeputusan.planAudit')
            ->where('user_username', $username)
            ->latest('id')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function tanggapi(Request $request, SkDistribusi $distribusi): JsonResponse
    {
        $username = $this->userIdentifier($request);
        abort_unless(
            $username === $distribusi->user_username || $this->role($request) === 'admin',
            403,
            'Anda tidak berwenang menanggapi distribusi SK ini.'
        );

        $data = $request->validate([
            'tanggapan' => ['nullable', 'string', 'max:1000'],
            'poin' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);

        $poin = null;
        if (!empty($data['poin'])) {
            $decoded = json_decode($data['poin'], true);
            if (is_array($decoded)) {
                $poin = array_map(fn($p) => [
                    'index' => (int) ($p['index'] ?? 0),
                    'text' => (string) ($p['text'] ?? ''),
                    'checked' => (bool) ($p['checked'] ?? false),
                    'note' => (string) ($p['note'] ?? ''),
                ], $decoded);
            }
        }

        abort_if(empty($data['tanggapan']) && empty($poin), 422, 'Tanggapan atau centang poin wajib diisi.');

        if ($poin !== null) {
            $total = count($poin);
            $checked = count(array_filter($poin, fn($p) => $p['checked']));
            $distribusi->status = $total > 0 && $checked === $total
                ? 'ditanggapi'
                : ($checked > 0 ? 'sebagian' : 'pending');
            $distribusi->tanggapan_poin = $poin;
        } else {
            $distribusi->status = 'ditanggapi';
        }

        if (!empty($data['tanggapan'])) {
            $distribusi->tanggapan = $data['tanggapan'];
        }
        $distribusi->responded_at = now();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('sk-tanggapan', 'public');
            $distribusi->file_tanggapan = [
                'name' => $file->getClientOriginalName(),
                'type' => $file->getClientMimeType(),
                'url'  => Storage::url($path),
            ];
        }

        $distribusi->save();

        return response()->json([
            'message' => 'Tanggapan berhasil disimpan.',
            'data' => $distribusi,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanWrite($request);

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, true);

        if (!empty($data['plan_audit_id'])) {
            $exists = SuratKeputusan::query()->where('plan_audit_id', $data['plan_audit_id'])->exists();
            abort_if($exists, 422, 'Plan audit ini sudah memiliki SK. Satu plan audit hanya boleh memiliki satu SK.');

            $this->fillFromPlan($data, (int) $data['plan_audit_id']);
        }

        if ($request->hasFile('file')) {
            $data['file_sk'] = $this->storeFile($request);
            if (empty($data['memutuskan'])) {
                $data['memutuskan'] = $this->extractMemutuskanFromUpload($data['file_sk']);
            }
        }

        $data['status'] = $data['status'] ?? 'pending_manajer';
        $data['steps'] = $data['steps'] ?? [];

        $data['uploaded_by'] = $this->userIdentifier($request);
        $data['uploaded_by_name'] = $this->userDisplayName($request);
        $data['uploaded_at'] = now();

        $sk = SuratKeputusan::query()->create($data);

        return response()->json([
            'message' => 'SK berhasil dibuat.',
            'data' => $sk->load('planAudit'),
        ], 201);
    }

    public function update(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $payload = $this->normalizePayload($request);
        $data = $this->validatePayload($payload, false);

        if (!empty($data['plan_audit_id'])) {
            $this->fillFromPlan($data, (int) $data['plan_audit_id']);
        }

        if ($request->hasFile('file')) {
            $data['file_sk'] = $this->storeFile($request);
            if (empty($data['memutuskan']) && empty($suratKeputusan->memutuskan)) {
                $data['memutuskan'] = $this->extractMemutuskanFromUpload($data['file_sk']);
            }
        }

        if (($data['status'] ?? null) === 'pending_afd') {
            $this->ensureCanApproveManajer($request);
        }

        if (($data['status'] ?? null) === 'selesai') {
            $this->ensureCanApproveAfd($request);
        }

        $suratKeputusan->fill($data);
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK berhasil diperbarui.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    public function destroy(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureIsAdmin($request);

        $suratKeputusan->delete();

        return response()->json([
            'ok' => true,
            'message' => 'SK berhasil dihapus.',
        ]);
    }

    public function approveManajer(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanApproveManajer($request);

        if ($suratKeputusan->status !== 'pending_manajer') {
            abort(422, 'SK tidak berada pada status pending_manajer.');
        }

        $steps = $suratKeputusan->steps ?? [];

        $steps['manajer'] = [
            'by' => $this->userIdentifier($request),
            'byName' => $this->userDisplayName($request),
            'approvedAt' => now()->toDateTimeString(),
        ];

        $suratKeputusan->status = 'pending_afd';
        $suratKeputusan->steps = $steps;
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK berhasil disetujui manajer.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    public function approveAfd(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanApproveAfd($request);

        if ($suratKeputusan->status !== 'pending_afd') {
            abort(422, 'SK tidak berada pada status pending_afd.');
        }

        $steps = $suratKeputusan->steps ?? [];

        $steps['afd'] = [
            'by' => $this->userIdentifier($request),
            'byName' => $this->userDisplayName($request),
            'approvedAt' => now()->toDateTimeString(),
        ];

        $suratKeputusan->status = 'selesai';
        $suratKeputusan->steps = $steps;
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK berhasil disetujui AFD.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    public function rejectManajer(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanApproveManajer($request);

        if ($suratKeputusan->status !== 'pending_manajer') {
            abort(422, 'SK tidak berada pada status pending_manajer.');
        }

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $steps = $suratKeputusan->steps ?? [];
        $steps['manajer'] = [
            'by' => $this->userIdentifier($request),
            'byName' => $this->userDisplayName($request),
            'rejectedAt' => now()->toDateTimeString(),
            'reason' => $request->input('reason'),
        ];

        $suratKeputusan->status = 'ditolak';
        $suratKeputusan->steps = $steps;
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK ditolak oleh manajer.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    public function rejectAfd(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        $this->ensureCanApproveAfd($request);

        if ($suratKeputusan->status !== 'pending_afd') {
            abort(422, 'SK tidak berada pada status pending_afd.');
        }

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $steps = $suratKeputusan->steps ?? [];
        $steps['afd'] = [
            'by' => $this->userIdentifier($request),
            'byName' => $this->userDisplayName($request),
            'rejectedAt' => now()->toDateTimeString(),
            'reason' => $request->input('reason'),
        ];

        $suratKeputusan->status = 'ditolak';
        $suratKeputusan->steps = $steps;
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK ditolak oleh AFD.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    // Auditor pengunggah (atau admin) mengunggah ulang SK setelah ditolak; kembali ke antrian manajer.
    public function resubmit(Request $request, SuratKeputusan $suratKeputusan): JsonResponse
    {
        if ($suratKeputusan->status !== 'ditolak') {
            abort(422, 'SK tidak berada pada status ditolak.');
        }

        $user = $request->user();
        $isUploader = $user && $this->userIdentifier($request) === $suratKeputusan->uploaded_by;
        abort_unless($isUploader || $this->role($request) === 'admin', 403, 'Hanya pengunggah SK atau admin yang boleh mengunggah ulang.');

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'no_sk' => ['nullable', 'string', 'max:120'],
        ]);

        $fileSk = $this->storeFile($request);
        $suratKeputusan->file_sk = $fileSk;
        if (empty($suratKeputusan->memutuskan)) {
            $suratKeputusan->memutuskan = $this->extractMemutuskanFromUpload($fileSk);
        }
        if ($request->filled('no_sk')) {
            $suratKeputusan->no_sk = $request->input('no_sk');
        }
        $suratKeputusan->status = 'pending_manajer';
        $suratKeputusan->uploaded_by = $this->userIdentifier($request);
        $suratKeputusan->uploaded_by_name = $this->userDisplayName($request);
        $suratKeputusan->uploaded_at = now();
        $suratKeputusan->save();

        return response()->json([
            'message' => 'SK berhasil diunggah ulang, menunggu approval manajer.',
            'data' => $suratKeputusan->load('planAudit'),
        ]);
    }

    private function validatePayload(array $payload, bool $isCreate): array
    {
        return Validator::make($payload, [
            'plan_audit_id' => ['nullable', 'integer', 'exists:plan_audits,id'],
            'no_spt' => ['nullable', 'string', 'max:80'],
            'unit_usaha' => ['nullable', 'string', 'max:150'],
            'jenis_audit' => ['nullable', 'string', 'max:80'],
            'no_sk' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:120'],
            'file_sk' => ['nullable', 'array'],
            'memutuskan' => ['nullable', 'string', 'max:5000'],
            'status' => [
                'nullable',
                'string',
                Rule::in(['pending_manajer', 'pending_afd', 'selesai', 'ditolak']),
            ],
            'steps' => ['nullable', 'array'],
        ])->validate();
    }

    private function storeFile(Request $request): array
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('sk', 'public');

        return [
            'name' => $file->getClientOriginalName(),
            'type' => 'application/pdf',
            'url'  => Storage::url($path),
            'path' => $path,
        ];
    }

    // Ekstrak poin "Memutuskan" dari PDF yang baru diupload (jika belum diisi manual).
    private function extractMemutuskanFromUpload(array $fileSk): ?string
    {
        if (empty($fileSk['path'])) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($fileSk['path']);

        return SkMemutuskanExtractor::extractFromPath($absolutePath);
    }

    private function normalizePayload(Request $request): array
    {
        $data = $request->all();

        $aliases = [
            'planId' => 'plan_audit_id',
            'plan_id' => 'plan_audit_id',
            'planAuditId' => 'plan_audit_id',
            'noSpt' => 'no_spt',
            'unitUsaha' => 'unit_usaha',
            'jenisAudit' => 'jenis_audit',
            'noSk' => 'no_sk',
            'fileSk' => 'file_sk',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && !array_key_exists($to, $data)) {
                $data[$to] = $data[$from];
            }
        }

        return $data;
    }

    private function fillFromPlan(array &$data, int $planAuditId): void
    {
        $plan = PlanAudit::query()->find($planAuditId);

        if (!$plan) {
            return;
        }

        $data['no_spt'] = $data['no_spt']
            ?? $plan->getAttribute('no_spt')
            ?? $plan->getAttribute('noSpt')
            ?? null;

        $data['unit_usaha'] = $data['unit_usaha']
            ?? $plan->getAttribute('unit_usaha')
            ?? $plan->getAttribute('unitUsaha')
            ?? $plan->getAttribute('cabang')
            ?? null;

        $data['jenis_audit'] = $data['jenis_audit']
            ?? $plan->getAttribute('jenis_audit')
            ?? $plan->getAttribute('jenisAudit')
            ?? null;
    }

    private function ensureCanWrite(Request $request): void
    {
        abort_unless(
            in_array($this->role($request), $this->writeRoles, true),
            403,
            'Role tidak diizinkan mengubah SK.'
        );
    }

    private function ensureIsAdmin(Request $request): void
    {
        abort_unless($this->role($request) === 'admin', 403, 'Hanya admin yang boleh mengedit/menghapus SK.');
    }

    private function ensureCanApproveManajer(Request $request): void
    {
        abort_unless(
            in_array($this->role($request), $this->approveManajerRoles, true),
            403,
            'Hanya admin/manajer yang boleh approve tahap manajer.'
        );
    }

    private function ensureCanApproveAfd(Request $request): void
    {
        abort_unless(
            $this->canApproveAfd($request),
            403,
            'Hanya admin/AFD yang boleh approve tahap AFD.'
        );
    }

    private function canApproveAfd(Request $request): bool
    {
        return in_array($this->role($request), $this->approveAfdRoles, true);
    }

    private function role(Request $request): string
    {
        return strtolower((string) ($request->user()?->role ?? ''));
    }

    private function userIdentifier(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return $user->username
            ?? $user->email
            ?? $user->id
            ?? null;
    }

    private function userDisplayName(Request $request): ?string
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        return $user->display_name
            ?? $user->name
            ?? $user->username
            ?? $user->email
            ?? null;
    }
}
