<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
            $table->string('platform'); // facebook, twitter, linkedin, whatsapp, email
            $table->string('share_url')->nullable();
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->timestamp('shared_at')->nullable();
            $table->foreignId('shared_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['post_id', 'platform']);
            $table->index('shared_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_shares');
    }
};
