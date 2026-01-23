<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CentralAuthController extends Controller
{
    /**
     * Register a new tenant (self-service registration).
     */
    public function registerTenant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'subdomain' => 'required|string|max:255|unique:domains,domain',
            'password' => 'required|string|min:8',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:central_users,email', // Separate table for central users
            'plan' => 'sometimes|string|in:free,starter,professional',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Create tenant
            $tenant = Tenant::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => $request->name,
                'email' => $request->email,
                'plan' => $request->plan ?? 'free',
                'is_active' => true,
            ]);

            // Set trial for paid plans
            if ($request->plan !== 'free' && config('tenancy.trial.enabled')) {
                $trialDuration = config('tenancy.trial.duration_days', 14);
                $tenant->update([
                    'trial_ends_at' => now()->addDays($trialDuration),
                ]);
            }

            // Create domain
            $domain = $tenant->domains()->create([
                'domain' => $request->subdomain,
            ]);

            // Create tenant database
            tenancy()->initialize($tenant);

            // Run migrations for tenant
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations',
                '--force' => true,
            ]);

            // Create admin user for tenant
            $user = \App\Models\User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            ]);

            // Assign admin role
            $user->assignRole('admin');

            tenancy()->end();

            DB::commit();

            return response()->json([
                'message' => 'Tenant registered successfully',
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $domain->domain,
                    'url' => "https://{$domain->domain}",
                    'plan' => $tenant->plan,
                ],
                'admin' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to register tenant',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
