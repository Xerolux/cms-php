<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class TenantManagementController extends Controller
{
    public function __construct()
    {
        // Only super admins can access these routes
        $this->middleware('role:super_admin');
    }

    /**
     * List all tenants.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::with('domains')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'tenants' => $tenants,
        ]);
    }

    /**
     * Create a new tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'domain' => 'required|string|max:255|unique:domains,domain',
            'plan' => 'required|string|in:free,starter,professional,enterprise',
            'trial' => 'boolean',
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
                'plan' => $request->plan,
                'is_active' => true,
            ]);

            // Set trial if requested
            if ($request->trial && config('tenancy.trial.enabled')) {
                $trialDuration = config('tenancy.trial.duration_days', 14);
                $tenant->update([
                    'trial_ends_at' => now()->addDays($trialDuration),
                ]);
            }

            // Create domain
            $domain = $tenant->domains()->create([
                'domain' => $request->domain,
            ]);

            // Create tenant database
            tenancy()->initialize($tenant);

            // Run migrations for tenant
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations',
                '--force' => true,
            ]);

            // Seed default data
            if ($request->seed) {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'TenantSeeder',
                    '--force' => true,
                ]);
            }

            tenancy()->end();

            DB::commit();

            return response()->json([
                'message' => 'Tenant created successfully',
                'tenant' => $tenant->load('domains'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to create tenant',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show tenant details.
     */
    public function show(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->load('domains');

        // Get tenant statistics
        tenancy()->initialize($tenant);

        $stats = [
            'users_count' => \App\Models\User::count(),
            'posts_count' => \App\Models\Post::count(),
            'pages_count' => \App\Models\Page::count(),
            'media_count' => \App\Models\Media::count(),
            'comments_count' => \App\Models\Comment::count(),
        ];

        tenancy()->end();

        return response()->json([
            'tenant' => $tenant,
            'stats' => $stats,
            'plan_details' => $tenant->getPlanDetailsAttribute(),
        ]);
    }

    /**
     * Update tenant.
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:tenants,email,' . $tenant->id,
            'plan' => 'sometimes|string|in:free,starter,professional,enterprise',
            'max_users' => 'sometimes|integer|min:1',
            'max_storage_gb' => 'sometimes|numeric|min:1',
            'max_posts' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenant->update($request->only([
            'name',
            'email',
            'plan',
            'max_users',
            'max_storage_gb',
            'max_posts',
            'is_active',
        ]));

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant->load('domains'),
        ]);
    }

    /**
     * Delete tenant.
     */
    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Drop tenant database
            tenancy()->initialize($tenant);
            $databaseName = DB::connection('tenant')->getDatabaseName();
            tenancy()->end();

            // Delete domain records
            $tenant->domains()->delete();

            // Delete tenant record
            $tenant->delete();

            // Drop database
            DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");

            DB::commit();

            return response()->json([
                'message' => 'Tenant deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to delete tenant',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activate tenant.
     */
    public function activate(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => true]);

        return response()->json([
            'message' => 'Tenant activated successfully',
        ]);
    }

    /**
     * Deactivate tenant.
     */
    public function deactivate(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => false]);

        return response()->json([
            'message' => 'Tenant deactivated successfully',
        ]);
    }

    /**
     * Suspend tenant.
     */
    public function suspend(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => false]);

        // Optionally, you could add a reason field and timestamps
        // $tenant->update([
        //     'is_active' => false,
        //     'suspended_at' => now(),
        //     'suspension_reason' => $request->reason,
        // ]);

        return response()->json([
            'message' => 'Tenant suspended successfully',
        ]);
    }

    /**
     * Unsuspend tenant.
     */
    public function unsuspend(Request $request, Tenant $tenant): JsonResponse
    {
        $tenant->update(['is_active' => true]);

        return response()->json([
            'message' => 'Tenant unsuspended successfully',
        ]);
    }

    /**
     * Reset tenant trial.
     */
    public function resetTrial(Request $request, Tenant $tenant): JsonResponse
    {
        $trialDuration = config('tenancy.trial.duration_days', 14);

        $tenant->update([
            'trial_ends_at' => now()->addDays($trialDuration),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Trial reset successfully',
            'trial_ends_at' => $tenant->trial_ends_at,
        ]);
    }

    /**
     * Get tenant statistics.
     */
    public function stats(Request $request, Tenant $tenant): JsonResponse
    {
        tenancy()->initialize($tenant);

        $stats = [
            'users' => [
                'total' => \App\Models\User::count(),
                'by_role' => [
                    'super_admin' => \App\Models\User::role('super_admin')->count(),
                    'admin' => \App\Models\User::role('admin')->count(),
                    'editor' => \App\Models\User::role('editor')->count(),
                    'author' => \App\Models\User::role('author')->count(),
                ],
            ],
            'posts' => [
                'total' => \App\Models\Post::count(),
                'by_status' => [
                    'published' => \App\Models\Post::where('status', 'published')->count(),
                    'draft' => \App\Models\Post::where('status', 'draft')->count(),
                    'scheduled' => \App\Models\Post::whereNotNull('published_at')
                        ->where('published_at', '>', now())
                        ->count(),
                ],
            ],
            'pages' => \App\Models\Page::count(),
            'media' => \App\Models\Media::count(),
            'comments' => \App\Models\Comment::count(),
            'storage_usage' => $tenant->storage_usage,
        ];

        tenancy()->end();

        return response()->json($stats);
    }

    /**
     * Get tenant users.
     */
    public function users(Request $request, Tenant $tenant): JsonResponse
    {
        tenancy()->initialize($tenant);

        $users = \App\Models\User::with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        tenancy()->end();

        return response()->json($users);
    }

    /**
     * Get tenant domains.
     */
    public function domains(Request $request, Tenant $tenant): JsonResponse
    {
        $domains = $tenant->domains;

        return response()->json($domains);
    }

    /**
     * Add domain to tenant.
     */
    public function addDomain(Request $request, Tenant $tenant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255|unique:domains,domain',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $domain = $tenant->domains()->create([
            'domain' => $request->domain,
        ]);

        return response()->json([
            'message' => 'Domain added successfully',
            'domain' => $domain,
        ], 201);
    }

    /**
     * Remove domain from tenant.
     */
    public function removeDomain(Request $request, Tenant $tenant, Domain $domain): JsonResponse
    {
        if ($domain->tenant_id !== $tenant->id) {
            return response()->json([
                'error' => 'Domain does not belong to this tenant',
            ], 403);
        }

        // Prevent removing the last domain
        if ($tenant->domains()->count() <= 1) {
            return response()->json([
                'error' => 'Cannot remove the last domain',
            ], 422);
        }

        $domain->delete();

        return response()->json([
            'message' => 'Domain removed successfully',
        ]);
    }

    /**
     * Backup tenant database.
     */
    public function backupDatabase(Request $request, Tenant $tenant): JsonResponse
    {
        try {
            tenancy()->initialize($tenant);

            // Run backup command
            Artisan::call('backup:run', [
                '--only-db' => true,
                '--disable-notifications' => true,
            ]);

            tenancy()->end();

            return response()->json([
                'message' => 'Database backup completed successfully',
            ]);
        } catch (\Exception $e) {
            tenancy()->end();

            return response()->json([
                'error' => 'Failed to backup database',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run migrations for tenant.
     */
    public function runMigrations(Request $request, Tenant $tenant): JsonResponse
    {
        try {
            tenancy()->initialize($tenant);

            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);

            $output = Artisan::output();

            tenancy()->end();

            return response()->json([
                'message' => 'Migrations completed successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            tenancy()->end();

            return response()->json([
                'error' => 'Failed to run migrations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run seeder for tenant.
     */
    public function runSeeder(Request $request, Tenant $tenant): JsonResponse
    {
        try {
            tenancy()->initialize($tenant);

            $seederClass = $request->get('seeder', 'TenantSeeder');

            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => $seederClass,
                '--force' => true,
            ]);

            $output = Artisan::output();

            tenancy()->end();

            return response()->json([
                'message' => 'Seeder completed successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            tenancy()->end();

            return response()->json([
                'error' => 'Failed to run seeder',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rollback migrations for tenant.
     */
    public function rollbackMigrations(Request $request, Tenant $tenant): JsonResponse
    {
        try {
            tenancy()->initialize($tenant);

            $steps = $request->get('steps', 1);

            Artisan::call('migrate:rollback', [
                '--database' => 'tenant',
                '--step' => $steps,
                '--force' => true,
            ]);

            $output = Artisan::output();

            tenancy()->end();

            return response()->json([
                'message' => 'Migrations rolled back successfully',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            tenancy()->end();

            return response()->json([
                'error' => 'Failed to rollback migrations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
