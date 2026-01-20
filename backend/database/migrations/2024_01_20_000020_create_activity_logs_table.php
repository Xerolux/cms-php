<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // login, logout, create, update, delete, view, export, etc.
            $table->string('model_type')->nullable(); // App\Models\Post, App\Models\User, etc.
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable(); // Old values for updates
            $table->json('new_values')->nullable(); // New values for creates/updates
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('tags')->nullable(); // Comma-separated tags for filtering (e.g., "security,admin,critical")
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast queries
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
