<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditTabConfig;
use App\Models\PlanAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditTabConfigController extends Controller
{
    // Daftar tab kanonik (key => label) dari config/audit_tabs.php
    public function tabList(): JsonResponse
    {
        $tabs = config('audit_tabs', []);

        return response()->json([
            'data' => collect($tabs)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    // Nilai jenis_audit yang sudah pernah dipakai di plan_audits, untuk pilihan admin.
    public function jenisAuditOptions(): JsonResponse
    {
        $options = PlanAudit::query()
            ->whereNotNull('jenis_audit')
            ->where('jenis_audit', '!=', '')
            ->distinct()
            ->orderBy('jenis_audit')
            ->pluck('jenis_audit');

        return response()->json(['data' => $options]);
    }

    // Ringkasan semua jenis_audit yang sudah dikonfigurasi (untuk tabel admin).
    public function index(): JsonResponse
    {
        $rows = AuditTabConfig::query()->orderBy('jenis_audit')->get();

        $grouped = $rows->groupBy('jenis_audit')->map(function ($items, $jenisAudit) {
            return [
                'jenis_audit' => $jenisAudit,
                'tabs' => $items->mapWithKeys(fn($r) => [$r->tab_key => (bool) $r->visible]),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    // Konfigurasi tab untuk satu jenis_audit tertentu. Tab yang belum ada row-nya dianggap visible=true (default).
    public function show(Request $request): JsonResponse
    {
        $jenisAudit = trim((string) $request->query('jenis_audit', ''));
        $allTabs = array_keys(config('audit_tabs', []));

        $overrides = AuditTabConfig::query()
            ->where('jenis_audit', $jenisAudit)
            ->pluck('visible', 'tab_key');

        $tabs = collect($allTabs)->mapWithKeys(fn($key) => [$key => $overrides->has($key) ? (bool) $overrides[$key] : true]);

        return response()->json([
            'jenis_audit' => $jenisAudit,
            'tabs' => $tabs,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403, 'Hanya admin yang boleh mengubah konfigurasi tab audit.');

        $allTabs = array_keys(config('audit_tabs', []));

        $data = $request->validate([
            'jenis_audit' => ['required', 'string', 'max:100'],
            'tabs' => ['required', 'array'],
            'tabs.*' => ['boolean'],
        ]);

        foreach ($data['tabs'] as $tabKey => $visible) {
            if (!in_array($tabKey, $allTabs, true)) {
                continue;
            }
            AuditTabConfig::query()->updateOrCreate(
                ['jenis_audit' => $data['jenis_audit'], 'tab_key' => $tabKey],
                ['visible' => (bool) $visible]
            );
        }

        return response()->json(['message' => 'Konfigurasi tab audit berhasil disimpan.']);
    }

    // Kembalikan ke default (semua tab tampil) untuk satu jenis_audit.
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'admin', 403, 'Hanya admin yang boleh mengubah konfigurasi tab audit.');

        $data = $request->validate(['jenis_audit' => ['required', 'string', 'max:100']]);

        AuditTabConfig::query()->where('jenis_audit', $data['jenis_audit'])->delete();

        return response()->json(['message' => 'Konfigurasi direset ke default (semua tab tampil).']);
    }
}
