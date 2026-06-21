<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditRecommendation;
use App\Models\AuditTask;
use App\Models\Pica;
use App\Models\PlanAudit;
use App\Models\SuratKeputusan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlanAudit::query()
            ->latest('id');

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('no_spt', 'like', "%{$keyword}%")
                    ->orWhere('cabang', 'like', "%{$keyword}%")
                    ->orWhere('jenis_audit', 'like', "%{$keyword}%")
                    ->orWhere('status', 'like', "%{$keyword}%");
            });
        }

        $plans = $query->get();

        $data = $plans->map(function (PlanAudit $plan) {
            return $this->buildPlanSummary($plan);
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function show(PlanAudit $plan): JsonResponse
    {
        $tasks = AuditTask::query()
            ->where('plan_audit_id', $plan->id)
            ->latest('id')
            ->get();

        $recommendations = AuditRecommendation::query()
            ->where('plan_audit_id', $plan->id)
            ->latest('id')
            ->get();

        $picas = Pica::query()
            ->where('plan_audit_id', $plan->id)
            ->with(['recommendation', 'task'])
            ->latest('id')
            ->get();

        $suratKeputusan = SuratKeputusan::query()
            ->where('plan_audit_id', $plan->id)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'plan' => $this->normalizePlan($plan),
                'summary' => $this->buildSummaryCounts($plan),
                'tasks' => $tasks,
                'recommendations' => $recommendations,
                'picas' => $picas,
                'surat_keputusan' => $suratKeputusan,
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $plans = PlanAudit::query()
            ->latest('id')
            ->get();

        $totalPlans = $plans->count();

        $taskTotal = AuditTask::query()->count();
        $recommendationTotal = AuditRecommendation::query()->count();
        $picaTotal = Pica::query()->count();
        $skTotal = SuratKeputusan::query()->count();

        return response()->json([
            'data' => [
                'plan_total' => $totalPlans,

                'task_total' => $taskTotal,
                'task_open' => AuditTask::query()->where('status', 'open')->count(),
                'task_progress' => AuditTask::query()->whereIn('status', ['progress', 'in_progress'])->count(),
                'task_done' => AuditTask::query()->whereIn('status', ['done', 'selesai', 'completed'])->count(),

                'recommendation_total' => $recommendationTotal,
                'recommendation_waiting_approval' => AuditRecommendation::query()->where('status', 'waiting_approval')->count(),
                'recommendation_approved' => AuditRecommendation::query()->where('status', 'approved')->count(),

                'pica_total' => $picaTotal,
                'pica_open' => Pica::query()->where('status', 'open')->count(),
                'pica_progress' => Pica::query()->where('status', 'progress')->count(),
                'pica_closed' => Pica::query()->where('status', 'closed')->count(),

                'sk_total' => $skTotal,
                'sk_pending_manajer' => SuratKeputusan::query()->where('status', 'pending_manajer')->count(),
                'sk_pending_afd' => SuratKeputusan::query()->where('status', 'pending_afd')->count(),
                'sk_selesai' => SuratKeputusan::query()->where('status', 'selesai')->count(),

                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    private function buildPlanSummary(PlanAudit $plan): array
    {
        return [
            'plan' => $this->normalizePlan($plan),
            'summary' => $this->buildSummaryCounts($plan),
        ];
    }

    private function buildSummaryCounts(PlanAudit $plan): array
    {
        $planId = $plan->id;

        $taskTotal = AuditTask::query()
            ->where('plan_audit_id', $planId)
            ->count();

        $taskDone = AuditTask::query()
            ->where('plan_audit_id', $planId)
            ->whereIn('status', ['done', 'selesai', 'completed'])
            ->count();

        $recommendationTotal = AuditRecommendation::query()
            ->where('plan_audit_id', $planId)
            ->count();

        $recommendationApproved = AuditRecommendation::query()
            ->where('plan_audit_id', $planId)
            ->where('status', 'approved')
            ->count();

        $picaTotal = Pica::query()
            ->where('plan_audit_id', $planId)
            ->count();

        $picaClosed = Pica::query()
            ->where('plan_audit_id', $planId)
            ->where('status', 'closed')
            ->count();

        $skTotal = SuratKeputusan::query()
            ->where('plan_audit_id', $planId)
            ->count();

        $skSelesai = SuratKeputusan::query()
            ->where('plan_audit_id', $planId)
            ->where('status', 'selesai')
            ->count();

        $progressParts = [];

        if ($taskTotal > 0) {
            $progressParts[] = ($taskDone / $taskTotal) * 100;
        }

        if ($recommendationTotal > 0) {
            $progressParts[] = ($recommendationApproved / $recommendationTotal) * 100;
        }

        if ($picaTotal > 0) {
            $progressParts[] = ($picaClosed / $picaTotal) * 100;
        }

        if ($skTotal > 0) {
            $progressParts[] = ($skSelesai / $skTotal) * 100;
        }

        $completionPercent = count($progressParts) > 0
            ? round(array_sum($progressParts) / count($progressParts), 2)
            : 0;

        return [
            'task_total' => $taskTotal,
            'task_open' => AuditTask::query()
                ->where('plan_audit_id', $planId)
                ->where('status', 'open')
                ->count(),
            'task_progress' => AuditTask::query()
                ->where('plan_audit_id', $planId)
                ->whereIn('status', ['progress', 'in_progress'])
                ->count(),
            'task_done' => $taskDone,

            'recommendation_total' => $recommendationTotal,
            'recommendation_waiting_approval' => AuditRecommendation::query()
                ->where('plan_audit_id', $planId)
                ->where('status', 'waiting_approval')
                ->count(),
            'recommendation_approved' => $recommendationApproved,

            'pica_total' => $picaTotal,
            'pica_open' => Pica::query()
                ->where('plan_audit_id', $planId)
                ->where('status', 'open')
                ->count(),
            'pica_progress' => Pica::query()
                ->where('plan_audit_id', $planId)
                ->where('status', 'progress')
                ->count(),
            'pica_closed' => $picaClosed,

            'sk_total' => $skTotal,
            'sk_pending_manajer' => SuratKeputusan::query()
                ->where('plan_audit_id', $planId)
                ->where('status', 'pending_manajer')
                ->count(),
            'sk_pending_afd' => SuratKeputusan::query()
                ->where('plan_audit_id', $planId)
                ->where('status', 'pending_afd')
                ->count(),
            'sk_selesai' => $skSelesai,

            'completion_percent' => $completionPercent,
        ];
    }

    private function normalizePlan(PlanAudit $plan): array
    {
        return [
            'id' => $plan->id,
            'no_spt' => $this->attr($plan, ['no_spt', 'noSpt', 'noSPT']),
            'cabang' => $this->attr($plan, ['cabang', 'cabang_plan', 'cabangPlan']),
            'unit_usaha' => $this->attr($plan, ['unit_usaha', 'unitUsaha']),
            'jenis_audit' => $this->attr($plan, ['jenis_audit', 'jenisAudit', 'tipe_audit', 'tipeAudit']),
            'auditor' => $this->attr($plan, ['auditor', 'nama_pemeriksa', 'namaPemeriksa']),
            'status' => $this->attr($plan, ['status']),
            'tanggal_mulai' => $this->attr($plan, ['tanggal_mulai', 'tgl_mulai', 'tglMulai', 'tanggal']),
            'tanggal_selesai' => $this->attr($plan, ['tanggal_selesai', 'tgl_selesai', 'tglSelesai']),
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
        ];
    }

    private function attr(\Illuminate\Database\Eloquent\Model $model, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $model->getAttribute($key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
