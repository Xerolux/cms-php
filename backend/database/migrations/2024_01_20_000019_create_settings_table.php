<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text');
            $table->string('group')->default('general');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_public')->default(false);
            $table->integer('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('group');
            $table->index('is_public');
        });

        // Insert minimal default settings
        DB::table('settings')->insert([
            [
                'key' => 'site_name',
                'value' => 'My Blog',
                'type' => 'text',
                'group' => 'general',
                'display_name' => 'Site Name',
                'description' => 'The name of your website',
                'is_public' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_description',
                'value' => 'A modern blog platform',
                'type' => 'textarea',
                'group' => 'general',
                'display_name' => 'Site Description',
                'description' => 'A short description of your website',
                'is_public' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'posts_per_page',
                'value' => '10',
                'type' => 'number',
                'group' => 'general',
                'display_name' => 'Posts Per Page',
                'description' => 'Number of posts to display per page',
                'is_public' => false,
                'sort_order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
