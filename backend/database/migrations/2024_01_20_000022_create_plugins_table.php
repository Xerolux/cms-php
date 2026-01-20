<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('version');
            $table->string('author')->nullable();
            $table->text('description')->nullable();
            $table->string('path'); // Plugin directory path
            $table->json('config')->nullable(); // Plugin configuration
            $table->boolean('is_active')->default(false);
            $table->foreignId('installed_by')->nullable()->constrained('users');
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        // Create plugin hooks table
        Schema::create('plugin_hooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hook'); // e.g., 'post.created', 'user.login'
            $table->text('description')->nullable();
            $table->foreignId('plugin_id')->constrained('plugins')->onDelete('cascade');
            $table->integer('priority')->default(10);
            $table->timestamps();

            $table->index(['hook', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_hooks');
        Schema::dropIfExists('plugins');
    }
};
