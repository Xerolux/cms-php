<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->string('template')->default('default'); // default, full-width, landing
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_in_menu')->default(false);
            $table->integer('menu_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Index für schneller Zugriff
            $table->index('slug');
            $table->index('is_visible');
            $table->index('is_in_menu');
            $table->index('menu_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
