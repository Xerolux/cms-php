<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TenantDashboardController extends Controller
{
    /**
     * Get tenant dashboard data.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $stats = $tenant->statistics;

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'plan' => $tenant->plan,
                'plan_details' => $tenant->getPlanDetailsAttribute(),
                'is_active' => $tenant->is_active,
                'is_on_trial' => $tenant->isOnTrial(),
                'has_active_subscription' => $tenant->hasActiveSubscription(),
                'trial_ends_at' => $tenant->trial_ends_at,
                'subscription_ends_at' => $tenant->subscription_ends_at,
                'days_remaining' => $tenant->days_remaining,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Get tenant statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $stats = $tenant->statistics;

        // Add recent activity
        $recentActivity = \App\Models\ActivityLog::with('user')
            ->latest()
            ->take(10)
            ->get();

        // Add posts statistics
        $postsStats = [
            'total' => \App\Models\Post::count(),
            'published' => \App\Models\Post::where('status', 'published')->count(),
            'draft' => \App\Models\Post::where('status', 'draft')->count(),
            'scheduled' => \App\Models\Post::whereNotNull('published_at')
                ->where('published_at', '>', now())
                ->count(),
        ];

        // Add page views (last 30 days)
        $pageViews = \App\Models\PageView::where('created_at', '>=', now()->subDays(30))
            ->count();

        return response()->json([
            'stats' => $stats,
            'posts_stats' => $postsStats,
            'page_views_last_30_days' => $pageViews,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Get tenant analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenant = tenant();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $period = $request->get('period', '30d'); // 7d, 30d, 90d, 1y

        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30),
        };

        // Page views over time
        $pageViewsOverTime = \App\Models\PageView::where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as views')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top posts
        $topPosts = \App\Models\Post::with('analytics')
            ->where('status', 'published')
            ->withCount('views')
            ->orderBy('views_count', 'desc')
            ->take(10)
            ->get();

        // Search queries
        $searchQueries = \App\Models\SearchQuery::where('created_at', '>=', $startDate)
            ->selectRaw('query, COUNT(*) as count')
            ->groupBy('query')
            ->orderBy('count', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'page_views_over_time' => $pageViewsOverTime,
            'top_posts' => $topPosts,
            'search_queries' => $searchQueries,
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => now(),
        ]);
    }
}
