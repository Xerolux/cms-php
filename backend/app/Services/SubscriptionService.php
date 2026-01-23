<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Calculate price for upgrade.
     */
    public function calculateUpgradePrice(Tenant $tenant, string $newPlan): array
    {
        $currentPlan = $tenant->plan;
        $plans = config('tenancy.plans');

        if (!isset($plans[$newPlan])) {
            throw new \InvalidArgumentException("Invalid plan: {$newPlan}");
        }

        $currentPrice = $plans[$currentPlan]['price'] ?? 0;
        $newPrice = $plans[$newPlan]['price'] ?? 0;

        // Calculate prorated amount
        $subscriptionEndsAt = $tenant->subscription_ends_at;
        $daysRemaining = 0;

        if ($subscriptionEndsAt && $subscriptionEndsAt->isFuture()) {
            $daysRemaining = now()->diffInDays($subscriptionEndsAt);
        }

        $proratedAmount = 0;
        if ($daysRemaining > 0) {
            $dailyDifference = ($newPrice - $currentPrice) / 30;
            $proratedAmount = $dailyDifference * $daysRemaining;
        }

        $totalAmount = max(0, $newPrice + $proratedAmount);

        return [
            'current_plan' => $currentPlan,
            'current_price' => $currentPrice,
            'new_plan' => $newPlan,
            'new_price' => $newPrice,
            'days_remaining' => $daysRemaining,
            'prorated_amount' => round($proratedAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'currency' => 'EUR',
        ];
    }

    /**
     * Check if tenant can upgrade to a specific plan.
     */
    public function canUpgradeTo(Tenant $tenant, string $plan): bool
    {
        $plans = config('tenancy.plans');

        if (!isset($plans[$plan])) {
            return false;
        }

        // Cannot downgrade if limits would be exceeded
        $currentPlan = $tenant->plan;
        $currentPlanDetails = $plans[$currentPlan];
        $newPlanDetails = $plans[$plan];

        // Check if this is actually a downgrade
        $planHierarchy = ['free' => 1, 'starter' => 2, 'professional' => 3, 'enterprise' => 4];
        $currentLevel = $planHierarchy[$currentPlan] ?? 0;
        $newLevel = $planHierarchy[$plan] ?? 0;

        if ($newLevel < $currentLevel) {
            // This is a downgrade - check if tenant exceeds new limits
            tenancy()->initialize($tenant);

            $userCount = \App\Models\User::count();
            $postCount = \App\Models\Post::count();

            tenancy()->end();

            $newMaxUsers = $newPlanDetails['max_users'] ?? -1;
            $newMaxPosts = $newPlanDetails['max_posts'] ?? -1;

            if ($newMaxUsers !== -1 && $userCount > $newMaxUsers) {
                return false;
            }

            if ($newMaxPosts !== -1 && $postCount > $newMaxPosts) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get available upgrade options for tenant.
     */
    public function getUpgradeOptions(Tenant $tenant): array
    {
        $plans = config('tenancy.plans');
        $currentPlan = $tenant->plan;
        $planHierarchy = ['free' => 1, 'starter' => 2, 'professional' => 3, 'enterprise' => 4];
        $currentLevel = $planHierarchy[$currentPlan] ?? 0;

        $options = [];

        foreach ($plans as $planKey => $planDetails) {
            $level = $planHierarchy[$planKey] ?? 0;

            // Only show higher-level plans as upgrades
            if ($level > $currentLevel) {
                $pricing = $this->calculateUpgradePrice($tenant, $planKey);

                $options[] = [
                    'plan' => $planKey,
                    'name' => $planDetails['name'],
                    'price' => $planDetails['price'],
                    'currency' => $planDetails['currency'],
                    'interval' => $planDetails['interval'],
                    'upgrade_pricing' => $pricing,
                    'features' => $planDetails['features'],
                ];
            }
        }

        return $options;
    }

    /**
     * Process subscription payment (placeholder for Stripe integration).
     */
    public function processPayment(Tenant $tenant, string $plan, string $paymentMethodId): array
    {
        // Here you would integrate with Stripe or another payment gateway
        // For now, this is a placeholder

        Log::info('Processing subscription payment', [
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'payment_method' => $paymentMethodId,
        ]);

        // Simulate payment processing
        $success = true; // In real implementation, this would call Stripe API

        if (!$success) {
            throw new \Exception('Payment processing failed');
        }

        return [
            'success' => true,
            'transaction_id' => 'txn_' . \Illuminate\Support\Str::random(32),
            'amount' => config("tenancy.plans.{$plan}.price", 0),
            'currency' => 'EUR',
        ];
    }

    /**
     * Create Stripe checkout session (placeholder).
     */
    public function createCheckoutSession(Tenant $tenant, string $plan, string $returnUrl): string
    {
        // Here you would create a Stripe Checkout Session
        // For now, return a placeholder URL

        $checkoutUrl = "https://checkout.stripe.com/pay?" . http_build_query([
            'tenant' => $tenant->id,
            'plan' => $plan,
            'return_url' => $returnUrl,
        ]);

        return $checkoutUrl;
    }

    /**
     * Handle webhook from payment provider (placeholder).
     */
    public function handleWebhook(array $payload): void
    {
        // Here you would handle webhooks from Stripe
        // e.g., payment succeeded, subscription cancelled, etc.

        $eventType = $payload['type'] ?? null;

        switch ($eventType) {
            case 'checkout.session.completed':
                $this->handleCheckoutCompleted($payload['data']['object']);
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($payload['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionCancelled($payload['data']['object']);
                break;

            default:
                Log::info('Unhandled webhook event type', ['type' => $eventType]);
        }
    }

    /**
     * Handle checkout completed event.
     */
    protected function handleCheckoutCompleted(array $data): void
    {
        $tenantId = $data['metadata']['tenant_id'] ?? null;
        $plan = $data['metadata']['plan'] ?? null;

        if ($tenantId && $plan) {
            $tenant = Tenant::find($tenantId);

            if ($tenant) {
                $tenant->update([
                    'plan' => $plan,
                    'is_active' => true,
                    'subscription_ends_at' => now()->addMonth(),
                    'stripe_customer_id' => $data['customer'] ?? null,
                    'stripe_subscription_id' => $data['subscription'] ?? null,
                ]);

                Log::info('Checkout completed', [
                    'tenant_id' => $tenantId,
                    'plan' => $plan,
                ]);
            }
        }
    }

    /**
     * Handle subscription updated event.
     */
    protected function handleSubscriptionUpdated(array $data): void
    {
        // Update tenant subscription details
    }

    /**
     * Handle subscription cancelled event.
     */
    protected function handleSubscriptionCancelled(array $data): void
    {
        $customerId = $data['customer'] ?? null;

        if ($customerId) {
            $tenant = Tenant::where('stripe_customer_id', $customerId)->first();

            if ($tenant) {
                $tenant->update([
                    'is_active' => false,
                ]);

                Log::info('Subscription cancelled', [
                    'tenant_id' => $tenant->id,
                ]);
            }
        }
    }

    /**
     * Get subscription usage statistics.
     */
    public function getUsageStatistics(Tenant $tenant): array
    {
        tenancy()->initialize($tenant);

        $stats = [
            'users' => [
                'current' => \App\Models\User::count(),
                'limit' => $tenant->max_users ?? $tenant->getPlanDetailsAttribute()['max_users'],
            ],
            'posts' => [
                'current' => \App\Models\Post::count(),
                'limit' => $tenant->max_posts ?? $tenant->getPlanDetailsAttribute()['max_posts'],
            ],
            'storage' => [
                'current_gb' => $tenant->storage_usage,
                'limit_gb' => $tenant->max_storage_gb ?? $tenant->getPlanDetailsAttribute()['max_storage_gb'],
            ],
        ];

        tenancy()->end();

        return $stats;
    }

    /**
     * Check if tenant is approaching limits.
     */
    public function checkLimits(Tenant $tenant): array
    {
        $usage = $this->getUsageStatistics($tenant);
        $warnings = [];

        foreach ($usage as $resource => $data) {
            $current = $data['current'];
            $limit = $data['limit'];

            if ($limit === -1) {
                continue; // Unlimited
            }

            $percentage = ($current / $limit) * 100;

            if ($percentage >= 90) {
                $warnings[] = [
                    'resource' => $resource,
                    'severity' => 'critical',
                    'message' => "You have used {$current} of {$limit} {$resource} ({$percentage}%)",
                ];
            } elseif ($percentage >= 75) {
                $warnings[] = [
                    'resource' => $resource,
                    'severity' => 'warning',
                    'message' => "You have used {$current} of {$limit} {$resource} ({$percentage}%)",
                ];
            }
        }

        return [
            'warnings' => $warnings,
            'usage' => $usage,
        ];
    }
}
