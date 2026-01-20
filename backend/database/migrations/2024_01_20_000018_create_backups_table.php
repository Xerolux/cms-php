<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // full, files, database
            $table->string('status'); // pending, creating, completed, failed
            $table->string('disk')->default('local'); // local, s3, etc.
            $table->string('path'); // Storage path
            $table->unsignedBigInteger('file_size')->nullable();
            $table->integer('items_count')->default(0); // Number of files/tables
            $table->text('description')->nullable();
            $table->json('options')->nullable(); // Backup options
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('backups');
    }
};
