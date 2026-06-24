<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\DataStoreController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\MonitoringController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PlanAuditController;
use App\Http\Controllers\Api\AuditTaskController;
use App\Http\Controllers\Api\AuditRecommendationController;
use App\Http\Controllers\Api\PicaController;
use App\Http\Controllers\Api\SuratKeputusanController;
use App\Http\Controllers\Api\ReportAuditController;
use App\Http\Controllers\Api\PemeriksaanKasController;
use App\Http\Controllers\Api\PemeriksaanBankController;
use App\Http\Controllers\Api\PemeriksaanSmhController;
use App\Http\Controllers\Api\PemeriksaanPerlengkapanController;
use App\Http\Controllers\Api\PemeriksaanMateraiController;
use App\Http\Controllers\Api\PlafonController;
use App\Http\Controllers\Api\BpkbOnhandController;
use App\Http\Controllers\Api\BpkbInprosesController;
use App\Http\Controllers\Api\KwitansiController;
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

    // ─── Akun Saya (profil self-service) ──────────────────────────
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // ─── Database Master Data ─────────────────────────────────────
    Route::prefix('database')->group(function () {
        Route::get('/unit-usaha-options', [DatabaseController::class, 'unitUsahaOptions']);
        Route::get('/{type}', [DatabaseController::class, 'index']);

        Route::middleware('akta.role:admin')->group(function () {
            Route::post('/{type}/import', [DatabaseController::class, 'import']);
            Route::delete('/{type}/truncate', [DatabaseController::class, 'truncate']);
            Route::post('/{type}', [DatabaseController::class, 'store']);
            Route::put('/{type}/{id}', [DatabaseController::class, 'update']);
            Route::delete('/{type}/{id}', [DatabaseController::class, 'destroy']);
        });
    });

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

    // ── Perlengkapan di luar SMH ──
    Route::get('/audit-detail/perlengkapan', [PemeriksaanPerlengkapanController::class, 'index']);
    Route::get('/audit-detail/perlengkapan/jenis', [PemeriksaanPerlengkapanController::class, 'jenis']);
    Route::get('/audit-detail/perlengkapan/smh-summary', [PemeriksaanPerlengkapanController::class, 'smhSummary']);
    Route::post('/audit-detail/perlengkapan', [PemeriksaanPerlengkapanController::class, 'store'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::put('/audit-detail/perlengkapan/{pemeriksaanPerlengkapan}', [PemeriksaanPerlengkapanController::class, 'update'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::delete('/audit-detail/perlengkapan/{pemeriksaanPerlengkapan}', [PemeriksaanPerlengkapanController::class, 'destroy'])
        ->middleware('akta.role:admin,manajer,auditor');

    // ── Plafon ──
    Route::get('/audit-detail/plafon/analisa',   [PlafonController::class, 'analisa']);
    Route::get('/audit-detail/plafon/unit-list', [PlafonController::class, 'unitList']);

    // ── Materai ──
    Route::get('/audit-detail/materai',                                  [PemeriksaanMateraiController::class, 'index']);
    Route::post('/audit-detail/materai/upload',                          [PemeriksaanMateraiController::class, 'upload'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::put('/audit-detail/materai/{pemeriksaanMaterai}/fisik',       [PemeriksaanMateraiController::class, 'updateFisik'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::delete('/audit-detail/materai/{pemeriksaanMaterai}',          [PemeriksaanMateraiController::class, 'destroy'])
        ->middleware('akta.role:admin,manajer,auditor');

    // ── BPKB Onhand ──
    Route::get('/audit-detail/bpkb',                              [BpkbOnhandController::class, 'index']);
    Route::get('/audit-detail/bpkb/search',                       [BpkbOnhandController::class, 'search']);
    Route::post('/audit-detail/bpkb/upload',                      [BpkbOnhandController::class, 'upload'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::post('/audit-detail/bpkb/scan',                        [BpkbOnhandController::class, 'scan'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::delete('/audit-detail/bpkb/scan/{bpkbOnhandItem}',    [BpkbOnhandController::class, 'unscan'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::delete('/audit-detail/bpkb/reset',                     [BpkbOnhandController::class, 'reset'])
        ->middleware('akta.role:admin,manajer,auditor');

    // ── BPKB Inproses ──
    Route::get('/audit-detail/bpkb-inproses',  [BpkbInprosesController::class, 'show']);
    Route::post('/audit-detail/bpkb-inproses', [BpkbInprosesController::class, 'save'])
        ->middleware('akta.role:admin,manajer,auditor');

    // ── Kwitansi Gantung ──
    Route::get('/audit-detail/kwitansi',  [KwitansiController::class, 'show']);
    Route::post('/audit-detail/kwitansi', [KwitansiController::class, 'save'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::post('/audit-detail/kwitansi/parse-excel', [KwitansiController::class, 'parseExcel'])
        ->middleware('akta.role:admin,manajer,auditor');

    // ── SMH ──
    Route::get('/audit-detail/smh', [PemeriksaanSmhController::class, 'index']);
    Route::get('/audit-detail/smh/scan', [PemeriksaanSmhController::class, 'scan']);
    Route::get('/audit-detail/smh/perlengkapan', [PemeriksaanSmhController::class, 'perlengkapan']);
    Route::post('/audit-detail/smh/upload', [PemeriksaanSmhController::class, 'upload'])
        ->middleware('akta.role:admin,manajer,auditor');
    Route::get('/audit-detail/smh/{pemeriksaanSmh}/sync-perlengkapan', [PemeriksaanSmhController::class, 'syncPerlengkapan']);
    Route::put('/audit-detail/smh/items/{item}', [PemeriksaanSmhController::class, 'checkItem'])
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

    Route::get('/plan-users', [PlanAuditController::class, 'teamOptions']);
    Route::get('/plans', [PlanAuditController::class, 'index']);
    Route::get('/plans/{plan}', [PlanAuditController::class, 'show']);

    Route::get('/tasks', [AuditTaskController::class, 'index']);
    Route::get('/tasks/{task}', [AuditTaskController::class, 'show']);

    // Auditor merekam pelaksanaan audit (mulai/selesai/lampiran)
    Route::post('/tasks/{task}/execute', [AuditTaskController::class, 'execute'])
        ->middleware('akta.role:admin,manajer,auditor');

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

    // Tambah plan: manajer & admin
    Route::post('/plans', [PlanAuditController::class, 'store'])
        ->middleware('akta.role:admin,manajer');

    // Edit & hapus plan: admin saja (admin yang perbaiki kesalahan)
    Route::put('/plans/{plan}', [PlanAuditController::class, 'update'])
        ->middleware('akta.role:admin');
    Route::delete('/plans/{plan}', [PlanAuditController::class, 'destroy'])
        ->middleware('akta.role:admin');

    // Alur birokrasi: advance & reject (semua role terautentikasi, kontrol di dalam controller)
    Route::post('/plans/{plan}/advance', [PlanAuditController::class, 'advance']);
    Route::post('/plans/{plan}/reject', [PlanAuditController::class, 'reject']);

    // Menu untuk user yang sedang login (server memfilter berdasarkan role)
    Route::get('/menus', [MenuController::class, 'myMenus']);

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

            // ── Role Management ───────────────────────────────────
            Route::get('/roles', [RoleController::class, 'index']);
            Route::post('/roles', [RoleController::class, 'store']);
            Route::put('/roles/{role}', [RoleController::class, 'update']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

            Route::get('/monitoring/stats', [MonitoringController::class, 'stats']);
            Route::get('/monitoring/health', [MonitoringController::class, 'health']);
            Route::get('/monitoring/activity-log', [MonitoringController::class, 'activityLog']);

            // ── Menu Management ───────────────────────────────────
            Route::get('/menus', [MenuController::class, 'index']);
            Route::post('/menus', [MenuController::class, 'store']);
            Route::put('/menus/{menu}', [MenuController::class, 'update']);
            Route::delete('/menus/{menu}', [MenuController::class, 'destroy']);
            Route::put('/menus/{menu}/roles', [MenuController::class, 'updateRoles']);
            Route::put('/menus/{menu}/toggle', [MenuController::class, 'toggle']);
            Route::post('/menus/seed', [MenuController::class, 'seed']);
            Route::post('/menus/reorder', [MenuController::class, 'reorder']);
        });
});
