<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        try {
            $settings = DB::table('settings')
                ->where('group', 'email')
                ->pluck('value', 'key')
                ->toArray();

            if (!empty($settings['mail_host'])) {
                $config = [
                    'transport' => 'smtp',
                    'host' => $settings['mail_host'],
                    'port' => $settings['mail_port'] ?? 587,
                    'encryption' => $settings['mail_encryption'] ?? 'tls',
                    'username' => $settings['mail_username'] ?? null,
                    'password' => $settings['mail_password'] ?? null,
                    'timeout' => null,
                ];

                Config::set('mail.mailers.smtp', $config);
            }

            if (!empty($settings['mail_from_address'])) {
                Config::set('mail.from', [
                    'address' => $settings['mail_from_address'],
                    'name' => $settings['mail_from_name'] ?? config('app.name'),
                ]);
            }
        } catch (\Exception $e) {
            // Fails silently if DB is not available yet
        }
    }
}
