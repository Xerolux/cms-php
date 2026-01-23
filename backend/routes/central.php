<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Central\CentralDashboardController;
use App\Http\Controllers\Central\TenantManagementController;
use App\Http\Controllers\Central\CentralAuthController;

// Central/Super Admin Routes (central domain only)
Route::prefix('api/v1/central')->middleware(['throttle:100,1'])->group(function () {
    // Public endpoints
    Route::post('/auth/register-tenant', [CentralAuthController::class, 'registerTenant'])
        ->middleware('throttle:5,1');

    // Protected endpoints
    Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
        // Central Dashboard
        Route::get('/dashboard', [CentralDashboardController::class, 'index']);
        Route::get('/stats', [CentralDashboardController::class, 'stats']);

        // Tenant Management
        Route::prefix('tenants')->group(function () {
            Route::get('/', [TenantManagementController::class, 'index']);
            Route::post('/', [TenantManagementController::class, 'store']);
            Route::get('/{tenant}', [TenantManagementController::class, 'show']);
            Route::put('/{tenant}', [TenantManagementController::class, 'update']);
            Route::delete('/{tenant}', [TenantManagementController::class, 'destroy']);

            // Tenant Actions
            Route::post('/{tenant}/activate', [TenantManagementController::class, 'activate']);
            Route::post('/{tenant}/deactivate', [TenantManagementController::class, 'deactivate']);
            Route::post('/{tenant}/suspend', [TenantManagementController::class, 'suspend']);
            Route::post('/{tenant}/unsuspend', [TenantManagementController::class, 'unsuspend']);
            Route::post('/{tenant}/reset-trial', [TenantManagementController::class, 'resetTrial']);

            // Tenant Statistics
            Route::get('/{tenant}/stats', [TenantManagementController::class, 'stats']);
            Route::get('/{tenant}/users', [TenantManagementController::class, 'users']);
            Route::get('/{tenant}/domains', [TenantManagementController::class, 'domains']);
            Route::post('/{tenant}/domains', [TenantManagementController::class, 'addDomain']);
            Route::delete('/{tenant}/domains/{domain}', [TenantManagementController::class, 'removeDomain']);

            // Tenant Database
            Route::post('/{tenant}/database/backup', [TenantManagementController::class, 'backupDatabase']);
            Route::post('/{tenant}/database/migrate', [TenantManagementController::class, 'runMigrations']);
            Route::post('/{tenant}/database/seed', [TenantManagementController::class, 'runSeeder']);
            Route::post('/{tenant}/database/rollback', [TenantManagementController::class, 'rollbackMigrations']);
        });

        // Platform-wide Statistics
        Route::prefix('platform')->group(function () {
            Route::get('/stats', [CentralDashboardController::class, 'platformStats']);
            Route::get('/revenue', [CentralDashboardController::class, 'revenueStats']);
            Route::get('/tenants-growth', [CentralDashboardController::class, 'tenantsGrowth']);
            Route::get('/plan-distribution', [CentralDashboardController::class, 'planDistribution']);
        });
    });
});
