<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->unsignedBigInteger('featured_image_id')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->integer('view_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('language', 2)->default('de');
            $table->unsignedBigInteger('translation_of_id')->nullable();

            $table->index(['status', 'published_at']);
            $table->index('slug');
            $table->index('featured_image_id');
            $table->index('translation_of_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
