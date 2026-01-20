<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('role_level')->default(0)->after('role');
            $table->foreignId('manager_id')->nullable()->after('role_level')->constrained('users');

            $table->index('role_level');
            $table->index('manager_id');
        });

        // Set default role levels
        DB::statement("UPDATE users SET role_level = CASE role
            WHEN 'super_admin' THEN 100
            WHEN 'admin' THEN 80
            WHEN 'editor' THEN 60
            WHEN 'author' THEN 40
            WHEN 'contributor' THEN 20
            WHEN 'subscriber' THEN 10
            ELSE 0
        END");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role_level', 'manager_id']);
        });
    }
};
