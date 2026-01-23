<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CentralDashboardController extends Controller
{
    public function __construct()
    {
        // Only super admins can access these routes
        $this->middleware('role:super_admin');
    }

    /**
     * Get central dashboard overview.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Central Dashboard',
            'stats' => $this->stats($request)->getData(true),
        ]);
    }

    /**
     * Get platform statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::active()->count();
        $trialTenants = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();
        $inactiveTenants = $totalTenants - $activeTenants;

        // Plan distribution
        $planDistribution = [
            'free' => Tenant::where('plan', 'free')->count(),
            'starter' => Tenant::where('plan', 'starter')->count(),
            'professional' => Tenant::where('plan', 'professional')->count(),
            'enterprise' => Tenant::where('plan', 'enterprise')->count(),
        ];

        // Recent tenants (last 7 days)
        $recentTenants = Tenant::where('created_at', '>=', now()->subDays(7))
            ->count();

        // Tenants expiring soon (next 30 days)
        $expiringSoon = Tenant::whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '>', now())
            ->where('subscription_ends_at', '<=', now()->addDays(30))
            ->count();

        return response()->json([
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'trial_tenants' => $trialTenants,
            'inactive_tenants' => $inactiveTenants,
            'recent_tenants' => $recentTenants,
            'expiring_soon' => $expiringSoon,
            'plan_distribution' => $planDistribution,
        ]);
    }

    /**
     * Get revenue statistics.
     */
    public function revenueStats(Request $request): JsonResponse
    {
        // This would integrate with Stripe/Payment Gateway
        // For now, return estimated revenue based on plans

        $plans = config('tenancy.plans');

        $estimatedMonthlyRevenue = 0;
        $estimatedAnnualRevenue = 0;

        $tenants = Tenant::active()->get();

        foreach ($tenants as $tenant) {
            $plan = $tenant->plan;
            $price = $plans[$plan]['price'] ?? 0;

            $estimatedMonthlyRevenue += $price;
            $estimatedAnnualRevenue += $price * 12;
        }

        return response()->json([
            'estimated_monthly_revenue' => $estimatedMonthlyRevenue,
            'estimated_annual_revenue' => $estimatedAnnualRevenue,
            'currency' => 'EUR',
            'note' => 'Estimated revenue based on plan prices. Actual revenue may vary.',
        ]);
    }

    /**
     * Get tenant growth data.
     */
    public function tenantsGrowth(Request $request): JsonResponse
    {
        $period = $request->get('period', '30d'); // 7d, 30d, 90d, 1y

        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };

        $tenants = Tenant::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => now(),
            'data' => $tenants,
        ]);
    }

    /**
     * Get plan distribution.
     */
    public function planDistribution(Request $request): JsonResponse
    {
        $planDistribution = Tenant::query()
            ->selectRaw('plan, COUNT(*) as count')
            ->groupBy('plan')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->plan => $item->count];
            });

        $plans = config('tenancy.plans');
        $totalTenants = Tenant::count();

        $distribution = [];
        foreach ($plans as $planKey => $planDetails) {
            $count = $planDistribution[$planKey] ?? 0;
            $percentage = $totalTenants > 0 ? round(($count / $totalTenants) * 100, 2) : 0;

            $distribution[$planKey] = [
                'name' => $planDetails['name'],
                'count' => $count,
                'percentage' => $percentage,
                'price' => $planDetails['price'],
            ];
        }

        return response()->json([
            'distribution' => $distribution,
            'total_tenants' => $totalTenants,
        ]);
    }

    /**
     * Get platform statistics overview.
     */
    public function platformStats(Request $request): JsonResponse
    {
        return response()->json([
            'stats' => $this->stats($request)->getData(true),
            'revenue' => $this->revenueStats($request)->getData(true),
            'growth' => $this->tenantsGrowth($request)->getData(true),
            'plan_distribution' => $this->planDistribution($request)->getData(true),
        ]);
    }
}
