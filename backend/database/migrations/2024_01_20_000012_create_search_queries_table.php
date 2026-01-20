<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query_text');
            $table->integer('results_count');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('searched_at');

            // Index für Performance
            $table->index('query_text');
            $table->index('searched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
    }
};
