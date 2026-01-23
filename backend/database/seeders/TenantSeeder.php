<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear existing data
        User::truncate();
        Setting::truncate();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'sanctum']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $editorRole = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'sanctum']);
        $authorRole = Role::firstOrCreate(['name' => 'author', 'guard_name' => 'sanctum']);

        // Create default admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@xquantoria.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole($superAdminRole);
        $admin->assignRole($adminRole);

        // Create additional demo users
        $editor = User::firstOrCreate(
            ['email' => 'editor@xquantoria.test'],
            [
                'name' => 'Editor User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $editor->assignRole($editorRole);

        $author = User::firstOrCreate(
            ['email' => 'author@xquantoria.test'],
            [
                'name' => 'Author User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $author->assignRole($authorRole);

        // Create default settings
        $defaultSettings = [
            [
                'key' => 'site_name',
                'value' => 'XQUANTORIA CMS',
                'type' => 'text',
                'group' => 'general',
                'is_public' => true,
            ],
            [
                'key' => 'site_description',
                'value' => 'A powerful content management system',
                'type' => 'textarea',
                'group' => 'general',
                'is_public' => true,
            ],
            [
                'key' => 'site_logo',
                'value' => null,
                'type' => 'image',
                'group' => 'general',
                'is_public' => true,
            ],
            [
                'key' => 'site_favicon',
                'value' => null,
                'type' => 'image',
                'group' => 'general',
                'is_public' => true,
            ],
            [
                'key' => 'timezone',
                'value' => 'Europe/Berlin',
                'type' => 'select',
                'group' => 'general',
                'is_public' => false,
            ],
            [
                'key' => 'locale',
                'value' => 'de',
                'type' => 'select',
                'group' => 'general',
                'is_public' => false,
            ],
            [
                'key' => 'date_format',
                'value' => 'd.m.Y',
                'type' => 'select',
                'group' => 'general',
                'is_public' => false,
            ],
            [
                'key' => 'time_format',
                'value' => 'H:i',
                'type' => 'select',
                'group' => 'general',
                'is_public' => false,
            ],
            [
                'key' => 'posts_per_page',
                'value' => '10',
                'type' => 'number',
                'group' => 'content',
                'is_public' => false,
            ],
            [
                'key' => 'allow_comments',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'content',
                'is_public' => false,
            ],
            [
                'key' => 'comment_moderation',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'content',
                'is_public' => false,
            ],
            [
                'key' => 'seo_title_separator',
                'value' => '-',
                'type' => 'text',
                'group' => 'seo',
                'is_public' => false,
            ],
            [
                'key' => 'seo_meta_description_length',
                'value' => '160',
                'type' => 'number',
                'group' => 'seo',
                'is_public' => false,
            ],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Tenant seeded successfully with default admin user and settings.');
        $this->command->info('Admin email: admin@xquantoria.test');
        $this->command->info('Admin password: password');
        $this->command->newLine();
    }
}
