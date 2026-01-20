<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'mail_host',
                'value' => 'smtp.mailtrap.io',
                'type' => 'text',
                'group' => 'email',
                'display_name' => 'Mail Host',
                'description' => 'SMTP Server Hostname',
                'is_public' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_port',
                'value' => '2525',
                'type' => 'number',
                'group' => 'email',
                'display_name' => 'Mail Port',
                'description' => 'SMTP Server Port',
                'is_public' => false,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_username',
                'value' => '',
                'type' => 'text',
                'group' => 'email',
                'display_name' => 'Mail Username',
                'description' => 'SMTP Username',
                'is_public' => false,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_password',
                'value' => '',
                'type' => 'text', // Should ideally be encrypted or password type, but text for now based on available types
                'group' => 'email',
                'display_name' => 'Mail Password',
                'description' => 'SMTP Password',
                'is_public' => false,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_encryption',
                'value' => 'tls',
                'type' => 'text',
                'group' => 'email',
                'display_name' => 'Mail Encryption',
                'description' => 'tls or ssl',
                'is_public' => false,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_from_address',
                'value' => 'hello@example.com',
                'type' => 'text',
                'group' => 'email',
                'display_name' => 'From Address',
                'description' => 'Default sender email address',
                'is_public' => false,
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_from_name',
                'value' => 'Blog CMS',
                'type' => 'text',
                'group' => 'email',
                'display_name' => 'From Name',
                'description' => 'Default sender name',
                'is_public' => false,
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('settings')->insert($settings);
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'email')->delete();
    }
};
