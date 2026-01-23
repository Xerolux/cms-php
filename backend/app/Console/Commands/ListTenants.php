<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ListTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::with('domains')->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s):");
        $this->newLine();

        $data = [];
        foreach ($tenants as $tenant) {
            $domains = $tenant->domains->pluck('domain')->implode(', ');

            $data[] = [
                $tenant->id,
                $tenant->name,
                $tenant->email,
                $domains,
                $tenant->plan,
                $tenant->is_active ? 'Yes' : 'No',
                $tenant->trial_ends_at ?? 'N/A',
                $tenant->subscription_ends_at ?? 'N/A',
            ];
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Domain(s)', 'Plan', 'Active', 'Trial Ends', 'Subscription Ends'],
            $data
        );

        return Command::SUCCESS;
    }
}
