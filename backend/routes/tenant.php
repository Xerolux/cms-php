<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\TenantDashboardController;
use App\Http\Controllers\Tenant\TenantSettingsController;
use App\Http\Controllers\Tenant\TenantSubscriptionController;
use App\Http\Controllers\Tenant\TenantUserController;

// Tenant Routes (applied to all tenant subdomains)
Route::middleware(['tenancy'])->group(function () {
    Route::prefix('api/v1')->group(function () {
        // Tenant Dashboard
        Route::get('/dashboard', [TenantDashboardController::class, 'index']);

        // Tenant Settings
        Route::prefix('tenant')->group(function () {
            Route::get('/', [TenantSettingsController::class, 'show']);
            Route::put('/', [TenantSettingsController::class, 'update'])
                ->middleware('role:admin,super_admin');

            // Tenant Users
            Route::get('/users', [TenantUserController::class, 'index'])
                ->middleware('role:admin,super_admin');
            Route::post('/users', [TenantUserController::class, 'store'])
                ->middleware('role:admin,super_admin')
                ->middleware('tenancy.check_limits');
            Route::put('/users/{user}', [TenantUserController::class, 'update'])
                ->middleware('role:admin,super_admin');
            Route::delete('/users/{user}', [TenantUserController::class, 'destroy'])
                ->middleware('role:admin,super_admin');

            // Subscription/Billing
            Route::prefix('subscription')->group(function () {
                Route::get('/', [TenantSubscriptionController::class, 'show']);
                Route::get('/plans', [TenantSubscriptionController::class, 'plans']);
                Route::post('/upgrade', [TenantSubscriptionController::class, 'upgrade']);
                Route::post('/downgrade', [TenantSubscriptionController::class, 'downgrade']);
                Route::post('/cancel', [TenantSubscriptionController::class, 'cancel']);
                Route::post('/resume', [TenantSubscriptionController::class, 'resume']);
                Route::get('/usage', [TenantSubscriptionController::class, 'usage']);
                Route::get('/invoices', [TenantSubscriptionController::class, 'invoices']);
                Route::get('/payment-methods', [TenantSubscriptionController::class, 'paymentMethods']);
                Route::post('/payment-methods', [TenantSubscriptionController::class, 'addPaymentMethod']);
                Route::delete('/payment-methods/{id}', [TenantSubscriptionController::class, 'removePaymentMethod']);
            });

            // Tenant Statistics
            Route::get('/stats', [TenantDashboardController::class, 'stats']);
            Route::get('/analytics', [TenantDashboardController::class, 'analytics']);
        });
    });
});
