<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('plan')->default('free');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // Plan limits (can override default plan limits)
            $table->integer('max_users')->nullable();
            $table->decimal('max_storage_gb', 8, 2)->nullable();
            $table->integer('max_posts')->nullable();

            // Features enabled for this tenant
            $table->json('features')->nullable();

            // Billing info
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('vat_number')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
    }
};
