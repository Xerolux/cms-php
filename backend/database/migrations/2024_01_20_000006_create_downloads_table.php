<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->unique();
            $table->string('original_filename');
            $table->string('filepath');
            $table->string('mime_type');
            $table->bigInteger('filesize');
            $table->text('description')->nullable();
            $table->enum('access_level', ['public', 'registered', 'premium'])->default('public');
            $table->integer('download_count')->default(0);
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
