<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CentralSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing data
        DB::table('domains')->truncate();
        DB::table('tenants')->truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create demo tenants
        $this->createDemoTenant('demo', 'Demo Tenant', 'demo@example.com', 'professional', true);
        $this->createDemoTenant('starter-tenant', 'Starter Plan', 'starter@example.com', 'starter', true);
        $this->createDemoTenant('free-tenant', 'Free Plan', 'free@example.com', 'free', false);

        $this->command->info('Central database seeded successfully with demo tenants.');
        $this->command->newLine();
    }

    /**
     * Create a demo tenant with database and seed data.
     */
    protected function createDemoTenant($subdomain, $name, $email, $plan, $withTrial): void
    {
        $this->command->info("Creating tenant: {$subdomain}");

        // Create tenant
        $tenant = Tenant::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => $name,
            'email' => $email,
            'plan' => $plan,
            'is_active' => true,
        ]);

        // Set trial if requested
        if ($withTrial && config('tenancy.trial.enabled')) {
            $trialDuration = config('tenancy.trial.duration_days', 14);
            $tenant->update([
                'trial_ends_at' => now()->addDays($trialDuration),
            ]);
        }

        // Create domain
        $domain = $tenant->domains()->create([
            'domain' => $subdomain . '.xquantoria.test',
        ]);

        // Initialize tenant and run migrations/seeder
        tenancy()->initialize($tenant);

        // Run migrations
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'tenant',
            '--force' => true,
        ]);

        // Run seeder
        \Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'TenantSeeder',
            '--force' => true,
        ]);

        tenancy()->end();

        $this->command->info("  - Domain: {$domain->domain}");
        $this->command->info("  - Plan: {$plan}");
        $this->command->info("  - Trial: " . ($withTrial ? 'Yes (' . $tenant->trial_ends_at . ')' : 'No'));
    }
}
