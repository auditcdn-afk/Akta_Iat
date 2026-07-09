<?php

namespace App\Http\Middleware;

use App\Models\PlanAudit;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAktaRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->is_disabled) {
            return response()->json([
                'message' => 'Akun ini dinonaktifkan.',
            ], 403);
        }

        // Plan Audit Mandiri/Sertijab: unit usaha yang bersangkutan sendiri yang mengisi,
        // jadi lewati pembatasan role untuk endpoint pemeriksaan yang terkait plan tersebut.
        if ($roles !== [] && $this->isForMandiriPlan($request)) {
            return $next($request);
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Akses ditolak.',
                'required_roles' => $roles,
                'current_role' => $user->role,
            ], 403);
        }

        return $next($request);
    }

    private function isForMandiriPlan(Request $request): bool
    {
        $planAuditId = $request->input('plan_audit_id') ?? $request->input('planAuditId');

        if (! $planAuditId) {
            foreach ($request->route()?->parameters() ?? [] as $param) {
                if ($param instanceof PlanAudit) {
                    $planAuditId = $param->id;
                    break;
                }
                if ($param instanceof Model && $param->getAttribute('plan_audit_id')) {
                    $planAuditId = $param->getAttribute('plan_audit_id');
                    break;
                }
            }
        }

        if (! $planAuditId) {
            return false;
        }

        return PlanAudit::query()->where('id', $planAuditId)->where('is_mandiri', true)->exists();
    }
}
