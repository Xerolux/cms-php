<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class CreateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create
                            {name : Tenant name}
                            {email : Tenant email}
                            {domain : Tenant domain}
                            {--plan=free : Subscription plan}
                            {--trial : Enable trial period}
                            {--seed : Run database seeder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant with database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $domain = $this->argument('domain');
        $plan = $this->option('plan');
        $trial = $this->option('trial');
        $seed = $this->option('seed');

        try {
            DB::beginTransaction();

            $this->info("Creating tenant: {$name}");

            // Create tenant
            $tenant = Tenant::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => $name,
                'email' => $email,
                'plan' => $plan,
                'is_active' => true,
            ]);

            // Set trial if requested
            if ($trial && config('tenancy.trial.enabled')) {
                $trialDuration = config('tenancy.trial.duration_days', 14);
                $tenant->update([
                    'trial_ends_at' => now()->addDays($trialDuration),
                ]);
                $this->info("Trial enabled: expires on {$tenant->trial_ends_at}");
            }

            // Create domain
            $domain = $tenant->domains()->create([
                'domain' => $domain,
            ]);

            $this->info("Domain created: {$domain->domain}");

            // Initialize tenant and run migrations
            tenancy()->initialize($tenant);

            $this->info('Running migrations...');

            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--force' => true,
            ]);

            $this->info(Artisan::output());

            // Run seeder if requested
            if ($seed) {
                $this->info('Running database seeder...');

                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'TenantSeeder',
                    '--force' => true,
                ]);

                $this->info(Artisan::output());
            }

            tenancy()->end();

            DB::commit();

            $this->info("Tenant created successfully!");
            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $tenant->id],
                    ['Name', $tenant->name],
                    ['Email', $tenant->email],
                    ['Domain', $domain->domain],
                    ['Plan', $tenant->plan],
                    ['Active', $tenant->is_active ? 'Yes' : 'No'],
                    ['Trial Ends', $tenant->trial_ends_at ?? 'N/A'],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error("Failed to create tenant: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
