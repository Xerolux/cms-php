<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TenantSubscriptionController extends Controller
{
    /**
     * Get current subscription details.
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $planDetails = $tenant->getPlanDetailsAttribute();

        return response()->json([
            'plan' => $tenant->plan,
            'plan_details' => $planDetails,
            'is_active' => $tenant->is_active,
            'is_on_trial' => $tenant->isOnTrial(),
            'has_active_subscription' => $tenant->hasActiveSubscription(),
            'trial_ends_at' => $tenant->trial_ends_at,
            'subscription_ends_at' => $tenant->subscription_ends_at,
            'days_remaining' => $tenant->days_remaining,
            'stripe_customer_id' => $tenant->stripe_customer_id,
            'stripe_subscription_id' => $tenant->stripe_subscription_id,
        ]);
    }

    /**
     * Get available plans.
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = config('tenancy.plans');

        return response()->json([
            'plans' => $plans,
            'current_plan' => tenant()?->plan,
        ]);
    }

    /**
     * Upgrade subscription.
     */
    public function upgrade(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'plan' => 'required|string|in:starter,professional,enterprise',
            'payment_method_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = $request->input('plan');
        $planDetails = config('tenancy.plans.' . $plan);

        if (!$planDetails) {
            return response()->json(['error' => 'Invalid plan'], 422);
        }

        // Here you would integrate with Stripe/Payment Gateway
        // For now, we'll just update the tenant record

        $tenant->update([
            'plan' => $plan,
            'is_active' => true,
            'subscription_ends_at' => now()->addMonth(),
        ]);

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'subscription_upgraded',
            'model_type' => 'Tenant',
            'model_id' => $tenant->id,
            'description' => "Subscription upgraded to {$plan}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Subscription upgraded successfully',
            'plan' => $plan,
            'plan_details' => $planDetails,
        ]);
    }

    /**
     * Downgrade subscription.
     */
    public function downgrade(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'plan' => 'required|string|in:free,starter',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan = $request->input('plan');
        $planDetails = config('tenancy.plans.' . $plan);

        if (!$planDetails) {
            return response()->json(['error' => 'Invalid plan'], 422);
        }

        // Check if downgrade is possible (e.g., not exceeding new limits)
        $currentPlan = $tenant->plan;
        if ($currentPlan === 'enterprise' && $plan === 'free') {
            $userCount = \App\Models\User::count();
            if ($userCount > 2) {
                return response()->json([
                    'error' => 'Cannot downgrade',
                    'message' => 'You have more users than the free plan allows. Please remove users before downgrading.',
                ], 422);
            }
        }

        $tenant->update([
            'plan' => $plan,
            'subscription_ends_at' => now()->addMonth(),
        ]);

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'subscription_downgraded',
            'model_type' => 'Tenant',
            'model_id' => $tenant->id,
            'description' => "Subscription downgraded to {$plan}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Subscription will be downgraded at the end of the current billing period',
            'plan' => $plan,
            'plan_details' => $planDetails,
            'effective_date' => $tenant->subscription_ends_at,
        ]);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        if ($tenant->isOnTrial()) {
            return response()->json([
                'error' => 'Cannot cancel trial',
                'message' => 'You cannot cancel a trial. It will expire automatically.',
            ], 422);
        }

        $tenant->update([
            'is_active' => false,
        ]);

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'subscription_cancelled',
            'model_type' => 'Tenant',
            'model_id' => $tenant->id,
            'description' => 'Subscription cancelled',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Subscription cancelled successfully',
            'subscription_ends_at' => $tenant->subscription_ends_at,
        ]);
    }

    /**
     * Resume cancelled subscription.
     */
    public function resume(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        if ($tenant->is_active) {
            return response()->json([
                'error' => 'Subscription is already active',
            ], 422);
        }

        $tenant->update([
            'is_active' => true,
        ]);

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'subscription_resumed',
            'model_type' => 'Tenant',
            'model_id' => $tenant->id,
            'description' => 'Subscription resumed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Subscription resumed successfully',
        ]);
    }

    /**
     * Get current usage statistics.
     */
    public function usage(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $stats = $tenant->statistics;
        $planDetails = $tenant->getPlanDetailsAttribute();

        return response()->json([
            'usage' => [
                'users' => [
                    'current' => $stats['users_count'],
                    'limit' => $tenant->max_users ?? $planDetails['max_users'],
                    'percentage' => $this->calculatePercentage(
                        $stats['users_count'],
                        $tenant->max_users ?? $planDetails['max_users']
                    ),
                ],
                'posts' => [
                    'current' => $stats['posts_count'],
                    'limit' => $tenant->max_posts ?? $planDetails['max_posts'],
                    'percentage' => $this->calculatePercentage(
                        $stats['posts_count'],
                        $tenant->max_posts ?? $planDetails['max_posts']
                    ),
                ],
                'storage' => [
                    'current' => $stats['storage_usage'],
                    'limit' => $tenant->max_storage_gb ?? $planDetails['max_storage_gb'],
                    'percentage' => $stats['storage_percentage'],
                ],
            ],
        ]);
    }

    /**
     * Get invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        // Here you would integrate with Stripe to fetch invoices
        // For now, return empty array
        return response()->json([
            'invoices' => [],
            'message' => 'Invoice management requires Stripe integration',
        ]);
    }

    /**
     * Get payment methods.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        // Here you would integrate with Stripe to fetch payment methods
        // For now, return empty array
        return response()->json([
            'payment_methods' => [],
            'message' => 'Payment method management requires Stripe integration',
        ]);
    }

    /**
     * Add payment method.
     */
    public function addPaymentMethod(Request $request): JsonResponse
    {
        // Here you would integrate with Stripe to add payment method
        return response()->json([
            'message' => 'Payment method management requires Stripe integration',
        ]);
    }

    /**
     * Remove payment method.
     */
    public function removePaymentMethod(Request $request, $id): JsonResponse
    {
        // Here you would integrate with Stripe to remove payment method
        return response()->json([
            'message' => 'Payment method management requires Stripe integration',
        ]);
    }

    /**
     * Calculate percentage.
     */
    private function calculatePercentage($current, $limit): float
    {
        if ($limit === -1) return 0; // Unlimited
        if ($limit == 0) return 100;

        return round(($current / $limit) * 100, 2);
    }
}
