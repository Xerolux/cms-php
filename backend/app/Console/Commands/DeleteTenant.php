<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeleteTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:delete
                            {tenant : Tenant ID}
                            {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a tenant and their database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        $force = $this->option('force');

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant not found: {$tenantId}");
            return Command::FAILURE;
        }

        $this->info("Tenant: {$tenant->name} ({$tenant->id})");
        $this->info("Email: {$tenant->email}");
        $this->info("Plan: {$tenant->plan}");

        // Show tenant stats
        tenancy()->initialize($tenant);
        $stats = [
            'Users' => \App\Models\User::count(),
            'Posts' => \App\Models\Post::count(),
            'Pages' => \App\Models\Page::count(),
            'Media' => \App\Models\Media::count(),
        ];
        tenancy()->end();

        foreach ($stats as $key => $value) {
            $this->info("{$key}: {$value}");
        }

        // Confirm deletion
        if (!$force) {
            if (!$this->confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
                $this->info('Deletion cancelled.');
                return Command::SUCCESS;
            }

            if (!$this->confirm('This will delete ALL data including the database. Continue?')) {
                $this->info('Deletion cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            DB::beginTransaction();

            $this->info('Deleting tenant...');

            // Get database name before deletion
            tenancy()->initialize($tenant);
            $databaseName = DB::connection('tenant')->getDatabaseName();
            tenancy()->end();

            // Delete domain records
            $tenant->domains()->delete();

            // Delete tenant record
            $tenant->delete();

            // Drop database
            $this->info("Dropping database: {$databaseName}");
            DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");

            DB::commit();

            $this->info('Tenant deleted successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error("Failed to delete tenant: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
