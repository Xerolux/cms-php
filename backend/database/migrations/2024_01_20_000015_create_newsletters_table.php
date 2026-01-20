<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->text('content');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('scheduled_at');
            $table->index('created_at');
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('status', ['pending', 'active', 'unsubscribed', 'bounced'])->default('pending');
            $table->string('confirmation_token')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_token')->nullable();
            $table->unsignedInteger('emails_sent')->default(0);
            $table->unsignedInteger('emails_opened')->default(0);
            $table->unsignedInteger('emails_clicked')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ip_address')->nullable();
            $table->string('referrer')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('email');
            $table->index('status');
            $table->index('confirmation_token');
            $table->index('unsubscribe_token');
        });

        Schema::create('newsletter_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscriber_id')->constrained('newsletter_subscribers')->onDelete('cascade');
            $table->timestamp('sent_at');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->string('unsubscribe_token')->nullable();

            $table->unique(['newsletter_id', 'subscriber_id']);
            $table->index('sent_at');
            $table->index('opened_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('newsletter_sent');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('newsletters');
    }
};
