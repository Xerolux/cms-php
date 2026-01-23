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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->onDelete('cascade');
            $table->string('event_type');
            $table->json('payload');
            $table->text('response_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->json('headers')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'created_at']);
            $table->index('event_type');
            $table->index('success');
            $table->index('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
