<?php

use Illuminate\Support\Facades\Route;
use Flux\Http\Controllers\DashboardController;
use Flux\Http\Api\DashboardApiController;

/*
|--------------------------------------------------------------------------
| Smart Migration Dashboard Routes
|--------------------------------------------------------------------------
*/

// Web routes
Route::get('/smart-migration', [DashboardController::class, 'index'])
    ->name('smart-migration.dashboard');

// API routes
Route::prefix('api/smart-migration')->name('smart-migration.api.')->group(function () {
    // GET endpoints
    Route::get('/status', [DashboardApiController::class, 'status'])->name('status');
    Route::get('/migrations', [DashboardApiController::class, 'migrations'])->name('migrations');
    Route::get('/history', [DashboardApiController::class, 'history'])->name('history');
    Route::get('/schema', [DashboardApiController::class, 'schema'])->name('schema');
    Route::get('/drift', [DashboardApiController::class, 'drift'])->name('drift');
    Route::get('/snapshots', [DashboardApiController::class, 'snapshots'])->name('snapshots');
    Route::get('/metrics', [DashboardApiController::class, 'metrics'])->name('metrics');
    Route::get('/performance/baselines', [DashboardApiController::class, 'performanceBaselines'])->name('performance.baselines');
    Route::get('/performance/report', [DashboardApiController::class, 'performanceReport'])->name('performance.report');
    Route::get('/performance/migration/{migration}', [DashboardApiController::class, 'migrationPerformance'])->name('performance.migration');
    Route::get('/migrations/preview/{migration}', [DashboardApiController::class, 'migrationPreview'])->name('migrations.preview');

    // POST/DELETE endpoints (Actions)
    Route::post('/drift/fix', [DashboardApiController::class, 'generateFixMigration'])->name('drift.fix');
    Route::post('/snapshots', [DashboardApiController::class, 'createSnapshot'])->name('snapshots.create');
    Route::delete('/snapshots/{name}', [DashboardApiController::class, 'deleteSnapshot'])->name('snapshots.delete');
    Route::post('/migrations/run', [DashboardApiController::class, 'runMigrations'])->name('migrations.run');
    Route::post('/migrations/rollback', [DashboardApiController::class, 'rollbackMigrations'])->name('migrations.rollback');
});
