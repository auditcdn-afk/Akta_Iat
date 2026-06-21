<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DataStoreController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\MonitoringController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\PlanAuditController;
use App\Http\Controllers\Api\AuditTaskController;
use App\Http\Controllers\Api\AuditRecommendationController;
use App\Http\Controllers\Api\PicaController;
use App\Http\Controllers\Api\SuratKeputusanController;
use App\Http\Controllers\Api\ReportAuditController;
use App\Http\Controllers\Api\PemeriksaanKasController;
use App\Http\Controllers\Api\PemeriksaanBankController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', [DataStoreController::class, 'ping']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/sk', [SuratKeputusanController::class, 'index']);
    Route::get('/sk/{suratKeputusan}', [SuratKeputusanController::class, 'show']);

    Route::post('/sk', [SuratKeputusanController::class, 'store'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::put('/sk/{suratKeputusan}', [SuratKeputusanController::class, 'update'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::delete('/sk/{suratKeputusan}', [SuratKeputusanController::class, 'destroy'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::post('/sk/{suratKeputusan}/approve-manajer', [SuratKeputusanController::class, 'approveManajer'])
        ->middleware('akta.role:admin,manajer');

    Route::post('/sk/{suratKeputusan}/approve-afd', [SuratKeputusanController::class, 'approveAfd'])
        ->middleware('akta.role:admin');

    Route::get('/report-audit', [ReportAuditController::class, 'index']);
    Route::get('/report-audit/summary', [ReportAuditController::class, 'summary']);
    Route::get('/report-audit/plans/{plan}', [ReportAuditController::class, 'show']);

    Route::get('/audit-detail/kas', [PemeriksaanKasController::class, 'index']);
    Route::get('/audit-detail/kas/summary', [PemeriksaanKasController::class, 'summary']);
    Route::get('/audit-detail/kas/{pemeriksaanKas}', [PemeriksaanKasController::class, 'show']);

    Route::post('/audit-detail/kas', [PemeriksaanKasController::class, 'store'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::put('/audit-detail/kas/{pemeriksaanKas}', [PemeriksaanKasController::class, 'update'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::delete('/audit-detail/kas/{pemeriksaanKas}', [PemeriksaanKasController::class, 'destroy'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::get('/audit-detail/bank', [PemeriksaanBankController::class, 'index']);
    Route::get('/audit-detail/bank/summary', [PemeriksaanBankController::class, 'summary']);
    Route::get('/audit-detail/bank/{pemeriksaanBank}', [PemeriksaanBankController::class, 'show']);

    Route::post('/audit-detail/bank', [PemeriksaanBankController::class, 'store'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::put('/audit-detail/bank/{pemeriksaanBank}', [PemeriksaanBankController::class, 'update'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::delete('/audit-detail/bank/{pemeriksaanBank}', [PemeriksaanBankController::class, 'destroy'])
        ->middleware('akta.role:admin,manajer,auditor');


    Route::get('/picas', [PicaController::class, 'index']);
    Route::get('/picas/{pica}', [PicaController::class, 'show']);

    Route::post('/picas', [PicaController::class, 'store'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::put('/picas/{pica}', [PicaController::class, 'update'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::delete('/picas/{pica}', [PicaController::class, 'destroy'])
        ->middleware('akta.role:admin,manajer,auditor');

    Route::post('/picas/{pica}/close', [PicaController::class, 'close'])
        ->middleware('akta.role:admin,manajer');

    Route::get('/all-data', [DataStoreController::class, 'allData']);

    Route::get('/data/{key}', [DataStoreController::class, 'read']);

    Route::put('/data', [DataStoreController::class, 'write']);
    Route::post('/data', [DataStoreController::class, 'write']);

    Route::get('/plans', [PlanAuditController::class, 'index']);
    Route::get('/plans/{plan}', [PlanAuditController::class, 'show']);

    Route::get('/tasks', [AuditTaskController::class, 'index']);
    Route::get('/tasks/{task}', [AuditTaskController::class, 'show']);

    Route::get('/recommendations', [AuditRecommendationController::class, 'index']);
    Route::get('/recommendations/{recommendation}', [AuditRecommendationController::class, 'show']);

    Route::middleware('akta.role:admin,manajer,auditor')->group(function () {
        Route::post('/recommendations', [AuditRecommendationController::class, 'store']);
        Route::put('/recommendations/{recommendation}', [AuditRecommendationController::class, 'update']);
        Route::delete('/recommendations/{recommendation}', [AuditRecommendationController::class, 'destroy']);
    });

    Route::post('/recommendations/{recommendation}/approve', [AuditRecommendationController::class, 'approve'])
        ->middleware('akta.role:admin,manajer');

    Route::middleware('akta.role:admin,manajer,auditor')->group(function () {
        Route::post('/tasks', [AuditTaskController::class, 'store']);
        Route::put('/tasks/{task}', [AuditTaskController::class, 'update']);
        Route::delete('/tasks/{task}', [AuditTaskController::class, 'destroy']);
    });

    Route::middleware('akta.role:admin,manajer,auditor')->group(function () {
        Route::post('/plans', [PlanAuditController::class, 'store']);
        Route::put('/plans/{plan}', [PlanAuditController::class, 'update']);
        Route::delete('/plans/{plan}', [PlanAuditController::class, 'destroy']);
    });

    Route::prefix('admin')
        ->middleware('akta.role:admin')
        ->group(function () {
            Route::get('/security-check', function () {
                return response()->json([
                    'ok' => true,
                    'message' => 'Admin endpoint aktif.',
                    'user' => request()->user()?->toAktaArray(),
                ]);
            });

            Route::apiResource('/users', UserController::class)
                ->only(['index', 'store', 'update', 'destroy']);

            Route::get('/monitoring/stats', [MonitoringController::class, 'stats']);
            Route::get('/monitoring/health', [MonitoringController::class, 'health']);
            Route::get('/monitoring/activity-log', [MonitoringController::class, 'activityLog']);
            Route::get('/menus', [MenuController::class, 'index']);
            Route::put('/menus', [MenuController::class, 'update']);
            Route::post('/menus/reset', [MenuController::class, 'reset']);
        });
});
